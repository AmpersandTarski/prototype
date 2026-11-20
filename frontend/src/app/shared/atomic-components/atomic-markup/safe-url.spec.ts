import { safeUrl } from './safe-url';

describe('safeUrl', () => {
  it.each([
    'https://ampersandtarski.github.io',
    'http://x.nl/a?b=c',
    'mailto:a@b.nl',
    'tel:+31201234567',
    './a.md',
    '#kop',
    '/pad',
    'pad/naar',
    'https://x.nl/a?b=1&c=2',
    '/zoek?q=a&b',
    '',
  ])('keeps %s', (url) => expect(safeUrl(url)).toBe(url));

  it.each([
    'javascript:alert(1)',
    'JaVaScRiPt:alert(1)',
    ' javascript:alert(1)',
    'java\tscript:alert(1)',
    'java\nscript:alert(1)',
    '\u0000javascript:alert(1)',
    'data:text/html;base64,PHNjcmlwdD4=',
    'data:image/svg+xml,<svg onload=alert(1)>',
    'vbscript:msgbox(1)',
    'file:///etc/passwd',
    '&#106;avascript:alert(1)',
    '&#x6A;avascript:alert(1)',
    '&#100;ata:text/html,x',
    'java&#9;script:alert(1)',
    'javascript&#58;alert(1)',
    'ms-msdt&colon;x',
    'search-ms&#58;query=x',
    'javascript&colon;alert(1)',
    '/api/v1/admin/installer',
    'api/v1/admin/installer?ignoreInvariantRules=true',
    'http://localhost/api/v1/admin/utils/regenerate-all-atom-ids',
    '/api/v1/ADMIN/execengine/run',
    '/api/v1/%61dmin/installer',
    '/api/v1/%2561dmin/installer',
    '/api/v1/./admin/installer',
    '/api/v1/%2e/admin/installer',
    '/api/v1/x/../admin/installer',
    '/api//v1/admin/installer',
    '/api\\v1\\admin\\installer',
  ])('empties %s', (url) => expect(safeUrl(url)).toBe(''));

  it.each([
    '/api/v1/resource/Note/n1',
    '/api/v1/administration-guide',
    'https://example.org/docs/admin',
  ])('keeps %s, which is no administration route', (url) =>
    expect(safeUrl(url)).toBe(url),
  );

  it('gives an empty URL for null and undefined', () => {
    expect(safeUrl(null)).toBe('');
    expect(safeUrl(undefined)).toBe('');
  });
});
