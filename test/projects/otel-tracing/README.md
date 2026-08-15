# otel-tracing

**Guards:** the OpenTelemetry instrumentation of the backend (issue #440): with the SDK
enabled, one API request yields exactly one trace, carrying the manually opened root span
(Slim 3 has no auto-instrumentation), the `app init`/`session init` phase spans and nested
`mysqli` query spans; `GET /admin/ruleengine/evaluate/all` additionally yields `conjunct <id>`
spans. Also guards the default: with `OTEL_SDK_DISABLED=true` nothing is exported.

The spec does not restart the stack's Apache. It starts a PHP built-in server inside the
prototype container with OTel enabled and the `console` exporter, fires requests at it, and
asserts on the span JSON that appears on the server's stdout. This keeps the check
deterministic and machine-independent: it asserts the *structure* of the telemetry (span
names, nesting, one trace id per request), never timing values.

See docs/guides/measuring-performance-with-opentelemetry.md for the feature itself.
