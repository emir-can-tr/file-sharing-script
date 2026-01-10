<?php
require_once 'config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Güvenlik hatası. Sayfayı yenileyip tekrar deneyin.';
        $messageType = 'error';
    } else {
        $file = $_FILES['file'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $originalName = sanitizeFileName($file['name']);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (in_array($extension, BLOCKED_EXTENSIONS)) {
                $message = 'Bu dosya türü güvenlik nedeniyle engellenmiştir.';
                $messageType = 'error';
            } else {
                $uniqueId = generateUniqueId();
                $savedName = $uniqueId . '.' . $extension;
                $targetPath = FILES_DIR . $savedName;
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $files = getFiles();
                    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
                    $files[$uniqueId] = [
                        'id' => $uniqueId, 'original_name' => $originalName, 'stored_name' => $savedName,
                        'extension' => $extension, 'size' => $file['size'], 'upload_date' => date('Y-m-d H:i:s'),
                        'downloads' => 0, 'has_password' => !empty($password), 'password' => $password
                    ];
                    saveFiles($files);
                    $downloadUrl = SITE_URL . '/download.php?id=' . $uniqueId;
                    $message = 'Dosya başarıyla yüklendi!<br><strong>İndirme Linki:</strong><br><input type="text" value="' . $downloadUrl . '" class="link-input" readonly onclick="this.select(); document.execCommand(\'copy\');">';
                    $messageType = 'success';
                } else {
                    $message = 'Dosya yüklenirken bir hata oluştu.';
                    $messageType = 'error';
                }
            }
        } else {
            $errorMessages = [UPLOAD_ERR_INI_SIZE => 'Dosya sunucu limitini aşıyor.', UPLOAD_ERR_FORM_SIZE => 'Dosya form limitini aşıyor.', UPLOAD_ERR_PARTIAL => 'Dosya kısmen yüklendi.', UPLOAD_ERR_NO_FILE => 'Dosya seçilmedi.', UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör bulunamadı.', UPLOAD_ERR_CANT_WRITE => 'Dosya yazılamadı.'];
            $message = $errorMessages[$file['error']] ?? 'Bilinmeyen hata.';
            $messageType = 'error';
        }
    }
}

$files = getFiles();
$recentFiles = array_filter($files, fn($f) => !$f['has_password']);
usort($recentFiles, fn($a, $b) => strtotime($b['upload_date']) - strtotime($a['upload_date']));
$recentFiles = array_slice($recentFiles, 0, 10);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dosyalarınızı hızlı ve güvenli paylaşın.">
    <title><?= SITE_NAME ?> - Dosya Paylaşım</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header><h1><?= SITE_NAME ?></h1><p>Dosyalarınızı hızlı ve güvenli paylaşın</p></header>
        <?php if ($message): ?><div class="message <?= $messageType ?>"><?= $message ?></div><?php endif; ?>
        <div class="upload-section">
            <h2>Dosya Yükle</h2>
            <form action="" method="POST" enctype="multipart/form-data" class="upload-form">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <div class="drop-zone" id="dropZone">
                    <input type="file" name="file" id="fileInput" style="display: none;" required>
                    <div class="drop-zone-text" id="dropZoneText"><span class="icon">📁</span><p>Dosyayı buraya sürükleyin veya tıklayın</p><p class="hint">Sunucu limitine kadar yükleyebilirsiniz</p></div>
                    <div class="file-info" id="fileInfo" style="display: none;"></div>
                </div>
                <div class="password-section"><label for="password"><input type="checkbox" id="usePassword" onchange="togglePassword()"> Şifre ile koru</label><input type="password" name="password" id="password" placeholder="Şifre (isteğe bağlı)" disabled></div>
                <button type="submit" class="btn-upload">Yükle</button>
            </form>
        </div>
        <?php if (!empty($recentFiles)): ?>
        <div class="recent-section">
            <h2>Son Yüklenen Dosyalar</h2>
            <div class="file-list">
                <?php foreach ($recentFiles as $file): ?>
                <a href="download.php?id=<?= $file['id'] ?>" class="file-item">
                    <span class="file-icon"><?= getFileIcon($file['extension']) ?></span>
                    <div class="file-details"><span class="file-name"><?= htmlspecialchars($file['original_name']) ?></span><span class="file-meta"><?= formatFileSize($file['size']) ?> • <?= date('d.m.Y', strtotime($file['upload_date'])) ?></span></div>
                    <span class="download-count"><?= $file['downloads'] ?> indirme</span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <footer><p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. Tüm hakları saklıdır.</p><p>Made by <a href="https://emircan.tr" target="_blank" style="color: #667eea;">Emir Can</a></p></footer>
    </div>
    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('fileInfo');
        const dropZoneText = document.getElementById('dropZoneText');
        dropZone.addEventListener('click', () => fileInput.click());
        dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
        dropZone.addEventListener('drop', (e) => { e.preventDefault(); dropZone.classList.remove('dragover'); if (e.dataTransfer.files[0]) { fileInput.files = e.dataTransfer.files; showFileInfo(e.dataTransfer.files[0]); } });
        fileInput.addEventListener('change', (e) => { if (e.target.files[0]) showFileInfo(e.target.files[0]); });
        function showFileInfo(file) { fileInfo.innerHTML = `<strong>${file.name}</strong> (${formatBytes(file.size)})`; fileInfo.style.display = 'block'; dropZoneText.style.display = 'none'; }
        function formatBytes(bytes) { if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB'; if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB'; if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB'; return bytes + ' B'; }
        function togglePassword() { const checkbox = document.getElementById('usePassword'); const passwordInput = document.getElementById('password'); passwordInput.disabled = !checkbox.checked; if (checkbox.checked) passwordInput.focus(); }
    </script>
</body>
</html>
