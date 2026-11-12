<?php
require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Config/database.php';

$db = getDB();
$sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
$stmt = $db->prepare($sql);
$stmt->execute(['ai_assistant_enabled', '1']);
$stmt->execute(['ai_provider', 'builtin']);
$stmt->execute(['ai_api_key', '']);
$stmt->execute(['ai_welcome_message', 'Hai! Selamat datang di nnasa_ca. Aku NNasa AI, asisten virtual resmi yang siap membantu kamu mencari aksesoris, mengecek harga dan ketersediaan produk, serta menjawab pertanyaan tentang toko kami. Ada yang bisa aku bantu?']);

echo "AI Settings successfully seeded into nnasa_ca_db settings table.\n";
