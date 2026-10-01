import katex from 'katex';

/**
 * Formats a LaTeX text as HTML, for the format LATEX of app-atomic-markup (DesignChoices
 * OK-25).
 *
 * A text in a database is a fragment, not a document: a claim with a quote, a definition,
 * some formulas and the author's own macros. So this converter does not try to be TeX. It
 * gives the mathematics to KaTeX, formats the structure it knows (headings, quotes,
 * theorem-like environments, lists, emphasis, code) and leaves everything else readable:
 * an unknown command shows its argument, and an unknown command without one shows its name.
 * No text disappears.
 *
 * The result is trusted HTML (the caller bypasses Angular's sanitiser, which would strip
 * MathML), so every piece of source text goes through `escapeHtml` here, and KaTeX runs
 * with `trust: false`.
 */

const MATH_ENVIRONMENTS = new Set([
  'equation',
  'equation*',
  'align',
  'align*',
  'gather',
  'gather*',
  'multline',
  'multline*',
  'displaymath',
  'math',
]);

/** Environments that read as a labelled block: "Definition. ..." */
const THEOREM_LIKE: Record<string, string> = {
  theorem: 'Theorem',
  lemma: 'Lemma',
  corollary: 'Corollary',
  proposition: 'Proposition',
  definition: 'Definition',
  example: 'Example',
  remark: 'Remark',
  proof: 'Proof',
  claim: 'Claim',
};

const HEADINGS: Record<string, string> = {
  section: 'h3',
  subsection: 'h4',
  subsubsection: 'h5',
  paragraph: 'h6',
};

/** Commands whose argument is shown in a tag. */
const WRAPPERS: Record<string, string> = {
  emph: 'em',
  textit: 'em',
  textsl: 'em',
  textbf: 'strong',
  texttt: 'code',
  textsc: 'span',
  textrm: 'span',
  textsf: 'span',
  underline: 'u',
  mbox: 'span',
  text: 'span',
};

/** Commands that stand for a character. */
const SYMBOLS: Record<string, string> = {
  textless: '&lt;',
  textgreater: '&gt;',
  textbackslash: '\\',
  textasciitilde: '~',
  textasciicircum: '^',
  textbar: '|',
  ldots: '…',
  dots: '…',
  S: '§',
  P: '¶',
  copyright: '©',
  LaTeX: 'LaTeX',
  TeX: 'TeX',
  quad: ' ',
  qquad: ' ',
  newline: '<br>',
  par: '</p><p>',
};

/** Commands that set up the page and show nothing; the number is their count of arguments. */
const SILENT: Record<string, number> = {
  label: 1,
  setcounter: 2,
  addtocounter: 2,
  vspace: 1,
  hspace: 1,
  noindent: 0,
  centering: 0,
  smallskip: 0,
  medskip: 0,
  bigskip: 0,
  newpage: 0,
  clearpage: 0,
  item: 0, // outside a list
};

const CITATIONS = new Set(['cite', 'citep', 'citet', 'citealp', 'citeauthor']);
const REFERENCES = new Set(['ref', 'eqref', 'pageref', 'autoref', 'cref', 'Cref']);

export function escapeHtml(text: string): string {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/**
 * The MathML for a formula. A macro that KaTeX does not know, such as the author's own
 * `\softmax`, becomes an operator name, and one with an argument shows that argument as text, so
 * one unknown macro does not turn the whole formula into source text. A formula that still does not parse is shown as its source.
 */
function math(source: string, display: boolean): string {
  const macros: Record<string, string> = {};
  for (let attempt = 0; attempt < 12; attempt++) {
    try {
      return katex.renderToString(source, {
        displayMode: display,
        output: 'mathml',
        throwOnError: true,
        trust: false,
        strict: 'ignore',
        macros: { ...macros },
      });
    } catch (e) {
      const unknown = /Undefined control sequence: \\([a-zA-Z]+)/.exec(String((e as Error)?.message ?? e));
      if (!unknown || `\\${unknown[1]}` in macros) break;
      // With an argument in braces the macro carries text, such as a hint in a calculational
      // proof (`\why{multiply by $c > 0$}`); without one it names an operator.
      const takesArgument = new RegExp(`\\\\${unknown[1]}\\s*\\{`).test(source);
      macros[`\\${unknown[1]}`] = takesArgument ? '\\text{#1}' : `\\operatorname{${unknown[1]}}`;
    }
  }
  return `<code class="markup__math-source">${escapeHtml(source)}</code>`;
}

/** The plain characters of running text: escaped, with TeX's typography. */
function plain(text: string): string {
  return escapeHtml(text)
    .replace(/---/g, '—')
    .replace(/--/g, '–')
    .replace(/``/g, '“')
    .replace(/&#39;&#39;/g, '”')
    .replace(/~/g, ' ')
    .replace(/\n[ \t]*\n\s*/g, '</p><p>');
}

class Parser {
  private i = 0;

  constructor(private readonly src: string) {}

  /** Parses until `stop` says so, or to the end. Returns the HTML. */
  parse(stop: (p: Parser) => boolean = () => false): string {
    let out = '';
    let text = '';
    const flush = () => {
      out += plain(text);
      text = '';
    };
    while (this.i < this.src.length && !stop(this)) {
      const c = this.src[this.i];
      if (c === '%') {
        // a comment runs to the end of the line
        const end = this.src.indexOf('\n', this.i);
        this.i = end < 0 ? this.src.length : end + 1;
      } else if (c === '$') {
        flush();
        out += this.dollarMath();
      } else if (c === '{') {
        flush();
        this.i++;
        out += this.parse((p) => p.peek() === '}');
        this.i++; // the closing brace
      } else if (c === '}') {
        this.i++; // a stray closing brace shows nothing
      } else if (c === '\\') {
        flush();
        out += this.command();
      } else {
        text += c;
        this.i++;
      }
    }
    flush();
    return out;
  }

  peek(): string {
    return this.src[this.i] ?? '';
  }

  private startsWith(s: string): boolean {
    return this.src.startsWith(s, this.i);
  }

  private dollarMath(): string {
    const display = this.startsWith('$$');
    const open = display ? '$$' : '$';
    const from = this.i + open.length;
    let end = from;
    while (end < this.src.length) {
      end = this.src.indexOf(open, end);
      if (end < 0) break;
      if (this.src[end - 1] !== '\\') break;
      end += 1;
    }
    if (end < 0) {
      // no closing dollar: the sign is text
      this.i += open.length;
      return escapeHtml(open);
    }
    this.i = end + open.length;
    return math(this.src.slice(from, end), display);
  }

  /** The raw text up to `closing`, which is consumed; the rest of the text when it is missing. */
  private rawUntil(closing: string): string {
    const end = this.src.indexOf(closing, this.i);
    const raw = this.src.slice(this.i, end < 0 ? this.src.length : end);
    this.i = end < 0 ? this.src.length : end + closing.length;
    return raw;
  }

  /** An argument in braces, as raw text; null when the next character is not a brace. */
  private rawArgument(): string | null {
    if (this.peek() !== '{') return null;
    let depth = 0;
    const from = this.i + 1;
    for (let j = this.i; j < this.src.length; j++) {
      const c = this.src[j];
      if (c === '\\') j++;
      else if (c === '{') depth++;
      else if (c === '}' && --depth === 0) {
        this.i = j + 1;
        return this.src.slice(from, j);
      }
    }
    this.i = this.src.length;
    return this.src.slice(from);
  }

  /** An argument in braces, formatted; null when there is none. */
  private argument(): string | null {
    const raw = this.rawArgument();
    return raw === null ? null : new Parser(raw).parse();
  }

  /** An optional argument in square brackets, as raw text. */
  private optional(): string | null {
    if (this.peek() !== '[') return null;
    const end = this.src.indexOf(']', this.i);
    if (end < 0) return null;
    const raw = this.src.slice(this.i + 1, end);
    this.i = end + 1;
    return raw;
  }

  private command(): string {
    this.i++; // the backslash
    const c = this.peek();
    if (c === '(') {
      this.i++;
      return math(this.rawUntil('\\)'), false);
    }
    if (c === '[') {
      this.i++;
      return math(this.rawUntil('\\]'), true);
    }
    if (c === '\\') {
      this.i++;
      this.optional(); // \\[2pt]
      return '<br>';
    }
    if (!/[a-zA-Z]/.test(c)) {
      // an escaped character: \% \& \_ \# \$ \{ \} and the thin spaces
      this.i++;
      return c === ',' || c === ';' || c === ' ' || c === '!' ? ' ' : escapeHtml(c);
    }
    let name = '';
    while (/[a-zA-Z]/.test(this.peek())) name += this.src[this.i++];
    const starred = this.peek() === '*';
    if (starred) this.i++;
    // TeX swallows the spaces after a command name
    while (this.peek() === ' ') this.i++;

    if (name === 'begin') return this.environment();
    if (name === 'end') {
      this.rawArgument(); // an \end without its \begin shows nothing
      return '';
    }
    if (name in SYMBOLS) return SYMBOLS[name];
    if (name in SILENT) {
      for (let n = 0; n < SILENT[name]; n++) this.rawArgument();
      return '';
    }
    if (name in HEADINGS) {
      this.optional();
      const tag = HEADINGS[name];
      return `</p><${tag}>${this.argument() ?? ''}</${tag}><p>`;
    }
    if (name in WRAPPERS) {
      const tag = WRAPPERS[name];
      const cls = name === 'textsc' ? ' class="markup__smallcaps"' : '';
      return `<${tag}${cls}>${this.argument() ?? ''}</${tag}>`;
    }
    if (CITATIONS.has(name)) {
      this.optional();
      this.optional();
      const keys = (this.rawArgument() ?? '').split(',').map((k) => escapeHtml(k.trim()));
      return `<span class="markup__cite">[${keys.join(', ')}]</span>`;
    }
    if (REFERENCES.has(name)) {
      return `<span class="markup__ref">${escapeHtml(this.rawArgument() ?? '')}</span>`;
    }
    if (name === 'footnote') {
      return ` <small class="markup__footnote">(${this.argument() ?? ''})</small>`;
    }
    if (name === 'url') {
      return `<code>${escapeHtml(this.rawArgument() ?? '')}</code>`;
    }
    if (name === 'href') {
      this.rawArgument(); // the address; the text is what the reader sees
      return this.argument() ?? '';
    }
    if (name === 'verb') {
      const delimiter = this.src[this.i++];
      return `<code>${escapeHtml(this.rawUntil(delimiter))}</code>`;
    }

    // An unknown command, such as a macro of the author. Its arguments stay readable; the
    // name is kept in an attribute so that a project can style its own macros.
    const args: string[] = [];
    for (let a = this.argument(); a !== null; a = this.argument()) args.push(a);
    if (args.length === 0) return `<code class="markup__cmd">\\${escapeHtml(name)}</code>`;
    return `<span class="markup__cmd" data-cmd="${escapeHtml(name)}">${args.join(' ')}</span>`;
  }

  private environment(): string {
    const name = this.rawArgument() ?? '';
    const closing = `\\end{${name}}`;
    if (MATH_ENVIRONMENTS.has(name)) {
      const body = this.rawUntil(closing);
      if (name === 'math') return math(body, false);
      if (name === 'displaymath' || name.startsWith('equation')) return math(body, true);
      // KaTeX knows the aligned forms inside display mathematics
      const inner = name.replace('*', '').replace('align', 'aligned').replace('gather', 'gathered');
      return math(`\\begin{${inner}}${body}\\end{${inner}}`, true);
    }
    if (name === 'verbatim' || name === 'lstlisting' || name === 'tabular' || name === 'tabular*') {
      // shown as written: a table in LaTeX reads better raw than half converted
      return `</p><pre class="markup__raw">${escapeHtml(this.rawUntil(closing).trim())}</pre><p>`;
    }
    const title = this.optional();
    const body = new Parser(this.rawUntil(closing));
    if (name === 'quote' || name === 'quotation') {
      return `</p><blockquote><p>${body.parse()}</p></blockquote><p>`;
    }
    if (name === 'itemize' || name === 'enumerate' || name === 'description') {
      const tag = name === 'enumerate' ? 'ol' : 'ul';
      return `</p><${tag}>${body.items()}</${tag}><p>`;
    }
    const safe = escapeHtml(name);
    const base = name.replace('*', '');
    if (base in THEOREM_LIKE) {
      const head = THEOREM_LIKE[base] + (title ? ` (${new Parser(title).parse()})` : '');
      return (
        `</p><div class="markup__env markup__env--${base}" data-env="${safe}">` +
        `<p><strong>${head}.</strong> ${body.parse()}</p></div><p>`
      );
    }
    // an unknown environment keeps its content, formatted
    return `</p><div class="markup__env" data-env="${safe}"><p>${body.parse()}</p></div><p>`;
  }

  /** The items of a list: everything between two \item commands is one item. */
  private items(): string {
    const parts = this.src.split(/\\item\b/).slice(1);
    return parts
      .map((part) => {
        const p = new Parser(part.replace(/^\s*\[([^\]]*)\]/, '\\textbf{$1} '));
        return `<li>${p.parse()}</li>`;
      })
      .join('');
  }
}

/** The HTML for a LaTeX text. */
export function renderLatex(source: string): string {
  const html = `<p>${new Parser(source).parse()}</p>`;
  // paragraphs that the block elements left empty
  return html.replace(/<p>\s*<\/p>/g, '');
}
