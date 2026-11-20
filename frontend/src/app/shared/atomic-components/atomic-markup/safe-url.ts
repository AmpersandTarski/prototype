/**
 * The URL schemes a link or an image in the Markdown editor may point to (DesignChoices OK-35).
 * A URL without a scheme (relative, a fragment, a path) passes; any other scheme, such as
 * `javascript:`, `data:` or `vbscript:`, gives an empty URL. The check ignores the control
 * characters and white space a browser ignores in a scheme, so `java\tscript:` is caught too.
 */
const ALLOWED = new Set(['http', 'https', 'mailto']);

export function safeUrl(url: unknown): string {
  const text = url === null || url === undefined ? '' : String(url);
  // eslint-disable-next-line no-control-regex
  const scheme = /^([a-z][a-z0-9+.-]*):/i.exec(
    text.replace(/[\x00-\x20\x7f]/g, ''),
  );
  return scheme === null || ALLOWED.has(scheme[1].toLowerCase()) ? text : '';
}
