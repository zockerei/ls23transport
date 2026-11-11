<?php

declare(strict_types=1);

require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

const MAIL_FROM = 'formular@ls23transport.at';
const MAIL_TO = 'info@ls23transport.at';

// mailbox.org sends the mail itself: SPF (include:mailbox.org) and DKIM match our domain → DMARC passes.
const SMTP_HOST = 'smtp.mailbox.org';
const SMTP_PORT = 465;
// Seconds. A slow server must not keep the visitor waiting forever.
const SMTP_TIMEOUT = 10;

/** Removes line breaks. A line break in a header value would start a new header (header injection). */
function header_safe(string $value): string
{
    return str_replace(["\r", "\n"], '', $value);
}

/**
 * Login data from a file OUTSIDE the web root (the repo is public, the password never goes in it):
 * the folder that contains public_html, e.g. ~/domains/ls23transport.at/form-config.php on Hostinger.
 * Locally that's the folder that contains the repo. Content:
 *   <?php return ['smtp_user' => '…', 'smtp_password' => '…'];
 */
function smtp_config(): ?array
{
    // forms/inc → forms → web root → its parent
    $file = dirname(__DIR__, 3) . '/form-config.php';
    if (!is_readable($file)) {
        return null;
    }
    $config = require $file;

    return is_array($config) && isset($config['smtp_user'], $config['smtp_password']) ? $config : null;
}

/**
 * Sends a plain-text mail to the business inbox.
 * `$replyTo` is the visitor's address: "Antworten" goes to them. It never goes in `From`:
 * only mailbox.org may send for our domain, not for theirs (SPF), so the mail would land in spam.
 * `$attachments`: [['path' => temp file, 'name' => file name in the mail], …], already checked by the handler.
 */
function send_mail(string $subject, string $body, string $replyTo = '', array $attachments = []): bool
{
    $subject = header_safe($subject);
    $replyTo = header_safe($replyTo);
    // Textareas send \r\n, our own text uses \n. Make it all \n first.
    $body = str_replace(["\r\n", "\r"], "\n", $body);

    // `php -S` (local development): write to mail.log in the repo root instead of sending.
    // `FORM_SMTP=1 php -S …` sends for real (needs the local form-config.php, see smtp_config()).
    if (PHP_SAPI === 'cli-server' && getenv('FORM_SMTP') !== '1') {
        return log_mail($subject, $body, $replyTo, $attachments);
    }

    $config = smtp_config();
    if ($config === null) {
        error_log('send_mail: form-config.php missing or incomplete');
        return false;
    }

    // true = PHPMailer throws on errors instead of returning false silently.
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        // Port 465: TLS from the first byte ("SMTPS").
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_user'];
        $mail->Password = $config['smtp_password'];
        $mail->Timeout = SMTP_TIMEOUT;

        // PHPMailer encodes the subject (umlauts) and wraps long lines itself.
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
        // A space = no "X-Mailer: PHPMailer 7.1.1" header (don't advertise versions).
        $mail->XMailer = ' ';

        $mail->setFrom(MAIL_FROM, 'Website-Formular');
        $mail->addAddress(MAIL_TO);
        if ($replyTo !== '') {
            $mail->addReplyTo($replyTo);
        }
        $mail->Subject = $subject;
        $mail->Body = $body;
        foreach ($attachments as $attachment) {
            // PHPMailer picks the MIME type from the extension of our own name and encodes the file as base64.
            $mail->addAttachment($attachment['path'], $attachment['name']);
        }

        $mail->send();

        return true;
    } catch (MailException) {
        // ErrorInfo = the SMTP server's answer. Never contains the password.
        error_log('send_mail: ' . $mail->ErrorInfo);

        return false;
    }
}

/** Readable copy of the mail for local testing. Attachments only by name and size. */
function log_mail(string $subject, string $body, string $replyTo, array $attachments = []): bool
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna')); // PHP's default is UTC
    $entry = '=== ' . $now->format('Y-m-d H:i:s') . " ===\n";
    $entry .= 'From: Website-Formular <' . MAIL_FROM . ">\n";
    $entry .= 'To: ' . MAIL_TO . "\n";
    if ($replyTo !== '') {
        $entry .= "Reply-To: {$replyTo}\n";
    }
    $entry .= "Subject: {$subject}\n";
    foreach ($attachments as $attachment) {
        $entry .= "Attachment: {$attachment['name']} (" . filesize($attachment['path']) . " bytes)\n";
    }
    $entry .= "\n{$body}\n\n";

    return file_put_contents(dirname(__DIR__, 2) . '/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
}
