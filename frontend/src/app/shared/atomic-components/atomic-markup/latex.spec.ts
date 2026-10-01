import { renderLatex, renderMath } from './latex';
import { markupFormat, renderMarkup } from './markup';

/** What the format LATEX makes of a text fragment (DesignChoices OK-25). */

/** The page after both steps: the HTML of `renderLatex`, then the formulas rendered in it. */
function page(source: string): string {
  const root = document.createElement('div');
  root.innerHTML = renderLatex(source);
  renderMath(root);
  return root.innerHTML;
}

describe('the format LATEX', () => {
  it('is known under the names LATEX and TEX', () => {
    expect(markupFormat('latex')).toBe('LATEX');
    expect(markupFormat('TeX')).toBe('LATEX');
    expect(renderMarkup('a $x$', 'LATEX')).toContain('class="markup__math"');
  });
});

describe('renderLatex, the text', () => {
  const shows: Array<[string, string, string]> = [
    ['a heading', String.raw`\subsection*{The need}`, '<h4>The need</h4>'],
    ['emphasis', String.raw`An \emph{open} claim`, '<em>open</em>'],
    ['code', String.raw`in \texttt{Lean}`, '<code>Lean</code>'],
    ['bold', String.raw`is \textbf{bold}`, '<strong>bold</strong>'],
    ['a quote', String.raw`\begin{quote}A box.\end{quote}`, '<blockquote><p>A box.</p></blockquote>'],
    [
      'a theorem-like environment with its title',
      String.raw`\begin{definition}[Stack] A pile.\end{definition}`,
      '<div class="markup__env markup__env--definition"><p><strong>Definition (Stack).</strong>',
    ],
    ['the argument of an unknown command', String.raw`See \lean{Stack.pile}`, '<span class="markup__cmd markup__cmd--lean">Stack.pile</span>'],
    ['the name of an unknown command without argument', String.raw`and \supports here`, String.raw`<code class="markup__cmd">\supports</code>`],
    ['the content of an unknown environment', String.raw`\begin{sidenote}Kept.\end{sidenote}`, '<div class="markup__env markup__env--sidenote"><p>Kept.</p></div>'],
    ['citations', String.raw`\citep{Ke2019, Sony2020}`, '<span class="markup__cite">[Ke2019, Sony2020]</span>'],
    ['a reference', String.raw`in \ref{sec:need}`, '<span class="markup__ref">sec:need</span>'],
    [
      'escaped characters and the typography of TeX',
      String.raw`50\% of A\&B, \textless{}k\textgreater{}, pages 3--4, ` + "``quoted''",
      '50% of A&amp;B, &lt;k&gt;, pages 3–4, “quoted”',
    ],
    ['a formula as a span with its source', 'The sum $a < b$.', '<span class="markup__math">a &lt; b</span>'],
    ['display mathematics', String.raw`\[ x \]`, '<span class="markup__math markup__math--display"> x </span>'],
    ['the text of a link, and no link', String.raw`\href{javascript:alert(1)}{click}`, '<p>click</p>'],
  ];
  it.each(shows)('shows %s', (_what, source, expected) => {
    expect(renderLatex(source)).toContain(expected);
  });

  const hides: Array<[string, string, string]> = [
    ['a comment', 'shown % hidden\nalso shown', 'hidden'],
    ['a label', String.raw`text\label{here}`, 'here'],
    ['a link address', String.raw`\href{javascript:alert(1)}{click} \url{x}`, 'href='],
  ];
  it.each(hides)('leaves out %s', (_what, source, absent) => {
    expect(renderLatex(source)).not.toContain(absent);
  });

  it('formats a list', () => {
    expect(renderLatex(String.raw`\begin{enumerate}\item one \item two\end{enumerate}`)).toMatch(
      /<ol><li>\s*one\s*<\/li><li>\s*two\s*<\/li><\/ol>/,
    );
  });

  it('starts a new paragraph at an empty line', () => {
    expect(renderLatex('one\n\ntwo')).toBe('<p>one</p><p>two</p>');
  });

  it('escapes HTML in every piece of source text', () => {
    const attack =
      String.raw`<script>alert(1)</script> \emph{<img src=x onerror=alert(2)>} \unknown{<b>} ` +
      String.raw`\begin{quote}<i>\end{quote} \texttt{<u>} \cite{<s>} $<a href=x>$ \begin{x"onmouseover="alert(1)}y\end{x"onmouseover="alert(1)}`;
    const html = renderLatex(attack);
    expect(html).not.toMatch(/<script|<img|<b>|<i>|<u>|<s>|<a |onmouseover="alert/);
    expect(html).toContain('&lt;script&gt;');
  });
});

describe('renderMath, the formulas in the page', () => {
  const renders: Array<[string, string, string]> = [
    ['inline mathematics', '$a + b$', '<math'],
    ['a fraction', String.raw`\[ \frac{1}{2} \]`, '<mfrac>'],
    ['display mathematics', String.raw`\[ x \]`, 'display="block"'],
    ['an align environment', String.raw`\begin{align*} a &= b \\ c &= d \end{align*}`, '<mtable'],
    ['an unknown macro as an operator name', String.raw`$\softmax(z)$`, 'softmax'],
    ['the argument of an unknown macro as text, such as a proof hint', String.raw`\[ a \\ = \why{multiply by $c > 0$} \\ b \]`, 'multiply by'],
  ];
  it.each(renders)('renders %s', (_what, source, expected) => {
    const html = page(source);
    expect(html).toContain(expected);
    expect(html).not.toContain('markup__math-source');
  });

  it('counts three formulas in a text with three', () => {
    expect(page(String.raw`The sum $a + b$ and \[ \frac{1}{2} \] and \(c\).`).match(/<math/g)).toHaveLength(3);
  });

  it('shows a formula that does not parse as its source', () => {
    const html = page(String.raw`$\frac{1}{$`);
    expect(html).toContain('markup__math-source');
    expect(html).toContain(String.raw`\frac{1}{`);
  });

  it('renders a formula once', () => {
    const root = document.createElement('div');
    root.innerHTML = renderLatex('$x$');
    renderMath(root);
    const once = root.innerHTML;
    renderMath(root);
    expect(root.innerHTML).toBe(once);
  });

  it('builds no element from HTML in a formula', () => {
    const root = document.createElement('div');
    root.innerHTML = renderLatex(String.raw`$\text{<img src=x onerror=alert(1)>}$ and $<script>$`);
    renderMath(root);
    expect(root.querySelector('img, script')).toBeNull();
  });
});
