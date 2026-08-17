/**
 * The dry-run round of a transactional interface: what it sends, what it gets back, and how
 * long it took. See DesignChoices OK-10.
 */

/** Violation pairs of one conjunct, as the backend reports and accepts them. */
export type ViolationPair = { src: string; tgt: string };

/** Verdicts of a dry-run round, keyed by conjunct id. */
export type CarriedVerdicts = Record<string, ViolationPair[]>;

/**
 * Body of a dry-run PATCH. Without the envelope the backend evaluates every conjunct the whole
 * buffer affects; with it, it evaluates only what the operations after `carriedOpCount` affect
 * and takes the rest from `carriedVerdicts`. The SAVE round never uses the envelope.
 */
export interface DryRunEnvelope {
  ops: unknown[];
  carriedVerdicts: CarriedVerdicts;
  /** How many leading operations of `ops` the carried verdicts already account for. */
  carriedOpCount: number;
}

/** What the backend adds to a dry-run response. */
export interface DryRunVerdictResponse {
  dryRunVerdicts?: CarriedVerdicts | null;
  dryRunOpCount?: number | null;
}

/** One measured dry-run round. Times are milliseconds on the `performance.now()` clock. */
export interface DryRunTiming {
  /** Number of buffered operations the round sent. */
  bufferSize: number;
  /** Operations the round left to the backend to evaluate (0 = the backend evaluated all). */
  carriedOpCount: number;
  /** From the edit that triggered the round to the rendered verdict, debounce included. */
  triggerToRendered: number;
  /** From the request leaving the client to the rendered verdict. This is the acceptance metric. */
  sentToRendered: number;
  /** Number of worklist entries the round produced. */
  worklistSize: number;
  /** Wall-clock moment the round was recorded, for correlating with a server-side trace. */
  at: number;
}

/**
 * Ring buffer of measured rounds, reachable from a browser session or an automated driver as
 * `window.ampersandDryRunTimings`. The instrument the acceptance of prototype#445 asks for
 * ("time from patch-sent to verdict-rendered") lives here, because both ends of that interval
 * are events only the client sees.
 */
const RING_SIZE = 200;

declare global {
  interface Window {
    ampersandDryRunTimings?: DryRunTiming[];
  }
}

export function recordDryRunTiming(timing: DryRunTiming): void {
  if (typeof window === 'undefined') return;
  const ring = (window.ampersandDryRunTimings ??= []);
  ring.push(timing);
  if (ring.length > RING_SIZE) ring.splice(0, ring.length - RING_SIZE);
}
