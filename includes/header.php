<?php
/**
 * header.php  -  the top of every page: <head>, logo and menu.
 * A page sets $pageTitle before including this file.
 */
use PrintHub\Auth;

$user      = Auth::user();                // Customer, Admin, or null (visitor)
$pageTitle = $pageTitle ?? 'Welcome';
$isServicesLanding = $pageTitle === 'Services and Prices';
$bodyClass = $bodyClass ?? '';
$userInitials = '';
if ($user !== null) {
    $nameParts = preg_split('/\s+/u', trim($user->getName()), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($nameParts) {
        $userInitials = mb_strtoupper(mb_substr($nameParts[0], 0, 1, 'UTF-8'), 'UTF-8');
        if (count($nameParts) > 1) {
            $userInitials .= mb_strtoupper(mb_substr($nameParts[count($nameParts) - 1], 0, 1, 'UTF-8'), 'UTF-8');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e(SHOP_TAGLINE) ?>">
    <title><?= e($pageTitle) ?> | <?= e(SHOP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>?v=<?= (int)filemtime(ROOT_PATH . '/assets/css/style.css') ?>">
    <?php if ($isServicesLanding): ?><link rel="stylesheet" href="<?= e(url('assets/css/services.css')) ?>?v=<?= (int)filemtime(ROOT_PATH . '/assets/css/services.css') ?>"><?php endif; ?>
</head>
<body<?= $bodyClass !== '' ? ' class="' . e($bodyClass) . '"' : '' ?>>
<a class="skip-link" href="#main">Skip to main content</a>

<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= $isServicesLanding ? '#services-main' : e(url('index.php')) ?>"<?= $isServicesLanding ? ' aria-label="Skip to services content"' : '' ?>>
            <svg width="32" height="32" viewBox="0 0 32 32" role="img" aria-label="PrintHub logo">
                <rect x="8" y="3" width="16" height="9" rx="1" fill="#fff"/>
                <rect x="3" y="11" width="26" height="12" rx="3" fill="#f59e0b"/>
                <rect x="8" y="18" width="16" height="11" rx="1" fill="#fff" stroke="#1e3a8a"/>
            </svg>
            <span><?= e(SHOP_NAME) ?></span>
        </a>
        <nav aria-label="Main navigation">
            <ul class="nav">
                <?php
                nav_link('index.php', 'Home');
                if ($user !== null && $user->isAdmin()) {
                    nav_link('admin/index.php', 'Orders');
                    nav_link('admin/services.php', 'Services');
                    nav_link('admin/promotions.php', 'Promotions');
                } else {
                    nav_link('services.php', 'Services');
                    if ($user !== null) {
                        nav_link('order.php', 'Order Now');
                        nav_link('my_orders.php', 'My Orders');
                    }
                }
                if ($user === null) {
                    nav_link('login.php', 'Login');
                    nav_link('register.php', 'Register');
                } else {
                    echo '<li class="nav-account"><details class="account-menu">'
                       . '<summary class="account-initials" aria-label="Account menu, ' . e($userInitials) . '" title="Account menu">' . e($userInitials) . '</summary>'
                       . '<div class="account-menu-panel"><a href="' . e(url('logout.php')) . '">Log out</a></div>'
                       . '</details></li>';
                }
                ?>
            </ul>
        </nav>
    </div>
</header>

<main id="main">
    <div class="container flash-messages">
        <?php flash_render(); ?>
    </div>
