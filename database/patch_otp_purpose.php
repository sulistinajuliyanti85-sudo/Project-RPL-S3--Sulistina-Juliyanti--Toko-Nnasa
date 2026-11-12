<?php
require_once dirname(__DIR__) . '/app/Config/database.php';
$db = getDB();
$db->exec("ALTER TABLE otp_challenges MODIFY `purpose` VARCHAR(50) NOT NULL");
$db->exec("ALTER TABLE otp_rate_limits MODIFY `purpose` VARCHAR(50) NOT NULL");
echo "OK: otp_challenges and otp_rate_limits purpose modified to VARCHAR(50)\n";
