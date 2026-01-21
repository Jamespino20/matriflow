<?php

/**
 * Mailer Service Class
 * Handles all email sending using PHPMailer with Mailtrap SMTP
 */

declare(strict_types=1);

// PHPMailer imports
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';
require_once __DIR__ . '/../lib/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    /**
     * Get email configuration from system settings or defaults
     */
    private static function getConfig(): array
    {
        static $config = null;

        if ($config !== null) {
            return $config;
        }

        // Load configuration from file
        $configFile = BASE_PATH . '/app/config/mail.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
        } else {
            // Fallback default (if file missing)
            $config = [
                'smtp_host' => 'sandbox.smtp.mailtrap.io',
                'smtp_port' => 2525,
                'smtp_user' => 'fea6bb39e5ea6b',
                'smtp_pass' => '28db8bb3ce7cbd',
                'from_email' => 'noreply@matriflow.infinityfreeapp.com',
                'from_name' => 'MatriFlow - CHMC Maternal Health System',
                'debug' => true,
            ];
        }

        // Load from database if available
        try {
            $stmt = Database::getInstance()->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('email_from_name', 'email_from_address')");
            $stmt->execute();
            while ($row = $stmt->fetch()) {
                if ($row['setting_key'] === 'email_from_name' && !empty($row['setting_value'])) {
                    $config['from_name'] = $row['setting_value'];
                }
                if ($row['setting_key'] === 'email_from_address' && !empty($row['setting_value'])) {
                    $config['from_email'] = $row['setting_value'];
                }
            }
        } catch (Throwable $e) {
            error_log("Failed to load email config from DB: " . $e->getMessage());
        }

        return $config;
    }

    /**
     * Send 2FA setup email with QR code and backup tokens
     */
    public static function send2FASetupEmail(string $toEmail, string $toName, string $setupUrl, string $qrCodeData, array $backupTokens, string $secret = ''): bool
    {
        try {
            $config = self::getConfig();

            // Log in debug mode
            if ($config['debug']) {
                error_log("=== 2FA SETUP EMAIL ===");
                error_log("To: $toEmail ($toName)");
                error_log("Setup URL: $setupUrl");

                $logFile = BASE_PATH . '/storage/logs/email_log.txt';
                $logDir = dirname($logFile);
                if (!is_dir($logDir)) {
                    mkdir($logDir, 0755, true);
                }

                $message = sprintf(
                    "[%s] 2FA Setup Email\nTo: %s\nSetup URL: %s\n\n",
                    date('Y-m-d H:i:s'),
                    $toEmail,
                    $setupUrl
                );
                file_put_contents($logFile, $message, FILE_APPEND);
            }

            // Send actual email via PHPMailer
            $mail = new PHPMailer(true);

            // Enable verbose debug output
            if ($config['debug']) {
                $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Show server responses
                $mail->Debugoutput = function ($str, $level) {
                    error_log("PHPMailer Debug [$level]: $str");
                };
            }

            $mail->isSMTP();
            $mail->SMTPDebug = 2;
            $mail->Host = $config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['smtp_user'];
            $mail->Password = $config['smtp_pass'];
            // Temporarily disable encryption (OpenSSL not enabled in php.ini)
            // TODO: Enable OpenSSL in php.ini and change to: PHPMailer::ENCRYPTION_STARTTLS
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $config['smtp_port'];

            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = 'Complete Your 2FA Setup - MatriFlow';

            $body = self::get2FASetupEmailTemplate($toName, $setupUrl, $qrCodeData, $backupTokens, $secret);
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);

            $result = $mail->send();

            if ($config['debug']) {
                error_log("Email sent successfully to: $toEmail");
            }

            return $result;
        } catch (Throwable $e) {
            error_log("Mailer::send2FASetupEmail failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send password reset email with disposable token
     */
    public static function sendPasswordResetEmail(string $toEmail, string $toName, string $resetUrl): bool
    {
        try {
            $config = self::getConfig();

            // Log in debug mode
            if ($config['debug']) {
                error_log("=== PASSWORD RESET EMAIL ===");
                error_log("To: $toEmail ($toName)");
                error_log("Reset URL: $resetUrl");

                $logFile = BASE_PATH . '/storage/logs/email_log.txt';
                $logDir = dirname($logFile);
                if (!is_dir($logDir)) {
                    mkdir($logDir, 0755, true);
                }

                $message = sprintf(
                    "[%s] Password Reset Email\nTo: %s\nReset URL: %s\n\n",
                    date('Y-m-d H:i:s'),
                    $toEmail,
                    $resetUrl
                );
                file_put_contents($logFile, $message, FILE_APPEND);
            }

            // Send actual email via PHPMailer
            $mail = new PHPMailer(true);

            // Enable verbose debug output
            if ($config['debug']) {
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function ($str, $level) {
                    error_log("PHPMailer Debug [$level]: $str");
                };
            }

            $mail->isSMTP();
            $mail->SMTPDebug = 2;
            $mail->Host = $config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['smtp_user'];
            $mail->Password = $config['smtp_pass'];
            // Temporarily disable encryption (OpenSSL not enabled in php.ini)
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $config['smtp_port'];

            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request - MatriFlow';

            $body = self::getPasswordResetEmailTemplate($toName, $resetUrl);
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log("Mailer::sendPasswordResetEmail failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send welcome email to new user
     */
    public static function sendWelcomeEmail(string $toEmail, string $toName, string $role): bool
    {
        try {
            $config = self::getConfig();

            if ($config['debug']) {
                error_log("=== WELCOME EMAIL ===");
                error_log("To: $toEmail ($toName)");
                error_log("Role: $role");
            }

            // TODO: Implement actual PHPMailer sending with welcome template
            return true;
        } catch (Throwable $e) {
            error_log("Mailer::sendWelcomeEmail failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * HTML email template for 2FA setup
     */
    private static function get2FASetupEmailTemplate(string $name, string $setupUrl, string $qrCodeData, array $backupTokens, string $secret = ''): string
    {
        $tokensList = implode('<br>', array_map(fn($t) => "<code>$t</code>", $backupTokens));

        $secretHtml = '';
        if ($secret) {
            $secretHtml = "<p>Or enter code manually: <strong style='font-family:monospace; font-size:16px; background:#eee; padding:4px 8px; letter-spacing:1px;'>$secret</strong></p>";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #14457b; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .button { display: inline-block; padding: 12px 24px; background: #14457b; color: white; text-decoration: none; border-radius: 4px; }
                .qr-code { text-align: center; margin: 20px 0; }
                .qr-code img { max-width: 200px; border: 1px solid #ddd; padding: 10px; background: white; }
                .backup-tokens { background: white; padding: 15px; border-left: 4px solid #14457b; margin: 20px 0; }
                code { background: #eee; padding: 2px 6px; border-radius: 3px; font-family: monospace; font-size: 14px; letter-spacing: 1px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to MatriFlow</h1>
                </div>
                <div class='content'>
                    <p>Hello <strong>$name</strong>,</p>
                    <p>Your MatriFlow account has been created. For security, you must complete Two-Factor Authentication (2FA) setup.</p>
                    
                    <h3>Step 1: Scan QR Code</h3>
                    <p>Scanning this code with Google Authenticator (or Authy) will add your account:</p>
                    
                    <div class='qr-code'>
                        <img src='$qrCodeData' alt='2FA QR Code' />
                        <p><em>If the image doesn't load, you may need to enable 'Display Images' in your email client.</em></p>
                        $secretHtml
                    </div>

                    <h3>Step 2: Save Backup Tokens</h3>
                    <div class='backup-tokens'>
                        <h4>Your Backup Tokens:</h4>
                        <p>Save these securely. Use them if you lose access to your phone.</p>
                        <p>$tokensList</p>
                    </div>
                    
                    <p><strong>Note:</strong> You must verify your 2FA code on your first login.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * HTML email template for password reset
     */
    private static function getPasswordResetEmailTemplate(string $name, string $resetUrl): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #14457b; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .button { display: inline-block; padding: 12px 24px; background: #14457b; color: white; text-decoration: none; border-radius: 4px; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Password Reset Request</h1>
                </div>
                <div class='content'>
                    <p>Hello <strong>$name</strong>,</p>
                    <p>We received a request to reset your MatriFlow account password.</p>
                   
                    <p style='text-align:center; margin: 30px 0;'>
                        <a href='$resetUrl' class='button'>Reset Password</a>
                    </p>
                    
                    <div class='warning'>
                        <strong>Important:</strong> This link expires in 30 minutes and can only be used once.
                    </div>
                    
                    <p>If you didn't request a password reset, please ignore this email or contact support if you have concerns.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
