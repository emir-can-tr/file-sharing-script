<?php
// UTF-8 Encoding
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Dosya Paylaşım Sitesi Ayarları
define('SITE_NAME', 'Dosya Paylaşım');
define('SITE_URL', 'https://dosya.example.com');
define('FILES_DIR', __DIR__ . '/files/');
define('DATA_FILE', __DIR__ . '/data/files.json');

// Admin ayarları
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'kendi_sifren');

// Dosya boyutu sınırı (0 = sınırsız, bytes cinsinden)
define('MAX_FILE_SIZE', 0);

// Tehlikeli dosya uzantıları
define('BLOCKED_EXTENSIONS', ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar', 'htaccess', 'htpasswd']);

// İzin verilen dosya türleri
define('ALLOWED_EXTENSIONS', []);

// Session güvenliği
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
session_start();

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function getCsrfToken() {
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Dosya adını güvenli hale getir (Türkçe destekli)
function sanitizeFileName($filename) {
    if (function_exists('mb_convert_encoding')) {
        $filename = mb_convert_encoding($filename, 'UTF-8', 'auto');
    }
    $filename = preg_replace('/[^\p{L}\p{N}\s\-\_\.]/u', '', $filename);
    $filename = preg_replace('/\.+/', '.', $filename);
    $filename = trim($filename, '.');
    if (empty($filename)) {
        $filename = 'dosya_' . time();
    }
    return $filename;
}

// Klasörleri oluştur
if (!file_exists(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
}
if (!file_exists(__DIR__ . '/files')) {
    mkdir(__DIR__ . '/files', 0755, true);
}
if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Yardımcı fonksiyonlar
function getFiles() {
    $data = file_get_contents(DATA_FILE);
    return json_decode($data, true) ?: [];
}

function saveFiles($files) {
    file_put_contents(DATA_FILE, json_encode($files, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function generateUniqueId() {
    return bin2hex(random_bytes(8));
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

function getFileIcon($extension) {
    $icons = [
        'pdf' => '&#128196;',
        'doc' => '&#128221;', 'docx' => '&#128221;',
        'xls' => '&#128202;', 'xlsx' => '&#128202;',
        'ppt' => '&#128253;', 'pptx' => '&#128253;',
        'zip' => '&#128230;', 'rar' => '&#128230;', '7z' => '&#128230;',
        'jpg' => '&#128444;', 'jpeg' => '&#128444;', 'png' => '&#128444;', 'gif' => '&#128444;', 'webp' => '&#128444;',
        'mp3' => '&#127925;', 'wav' => '&#127925;', 'flac' => '&#127925;',
        'mp4' => '&#127916;', 'avi' => '&#127916;', 'mkv' => '&#127916;', 'mov' => '&#127916;',
        'txt' => '&#128195;',
        'html' => '&#128187;', 'css' => '&#127912;', 'js' => '&#9889;',
        'php' => '&#128024;', 'py' => '&#128013;',
        'exe' => '&#9881;', 'msi' => '&#9881;',
    ];
    return $icons[strtolower($extension)] ?? '&#128193;';
}
