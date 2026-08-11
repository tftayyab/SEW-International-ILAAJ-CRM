<?php
/**
 * Role helpers — no login; role set from landing screen.
 */

declare(strict_types=1);

const ROLE_EDITOR = 'editor';
const ROLE_AMEER = 'ameer';

function current_role(): ?string
{
    $role = $_SESSION['role'] ?? null;
    if ($role === ROLE_EDITOR || $role === ROLE_AMEER) {
        return $role;
    }
    return null;
}

function is_editor(): bool
{
    return current_role() === ROLE_EDITOR;
}

function is_ameer(): bool
{
    return current_role() === ROLE_AMEER;
}

function require_role(string $role): void
{
    if (current_role() !== $role) {
        if (is_api_request()) {
            json_error('Unauthorized. Editor access required.', 403);
        }
        redirect(base_url('index.php'));
    }
}

function require_editor(): void
{
    require_role(ROLE_EDITOR);
}

function require_ameer(): void
{
    require_role(ROLE_AMEER);
}

function require_any_role(): void
{
    if (!current_role()) {
        if (is_api_request()) {
            json_error('Please select a role from the home screen.', 401);
        }
        redirect(base_url('index.php'));
    }
}

function set_role(string $role): void
{
    if ($role !== ROLE_EDITOR && $role !== ROLE_AMEER) {
        return;
    }
    $_SESSION['role'] = $role;
}

function clear_role(): void
{
    unset($_SESSION['role']);
}
