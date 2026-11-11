<?php

declare(strict_types=1);

/*
 * Request form (/kontakt/): spam check → validate → mail to the business inbox → /danke/.
 * Field rules: §4 of the plan. The labels must match the form.
 */

require_once __DIR__ . '/inc/validate.php';
require_once __DIR__ . '/inc/spam.php';
require_once __DIR__ . '/inc/mail.php';
require_once __DIR__ . '/inc/format.php';
require_once __DIR__ . '/inc/response.php';

const SERVICES = [
    'umzug' => 'Umzug & Büroumzug',
    'moebelmontage' => 'Möbelmontage',
    'abtransport' => 'Abtransport',
    'kurier-pakete' => 'Kurierfahrten',
    'abholservice' => 'Abholservice',
    'sonstiges' => 'Sonstiges',
];

const TIME_WINDOWS = [
    'vormittag' => 'Vormittag',
    'nachmittag' => 'Nachmittag',
    'abend' => 'Abend',
    'flexibel' => 'Flexibel',
];

require_post();

// Spam gets the same answer as a real request, so bots can't tell the difference. Nothing is sent.
if (is_spam()) {
    respond_success();
}

$services = field_list('service');
$name = field('name');
$company = field('company');
$phone = field('phone');
$email = field('email');
$fromZip = field('from_zip');
$fromCity = field('from_city');
$toZip = field('to_zip');
$toCity = field('to_city');
$date = field('date');
$timeWindow = field('time_window');
$dateAlt = field('date_alt');
$message = field('message');

// In form order: the error page lists the messages in this order.
$errors = [];

if (!all_known($services, array_keys(SERVICES))) {
    $errors['service'] = 'Bitte wählen Sie mindestens eine Leistung aus.';
}

if ($name === '') {
    $errors['name'] = 'Bitte geben Sie Ihren Namen an.';
} elseif (!length_between($name, 2, 100)) {
    $errors['name'] = 'Der Name muss zwischen 2 und 100 Zeichen lang sein.';
}

if (!length_between($company, 0, 100)) {
    $errors['company'] = 'Der Firmenname darf höchstens 100 Zeichen lang sein.';
}

if ($phone === '') {
    $errors['phone'] = 'Bitte geben Sie Ihre Telefonnummer an.';
} elseif (!is_phone($phone)) {
    $errors['phone'] = 'Bitte geben Sie eine gültige Telefonnummer an, z. B. 0664 1234567.';
}

if ($email === '') {
    $errors['email'] = 'Bitte geben Sie Ihre E-Mail-Adresse an.';
} elseif (!is_email($email)) {
    $errors['email'] = 'Bitte geben Sie eine gültige E-Mail-Adresse an, z. B. name@beispiel.at.';
}

if ($fromZip === '') {
    $errors['from_zip'] = 'Bitte geben Sie die Postleitzahl des Abholorts an.';
} elseif (!is_zip($fromZip)) {
    $errors['from_zip'] = 'Die Postleitzahl des Abholorts muss 4 Ziffern haben.';
}

if ($fromCity === '') {
    $errors['from_city'] = 'Bitte geben Sie den Abholort an.';
} elseif (!length_between($fromCity, 1, 100)) {
    $errors['from_city'] = 'Der Abholort darf höchstens 100 Zeichen lang sein.';
}

if ($toZip !== '' && !is_zip($toZip)) {
    $errors['to_zip'] = 'Die Postleitzahl des Ziels muss 4 Ziffern haben.';
}

if (!length_between($toCity, 0, 100)) {
    $errors['to_city'] = 'Der Zielort darf höchstens 100 Zeichen lang sein.';
}

if ($date === '') {
    $errors['date'] = 'Bitte geben Sie einen Wunschtermin an.';
} elseif (!is_date_in_range($date, MAX_MONTHS)) {
    $errors['date'] = 'Der Wunschtermin muss zwischen heute und in 12 Monaten liegen.';
}

if (!is_one_of($timeWindow, array_keys(TIME_WINDOWS))) {
    $errors['time_window'] = 'Bitte wählen Sie ein Zeitfenster aus.';
}

if ($dateAlt !== '' && !is_date_in_range($dateAlt, MAX_MONTHS)) {
    $errors['date_alt'] = 'Der Alternativtermin muss zwischen heute und in 12 Monaten liegen.';
}

if ($message === '') {
    $errors['message'] = 'Bitte beschreiben Sie Ihren Auftrag.';
} elseif (!length_between($message, 10, 3000)) {
    $errors['message'] = 'Die Beschreibung muss zwischen 10 und 3000 Zeichen lang sein.';
}

if ($errors !== []) {
    respond_invalid('Anfrage', $errors);
}

// Everything is valid from here on.

$serviceLabels = implode(', ', array_map(fn(string $slug) => SERVICES[$slug], $services));

$details = [
    'Leistung' => $serviceLabels,
    'Name' => $name,
    'Firma' => $company,
    'Telefon' => $phone,
    'E-Mail' => $email,
    'Abholung' => "{$fromZip} {$fromCity}",
    'Ziel' => trim("{$toZip} {$toCity}"),
    'Wunschtermin' => format_date($date) . ', ' . TIME_WINDOWS[$timeWindow],
    'Alternativtermin' => $dateAlt === '' ? '' : format_date($dateAlt),
];

$body = mail_body(
    "Neue Anfrage über das Formular auf ls23transport.at.\n"
        . 'Mit „Antworten“ schreiben Sie direkt an die Person, die angefragt hat.',
    $details,
    ['Beschreibung' => $message],
);

$subject = "Anfrage: {$serviceLabels} – {$name}";

if (!send_mail($subject, $body, $email)) {
    // Goes to the server's error log. No personal data in it.
    error_log('anfrage.php: sending failed');
    respond_send_failed('Anfrage');
}

respond_success();
