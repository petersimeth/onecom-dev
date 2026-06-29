<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

// User management now lives entirely in admin.php (see AdminUserService), which
// is a superset of the old standalone page. This file is kept only so existing
// bookmarks and links keep working; it forwards to the admin dashboard instead
// of duplicating privileged user/role mutation logic.
shopSignalRequireAdmin();

header('Location: ' . shopSignalAssetUrl('admin.php'), true, 302);
exit;
