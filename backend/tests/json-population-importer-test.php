<?php

/*
 * Zelfstandige test voor de streaming populatie-importer (deze repo heeft geen
 * phpunit-suite voor de backend). Draaien:
 *
 *     php backend/tests/json-population-importer-test.php
 *
 * Test de streaming-laag (blocks()) zonder database: sleutelvolgorde-
 * onafhankelijkheid, foutafhandeling en de geheugen-eigenschap waar deze
 * importer voor bestaat (piek onafhankelijk van de bestandsgrootte).
 */

require __DIR__ . '/../lib/autoload.php';

use Ampersand\Exception\BadRequestException;
use Ampersand\IO\JsonPopulationImporter;

$ref = new ReflectionClass(JsonPopulationImporter::class);
$imp = $ref->newInstanceWithoutConstructor();   // parse-laag heeft Model/Logger niet nodig
$blocksM = $ref->getMethod('blocks');

$fail = 0;
function check(bool $ok, string $msg): void
{
    global $fail;
    echo ($ok ? '  OK   ' : '  FAIL ') . $msg . "\n";
    if (!$ok) {
        $fail++;
    }
}

function blocksOf(object $imp, ReflectionMethod $m, string $json, string $pointer, string $nameKey, string $listKey): array
{
    $f = tempnam(sys_get_temp_dir(), 'pop') . '.json';
    file_put_contents($f, $json);
    try {
        return iterator_to_array($m->invoke($imp, $f, $pointer, $nameKey, $listKey), false);
    } finally {
        unlink($f);
    }
}

// 1) normale sleutelvolgorde -> paren [naam, lijst]
$bs = blocksOf($imp, $blocksM, '{"atoms":[{"concept":"A","atoms":["a1","a2"]}],"links":[]}', '/atoms/-', 'concept', 'atoms');
check(count($bs) === 1 && $bs[0][0] === 'A' && $bs[0][1] === ['a1', 'a2'],
    'atoms-blok, normale sleutelvolgorde');

// 2) omgekeerde sleutelvolgorde — zó schrijft Population::export() het zelf
//    (atoms vóór concept, links vóór relation); moet dus blijven werken
$bs = blocksOf($imp, $blocksM,
    '{"atoms":[{"atoms":["a1"],"concept":"A"}],"links":[{"links":[{"src":"a","tgt":"b"}],"relation":"r[A*B]"}]}',
    '/links/-', 'relation', 'links');
check(count($bs) === 1 && $bs[0][0] === 'r[A*B]' && $bs[0][1][0]->src === 'a',
    'links-blok, omgekeerde sleutelvolgorde (export-formaat)');

// 3) meerdere blokken achter elkaar (state machine reset per blok)
$bs = blocksOf($imp, $blocksM,
    '{"atoms":[{"concept":"A","atoms":["a1"]},{"concept":"B","atoms":["b1","b2"]}]}', '/atoms/-', 'concept', 'atoms');
check(count($bs) === 2 && $bs[0][0] === 'A' && $bs[1][0] === 'B' && $bs[1][1] === ['b1', 'b2'],
    'twee opeenvolgende blokken');

// 4) ontbrekende toplevel-key levert een lege stroom (bestand met alleen links)
$bs = blocksOf($imp, $blocksM, '{"links":[]}', '/atoms/-', 'concept', 'atoms');
check($bs === [], 'ontbrekende atoms-key levert lege stroom');

// 5) kapotte JSON -> BadRequestException
try {
    blocksOf($imp, $blocksM, '{"atoms": [ {"concept": "A", ', '/atoms/-', 'concept', 'atoms');
    check(false, 'syntaxfout gooit BadRequestException');
} catch (BadRequestException $e) {
    check(true, 'syntaxfout -> BadRequestException');
}

// 5) GEHEUGEN-REGRESSIE: een groot bestand (2M atomen, ~40 blokken) streamen mag
//    slechts een fractie van de bestandsgrootte aan piekgeheugen kosten. Dit is de
//    eigenschap waarvoor deze importer bestaat; deze check breekt zodra iemand de
//    populatie weer materialiseert.
$f = tempnam(sys_get_temp_dir(), 'big') . '.json';
$h = fopen($f, 'w');
fwrite($h, '{"atoms":[');
for ($c = 0; $c < 40; $c++) {
    if ($c) {
        fwrite($h, ',');
    }
    fwrite($h, '{"concept":"C' . $c . '","atoms":[');
    for ($i = 0; $i < 50000; $i++) {
        fwrite($h, ($i ? ',' : '') . '"atom-' . $c . '-' . $i . '"');
    }
    fwrite($h, ']}');
}
fwrite($h, '],"links":[]}');
fclose($h);
$size = filesize($f);

$n = 0;
foreach ($blocksM->invoke($imp, $f, '/atoms/-', 'concept', 'atoms') as [$name, $list]) {
    $n += count($list);
}
$peak = memory_get_peak_usage(true);
unlink($f);
check($n === 2000000, "2M atomen gelezen (werkelijk: $n)");
check($peak < $size / 2, sprintf('piekgeheugen %.0f MB < helft bestandsgrootte (%.0f MB)',
    $peak / 1048576, $size / 1048576));

echo $fail === 0 ? "\nALLE TESTS OK\n" : "\n{$fail} TEST(S) GEFAALD\n";
exit($fail === 0 ? 0 : 1);
