<?php
require __DIR__ . '/includes/module.php';
$user = require_auth();
require_permission('leads.view');
module_action('leads');
$businessId = (int)$user['business_id'];
$pdo = db();
$search = trim((string)($_GET['q'] ?? ''));
$sql = 'SELECT l.*,c.name converted_name FROM leads l LEFT JOIN customers c ON c.id=l.converted_customer_id WHERE l.business_id=?';
$params = [$businessId];
if ($search !== '') {
    $sql .= ' AND (l.name LIKE ? OR l.company LIKE ? OR l.email LIKE ?)';
    array_push($params, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%');
}
$sql .= ' ORDER BY l.created_at DESC';
$query = $pdo->prepare($sql);
$query->execute($params);
$rows = $query->fetchAll();
$counts = ['Open' => 0, 'Won' => 0, 'Lost' => 0];
$pipelineValue = 0.0;
foreach ($rows as $row) {
    $counts[$row['status']]++;
    if ($row['status'] === 'Open') $pipelineValue += (float)$row['deal_value'];
}
$fields = '<label>Contact name *<input required name="name"></label><label>Company<input name="company"></label><label>Email<input type="email" name="email"></label><label>Phone<input name="phone"></label><label>Source<input name="source" placeholder="Referral, website…"></label><label>Stage<select name="stage"><option>New</option><option>Qualified</option><option>Proposal</option><option>Negotiation</option><option>Won</option><option>Lost</option></select></label><label>Deal value<input type="number" step="0.01" min="0" name="deal_value" value="0"></label><label>Expected close<input type="date" name="expected_close"></label><label class="wide">Notes<textarea name="notes"></textarea></label>';
page_start('Leads & deals', 'leads');
module_css('leads');
?>
<div class="section-head"><div><span class="eyebrow">SALES PIPELINE</span><h1>Leads & deals</h1><p>Keep promising conversations moving forward.</p></div><button class="button primary" onclick="document.getElementById('record-modal').showModal()"><i class="ti ti-plus"></i> Add lead</button></div>
<div class="summary-grid"><div class="summary-tile"><small>Open opportunities</small><strong><?= $counts['Open'] ?></strong></div><div class="summary-tile"><small>Pipeline value</small><strong><?= money($pipelineValue) ?></strong></div><div class="summary-tile"><small>Won deals</small><strong><?= $counts['Won'] ?></strong></div></div>
<div class="content-card"><form class="toolbar"><input class="search" name="q" value="<?= e($search) ?>" placeholder="Search leads"><button class="button secondary">Search</button></form><div class="table-scroll"><table class="data-table"><thead><tr><th>CONTACT</th><th>STAGE</th><th>SOURCE</th><th>EXPECTED CLOSE</th><th>VALUE</th><th>FOLLOW-UP</th><th>CUSTOMER</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><span class="table-strong"><?= e($row['name']) ?></span><div class="help-text"><?= e($row['company'] ?: $row['email'] ?: '—') ?></div></td><td><form method="post" class="status-form"><input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="stage"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><select name="stage" onchange="this.form.submit()"><?php foreach (['New','Qualified','Proposal','Negotiation','Won','Lost'] as $stage): ?><option <?= $row['stage'] === $stage ? 'selected' : '' ?>><?= e($stage) ?></option><?php endforeach ?></select></form></td><td><?= e($row['source'] ?: '—') ?></td><td><?= fmt_date($row['expected_close']) ?></td><td class="table-strong"><?= money($row['deal_value']) ?></td><td><?= fmt_date($row['follow_up']) ?></td><td><?php if ($row['converted_customer_id']): ?><a class="table-action" href="customers.php?view=<?= (int)$row['converted_customer_id'] ?>"><?= e($row['converted_name'] ?: 'Customer') ?></a><?php elseif ($row['status'] === 'Won'): ?><a class="table-action" href="lead-convert.php?id=<?= (int)$row['id'] ?>">Convert to customer</a><?php else: ?><span class="help-text">—</span><?php endif ?></td></tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="7" class="empty-row">No leads yet. Add a lead to start building your pipeline.</td></tr><?php endif ?></tbody></table></div></div>
<?php modal_form('Add lead', 'create', $fields); page_end();
