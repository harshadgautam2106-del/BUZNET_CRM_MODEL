<?php
declare(strict_types=1);

function create_business_backup(PDO $pdo, int $businessId): array {
    $snapshot = [
        'format' => 'buznet-crm-json-backup', 'version' => 1, 'created_at' => date(DATE_ATOM),
        'business' => null, 'users' => [], 'user_permissions' => [], 'customers' => [], 'leads' => [],
        'products' => [], 'tasks' => [], 'invoices' => [], 'invoice_items' => [], 'payments' => [],
        'expenses' => [], 'quotations' => [], 'quotation_items' => [], 'activity_logs' => [],
    ];
    $query = $pdo->prepare('SELECT * FROM businesses WHERE id=?');
    $query->execute([$businessId]); $snapshot['business'] = $query->fetch();
    $query = $pdo->prepare('SELECT id,business_id,name,email,role,active,last_login_at,created_at FROM users WHERE business_id=? ORDER BY id');
    $query->execute([$businessId]); $snapshot['users'] = $query->fetchAll();
    $userIds = array_map('intval', array_column($snapshot['users'], 'id'));
    $idList = $userIds ? implode(',', $userIds) : '0';
    $snapshot['user_permissions'] = $pdo->query("SELECT user_id,permission_key,granted FROM user_permissions WHERE user_id IN ($idList) ORDER BY user_id,permission_key")->fetchAll();
    foreach (['customers','leads','products','tasks','invoices','payments','expenses','quotations'] as $table) {
        $query = $pdo->prepare("SELECT * FROM `$table` WHERE business_id=? ORDER BY id");
        $query->execute([$businessId]); $snapshot[$table] = $query->fetchAll();
    }
    $invoiceIds = array_map('intval', array_column($snapshot['invoices'], 'id'));
    $quoteIds = array_map('intval', array_column($snapshot['quotations'], 'id'));
    $snapshot['invoice_items'] = $invoiceIds ? $pdo->query('SELECT * FROM invoice_items WHERE invoice_id IN (' . implode(',', $invoiceIds) . ') ORDER BY id')->fetchAll() : [];
    $snapshot['quotation_items'] = $quoteIds ? $pdo->query('SELECT * FROM quotation_items WHERE quotation_id IN (' . implode(',', $quoteIds) . ') ORDER BY id')->fetchAll() : [];
    $snapshot['activity_logs'] = $pdo->query("SELECT id,user_id,event,detail,ip_address,created_at FROM activity_logs WHERE user_id IN ($idList) ORDER BY id")->fetchAll();
    return $snapshot;
}

function encode_business_backup(PDO $pdo, int $businessId): string {
    $json = json_encode(create_business_backup($pdo, $businessId), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) throw new RuntimeException('Business data could not be encoded as a backup.');
    return $json;
}
