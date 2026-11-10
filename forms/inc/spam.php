<?php

declare(strict_types=1);

require_once __DIR__ . '/validate.php';

/** Forms sent faster than this after page load are from bots. */
const MIN_FILL_MS = 3000;

/*
 * Honeypot + time trap. Both are cheap and invisible to humans.
 * The handler answers spam with a fake success and sends nothing, so bots don't learn what failed.
 */
function is_spam(): bool
{
    // Honeypot: humans never see the `website` field, bots fill in everything.
    if (field('website') !== '') {
        return true;
    }

    // Time trap: JS (3.5) writes the page-load time in ms. Without JS it stays empty, so skip the check.
    $ts = field('ts');
    if ($ts === '') {
        return false;
    }
    // JS only ever writes digits.
    if (preg_match('/^[0-9]{1,15}$/D', $ts) !== 1) {
        return true;
    }

    $elapsed = (int) floor(microtime(true) * 1000) - (int) $ts;

    // Negative = the visitor's clock is ahead of ours. Let it pass: a wrong clock must never block a real person.
    return $elapsed >= 0 && $elapsed < MIN_FILL_MS;
}
