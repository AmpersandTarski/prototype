import { renderLatex } from './latex';
import { markupFormat, renderMarkup } from './markup';

/** What the format LATEX makes of a text fragment (DesignChoices OK-25). */
describe('renderLatex', () => {
  it('is the format LATEX, also under the name TEX', () => {
    expect(markupFormat('latex')).toBe('LATEX');
    expect(markupFormat('TeX')).toBe('LATEX');
    expect(renderMarkup('a $x$', 'LATEX')).toContain('<math');
  });

  it('gives inline and display mathematics to KaTeX, as MathML', () => {
    const html = renderLatex('The sum $a + b$ and \\[ \\frac{1}{2} \\] and \\(c\\).');
    expect(html.match(/<math/g)).toHaveLength(3);
    expect(html).toContain('display="block"');
    expect(html).toContain('<mfrac>');
  });

  it('formats an align environment as display mathematics', () => {
    const html = renderLatex('\\begin{align*} a &= b \\\\ c &= d \\end{align*}');
    expect(html).toContain('<math');
    expect(html).not.toContain('markup__math-source');
  });

  it('shows an unknown macro in a formula as an operator name', () => {
    const html = renderLatex('$\\softmax(z)$');
    expect(html).toContain('<math');
    expect(html).toContain('softmax');
    expect(html).not.toContain('markup__math-source');
  });

  it('shows the argument of an unknown macro in a formula as text, such as a proof hint', () => {
    const html = renderLatex('\\[ a \\\\ = \\why{multiply by $c > 0$} \\\\ b \\]');
    expect(html).toContain('<math');
    expect(html).toContain('multiply by');
    expect(html).not.toContain('markup__math-source');
  });

  it('shows a formula that does not parse as its source', () => {
    const html = renderLatex('$\\frac{1}{$');
    expect(html).toContain('markup__math-source');
    expect(html).toContain('\\frac{1}{');
  });

  it('formats headings, emphasis and code', () => {
    const html = renderLatex('\\subsection*{The need} An \\emph{open} claim in \\texttt{Lean} is \\textbf{bold}.');
    expect(html).toContain('<h4>The need</h4>');
    expect(html).toContain('<em>open</em>');
    expect(html).toContain('<code>Lean</code>');
    expect(html).toContain('<strong>bold</strong>');
  });

  it('formats a quote and a theorem-like environment', () => {
    const html = renderLatex('\\begin{quote}A box.\\end{quote}\\begin{definition}[Stack] A pile.\\end{definition}');
    expect(html).toContain('<blockquote><p>A box.</p></blockquote>');
    expect(html).toContain('<strong>Definition (Stack).</strong>');
    expect(html).toContain('markup__env--definition');
  });

  it('formats a list', () => {
    const html = renderLatex('\\begin{enumerate}\\item one \\item two\\end{enumerate}');
    expect(html).toMatch(/<ol><li>\s*one\s*<\/li><li>\s*two\s*<\/li><\/ol>/);
  });

  it('keeps the argument of an unknown command, and names a command without one', () => {
    const html = renderLatex('See \\lean{Stack.pile} and \\supports here.');
    expect(html).toContain('<span class="markup__cmd" data-cmd="lean">Stack.pile</span>');
    expect(html).toContain('<code class="markup__cmd">\\supports</code>');
  });

  it('keeps the content of an unknown environment', () => {
    const html = renderLatex('\\begin{sidenote}Kept.\\end{sidenote}');
    expect(html).toContain('data-env="sidenote"');
    expect(html).toContain('Kept.');
  });

  it('shows citations and references, and nothing for a label', () => {
    const html = renderLatex('As shown \\citep{Ke2019, Sony2020} in \\ref{sec:need}.\\label{here}');
    expect(html).toContain('<span class="markup__cite">[Ke2019, Sony2020]</span>');
    expect(html).toContain('<span class="markup__ref">sec:need</span>');
    expect(html).not.toContain('here');
  });

  it('reads the escaped characters and the typography of TeX', () => {
    const html = renderLatex("50\\% of A\\&B, \\textless{}k\\textgreater{}, pages 3--4, ``quoted''");
    expect(html).toContain('50% of A&amp;B, &lt;k&gt;, pages 3–4, “quoted”');
  });

  it('leaves out a comment', () => {
    expect(renderLatex('shown % hidden\nalso shown')).not.toContain('hidden');
  });

  it('starts a new paragraph at an empty line', () => {
    expect(renderLatex('one\n\ntwo')).toBe('<p>one</p><p>two</p>');
  });

  it('escapes HTML in every piece of source text, since the result is trusted', () => {
    const attack = '<script>alert(1)</script> \\emph{<img src=x onerror=alert(2)>} \\unknown{<b>} \\begin{quote}<i>\\end{quote} \\texttt{<u>} \\cite{<s>} $<$';
    const html = renderLatex(attack);
    expect(html).not.toMatch(/<script|<img|<b>|<i>|<u>|<s>/);
    expect(html).toContain('&lt;script&gt;');
  });

  it('does not let a command or environment name carry HTML into an attribute', () => {
    const html = renderLatex('\\begin{x"onmouseover="alert(1)}y\\end{x"onmouseover="alert(1)}');
    expect(html).not.toContain('onmouseover="alert');
  });

  it('gives no link for \\href and \\url, only the text', () => {
    const html = renderLatex('\\href{javascript:alert(1)}{click} \\url{javascript:alert(2)}');
    expect(html).not.toContain('href=');
    expect(html).toContain('click');
  });
});
