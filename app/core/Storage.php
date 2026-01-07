<?php
declare(strict_types=1);

final class Storage
{
    public static function ensureDirs(): void
    {
        $dirs = [
            STORAGE_PATH . '/uploads/avatars',
            STORAGE_PATH . '/uploads/attachments',
            STORAGE_PATH . '/interactions',
            STORAGE_PATH . '/logs',
        ];
        foreach ($dirs as $d) {
            if (!is_dir($d)) {
                @mkdir($d, 0755, true);
            }
        }
    }

    public static function saveAvatarFromBase64(string $base64, int $userId): string
    {
        // Expect data URI like: data:image/png;base64,...
        if (strpos($base64, 'base64,') === false) {
            throw new RuntimeException('Invalid base64 data.');
        }
        [$meta, $b64] = explode(',', $base64, 2);
        $bin = base64_decode($b64);
        if ($bin === false)
            throw new RuntimeException('Invalid base64 payload');
        $path = STORAGE_PATH . '/uploads/avatars/' . $userId . '.png';
        file_put_contents($path, $bin);
        return $path;
    }

    public static function saveUploadedAvatar(string $tmpPath, int $userId): string
    {
        $dest = STORAGE_PATH . '/uploads/avatars/' . $userId . '_' . time() . '.upload';
        if (!move_uploaded_file($tmpPath, $dest)) {
            // fallback to copy
            if (!copy($tmpPath, $dest)) {
                throw new RuntimeException('Failed to save uploaded file to storage.');
            }
        }
        return $dest;
    }

    public static function appendInteraction(int $userId, string $action, array $meta = []): string
    {
        $dir = STORAGE_PATH . '/interactions';
        if (!is_dir($dir))
            @mkdir($dir, 0755, true);
        $entry = [
            'timestamp' => date('c'),
            'user_id' => $userId,
            'action' => $action,
            'meta' => $meta,
        ];
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        $fn = $dir . '/' . $userId . '.log';
        file_put_contents($fn, $line, FILE_APPEND | LOCK_EX);
        return $fn;
    }
}
