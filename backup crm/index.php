<?php
declare(strict_types=1);
require __DIR__ . '/app.php';
if (!current_user()) redirect('login.php');
redirect('dashboard.php');
