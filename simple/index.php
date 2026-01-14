<?php
require_once 'config.php';

$files = getFiles();

// Son yüklenen dosyalar (en son 10 tanesi, şifresiz olanlar)
$recentFiles = array_filter($files, fn($f) => !$f['has_password']);
usort($recentFiles, fn($a, $b) => strtotime($b['upload_date']) - strtotime($a['upload_date']));
$recentFiles = array_slice($recentFiles, 0, 10);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dosyalarınızı hızlı ve güvenli paylaşın. Ücretsiz dosya yükleme ve paylaşma servisi.">
    <title><?= SITE_NAME ?> - Dosya Paylaşım</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .progress-container {
            display: none;
            margin-top: 20px;
        }
        .progress-bar {
            width: 100%;
            height: 30px;
            background: rgba(255,255,255,0.1);
            border-radius: 15px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            width: 0%;
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .progress-text {
            margin-top: 10px;
            text-align: center;
            color: #a0a0a0;
            font-size: 0.9rem;
        }
        .upload-speed {
            color: #667eea;
        }
        .btn-cancel {
            background: #dc3545;
            margin-top: 15px;
        }
        .btn-cancel:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><?= SITE_NAME ?></h1>
            <p>Dosyalarınızı hızlı ve güvenli paylaşın</p>
        </header>

        <div id="messageContainer"></div>

        <div class="upload-section">
            <h2>Dosya Yükle</h2>
            <form id="uploadForm" class="upload-form">
                <input type="hidden" name="csrf_token" id="csrfToken" value="<?= getCsrfToken() ?>">
                <div class="drop-zone" id="dropZone">
                    <input type="file" name="file" id="fileInput" style="display: none;">
                    <div class="drop-zone-text" id="dropZoneText">
                        <span class="icon">📁</span>
                        <p>Dosyayı buraya sürükleyin veya tıklayın</p>
                        <p class="hint">Sunucu limitine kadar yükleyebilirsiniz</p>
                    </div>
                    <div class="file-info" id="fileInfo" style="display: none;"></div>
                </div>

                <div class="progress-container" id="progressContainer">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill">0%</div>
                    </div>
                    <div class="progress-text">
                        <span id="progressText">Yükleniyor...</span>
                        <span class="upload-speed" id="uploadSpeed"></span>
                    </div>
                    <button type="button" class="btn-upload btn-cancel" id="cancelBtn">İptal Et</button>
                </div>

                <div class="password-section" id="passwordSection">
                    <label for="password">
                        <input type="checkbox" id="usePassword" onchange="togglePassword()">
                        Şifre ile koru
                    </label>
                    <input type="password" name="password" id="password" placeholder="Şifre (isteğe bağlı)" disabled>
                </div>

                <button type="submit" class="btn-upload" id="uploadBtn">Yükle</button>
            </form>
        </div>

        <?php if (!empty($recentFiles)): ?>
        <div class="recent-section">
            <h2>Son Yüklenen Dosyalar</h2>
            <div class="file-list">
                <?php foreach ($recentFiles as $file): ?>
                <a href="download.php?id=<?= $file['id'] ?>" class="file-item">
                    <span class="file-icon"><?= getFileIcon($file['extension']) ?></span>
                    <div class="file-details">
                        <span class="file-name"><?= htmlspecialchars($file['original_name']) ?></span>
                        <span class="file-meta"><?= formatFileSize($file['size']) ?> • <?= date('d.m.Y', strtotime($file['upload_date'])) ?></span>
                    </div>
                    <span class="download-count"><?= $file['downloads'] ?> indirme</span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <footer>
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. Tüm hakları saklıdır.</p>
            <p>Made by <a href="https://emircan.tr" target="_blank" style="color: #667eea;">Emir Can</a></p>
        </footer>
    </div>

    <script>
        const CHUNK_SIZE = 25 * 1024 * 1024; // 25MB parçalar
        let currentUpload = null;
        let uploadCancelled = false;

        const dropZone = document.getElementById('dropZone');
        const dropZoneText = document.getElementById('dropZoneText');
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('fileInfo');
        const uploadForm = document.getElementById('uploadForm');
        const progressContainer = document.getElementById('progressContainer');
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');
        const uploadSpeed = document.getElementById('uploadSpeed');
        const uploadBtn = document.getElementById('uploadBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const passwordSection = document.getElementById('passwordSection');
        const messageContainer = document.getElementById('messageContainer');
        const csrfToken = document.getElementById('csrfToken').value;

        // Drag & Drop
        dropZone.addEventListener('click', () => fileInput.click());

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            if (e.dataTransfer.files[0]) {
                fileInput.files = e.dataTransfer.files;
                showFileInfo(e.dataTransfer.files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files[0]) {
                showFileInfo(e.target.files[0]);
            }
        });

        function showFileInfo(file) {
            const size = formatBytes(file.size);
            fileInfo.innerHTML = `<strong>${file.name}</strong> (${size})`;
            fileInfo.style.display = 'block';
            dropZoneText.style.display = 'none';
        }

        function formatBytes(bytes) {
            if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
            if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
            return bytes + ' B';
        }

        function formatTime(seconds) {
            if (seconds < 60) return Math.round(seconds) + ' sn';
            if (seconds < 3600) {
                const mins = Math.floor(seconds / 60);
                const secs = Math.round(seconds % 60);
                return `${mins} dk ${secs} sn`;
            }
            const hours = Math.floor(seconds / 3600);
            const mins = Math.floor((seconds % 3600) / 60);
            return `${hours} saat ${mins} dk`;
        }

        function togglePassword() {
            const checkbox = document.getElementById('usePassword');
            const passwordInput = document.getElementById('password');
            passwordInput.disabled = !checkbox.checked;
            if (checkbox.checked) passwordInput.focus();
        }

        function showMessage(text, type) {
            messageContainer.innerHTML = `<div class="message ${type}">${text}</div>`;
        }

        // Form submit
        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const file = fileInput.files[0];
            if (!file) {
                showMessage('Lütfen bir dosya seçin.', 'error');
                return;
            }

            uploadCancelled = false;
            const password = document.getElementById('password').value;
            const totalChunks = Math.ceil(file.size / CHUNK_SIZE);

            // UI güncelle
            uploadBtn.style.display = 'none';
            passwordSection.style.display = 'none';
            progressContainer.style.display = 'block';
            messageContainer.innerHTML = '';

            try {
                // 1. Upload başlat
                const initResponse = await fetch('upload_handler.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        action: 'init',
                        csrf_token: csrfToken,
                        fileName: file.name,
                        fileSize: file.size,
                        totalChunks: totalChunks,
                        password: password
                    })
                });

                const initData = await initResponse.json();
                if (!initData.success) {
                    throw new Error(initData.error);
                }

                currentUpload = initData.uploadId;
                let uploadedBytes = 0;
                let startTime = Date.now();

                // 2. Parçaları yükle
                for (let i = 0; i < totalChunks; i++) {
                    if (uploadCancelled) {
                        throw new Error('Yükleme iptal edildi.');
                    }

                    const start = i * CHUNK_SIZE;
                    const end = Math.min(start + CHUNK_SIZE, file.size);
                    const chunk = file.slice(start, end);

                    const formData = new FormData();
                    formData.append('action', 'chunk');
                    formData.append('csrf_token', csrfToken);
                    formData.append('uploadId', currentUpload);
                    formData.append('chunkIndex', i);
                    formData.append('chunk', chunk);

                    const chunkResponse = await fetch('upload_handler.php', {
                        method: 'POST',
                        body: formData
                    });

                    const chunkData = await chunkResponse.json();
                    if (!chunkData.success) {
                        throw new Error(chunkData.error);
                    }

                    uploadedBytes += chunk.size;
                    const percent = Math.round((uploadedBytes / file.size) * 100);
                    progressFill.style.width = percent + '%';
                    progressFill.textContent = percent + '%';

                    // Hız hesapla
                    const elapsed = (Date.now() - startTime) / 1000;
                    const speed = uploadedBytes / elapsed;
                    const remaining = (file.size - uploadedBytes) / speed;

                    progressText.textContent = `${formatBytes(uploadedBytes)} / ${formatBytes(file.size)}`;
                    uploadSpeed.textContent = ` • ${formatBytes(speed)}/s • ${formatTime(remaining)} kaldı`;
                }

                // 3. Upload tamamla
                const completeResponse = await fetch('upload_handler.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        action: 'complete',
                        csrf_token: csrfToken,
                        uploadId: currentUpload
                    })
                });

                const completeData = await completeResponse.json();
                if (!completeData.success) {
                    throw new Error(completeData.error);
                }

                // Başarılı
                progressContainer.style.display = 'none';
                showMessage(`Dosya başarıyla yüklendi!<br><strong>İndirme Linki:</strong><br><input type="text" value="${completeData.downloadUrl}" class="link-input" readonly onclick="this.select(); document.execCommand('copy');">`, 'success');

                // Formu sıfırla
                resetForm();

            } catch (error) {
                progressContainer.style.display = 'none';
                uploadBtn.style.display = 'block';
                passwordSection.style.display = 'block';
                showMessage(error.message, 'error');

                // İptal edildi ise temizle
                if (currentUpload) {
                    fetch('upload_handler.php', {
                        method: 'POST',
                        body: new URLSearchParams({
                            action: 'cancel',
                            csrf_token: csrfToken,
                            uploadId: currentUpload
                        })
                    });
                }
            }
        });

        // İptal butonu
        cancelBtn.addEventListener('click', () => {
            uploadCancelled = true;
        });

        function resetForm() {
            fileInput.value = '';
            fileInfo.style.display = 'none';
            dropZoneText.style.display = 'block';
            uploadBtn.style.display = 'block';
            passwordSection.style.display = 'block';
            document.getElementById('password').value = '';
            document.getElementById('usePassword').checked = false;
            document.getElementById('password').disabled = true;
            progressFill.style.width = '0%';
            progressFill.textContent = '0%';
            currentUpload = null;
        }
    </script>
</body>
</html>
