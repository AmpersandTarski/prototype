<?php

/**
 * Generate the .xlsx fixtures used by the import-multivalue end-to-end test (run.sh).
 * Uses the framework's bundled PhpSpreadsheet, so no extra tooling is required.
 *
 * Run:
 *     php test/projects/import-multivalue/e2e/make-fixtures.php
 *
 * Writes (next to this script):
 *   - mv-rel.xlsx : RELATION approach (target multi-value, source multi-value/cartesian, flipped + multi)
 *   - mv-ifc.xlsx : INTERFACE approach (multi-value sub-interface column)
 */

require __DIR__ . '/../../../../backend/lib/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$dir = __DIR__;

// ---- File 1: RELATION approach (sheet name must NOT match an interface) ----
$rel = new Spreadsheet();
$ws = $rel->getActiveSheet();
$ws->setTitle('Relations');
$ws->fromArray([
    // Block 1: target multi-value  skills[Person*Skill] with '[Skill,]'
    ['[Person]',    'skills'],
    ['Person',      '[Skill,]'],
    ['pete',        'cooking, diving , flying'], // 3 skills, spaces trimmed
    ['john',        'reading'],                  // single value
    ['mary',        ', ,'],                       // only delimiters -> no skills
    [null,          'this row is a comment'],    // empty col A -> ignored
    // Block 2: source multi-value (cartesian)  related[Skill*Skill], '[Skill,]' in col A
    ['[Skill]',     'related'],
    ['[Skill,]',    'Skill'],
    ['alpha, beta', 'gamma'],                    // -> (alpha,gamma),(beta,gamma)
    // Block 3: flipped relation + multi-value  enrolled[Project*Person], header 'enrolled~'
    ['[Person]',    'enrolled~'],
    ['Person',      '[Project,]'],
    ['pete',        'p1, p2'],                    // -> enrolled (p1,pete),(p2,pete)
], null, 'A1', true);
(new Xlsx($rel))->save($dir . '/mv-rel.xlsx');
echo "wrote {$dir}/mv-rel.xlsx\n";

// ---- File 2: INTERFACE approach (sheet name matches INTERFACE "PersonSkills") ----
$ifc = new Spreadsheet();
$wsi = $ifc->getActiveSheet();
$wsi->setTitle('PersonSkills');
$wsi->fromArray([
    ['Person', '[Skills,]'],         // concept + multi-value sub-interface column
    ['pete',   'singing, dancing'],  // existing person, +2 skills (multi-value split)
    ['john',   'coding , hiking ,'], // existing person, +2 skills (trailing delimiter ignored)
], null, 'A1', true);
(new Xlsx($ifc))->save($dir . '/mv-ifc.xlsx');
echo "wrote {$dir}/mv-ifc.xlsx\n";
