<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';

logout_user();
header('Location: /soma_cashflow/public/login.php');
exit;
