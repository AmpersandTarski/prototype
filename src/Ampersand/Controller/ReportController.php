<?php

namespace Ampersand\Controller;

use Ampersand\Exception\AccessDeniedException;
use Ampersand\Misc\Reporter;
use Slim\Http\Request;
use Slim\Http\Response;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class ReportController extends AbstractController
{
    protected function guard(): void
    {
        if ($this->app->getSettings()->get('global.productionEnv')) {
            throw new AccessDeniedException("Reports are not allowed in production environment", 403);
        }
    }

    public function reportRelations(Request $request, Response $response, array $args): Response
    {
        $this->guard();

        // Get report
        $reporter = new Reporter(new JsonEncoder(), $response->getBody());
        $reporter->reportRelationDefinitions($this->app->getModel()->getRelations(), 'json');

        // Return reponse
        return $response->withHeader('Content-Type', 'application/json;charset=utf-8');
    }

    public function conjunctUsage(Request $request, Response $response, array $args): Response
    {
        $this->guard();

        // Get report
        $reporter = new Reporter(new JsonEncoder(), $response->getBody());
        $reporter->reportConjunctUsage($this->app->getModel()->getAllConjuncts(), 'json');

        // Return reponse
        return $response->withHeader('Content-Type', 'application/json;charset=utf-8');
    }

    public function conjunctPerformance(Request $request, Response $response, array $args): Response
    {
        $this->guard();

        // Get report
        $reporter = new Reporter(new CsvEncoder(';', '"'), $response->getBody());
        $reporter->reportConjunctPerformance($this->app->getModel()->getAllConjuncts(), 'csv');
        
        // Set response headers
        $filename = $this->app->getName() . "_conjunct-performance_" . date('Y-m-d\TH-i-s') . ".csv";
        return $response->withHeader('Content-Disposition', "attachment; filename={$filename}")
                        ->withHeader('Content-Type', 'text/csv; charset=utf-8');
    }

    public function interfaces(Request $request, Response $response, array $args): Response
    {
        $this->guard();

        // Input
        $details = $request->getQueryParam('details', false);

        // Get report
        $reporter = new Reporter(new CsvEncoder(';', '"'), $response->getBody());
        if ($details) {
            $reporter->reportInterfaceObjectDefinitions($this->app->getModel()->getAllInterfaces(), 'csv');
        } else {
            $reporter->reportInterfaceDefinitions($this->app->getModel()->getAllInterfaces(), 'csv');
        }

        // Set response headers
        $filename = $this->app->getName() . "_interface-definitions_" . date('Y-m-d\TH-i-s') . ".csv";
        return $response->withHeader('Content-Disposition', "attachment; filename={$filename}")
                        ->withHeader('Content-Type', 'text/csv; charset=utf-8');
    }

    public function interfaceIssues(Request $request, Response $response, array $args): Response
    {
        $this->guard();
        
        // Get report
        $reporter = new Reporter(new CsvEncoder(';', '"'), $response->getBody());
        $reporter->reportInterfaceIssues($this->app->getModel()->getAllInterfaces(), 'csv');

        // Set response headers
        $filename = $this->app->getName() . "_interface-issues_" . date('Y-m-d\TH-i-s') . ".csv";
        return $response->withHeader('Content-Disposition', "attachment; filename={$filename}")
                        ->withHeader('Content-Type', 'text/csv; charset=utf-8');
    }
}