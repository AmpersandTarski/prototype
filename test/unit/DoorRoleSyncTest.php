<?php

/**
 * Standalone test for the role synchroniser of the door ({@see \Ampersand\Misc\DoorRoleSync}, OK-33).
 *
 * A fake Pocket ID keeps groups, APIs and permissions in memory and answers the same endpoints as
 * Pocket ID v2.19.2 (swagger.json of pocket-id.org): GET and POST /api/user-groups, GET and POST
 * /api/apis, GET /api/apis/{id} and PUT /api/apis/{id}/permissions, which replaces the full set
 * and keeps a permission whose key stays, with its id. Like Pocket ID, it refuses a permission key
 * that is not an OAuth scope token or that it reserves, and a group name shorter than two characters.
 *
 * Run:
 *     php test/unit/DoorRoleSyncTest.php
 *
 * Exits 0 when all checks pass, 1 otherwise.
 */

require_once __DIR__ . '/../../backend/lib/autoload.php';

use Ampersand\Misc\DoorRoleSync;

const RESOURCE = 'https://app.example.org';

$pass = 0;
$fail = 0;
function check(string $label, $actual, $expected): void
{
    global $pass, $fail;
    if ($actual === $expected) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}\n       expected " . var_export($expected, true) . "\n       got      " . var_export($actual, true) . "\n";
    }
}

class FakePocketId
{
    public array $groups = [];
    public array $apis = [];
    public array $calls = [];
    private int $nextId = 1;

    public function call(string $method, string $path, ?array $body = null): array
    {
        $this->calls[] = "{$method} " . strtok($path, '?');
        parse_str((string) parse_url($path, PHP_URL_QUERY), $query);
        $route = strtok($path, '?');

        if ($method === 'GET' && $route === '/api/user-groups') {
            $hits = array_values(array_filter($this->groups, fn ($g) => str_contains($g['name'], $query['search'] ?? '')));
            return ['data' => $hits, 'pagination' => ['totalPages' => 1]];
        }
        if ($method === 'POST' && $route === '/api/user-groups') {
            if (mb_strlen($body['name']) < 2) {
                throw new RuntimeException('400 name too short');
            }
            $group = ['id' => 'g' . $this->nextId++, 'name' => $body['name'], 'friendlyName' => $body['friendlyName']];
            $this->groups[] = $group;
            return $group;
        }
        if ($method === 'GET' && $route === '/api/apis') {
            $hits = array_values(array_filter($this->apis, fn ($a) => str_contains($a['resource'], $query['search'] ?? '')));
            // The list leaves out the permissions; GET /api/apis/{id} has them.
            return ['data' => array_map(fn ($a) => ['id' => $a['id'], 'name' => $a['name'], 'resource' => $a['resource']], $hits), 'pagination' => ['totalPages' => 1]];
        }
        if ($method === 'POST' && $route === '/api/apis') {
            $api = ['id' => 'a' . $this->nextId++, 'name' => $body['name'], 'resource' => $body['resource'], 'permissions' => []];
            $this->apis[$api['id']] = $api;
            return $api;
        }
        if ($method === 'GET' && preg_match('#^/api/apis/([^/]+)$#', $route, $m)) {
            return $this->apis[$m[1]];
        }
        if ($method === 'PUT' && preg_match('#^/api/apis/([^/]+)/permissions$#', $route, $m)) {
            $existing = [];
            foreach ($this->apis[$m[1]]['permissions'] as $p) {
                $existing[$p['key']] = $p;
            }
            $new = [];
            foreach ($body['permissions'] as $p) {
                if (!DoorRoleSync::canBePermission($p['key'])) {
                    throw new RuntimeException("400 permissions key {$p['key']} invalid");
                }
                $new[] = ['id' => $existing[$p['key']]['id'] ?? 'p' . $this->nextId++, 'key' => $p['key'], 'name' => $p['name'], 'description' => $p['description'] ?? ''];
            }
            $this->apis[$m[1]]['permissions'] = $new;
            return $this->apis[$m[1]];
        }
        throw new RuntimeException("unexpected call {$method} {$path}");
    }

    public function groupNames(): array
    {
        $names = array_map(fn ($g) => $g['name'], $this->groups);
        sort($names);
        return $names;
    }

    public function permissionKeys(): array
    {
        $keys = [];
        foreach ($this->apis as $api) {
            foreach ($api['permissions'] as $p) {
                $keys[] = $p['key'];
            }
        }
        sort($keys);
        return $keys;
    }
}

function sync(FakePocketId $pocketId, array $roles, bool $dryRun = false): DoorRoleSync
{
    $sync = new DoorRoleSync(Closure::fromCallable([$pocketId, 'call']), RESOURCE, 'DoorTest', $dryRun);
    $sync->sync($roles);
    return $sync;
}

$scriptRoles = ['Administrator', 'Editor', 'Reader', 'ExecEngine', 'SYSTEM', 'Any', 'Anonymous'];

echo "A first run on an empty Pocket ID\n";
$pocketId = new FakePocketId();
$first = sync($pocketId, $scriptRoles);
check("every role that is handed out gets a group", $pocketId->groupNames(), ['Administrator', 'Editor', 'Reader']);
check("the API of the prototype exists", array_values(array_map(fn ($a) => $a['resource'], $pocketId->apis)), [RESOURCE]);
check("every role gets a permission on it", $pocketId->permissionKeys(), ['Administrator', 'Editor', 'Reader']);
check("the run reports no error", $first->errors(), []);

echo "A second run changes nothing\n";
$callsBefore = count($pocketId->calls);
$second = sync($pocketId, $scriptRoles);
$writes = array_filter(array_slice($pocketId->calls, $callsBefore), fn ($c) => !str_starts_with($c, 'GET'));
check("it writes nothing", array_values($writes), []);
check("it reports that every role has its permission", in_array('every role has its permission', $second->report(), true), true);

echo "A new role in the script\n";
$apiId = array_key_first($pocketId->apis);
$editorIdBefore = array_values(array_filter($pocketId->apis[$apiId]['permissions'], fn ($p) => $p['key'] === 'Editor'))[0]['id'];
$pocketId->groups[] = ['id' => 'other', 'name' => 'OtherApplication', 'friendlyName' => 'Other'];
sync($pocketId, array_merge($scriptRoles, ['Stampreporter']));
check("gets a group", in_array('Stampreporter', $pocketId->groupNames(), true), true);
check("and a permission", $pocketId->permissionKeys(), ['Administrator', 'Editor', 'Reader', 'Stampreporter']);
$editorIdAfter = array_values(array_filter($pocketId->apis[$apiId]['permissions'], fn ($p) => $p['key'] === 'Editor'))[0]['id'];
check("an existing permission keeps its id, so a machine keeps its grant", $editorIdAfter, $editorIdBefore);
check("a group of another application stays", in_array('OtherApplication', $pocketId->groupNames(), true), true);

echo "A role that fits only as a group\n";
$pocketId = new FakePocketId();
$sync = sync($pocketId, ['Coördinator', 'Read Only', 'groups', 'Editor']);
check("the groups exist", $pocketId->groupNames(), ['Coördinator', 'Editor', 'Read Only', 'groups']);
check("only the scope tokens became permissions", $pocketId->permissionKeys(), ['Editor']);
check("the report names a role that machines cannot get",
    count(array_filter($sync->report(), fn ($l) => str_contains($l, "'Coördinator' cannot be a permission"))), 1);
check("this is no error", $sync->errors(), []);

echo "A role that cannot be a group\n";
$pocketId = new FakePocketId();
$sync = sync($pocketId, ['Editor', 'A,B', 'X']);
check("both are errors", $sync->errors(), ['A,B', 'X']);
check("the other roles still get their group", $pocketId->groupNames(), ['Editor']);

echo "A dry run\n";
$pocketId = new FakePocketId();
$dry = sync($pocketId, $scriptRoles, true);
check("writes nothing", array_values(array_filter($pocketId->calls, fn ($c) => !str_starts_with($c, 'GET'))), []);
check("and says what it would do", count(array_filter($dry->report(), fn ($l) => str_starts_with($l, 'would '))), 7);

echo "The roles of a compiled script\n";
$file = tempnam(sys_get_temp_dir(), 'roles-');
file_put_contents($file, json_encode([['name' => 'Editor', 'isService' => false], ['name' => 'ExecEngine', 'isService' => false]]));
check("come from roles.json", DoorRoleSync::rolesFromFile($file), ['Editor', 'ExecEngine']);
unlink($file);

echo "\n{$pass} checks passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
