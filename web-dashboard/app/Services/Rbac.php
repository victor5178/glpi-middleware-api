<?php

namespace App\Services;

/**
 * Resolves the currently logged-in GLPI user's effective permissions and answers
 * can()/isAdmin() questions for the rest of the app (route middleware, the @perm
 * Blade directive, and controller guards).
 *
 * Resolution order:
 *   1. No session user            -> deny everything.
 *   2. Username in config super_admins -> full Administrator (fail-safe).
 *   3. Assigned role in the DB     -> that role's permissions (is_admin => all).
 *   4. No assignment               -> the configured default role's permissions.
 *
 * The resolved map is cached for the lifetime of the request (this service is a
 * singleton) so the middleware is only queried once per page.
 */
class Rbac
{
    protected ?array $resolved = null;

    public function __construct(protected MiddlewareClient $client) {}

    /** True if the current user may perform $action on $module. */
    public function can(string $module, string $action = 'view'): bool
    {
        $r = $this->resolve();
        if ($r['is_admin']) {
            return true;
        }
        return (bool) ($r['permissions'][$module][$action] ?? false);
    }

    public function isAdmin(): bool
    {
        return $this->resolve()['is_admin'];
    }

    public function roleName(): ?string
    {
        return $this->resolve()['role_name'];
    }

    /** Force a re-fetch (e.g. right after an admin changes assignments). */
    public function flush(): void
    {
        $this->resolved = null;
    }

    /** @return array{is_admin:bool, role_name:?string, permissions:array} */
    protected function resolve(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $username = session('glpi_user');
        if (! $username) {
            return $this->resolved = ['is_admin' => false, 'role_name' => null, 'permissions' => []];
        }

        // Fail-safe super admins from config — always full access.
        $supers = array_map('strtolower', (array) config('rbac.super_admins', []));
        if (in_array(strtolower((string) $username), $supers, true)) {
            return $this->resolved = ['is_admin' => true, 'role_name' => 'Administrator (config)', 'permissions' => []];
        }

        $data = $this->client->resolvePermissions((string) $username) ?? [];
        $isAdmin = (bool) ($data['is_admin'] ?? false);
        $roleName = $data['role_name'] ?? null;
        $perms = is_array($data['permissions'] ?? null) ? $data['permissions'] : [];

        // No role assigned -> fall back to the configured default role.
        if (empty($data['role_id']) && ! $isAdmin) {
            [$roleName, $isAdmin, $perms] = $this->defaultRole();
        }

        return $this->resolved = ['is_admin' => $isAdmin, 'role_name' => $roleName, 'permissions' => $perms];
    }

    /** @return array{0:?string,1:bool,2:array} name, is_admin, permissions */
    protected function defaultRole(): array
    {
        $name = (string) config('rbac.default_role', '');
        if ($name === '') {
            return [null, false, []]; // deny-by-default
        }
        foreach ($this->client->rbacRoles() as $role) {
            if (strcasecmp((string) ($role['name'] ?? ''), $name) === 0) {
                return [$role['name'], (bool) ($role['is_admin'] ?? false), is_array($role['permissions'] ?? null) ? $role['permissions'] : []];
            }
        }
        return [null, false, []];
    }
}
