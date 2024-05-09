<?php

namespace Ampersand\Controller;

use Ampersand\IO\RDFGraph;
use Ampersand\Misc\Reporter;
use Slim\Http\Request;
use Slim\Http\Response;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class ReportController extends AbstractController
{
    protected function guard(): void
    {
        $this->preventProductionMode();
        $this->requireAdminRole();
    }

    public function exportMetaModel(Request $request, Response $response, array $args): Response
    {
        $this->guard();

        // Content negotiation
        $acceptHeader = $request->getParam('format') ?? $request->getHeaderLine('Accept');
        $rdfFormat = RDFGraph::getResponseFormat($acceptHeader);

        $graph = new RDFGraph($this->app->getModel(), $this->app->getSettings());

        // Output
        $mimetype = $rdfFormat->getDefaultMimeType();
        switch ($mimetype) {
            case 'text/html':
                return $response->withHeader('Content-Type', 'text/html')->write($graph->dump('html'));
            case 'text/plain':
                return $response->withHeader('Content-Type', 'text/plain')->write($graph->dump('text'));
            default:
                $filename = $this->app->getName() . "_meta-model_" . date('Y-m-d\TH-i-s') . "." . $rdfFormat->getDefaultExtension();
                return $response
                    ->withHeader('Content-Type', $rdfFormat->getDefaultMimeType())
                    ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
                    ->write($graph->serialise($rdfFormat));
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