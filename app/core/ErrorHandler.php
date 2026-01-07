<?php

declare(strict_types=1);

final class ErrorHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
    }

    public static function handleException(Throwable $e): void
    {
        error_log($e);

        http_response_code(500);

        if (ini_get('display_errors') === '1') {
            echo "<h1>500 Internal Server Error</h1>";
            echo "<p>Uncaught exception: '" . get_class($e) . "'</p>";
            echo "<p>Message: '" . $e->getMessage() . "'</p>";
            echo "<p>Stack trace:</p><pre>" . $e->getTraceAsString() . "</pre>";
            echo "<p>Thrown in '" . $e->getFile() . "' on line " . $e->getLine() . "</p>";
        } else {
            // Show a generic error page to the user
            echo "<h1>500 Internal Server Error</h1>";
            echo "<p>Sorry, something went wrong. Please try again later.</p>";
        }

        exit();
    }

    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting
            return false;
        }

        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
}
