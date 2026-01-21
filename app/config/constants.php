<?php

declare(strict_types=1);

const APP_NAME = 'MatriFlow';
const APP_TZ = 'Asia/Manila';
define('FPDF_FONTPATH', APP_PATH . '/lib/font/');

date_default_timezone_set(APP_TZ);

function base_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Auto-detect subdirectory for local XAMPP
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $basePath = str_replace('\\', '/', BASE_PATH);
    $baseDir = str_replace($docRoot, '', $basePath);
    $baseDir = ($baseDir === '/' || $baseDir === '.') ? '' : rtrim($baseDir, '/');

    $path = '/' . ltrim($path, '/');
    return $scheme . '://' . $host . $baseDir . $path;
}

function redirect(string $path): never
{
    // If absolute path starting with /, prepend base_url logic locally
    if (strpos($path, '/') === 0) {
        $path = base_url($path);
    }
    header('Location: ' . $path);
    exit;
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function user_agent(): string
{
    return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
}
