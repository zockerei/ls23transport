<?php

declare(strict_types=1);

/*
 * File uploads for the form handlers. Files are only attached to the mail, never stored:
 * PHP keeps them in a temp folder during the request and deletes them when the request ends.
 * Like validate.php, these only answer "what's wrong?". The German messages live in each handler.
 */

/*
 * Allowed types, recognised by the first bytes of the file ("magic bytes"), never by the file name
 * or the browser's Content-Type: both come from the visitor and can say anything.
 * The value is the extension we give the attachment.
 */
const UPLOAD_SIGNATURES = [
    'pdf' => '%PDF-',
    'jpg' => "\xFF\xD8\xFF",
    'png' => "\x89PNG\r\n\x1A\n",
];

/**
 * True when the request was bigger than PHP's `post_max_size`. PHP then drops the WHOLE request:
 * $_POST and $_FILES are empty, so without this check the visitor would see "Bitte geben Sie Ihren Namen an".
 */
function request_too_large(): bool
{
    return (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && $_POST === [] && $_FILES === [];
}

/**
 * The files of a `name[]` file input as a list: [['tmp' => …, 'size' => …, 'error' => …], …].
 * PHP's own layout is sideways ($_FILES['files']['tmp_name'][0], $_FILES['files']['size'][0], …).
 * An empty file input still sends one entry with UPLOAD_ERR_NO_FILE: those are left out.
 */
function uploaded_files(string $key): array
{
    $files = $_FILES[$key] ?? null;
    if (!is_array($files) || !is_array($files['error'] ?? null)) {
        return [];
    }

    $list = [];
    foreach ($files['error'] as $i => $error) {
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $list[] = [
            'tmp' => (string) ($files['tmp_name'][$i] ?? ''),
            'size' => (int) ($files['size'][$i] ?? 0),
            'error' => (int) $error,
        ];
    }

    return $list;
}

/** 'pdf', 'jpg' or 'png' from the file's first bytes, or null for anything else. */
function upload_type(string $path): ?string
{
    $start = file_get_contents($path, false, null, 0, 8);
    if ($start === false) {
        return null;
    }

    foreach (UPLOAD_SIGNATURES as $extension => $signature) {
        if (str_starts_with($start, $signature)) {
            return $extension;
        }
    }

    return null;
}

/** What's wrong with one uploaded file: 'size', 'type', 'failed', or null if it's fine. */
function upload_problem(array $file, int $maxBytes): ?string
{
    // Bigger than the server's upload_max_filesize: PHP didn't even keep the file.
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return 'size';
    }
    // is_uploaded_file: the path really is an upload of this request, not e.g. /etc/passwd.
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp'])) {
        return 'failed';
    }
    if ($file['size'] > $maxBytes) {
        return 'size';
    }
    if (upload_type($file['tmp']) === null) {
        return 'type';
    }

    return null;
}
