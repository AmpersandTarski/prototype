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
        $leftConcept = $this->ampersandApp->getModel()->getConceptByLabel((string)$worksheet->getCell('A1'));
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
                try {
                    $subIfcObj = $ifc->getIfcObject()->getSubinterfaceByLabel($cellvalue);
                    $dataColumns[$columnLetter] = $subIfcObj;
                } catch (NotDefinedException $e) {
                    throw new BadRequestException("Cannot process column {$columnLetter} '{$cellvalue}' in sheet {$worksheet->getTitle()}, because subinterface in undefined", previous: $e);
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
                throw new Exception("Error in cell '{$cell->getWorksheet()->getTitle()}!{$cell->getCoordinate()}': {$e->getMessage()}", $e->getCode(), $e);
            }
            
            // Process other columns of this row
            foreach ($dataColumns as $columnLetter => $subIfcObj) {
                /** @var \Ampersand\Interfacing\InterfaceObjectInterface $subIfcObj */
                $cell = $worksheet->getCell($columnLetter . $rowNr);
                
                try {
                    $cellvalue = (string)$cell->getCalculatedValue();
                    
                    if ($cellvalue === '') {
                        continue; // skip if not value provided
                    }
                    
                    // Overwrite $cellvalue in case of datetime
                    // The @ is a php indicator for a unix timestamp (http://php.net/manual/en/datetime.formats.compound.php), later used for typeConversion
                    if (Date::isDateTime($cell) && !empty($cellvalue)) {
                        $cellvalue = '@' . (string) Date::excelToTimestamp((int) $cellvalue);
                    }

                    $subIfcObj->add($leftResource, $cellvalue);
                } catch (Exception $e) {
                    throw new Exception("Error in cell '{$cell->getWorksheet()->getTitle()}!{$cell->getCoordinate()}': {$e->getMessage()}", $e->getCode(), $e);
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
        // Find import blocks
        $blockStartRowNrs = [];
        foreach ($worksheet->getRowIterator() as $row) {
            $rowNr = $row->getRowIndex();
            $cellvalue = $worksheet->getCell('A'. $rowNr)->getCalculatedValue();

            // Import block is indicated by '[]' brackets in cell Ax
            if (substr(trim($cellvalue), 0, 1) === '[') {
                $blockStartRowNrs[] = $rowNr;
            }
        }

        // Process import blocks
        foreach ($blockStartRowNrs as $key => $startRowNr) {
            $endRowNr = isset($blockStartRowNrs[$key + 1]) ? ($blockStartRowNrs[$key + 1] - 1) : null;
            $this->parseBlock($worksheet, $startRowNr, $endRowNr);
        }
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
                $leftConcept = $this->ampersandApp->getModel()->getConceptByLabel($worksheet->getCell('A'. $row->getRowIndex())->getCalculatedValue());

                foreach ($row->getCellIterator() as $cell) {
                    try {
                        $col = $cell->getColumn();
                        $line2[$col] = trim((string) $cell->getCalculatedValue()); // no leading/trailing spaces allowed

                        // Handle possibility that column contains multiple values to insert into the relation seperated by a delimiter
                        // The syntax to indicate this is: '[Concept,]' where in this example the comma ',' is the delimiter
                        // The square brackets are needed to indicate that this is a multi value column
                        $delimiter = null;
                        if (substr(
                            $line2[$col], 0, 1) === '['
                            && substr($line2[$col], -1) === ']'
                        ) {
                            $delimiter = substr($line2[$col], -2, 1);
                            $line2[$col] = substr($line2[$col], 1, strlen($line2[$col]) - 3);
                        }
                    
                        // Import header can be determined now using line 1 and line 2
                        if ($col === 'A') {
                            $header[$col] = ['concept' => $leftConcept, 'relation' => null, 'flipped' => null];
                        } else {
                            if ($line1[$col] === '' || $line2[$col] === '') { // @phan-suppress-current-line PhanTypeInvalidDimOffset
                                // Skipping column
                                $this->logger->notice("Skipping column {$col} in sheet {$worksheet->getTitle()}, because header is not complete");
                            // Relation is flipped when last character is a tilde (~)
                            } elseif (substr($line1[$col], -1) === '~') { // @phan-suppress-current-line PhanTypeInvalidDimOffset
                                $rightConcept = $this->ampersandApp->getModel()->getConceptByLabel($line2[$col]);
                                
                                $header[$col] = ['concept' => $rightConcept
                                                ,'relation' => $this->ampersandApp->getRelation(substr($line1[$col], 0, -1), $rightConcept, $leftConcept) // @phan-suppress-current-line PhanTypeInvalidDimOffset
                                                ,'flipped' => true
                                                ,'delimiter' => $delimiter
                                                ];
                            } else {
                                $rightConcept = $this->ampersandApp->getModel()->getConceptByLabel($line2[$col]);
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

                    // If cell Ax is empty, skip complete row
                    if ($srcAtomId === '') {
                        $this->logger->notice("Skipping row {$row->getRowIndex()}, because column A is empty");
                        continue; // proceed to next row
                    // If cell Ax contains '_NEW', this means to automatically create a new atom
                    } elseif ($srcAtomId === '_NEW') {
                        $leftAtom = $srcConcept->createNewAtom()->add();
                    // Else instantiate atom with given atom identifier
                    } else {
                        $leftAtom = (new Atom($srcAtomId, $srcConcept))->add();
                    }
                } catch (Exception $e) {
                    $this->throwException($e, $cellA);
                }

                $this->processDataRow($leftAtom, $row, $header);
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
                    // Handle case with delimited multi values
                    } else {
                        foreach (explode($headerInfo[$col]['delimiter'], $cellvalue) as $value) {
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
