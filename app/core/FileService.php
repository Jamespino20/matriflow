<?php

declare(strict_types=1);

final class FileService
{
    private const UPLOAD_DIR = STORAGE_PATH . '/uploads/documents';

    public static function ensureDir(): void
    {
        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }
    }

    /**
     * Specialized avatar upload from base64 data.
     */
    public static function saveAvatar(int $userId, string $base64Data): string
    {
        $dir = BASE_PATH . '/storage/uploads/avatars';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Remove prefix if exists (e.g., data:image/png;base64,)
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
            $type = strtolower($type[1]); // png, jpg, etc.
        } else {
            $type = 'png'; // default
        }

        $imgData = base64_decode($base64Data);
        if ($imgData === false) {
            throw new Exception('Invalid base64 image data.');
        }

        if (strlen($imgData) > 2 * 1024 * 1024) {
            throw new Exception('Avatar file size must be less than 2MB.');
        }

        // Convert to image and resize
        $img = imagecreatefromstring($imgData);
        if ($img === false) {
            throw new Exception('Invalid image data.');
        }

        $resized = imagescale($img, 200, 200);
        $targetFilename = $userId . '.png';
        $targetPath = $dir . '/' . $targetFilename;
        imagepng($resized, $targetPath);
        unset($img);
        unset($resized);

        return 'storage/uploads/avatars/' . $targetFilename;
    }

    /**
     * Get avatar URL for a user
     */
    public static function getAvatarUrl(int $userId): string
    {
        // Serve from a controller that checks file existence
        return base_url('/public/controllers/avatar.php?uid=' . $userId . '&t=' . time());
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

    public static function saveDocument(int $uploaderId, array $file, string $category = 'other', $userId = null, string $description = ''): array
    {
        $uploadDir = BASE_PATH . '/storage/uploads/documents';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Validate file
        $allowedTypes = [
            'application/pdf',
            'image/png',
            'image/jpeg',
            'image/jpg',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/octet-stream' // Sometimes seen for generic binaries
        ];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        unset($finfo);

        // Fallback or specific check for common extensions if mime type detection is weak
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safeExts = ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx'];

        if (!in_array($mimeType, $allowedTypes) && !in_array($ext, $safeExts)) {
            throw new Exception('Invalid file type. Only PDF, images, and Word documents are allowed.');
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('File size must be less than 10MB.');
        }

        // Generate unique filename
        $uniqueId = uniqid('doc_', true);
        $filename = $uniqueId . '.' . $ext;
        $filePath = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to save file.');
        }

        // Save to database
        $db = db();
        $sql = "INSERT INTO documents (user_id, uploader_user_id, category, file_path, file_name, description, file_type, file_size, uploaded_at)
                VALUES (:user_id, :uploader_user_id, :category, :file_path, :file_name, :description, :file_type, :file_size, NOW())";
        $stmt = $db->prepare($sql);

        // Handle multi-user sharing if userId is an array
        $userIds = is_array($userId) ? $userId : [$userId];

        // If it's a patient uploading, we ALWAYS want one record for the patient themselves
        // even if they share it with others.
        if (!in_array($uploaderId, $userIds)) {
            // Check if uploader is a patient
            $check = $db->prepare("SELECT role FROM users WHERE user_id = ?");
            $check->execute([$uploaderId]);
            $role = $check->fetchColumn();
            if ($role === 'patient') {
                array_unshift($userIds, $uploaderId);
            }
        }

        $userIds = array_unique(array_filter($userIds));
        $documentIds = [];

        foreach ($userIds as $uid) {
            $stmt->execute([
                ':user_id' => $uid,
                ':uploader_user_id' => $uploaderId,
                ':category' => $category,
                ':file_path' => 'storage/uploads/documents/' . $filename,
                ':file_name' => $file['name'],
                ':description' => $description,
                ':file_type' => $mimeType,
                ':file_size' => $file['size']
            ]);
            $documentIds[] = (int)$db->lastInsertId();
        }

        return [
            'document_ids' => $documentIds,
            'file_name' => $file['name'],
            'file_path' => 'storage/uploads/documents/' . $filename,
            'category' => $category
        ];
    }
}
