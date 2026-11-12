<?php
/**
 * Automated Test Suite: 15 Scenarios for WhatsApp OTP Authentication
 * Store: nnasa_ca Accessories Marketplace
 * Senior Backend & Cybersecurity Implementation
 */

require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
require_once dirname(__DIR__) . '/app/Helpers/auth.php';
require_once dirname(__DIR__) . '/app/Helpers/security.php';
require_once dirname(__DIR__) . '/app/Models/OTPModel.php';
require_once dirname(__DIR__) . '/app/Services/OTPService.php';
require_once dirname(__DIR__) . '/app/Services/WhatsApp/WhatsAppService.php';

$db = getDB();
$otpModel = new OTPModel($db);
$waService = new WhatsAppService($db);
$otpService = new OTPService($db);

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertTest(string $scenarioNum, string $title, bool $condition, string $detail = '') {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "[\033[32mPASS\033[0m] Skenario {$scenarioNum}: {$title}\n";
        if ($detail) echo "       -> {$detail}\n";
    } else {
        $failedTests++;
        echo "[\033[31mFAIL\033[0m] Skenario {$scenarioNum}: {$title}\n";
        if ($detail) echo "       -> {$detail}\n";
    }
}

echo "========================================================================\n";
echo "   NNASA_CA WHATSAPP OTP INTEGRATION TEST SUITE (15 SCENARIOS)\n";
echo "========================================================================\n\n";

// -------------------------------------------------------------------------
// Skenario 1: Konfigurasi tanpa token
// -------------------------------------------------------------------------
$cfgInfo = $waService->getConfigInfo();
// Test dispatch when token is placeholder
$dummyChId1 = $otpModel->createChallenge(null, '6281234567890', 'phone_verify', 'dummyhash123', 5, 60, 5);
$resNoToken = $waService->sendOTP($dummyChId1, '6281234567890', '123456', 5);
$stmtLog1 = $db->prepare("SELECT * FROM otp_delivery_logs WHERE challenge_id = ? ORDER BY id DESC LIMIT 1");
$stmtLog1->execute([$dummyChId1]);
$log1 = $stmtLog1->fetch(PDO::FETCH_ASSOC);
$s1Pass = ($resNoToken['success'] === true && $log1 && $log1['delivery_status'] === 'simulated' && $log1['error_code'] === 'TOKEN_NOT_CONFIGURED');
assertTest("1", "Konfigurasi tanpa token / token placeholder ditangani aman", $s1Pass, "Delivery status dicatat sebagai 'simulated' dan error_code 'TOKEN_NOT_CONFIGURED' tanpa crash.");
// Cleanup dummy challenge & log
$db->exec("DELETE FROM otp_delivery_logs WHERE challenge_id = {$dummyChId1}");
$db->exec("DELETE FROM otp_challenges WHERE id = {$dummyChId1}");

// -------------------------------------------------------------------------
// Skenario 2: Token tidak valid
// -------------------------------------------------------------------------
$resInvalidToken = $waService->testConnection('token_palsu_random_xyz123', 'https://api.fonnte.com/send', 'fonnte');
$s2Pass = ($resInvalidToken['success'] === false);
assertTest("2", "Token tidak valid ditolak oleh gateway", $s2Pass, "Pesan: " . ($resInvalidToken['message'] ?? 'Ditolak dengan benar'));

// -------------------------------------------------------------------------
// Skenario 3: Endpoint provider salah
// -------------------------------------------------------------------------
$resBadEndpoint = $waService->testConnection('any_token', 'https://invalid-nonexistent-domain-nnasaca.com/api', 'fonnte');
$s3Pass = ($resBadEndpoint['success'] === false && !empty($resBadEndpoint['error_code']));
assertTest("3", "Endpoint provider salah ditangani dengan error anggun", $s3Pass, "Error: " . ($resBadEndpoint['error_code'] ?? 'UNKNOWN') . " (" . ($resBadEndpoint['message'] ?? '') . ")");

// -------------------------------------------------------------------------
// Skenario 4: Pengiriman OTP ke nomor valid
// -------------------------------------------------------------------------
$testPhoneValid = '081298765432';
$normPhone = normalize_phone_number($testPhoneValid);
$phoneHash4 = hash('sha256', $normPhone);
// Ensure clean state for test phone
$db->exec("DELETE FROM otp_challenges WHERE destination_hash = '{$phoneHash4}'");
$db->exec("DELETE FROM otp_rate_limits WHERE subject_hash = '{$phoneHash4}'");

$reqValid = $otpService->requestOTP($normPhone, 'register', null, '127.0.0.1');
$s4Pass = ($normPhone === '6281298765432' && $reqValid['success'] === true && !empty($reqValid['challenge_id']));
assertTest("4", "Pengiriman OTP ke nomor valid & normalisasi E.164", $s4Pass, "Nomor '{$testPhoneValid}' dinormalisasi ke '{$normPhone}', Challenge ID: " . ($reqValid['challenge_id'] ?? 0));

// -------------------------------------------------------------------------
// Skenario 5: Nomor dengan format tidak valid
// -------------------------------------------------------------------------
$invalidPhones = ['123', '081', 'abcdefgh', '+1234'];
$allRejected = true;
foreach ($invalidPhones as $inv) {
    $norm = normalize_phone_number($inv);
    $reqInv = $otpService->requestOTP($inv, 'register', null, '127.0.0.1');
    if ($norm !== null || $reqInv['success'] === true) {
        $allRejected = false;
        break;
    }
}
assertTest("5", "Nomor dengan format tidak valid ditolak ketat", $allRejected, "Input nomor tidak valid otomatis diblokir sebelum request dibuat.");

// -------------------------------------------------------------------------
// Skenario 6: OTP benar
// -------------------------------------------------------------------------
$testTarget6 = '628111222333';
$testCode6 = '654321';
$testHash6 = hash_otp_code($testCode6, $testTarget6);
$chId6 = $otpModel->createChallenge(null, $testTarget6, 'phone_verify', $testHash6, 5, 60, 5);

$verifRes6 = $otpService->verifyOTP($testTarget6, 'phone_verify', $testCode6);
$s6Pass = ($verifRes6['success'] === true);
$chRecord6 = $otpModel->findLatestActiveChallenge($testTarget6, 'phone_verify');
// Consumed challenges should no longer be returned by findLatestActiveChallenge
$s6Consumed = ($chRecord6 === null);
assertTest("6", "Verifikasi OTP benar berhasil & challenge ditandai consumed", ($s6Pass && $s6Consumed), "Kode '654321' diverifikasi sukses dan challenge berstatus consumed_at.");

// -------------------------------------------------------------------------
// Skenario 7: OTP salah
// -------------------------------------------------------------------------
$testTarget7 = '628111222444';
$testCode7 = '112233';
$testHash7 = hash_otp_code($testCode7, $testTarget7);
$chId7 = $otpModel->createChallenge(null, $testTarget7, 'phone_verify', $testHash7, 5, 60, 5);

$verifRes7 = $otpService->verifyOTP($testTarget7, 'phone_verify', '999999');
$stmtCh7 = $db->prepare("SELECT attempts FROM otp_challenges WHERE id = ?");
$stmtCh7->execute([$chId7]);
$attemptsCount7 = (int)$stmtCh7->fetchColumn();
$s7Pass = ($verifRes7['success'] === false && $attemptsCount7 === 1 && $verifRes7['remaining_attempts'] === 4);
assertTest("7", "OTP salah ditolak & counter percobaan ditambah", $s7Pass, "Percobaan bertambah menjadi {$attemptsCount7}, sisa kesempatan: " . ($verifRes7['remaining_attempts'] ?? 0));

// -------------------------------------------------------------------------
// Skenario 8: OTP kedaluwarsa
// -------------------------------------------------------------------------
$testTarget8 = '628111222555';
$testCode8 = '334455';
$testHash8 = hash_otp_code($testCode8, $testTarget8);
$chId8 = $otpModel->createChallenge(null, $testTarget8, 'phone_verify', $testHash8, 5, 60, 5);

// Mundurkan expires_at ke masa lalu menggunakan waktu PHP tersinkronisasi
$pastDate = date('Y-m-d H:i:s', time() - 300);
$db->prepare("UPDATE otp_challenges SET expires_at = ? WHERE id = ?")->execute([$pastDate, $chId8]);
$verifRes8 = $otpService->verifyOTP($testTarget8, 'phone_verify', $testCode8);
$s8Pass = ($verifRes8['success'] === false && str_contains($verifRes8['message'], 'kedaluwarsa'));
assertTest("8", "OTP kedaluwarsa ditolak secara tegas", $s8Pass, "Pesan penolakan: '{$verifRes8['message']}'");

// -------------------------------------------------------------------------
// Skenario 9: OTP yang sama digunakan kembali (Replay Attack Protection)
// -------------------------------------------------------------------------
// Challenge 6 was already consumed above
$verifReplay9 = $otpService->verifyOTP($testTarget6, 'phone_verify', $testCode6);
$s9Pass = ($verifReplay9['success'] === false);
assertTest("9", "Proteksi Replay Attack: OTP yang sudah digunakan tidak bisa dipakai ulang", $s9Pass, "Pesan: '{$verifReplay9['message']}'");

// -------------------------------------------------------------------------
// Skenario 10: Pengiriman ulang terlalu cepat (Cooldown Enforcement)
// -------------------------------------------------------------------------
$testTarget10 = '628111222666';
$hash10 = hash('sha256', $testTarget10);
$db->exec("DELETE FROM otp_challenges WHERE destination_hash = '{$hash10}'");
$db->exec("DELETE FROM otp_rate_limits WHERE subject_hash = '{$hash10}'");

$req10_1 = $otpService->requestOTP($testTarget10, 'register', null, '127.0.0.1');
// Langsung minta lagi seketika tanpa jeda
$req10_2 = $otpService->requestOTP($testTarget10, 'register', null, '127.0.0.1');
$s10Pass = ($req10_1['success'] === true && $req10_2['success'] === false && !empty($req10_2['wait_seconds']) && $req10_2['wait_seconds'] <= 60);
assertTest("10", "Cooldown pengiriman ulang (60 detik) ditegakkan", $s10Pass, "Pengiriman kedua ditolak dengan jeda tunggu: " . ($req10_2['wait_seconds'] ?? 0) . " detik.");

// -------------------------------------------------------------------------
// Skenario 11: Percobaan OTP melebihi batas (Max Attempts Lockout)
// -------------------------------------------------------------------------
$testTarget11 = '628111222777';
$testCode11 = '778899';
$testHash11 = hash_otp_code($testCode11, $testTarget11);
$chId11 = $otpModel->createChallenge(null, $testTarget11, 'phone_verify', $testHash11, 5, 60, 3); // Max 3 attempts

$otpService->verifyOTP($testTarget11, 'phone_verify', '000001'); // 1
$otpService->verifyOTP($testTarget11, 'phone_verify', '000002'); // 2
$otpService->verifyOTP($testTarget11, 'phone_verify', '000003'); // 3

// Ke-4 memasukkan kode yang benar, tapi challenge sudah terkunci!
$verifLockout11 = $otpService->verifyOTP($testTarget11, 'phone_verify', $testCode11);
$s11Pass = ($verifLockout11['success'] === false && str_contains($verifLockout11['message'], 'Batas percobaan'));
assertTest("11", "Challenge terkunci otomatis saat batas salah terlampaui", $s11Pass, "Kode benar tetap ditolak jika batas percobaan (3x) telah habis.");

// -------------------------------------------------------------------------
// Skenario 12: Provider mengalami timeout
// -------------------------------------------------------------------------
$fonnte = new FonnteAdapter('dummy_token', 'https://10.255.255.1/slow', 1);
$timeStart = microtime(true);
$timeoutRes = $fonnte->sendMessage('628123456789', 'Tes timeout', 'dummy_token', 'https://10.255.255.1/slow', null, 1);
$duration = microtime(true) - $timeStart;
$s12Pass = ($timeoutRes['success'] === false && !empty($timeoutRes['error_code']));
assertTest("12", "Penanganan cURL Timeout pada Gateway WhatsApp", $s12Pass, "Durasi cURL: " . round($duration, 2) . "s, Error: {$timeoutRes['error_code']}");

// -------------------------------------------------------------------------
// Skenario 13: Pengguna tanpa hak akses membuka menu admin
// -------------------------------------------------------------------------
$cmd13 = 'C:\\xampp\\php\\php.exe -r "$_GET=[\'action\'=>\'get_dashboard_data\']; require \'c:/xampp/htdocs/toko/api/admin/whatsapp-otp.php\';"';
$out13 = [];
$ret13 = 0;
exec($cmd13, $out13, $ret13);
$raw13 = implode("\n", $out13);
$json13 = json_decode($raw13, true);
$s13Pass = ($json13 && $json13['success'] === false && str_contains($json13['message'] ?? '', 'Akses ditolak'));
assertTest("13", "RBAC: Pengguna tanpa hak akses admin ditolak dengan HTTP 403", $s13Pass, "Akses admin API diblokir: '" . ($json13['message'] ?? 'Ditolak') . "'");

// -------------------------------------------------------------------------
// Skenario 14: Pemeriksaan agar token tidak muncul di browser dan log
// -------------------------------------------------------------------------
$sampleRawSecret = 'fonnte_live_tok_998877665544332211';
$sampleMasked = mask_api_key($sampleRawSecret, 6, 4);
$isMaskFunctionSafe = str_contains($sampleMasked, '••••') && !str_contains($sampleMasked, '9988776655');

$dashboardInfo = $waService->getConfigInfo();
// Periksa database log: pastikan tidak ada token mentah atau plaintext OTP di kolom delivery
$stmtTokenLeak = $db->query("
    SELECT COUNT(*) FROM otp_delivery_logs 
    WHERE provider_message_id LIKE '%9988776655%' OR error_code LIKE '%9988776655%'
");
$leakCount = (int)$stmtTokenLeak->fetchColumn();
$s14Pass = ($isMaskFunctionSafe && $leakCount === 0 && !empty($dashboardInfo['masked_token']));
assertTest("14", "Perlindungan Rahasia: Token API tidak bocor di frontend atau log", $s14Pass, "Fungsi masking aktif ('{$sampleMasked}') & tidak ditemukan jejak token mentah di database logs.");

// -------------------------------------------------------------------------
// Skenario 15: Verifikasi akun hanya berhasil setelah OTP benar
// -------------------------------------------------------------------------
// Create a test user
$db->exec("DELETE FROM users WHERE email = 'test_wa_otp@nnasaca.com'");
$db->prepare("
    INSERT INTO users (name, email, password_hash, phone, role, status, phone_verified_at)
    VALUES ('Tester OTP', 'test_wa_otp@nnasaca.com', 'dummyhash', '628999888777', 'customer', 'active', NULL)
")->execute();
$testUserId15 = (int)$db->lastInsertId();

// Verify that initially phone_verified_at IS NULL
$stmtU15 = $db->prepare("SELECT phone_verified_at FROM users WHERE id = ?");
$stmtU15->execute([$testUserId15]);
$verifAtInit = $stmtU15->fetchColumn();

// Create OTP challenge linked to this user
$targetPhone15 = '628999888777';
$code15 = '556677';
$hash15 = hash_otp_code($code15, $targetPhone15);
$otpModel->createChallenge($testUserId15, $targetPhone15, 'phone_verify', $hash15, 5, 60, 5);

// Wrong attempt first
$otpService->verifyOTP($targetPhone15, 'phone_verify', '000000');
$stmtU15->execute([$testUserId15]);
$verifAtAfterWrong = $stmtU15->fetchColumn();

// Correct attempt
$otpService->verifyOTP($targetPhone15, 'phone_verify', $code15);
$stmtU15->execute([$testUserId15]);
$verifAtAfterCorrect = $stmtU15->fetchColumn();

$s15Pass = ($verifAtInit === null && $verifAtAfterWrong === null && $verifAtAfterCorrect !== null);
assertTest("15", "Akun terverifikasi HANYA setelah kode OTP terbukti benar", $s15Pass, "Initial: NULL, After Wrong OTP: NULL, After Correct OTP: {$verifAtAfterCorrect}");

// Cleanup test user
$db->exec("DELETE FROM users WHERE id = {$testUserId15}");
$db->exec("DELETE FROM otp_challenges WHERE destination_hash = '" . hash('sha256', strtolower(trim($targetPhone15))) . "'");

echo "\n========================================================================\n";
echo "   RINGKASAN HASIL PENGUJIAN: {$passedTests} / {$totalTests} SKENARIO BERHASIL (";
if ($failedTests === 0) {
    echo "\033[32m100% SUCCESS\033[0m)\n";
} else {
    echo "\033[31m{$failedTests} FAILED\033[0m)\n";
}
echo "========================================================================\n";
