<?php
require_once 'config.php';

$error = '';
$success = '';

// Çıkış yap
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    header('Location: admin.php');
    exit;
}

// Giriş kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Güvenlik hatası!';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: admin.php');
            exit;
        } else {
            $error = 'Kullanıcı adı veya şifre hatalı!';
        }
    }
}

$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Admin işlemleri
if ($isLoggedIn) {
    $files = getFiles();

    // Dosya silme
    if (isset($_POST['delete']) && isset($_POST['file_id'])) {
        if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
            $error = 'Güvenlik hatası!';
        } else {
            $fileId = $_POST['file_id'];
            // Dosyayı ID ile bul
            $fileIndex = null;
            foreach ($files as $key => $f) {
                if ($f['id'] === $fileId) {
                    $fileIndex = $key;
                    break;
                }
            }
            if ($fileIndex !== null) {
                $filePath = FILES_DIR . $files[$fileIndex]['saved_name'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                array_splice($files, $fileIndex, 1);
                saveFiles($files);
                $success = 'Dosya başarıyla silindi!';
                $files = getFiles();
            }
        }
    }

    // Toplu silme
    if (isset($_POST['bulk_delete']) && isset($_POST['selected_files'])) {
        if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
            $error = 'Güvenlik hatası!';
        } else {
            $selectedFiles = $_POST['selected_files'];
            $deletedCount = 0;
            // Dosyaları ID ile bul ve sil
            foreach ($selectedFiles as $fileId) {
                foreach ($files as $key => $f) {
                    if ($f['id'] === $fileId) {
                        $filePath = FILES_DIR . $f['saved_name'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                        array_splice($files, $key, 1);
                        $deletedCount++;
                        break;
                    }
                }
            }
            saveFiles($files);
            $success = $deletedCount . ' dosya silindi!';
            $files = getFiles();
        }
    }

    // Dosyaları tarihe göre sırala (yeniden eskiye)
    usort($files, fn($a, $b) => strtotime($b['upload_date']) - strtotime($a['upload_date']));

    // İstatistikler
    $totalFiles = count($files);
    $totalSize = array_sum(array_column($files, 'size'));
    $totalDownloads = array_sum(array_column($files, 'downloads'));
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Panel - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .logout-btn:hover {
            background: #c82333;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: rgba(102, 126, 234, 0.2);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #667eea;
        }
        .stat-label {
            color: #a0a0a0;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .admin-table th,
        .admin-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .admin-table th {
            background: rgba(102, 126, 234, 0.2);
            font-weight: 600;
        }
        .admin-table tr:hover {
            background: rgba(255,255,255,0.05);
        }
        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        .btn-delete:hover {
            background: #c82333;
        }
        .btn-copy {
            background: #28a745;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        .btn-copy:hover {
            background: #218838;
        }
        .bulk-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: center;
        }
        .checkbox-cell {
            width: 40px;
        }
        .login-form {
            max-width: 400px;
            margin: 50px auto;
        }
        .login-form input {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 15px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            color: white;
            font-size: 1rem;
        }
        .login-form input:focus {
            outline: none;
            border-color: #667eea;
        }
        .password-badge {
            background: #ffc107;
            color: #000;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        .file-name-cell {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .table-responsive {
            overflow-x: auto;
        }
        @media (max-width: 768px) {
            .admin-table th:nth-child(4),
            .admin-table td:nth-child(4),
            .admin-table th:nth-child(5),
            .admin-table td:nth-child(5) {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$isLoggedIn): ?>
        <!-- Giriş Formu -->
        <header>
            <h1><?= SITE_NAME ?></h1>
            <p>Admin Girişi</p>
        </header>

        <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="upload-section login-form">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <input type="text" name="username" placeholder="Kullanıcı Adı" required autofocus>
                <input type="password" name="password" placeholder="Şifre" required>
                <button type="submit" name="login" class="btn-upload" style="width: 100%;">Giriş Yap</button>
            </form>
        </div>

        <?php else: ?>
        <!-- Admin Panel -->
        <div class="admin-header">
            <div>
                <h1><?= SITE_NAME ?></h1>
                <p>Admin Panel</p>
            </div>
            <div>
                <a href="/" class="btn-upload" style="margin-right: 10px;">Siteye Git</a>
                <a href="?logout=1" class="logout-btn">Çıkış Yap</a>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="message success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Istatistikler -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $totalFiles ?></div>
                <div class="stat-label">Toplam Dosya</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= formatFileSize($totalSize) ?></div>
                <div class="stat-label">Toplam Boyut</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $totalDownloads ?></div>
                <div class="stat-label">Toplam İndirme</div>
            </div>
        </div>

        <!-- Dosya Listesi -->
        <div class="upload-section">
            <h2>Dosya Yönetimi</h2>

            <?php if (empty($files)): ?>
            <p style="color: #a0a0a0; text-align: center; padding: 30px;">Henüz dosya yüklenmemiş.</p>
            <?php else: ?>

            <form method="POST" id="bulkForm">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                <div class="bulk-actions">
                    <label>
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()"> Tümünü Seç
                    </label>
                    <button type="submit" name="bulk_delete" class="btn-delete" onclick="return confirm('Seçili dosyaları silmek istediğinize emin misiniz?')">Seçilenleri Sil</button>
                </div>

                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th class="checkbox-cell"></th>
                                <th>Dosya Adı</th>
                                <th>Boyut</th>
                                <th>Tarih</th>
                                <th>İndirme</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $file): ?>
                            <tr>
                                <td class="checkbox-cell">
                                    <input type="checkbox" name="selected_files[]" value="<?= $file['id'] ?>" class="file-checkbox">
                                </td>
                                <td class="file-name-cell" title="<?= htmlspecialchars($file['original_name']) ?>">
                                    <?= getFileIcon($file['extension']) ?>
                                    <?= htmlspecialchars($file['original_name']) ?>
                                    <?php if ($file['has_password']): ?>
                                    <span class="password-badge">Şifreli</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatFileSize($file['size']) ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($file['upload_date'])) ?></td>
                                <td><?= $file['downloads'] ?></td>
                                <td>
                                    <button type="button" class="btn-copy" onclick="copyLink('<?= $file['id'] ?>')">Link</button>
                                    <button type="submit" name="delete" class="btn-delete" onclick="return confirm('Bu dosyayı silmek istediğinize emin misiniz?')">Sil</button>
                                    <input type="hidden" name="file_id" value="<?= $file['id'] ?>" disabled>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <footer>
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. Tüm hakları saklıdır.</p>
            <p>Made by <a href="https://emircan.tr" target="_blank" style="color: #667eea;">Emir Can</a></p>
        </footer>
    </div>

    <script>
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.file-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
        }

        function copyLink(fileId) {
            const url = '<?= SITE_URL ?>/download.php?id=' + fileId;
            navigator.clipboard.writeText(url).then(() => {
                alert('Link kopyalandı!');
            });
        }

        // Tek dosya silme için form düzeltmesi
        document.querySelectorAll('.btn-delete[name="delete"]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const row = this.closest('tr');
                const hiddenInput = row.querySelector('input[name="file_id"]');
                document.querySelectorAll('input[name="file_id"]').forEach(inp => inp.disabled = true);
                hiddenInput.disabled = false;
            });
        });
    </script>
</body>
</html>
