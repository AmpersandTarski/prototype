<?php

namespace Ampersand\API\Middleware;

use Ampersand\Misc\Otel;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Route;
use Throwable;

/**
 * Opens one OpenTelemetry server span per API request.
 *
 * OpenTelemetry's zero-code instrumentation has no hook for Slim 3, so the
 * framework opens the root span itself. All spans opened further down
 * (mysqli/curl/IO auto-instrumentation and Otel::span) nest under this span,
 * which yields exactly one trace per API request.
 * See docs/guides/measuring-performance-with-opentelemetry.md
 */
class OtelRequestSpanMiddleware
{
    /**
     * When given, the span starts at the script start time, so the trace also
     * shows the bootstrap time spent before this middleware runs
     */
    protected ?float $scriptStartTime;

    public function __construct(?float $scriptStartTime = null)
    {
        $this->scriptStartTime = $scriptStartTime;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next): ResponseInterface
    {
        // Continue the trace of the caller (e.g. a reverse proxy or test driver)
        // when W3C trace context headers are present
        $carrier = [];
        foreach (['traceparent', 'tracestate', 'baggage'] as $header) {
            if ($request->hasHeader($header)) {
                $carrier[$header] = $request->getHeaderLine($header);
            }
        }
        $parentContext = Globals::propagator()->extract($carrier);

        // The route attribute is set by Slim before the middleware runs (see
        // setting determineRouteBeforeAppMiddleware in bootstrap/framework.php).
        // Use the route pattern (low cardinality), not the concrete path.
        $route = $request->getAttribute('route');
        $routePattern = $route instanceof Route ? $route->getPattern() : $request->getUri()->getPath();

        $builder = Otel::tracer()
            ->spanBuilder("{$request->getMethod()} {$routePattern}")
            ->setParent($parentContext)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('http.request.method', $request->getMethod())
            ->setAttribute('http.route', $routePattern)
            ->setAttribute('url.path', $request->getUri()->getPath());
        if (isset($this->scriptStartTime)) {
            $builder->setStartTimestamp((int) ($this->scriptStartTime * 1_000_000_000));
        }
        $span = $builder->startSpan();
        $scope = $span->activate();

        try {
            $response = $next($request, $response);
            $span->setAttribute('http.response.status_code', $response->getStatusCode());
            if ($response->getStatusCode() >= 500) {
                $span->setStatus(StatusCode::STATUS_ERROR);
            }
            return $response;
        } catch (Throwable $e) {
            // Slim turns the exception into an error response outside this
            // middleware, so record it here and rethrow
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
