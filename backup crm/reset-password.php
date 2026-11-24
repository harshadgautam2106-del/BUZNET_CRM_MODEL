<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
if (current_user()) redirect('dashboard.php');
$token = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));
$validToken = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
$error = '';
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string)($_POST['password'] ?? '');
    if (!$validToken || strlen($password) < 12 || $password !== (string)($_POST['confirm_password'] ?? '')) {
        $error = 'Use a valid reset link and enter matching passwords with at least 12 characters.';
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $query = $pdo->prepare('SELECT t.id,t.user_id FROM password_reset_tokens t JOIN users u ON u.id=t.user_id WHERE t.token_hash=? AND t.used_at IS NULL AND t.expires_at>NOW() AND u.active=1 FOR UPDATE');
            $query->execute([hash('sha256', $token)]);
            $row = $query->fetch();
            if (!$row) throw new RuntimeException('This reset link is invalid or has expired. Request a new link.');
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $row['user_id']]);
            $pdo->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$row['user_id']]);
            $pdo->commit();
            $success = true;
        } catch (Throwable $exception) {
            if (db()->inTransaction()) db()->rollBack();
            $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Password could not be reset. Request a new link and try again.';
        }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Choose a new password · BUZNET CRM</title><link rel="stylesheet" href="assets/css/app.css"><script src="https://unpkg.com/@tabler/icons@latest/icons-webfont/tabler-icons.min.js"></script></head><body class="auth-body"><div class="auth-shell"><div class="auth-brand"><span class="brand-mark"><i class="ti ti-chart-dots-3"></i></span><span>BUZNET<small>BUSINESS OS</small></span></div><div class="auth-card"><span class="eyebrow">ACCOUNT RECOVERY</span><h1>Choose a new password.</h1><?php if($success):?><div class="flash success">Password updated. You can now sign in.</div><a class="button primary full" href="login.php">Go to sign in</a><?php else:?><?php if($error):?><div class="flash error"><?=e($error)?></div><?php endif?><form method="post" class="stack-form"><input type="hidden" name="_csrf" value="<?=e(csrf())?>"><input type="hidden" name="token" value="<?=e($token)?>"><label>New password<input type="password" name="password" minlength="12" required autocomplete="new-password"></label><label>Confirm new password<input type="password" name="confirm_password" minlength="12" required autocomplete="new-password"></label><button class="button primary full">Update password</button></form><?php endif?></div></div></body></html>
