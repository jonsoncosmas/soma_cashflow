<?php
declare(strict_types=1);

require __DIR__ . '/../includes/session.php';

logout_user();

// Clear cached pages and local storage on logout - important on a shared
// device, since the service worker's page cache is keyed by URL, not by
// user, so without this the next person to log in could briefly see a
// stale cached page from this session while offline.
header('Clear-Site-Data: "cache", "storage"');
header('Location: /soma_cashflow/public/login.php');
exit;
