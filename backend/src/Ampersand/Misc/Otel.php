<?php

namespace Ampersand\Misc;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use Throwable;

/**
 * Thin wrapper around the OpenTelemetry API for manual instrumentation.
 *
 * When OTEL_SDK_DISABLED=true (the default in the Docker image) the tracer is a
 * no-op, so instrumented code paths are safe to call unconditionally.
 * See docs/guides/measuring-performance-with-opentelemetry.md
 */
class Otel
{
    protected static ?TracerInterface $tracer = null;

    public static function tracer(): TracerInterface
    {
        return self::$tracer ??= Globals::tracerProvider()->getTracer('ampersand-prototype-framework');
    }

    /**
     * Run $fn inside a span
     *
     * The span is the active span for the duration of $fn, so spans opened
     * deeper down (e.g. by mysqli auto-instrumentation) nest under it.
     * The span is passed to $fn, so $fn can add attributes to it.
     */
    public static function span(string $name, callable $fn, array $attributes = []): mixed
    {
        $span = self::tracer()->spanBuilder($name)->setAttributes($attributes)->startSpan();
        $scope = $span->activate();
        try {
            return $fn($span);
        } catch (Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
