<?php

namespace App\Http\Controllers;

use App\Services\MiddlewareClient;
use App\Services\Rbac;
use Illuminate\Http\Request;

/**
 * Administrator-only screen to manage RBAC: define roles (per-module/action
 * permissions) and assign GLPI usernames to roles. Guarded by `perm:admin`.
 */
class AccessController extends Controller
{
    public function index(MiddlewareClient $client)
    {
        return view('access', [
            'roles'       => $client->rbacRoles(),
            'assignments' => $client->rbacUserRoles(),
            'modules'     => config('rbac.modules'),
            'actions'     => config('rbac.actions'),
            'error'       => $client->lastError,
        ]);
    }

    public function saveRole(Request $request, MiddlewareClient $client, Rbac $rbac)
    {
        $data = $request->validate([
            'id'            => 'nullable|integer',
            'name'          => 'required|string|max:80',
            'is_admin'      => 'nullable|boolean',
            'permissions'   => 'nullable|array',
        ]);

        // Build a clean permission map from the checkbox grid.
        $perms = [];
        foreach (array_keys(config('rbac.modules')) as $module) {
            foreach (config('rbac.actions') as $action) {
                $perms[$module][$action] = (int) ($data['permissions'][$module][$action] ?? 0);
            }
        }

        $payload = [
            'name'        => $data['name'],
            'is_admin'    => (int) ($data['is_admin'] ?? 0),
            'permissions' => $perms,
        ];

        $ok = empty($data['id'])
            ? $client->saveRbacRole($payload)
            : $client->updateRbacRole((int) $data['id'], $payload);

        $rbac->flush();

        return $ok
            ? back()->with('status', 'Role saved.')
            : back()->with('error', $client->lastError ?? 'Could not save the role.');
    }

    public function deleteRole(Request $request, MiddlewareClient $client, Rbac $rbac)
    {
        $id = (int) $request->input('id');
        $ok = $id > 0 && $client->deleteRbacRole($id);
        $rbac->flush();

        return $ok
            ? back()->with('status', 'Role deleted.')
            : back()->with('error', $client->lastError ?? 'Could not delete the role.');
    }

    public function assignUser(Request $request, MiddlewareClient $client, Rbac $rbac)
    {
        $data = $request->validate([
            'username' => 'required|string|max:150',
            'role_id'  => 'required|integer',
        ]);

        $ok = $client->assignRbacUser(trim($data['username']), (int) $data['role_id']);
        $rbac->flush();

        return $ok
            ? back()->with('status', 'User assigned.')
            : back()->with('error', $client->lastError ?? 'Could not assign the user.');
    }

    public function removeUser(Request $request, MiddlewareClient $client, Rbac $rbac)
    {
        $username = (string) $request->input('username');
        $ok = $username !== '' && $client->removeRbacUser($username);
        $rbac->flush();

        return $ok
            ? back()->with('status', 'Assignment removed.')
            : back()->with('error', $client->lastError ?? 'Could not remove the assignment.');
    }
}
