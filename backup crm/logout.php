<?php
require __DIR__.'/app.php';if(current_user())audit('logout','Signed out');$_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',$p['secure'],$p['httponly']);}session_destroy();redirect('login.php');
