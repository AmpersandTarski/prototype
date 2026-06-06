<?php

namespace Ampersand\IO;

use Exception;
use Ampersand\Core\Atom;
use Psr\Log\LoggerInterface;
use Ampersand\Interfacing\Ifc;
use Ampersand\Interfacing\ResourceList;
use Ampersand\AmpersandApp;
use Ampersand\Exception\AccessDeniedException;
use Ampersand\Exception\BadRequestException;
use Ampersand\Exception\NotDefined\NotDefinedException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Row;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExcelImporter
{
    /**
     * Logger
     */
    private LoggerInterface $logger;

    /**
     * Reference to application instance
     */
    protected AmpersandApp $ampersandApp;

    /**
     * Constructor
     */
    public function __construct(AmpersandApp $app, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->ampersandApp = $app;
    }
    
    /**
     * Parse excelsheet and import population
     */
    public function parseFile(string $filename): void
    {
        $file = IOFactory::load($filename);

        $this->logger->info("Excel import started: parsing file {$filename}");
        
        // Loop over all worksheets
        foreach ($file->getWorksheetIterator() as $worksheet) {
            try {
                // First check if there is an interface with the same id as the worksheet
                $ifc = $this->ampersandApp->getModel()->getInterfaceByLabel($worksheet->getTitle());
            } catch (Exception $e) {
                $this->logger->notice("No interface found with name as title of worksheet '{$worksheet->getTitle()}'. Parsing file without interface");
                $this->parseWorksheet($worksheet); // Older two-header-row format
                continue;
            }
            $this->parseWorksheetWithIfc($worksheet, $ifc);
        }
        
        $this->logger->info("Excel import completed");
    }
    
    /**
     * Parse worksheet according to an Ampersand interface definition.
     *
     * Use interface name as worksheet name. Format for content is as follows:
     *          | Column A        | Column B        | Column C        | etc
     * Row 1    | <Concept>       | <ifc label x>   | <ifc label y>   | etc
     * Row 2    | <Atom a1>       | <tgtAtom b1>    | <tgtAtom c1>    | etc
     * Row 3    | <Atom a2>       | <tgtAtom b2>    | <tgtAtom c2>    | etc
     * etc
     *
     * A column header may use the '[label<delim>]' syntax (e.g. '[Role,]') to indicate that
     * its cells contain multiple values separated by the given delimiter.
     */
    protected function parseWorksheetWithIfc(Worksheet $worksheet, Ifc $ifc): void
    {
        // Source concept of interface MUST be SESSION
        if (!$ifc->getSrcConcept()->isSession()) {
            throw new BadRequestException("Source concept of interface '{$ifc->getLabel()}' must be SESSION in order to be used as import interface");
        }

        if (!$this->ampersandApp->isAccessibleIfc($ifc)) {
            throw new AccessDeniedException("You do not have access to import using interface '{$ifc->getLabel()}' as specified in sheet {$worksheet->getTitle()}");
        }

        // Determine $leftConcept from cell A1
        $cellA1 = $worksheet->getCell('A1');
        try {
            $leftConcept = $this->ampersandApp->getModel()->getConcept((string) $cellA1);
        } catch (Exception $e) {
            $this->throwException($e, $cellA1);
        }

        if ($leftConcept !== $ifc->getTgtConcept()) {
            throw new BadRequestException("Target concept of interface '{$ifc->getLabel()}' does not match concept specified in cell {$worksheet->getTitle()}!A1");
        }

        // The list to add/update items from
        $resourceList = ResourceList::makeFromInterface($this->ampersandApp->getSession()->getId(), $ifc->getId());
        
        // Parse other columns of first row
        $dataColumns = [];
        foreach ($worksheet->getColumnIterator('B') as $column) {
            $columnLetter = $column->getColumnIndex();
            $cellvalue = (string)$worksheet->getCell($columnLetter . '1')->getCalculatedValue();
            
            if ($cellvalue !== '') {
                // A column may hold multiple values per cell, using the '[label<delim>]' syntax
                // (e.g. '[Role,]'), just like the multi-value columns of the relation-based importer.
                [$label, $delimiter] = $this->parseHeaderWithOptionalDelimiter($cellvalue);
                try {
                    $subIfcObj = $ifc->getIfcObject()->getSubinterfaceByLabel($label);
                    $dataColumns[$columnLetter] = ['subifc' => $subIfcObj, 'delimiter' => $delimiter];
                } catch (NotDefinedException $e) {
                    throw new BadRequestException("Cannot process column {$columnLetter} '{$label}' in sheet {$worksheet->getTitle()}, because subinterface in undefined", previous: $e);
                }
            } else {
                $this->logger->notice("Skipping column {$columnLetter} in sheet {$worksheet->getTitle()}, because header is not provided");
            }
        }
        
        // Parse other rows
        foreach ($worksheet->getRowIterator(2) as $row) {
            $rowNr = $row->getRowIndex();
            $cell = $worksheet->getCell('A'.$rowNr);

            try {
                $firstCol = (string)$cell->getCalculatedValue();

                // If cell Ax is empty, skip complete row
                if ($firstCol === '') {
                    $this->logger->notice("Skipping row {$rowNr} in sheet {$worksheet->getTitle()}, because column A is empty");
                    continue;
                // If cell Ax contains '_NEW', this means to automatically create a new atom
                } elseif ($firstCol === '_NEW') {
                    $leftResource = $resourceList->post();
                // Else instantiate atom with given atom identifier
                } else {
                    $leftAtom = new Atom($firstCol, $leftConcept);
                    if ($leftAtom->exists()) {
                        $leftResource = $resourceList->one($firstCol);
                    } else { // Try a POST
                        $leftResource = $resourceList->create($leftAtom->getId());
                    }
                }
            } catch (Exception $e) {
                $this->throwException($e, $cell);
            }
            
            // Process other columns of this row
            foreach ($dataColumns as $columnLetter => $colInfo) {
                /** @var \Ampersand\Interfacing\InterfaceObjectInterface $subIfcObj */
                $subIfcObj = $colInfo['subifc'];
                $cell = $worksheet->getCell($columnLetter . $rowNr);

                try {
                    // A cell yields one value, or multiple values when the column declared a delimiter
                    foreach ($this->splitCellValue($cell, $colInfo['delimiter']) as $cellvalue) {
                        $subIfcObj->add($leftResource, $cellvalue);
                    }
                } catch (Exception $e) {
                    $this->throwException($e, $cell);
                }
            }
        }
    }
    
    /**
     * Parse worksheet according to the 2-row header information.
     * Row 1 contains the relation names, Row 2 the corresponding concept names
     * Multiple block of imports can be specified on a single sheet.
     * The parser looks for the brackets '[ ]' to start a new block
     *
     * Format of sheet:
     *           | Column A        | Column B        | Column C        | etc
     * Row 1     | [ block label ] | <relation name> | <relation name> | etc
     * Row 2     | <srcConcept>    | <tgtConcept1>   | <tgtConcept2>   | etc
     * Row 3     | <srcAtom a1>    | <tgtAtom b1>    | <tgtAtomN c1>   | etc
     * Row 4     | <srcAtom a2>    | <tgtAtom b2>    | <tgtAtomN c2>   | etc
     * etc
     *
     */
    protected function parseWorksheet(Worksheet $worksheet): void
    {
        // Find and process import blocks
        $blockStartRowNrs = $this->findBlockStartRows($worksheet);
        foreach ($blockStartRowNrs as $key => $startRowNr) {
            $endRowNr = isset($blockStartRowNrs[$key + 1]) ? ($blockStartRowNrs[$key + 1] - 1) : null;
            $this->parseBlock($worksheet, $startRowNr, $endRowNr);
        }
    }

    /**
     * Determine the row numbers at which import blocks start.
     *
     * A block is indicated by brackets '[ ]' in cell Ax, but only when the cell directly above
     * it is NOT bracketed. This lets the source column of a block use the multi-value syntax
     * '[Concept,]' (on the concept row, directly below the block starter) without being mistaken
     * for the start of a new block. Mirrors the Haskell importer's isStartOfTable.
     *
     * @return int[] block start row numbers, in ascending order
     */
    protected function findBlockStartRows(Worksheet $worksheet): array
    {
        $blockStartRowNrs = [];
        foreach ($worksheet->getRowIterator() as $row) {
            $rowNr = $row->getRowIndex();

            if (!$this->isBracketed((string) $worksheet->getCell('A'. $rowNr)->getCalculatedValue())) {
                continue;
            }
            $aboveBracketed = $rowNr > 1
                && $this->isBracketed((string) $worksheet->getCell('A'. ($rowNr - 1))->getCalculatedValue());
            if (!$aboveBracketed) {
                $blockStartRowNrs[] = $rowNr;
            }
        }
        return $blockStartRowNrs;
    }

    /**
     * Undocumented function
     */
    protected function parseBlock(Worksheet $worksheet, int $startRowNr, ?int $endRowNr = null): void
    {
        $line1 = [];
        $line2 = [];
        $header = [];

        $i = 0; // row counter
        foreach ($worksheet->getRowIterator($startRowNr, $endRowNr) as $row) { // @phan-suppress-current-line PhanTypeMismatchArgumentNullable
            $i++; // increment row counter

            // Header line 1 specifies relation names
            if ($i === 1) {
                foreach ($row->getCellIterator() as $cell) {
                    try {
                        // No leading/trailing spaces allowed
                        $line1[$cell->getColumn()] = trim((string) $cell->getCalculatedValue());
                    } catch (Exception $e) {
                        $this->throwException($e, $cell);
                    }
                }
            // Header line 2 specifies concept names
            } elseif ($i === 2) {
                $cellA2i = $worksheet->getCell('A'. $row->getRowIndex()); // 2nd row of block, not necessary row 2 in sheet

                // The source column may itself declare a delimiter (multi-value source), just like target columns
                [$srcConceptName, $srcDelimiter] = $this->parseHeaderWithOptionalDelimiter((string) $cellA2i->getCalculatedValue());
                try {
                    $leftConcept = $this->ampersandApp->getModel()->getConcept($srcConceptName);
                } catch (Exception $e) {
                    $this->throwException($e, $cellA2i);
                }

                foreach ($row->getCellIterator() as $cell) {
                    try {
                        $col = $cell->getColumn();

                        // A column may contain multiple values to insert into the relation, separated by a delimiter.
                        // Syntax in this (concept) row: '[Concept,]' where the character before ']' (here ',') is the delimiter.
                        [$line2[$col], $delimiter] = $this->parseHeaderWithOptionalDelimiter((string) $cell->getCalculatedValue());

                        // Import header can be determined now using line 1 and line 2
                        if ($col === 'A') {
                            $header[$col] = ['concept' => $leftConcept, 'relation' => null, 'flipped' => null, 'delimiter' => $srcDelimiter];
                        } else {
                            if ($line1[$col] === '' || $line2[$col] === '') { // @phan-suppress-current-line PhanTypeInvalidDimOffset
                                // Skipping column
                                $this->logger->notice("Skipping column {$col} in sheet {$worksheet->getTitle()}, because header is not complete");
                            // Relation is flipped when last character is a tilde (~)
                            } elseif (substr($line1[$col], -1) === '~') { // @phan-suppress-current-line PhanTypeInvalidDimOffset
                                $rightConcept = $this->ampersandApp->getModel()->getConcept($line2[$col]);
                                
                                $header[$col] = ['concept' => $rightConcept
                                                ,'relation' => $this->ampersandApp->getRelation(substr($line1[$col], 0, -1), $rightConcept, $leftConcept) // @phan-suppress-current-line PhanTypeInvalidDimOffset
                                                ,'flipped' => true
                                                ,'delimiter' => $delimiter
                                                ];
                            } else {
                                $rightConcept = $this->ampersandApp->getModel()->getConcept($line2[$col]);
                                $header[$col] = ['concept' => $rightConcept
                                                ,'relation' => $this->ampersandApp->getRelation($line1[$col], $leftConcept, $rightConcept) // @phan-suppress-current-line PhanTypeInvalidDimOffset
                                                ,'flipped' => false
                                                ,'delimiter' => $delimiter
                                                ];
                            }
                        }
                    } catch (Exception $e) {
                        $this->throwException($e, $cell);
                    }
                }
            // Data lines
            } else {
                $cellA = $worksheet->getCell('A' . $row->getRowIndex());

                try {
                    $srcAtomId = $this->getCalculatedValueAsAtomId($cellA);
                    /** @var \Ampersand\Core\Concept $srcConcept */
                    $srcConcept = $header['A']['concept']; // @phan-suppress-current-line PhanTypeInvalidDimOffset

                    // Determine the source atom(s) for this row. A source column with a delimiter may
                    // yield multiple atoms; each is paired with every target value (cartesian product).
                    $leftAtoms = [];
                    // If cell Ax is empty, skip complete row
                    if ($srcAtomId === '') {
                        $this->logger->notice("Skipping row {$row->getRowIndex()}, because column A is empty");
                        continue; // proceed to next row
                    // If cell Ax contains '_NEW', this means to automatically create a new atom
                    } elseif ($srcAtomId === '_NEW') {
                        $leftAtoms[] = $srcConcept->createNewAtom()->add();
                    // A delimiter on the source column splits the cell into multiple source atoms
                    } elseif (!is_null($header['A']['delimiter'])) { // @phan-suppress-current-line PhanTypeInvalidDimOffset
                        foreach ($this->splitDelimited($srcAtomId, $header['A']['delimiter']) as $id) { // @phan-suppress-current-line PhanTypeInvalidDimOffset
                            $leftAtoms[] = (new Atom($id, $srcConcept))->add();
                        }
                    // Else instantiate atom with given atom identifier
                    } else {
                        $leftAtoms[] = (new Atom($srcAtomId, $srcConcept))->add();
                    }
                } catch (Exception $e) {
                    $this->throwException($e, $cellA);
                }

                foreach ($leftAtoms as $leftAtom) {
                    $this->processDataRow($leftAtom, $row, $header);
                }
            }
        }
    }
    
    /**
     * Undocumented function
     */
    protected function processDataRow(Atom $leftAtom, Row $row, array $headerInfo): void
    {
        foreach ($row->getCellIterator('B') as $cell) {
            try {
                $col = $cell->getColumn();
                $rightAtoms = [];

                // Skip cell if column must not be imported
                if (!array_key_exists($col, $headerInfo)) {
                    continue; // continue to next cell
                }

                $cellvalue = $this->getCalculatedValueAsAtomId($cell); // @phan-suppress-current-line PhanTypeMismatchArgumentNullable

                // If cell is empty, skip column
                if ($cellvalue === '') {
                    continue; // continue to next cell
                } elseif ($cellvalue === '_NEW') {
                    $rightAtoms[] = $leftAtom;
                } else {
                    if (is_null($headerInfo[$col]['delimiter'])) {
                        $rightAtoms[] = new Atom($cellvalue, $headerInfo[$col]['concept']);
                    // Handle case with delimited multi values: split, trim each value and drop empty ones
                    } else {
                        foreach ($this->splitDelimited($cellvalue, $headerInfo[$col]['delimiter']) as $value) {
                            $rightAtoms[] = new Atom($value, $headerInfo[$col]['concept']);
                        }
                    }
                }

                foreach ($rightAtoms as $rightAtom) {
                    $rightAtom->add();
                    $leftAtom->link($rightAtom, $headerInfo[$col]['relation'], $headerInfo[$col]['flipped'])->add();
                }
            } catch (Exception $e) {
                $this->throwException($e, $cell);
            }
        }
    }

    protected function getCalculatedValueAsAtomId(Cell $cell): string
    {
        $cellvalue = (string) $cell->getCalculatedValue(); // !Do NOT trim this cellvalue, because atoms may have leading/trailing whitespace

        // Overwrite $cellvalue in case of datetime
        // the @ is a php indicator for a unix timestamp (http://php.net/manual/en/datetime.formats.compound.php), later used for typeConversion
        if (Date::isDateTime($cell) && $cellvalue !== '') {
            $cellvalue = '@'.(string)Date::excelToTimestamp((int)$cellvalue);
        }

        return $cellvalue;
    }

    /**
     * Whether a (trimmed) cell value is wrapped in square brackets, e.g. '[Person]' or '[Skill,]'.
     * Mirrors the Haskell importer's isBracketed.
     */
    protected function isBracketed(string $value): bool
    {
        $value = trim($value);
        return strlen($value) >= 2 && $value[0] === '[' && substr($value, -1) === ']';
    }

    /**
     * Parse a header cell that may declare a multi-value column.
     *
     * Syntax: '[Name<delim>]' where <delim> is the single delimiter character directly
     * before the closing bracket (e.g. '[EPPOcode,]' yields name 'EPPOcode', delimiter ',').
     * Without brackets the trimmed cell value is the name and there is no delimiter.
     * Mirrors the Haskell importer's conceptNameWithOptionalDelimiter.
     *
     * @return array{0: string, 1: ?string} [name, delimiter]
     */
    protected function parseHeaderWithOptionalDelimiter(string $cellValue): array
    {
        $value = trim($cellValue);

        if (strlen($value) >= 4 && $value[0] === '[' && substr($value, -1) === ']') {
            $delimiter = substr($value, -2, 1);
            $name = trim(substr($value, 1, strlen($value) - 3));
            return [$name, $delimiter];
        }

        return [$value, null];
    }

    /**
     * Split a delimited cell value into individual atom ids.
     *
     * Each value is trimmed and empty values are dropped, mirroring the Haskell
     * importer's unDelimit combined with its 'filter (not . null)'.
     *
     * @return array<string>
     */
    protected function splitDelimited(string $value, string $delimiter): array
    {
        $result = [];
        foreach (explode($delimiter, $value) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $result[] = $part;
            }
        }
        return $result;
    }

    /**
     * Read a cell as one or more atom values.
     *
     * Without a delimiter this yields a single value (a datetime cell is converted to a
     * unix timestamp '@...', as elsewhere in this importer). With a delimiter the text is
     * split into multiple values; a datetime cell is numeric and is never split.
     *
     * @return array<string>
     */
    protected function splitCellValue(Cell $cell, ?string $delimiter): array
    {
        $cellvalue = (string) $cell->getCalculatedValue();

        // Datetime cells are numeric and never multi-valued; convert and return as a single value.
        // The @ is a php indicator for a unix timestamp, later used for typeConversion.
        if (Date::isDateTime($cell) && $cellvalue !== '') {
            return ['@' . (string) Date::excelToTimestamp((int) $cellvalue)];
        }

        if (is_null($delimiter)) {
            // Do NOT trim a single value: atoms may have significant leading/trailing whitespace
            return $cellvalue === '' ? [] : [$cellvalue];
        }

        return $this->splitDelimited($cellvalue, $delimiter);
    }

    protected function throwException(Exception $e, ?Cell $cell): void
    {
        if (is_null($cell)) {
            throw new BadRequestException(
                message: "Error while importing excelsheet without cell location: {$e->getMessage()}",
                previous: $e
            );
        }

        throw new BadRequestException(
            message: "Error in cell '{$cell->getWorksheet()->getTitle()}!{$cell->getCoordinate()}': {$e->getMessage()}",
            previous: $e
        );
    }
}
