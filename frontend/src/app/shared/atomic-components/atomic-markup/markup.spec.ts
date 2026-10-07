import { formatName, markupFormat, renderMarkup } from './markup';

describe('markupFormat', () => {
  it('knows the formats and their aliases, in any case', () => {
    expect(markupFormat('markdown')).toBe('MARKDOWN');
    expect(markupFormat('GitHub-Markdown')).toBe('GFM');
    expect(markupFormat(' html ')).toBe('HTML');
    expect(markupFormat('ascii')).toBe('TEXT');
  });

  it('gives TEXT for an empty name', () => {
    expect(markupFormat('')).toBe('TEXT');
    expect(markupFormat(null)).toBe('TEXT');
  });

  it('gives TEXT for an unknown name, with one warning per name', () => {
    const warn = jest.spyOn(console, 'warn').mockImplementation(() => undefined);
    expect(markupFormat('EBCDIC')).toBe('TEXT');
    expect(markupFormat('ebcdic')).toBe('TEXT');
    expect(warn).toHaveBeenCalledTimes(1);
    warn.mockRestore();
  });
});

describe('formatName', () => {
  it('reads a string, an OBJECT atom and a list', () => {
    expect(formatName('GFM')).toBe('GFM');
    expect(formatName({ _id_: 'HTML', _label_: 'Html' })).toBe('HTML');
    expect(formatName(['MARKDOWN', 'HTML'])).toBe('MARKDOWN');
    expect(formatName([])).toBe('');
    expect(formatName(undefined)).toBe('');
  });
});

describe('renderMarkup', () => {
  it('formats Markdown without the GitHub extensions', () => {
    expect(renderMarkup('**bold**', 'MARKDOWN')).toContain('<strong>bold</strong>');
    expect(renderMarkup('| a |\n|---|\n| 1 |', 'MARKDOWN')).not.toContain('<table');
  });

  it('formats GitHub-flavoured Markdown with tables and strikethrough', () => {
    const html = renderMarkup('| a |\n|---|\n| 1 |\n\n~~x~~', 'GFM');
    expect(html).toContain('<table');
    expect(html).toContain('<del>x</del>');
  });

  it('passes HTML on, for Angular to sanitise', () => {
    expect(renderMarkup('<b>x</b>', 'HTML')).toBe('<b>x</b>');
  });

  it('escapes plain text', () => {
    expect(renderMarkup('<b>x</b> & "y"', 'TEXT')).toBe('&lt;b&gt;x&lt;/b&gt; &amp; &quot;y&quot;');
  });

  it('shows nothing for a missing text', () => {
    expect(renderMarkup(null, 'MARKDOWN')).toBe('');
  });
});
