<?php

declare(strict_types=1);

/*
 * Input and validation helpers shared by the form handlers.
 * They only answer "is this valid?". The German error messages live in each handler.
 */

const FORM_TIMEZONE = 'Europe/Vienna';

/** One text field, trimmed. Missing, or an array (`name[]=x`), gives ''. */
function field(string $key): string
{
    $value = $_POST[$key] ?? '';

    return is_string($value) ? trim($value) : '';
}

/** A checkbox group like `service[]`, without duplicates. Anything that isn't a list of strings gives []. */
function field_list(string $key): array
{
    $values = $_POST[$key] ?? [];
    if (!is_array($values)) {
        return [];
    }

    return array_values(array_unique(array_filter($values, 'is_string')));
}

/** Length in characters (not bytes: "Müller" is 6). */
function length_between(string $value, int $min, int $max): bool
{
    $length = mb_strlen($value);

    return $length >= $min && $length <= $max;
}

/*
 * The `D` after each regex: without it, `$` also matches before a trailing newline,
 * so "4020\n" would count as a valid ZIP code.
 */

function is_phone(string $value): bool
{
    return preg_match('/^[+0-9 ()\/-]{6,25}$/D', $value) === 1;
}

/** Austrian ZIP code: exactly 4 digits. */
function is_zip(string $value): bool
{
    return preg_match('/^[0-9]{4}$/D', $value) === 1;
}

function is_email(string $value): bool
{
    return strlen($value) <= 254 && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

/** For radio groups: the value must be one of the allowed ones. */
function is_one_of(string $value, array $allowed): bool
{
    return in_array($value, $allowed, true);
}

/** For checkbox groups: at least one value, and every value is allowed. */
function all_known(array $values, array $allowed): bool
{
    return $values !== [] && array_diff($values, $allowed) === [];
}

/** A real `Y-m-d` date in Vienna time, or null. */
function parse_date(string $value): ?DateTimeImmutable
{
    // `!` resets the time to 00:00, so dates compare as whole days.
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone(FORM_TIMEZONE));

    // createFromFormat accepts 2026-02-31 and turns it into 3 March. Formatting back catches that.
    if ($date === false || $date->format('Y-m-d') !== $value) {
        return null;
    }

    return $date;
}

/** A date from today (Vienna) up to `$maxMonths` ahead. */
function is_date_in_range(string $value, int $maxMonths): bool
{
    $date = parse_date($value);
    if ($date === null) {
        return false;
    }

    $today = new DateTimeImmutable('today', new DateTimeZone(FORM_TIMEZONE));

    return $date >= $today && $date <= $today->modify("+{$maxMonths} months");
}
