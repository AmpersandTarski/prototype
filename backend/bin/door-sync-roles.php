<?php

/**
 * The role synchroniser of the door (OK-33), for a deployment.
 *
 * Sees to it that Pocket ID has a group for every role of the script, the API of the prototype,
 * and a permission for every role on that API. Run it in the container of the prototype after
 * every deployment, for instance:
 *
 *     docker exec <prototype> php /var/www/backend/bin/door-sync-roles.php
 *     docker exec <prototype> php /var/www/backend/bin/door-sync-roles.php --dry-run
 *
 * It reads three environment variables:
 *
 *     AMPERSAND_DOOR_TOKEN_ISSUER    the address of Pocket ID, as for the door itself
 *     AMPERSAND_DOOR_TOKEN_AUDIENCE  the resource of the API under which the prototype is registered
 *     POCKET_ID_API_KEY              an API key of Pocket ID (Settings, API keys); it can change every
 *                                    group and permission, so it lives only in the .env of the server
 *
 * Exits 0 when every role has its group, 1 when a role could not get one (the report names it),
 * and 2 when the synchroniser could not run.
 */

require_once __DIR__ . '/../lib/autoload.php';

use Ampersand\Misc\DoorRoleSync;

$dryRun = in_array('--dry-run', $argv, true);
$issuer = getenv('AMPERSAND_DOOR_TOKEN_ISSUER') ?: '';
$audience = getenv('AMPERSAND_DOOR_TOKEN_AUDIENCE') ?: '';
$apiKey = getenv('POCKET_ID_API_KEY') ?: '';

$missing = array_keys(array_filter([
    'AMPERSAND_DOOR_TOKEN_ISSUER' => $issuer,
    'AMPERSAND_DOOR_TOKEN_AUDIENCE' => $audience,
    'POCKET_ID_API_KEY' => $apiKey,
], fn ($value) => trim($value) === ''));
if ($missing !== []) {
    fwrite(STDERR, "door-sync-roles: set " . implode(', ', $missing) . "\n");
    exit(2);
}

$generics = __DIR__ . '/../generics';
try {
    $roles = DoorRoleSync::rolesFromFile("{$generics}/roles.json");
    $settings = json_decode((string) @file_get_contents("{$generics}/settings.json"), true);
    $contextName = is_array($settings) ? ($settings['global.contextName'] ?? 'Ampersand prototype') : 'Ampersand prototype';

    $sync = DoorRoleSync::forPocketId($issuer, $apiKey, $audience, (string) $contextName, $dryRun);
    $complete = $sync->sync($roles);
} catch (Throwable $e) {
    fwrite(STDERR, "door-sync-roles: " . $e->getMessage() . "\n");
    exit(2);
}

foreach ($sync->report() as $line) {
    echo $line, "\n";
}
exit($complete ? 0 : 1);
