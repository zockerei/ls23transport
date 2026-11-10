<?php

declare(strict_types=1);

const MAIL_FROM = 'formular@ls23transport.at';
const MAIL_TO = 'info@ls23transport.at';

/** Removes line breaks. A line break in a header value would start a new header (header injection). */
function header_safe(string $value): string
{
    return str_replace(["\r", "\n"], '', $value);
}

/**
 * Sends a plain-text mail to the business inbox.
 * `$replyTo` is the visitor's address: "Antworten" goes to them. It never goes in `From`,
 * because our server isn't allowed to send for their domain (SPF), so the mail would land in spam.
 */
function send_mail(string $subject, string $body, string $replyTo = ''): bool
{
    $subject = header_safe($subject);
    $replyTo = header_safe($replyTo);
    // Textareas send \r\n, our own text uses \n. Make it all \n first.
    $body = str_replace(["\r\n", "\r"], "\n", $body);

    $headers = [
        'From' => 'Website-Formular <' . MAIL_FROM . '>',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        // Encodes umlauts and wraps long lines (mail lines may not exceed 998 characters).
        'Content-Transfer-Encoding' => 'quoted-printable',
    ];
    if ($replyTo !== '') {
        $headers['Reply-To'] = $replyTo;
    }

    // `php -S` (local development): write to mail.log in the repo root instead of sending.
    if (PHP_SAPI === 'cli-server') {
        return log_mail($subject, $body, $headers);
    }

    return mail(
        MAIL_TO,
        // Headers may only contain ASCII. This encodes umlauts as =?UTF-8?B?...?=
        mb_encode_mimeheader($subject, 'UTF-8'),
        // Mail line breaks are \r\n. A lone \n would be encoded as "=0A" instead of a line break.
        quoted_printable_encode(str_replace("\n", "\r\n", $body)),
        $headers,
        // Envelope sender (where bounces go). SPF checks this address's domain.
        '-f' . MAIL_FROM,
    );
}

/** Readable copy of the mail for local testing (subject and body not encoded). */
function log_mail(string $subject, string $body, array $headers): bool
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna')); // PHP's default is UTC
    $entry = '=== ' . $now->format('Y-m-d H:i:s') . " ===\n";
    $entry .= 'To: ' . MAIL_TO . "\n";
    $entry .= "Subject: {$subject}\n";
    foreach ($headers as $name => $value) {
        $entry .= "{$name}: {$value}\n";
    }
    $entry .= "\n{$body}\n\n";

    return file_put_contents(dirname(__DIR__, 2) . '/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
}
