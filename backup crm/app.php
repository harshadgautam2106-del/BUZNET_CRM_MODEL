<?php
declare(strict_types=1);
date_default_timezone_set(getenv('BUZNET_TIMEZONE') ?: 'Asia/Kolkata');
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('buznet_session');
    session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax','path'=>'/']);
    session_start();
}
const APP_NAME = 'BUZNET CRM';
const CURRENCY = '₹';
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $host=getenv('BUZNET_DB_HOST') ?: '127.0.0.1'; $name=getenv('BUZNET_DB_NAME') ?: 'buznet_crm';
    $user=getenv('BUZNET_DB_USER') ?: 'root'; $pass=getenv('BUZNET_DB_PASS') ?: '';
    try { $pdo=new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); return $pdo; }
    catch (Throwable $e) { http_response_code(503); exit('<h1>Database unavailable</h1><p>Create the database and import <code>database/schema.sql</code>, then configure BUZNET_DB_* environment variables.</p>'); }
}
function e(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function redirect(string $to): never { header('Location: '.$to); exit; }
function csrf(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void { if(!hash_equals(csrf(),(string)($_POST['_csrf']??''))){http_response_code(419);exit('Your session expired. Go back and try again.');} }
function require_auth(): array { if(!current_user()) redirect('login.php'); $q=db()->prepare('SELECT id,business_id,name,email,role,active FROM users WHERE id=?');$q->execute([$_SESSION['user']['id']]);$u=$q->fetch();if(!$u||!$u['active']){session_destroy();redirect('login.php');}$_SESSION['user']=$u;return $u; }
function require_owner(): array { $u=require_auth();if($u['role']!=='owner'){http_response_code(403);exit('Owner access required.');}return $u; }
function money(float|string|null $v): string { static $currency=null;if($currency===null&&current_user()){try{$q=db()->prepare('SELECT currency FROM businesses WHERE id=?');$q->execute([current_user()['business_id']]);$currency=$q->fetchColumn()?:'INR';}catch(Throwable $e){$currency='INR';}}$symbols=['INR'=>'₹','USD'=>'$','EUR'=>'€','GBP'=>'£','AUD'=>'A$','CAD'=>'C$','JPY'=>'¥'];$code=$currency?:'INR';return ($symbols[$code]??$code).' '.number_format((float)$v,2); }
function flash(string $message,string $type='success'): void { $_SESSION['flash']=['message'=>$message,'type'=>$type]; }
function take_flash(): ?array { $f=$_SESSION['flash']??null;unset($_SESSION['flash']);return $f; }
function audit(string $event,string $detail=''): void { if(!current_user())return;$q=db()->prepare('INSERT INTO activity_logs(user_id,event,detail,ip_address) VALUES(?,?,?,?)');$q->execute([current_user()['id'],$event,$detail,$_SERVER['REMOTE_ADDR']??'']); }
function fmt_date(?string $d): string { return $d ? date('d M Y',strtotime($d)) : '—'; }
function page_start(string $title,string $active='dashboard'): void {
 $u=require_auth();$flash=take_flash();$nav=[['dashboard','Overview','dashboard.php','dashboard'],['customers','Customers','customers.php','users'],['leads','Leads & deals','leads.php','target'],['products','Products & services','products.php','box'],['tasks','Tasks & reminders','tasks.php','checklist'],['quotations','Quotations','quotations.php','file-description'],['managers','Managers','managers.php','users-group'],['invoices','Invoices','invoices.php','file-invoice'],['payments','Payments','payments.php','cash'],['expenses','Expenses','expenses.php','receipt'],['reports','Reports','reports.php','chart-bar'],['help','Help & manual','help.php','book-2'],['settings','Settings','settings.php','settings']];$dueQuery=db()->prepare("SELECT COUNT(*) FROM tasks WHERE business_id=? AND status!='Completed' AND due_date=CURDATE()");$dueQuery->execute([$u['business_id']]);$dueCount=(int)$dueQuery->fetchColumn();
 echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' · BUZNET CRM</title><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/css/app.css"><script src="https://unpkg.com/@tabler/icons@latest/icons-webfont/tabler-icons.min.js"></script></head><body><aside class="sidebar" id="sidebar"><a class="brand" href="dashboard.php"><span class="brand-mark"><i class="ti ti-chart-dots-3"></i></span><span>BUZNET<small>BUSINESS OS</small></span></a><div class="workspace"><span class="workspace-dot"></span><span>My workspace</span><i class="ti ti-chevron-down"></i></div><div class="nav-label">WORKSPACE</div><nav>';
 foreach($nav as [$key,$label,$href,$icon]) { if($u['role']!=='owner'&&$key!=='settings'){$permissionKey=$key==='managers'?'managers.view':$key.'.view';$permissionQuery=db()->prepare('SELECT granted FROM user_permissions WHERE user_id=? AND permission_key=?');$permissionQuery->execute([$u['id'],$permissionKey]);if(!(bool)$permissionQuery->fetchColumn())continue;} if($key==='managers'&&$u['role']!=='owner')continue; echo '<a class="nav-link '.($active===$key?'active':'').'" href="'.e($href).'"><i class="ti ti-'.e($icon).'"></i><span>'.e($label).'</span>'.($key==='tasks'?'<span class="nav-count">'.$dueCount.'</span>':'').'</a>'; }
 echo '</nav><div class="sidebar-bottom"><div class="help-card"><span class="help-icon"><i class="ti ti-sparkles"></i></span><strong>Your business, in focus.</strong><p>Keep every customer and cash flow in one place.</p><a href="reports.php">Explore reports <i class="ti ti-arrow-up-right"></i></a></div><div class="user-mini"><div class="avatar">'.e(strtoupper(substr($u['name'],0,1))).'</div><div class="user-info"><strong>'.e($u['name']).'</strong><small>'.e(ucfirst($u['role'])).' account</small></div><a class="logout" href="logout.php" title="Sign out"><i class="ti ti-logout"></i></a></div></div></aside><main class="main"><header class="topbar"><button class="icon-button menu-toggle" onclick="document.body.classList.toggle(\'menu-open\')"><i class="ti ti-menu-2"></i></button><div class="breadcrumb">Workspace <i class="ti ti-chevron-right"></i> <strong>'.e($title).'</strong></div><div class="top-actions"><span class="today"><i class="ti ti-calendar"></i> '.date('D, d M Y').'</span><span class="top-divider"></span><button class="icon-button" title="Notifications"><i class="ti ti-bell"></i><b class="notification-dot"></b></button><div class="avatar top-avatar">'.e(strtoupper(substr($u['name'],0,1))).'</div></div></header><section class="page-content">';
 if($flash) echo '<div class="flash '.e($flash['type']).'">'.e($flash['message']).'</div>';
}
function page_end(): void { echo '</section></main><script src="assets/js/app.js"></script></body></html>'; }
