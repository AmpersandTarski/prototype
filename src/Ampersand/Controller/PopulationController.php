<?php

namespace Ampersand\Controller;

use Ampersand\Core\Population;
use Ampersand\Exception\AccessDeniedException;
use Ampersand\Exception\BadRequestException;
use Ampersand\Exception\UploadException;
use Ampersand\IO\ExcelImporter;
use Ampersand\Log\Logger;
use Slim\Http\Request;
use Slim\Http\Response;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class PopulationController extends AbstractController
{
    public function importPopulationFromUpload(Request $request, Response $response, array $args): Response
    {
        // Check for required role
        if (!$this->app->hasRole($this->app->getSettings()->get('rbac.importerRoles'))) {
            throw new AccessDeniedException("You do not have access to import population", 403);
        }
        
        $fileInfo = $_FILES['file'];
        
        // Check if there is a file uploaded
        if (!is_uploaded_file($fileInfo['tmp_name'])) {
            throw new UploadException($fileInfo['error']);
        }

        $transaction = $this->app->newTransaction();

        // Determine and execute import method based on extension.
        $extension = pathinfo($fileInfo['name'], PATHINFO_EXTENSION);
        switch ($extension) {
            case 'json':
                $decoder = new JsonDecode(false);
                $populationContent = $decoder->decode(file_get_contents($fileInfo['tmp_name']), JsonEncoder::FORMAT);
                $population = new Population($this->app->getModel(), Logger::getLogger('IO'));
                $population->loadFromPopulationFile($populationContent);
                $population->import();
                break;
            case 'xls':
            case 'xlsx':
            case 'ods':
                $importer = new ExcelImporter($this->app, Logger::getLogger('IO'));
                $importer->parseFile($fileInfo['tmp_name']);
                break;
            default:
                throw new BadRequestException("Unsupported file extension");
                break;
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
            , 'sessionRefreshAdvice'  => $this->angularApp->getSessionRefreshAdvice()
            ],
            $transaction->isCommitted() ? 200 : 400, // 400 'Bad request' is used to trigger error in file uploader interface
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
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
        
        $concepts = array_map(function (string $conceptLabel) use ($model) {
            return $model->getConceptByLabel($conceptLabel);
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
