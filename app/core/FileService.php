<?php

declare(strict_types=1);

final class FileService
{
    private const UPLOAD_DIR = __DIR__ . '/../../storage/uploads';

    public static function ensureDir(): void
    {
        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }
    }

    /**
     * Specialized avatar upload with resizing to save inodes and space.
     */
    public static function saveAvatar(int $userId, string $base64Data): string
    {
        $dir = __DIR__ . '/../../public/assets/images/avatars';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        list($type, $data) = explode(';', $base64Data);
        list(, $data)      = explode(',', $data);
        $decoded = base64_decode($data);

        $srcImg = imagecreatefromstring($decoded);
        if (!$srcImg) throw new Exception('Invalid image data.');

        $width = imagesx($srcImg);
        $height = imagesy($srcImg);
        $size = 256; // Standardized avatar size

        $dstImg = imagecreatetruecolor($size, $size);
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);

        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $size, $size, $width, $height);

        $path = $dir . '/' . $userId . '.png';
        imagepng($dstImg, $path, 9);

        imagedestroy($srcImg);
        imagedestroy($dstImg);

        return '/public/assets/images/avatars/' . $userId . '.png?t=' . time();
    }

    /**
     * Generic document upload
     */
    public static function uploadDocument(array $file, int $ownerId, string $category = 'general'): array
    {
        self::ensureDir();

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $newName = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = self::UPLOAD_DIR . '/' . $newName;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new Exception('Failed to move uploaded file.');
        }

        // Return metadata for DB insertion
        return [
            'original_name' => $file['name'],
            'storage_path' => $newName,
            'file_size' => $file['size'],
            'file_type' => $file['type'],
            'category' => $category,
            'user_id' => $ownerId
        ];
    }

    public static function saveDocument(int $uploaderId, array $file, string $category, ?int $patientId, string $description): array
    {
        $allowedTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('File type not allowed. Supported: PDF, JPG, PNG, WEBP, DOCX.');
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('File too large. Max size: 5MB.');
        }

        $uploadDir = __DIR__ . '/../../public/assets/uploads/documents/' . date('Y/m');
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
        $uniqueName = time() . '_' . bin2hex(random_bytes(4)) . '_' . $safeName;
        $destPath = $uploadDir . '/' . $uniqueName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new Exception('Failed to save file.');
        }

        $relativePath = '/public/assets/uploads/documents/' . date('Y/m') . '/' . $uniqueName;

        $stmt = db()->prepare("INSERT INTO documents (uploader_user_id, patient_id, file_name, file_path, file_type, file_size, category, description) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $uploaderId,
            $patientId,
            $file['name'],
            $relativePath,
            $file['type'],
            $file['size'],
            $category,
            $description
        ]);

        $id = db()->lastInsertId();

        AuditLogger::log($uploaderId, 'documents', 'INSERT', (int)$id, 'document_uploaded: ' . $file['name']);

        return [
            'document_id' => $id,
            'file_name' => $file['name'],
            'file_path' => $relativePath,
            'uploaded_at' => date('Y-m-d H:i:s')
        ];
    }
}
