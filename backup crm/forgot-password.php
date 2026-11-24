<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
if (current_user()) redirect('dashboard.php');
$message = 'If an active account matches that email, a password reset link will be sent shortly.';
$waitMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $now = time();
    $_SESSION['reset_requests'] = array_values(array_filter($_SESSION['reset_requests'] ?? [], static fn($ts) => $ts > $now - 900));
    if (count($_SESSION['reset_requests']) >= 4) {
        $waitMessage = 'Please wait before requesting another reset link.';
    } else {
        $_SESSION['reset_requests'][] = $now;
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $query = db()->prepare('SELECT id,name,email FROM users WHERE email=? AND active=1 LIMIT 1');
            $query->execute([$email]);
            $user = $query->fetch();
            if ($user) {
                $token = bin2hex(random_bytes(32));
                db()->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$user['id']]);
                db()->prepare('INSERT INTO password_reset_tokens(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))')->execute([$user['id'], hash('sha256', $token)]);
                $baseUrl = rtrim((string)(getenv('BUZNET_APP_URL') ?: ''), '/');
                if ($baseUrl === '') {
                    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                    $baseUrl = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(str_replace(' ', '%20', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/.');
                }
                $link = $baseUrl . '/reset-password.php?token=' . rawurlencode($token);
                $from = (string)(getenv('BUZNET_MAIL_FROM') ?: 'no-reply@localhost');
                $subject = 'Reset your BUZNET CRM password';
                $body = "Hello {$user['name']},\r\n\r\nUse this link to choose a new password. It expires in 30 minutes and can only be used once:\r\n$link\r\n\r\nIf you did not request this, you can ignore this message.\r\n";
                @mail($user['email'], $subject, $body, 'From: ' . $from . "\r\nContent-Type: text/plain; charset=UTF-8");
            }
        }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Password recovery · BUZNET CRM</title><link rel="stylesheet" href="assets/css/app.css"><script src="https://unpkg.com/@tabler/icons@latest/icons-webfont/tabler-icons.min.js"></script></head><body class="auth-body"><div class="auth-shell"><div class="auth-brand"><span class="brand-mark"><i class="ti ti-chart-dots-3"></i></span><span>BUZNET<small>BUSINESS OS</small></span></div><div class="auth-card"><span class="eyebrow">ACCOUNT RECOVERY</span><h1>Reset your password.</h1><p class="muted">Enter your account email and we’ll send a one-time reset link if it matches.</p><?php if($_SERVER['REQUEST_METHOD']==='POST'):?><div class="flash success"><?=e($message)?></div><?php if($waitMessage):?><div class="flash error"><?=e($waitMessage)?></div><?php endif?><?php endif?><form method="post" class="stack-form"><input type="hidden" name="_csrf" value="<?=e(csrf())?>"><label>Email address<input type="email" name="email" required autocomplete="email"></label><button class="button primary full">Send reset link</button></form><p class="auth-foot"><a href="login.php">Back to sign in</a></p></div></div></body></html>
