<?php
/** Stream an uploaded order file only to its customer or an administrator. */
require __DIR__ . '/includes/bootstrap.php';

use PrintHub\Auth;
use PrintHub\Order;

$user = Auth::requireLogin();
$itemId = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);
$item = $itemId ? (new Order())->findItemForDownload($itemId) : null;

// Use the same response for missing files and unauthorized requests.
if ($item === null || empty($item['file_path']) ||
    (!$user->isAdmin() && (int)$item['user_id'] !== $user->getId())) {
    http_response_code(404);
    exit('File not found.');
}

$relativePath = (string)$item['file_path'];
$uploadDirectory = realpath(ROOT_PATH . '/uploads');
$filePath = realpath(ROOT_PATH . '/' . $relativePath);
$insideUploads = $uploadDirectory !== false && $filePath !== false &&
    strncasecmp($filePath, $uploadDirectory . DIRECTORY_SEPARATOR, strlen($uploadDirectory . DIRECTORY_SEPARATOR)) === 0;

if (strpos($relativePath, 'uploads/') !== 0 || !$insideUploads || !is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    exit('File not found.');
}

$originalName = basename((string)$item['file_name']);
$fallbackName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName) ?: 'download';
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = $finfo ? finfo_file($finfo, $filePath) : false;
if ($finfo) {
    finfo_close($finfo);
}

header('Content-Type: ' . ($mimeType ?: 'application/octet-stream'));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="' . $fallbackName . '"; filename*=UTF-8\'\'' . rawurlencode($originalName));
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, no-store, max-age=0');
readfile($filePath);
exit;
