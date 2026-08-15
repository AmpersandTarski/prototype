<?php

namespace Ampersand\Controller;

use Ampersand\Core\Population;
use Ampersand\Exception\AccessDeniedException;
use Ampersand\Exception\BadRequestException;
use Ampersand\Exception\UploadException;
use Ampersand\IO\ExcelImporter;
use Ampersand\IO\JsonPopulationImporter;
use Ampersand\IO\YamlPopulationImporter;
use Ampersand\Log\Logger;
use Slim\Http\Request;
use Slim\Http\Response;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class PopulationController extends AbstractController
{
    public function importPopulationFromUpload(Request $request, Response $response, array $args): Response
    {
        // Check for required role
        if (!$this->app->hasRole($this->app->getSettings()->get('rbac.importerRoles'))) {
            throw new AccessDeniedException("You do not have access to import population");
        }
        
        $fileInfo = $_FILES['file'];
        
        // Check if there is a file uploaded
        if (!is_uploaded_file($fileInfo['tmp_name'])) {
            throw new UploadException($fileInfo['error']);
        }

        $transaction = $this->app->newTransaction();

        // Determine the import method from the file CONTENT, not its extension. The extension
        // is advisory: a population is recognized by what it holds, so a correct file with a
        // wrong (.txt, .dat) or missing extension imports just the same (see issue #1673).
        switch ($this->detectImportFormat($fileInfo['tmp_name'])) {
            case 'json':
                // Streaming import: memory is bounded by the largest single block in the
                // file, not by the population size. Semantics (Atom/Link::add() within
                // this transaction, invariants evaluated at close) are unchanged.
                $importer = new JsonPopulationImporter($this->app->getModel(), Logger::getLogger('IO'));
                $importer->importFile($fileInfo['tmp_name']);
                break;
            case 'yaml':
                // YAML is transcoded to JSON and imported by the SAME JsonPopulationImporter,
                // so JSON and YAML uploads behave identically — neither format lets through
                // anything the other blocks.
                $importer = new YamlPopulationImporter($this->app->getModel(), Logger::getLogger('IO'));
                $importer->importFile($fileInfo['tmp_name']);
                break;
            case 'excel':
                $importer = new ExcelImporter($this->app, Logger::getLogger('IO'));
                $importer->parseFile($fileInfo['tmp_name']);
                break;
            default:
                throw new BadRequestException("Unrecognized file: expected a JSON, YAML or Excel population file");
        }

        // Commit transaction
        $transaction->runExecEngine()->close();
        if ($transaction->isCommitted()) {
            $this->app->userLog()->notice("Imported {$fileInfo['name']} successfully");
        }
        unlink($fileInfo['tmp_name']);
        
        // Check all process rules that are relevant for the activate roles
        $this->app->checkProcessRules();

        return $response->withJson(
            [ 'files'                 => $_FILES
            , 'notifications'         => $this->app->userLog()->getAll()
            , 'invariantRulesHold'    => $transaction->invariantRulesHold()
            , 'isCommitted'           => $transaction->isCommitted()
            , 'sessionRefreshAdvice'  => $this->frontend->getSessionRefreshAdvice()
            ],
            $transaction->isCommitted() ? 200 : 400, // 400 'Bad request' is used to trigger error in file uploader interface
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Recognize the population file format from its content, so the extension is advisory.
     *
     * Returns 'json', 'yaml' or 'excel', or throws when the file is empty. There is no
     * protocol validation here (see issue #1673): the format is picked from a few leading
     * bytes; a mismatched or malformed file surfaces later as a plain import error.
     *
     *  - Excel is binary: xlsx/ods are ZIP archives ("PK\x03\x04"), xls is an OLE compound
     *    document ("\xD0\xCF\x11\xE0"). A text population never starts with these bytes.
     *  - Otherwise the file is text. A JSON population starts with '{' (or '['); anything
     *    else is read as YAML. Because YAML 1.2 is a superset of JSON, both routes end at
     *    the same importer, so this split never accepts what the other format would reject.
     */
    protected function detectImportFormat(string $filePath): string
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new BadRequestException("Could not read the uploaded file");
        }
        $head = fread($handle, 4096);
        fclose($handle);

        if (str_starts_with($head, "PK\x03\x04") || str_starts_with($head, "\xD0\xCF\x11\xE0")) {
            return 'excel';
        }

        // Skip a UTF-8 BOM and leading whitespace to find the first meaningful character.
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $head);
        $text = ltrim($text);
        if ($text === '') {
            throw new BadRequestException("The uploaded file is empty");
        }

        return ($text[0] === '{' || $text[0] === '[') ? 'json' : 'yaml';
    }

    public function exportAllPopulation(Request $request, Response $response, array $args): Response
    {
        $this->preventProductionMode();
        $this->requireAdminRole();

        $model = $this->app->getModel();

        // Export population to response body
        $population = new Population($model, Logger::getLogger('IO'));
        $population->loadExistingPopulation($model->getAllConcepts(), $model->getRelations());
        $response->getBody()->write($population->export(new JsonEncoder(), 'json'));

        // Return response
        $filename = $this->app->getName() . "_population_" . date('Y-m-d\TH-i-s') . ".json";
        return $response->withHeader('Content-Disposition', "attachment; filename={$filename}")
                        ->withHeader('Content-Type', 'application/json;charset=utf-8');
    }

    public function exportSelectionOfPopulation(Request $request, Response $response, array $args): Response
    {
        $this->preventProductionMode();
        $this->requireAdminRole();
        
        // Process input
        $body = $request->reparseBody()->getParsedBody();
        $model = $this->app->getModel();
        
        $concepts = array_map(function (string $conceptName) use ($model) {
            return $model->getConcept($conceptName);
        }, $body->concepts);

        $relations = array_map(function (string $relSignature) use ($model) {
            return $model->getRelation($relSignature);
        }, $body->relations);

        // Export population to response body
        $population = new Population($model, Logger::getLogger('IO'));
        $population->loadExistingPopulation($concepts, $relations);
        $response->getBody()->write($population->export(new JsonEncoder(), 'json'));

        // Return response
        $filename = $this->app->getName() . "_population-subset_" . date('Y-m-d\TH-i-s') . ".json";
        return $response->withHeader('Content-Disposition', "attachment; filename={$filename}")
                        ->withHeader('Content-Type', 'application/json;charset=utf-8');
    }
}
