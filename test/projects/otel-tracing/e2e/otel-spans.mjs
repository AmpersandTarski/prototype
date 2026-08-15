// End-to-end regression for the OpenTelemetry instrumentation (issue #440).
//
// Guards the promise of docs/guides/measuring-performance-with-opentelemetry.md:
// with the SDK enabled, one API request yields exactly one trace, carrying the
// manually opened root span (Slim 3 has no auto-instrumentation), the phase spans
// (app init, session init, transaction close) and nested mysqli query spans;
// evaluating all rules yields `conjunct <id>` spans. With OTEL_SDK_DISABLED=true
// (the image default) nothing is exported.
//
// The spec asserts the STRUCTURE of the telemetry (span names, one trace id per
// request), never timing values, so it is deterministic and machine-independent.
//
// It does not touch the stack's Apache: it starts PHP built-in servers inside the
// prototype container (with per-process OTel env), fires requests at them, and
// reads the span JSON that the `console` exporter prints on the server's stdout.
//
// Run by test/run-regression.sh (picked up as e2e/*.mjs). Env in:
//   PROTOTYPE_URL       base URL of the running prototype (unused here)
//   PROTOTYPE_CONTAINER prototype (web) container name (e.g. reg-otel-tracing-prototype)
// Exit 0 = all assertions pass, 1 = failure.

import { execFileSync } from 'node:child_process';

const PROTO = process.env.PROTOTYPE_CONTAINER || 'reg-otel-tracing-prototype';

let failed = false;
const pass = (m) => console.log(`  PASS  ${m}`);
const fail = (m) => { console.log(`  FAIL  ${m}`); failed = true; };

function docker(...args) {
  return execFileSync('docker', ['exec', ...args], { encoding: 'utf8' });
}

// Start a PHP built-in server in the container with the given env, request the
// given API routes through it, stop it, and return the raw server stdout.
// The console exporter flushes the spans of a request when that request ends,
// so the output is complete once every curl returned.
function serveAndRequest(port, env, routes) {
  const log = `/tmp/otel-spec-${port}.log`;
  const envArgs = Object.entries(env).flatMap(([k, v]) => ['-e', `${k}=${v}`]);
  docker('-d', ...envArgs, PROTO, 'sh', '-c',
    `php -S 127.0.0.1:${port} -t /var/www/html > ${log} 2>&1`);
  try {
    for (const route of routes) {
      // Retry briefly: the server needs a moment to accept connections
      docker(PROTO, 'sh', '-c',
        `for i in 1 2 3 4 5 6 7 8 9 10; do
           code=$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:${port}/api/v1/index.php${route}) && [ "$code" = 200 ] && exit 0
           sleep 1
         done
         echo "no 200 from ${route} (last: $code)" >&2; exit 1`);
    }
    return docker(PROTO, 'cat', log);
  } finally {
    // [p]hp: the bracket keeps the pattern from matching this shell's own command line
    docker(PROTO, 'sh', '-c', `pkill -f '[p]hp -S 127.0.0.1:${port}' || true`);
  }
}

// The console exporter prints one pretty-printed JSON array of spans per flush.
// Extract every top-level JSON array from the mixed stdout (server log lines in
// between) and flatten to a list of spans.
function parseSpans(output) {
  const spans = [];
  let depth = 0, start = -1, inString = false, escaped = false;
  for (let i = 0; i < output.length; i++) {
    const c = output[i];
    if (inString) {
      if (escaped) escaped = false;
      else if (c === '\\') escaped = true;
      else if (c === '"') inString = false;
      continue;
    }
    if (c === '"') inString = true;
    else if (c === '[' || c === '{') { if (depth === 0 && c === '[') start = i; depth++; }
    else if (c === ']' || c === '}') {
      depth--;
      if (depth === 0 && start >= 0) {
        try { spans.push(...JSON.parse(output.slice(start, i + 1))); } catch { /* not a span batch */ }
        start = -1;
      }
    }
  }
  // Server log lines contain fragments like "[200]" that parse as JSON arrays
  // too, so keep only elements that have the shape of a span.
  return spans
    .filter((s) => s !== null && typeof s === 'object' && typeof s.name === 'string' && s.context)
    .map((s) => ({
      name: s.name,
      traceId: s.context.trace_id,
      parentId: s.parent_span_id ?? '',
    }));
}

const names = (spans) => spans.map((s) => s.name);

// ---------------------------------------------------------------------------
// Scenario 1: SDK enabled — one trace per request, with the expected spans
// ---------------------------------------------------------------------------
const out = serveAndRequest(8099,
  { OTEL_SDK_DISABLED: 'false', OTEL_TRACES_EXPORTER: 'console' },
  ['/app/navbar', '/admin/ruleengine/evaluate/all']);
const spans = parseSpans(out);

// Bootstrap-phase IO (autoload file reads, session fopen, db connect) happens
// before the middleware opens the root span, so those auto-instrumented spans
// form separate parentless mini-traces. Assert on the request spans by name.
const roots = spans.filter((s) => s.parentId === '');

const navbarRoots = roots.filter((s) => s.name === 'GET /app/navbar');
navbarRoots.length === 1
  ? pass('exactly one root span named after method + route pattern (GET /app/navbar)')
  : fail(`expected 1 root span 'GET /app/navbar', got ${navbarRoots.length} (roots: ${names(roots).join(', ')})`);
const navbarRoot = navbarRoots[0];

const evalRoots = roots.filter((s) => s.name === 'GET /admin/ruleengine/evaluate/all');
evalRoots.length === 1
  ? pass('exactly one root span GET /admin/ruleengine/evaluate/all')
  : fail(`expected 1 root span for evaluate/all, got ${evalRoots.length} (roots: ${names(roots).join(', ')})`);
const evalRoot = evalRoots[0];

if (navbarRoot) {
  const inTrace = spans.filter((s) => s.traceId === navbarRoot.traceId && s !== navbarRoot);
  for (const expected of ['app init', 'session init', 'transaction close']) {
    inTrace.some((s) => s.name === expected)
      ? pass(`navbar trace carries phase span '${expected}'`)
      : fail(`navbar trace misses phase span '${expected}' (has: ${[...new Set(names(inTrace))].join(', ')})`);
  }
  inTrace.some((s) => s.name.startsWith('mysqli'))
    ? pass('navbar trace carries auto-instrumented mysqli spans')
    : fail('navbar trace carries no mysqli spans');
  const foreign = spans.filter((s) => s.parentId === '' && s.traceId === navbarRoot.traceId && s !== navbarRoot);
  foreign.length === 0
    ? pass('navbar request is exactly one trace (no second root in its trace id)')
    : fail('navbar trace id has more than one root span');
}

if (evalRoot) {
  const inTrace = spans.filter((s) => s.traceId === evalRoot.traceId);
  inTrace.some((s) => s.name.startsWith('conjunct '))
    ? pass('evaluate/all trace carries conjunct spans')
    : fail(`evaluate/all trace carries no conjunct spans (has: ${[...new Set(names(inTrace))].join(', ')})`);
}

// ---------------------------------------------------------------------------
// Scenario 2: SDK disabled (the image default) — nothing is exported
// ---------------------------------------------------------------------------
const outOff = serveAndRequest(8098,
  { OTEL_SDK_DISABLED: 'true', OTEL_TRACES_EXPORTER: 'console' },
  ['/app/navbar']);
parseSpans(outOff).length === 0
  ? pass('with OTEL_SDK_DISABLED=true no spans are exported')
  : fail('spans were exported although the SDK is disabled');

process.exit(failed ? 1 : 0);
