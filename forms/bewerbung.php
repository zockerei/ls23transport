<?php

declare(strict_types=1);

/*
 * Application form (/jobs/#bewerbung): spam check → validate → mail to the business inbox → /jobs/danke/.
 * Field rules: §4 of the plan. The labels must match the form.
 * Everything that isn't specific to applications lives in inc/ and is shared with anfrage.php.
 */

require_once __DIR__ . '/inc/validate.php';
require_once __DIR__ . '/inc/spam.php';
require_once __DIR__ . '/inc/mail.php';
require_once __DIR__ . '/inc/format.php';
require_once __DIR__ . '/inc/upload.php';
require_once __DIR__ . '/inc/response.php';

const SCOPES = [
    'vollzeit' => 'Vollzeit',
    'teilzeit' => 'Teilzeit',
];

// Attachments. The numbers are also in the form's hint text. mailbox.org takes mails up to ~137 MB.
const MAX_FILES = 3;
const MAX_FILE_BYTES = 5 * 1024 * 1024;
const MAX_TOTAL_BYTES = 10 * 1024 * 1024;

require_post();

// Over the server's post_max_size, PHP drops every field. Say what happened instead of "name missing".
if (request_too_large()) {
    respond_invalid('Bewerbung', [
        'files' => 'Die Anhänge sind zu groß. Zusammen dürfen sie höchstens 10 MB haben.',
    ]);
}

// Spam gets the same answer as a real application, so bots can't tell the difference. Nothing is sent.
if (is_spam()) {
    respond_success('/jobs/danke/');
}

$name = field('name');
$phone = field('phone');
$email = field('email');
$city = field('city');
$scope = field('scope');
$start = field('start');
$days = field('days');
$message = field('message');
$files = uploaded_files('files');

// In form order: the error page lists the messages in this order.
$errors = [];

if ($name === '') {
    $errors['name'] = 'Bitte geben Sie Ihren Namen an.';
} elseif (!length_between($name, 2, 100)) {
    $errors['name'] = 'Der Name muss zwischen 2 und 100 Zeichen lang sein.';
}

if ($phone === '') {
    $errors['phone'] = 'Bitte geben Sie Ihre Telefonnummer an.';
} elseif (!is_phone($phone)) {
    $errors['phone'] = 'Bitte geben Sie eine gültige Telefonnummer an, z. B. 0664 1234567.';
}

// Optional here, unlike in the request form: he calls applicants back.
if ($email !== '' && !is_email($email)) {
    $errors['email'] = 'Bitte geben Sie eine gültige E-Mail-Adresse an, z. B. name@beispiel.at.';
}

if ($city === '') {
    $errors['city'] = 'Bitte geben Sie Ihren Wohnort an.';
} elseif (!length_between($city, 1, 100)) {
    $errors['city'] = 'Der Wohnort darf höchstens 100 Zeichen lang sein.';
}

if (!is_one_of($scope, array_keys(SCOPES))) {
    $errors['scope'] = 'Bitte wählen Sie Vollzeit oder Teilzeit aus.';
}

if ($start !== '' && !is_date_in_range($start, MAX_MONTHS)) {
    $errors['start'] = 'Das Datum muss zwischen heute und in 12 Monaten liegen.';
}

if (!length_between($days, 0, 300)) {
    $errors['days'] = 'Die verfügbaren Tage und Zeiten dürfen höchstens 300 Zeichen lang sein.';
}

if (!length_between($message, 0, 3000)) {
    $errors['message'] = 'Die Nachricht darf höchstens 3000 Zeichen lang sein.';
}

$problems = array_map(fn(array $file) => upload_problem($file, MAX_FILE_BYTES), $files);
if (count($files) > MAX_FILES) {
    $errors['files'] = 'Bitte wählen Sie höchstens 3 Dateien aus.';
} elseif (in_array('failed', $problems, true)) {
    $errors['files'] = 'Eine Datei konnte nicht hochgeladen werden. Bitte wählen Sie sie noch einmal aus.';
} elseif (in_array('size', $problems, true)) {
    $errors['files'] = 'Jede Datei darf höchstens 5 MB haben.';
} elseif (in_array('type', $problems, true)) {
    $errors['files'] = 'Bitte wählen Sie nur PDF-, JPG- oder PNG-Dateien aus.';
} elseif (array_sum(array_column($files, 'size')) > MAX_TOTAL_BYTES) {
    $errors['files'] = 'Die Anhänge dürfen zusammen höchstens 10 MB haben.';
}

if ($errors !== []) {
    respond_invalid('Bewerbung', $errors);
}

// Everything is valid from here on.

// Our own file names: the visitor's name could contain anything ("../", line breaks, their full name).
$attachments = [];
foreach ($files as $i => $file) {
    $attachments[] = [
        'path' => $file['tmp'],
        'name' => 'Anhang-' . ($i + 1) . '.' . upload_type($file['tmp']),
    ];
}

$intro = "Neue Bewerbung über das Formular auf ls23transport.at.\n"
    . ($email !== ''
        ? 'Mit „Antworten“ schreiben Sie direkt an die Person, die sich beworben hat.'
        : 'Keine E-Mail-Adresse angegeben: bitte telefonisch melden.');

$body = mail_body(
    $intro,
    [
        'Name' => $name,
        'Telefon' => $phone,
        'E-Mail' => $email,
        'Wohnort' => $city,
        'Ausmaß' => SCOPES[$scope],
        'Verfügbar ab' => $start === '' ? '' : format_date($start),
        'Tage und Zeiten' => $days,
        'Anhänge' => implode(', ', array_column($attachments, 'name')),
    ],
    ['Nachricht' => $message],
);

$subject = 'Bewerbung: ' . $name . ' (' . SCOPES[$scope] . ')';

if (!send_mail($subject, $body, $email, $attachments)) {
    // Goes to the server's error log. No personal data in it.
    error_log('bewerbung.php: sending failed');
    respond_send_failed('Bewerbung');
}

respond_success('/jobs/danke/');
