<?php
// Dosya Paylaşım Sitesi Ayarları (Simple Version)
define('SITE_NAME', 'Dosya Paylaşım');
define('SITE_URL', 'https://yourdomain.com');
define('FILES_DIR', __DIR__ . '/files/');
define('DATA_FILE', __DIR__ . '/data/files.json');

define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'your_secure_password');
define('MAX_FILE_SIZE', 0);
define('BLOCKED_EXTENSIONS', ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar', 'htaccess', 'htpasswd']);
define('ALLOWED_EXTENSIONS', []);

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function getCsrfToken() { return $_SESSION['csrf_token']; }
function verifyCsrfToken($token) { return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token); }

function sanitizeFileName($filename) {
    $filename = preg_replace('/[^\p{L}\p{N}\s\-\_\.]/u', '', $filename);
    $filename = preg_replace('/\.+/', '.', $filename);
    $filename = trim($filename, '.');
    if (empty($filename)) { $filename = 'dosya_' . time(); }
    return $filename;
}

if (!file_exists(__DIR__ . '/data')) { mkdir(__DIR__ . '/data', 0755, true); }
if (!file_exists(__DIR__ . '/files')) { mkdir(__DIR__ . '/files', 0755, true); }
if (!file_exists(DATA_FILE)) { file_put_contents(DATA_FILE, json_encode([], JSON_PRETTY_PRINT)); }

function getFiles() { return json_decode(file_get_contents(DATA_FILE), true) ?: []; }
function saveFiles($files) { file_put_contents(DATA_FILE, json_encode($files, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); }
function generateUniqueId() { return bin2hex(random_bytes(8)); }

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

function getFileIcon($extension) {
    $icons = ['pdf' => '📄', 'doc' => '📝', 'docx' => '📝', 'xls' => '📊', 'xlsx' => '📊', 'ppt' => '📽️', 'pptx' => '📽️', 'zip' => '📦', 'rar' => '📦', '7z' => '📦', 'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️', 'webp' => '🖼️', 'mp3' => '🎵', 'wav' => '🎵', 'flac' => '🎵', 'mp4' => '🎬', 'avi' => '🎬', 'mkv' => '🎬', 'mov' => '🎬', 'txt' => '📃', 'html' => '🌐', 'css' => '🎨', 'js' => '⚡', 'exe' => '⚙️', 'msi' => '⚙️'];
    return $icons[strtolower($extension)] ?? '📁';
}
