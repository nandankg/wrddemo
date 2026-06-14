<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_roles.php';
require_once __DIR__ . '/app_context.php';

function current_user(): ?array { return $_SESSION['user'] ?? null; }
function is_logged_in(): bool { return isset($_SESSION['user']); }
function user_role(): ?string { return $_SESSION['user']['role'] ?? null; }

function login_user(string $username, string $password): bool {
    $st = db()->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([$username]);
    $u = $st->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        unset($u['password_hash']);
        $_SESSION['user'] = $u;
        return true;
    }
    return false;
}

/** Quick role switch for the demo (no password). Scoped to the active product's roles. */
function switch_to_role(string $role): bool {
    $ctx = app_ctx();
    if ($ctx && !role_allowed_in_app($role, $ctx['key'])) return false;
    $st = db()->prepare('SELECT * FROM users WHERE role = ? LIMIT 1');
    $st->execute([$role]);
    $u = $st->fetch();
    if ($u) { unset($u['password_hash']); $_SESSION['user'] = $u; return true; }
    return false;
}

function logout(): void { unset($_SESSION['user']); }

function require_login(): void {
    if (!is_logged_in()) { header('Location: ' . base_url('auth/login.php')); exit; }
}

/** Role groups for menu visibility. */
function role_in(array $roles): bool { return in_array(user_role(), $roles, true); }

/** Human-friendly current role label. */
function role_label(): string {
    $u = current_user();
    return $u ? ($u['designation'] ?? $u['role']) : '';
}
