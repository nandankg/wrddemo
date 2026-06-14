<?php
declare(strict_types=1);
require_once __DIR__ . '/apps.php';

/** Default brand accent used by the launcher and any non-product page. */
const WRD_DEFAULT_ACCENT = '#0E7C86';

/**
 * Declare which product the current page belongs to.
 * Returns true if the key is known (context set), false otherwise (context cleared).
 */
function set_app_context(string $key): bool {
    $app = wrd_app($key);
    $GLOBALS['APP_CTX'] = $app;   // array or null
    return $app !== null;
}

function app_ctx(): ?array { return $GLOBALS['APP_CTX'] ?? null; }

function app_accent(): string { return app_ctx()['accent'] ?? WRD_DEFAULT_ACCENT; }

function app_roles(): array { return app_ctx()['roles'] ?? []; }

function app_nav(): array { return app_ctx()['nav'] ?? []; }
