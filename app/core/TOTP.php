<?php

declare(strict_types=1);

final class TOTP
{
    public static function generateSecret(int $length = 20): string
    {
        // 20 bytes -> typical; encoded in base32 without padding
        $bytes = random_bytes($length);
        return self::base32Encode($bytes);
    }

    /**
     * Generate an array of hex-encoded backup tokens
     */
    public static function generateBackupTokens(int $count = 3, int $bytesLength = 4): array
    {
        $tokens = [];
        for ($i = 0; $i < $count; $i++) {
            $tokens[] = bin2hex(random_bytes($bytesLength));
        }
        return $tokens;
    }

    public static function verify(string $base32Secret, string $code, int $window = 1, int $digits = 6): bool
    {
        $code = preg_replace('/\s+/', '', $code ?? '');
        if (!preg_match('/^\d{6,8}$/', $code))
            return false;

        $timeSlice = (int) floor(time() / 30);

        for ($i = -$window; $i <= $window; $i++) {
            $calc = self::code($base32Secret, $timeSlice + $i, $digits);
            if (hash_equals($calc, $code))
                return true;
        }
        return false;
    }

    public static function code(string $base32Secret, int $timeSlice = null, int $digits = 6): string
    {
        if ($timeSlice === null)
            $timeSlice = (int) floor(time() / 30);

        $secret = self::base32Decode($base32Secret);
        if ($secret === '')
            return '';

        // 8-byte counter (big endian)
        $time = pack('N*', 0) . pack('N*', $timeSlice);

        $hash = hash_hmac('sha1', $time, $secret, true);

        $offset = ord(substr($hash, -1)) & 0x0F;
        $part = substr($hash, $offset, 4);
        $value = unpack('N', $part)[1] & 0x7FFFFFFF;

        $mod = 10 ** $digits;
        $otp = (string) ($value % $mod);
        return str_pad($otp, $digits, '0', STR_PAD_LEFT);
    }

    public static function otpauthUrl(string $issuer, string $accountLabel, string $base32Secret): string
    {
        // otpauth://totp/{issuer}:{account}?secret=...&issuer=...&period=30&digits=6
        $label = rawurlencode($issuer . ':' . $accountLabel);
        $issuerEnc = rawurlencode($issuer);
        $secretEnc = rawurlencode($base32Secret);

        return "otpauth://totp/{$label}?secret={$secretEnc}&issuer={$issuerEnc}&period=30&digits=6";
    }

    public static function qrUrl(string $otpauthUrl, int $size = 200): string
    {
        // Use qr-server.com for QR image (CORS friendly)
        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . ((int) $size) . 'x' . ((int) $size) . '&data=' . rawurlencode($otpauthUrl);
    }

    private static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $out = '';

        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }

        for ($i = 0; $i < strlen($bits); $i += 5) {
            $chunk = substr($bits, $i, 5);
            if (strlen($chunk) < 5)
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $out .= $alphabet[bindec($chunk)];
        }

        return $out;
    }

    private static function base32Decode(string $b32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
        if ($b32 === '')
            return '';

        $bits = '';
        for ($i = 0, $len = strlen($b32); $i < $len; $i++) {
            $pos = strpos($alphabet, $b32[$i]);
            if ($pos === false)
                return '';
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $byte = substr($bits, $i, 8);
            $out .= chr(bindec($byte));
        }

        return $out;
    }
}
