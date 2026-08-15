# Measuring performance with OpenTelemetry

The backend of every generated prototype is instrumented with [OpenTelemetry](https://opentelemetry.io/) (OTel). It is **disabled by default** and is switched on per deployment with environment variables, so it works for any prototype built on the base image without changing the application.

With tracing enabled, every API request produces one trace that shows where the time goes:

| Span | Source | Attributes |
| --- | --- | --- |
| `GET /resource/{resourceType}/{resourceId}[/{ifcPath:.*}]` | root span, one per API request ([OtelRequestSpanMiddleware](../../backend/src/Ampersand/API/Middleware/OtelRequestSpanMiddleware.php)) | `http.request.method`, `http.route`, `url.path`, `http.response.status_code` |
| `app init` | initialization of the Ampersand application (PHASE-2) | |
| `session init` | session creation/resume (PHASE-3, includes session conjuncts) | |
| `conjunct <id>` | one per evaluated conjunct ([Conjunct::evaluate](../../backend/src/Ampersand/Rule/Conjunct.php)) | `ampersand.conjunct`, `ampersand.violations` |
| `execengine run` | full ExecEngine loop of a transaction | |
| `transaction close` | conjunct re-evaluation, invariant check, commit/rollback | |
| `mysqli_query` etc. | every database query, via zero-code auto-instrumentation | SQL statement |
| `curl_exec`, file IO | outgoing HTTP calls and file operations, via zero-code auto-instrumentation | |

The root span starts at PHP script start, so bootstrap time (PHASE-1) is visible as the gap before the first child span. Auto-instrumented calls that happen *during* bootstrap (autoload file reads, session file IO, the database connect) run before the root span opens; they appear as separate tiny traces, which you can ignore.

## Quick start (dev stack)

Start the dev stack with the OTel overlay. It enables tracing and adds a [Jaeger](https://www.jaegertracing.io/) trace viewer:

```bash
docker compose -f compose.yaml -f compose.otel.yaml up -d --build
./generate.sh hello-world main.adl
```

Use the application on http://localhost, then open the Jaeger UI on **http://localhost:16686**, select service `ampersand-prototype` and press *Find traces*. Every API request is one trace; open one to see the waterfall of spans listed above.

## Enabling on any deployed prototype

Any prototype built on base image **v2.7.0 or later** (`FROM ampersandtarski/prototype-framework:<version>`) accepts the standard [OTel environment variables](https://opentelemetry.io/docs/languages/sdk-configuration/); older images do not contain the instrumentation, so these variables have no effect there. The image defaults are:

```bash
OTEL_SDK_DISABLED=true            # tracing off; set to false to enable
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=ampersand-prototype
OTEL_TRACES_EXPORTER=console      # spans go to the container log (stdout)
OTEL_METRICS_EXPORTER=none
OTEL_LOGS_EXPORTER=none
OTEL_PROPAGATORS=baggage,tracecontext
```

To ship traces to a collector (an OTel collector, Jaeger, Grafana Tempo, or any OTLP endpoint):

```bash
OTEL_SDK_DISABLED=false
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_ENDPOINT=http://<collector-host>:4318
```

For a quick look without any collector, set only `OTEL_SDK_DISABLED=false`: the default `console` exporter prints each span as JSON in the container log (`docker logs <container>`).

## Answering "where does the time go?"

An example of the kind of question this answers. Session creation in a large prototype (the FC5 Landeneisenregister) takes tens of seconds. Enable tracing, open a fresh session, and look at the trace of its first request: the `session init` span carries that time, and the `conjunct <id>` spans under it show which conjuncts are responsible, each backed by a `mysqli` query span holding the exact SQL. That turns "the application is slow" into "these conjunct queries are slow" — a question a database index or a model change can answer.

The general recipe:

1. Enable tracing (see above) and perform the slow action once.
2. Find the trace of the slow request (sort by duration in the Jaeger UI).
3. Read the waterfall top-down: the deepest long span names the culprit — a conjunct (rule evaluation), the ExecEngine, or a single query.
4. `ampersand.conjunct` names the conjunct; look it up in `backend/generics/conjuncts.json` to see which rule and which term it evaluates.

## Overhead and safety

With `OTEL_SDK_DISABLED=true` (the default) the instrumentation calls resolve to no-op implementations; measurements and export do not happen. Enabling tracing adds the cost of span bookkeeping and export; use it to investigate, not as an always-on default in production.

## Trace context propagation

The middleware honours incoming [W3C trace context](https://www.w3.org/TR/trace-context/) headers (`traceparent`, `tracestate`, `baggage`). A reverse proxy that starts spans itself (e.g. Traefik with tracing enabled) or a test driver can therefore link the backend trace to its own.

## Under the hood

- The PECL extension `opentelemetry` (installed in both `Dockerfile` and `dev.Dockerfile`) provides the hook mechanism for zero-code auto-instrumentation; the composer packages `open-telemetry/opentelemetry-auto-{mysqli,curl,io}` use it to trace queries, HTTP calls and file IO without code changes.
- OpenTelemetry has no auto-instrumentation for Slim 3, which the framework uses for its API. [OtelRequestSpanMiddleware](../../backend/src/Ampersand/API/Middleware/OtelRequestSpanMiddleware.php) therefore opens the root span manually as the outermost Slim middleware.
- Manual spans elsewhere in the framework use the small wrapper [Ampersand\Misc\Otel](../../backend/src/Ampersand/Misc/Otel.php): `Otel::span('name', fn () => ...)`. Use it when you instrument new framework code.
- Metrics and logs are not exported (exporters default to `none`); the current instrumentation covers traces. The Monolog log bridge needs Monolog ≥ 2 and waits for the framework's Monolog upgrade.
