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

/** Is a nav item visible to $role? Empty/absent allow-list means everyone. */
function nav_role_ok(?array $rolesAllowed, ?string $role): bool {
    if (empty($rolesAllowed)) return true;
    return $role !== null && in_array($role, $rolesAllowed, true);
}

/** Filter a nav array to the items $role may see. */
function app_nav_visible(array $nav, ?string $role): array {
    return array_values(array_filter($nav, fn($it) => nav_role_ok($it['roles'] ?? null, $role)));
}

/** Can $role open the page identified by $navKey in the active product? Unknown keys are allowed. */
function app_can_access(string $navKey, ?string $role): bool {
    foreach (app_nav() as $it) {
        if ($it['key'] === $navKey) return nav_role_ok($it['roles'] ?? null, $role);
    }
    return true;
}

/** Page guard: bounce $role to the product home if it cannot access $navKey. */
function app_require_access(string $navKey): void {
    $role = function_exists('user_role') ? user_role() : null;
    if (!app_can_access($navKey, $role)) {
        $home = app_ctx()['home'] ?? 'index.php';
        header('Location: ' . base_url($home)); exit;
    }
}
