<?php
require_once 'config.php';

$fileId = $_GET['id'] ?? '';
$files = getFiles();
$error = '';
$file = null;
$needsPassword = false;
$downloadReady = false;

// Dosyayı bul
foreach ($files as $key => $f) {
    if ($f['id'] === $fileId) {
        $file = $f;
        $fileKey = $key;
        break;
    }
}

if (empty($fileId) || !$file) {
    $error = 'Dosya bulunamadı veya silinmiş olabilir.';
} else {
    $filePath = FILES_DIR . $file['saved_name'];

    if (!file_exists($filePath)) {
        $error = 'Dosya sunucuda bulunamadı.';
    } elseif ($file['has_password']) {
        $needsPassword = true;

        // Şifre kontrolü
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
            if (password_verify($_POST['password'], $file['password'])) {
                $needsPassword = false;
                $downloadReady = true;
            } else {
                $error = 'Yanlış şifre!';
            }
        }
    } else {
        $downloadReady = true;
    }

    // Dosya indirme
    if ($downloadReady && isset($_GET['download'])) {
        // İndirme sayısını artır
        $files[$fileKey]['downloads']++;
        saveFiles($files);

        // Güvenli dosya adı için header injection koruması
        $safeFileName = preg_replace('/["\r\n]/', '', $file['original_name']);

        // Dosyayı indir
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $safeFileName . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        ob_clean();
        flush();
        readfile($filePath);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $file ? htmlspecialchars($file['original_name']) : 'Dosya Bulunamadı' ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <a href="index.php" class="logo-link">
                <h1><?= SITE_NAME ?></h1>
            </a>
        </header>

        <?php if ($error && !$needsPassword): ?>
        <div class="message error">
            <?= $error ?>
        </div>
        <div class="back-link">
            <a href="index.php" class="btn-upload">Ana Sayfaya Dön</a>
        </div>

        <?php elseif ($file): ?>
        <div class="download-section">
            <div class="file-preview">
                <span class="file-icon-large"><?= getFileIcon($file['extension']) ?></span>
                <h2><?= htmlspecialchars($file['original_name']) ?></h2>
                <div class="file-stats">
                    <span><?= formatFileSize($file['size']) ?></span>
                    <span>&#8226;</span>
                    <span><?= date('d.m.Y H:i', strtotime($file['upload_date'])) ?></span>
                    <span>&#8226;</span>
                    <span><?= $file['downloads'] ?> indirme</span>
                </div>
            </div>

            <?php if ($needsPassword): ?>
            <div class="password-form">
                <?php if ($error): ?>
                <div class="message error"><?= $error ?></div>
                <?php endif; ?>
                <p>Bu dosya şifre ile korunuyor.</p>
                <form method="POST">
                    <input type="password" name="password" placeholder="Şifreyi girin" required autofocus>
                    <button type="submit" class="btn-upload">Şifreyi Doğrula</button>
                </form>
            </div>

            <?php else: ?>
            <a href="?id=<?= $fileId ?>&download=1" class="btn-download">
                <span class="download-icon">&#11015;</span>
                İndir
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <footer>
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. Tüm hakları saklıdır.</p>
            <p>Made by <a href="https://emircan.tr" target="_blank" style="color: #667eea;">Emir Can</a></p>
        </footer>
    </div>
</body>
</html>
