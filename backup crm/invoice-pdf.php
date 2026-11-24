<?php
declare(strict_types=1);
require __DIR__ . '/includes/module.php';
require __DIR__ . '/includes/pdf.php';
$user = require_auth();
require_permission('invoices.view');
$businessId = (int)$user['business_id'];
$invoiceId = (int)($_GET['id'] ?? 0);
$query = db()->prepare('SELECT i.*,c.name customer,c.company,c.email,c.phone,c.city,c.gstin FROM invoices i JOIN customers c ON c.id=i.customer_id WHERE i.id=? AND i.business_id=?');
$query->execute([$invoiceId, $businessId]);
$invoice = $query->fetch();
if (!$invoice) { http_response_code(404); exit('Invoice not found.'); }
$query = db()->prepare('SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY id');
$query->execute([$invoiceId]);
$items = $query->fetchAll();
$business = json_decode((string)$invoice['business_snapshot'], true) ?: [];
$customer = json_decode((string)$invoice['customer_snapshot'], true) ?: [];
$sumCgst = $sumSgst = $sumIgst = 0.0;
foreach ($items as $taxItem) { $sumCgst += (float)($taxItem['cgst_amount'] ?? 0); $sumSgst += (float)($taxItem['sgst_amount'] ?? 0); $sumIgst += (float)($taxItem['igst_amount'] ?? 0); }
$currency = (string)($business['currency'] ?? 'INR');
$amount = static fn($value) => $currency . ' ' . number_format((float)$value, 2);
$pages = [];
$chunkedItems = array_chunk($items, 25);
if (!$chunkedItems) $chunkedItems = [[]];
foreach ($chunkedItems as $pageNumber => $pageItems) {
    $commands = [];
    pdf_text($commands, 50, 795, (string)($business['name'] ?? 'BUZNET CRM'), 18, true);
    pdf_text($commands, 50, 777, (string)($invoice['document_type'] ?? 'INVOICE'), 10, true);
    pdf_text($commands, 410, 795, (string)$invoice['invoice_number'], 13, true);
    pdf_text($commands, 410, 777, 'Status: ' . (string)$invoice['status'], 9);
    pdf_rule($commands, 762);
    pdf_text($commands, 50, 742, 'BILL TO', 8, true);
    pdf_text($commands, 50, 726, (string)($customer['name'] ?? $invoice['customer']), 11, true);
    pdf_text($commands, 50, 711, 'GSTIN: ' . (string)($customer['gstin'] ?? '—'), 8);
    pdf_text($commands, 50, 697, (string)($customer['company'] ?? $invoice['company'] ?? ''), 9);
    pdf_text($commands, 50, 683, (string)($customer['email'] ?? $invoice['email'] ?? ''), 9);
    pdf_text($commands, 50, 669, (string)($customer['phone'] ?? $invoice['phone'] ?? ''), 9);
    pdf_text($commands, 410, 742, 'INVOICE DATE', 8, true);
    pdf_text($commands, 410, 726, fmt_date($invoice['invoice_date']), 10);
    pdf_text($commands, 410, 704, 'DUE DATE', 8, true);
    pdf_text($commands, 410, 688, fmt_date($invoice['due_date']), 10);
    pdf_text($commands, 50, 653, 'Place of supply: ' . (string)($invoice['place_of_supply_state'] ?? '') . ' (' . (string)($invoice['place_of_supply_code'] ?? '') . ')', 8);
    $y = 632;
    pdf_rule($commands, 642);
    pdf_text($commands, 50, $y, 'DESCRIPTION / HSN-SAC', 8, true);
    pdf_text($commands, 340, $y, 'QTY', 8, true);
    pdf_text($commands, 390, $y, 'RATE', 8, true);
    pdf_text($commands, 455, $y, 'GST', 8, true);
    pdf_text($commands, 510, $y, 'TOTAL', 8, true);
    $y -= 18;
    foreach ($pageItems as $item) {
        pdf_text($commands, 50, $y, mb_substr((string)$item['description'] . ' / ' . (string)($item['hsn_sac'] ?? ''), 0, 42), 9);
        pdf_text($commands, 340, $y, (string)$item['quantity'], 9);
        pdf_text($commands, 390, $y, number_format((float)$item['unit_price'], 2), 9);
        pdf_text($commands, 455, $y, number_format((float)$item['tax_rate'], 2) . '%', 9);
        pdf_text($commands, 510, $y, number_format((float)$item['line_total'], 2), 9);
        $y -= 18;
    }
    if ($pageNumber === count($chunkedItems) - 1) {
        $y -= 4;
        pdf_rule($commands, $y);
        $y -= 20;
        pdf_text($commands, 365, $y, 'Subtotal', 9); pdf_text($commands, 475, $y, $amount($invoice['subtotal']), 9);
        $y -= 17;
        pdf_text($commands, 365, $y, 'CGST', 9); pdf_text($commands, 475, $y, $amount($sumCgst), 9);
        $y -= 15;
        pdf_text($commands, 365, $y, 'SGST', 9); pdf_text($commands, 475, $y, $amount($sumSgst), 9);
        $y -= 15;
        pdf_text($commands, 365, $y, 'IGST', 9); pdf_text($commands, 475, $y, $amount($sumIgst), 9);
        $y -= 20;
        pdf_text($commands, 365, $y, 'TOTAL', 11, true); pdf_text($commands, 475, $y, $amount($invoice['total']), 11, true);
        $y -= 18;
        pdf_text($commands, 365, $y, 'PAID', 9); pdf_text($commands, 475, $y, $amount($invoice['amount_paid']), 9);
        $y -= 18;
        pdf_text($commands, 365, $y, 'BALANCE DUE', 10, true); pdf_text($commands, 475, $y, $amount((float)$invoice['total']-(float)$invoice['amount_paid']), 10, true);
        if ($invoice['notes']) {
            pdf_rule($commands, 105);
            pdf_text($commands, 50, 87, 'NOTES / TERMS', 8, true);
            pdf_text($commands, 50, 70, mb_substr(str_replace(["\r", "\n"], ' ', (string)$invoice['notes']), 0, 105), 8);
        }
    }
    pdf_text($commands, 50, 30, 'BUZNET CRM · Generated ' . date('d M Y'), 7);
    pdf_text($commands, 500, 30, 'Page ' . ($pageNumber + 1), 7);
    $pages[] = $commands;
}
$pdf = build_document_pdf($pages);
audit('invoice.pdf.download', (string)$invoice['invoice_number']);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$invoice['invoice_number']) . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: no-store, private');
echo $pdf;
