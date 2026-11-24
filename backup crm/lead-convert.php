<?php
declare(strict_types=1);
require __DIR__ . '/includes/module.php';
$user = require_auth();
require_permission('leads.edit');
$businessId = (int)$user['business_id'];
$leadId = (int)($_GET['id'] ?? $_POST['lead_id'] ?? 0);
$pdo = db();
$error = '';

$leadQuery = $pdo->prepare('SELECT * FROM leads WHERE id=? AND business_id=?');
$leadQuery->execute([$leadId, $businessId]);
$lead = $leadQuery->fetch();
if (!$lead) { http_response_code(404); exit('Lead not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $pdo->beginTransaction();
        $lockedQuery = $pdo->prepare('SELECT * FROM leads WHERE id=? AND business_id=? FOR UPDATE');
        $lockedQuery->execute([$leadId, $businessId]);
        $lockedLead = $lockedQuery->fetch();
        if (!$lockedLead || $lockedLead['status'] !== 'Won') {
            throw new RuntimeException('Only a won lead can be converted to a customer.');
        }
        if ($lockedLead['converted_customer_id']) {
            throw new RuntimeException('This lead has already been linked to a customer.');
        }
        $customerId = (int)($_POST['customer_id'] ?? 0);
        if ($customerId > 0) {
            $customerQuery = $pdo->prepare('SELECT id FROM customers WHERE id=? AND business_id=? AND archived_at IS NULL');
            $customerQuery->execute([$customerId, $businessId]);
            if (!$customerQuery->fetchColumn()) throw new RuntimeException('Select an active customer from this workspace.');
        } else {
            $matchQuery = $pdo->prepare('SELECT id,name,email,phone FROM customers WHERE business_id=? AND archived_at IS NULL AND ((?<>\'\' AND LOWER(email)=LOWER(?)) OR (?<>\'\' AND phone=?))');
            $email = trim((string)$lockedLead['email']);
            $phone = trim((string)$lockedLead['phone']);
            $matchQuery->execute([$businessId, $email, $email, $phone, $phone]);
            $matches = $matchQuery->fetchAll();
            if ($matches) throw new RuntimeException('A customer with this email or phone already exists. Choose that customer below to link the lead.');
            $insertCustomer = $pdo->prepare('INSERT INTO customers(business_id,name,company,email,phone,notes) VALUES(?,?,?,?,?,?)');
            $insertCustomer->execute([$businessId, $lockedLead['name'], $lockedLead['company'], $email ?: null, $phone ?: null, $lockedLead['notes']]);
            $customerId = (int)$pdo->lastInsertId();
        }
        $link = $pdo->prepare('UPDATE leads SET converted_customer_id=?,converted_at=NOW() WHERE id=? AND business_id=? AND converted_customer_id IS NULL');
        $link->execute([$customerId, $leadId, $businessId]);
        if ($link->rowCount() !== 1) throw new RuntimeException('Lead conversion could not be completed. Reload and check its status.');
        $pdo->commit();
        audit('lead.convert', 'Lead #' . $leadId . ' linked to customer #' . $customerId);
        flash('Lead linked to the customer successfully.');
        redirect('customers.php?view=' . $customerId);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Lead conversion failed. Please try again.';
    }
}

$matchQuery = $pdo->prepare('SELECT id,name,company,email,phone FROM customers WHERE business_id=? AND archived_at IS NULL AND ((?<>\'\' AND LOWER(email)=LOWER(?)) OR (?<>\'\' AND phone=?)) ORDER BY name');
$email = trim((string)$lead['email']);
$phone = trim((string)$lead['phone']);
$matchQuery->execute([$businessId, $email, $email, $phone, $phone]);
$matches = $matchQuery->fetchAll();
$customerQuery = $pdo->prepare('SELECT id,name,company FROM customers WHERE business_id=? AND archived_at IS NULL ORDER BY name');
$customerQuery->execute([$businessId]);
$customers = $customerQuery->fetchAll();

page_start('Convert lead', 'leads');
?>
<div class="section-head"><div><span class="eyebrow">SALES PIPELINE</span><h1>Convert lead</h1><p>Link this won opportunity to the right customer record.</p></div><a class="button secondary" href="leads.php">Back to leads</a></div>
<?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif ?>
<div class="conversion-layout"><section class="content-card"><h2><?= e($lead['name']) ?></h2><div class="detail-line"><span>Company</span><?= e($lead['company'] ?: '—') ?></div><div class="detail-line"><span>Email</span><?= e($lead['email'] ?: '—') ?></div><div class="detail-line"><span>Phone</span><?= e($lead['phone'] ?: '—') ?></div><div class="detail-line"><span>Lead status</span><?= e($lead['status']) ?></div></section><section class="content-card"><h2>Choose a customer</h2><?php if ($lead['status'] !== 'Won'): ?><p class="help-text">Mark this lead Won before converting it.</p><?php elseif ($lead['converted_customer_id']): ?><p>This lead is already linked to customer #<?= (int)$lead['converted_customer_id'] ?>.</p><?php else: ?>
<?php if ($matches): ?><div class="flash success">Possible duplicate<?= count($matches)===1?'':'s' ?> found. Link this lead to an existing customer to avoid creating another record.</div><?php endif ?>
<form method="post" class="stack-form"><input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="lead_id" value="<?= $leadId ?>"><label>Existing customer<select name="customer_id"><option value="">Create a new customer<?= $matches ? ' (blocked while duplicate exists)' : '' ?></option><?php foreach ($customers as $customer): ?><option value="<?= (int)$customer['id'] ?>"><?= e($customer['name'] . ($customer['company'] ? ' · ' . $customer['company'] : '')) ?></option><?php endforeach ?></select></label><p class="help-text">When creating a customer, the lead’s name, company, contact details and notes are copied.</p><button class="button primary" <?= $matches ? 'disabled' : '' ?>>Create customer from lead</button><?php if ($matches): ?><button class="button secondary" type="submit" formaction="" onclick="this.form.customer_id.required=true">Link selected customer</button><?php endif ?></form>
<?php endif ?></section></div>
<style>.conversion-layout{display:grid;grid-template-columns:1fr 1fr;gap:14px}.conversion-layout .content-card h2{font:650 14px var(--display);margin:0 0 10px}.conversion-layout .stack-form{max-width:470px}.conversion-layout select{display:block;width:100%;height:38px;margin-top:5px;border:1px solid #e5e9ed;border-radius:6px;padding:0 9px}.button:disabled{opacity:.45;cursor:not-allowed}@media(max-width:700px){.conversion-layout{grid-template-columns:1fr}}</style>
<?php page_end();
