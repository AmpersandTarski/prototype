import { safeUrl } from './safe-url';

describe('safeUrl', () => {
  it.each([
    'https://ampersandtarski.github.io',
    'http://x.nl/a?b=c',
    'mailto:a@b.nl',
    './a.md',
    '#kop',
    '/pad',
    'pad/naar',
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
  ])('empties %s', (url) => expect(safeUrl(url)).toBe(''));

  it('gives an empty URL for null and undefined', () => {
    expect(safeUrl(null)).toBe('');
    expect(safeUrl(undefined)).toBe('');
  });
});
