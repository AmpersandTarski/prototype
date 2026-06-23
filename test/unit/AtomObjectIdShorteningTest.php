<?php

/**
 * Standalone regression test for object-identity shortening in {@see \Ampersand\Core\Atom::setId()}.
 *
 * Object atoms are stored in a VARCHAR(255) column and must be unique within their concept. Ids
 * longer than 254 characters are deterministically shortened to <first 243 chars>_<10 hex of sha1>.
 * This guarantees uniqueness within 254 chars while keeping every reference to the same id mapped
 * to the same atom (links, lookups, re-imports, both importers).
 *
 * Runs on the host with plain PHP — no database, no Ampersand compiler, no Docker. It builds a bare
 * Concept (of TType::OBJECT) without booting the application and constructs the real Atom on it.
 *
 * Run:
 *     php test/unit/AtomObjectIdShorteningTest.php
 *
 * Exits 0 when all checks pass, 1 otherwise.
 */

require __DIR__ . '/../../backend/lib/autoload.php';

use Ampersand\Core\Atom;
use Ampersand\Core\Concept;
use Ampersand\Core\TType;

/** Build a bare Concept of the given TType, bypassing its constructor (only ->type is used by setId). */
function makeConcept(TType $type): Concept
{
    $concept = (new ReflectionClass(Concept::class))->newInstanceWithoutConstructor();
    $concept->type = $type;
    return $concept;
}

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
        echo "  FAIL {$label}\n       expected: " . var_export($expected, true) . "\n       actual:   " . var_export($actual, true) . "\n";
    }
}

$object = makeConcept(TType::OBJECT);
$alphanumeric = makeConcept(TType::ALPHANUMERIC);

// Short object ids are kept verbatim.
check("short object id unchanged", (new Atom('plain-id', $object))->getId(), 'plain-id');
check("254-char object id unchanged length", mb_strlen((new Atom(str_repeat('a', 254), $object))->getId()), 254);

// Long object ids are shortened to exactly 254 chars: 243 + '_' + 10 hex.
$long = str_repeat('a', 300);
$shortened = (new Atom($long, $object))->getId();
check("long object id shortened to 254 chars", mb_strlen($shortened), 254);
check("shortened keeps 243-char prefix + underscore", substr($shortened, 0, 244), str_repeat('a', 243) . '_');
check("shortened suffix is 10 hex of sha1(full id)", substr($shortened, -10), substr(sha1($long), 0, 10));

// CROSS-REPO KNOWN-ANSWER VECTOR. This exact literal is also asserted in the Ampersand compiler's
// test suite (src/Ampersand/Test/Parser/QuickChecks.hs, doObjectIdShorteningTest). Both repos must
// produce this same string for "a" x 300, guaranteeing the compiler's importer and Atom::setId use
// an identical algorithm. Do not change one without the other.
check("known-answer vector matches compiler", $shortened, str_repeat('a', 243) . '_003ef1ba9e');

// Deterministic: same input -> same output (so all references map to the same atom).
check("deterministic", (new Atom($long, $object))->getId(), (new Atom($long, $object))->getId());

// Distinct ids that share the first 243 chars but differ only beyond char 254 stay distinct,
// because the hash is taken over the full id. This is exactly the collision the DB column would cause.
$idA = str_repeat('x', 260) . 'AAA';
$idB = str_repeat('x', 260) . 'BBB';
$shortA = (new Atom($idA, $object))->getId();
$shortB = (new Atom($idB, $object))->getId();
check("colliding-prefix ids stay distinct after shortening", $shortA !== $shortB, true);
check("both shortened within 254 chars", mb_strlen($shortA) <= 254 && mb_strlen($shortB) <= 254, true);

// Multibyte ids are counted in characters (not bytes) and not split mid-character.
$mb = str_repeat('é', 300); // 300 characters, 600 bytes
$shortMb = (new Atom($mb, $object))->getId();
check("multibyte id shortened to 254 chars", mb_strlen($shortMb), 254);
check("multibyte prefix intact (no split char)", mb_substr($shortMb, 0, 243), str_repeat('é', 243));

// Shortening is OBJECT-only: other technical types keep their id verbatim (their id IS the value).
check("alphanumeric long id NOT shortened", (new Atom($long, $alphanumeric))->getId(), $long);

echo "\n==== SUMMARY: {$pass} passed, {$fail} failed ====\n";
exit($fail === 0 ? 0 : 1);
