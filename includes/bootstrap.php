<?php
/**
 * bootstrap.php  -  the FIRST file every page includes.
 * It prepares everything the site needs before the page starts.
 */

// 1. Where is the project on the hard drive?
define('ROOT_PATH', dirname(__DIR__));

// 2. Load settings and helper functions
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/helpers.php';

// 3. AUTOLOADER: when PHP meets "new PrintHub\Service()", it calls this function,
//    which loads src/Service.php for us. No need to write require for every class.
spl_autoload_register(function (string $class): void {
    $prefix = 'PrintHub\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return; // not one of our classes
    }
    $file = ROOT_PATH . '/src/' . substr($class, strlen($prefix)) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// 4. Start the session (this is how PHP remembers who is logged in)
session_start();

// 5. Last-line-of-defence error handler. Any exception that nobody caught ends up here,
//    so visitors see a friendly page instead of a scary technical error.
set_exception_handler(function (Throwable $e): void {
    error_log($e->getMessage());
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Error</title>'
       . '<link rel="stylesheet" href="' . e(url('assets/css/style.css')) . '"></head>'
       . '<body><main class="container"><h1>Something went wrong</h1>'
       . '<p>Sorry, the page could not be loaded. Please try again later.</p>';
    if (DEBUG) {
        echo '<p class="muted"><strong>Debug info:</strong> ' . e($e->getMessage()) . '</p>';
    }
    echo '<p><a class="btn" href="' . e(url('index.php')) . '">Back to Home</a></p></main></body></html>';
    exit;
});
