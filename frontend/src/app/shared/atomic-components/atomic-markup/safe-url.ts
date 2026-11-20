/**
 * The URL schemes a Markdown link or image may point to, in the editor and in the reading mode
 * (DesignChoices OK-35). A URL without a scheme (relative, a fragment, a path) passes, and so do
 * `http:`, `https:`, `mailto:` and `tel:`; any other scheme, such as `javascript:`, `data:` or
 * `vbscript:`, gives an empty URL.
 *
 * The check reads the URL as a browser would. It ignores the control characters and white space a
 * browser ignores in a scheme, so `java\tscript:` is caught, and it decodes numeric character
 * references, so `&#106;avascript:` is caught. `marked` leaves a reference in the URL as written,
 * and the browser decodes it in the attribute. A named reference such as `&colon;` before the
 * path cannot be told apart from a scheme without a full table of names, so a URL with a `&`
 * before its first `/`, `?` or `#` gives an empty URL too.
 *
 * A URL into the administration routes of the framework (`api/v1/admin`) gives an empty URL as
 * well. Several of those routes change the application on a GET, among them the installer, which
 * empties the database outside production mode; an image in a text would call them for everyone
 * who opens it (AmpersandTarski/prototype#548).
 */
const ALLOWED = new Set(['http', 'https', 'mailto', 'tel']);

/** The administration routes, in a URL whose percent-escapes are decoded. */
const ADMIN = /api\/v1\/admin(?![a-z0-9_-])/i;

export function safeUrl(url: unknown): string {
  const text = url === null || url === undefined ? '' : String(url);
  const decoded = text
    .replace(/&#x([0-9a-f]+);?/gi, (_, hex: string) =>
      codePoint(parseInt(hex, 16)),
    )
    .replace(/&#(\d+);?/g, (_, dec: string) => codePoint(parseInt(dec, 10)))
    // eslint-disable-next-line no-control-regex
    .replace(/[\x00-\x20\x7f]/g, '');
  if (/^[^/?#]*&/.test(decoded)) return '';
  if (ADMIN.test(normalisedPath(decoded))) return '';
  const scheme = /^([a-z][a-z0-9+.-]*):/i.exec(decoded);
  return scheme === null || ALLOWED.has(scheme[1].toLowerCase()) ? text : '';
}

/**
 * The path a browser requests for a URL: with `.` and `..` segments resolved (also as `%2e`),
 * `\` read as `/`, percent-escapes decoded (also twice escaped ones) and repeated slashes as one.
 */
function normalisedPath(url: string): string {
  let text: string;
  try {
    text = new URL(url, 'http://prototype.invalid/').pathname;
  } catch {
    text = url;
  }
  for (let i = 0; i < 3; i++) {
    try {
      const next = decodeURIComponent(text);
      if (next === text) break;
      text = next;
    } catch {
      break;
    }
  }
  return text.replace(/\\/g, '/').replace(/\/{2,}/g, '/');
}

/** The character of a numeric reference; one outside Unicode becomes U+FFFD, as in a browser. */
function codePoint(n: number): string {
  return n > 0 && n <= 0x10ffff ? String.fromCodePoint(n) : '�';
}
