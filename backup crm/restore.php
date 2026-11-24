<?php
declare(strict_types=1);
require __DIR__ . '/includes/module.php';
require __DIR__ . '/services/backup-service.php';
$owner = require_owner();
$businessId = (int)$owner['business_id'];
$pdo = db();
$error = '';
$preview = null;
$previewPath = null;
$previewKey = $_SESSION['restore_preview_key'] ?? '';
$action = (string)($_POST['action'] ?? '');

$validateBackup = static function (array $data) use ($businessId): array {
    $required = ['business','users','customers','leads','products','tasks','invoices','invoice_items','payments','expenses','quotations','quotation_items'];
    if (($data['format'] ?? '') !== 'buznet-crm-json-backup' || (int)($data['version'] ?? 0) !== 1) throw new RuntimeException('Unsupported backup file format or version.');
    if (!is_array($data['business'] ?? null) || (int)($data['business']['id'] ?? 0) !== $businessId) throw new RuntimeException('This backup belongs to a different workspace. Restore requires the same business ID.');
    foreach ($required as $key) if (!is_array($data[$key] ?? null)) throw new RuntimeException('The backup is incomplete: missing ' . $key . '.');
    foreach ($required as $key) if (count($data[$key]) > 100000) throw new RuntimeException('The backup contains too many rows to restore safely.');
    return $data;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($action === 'preview') {
        try {
            if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Choose a backup JSON file to continue.');
            if ((int)$_FILES['backup_file']['size'] < 2 || (int)$_FILES['backup_file']['size'] > 25 * 1024 * 1024) throw new RuntimeException('Backup files must be 25 MB or smaller.');
            $raw = file_get_contents($_FILES['backup_file']['tmp_name']);
            $decoded = json_decode((string)$raw, true, 512, JSON_THROW_ON_ERROR);
            $preview = $validateBackup($decoded);
            $dir = __DIR__ . '/storage/restore-previews';
            if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('Private restore storage is unavailable.');
            $previewKey = bin2hex(random_bytes(24));
            $previewPath = $dir . '/' . $previewKey . '.json';
            if (file_put_contents($previewPath, $raw, LOCK_EX) === false) throw new RuntimeException('The backup preview could not be saved safely.');
            $_SESSION['restore_preview_key'] = $previewKey;
            $_SESSION['restore_preview_expires'] = time() + 900;
        } catch (Throwable $exception) {
            $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Backup file could not be validated.';
        }
    } elseif ($action === 'restore') {
        $previewKey = (string)($_SESSION['restore_preview_key'] ?? '');
        $previewExpires = (int)($_SESSION['restore_preview_expires'] ?? 0);
        if (!preg_match('/^[a-f0-9]{48}$/', $previewKey) || $previewExpires < time()) {
            $error = 'The preview expired. Upload and preview the backup again.';
        } else {
            $previewPath = __DIR__ . '/storage/restore-previews/' . $previewKey . '.json';
            try {
                $passwordQuery = $pdo->prepare('SELECT password_hash FROM users WHERE id=? AND business_id=? AND role=\'owner\' AND active=1');
                $passwordQuery->execute([$owner['id'], $businessId]);
                if (!password_verify((string)($_POST['current_password'] ?? ''), (string)$passwordQuery->fetchColumn())) throw new RuntimeException('Current Owner password is incorrect.');
                if ((string)($_POST['confirmation'] ?? '') !== 'RESTORE') throw new RuntimeException('Type RESTORE exactly to confirm replacement of current business records.');
                $raw = file_get_contents($previewPath);
                if ($raw === false) throw new RuntimeException('The preview file is missing. Upload the backup again.');
                $data = $validateBackup(json_decode($raw, true, 512, JSON_THROW_ON_ERROR));
                $backupDir = __DIR__ . '/storage/backups';
                if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true) && !is_dir($backupDir)) throw new RuntimeException('Private recovery storage is unavailable.');
                $recoveryPath = $backupDir . '/pre-restore-' . $businessId . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
                $recoveryJson = encode_business_backup($pdo, $businessId);
                if (file_put_contents($recoveryPath, $recoveryJson, LOCK_EX) === false) throw new RuntimeException('Could not create the required pre-restore recovery backup. No records were changed.');

                $pdo->beginTransaction();
                if (is_array($data['business'])) {
                    $businessColumns = $pdo->query('SHOW COLUMNS FROM businesses')->fetchAll(PDO::FETCH_COLUMN);
                    $profile = array_intersect_key($data['business'], array_flip($businessColumns));
                    unset($profile['id'], $profile['created_at']);
                    if ($profile) {
                        $sets = [];
                        foreach (array_keys($profile) as $column) {
                            if (!preg_match('/^[a-z_]+$/i', $column)) throw new RuntimeException('Backup contains an invalid business field.');
                            $sets[] = '`' . $column . '`=?';
                        }
                        $pdo->prepare('UPDATE businesses SET ' . implode(',', $sets) . ' WHERE id=?')->execute([...array_values($profile), $businessId]);
                    }
                }
                $pdo->exec('DELETE FROM quotation_items WHERE quotation_id IN (SELECT id FROM quotations WHERE business_id=' . $businessId . ')');
                $pdo->exec('DELETE FROM quotations WHERE business_id=' . $businessId);
                $pdo->exec('DELETE FROM invoice_items WHERE invoice_id IN (SELECT id FROM invoices WHERE business_id=' . $businessId . ')');
                $pdo->exec('DELETE FROM payments WHERE business_id=' . $businessId);
                $pdo->exec('DELETE FROM invoices WHERE business_id=' . $businessId);
                $pdo->exec('DELETE FROM tasks WHERE business_id=' . $businessId);
                $pdo->exec('DELETE FROM leads WHERE business_id=' . $businessId);
                $pdo->exec('DELETE FROM expenses WHERE business_id=' . $businessId);
                $pdo->exec('DELETE FROM products WHERE business_id=' . $businessId);
                $pdo->exec('DELETE FROM customers WHERE business_id=' . $businessId);
                $maps = ['customers'=>[], 'leads'=>[], 'products'=>[], 'tasks'=>[], 'invoices'=>[], 'quotations'=>[]];
                $insertRows = static function (string $table, array $rows, callable $transform) use ($pdo, &$maps): void {
                    if (!$rows) return;
                    $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($rows as $row) {
                        if (!is_array($row)) throw new RuntimeException('A backup record is malformed.');
                        $oldId = (int)($row['id'] ?? 0);
                        $row = $transform($row);
                        unset($row['id']);
                        $row = array_intersect_key($row, array_flip($columns));
                        if (!$row) continue;
                        $names = array_keys($row);
                        foreach ($names as $name) if (!preg_match('/^[a-z_]+$/i', $name)) throw new RuntimeException('Backup contains an invalid field name.');
                        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $names) . '`) VALUES (' . implode(',', array_fill(0, count($names), '?')) . ')';
                        $statement = $pdo->prepare($sql);
                        $statement->execute(array_values($row));
                        if ($oldId > 0 && array_key_exists($table, $maps)) $maps[$table][$oldId] = (int)$pdo->lastInsertId();
                    }
                };
                $withBusiness = static fn(array $row): array => array_merge($row, ['business_id'=>$businessId]);
                $insertRows('customers', $data['customers'], $withBusiness);
                $insertRows('products', $data['products'], $withBusiness);
                $insertRows('leads', $data['leads'], static function(array $row) use ($withBusiness, &$maps): array { $row['converted_customer_id'] = $maps['customers'][(int)($row['converted_customer_id'] ?? 0)] ?? null; return $withBusiness($row); });
                $insertRows('invoices', $data['invoices'], static function(array $row) use ($withBusiness, &$maps): array { $row['customer_id'] = $maps['customers'][(int)($row['customer_id'] ?? 0)] ?? 0; return $withBusiness($row); });
                $insertRows('invoice_items', $data['invoice_items'], static function(array $row) use (&$maps): array { $row['invoice_id'] = $maps['invoices'][(int)($row['invoice_id'] ?? 0)] ?? 0; $row['product_id'] = $maps['products'][(int)($row['product_id'] ?? 0)] ?? null; return $row; });
                $insertRows('quotations', $data['quotations'], static function(array $row) use ($withBusiness, &$maps): array { $row['customer_id'] = $maps['customers'][(int)($row['customer_id'] ?? 0)] ?? 0; $row['invoice_id'] = $maps['invoices'][(int)($row['invoice_id'] ?? 0)] ?? null; return $withBusiness($row); });
                $insertRows('quotation_items', $data['quotation_items'], static function(array $row) use (&$maps): array { $row['quotation_id'] = $maps['quotations'][(int)($row['quotation_id'] ?? 0)] ?? 0; return $row; });
                $insertRows('tasks', $data['tasks'], static function(array $row) use ($withBusiness, &$maps): array { $row['customer_id'] = $maps['customers'][(int)($row['customer_id'] ?? 0)] ?? null; return $withBusiness($row); });
                $insertRows('payments', $data['payments'], static function(array $row) use ($withBusiness, &$maps): array { $row['customer_id'] = $maps['customers'][(int)($row['customer_id'] ?? 0)] ?? 0; $row['invoice_id'] = $maps['invoices'][(int)($row['invoice_id'] ?? 0)] ?? 0; return $withBusiness($row); });
                $insertRows('expenses', $data['expenses'], $withBusiness);
                $pdo->commit();
                @unlink($previewPath);
                unset($_SESSION['restore_preview_key'], $_SESSION['restore_preview_expires']);
                audit('backup.restore', 'Restored business data; recovery file ' . basename($recoveryPath));
                flash('Workspace restored. A pre-restore recovery copy was saved privately.');
                redirect('settings.php');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Restore failed. Current records were rolled back where possible.';
            }
        }
    }
}

if (!$preview && $previewKey && (int)($_SESSION['restore_preview_expires'] ?? 0) >= time()) {
    $previewPath = __DIR__ . '/storage/restore-previews/' . $previewKey . '.json';
    if (is_file($previewPath)) { try { $preview = $validateBackup(json_decode((string)file_get_contents($previewPath), true, 512, JSON_THROW_ON_ERROR)); } catch (Throwable $exception) { $error = 'The saved preview is no longer valid. Upload again.'; } }
}
page_start('Restore backup', 'settings');
?>
<style>.restore-card{max-width:760px;background:#fff;border:1px solid #edf0f2;border-radius:9px;padding:19px}.restore-card h2{font:650 14px var(--display);margin:0 0 5px}.restore-card p{font-size:10px;color:#86919d}.restore-table{width:100%;border-collapse:collapse;margin:12px 0}.restore-table td{padding:8px;border-bottom:1px solid #edf0f2;font-size:10px}.restore-warning{background:#fff4e6;color:#9b6a1f;border-radius:7px;padding:12px;font-size:10px;margin:12px 0}.restore-card input[type=file],.restore-card input[type=password],.restore-card input[type=text]{display:block;margin-top:6px;max-width:100%;height:38px;border:1px solid #e5e9ed;border-radius:6px;padding:8px}</style>
<div class="section-head"><div><span class="eyebrow">DATA MANAGEMENT</span><h1>Restore backup</h1><p>Restore a BUZNET JSON backup into this workspace.</p></div><a class="button secondary" href="settings.php">Back to settings</a></div>
<?php if($error):?><div class="flash error"><?=e($error)?></div><?php endif?>
<section class="restore-card"><h2>1. Validate backup</h2><p>Only restore a backup created for this same workspace. Restore replaces operational records. Users and login credentials are kept as they are now.</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="preview"><label>Backup JSON file<input type="file" name="backup_file" accept="application/json,.json" required></label><button class="button secondary" style="margin-top:12px">Preview backup</button></form>
<?php if($preview):?><hr><h2>2. Review restore</h2><p>Workspace: <strong><?=e($preview['business']['name']??'')?></strong> · Created: <?=e($preview['created_at']??'Unknown')?> · Format version <?=e($preview['version'])?></p><table class="restore-table"><tbody><?php foreach(['customers','leads','products','tasks','invoices','invoice_items','payments','expenses','quotations','quotation_items'] as $table):?><tr><td><?=e(ucwords(str_replace('_',' ',$table)))?></td><td><?=count($preview[$table])?> records</td></tr><?php endforeach?></tbody></table><div class="restore-warning">This replaces current customers, leads, catalog, tasks, invoices, payments, expenses and quotations. A private pre-restore recovery backup is created first. The operation can still fail if the uploaded file contains broken relationships.</div><h2>3. Confirm restore</h2><form method="post" class="stack-form"><input type="hidden" name="_csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="restore"><label>Current Owner password<input type="password" name="current_password" autocomplete="current-password" required></label><label>Type RESTORE to confirm<input type="text" name="confirmation" required autocomplete="off"></label><button class="button primary">Create recovery copy and restore</button></form><?php endif?></section>
<?php page_end();
