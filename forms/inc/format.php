<?php

declare(strict_types=1);

/*
 * Turns valid form input into the text of the mail. Shared by the form handlers.
 */

require_once __DIR__ . '/validate.php';

/** "Fr, 20.11.2026". The date is already validated. */
function format_date(string $value): string
{
    $weekdays = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
    $date = parse_date($value);

    return $weekdays[(int) $date->format('N') - 1] . ', ' . $date->format('d.m.Y');
}

/**
 * The mail body: intro, then one aligned "Label:  value" line per field, then the long texts as blocks.
 * Empty values (optional fields) are left out.
 *
 *   Name:      Maria Muster
 *   Telefon:   0664 1234567
 *
 *   Beschreibung:
 *   …
 */
function mail_body(string $intro, array $details, array $texts = []): string
{
    $details = array_filter($details, fn(string $value) => $value !== '');
    $texts = array_filter($texts, fn(string $value) => $value !== '');

    // Longest label + ":" + one space. mb_ functions count "ä" as one character, not two bytes.
    $width = max(array_map(mb_strlen(...), array_keys($details))) + 2;

    $body = "{$intro}\n\n";
    foreach ($details as $label => $value) {
        $body .= mb_str_pad("{$label}:", $width) . $value . "\n";
    }
    foreach ($texts as $label => $text) {
        $body .= "\n{$label}:\n{$text}\n";
    }

    return $body;
}
