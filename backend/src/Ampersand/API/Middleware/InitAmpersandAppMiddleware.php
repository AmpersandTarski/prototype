<?php

namespace Ampersand\API\Middleware;

use Ampersand\AmpersandApp;
use Ampersand\Exception\NotInstalledException;
use Ampersand\Exception\SessionExpiredException;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class InitAmpersandAppMiddleware
{
    protected AmpersandApp $app;
    protected LoggerInterface $logger;

    public function __construct(AmpersandApp $app, LoggerInterface $logger)
    {
        $this->app = $app;
        $this->logger = $logger;
    }
    
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next): ResponseInterface
    {
        $sessionIsResetFlag = false;
    
        try {
            $this->app->init(); // initialize Ampersand application

            $this->app->setSession(); // initialize session
        
        } catch (NotInstalledException $e) {
            // Make sure to close any open transaction
            $this->app->getCurrentTransaction()->cancel();
            
            /** @var \Slim\Route $route */
            $route = $request->getAttribute('route');
            
            // If application installer API ROUTE is called, continue
            if (in_array($route->getName(), ['applicationInstaller', 'updateChecksum'], true)) {
                return $next($request, $response);
            } else {
                throw $e;
            }
        } catch (SessionExpiredException $e) {
            // Automatically reset session and continue the application
            // This is more user-friendly then directly throwing a "Your session has expired" error to the user
            $this->app->resetSession();
            $sessionIsResetFlag = true; // raise flag, which is used below
        }

        try {
            return $next($request, $response);
        } catch (Exception $e) {
            // If an exception is thrown in the application after the session is automatically reset, it is probably caused by this session reset (e.g. logout)
            if ($sessionIsResetFlag) {
                throw new SessionExpiredException("Your session has expired", previous: $e);
            } else {
                throw $e;
            }
        }
    }
}
