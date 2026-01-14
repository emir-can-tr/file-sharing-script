<?php
require_once 'config.php';

header('Content-Type: application/json');

// CSRF kontrolü
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'Güvenlik hatası!']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'init':
        // Yükleme başlat - geçici dosya oluştur
        $fileName = sanitizeFileName($_POST['fileName'] ?? 'dosya');
        $fileSize = (int)($_POST['fileSize'] ?? 0);
        $extension = strtolower(pathinfo($_POST['fileName'] ?? '', PATHINFO_EXTENSION));

        // Tehlikeli uzantı kontrolü
        if (in_array($extension, BLOCKED_EXTENSIONS)) {
            echo json_encode(['success' => false, 'error' => 'Bu dosya türü güvenlik nedeniyle engellenmiştir.']);
            exit;
        }

        // Dosya boyutu kontrolü
        if (MAX_FILE_SIZE > 0 && $fileSize > MAX_FILE_SIZE) {
            echo json_encode(['success' => false, 'error' => 'Dosya boyutu çok büyük. Maksimum: ' . formatFileSize(MAX_FILE_SIZE)]);
            exit;
        }

        // Benzersiz upload ID oluştur
        $uploadId = bin2hex(random_bytes(16));
        $tempDir = __DIR__ . '/temp/';

        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Upload bilgilerini kaydet
        $uploadInfo = [
            'uploadId' => $uploadId,
            'fileName' => $fileName,
            'originalName' => $_POST['fileName'] ?? 'dosya',
            'fileSize' => $fileSize,
            'extension' => $extension,
            'uploadedChunks' => [],
            'totalChunks' => (int)($_POST['totalChunks'] ?? 1),
            'password' => $_POST['password'] ?? '',
            'createdAt' => time()
        ];

        file_put_contents($tempDir . $uploadId . '.json', json_encode($uploadInfo));

        echo json_encode(['success' => true, 'uploadId' => $uploadId]);
        break;

    case 'chunk':
        // Parça yükle
        $uploadId = $_POST['uploadId'] ?? '';
        $chunkIndex = (int)($_POST['chunkIndex'] ?? 0);
        $tempDir = __DIR__ . '/temp/';
        $infoFile = $tempDir . $uploadId . '.json';

        if (!preg_match('/^[a-f0-9]{32}$/', $uploadId) || !file_exists($infoFile)) {
            echo json_encode(['success' => false, 'error' => 'Geçersiz upload ID']);
            exit;
        }

        $uploadInfo = json_decode(file_get_contents($infoFile), true);

        if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Parça yüklenemedi']);
            exit;
        }

        // Parçayı kaydet
        $chunkFile = $tempDir . $uploadId . '_chunk_' . $chunkIndex;
        move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkFile);

        // Yüklenen parçaları güncelle
        $uploadInfo['uploadedChunks'][] = $chunkIndex;
        file_put_contents($infoFile, json_encode($uploadInfo));

        echo json_encode([
            'success' => true,
            'chunkIndex' => $chunkIndex,
            'uploaded' => count($uploadInfo['uploadedChunks']),
            'total' => $uploadInfo['totalChunks']
        ]);
        break;

    case 'complete':
        // Yükleme tamamla - parçaları birleştir
        $uploadId = $_POST['uploadId'] ?? '';
        $tempDir = __DIR__ . '/temp/';
        $infoFile = $tempDir . $uploadId . '.json';

        if (!preg_match('/^[a-f0-9]{32}$/', $uploadId) || !file_exists($infoFile)) {
            echo json_encode(['success' => false, 'error' => 'Geçersiz upload ID']);
            exit;
        }

        $uploadInfo = json_decode(file_get_contents($infoFile), true);

        // Tüm parçalar yüklendi mi kontrol et
        if (count($uploadInfo['uploadedChunks']) < $uploadInfo['totalChunks']) {
            echo json_encode(['success' => false, 'error' => 'Tüm parçalar yüklenmedi']);
            exit;
        }

        // Dosya ID oluştur
        $fileId = generateUniqueId();
        $storedName = $fileId . '.' . $uploadInfo['extension'];
        $destination = FILES_DIR . $storedName;

        // Parçaları birleştir
        $output = fopen($destination, 'wb');
        for ($i = 0; $i < $uploadInfo['totalChunks']; $i++) {
            $chunkFile = $tempDir . $uploadId . '_chunk_' . $i;
            if (file_exists($chunkFile)) {
                $chunk = fopen($chunkFile, 'rb');
                while (!feof($chunk)) {
                    fwrite($output, fread($chunk, 8192));
                }
                fclose($chunk);
                unlink($chunkFile); // Parçayı sil
            }
        }
        fclose($output);

        // Dosya bilgilerini kaydet
        $files = getFiles();
        $password = $uploadInfo['password'];

        $files[] = [
            'id' => $fileId,
            'original_name' => $uploadInfo['fileName'],
            'saved_name' => $storedName,
            'size' => filesize($destination),
            'extension' => $uploadInfo['extension'],
            'password' => $password ? password_hash($password, PASSWORD_DEFAULT) : null,
            'has_password' => !empty($password),
            'upload_date' => date('Y-m-d H:i:s'),
            'downloads' => 0
        ];
        saveFiles($files);

        // Temp dosyasını sil
        unlink($infoFile);

        $downloadUrl = SITE_URL . '/download.php?id=' . $fileId;

        echo json_encode([
            'success' => true,
            'fileId' => $fileId,
            'downloadUrl' => $downloadUrl
        ]);
        break;

    case 'cancel':
        // Yüklemeyi iptal et
        $uploadId = $_POST['uploadId'] ?? '';
        $tempDir = __DIR__ . '/temp/';

        if (preg_match('/^[a-f0-9]{32}$/', $uploadId)) {
            // Tüm chunk dosyalarını sil
            $files = glob($tempDir . $uploadId . '*');
            foreach ($files as $file) {
                unlink($file);
            }
        }

        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Geçersiz işlem']);
}
