<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) set_flash('error', 'Session expired. Please try again.');
    else {
        $id = (int)($_POST['id'] ?? 0); $status = (string)($_POST['status'] ?? 'new');
        if (in_array($status, ['new','read','replied','archived'], true)) {
            db()->prepare('UPDATE contact_messages SET status = :status WHERE id = :id')->execute(['status'=>$status,'id'=>$id]);
            set_flash('success', 'Enquiry status updated.');
        }
    }
    redirect('admin/messages.php?id=' . (int)($_POST['id'] ?? 0));
}
$messages = db()->query('SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 200')->fetchAll();
$selectedId = (int)($_GET['id'] ?? 0); $selected = null; foreach ($messages as $message) if ((int)$message['id'] === $selectedId) $selected = $message;
$adminPageTitle = 'Enquiries'; require __DIR__ . '/includes/header.php';
?><section class="admin-panel"><div class="panel-heading"><div><span class="admin-overline">LET'S KEEP THE CONVERSATION GOING</span><h2>Customer enquiries <small><?= count($messages) ?> latest</small></h2></div></div><?php if (!$messages): ?><div class="admin-empty">Nothing in the inbox yet. New website enquiries will appear here.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>NAME / EMAIL</th><th>SUBJECT</th><th>RECEIVED</th><th>STATUS</th><th></th></tr></thead><tbody><?php foreach ($messages as $message): ?><tr><td><strong><?= e($message['name']) ?></strong><small><?= e($message['email']) ?></small></td><td><?= e($message['subject']) ?></td><td><?= e(date('M j, Y · g:i a', strtotime($message['created_at']))) ?></td><td><span class="status-pill <?= e($message['status']) ?>"><?= e($message['status']) ?></span></td><td><a class="table-action" href="<?= e(base_url('admin/messages.php?id=' . $message['id'])) ?>">Read</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section><?php if ($selected): ?><section class="admin-panel message-detail"><div class="panel-heading"><div><span class="admin-overline">MESSAGE #<?= (int)$selected['id'] ?></span><h2><?= e($selected['subject']) ?></h2></div><a href="<?= e(base_url('admin/messages.php')) ?>">Close ×</a></div><div class="message-meta"><strong><?= e($selected['name']) ?></strong><a href="mailto:<?= e($selected['email']) ?>"><?= e($selected['email']) ?></a><?php if ($selected['phone']): ?><span><?= e($selected['phone']) ?></span><?php endif; ?><span><?= e(date('M j, Y · g:i a', strtotime($selected['created_at']))) ?></span></div><p class="message-body"><?= nl2br(e($selected['message'])) ?></p><form method="post" class="status-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$selected['id'] ?>"><label>Update status<select name="status"><?php foreach (['new','read','replied','archived'] as $status): ?><option value="<?= $status ?>" <?= $selected['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select></label><button class="admin-button" type="submit">Save status</button></form></section><?php endif; ?><?php require __DIR__ . '/includes/footer.php'; ?>
