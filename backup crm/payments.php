<?php
declare(strict_types=1);
require __DIR__ . '/includes/module.php';
$user = require_auth();
require_permission('payments.view');
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPost) require_permission('payments.edit');
$businessId = (int)$user['business_id'];
$pdo = db();
$error = '';

if ($isPost) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'record');
    try {
        if ($action === 'reverse') {
            $user = require_owner();
            $paymentId = (int)($_POST['payment_id'] ?? 0);
            $reason = trim((string)($_POST['reversal_note'] ?? ''));
            if ($reason === '') throw new RuntimeException('Enter a reason for reversing this payment.');
            $pdo->beginTransaction();
            $paymentQuery = $pdo->prepare('SELECT * FROM payments WHERE id=? AND business_id=? AND reversed_at IS NULL FOR UPDATE');
            $paymentQuery->execute([$paymentId, $businessId]);
            $payment = $paymentQuery->fetch();
            if (!$payment) throw new RuntimeException('Payment not found, or it has already been reversed.');
            $invoiceQuery = $pdo->prepare('SELECT * FROM invoices WHERE id=? AND business_id=? FOR UPDATE');
            $invoiceQuery->execute([$payment['invoice_id'], $businessId]);
            $invoice = $invoiceQuery->fetch();
            if (!$invoice || $invoice['status'] === 'Void') throw new RuntimeException('The related invoice cannot be updated.');
            $reverseQuery = $pdo->prepare('UPDATE payments SET reversed_at=NOW(),reversed_by=?,reversal_note=? WHERE id=? AND business_id=? AND reversed_at IS NULL');
            $reverseQuery->execute([$user['id'], mb_substr($reason, 0, 500), $paymentId, $businessId]);
            if ($reverseQuery->rowCount() !== 1) throw new RuntimeException('Payment was already reversed. Reload and try again.');
            $sumQuery = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=? AND business_id=? AND reversed_at IS NULL');
            $sumQuery->execute([$invoice['id'], $businessId]);
            $remainingPaid = round((float)$sumQuery->fetchColumn(), 2);
            $newStatus = $remainingPaid + 0.001 >= (float)$invoice['total'] ? 'Paid' : (($invoice['due_date'] && $invoice['due_date'] < date('Y-m-d')) ? 'Overdue' : ($remainingPaid > 0 ? 'Partially Paid' : 'Issued'));
            $update = $pdo->prepare('UPDATE invoices SET amount_paid=?,status=? WHERE id=? AND business_id=?');
            $update->execute([$remainingPaid, $newStatus, $invoice['id'], $businessId]);
            $pdo->commit();
            audit('payment.reverse', $invoice['invoice_number'] . ' payment #' . $paymentId . ': ' . $reason);
            flash('Payment reversed and invoice balance recalculated.');
            redirect('payments.php');
        }
        if ($action !== 'record') throw new RuntimeException('Unsupported payment action.');
        $invoiceId = (int)($_POST['invoice_id'] ?? 0);
        $amount = round((float)($_POST['amount'] ?? 0), 2);
        $pdo->beginTransaction();
        $statement = $pdo->prepare('SELECT i.*,c.id customer_id FROM invoices i JOIN customers c ON c.id=i.customer_id WHERE i.id=? AND i.business_id=? FOR UPDATE');
        $statement->execute([$invoiceId, $businessId]);
        $invoice = $statement->fetch();
        if (!$invoice || in_array($invoice['status'], ['Draft', 'Void'], true)) {
            throw new RuntimeException('Choose an issued, non-void invoice.');
        }
        $balance = round((float)$invoice['total'] - (float)$invoice['amount_paid'], 2);
        if ($amount <= 0 || $amount > $balance) {
            throw new RuntimeException('Payment amount must be positive and cannot exceed the invoice balance.');
        }
        $reference = trim((string)($_POST['reference'] ?? ''));
        if ($reference !== '') {
            $duplicate = $pdo->prepare('SELECT id FROM payments WHERE business_id=? AND invoice_id=? AND amount=? AND reference=? AND reversed_at IS NULL');
            $duplicate->execute([$businessId, $invoiceId, $amount, $reference]);
            if ($duplicate->fetchColumn()) throw new RuntimeException('This payment reference already exists for this invoice.');
        }
        $method = in_array($_POST['method'] ?? '', ['Cash', 'Bank transfer', 'UPI', 'Card', 'Cheque', 'Other'], true) ? $_POST['method'] : 'Other';
        $paidAt = trim((string)($_POST['paid_at'] ?? date('Y-m-d')));
        $insert = $pdo->prepare('INSERT INTO payments(business_id,invoice_id,customer_id,paid_at,amount,method,reference,notes) VALUES(?,?,?,?,?,?,?,?)');
        $insert->execute([$businessId, $invoiceId, $invoice['customer_id'], $paidAt, $amount, $method, $reference ?: null, trim((string)($_POST['notes'] ?? ''))]);
        $paidTotal = round((float)$invoice['amount_paid'] + $amount, 2);
        $status = $paidTotal >= (float)$invoice['total'] ? 'Paid' : 'Partially Paid';
        $update = $pdo->prepare('UPDATE invoices SET amount_paid=?,status=? WHERE id=? AND business_id=?');
        $update->execute([$paidTotal, $status, $invoiceId, $businessId]);
        $pdo->commit();
        audit('payment.record', $invoice['invoice_number'] . ' ' . money($amount));
        flash('Payment recorded and invoice balance updated.');
        redirect('payments.php');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Payment could not be recorded. Please retry.';
    }
}

        $statement = $pdo->prepare('SELECT p.*,i.invoice_number,c.name customer FROM payments p JOIN invoices i ON i.id=p.invoice_id JOIN customers c ON c.id=p.customer_id WHERE p.business_id=? ORDER BY p.paid_at DESC,p.id DESC');
$statement->execute([$businessId]);
$rows = $statement->fetchAll();
$statement = $pdo->prepare("SELECT i.id,i.invoice_number,i.total,i.amount_paid,c.name customer FROM invoices i JOIN customers c ON c.id=i.customer_id WHERE i.business_id=? AND i.status IN ('Issued','Partially Paid','Overdue') AND i.total>i.amount_paid ORDER BY i.invoice_date DESC");
$statement->execute([$businessId]);
$openInvoices = $statement->fetchAll();
$invoiceOptions = '';
foreach ($openInvoices as $invoice) {
    $remaining = (float)$invoice['total'] - (float)$invoice['amount_paid'];
    $invoiceOptions .= '<option value="' . (int)$invoice['id'] . '" data-balance="' . e($remaining) . '">' . e($invoice['invoice_number'] . ' · ' . $invoice['customer'] . ' · balance ' . money($remaining)) . '</option>';
}
$fields = '<label class="wide">Invoice<select required name="invoice_id" id="payment-invoice"><option value="">Select open invoice</option>' . $invoiceOptions . '</select></label>'
    . '<label>Amount<input required name="amount" id="payment-amount" type="number" min="0.01" step="0.01"></label>'
    . '<label>Payment date<input required type="date" name="paid_at" value="' . date('Y-m-d') . '"></label>'
    . '<label>Method<select name="method"><option>Bank transfer</option><option>UPI</option><option>Cash</option><option>Card</option><option>Cheque</option><option>Other</option></select></label>'
    . '<label>Reference<input name="reference"></label><label class="wide">Notes<input name="notes"></label>';

page_start('Payments', 'payments');
module_css('payments');
?>
<div class="section-head"><div><span class="eyebrow">CASH IN</span><h1>Payments</h1><p>Receipts linked to invoices keep every balance in sync.</p></div><button class="button primary" onclick="document.getElementById('record-modal').showModal()" <?= $openInvoices ? '' : 'disabled' ?>><i class="ti ti-plus"></i> Record payment</button></div>
<?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif ?>
<div class="content-card"><div class="table-scroll"><table class="data-table"><thead><tr><th>DATE</th><th>INVOICE</th><th>CUSTOMER</th><th>METHOD</th><th>REFERENCE</th><th>AMOUNT</th><th>STATUS</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= fmt_date($row['paid_at']) ?></td><td><a class="table-strong" href="invoice-view.php?id=<?= (int)$row['invoice_id'] ?>"><?= e($row['invoice_number']) ?></a></td><td><?= e($row['customer']) ?></td><td><?= e($row['method']) ?></td><td><?= e($row['reference'] ?: '—') ?></td><td class="table-strong"><?= money($row['amount']) ?></td><td><?= $row['reversed_at'] ? '<span class="status void">Reversed</span>' : '<span class="status paid">Recorded</span>' ?></td><td><?php if ($user['role'] === 'owner' && !$row['reversed_at']): ?><form method="post" onsubmit="return confirm('Reverse this payment and recalculate the invoice balance?')"><input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="reverse"><input type="hidden" name="payment_id" value="<?= (int)$row['id'] ?>"><input type="text" name="reversal_note" maxlength="500" required placeholder="Reason" aria-label="Reason for payment reversal" style="width:90px;font-size:9px"><button class="link-button" style="color:#bb665c">Reverse</button></form><?php elseif ($row['reversed_at']): ?><span class="help-text"><?= e($row['reversal_note'] ?: 'Reversed') ?></span><?php endif ?></td></tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="8" class="empty-row">Recorded receipts will appear here.</td></tr><?php endif ?></tbody></table></div></div>
<?php modal_form('Record payment', 'record', $fields); ?>
<script>document.getElementById('payment-invoice').addEventListener('change',function(){const balance=Number(this.selectedOptions[0].dataset.balance)||0;const amount=document.getElementById('payment-amount');amount.max=balance;amount.value=balance.toFixed(2)})</script>
<?php page_end();
