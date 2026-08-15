<?php

/**
 * Standalone regression test for the runtime spreadsheet importer's multi-value (multi-column) support.
 *
 * Runs on the host with plain PHP — no database, no Ampersand compiler, no Docker. It exercises the
 * real {@see \Ampersand\IO\ExcelImporter} methods (via a small probe subclass) and builds its inputs
 * in-memory with the bundled PhpSpreadsheet, so there are no .xlsx fixtures to maintain.
 *
 * It covers the parse/split layer and the block-start detection. The database-level behaviour
 * (cartesian product, flipped relations, INTERFACE-approach add()) needs a running prototype and is
 * covered by the optional end-to-end script in test/projects/import-multivalue/e2e/.
 *
 * Run:
 *     php test/unit/ExcelImporterMultiValueTest.php
 *
 * Exits 0 when all checks pass, 1 otherwise.
 */

require __DIR__ . '/../../backend/lib/autoload.php';

use Ampersand\IO\ExcelImporter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exposes the protected importer methods under test, without booting the application
 * (the tested methods do not use the app or logger).
 */
class ExcelImporterTestProbe extends ExcelImporter
{
    public function __construct()
    {
        // Intentionally do not call parent::__construct(): the methods under test are pure.
    }

    public function tIsBracketed(string $v): bool
    {
        return $this->isBracketed($v);
    }

    public function tParseHeader(string $v): array
    {
        return $this->parseHeaderWithOptionalDelimiter($v);
    }

    public function tSplitDelimited(string $v, string $d): array
    {
        return $this->splitDelimited($v, $d);
    }

    public function tSplitCellValue(Cell $cell, ?string $d): array
    {
        return $this->splitCellValue($cell, $d);
    }

    public function tFindBlockStartRows(Worksheet $ws): array
    {
        return $this->findBlockStartRows($ws);
    }
}

$probe = new ExcelImporterTestProbe();

$pass = 0;
$fail = 0;
function check(string $label, $got, $expected): void
{
    global $pass, $fail;
    if ($got === $expected) {
        $pass++;
        echo "  PASS  $label\n";
    } else {
        $fail++;
        echo "  FAIL  $label\n";
        echo "        expected: " . json_encode($expected) . "\n";
        echo "        got     : " . json_encode($got) . "\n";
    }
}

echo "== isBracketed ==\n";
check("plain text",        $probe->tIsBracketed('Person'),     false);
check("bracketed",         $probe->tIsBracketed('[Person]'),   true);
check("bracketed + delim", $probe->tIsBracketed('[Skill,]'),   true);
check("outer spaces",      $probe->tIsBracketed('  [A] '),     true);
check("empty brackets",    $probe->tIsBracketed('[]'),         true);
check("only open",         $probe->tIsBracketed('[Person'),    false);
check("empty string",      $probe->tIsBracketed(''),           false);

echo "== parseHeaderWithOptionalDelimiter ==\n";
check("plain concept",        $probe->tParseHeader('EPPOcode'),        ['EPPOcode', null]);
check("multi comma",          $probe->tParseHeader('[EPPOcode,]'),     ['EPPOcode', ',']);
check("multi slash",          $probe->tParseHeader('[EPPOcode/]'),     ['EPPOcode', '/']);
check("multi semicolon",      $probe->tParseHeader('[Code;]'),         ['Code', ';']);
check("outer spaces trimmed", $probe->tParseHeader('  [EPPOcode,]  '), ['EPPOcode', ',']);
check("inner spaces trimmed", $probe->tParseHeader('[ EPPOcode ,]'),   ['EPPOcode', ',']);
check("short concept [A,]",   $probe->tParseHeader('[A,]'),            ['A', ',']);
check("not bracketed label",  $probe->tParseHeader('Works for'),       ['Works for', null]);
check("empty brackets []",    $probe->tParseHeader('[]'),              ['[]', null]);
check("3-char [X] no delim",  $probe->tParseHeader('[X]'),             ['[X]', null]);
check("empty string",         $probe->tParseHeader(''),                ['', null]);

echo "== splitDelimited (trim each + drop empties) ==\n";
check("comma with spaces",        $probe->tSplitDelimited('abc, def ,ghi', ','), ['abc', 'def', 'ghi']);
check("trailing delimiter empty", $probe->tSplitDelimited('abc,,def,',     ','), ['abc', 'def']);
check("only delimiters",          $probe->tSplitDelimited(',,,',           ','), []);
check("blank value",              $probe->tSplitDelimited('   ',           ','), []);
check("single value",             $probe->tSplitDelimited('single',        ','), ['single']);
check("slash delimiter",          $probe->tSplitDelimited('d1/ d2 /d1',    '/'), ['d1', 'd2', 'd1']);

echo "== splitCellValue ==\n";
$ss = new Spreadsheet();
$ws = $ss->getActiveSheet();
$ws->setCellValueExplicit('A1', ' spaced ', DataType::TYPE_STRING);
check("text no-delim preserves spaces", $probe->tSplitCellValue($ws->getCell('A1'), null), [' spaced ']);
$ws->setCellValueExplicit('A2', '', DataType::TYPE_STRING);
check("empty text no-delim", $probe->tSplitCellValue($ws->getCell('A2'), null), []);
$ws->setCellValueExplicit('A3', 'char2, char3 , ', DataType::TYPE_STRING);
check("text delim split", $probe->tSplitCellValue($ws->getCell('A3'), ','), ['char2', 'char3']);
$serial = Date::PHPToExcel(new DateTime('2024-03-15 00:00:00', new DateTimeZone('UTC')));
$ws->setCellValueExplicit('A4', $serial, DataType::TYPE_NUMERIC);
$ws->getStyle('A4')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
$expectedTs = '@' . (string) Date::excelToTimestamp((int) $serial);
check("datetime no-delim -> single @ts", $probe->tSplitCellValue($ws->getCell('A4'), null), [$expectedTs]);
check("datetime with delim not split",   $probe->tSplitCellValue($ws->getCell('A4'), ','), [$expectedTs]);

echo "== findBlockStartRows (block-start detection) ==\n";
// Three blocks. Note row 8 holds a source multi-value header '[Skill,]' directly below the block
// starter '[Skill]' on row 7 — it must NOT be treated as the start of a new block.
$bs = new Spreadsheet();
$bws = $bs->getActiveSheet();
$bws->fromArray([
    ['[Person]',    'skills'],     // 1  block start
    ['Person',      '[Skill,]'],   // 2
    ['pete',        'cooking'],    // 3
    ['john',        'reading'],    // 4
    ['mary',        ',,'],         // 5
    [null,          'comment'],    // 6  empty col A
    ['[Skill]',     'related'],    // 7  block start
    ['[Skill,]',    'Skill'],      // 8  source multi-value -> NOT a block start
    ['alpha, beta', 'gamma'],      // 9
    ['[Person]',    'enrolled~'],  // 10 block start
    ['Person',      '[Project,]'], // 11
    ['pete',        'p1, p2'],     // 12
], null, 'A1', true);
check("block starts skip the source-multi-value row", $probe->tFindBlockStartRows($bws), [1, 7, 10]);

// Sanity: a bracketed row on row 1 is always a start; adjacent bracketed rows yield only the first.
$bs2 = new Spreadsheet();
$bws2 = $bs2->getActiveSheet();
$bws2->fromArray([
    ['[A]',   'r'],   // 1 start
    ['[A,]',  'A'],   // 2 not a start (above is bracketed)
    ['x',     'y'],   // 3
], null, 'A1', true);
check("row 1 starts, adjacent bracketed absorbed", $probe->tFindBlockStartRows($bws2), [1]);

// Sanity: no brackets at all -> no blocks.
$bs3 = new Spreadsheet();
$bws3 = $bs3->getActiveSheet();
$bws3->fromArray([['a', 'b'], ['c', 'd']], null, 'A1', true);
check("no brackets -> no blocks", $probe->tFindBlockStartRows($bws3), []);

echo "\n==== SUMMARY: $pass passed, $fail failed ====\n";
exit($fail === 0 ? 0 : 1);
