<?php
/**
 * helpers.php  -  small reusable functions used by many pages.
 * (These are plain functions, not classes, because they are tiny tools.)
 */

/** Escape text before printing it in HTML. This stops XSS (malicious <script> tags). */
function e(?string $text): string
{
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

/** Build a full link inside the site, e.g. url('login.php') -> /printhub/login.php */
function url(string $path): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Send the browser to another page, then stop running this script. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Format a number as pesos, e.g. 1234.5 -> ₱1,234.50 */
function money($amount): string
{
    return '₱' . number_format((float)$amount, 2);
}

/** Put a value back into a form field after an error, safely escaped. */
function old(string $field): string
{
    return e($_POST[$field] ?? '');
}

// ---- Flash messages: a message shown once on the NEXT page ---------------
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_render(): void
{
    foreach ($_SESSION['flash'] ?? [] as $item) {
        // role="alert" lets screen readers announce the message
        echo '<div class="alert alert-' . e($item['type']) . '" role="alert">' . e($item['message']) . '</div>';
    }
    unset($_SESSION['flash']);
}

/** Show a list of validation errors (from Validator or ValidationException). */
function errors_render(array $errors): void
{
    if (!$errors) {
        return;
    }
    echo '<div class="alert alert-error" role="alert"><ul>';
    foreach ($errors as $message) {
        echo '<li>' . e($message) . '</li>';
    }
    echo '</ul></div>';
}

// ---- CSRF protection: proves a form really came from OUR site -----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Put this inside every <form method="post"> */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Call this first when handling a POST. Throws an exception if the token is wrong. */
function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        throw new PrintHub\ValidationException(['Your form expired. Please reload the page and try again.']);
    }
}

/** One menu link. Marks the current page with aria-current so it can be styled and announced. */
function nav_link(string $path, string $label): void
{
    $isCurrent = ($_SERVER['SCRIPT_NAME'] ?? '') === url($path);
    echo '<li><a href="' . e(url($path)) . '"' . ($isCurrent ? ' aria-current="page"' : '') . '>' . e($label) . '</a></li>';
}
