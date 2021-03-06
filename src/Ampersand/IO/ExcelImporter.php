<?php

namespace Ampersand\IO;

use Exception;
use Ampersand\Core\Atom;
use Psr\Log\LoggerInterface;
use Ampersand\Interfacing\Ifc;
use Ampersand\Interfacing\ResourceList;
use Ampersand\AmpersandApp;
use Ampersand\Exception\AccessDeniedException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Row;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExcelImporter
{
    /**
     *
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * Reference to application instance
     *
     * @var \Ampersand\AmpersandApp
     */
    protected $ampersandApp;

    /**
     * Constructor
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(AmpersandApp $app, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->ampersandApp = $app;
    }
    
    /**
     * Parse excelsheet and import population
     *
     * @param string $filename
     * @return void
     */
    public function parseFile($filename)
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
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet
     * @param \Ampersand\Interfacing\Ifc $ifc
     * @return void
     *
     * Use interface name as worksheet name. Format for content is as follows:
     *          | Column A        | Column B        | Column C        | etc
     * Row 1    | <Concept>       | <ifc label x>   | <ifc label y>   | etc
     * Row 2    | <Atom a1>       | <tgtAtom b1>    | <tgtAtom c1>    | etc
     * Row 3    | <Atom a2>       | <tgtAtom b2>    | <tgtAtom c2>    | etc
     * etc
     */
    protected function parseWorksheetWithIfc(Worksheet $worksheet, Ifc $ifc)
    {
        // Source concept of interface MUST be SESSION
        if (!$ifc->getSrcConcept()->isSession()) {
            throw new Exception("Source concept of interface '{$ifc->getLabel()}' must be SESSION in order to be used as import interface", 400);
        }

        if (!$this->ampersandApp->isAccessibleIfc($ifc)) {
            throw new AccessDeniedException("You do not have access to import using interface '{$ifc->getLabel()}' as specified in sheet {$worksheet->getTitle()}");
        }

        // Determine $leftConcept from cell A1
        $leftConcept = $this->ampersandApp->getModel()->getConceptByLabel((string)$worksheet->getCell('A1'));
        if ($leftConcept !== $ifc->getTgtConcept()) {
            throw new Exception("Target concept of interface '{$ifc->getLabel()}' does not match concept specified in cell {$worksheet->getTitle()}!A1", 400);
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
                } catch (Exception $e) {
                    throw new Exception("Cannot process column {$columnLetter} '{$cellvalue}' in sheet {$worksheet->getTitle()}, because subinterface in undefined", 400, $e);
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
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet
     * @return void
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
    protected function parseWorksheet(Worksheet $worksheet)
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
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet
     * @param int $startRowNr
     * @param int|null $endRowNr
     * @return void
     */
    protected function parseBlock(Worksheet $worksheet, int $startRowNr, int $endRowNr = null)
    {
        $line1 = [];
        $line2 = [];
        $header = [];

        $i = 0; // row counter
        foreach ($worksheet->getRowIterator($startRowNr, $endRowNr) as $row) {
            $i++; // increment row counter

            // Header line 1 specifies relation names
            if ($i === 1) {
                foreach ($row->getCellIterator() as $cell) {
                    // No leading/trailing spaces allowed
                    $line1[$cell->getColumn()] = trim((string) $cell->getCalculatedValue());
                }
            // Header line 2 specifies concept names
            } elseif ($i === 2) {
                $leftConcept = $this->ampersandApp->getModel()->getConceptByLabel($worksheet->getCell('A'. $row->getRowIndex())->getCalculatedValue());

                foreach ($row->getCellIterator() as $cell) {
                    $col = $cell->getColumn();
                    $line2[$col] = trim((string) $cell->getCalculatedValue()); // no leading/trailing spaces allowed
                
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
                                            ];
                        } else {
                            $rightConcept = $this->ampersandApp->getModel()->getConceptByLabel($line2[$col]);
                            $header[$col] = ['concept' => $rightConcept
                                            ,'relation' => $this->ampersandApp->getRelation($line1[$col], $leftConcept, $rightConcept) // @phan-suppress-current-line PhanTypeInvalidDimOffset
                                            ,'flipped' => false
                                            ];
                        }
                    }
                }
            // Data lines
            } else {
                $col = 'A';
                $cellA = $this->getCalculatedValueAsAtomId($worksheet->getCell($col . $row->getRowIndex()));

                // If cell Ax is empty, skip complete row
                if ($cellA === '') {
                    $this->logger->notice("Skipping row {$row->getRowIndex()}, because column A is empty");
                    continue; // proceed to next row
                // If cell Ax contains '_NEW', this means to automatically create a new atom
                } elseif ($cellA === '_NEW') {
                    $leftAtom = $header[$col]['concept']->createNewAtom()->add(); // @phan-suppress-current-line PhanTypeInvalidDimOffset
                // Else instantiate atom with given atom identifier
                } else {
                    $leftAtom = (new Atom($cellA, $header[$col]['concept']))->add(); // @phan-suppress-current-line PhanTypeInvalidDimOffset
                }

                $this->processDataRow($leftAtom, $row, $header);
            }
        }
    }
    
    /**
     * Undocumented function
     *
     * @param \Ampersand\Core\Atom $leftAtom
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Row $row
     * @param array $headerInfo
     * @return void
     */
    protected function processDataRow(Atom $leftAtom, Row $row, array $headerInfo)
    {
        foreach ($row->getCellIterator('B') as $cell) {
            $col = $cell->getColumn();

            // Skip cell if column must not be imported
            if (!array_key_exists($col, $headerInfo)) {
                continue; // continue to next cell
            }

            $cellvalue = $this->getCalculatedValueAsAtomId($cell); // @phan-suppress-current-line PhanTypeMismatchArgumentNullable

            // If cell is empty, skip column
            if ($cellvalue === '') {
                continue; // continue to next cell
            } elseif ($cellvalue === '_NEW') {
                $rightAtom = $leftAtom;
            } else {
                $rightAtom = (new Atom($cellvalue, $headerInfo[$col]['concept']))->add();
            }

            $leftAtom->link($rightAtom, $headerInfo[$col]['relation'], $headerInfo[$col]['flipped'])->add();
        }
    }

    protected function getCalculatedValueAsAtomId(Cell $cell): string
    {
        $cellvalue = (string) $cell->getCalculatedValue(); // !Do NOT trim this cellvalue, because atoms may have leading/trailing whitespace

        // Overwrite $cellvalue in case of datetime
        // the @ is a php indicator for a unix timestamp (http://php.net/manual/en/datetime.formats.compound.php), later used for typeConversion
        if (Date::isDateTime($cell) && !empty($cellvalue)) {
            $cellvalue = '@'.(string)Date::excelToTimestamp((int)$cellvalue);
        }

        return $cellvalue;
    }
}
