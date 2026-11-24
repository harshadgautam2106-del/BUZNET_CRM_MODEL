<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
require __DIR__ . '/services/backup-service.php';
$owner = require_owner();
$businessId = (int)$owner['business_id'];
try { $json = encode_business_backup(db(), $businessId); }
catch (Throwable $exception) { http_response_code(500); exit('Backup export could not be created.'); }
audit('backup.download', 'Business data JSON export');
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="buznet-backup-' . $businessId . '-' . date('Ymd-His') . '.json"');
header('Cache-Control: no-store, private');
echo $json;
