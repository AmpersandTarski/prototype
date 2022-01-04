<?php

namespace Ampersand\API\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class LogPerformanceMiddleware
{
    protected LoggerInterface $logger;
    protected string $prefix;
    protected ?float $startTime;

    public function __construct(LoggerInterface $logger, string $prefix = '', ?float $startTime = null)
    {
        $this->logger = $logger;
        $this->prefix = $prefix;
        $this->startTime = $startTime;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next): ResponseInterface
    {
        $startTime = $this->startTime ?? (float) microtime(true);

        $response = $next($request, $response);

        // Report performance until here
        $executionTime = round(microtime(true) - $startTime, 2);
        $memoryUsage = round(memory_get_usage() / 1024 / 1024, 2); // Mb
        $this->logger->notice("{$this->prefix}Memory in use: {$memoryUsage} Mb");
        $this->logger->notice("{$this->prefix}Execution time: {$executionTime} sec");

        return $response;
    }

}
