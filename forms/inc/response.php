<?php

declare(strict_types=1);

/*
 * How a handler answers. Two kinds of visitors:
 * - JS (3.5): fetch() with `Accept: application/json` gets JSON and shows the result in the form.
 * - No JS: a normal form POST gets a redirect (success) or the error page.
 */

/** Handlers only accept POST. Anything else (a visitor opening the URL) gets 405. */
function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit;
    }
}

/** True for fetch() from JS, which asks for JSON. */
function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function send_json(int $status, array $data): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Success. Also the fake success for spam. */
function respond_success(string $redirect = '/danke/'): never
{
    if (wants_json()) {
        // JS goes to the same page itself, so the target is defined only here.
        send_json(200, ['ok' => true, 'redirect' => $redirect]);
    }

    // 303 See Other: the browser follows with a GET, so a reload on /danke/ doesn't send the form again.
    header("Location: {$redirect}", true, 303);
    exit;
}

/*
 * `$noun` names what was sent, in the texts the visitor sees: "Anfrage", "Bewerbung".
 * Both are feminine, so "Ihre …" fits both.
 */

/** Validation failed. `$errors` = field name → German message, in form order. */
function respond_invalid(string $noun, array $errors): never
{
    if (wants_json()) {
        send_json(422, ['ok' => false, 'errors' => $errors]);
    }

    render_error_page(422, "{$noun} nicht gesendet", 'Bitte prüfen Sie diese Angaben:', $errors);
}

/** Sending failed. No details for the visitor: they can't fix it, and details only help attackers. */
function respond_send_failed(string $noun): never
{
    $message = "Ihre {$noun} konnte gerade nicht gesendet werden. "
        . 'Bitte versuchen Sie es später noch einmal oder rufen Sie uns an.';

    if (wants_json()) {
        send_json(500, ['ok' => false, 'message' => $message]);
    }

    render_error_page(500, "{$noun} nicht gesendet", $message, []);
}

/** The HTML error page for visitors without JS. The template uses `$title`, `$lead` and `$errors`. */
function render_error_page(int $status, string $title, string $lead, array $errors): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    require __DIR__ . '/error-page.php';
    exit;
}

/** Escapes text for HTML. Everything printed into the page goes through this (prevents XSS). */
function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
