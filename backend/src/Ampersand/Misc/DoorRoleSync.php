<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Misc;

use Closure;
use RuntimeException;

/**
 * The role synchroniser of the door (OK-33): it sees to it that the identity service knows every
 * role of the script.
 *
 * With the door on (OK-32), a person gets a role through a group of the same name in the identity
 * service, and a machine through a permission of the same name on the API under which the
 * prototype is registered. The synchroniser reads the roles of the script (`roles.json`, which the
 * compiler writes with every prototype) and adds to Pocket ID what is missing: a group per role,
 * the API of the prototype, and a permission per role on that API. It removes nothing, because
 * the identity service may know groups that another application uses.
 *
 * Two kinds of role names do not fit everywhere. A name with a comma cannot be a group, because the
 * proxy passes the groups comma-separated; a name shorter than two characters cannot be a group in
 * Pocket ID. Such a role is an error, and the deployment that runs the synchroniser stops on it. A
 * name that is not an OAuth scope token (printable ASCII without space, double quote or backslash,
 * RFC 6749), or that Pocket ID reserves, cannot be a permission: such a role gets its group, so
 * people can have it, and the report names it as a role that no machine can get.
 *
 * Pocket ID's API is called with an API key in the header X-API-KEY. The HTTP calls go through a
 * closure, so that the test can play Pocket ID.
 */
final class DoorRoleSync
{
    /** Roles that are never handed out, and so get no group and no permission. */
    public const ROLES_NOT_HANDED_OUT = ['ExecEngine', 'SYSTEM', ProtoContext::ROLE_ANY, ProtoContext::ROLE_ANONYMOUS];

    /** Permission keys that Pocket ID reserves for its own scopes (backend/internal/api/service.go). */
    public const RESERVED_PERMISSION_KEYS = ['openid', 'profile', 'email', 'email_verified', 'groups', 'offline_access'];

    /** @var string[] lines of the report */
    private array $report = [];

    /** @var string[] roles that could not get a group */
    private array $errors = [];

    /**
     * @param Closure(string $method, string $path, ?array $body): array $call calls the API of Pocket ID
     *        and returns the decoded JSON of the answer; it throws on any status other than 2xx
     */
    public function __construct(
        private Closure $call,
        private string $apiResource,
        private string $apiName,
        private bool $dryRun = false
    ) {
        $this->apiResource = rtrim($apiResource, '/');
    }

    /**
     * A synchroniser that talks to the Pocket ID at the given address.
     */
    public static function forPocketId(string $address, string $apiKey, string $apiResource, string $apiName, bool $dryRun = false): self
    {
        $address = rtrim($address, '/');
        $call = function (string $method, string $path, ?array $body = null) use ($address, $apiKey): array {
            $curl = curl_init($address . $path);
            $headers = ['X-API-KEY: ' . $apiKey, 'Accept: application/json'];
            if ($body !== null) {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
            }
            curl_setopt_array($curl, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 20,
            ]);
            $answer = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);
            curl_close($curl);
            if ($answer === false || $status < 200 || $status >= 300) {
                // The answer of Pocket ID names the field and the reason; the API key is not in it.
                throw new RuntimeException("{$method} {$path} gave status {$status} {$error} " . substr((string) $answer, 0, 300));
            }
            $decoded = json_decode((string) $answer, true);
            return is_array($decoded) ? $decoded : [];
        };
        return new self(Closure::fromCallable($call), $apiResource, $apiName, $dryRun);
    }

    /**
     * Read the role names from roles.json.
     *
     * @return string[]
     */
    public static function rolesFromFile(string $rolesJson): array
    {
        $roles = json_decode((string) file_get_contents($rolesJson), true);
        if (!is_array($roles)) {
            throw new RuntimeException("Cannot read the roles in {$rolesJson}");
        }
        return array_values(array_filter(array_map(fn ($role) => $role['name'] ?? null, $roles), 'is_string'));
    }

    /**
     * Add what is missing. Returns true when every role has its group.
     *
     * @param string[] $roleNames all roles of the script, as roles.json names them
     */
    public function sync(array $roleNames): bool
    {
        $roles = array_values(array_filter(
            array_unique($roleNames),
            fn (string $name) => !in_array($name, self::ROLES_NOT_HANDED_OUT, true)
        ));

        foreach ($roles as $role) {
            $this->syncGroup($role);
        }
        $this->syncPermissions($roles);

        return $this->errors === [];
    }

    /** @return string[] */
    public function report(): array
    {
        return $this->report;
    }

    /** @return string[] */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Whether a role name can be the name of a permission: an OAuth scope token that Pocket ID does not reserve.
     */
    public static function canBePermission(string $role): bool
    {
        return preg_match('/^[\x21\x23-\x5B\x5D-\x7E]+$/', $role) === 1
            && !in_array(strtolower($role), self::RESERVED_PERMISSION_KEYS, true);
    }

    private function syncGroup(string $role): void
    {
        if (str_contains($role, ',')) {
            $this->errors[] = $role;
            $this->report[] = "error: role '{$role}' has a comma, which would split the list of groups; rename the role";
            return;
        }
        if (mb_strlen($role) < 2) {
            $this->errors[] = $role;
            $this->report[] = "error: role '{$role}' is shorter than two characters, the minimum of a group in Pocket ID; rename the role";
            return;
        }

        if ($this->groupExists($role)) {
            $this->report[] = "group '{$role}' exists";
            return;
        }
        if (!$this->dryRun) {
            ($this->call)('POST', '/api/user-groups', ['name' => $role, 'friendlyName' => mb_substr($role, 0, 50)]);
        }
        $this->report[] = ($this->dryRun ? 'would create' : 'created') . " group '{$role}'";
    }

    private function groupExists(string $role): bool
    {
        $page = 1;
        do {
            $answer = ($this->call)('GET', '/api/user-groups?' . http_build_query([
                'search' => $role,
                'pagination' => ['page' => $page, 'limit' => 100],
            ]), null);
            foreach ($answer['data'] ?? [] as $group) {
                if (($group['name'] ?? null) === $role) {
                    return true;
                }
            }
            $totalPages = (int) ($answer['pagination']['totalPages'] ?? 1);
            $page++;
        } while ($page <= $totalPages);
        return false;
    }

    /**
     * @param string[] $roles
     */
    private function syncPermissions(array $roles): void
    {
        $permissionRoles = [];
        foreach ($roles as $role) {
            if (self::canBePermission($role)) {
                $permissionRoles[] = $role;
            } else {
                $this->report[] = "note: role '{$role}' cannot be a permission (not an OAuth scope token, or reserved by Pocket ID); people can have it, machines cannot";
            }
        }

        $api = $this->findApi();
        if ($api === null) {
            if ($this->dryRun) {
                $this->report[] = "would create API '{$this->apiName}' with resource {$this->apiResource}";
                foreach ($permissionRoles as $role) {
                    $this->report[] = "would add permission '{$role}'";
                }
                return;
            }
            $api = ($this->call)('POST', '/api/apis', ['name' => $this->apiName, 'resource' => $this->apiResource]);
            $this->report[] = "created API '{$this->apiName}' with resource {$this->apiResource}";
        } else {
            $this->report[] = "API with resource {$this->apiResource} exists";
        }

        // PUT replaces the full set, so the existing permissions go along unchanged; Pocket ID keeps
        // a permission whose key stays, with its grants to machines.
        $permissions = [];
        foreach ($api['permissions'] ?? [] as $permission) {
            $permissions[$permission['key']] = [
                'key' => $permission['key'],
                'name' => $permission['name'] ?? $permission['key'],
                'description' => $permission['description'] ?? '',
            ];
        }
        $added = [];
        foreach ($permissionRoles as $role) {
            if (!isset($permissions[$role])) {
                $permissions[$role] = ['key' => $role, 'name' => $role, 'description' => "Role {$role} of the Ampersand script"];
                $added[] = $role;
            }
        }

        if ($added === []) {
            $this->report[] = "every role has its permission";
            return;
        }
        if (!$this->dryRun) {
            ($this->call)('PUT', "/api/apis/{$api['id']}/permissions", ['permissions' => array_values($permissions)]);
        }
        foreach ($added as $role) {
            $this->report[] = ($this->dryRun ? 'would add' : 'added') . " permission '{$role}'";
        }
    }

    private function findApi(): ?array
    {
        $answer = ($this->call)('GET', '/api/apis?' . http_build_query([
            'search' => $this->apiResource,
            'pagination' => ['page' => 1, 'limit' => 100],
        ]), null);
        foreach ($answer['data'] ?? [] as $api) {
            if (rtrim((string) ($api['resource'] ?? ''), '/') === $this->apiResource) {
                // The list may leave out the permissions; the API itself has them.
                return ($this->call)('GET', "/api/apis/{$api['id']}", null);
            }
        }
        return null;
    }
}
