import katex from 'katex';

/**
 * Formats a LaTeX text as HTML, for the format LATEX of app-atomic-markup (DesignChoices
 * OK-25).
 *
 * A text in a database is a fragment, not a document: a claim with a quote, a definition,
 * some formulas and the author's own macros. So this converter does not try to be TeX. It
 * formats the structure it knows (headings, quotes, theorem-like environments, lists,
 * emphasis, code) and leaves everything else readable: an unknown command shows its
 * argument, and an unknown command without one shows its name. No text disappears.
 *
 * The work is in two steps, so that Angular's sanitiser stays in charge of the HTML:
 * 1. `renderLatex` gives HTML without mathematics. A formula is a `span` with the class
 *    `markup__math` that holds its TeX source as text. Angular binds and sanitises this HTML
 *    like that of any other format.
 * 2. `renderMath` runs in the page, on the element that holds that HTML. KaTeX builds the
 *    MathML of every formula as DOM nodes, from the text of its span; no HTML string from the
 *    source passes the sanitiser by.
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

/** Environments shown as written: a table in LaTeX reads better raw than half converted. */
const RAW_ENVIRONMENTS = new Set(['verbatim', 'lstlisting', 'tabular', 'tabular*']);

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
/** Escaped characters that TeX uses as a thin space. */
const SPACES = new Set([',', ';', ' ', '!']);

export function escapeHtml(text: string): string {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/** A formula as a span that holds its source; `renderMath` turns it into MathML. */
function mathSpan(source: string, display: boolean): string {
  const cls = display ? 'markup__math markup__math--display' : 'markup__math';
  return `<span class="${cls}">${escapeHtml(source)}</span>`;
}

/** The plain characters of running text: escaped, with TeX's typography. */
function plain(text: string): string {
  return escapeHtml(text)
    .replace(/---/g, '—')
    .replace(/--/g, '–')
    .replace(/``/g, '“')
    .replace(/&#39;&#39;/g, '”')
    .replace(/~/g, '\u00a0')
    .replace(/\n[ \t]*\n\s*/g, '</p><p>');
}

const isLetter = (c: string) => /[a-zA-Z]/.test(c);

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
      const special = this.special();
      if (special === null) {
        text += this.src[this.i++];
      } else {
        flush();
        out += special;
      }
    }
    flush();
    return out;
  }

  peek(): string {
    return this.src[this.i] ?? '';
  }

  /** What a character with a meaning in TeX gives; null for an ordinary character. */
  private special(): string | null {
    switch (this.peek()) {
      case '%': {
        // a comment runs to the end of the line
        const end = this.src.indexOf('\n', this.i);
        this.i = end < 0 ? this.src.length : end + 1;
        return '';
      }
      case '$':
        return this.dollarMath();
      case '{': {
        this.i++;
        const group = this.parse((p) => p.peek() === '}');
        this.i++; // the closing brace
        return group;
      }
      case '}':
        this.i++; // a stray closing brace shows nothing
        return '';
      case '\\':
        return this.backslash();
      default:
        return null;
    }
  }

  private dollarMath(): string {
    const display = this.src.startsWith('$$', this.i);
    const open = display ? '$$' : '$';
    const from = this.i + open.length;
    let end = this.src.indexOf(open, from);
    while (end > 0 && this.src[end - 1] === '\\') end = this.src.indexOf(open, end + 1);
    if (end < 0) {
      // no closing dollar: the sign is text
      this.i += open.length;
      return escapeHtml(open);
    }
    this.i = end + open.length;
    return mathSpan(this.src.slice(from, end), display);
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

  /** An argument in braces, formatted; the empty string when there is none. */
  private argument(): string {
    const raw = this.rawArgument();
    return raw === null ? '' : new Parser(raw).parse();
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

  /** Everything that starts with a backslash. */
  private backslash(): string {
    this.i++; // the backslash
    const c = this.peek();
    if (isLetter(c)) return this.command(this.name());
    this.i++;
    if (c === '(') return mathSpan(this.rawUntil(String.raw`\)`), false);
    if (c === '[') return mathSpan(this.rawUntil(String.raw`\]`), true);
    if (c === '\\') {
      this.optional(); // \\[2pt]
      return '<br>';
    }
    // an escaped character: \% \& \_ \# \$ \{ \} and the thin spaces
    return SPACES.has(c) ? ' ' : escapeHtml(c);
  }

  /** The name of a command; TeX swallows a star and the spaces after it. */
  private name(): string {
    let name = '';
    while (isLetter(this.peek())) name += this.src[this.i++];
    if (this.peek() === '*') this.i++;
    while (this.peek() === ' ') this.i++;
    return name;
  }

  private command(name: string): string {
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
      return `</p><${tag}>${this.argument()}</${tag}><p>`;
    }
    if (name in WRAPPERS) {
      const tag = WRAPPERS[name];
      const cls = name === 'textsc' ? ' class="markup__smallcaps"' : '';
      return `<${tag}${cls}>${this.argument()}</${tag}>`;
    }
    return this.reference(name) ?? this.unknown(name);
  }

  /** Citations, references, footnotes and addresses; null for another command. */
  private reference(name: string): string | null {
    if (CITATIONS.has(name)) {
      this.optional();
      this.optional();
      const keys = (this.rawArgument() ?? '').split(',').map((k) => escapeHtml(k.trim()));
      return `<span class="markup__cite">[${keys.join(', ')}]</span>`;
    }
    if (REFERENCES.has(name)) {
      return `<span class="markup__ref">${escapeHtml(this.rawArgument() ?? '')}</span>`;
    }
    switch (name) {
      case 'footnote':
        return ` <small class="markup__footnote">(${this.argument()})</small>`;
      case 'url':
        return `<code>${escapeHtml(this.rawArgument() ?? '')}</code>`;
      case 'href':
        this.rawArgument(); // the address; the text is what the reader sees
        return this.argument();
      case 'verb':
        return `<code>${escapeHtml(this.rawUntil(this.src[this.i++]))}</code>`;
      default:
        return null;
    }
  }

  /**
   * An unknown command, such as a macro of the author. Its arguments stay readable; the name
   * is kept in the class, so that a project can style its own macros.
   */
  private unknown(name: string): string {
    const args: string[] = [];
    while (this.peek() === '{') args.push(this.argument());
    const safe = escapeHtml(name);
    if (args.length === 0) return `<code class="markup__cmd">\\${safe}</code>`;
    return `<span class="markup__cmd markup__cmd--${safe}">${args.join(' ')}</span>`;
  }

  private environment(): string {
    const name = this.rawArgument() ?? '';
    const closing = `\\end{${name}}`;
    if (MATH_ENVIRONMENTS.has(name)) return this.mathEnvironment(name, this.rawUntil(closing));
    if (RAW_ENVIRONMENTS.has(name)) {
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
    const base = name.replace('*', '');
    // An environment name reaches the class only when it is a plain word.
    const cls = /^[a-zA-Z]+$/.test(base) ? `markup__env markup__env--${base}` : 'markup__env';
    const titled = title ? ` (${new Parser(title).parse()})` : '';
    const head = base in THEOREM_LIKE ? `<strong>${THEOREM_LIKE[base]}${titled}.</strong> ` : '';
    // an unknown environment keeps its content, formatted
    return `</p><div class="${cls}"><p>${head}${body.parse()}</p></div><p>`;
  }

  private mathEnvironment(name: string, body: string): string {
    if (name === 'math') return mathSpan(body, false);
    if (name === 'displaymath' || name.startsWith('equation')) return mathSpan(body, true);
    // KaTeX knows the aligned forms inside display mathematics
    const inner = name.replace('*', '').replace('align', 'aligned').replace('gather', 'gathered');
    return mathSpan(`\\begin{${inner}}${body}\\end{${inner}}`, true);
  }

  /** The items of a list: everything between two \item commands is one item. */
  items(): string {
    return this.src
      .split(/\\item\b/)
      .slice(1)
      .map((part) => {
        const labelled = part.replace(/^\s*\[([^\]]*)\]/, String.raw`\textbf{$1} `);
        return `<li>${new Parser(labelled).parse()}</li>`;
      })
      .join('');
  }
}

/** The HTML for a LaTeX text, with every formula as a `markup__math` span (see the top). */
export function renderLatex(source: string): string {
  const html = `<p>${new Parser(source).parse()}</p>`;
  // paragraphs that the block elements left empty
  return html.replace(/<p>\s*<\/p>/g, '');
}

/**
 * The definition for a macro that KaTeX does not know. With an argument in braces the macro
 * carries text, such as a hint in a calculational proof (`\why{multiply by $c > 0$}`);
 * without one it names an operator (`\softmax`).
 */
function fallbackMacro(name: string, source: string): string {
  const takesArgument = new RegExp(String.raw`\\${name}\s*\{`).test(source);
  return takesArgument ? String.raw`\text{#1}` : String.raw`\operatorname{${name}}`;
}

/**
 * Renders one formula into `target` as MathML. An unknown macro gets a definition on the
 * spot (see `fallbackMacro`), so one unknown macro does not turn the whole formula into
 * source text. Returns false when the formula still does not parse; `target` then keeps
 * showing its source.
 */
export function renderFormula(source: string, target: HTMLElement, display: boolean): boolean {
  const macros: Record<string, string> = {};
  // KaTeX empties the element it renders into before it parses, so it builds in a loose
  // element; `target` changes only when the formula parsed.
  const built = target.ownerDocument.createElement('span');
  for (let attempt = 0; attempt < 12; attempt++) {
    try {
      katex.render(source, built, {
        displayMode: display,
        output: 'mathml',
        throwOnError: true,
        trust: false,
        strict: 'ignore',
        macros: { ...macros },
      });
      target.replaceChildren(...Array.from(built.childNodes));
      return true;
    } catch (e) {
      const unknown = /Undefined control sequence: \\([a-zA-Z]+)/.exec(String((e as Error)?.message ?? e));
      const macro = unknown ? `\\${unknown[1]}` : '';
      if (!unknown || macro in macros) return false;
      macros[macro] = fallbackMacro(unknown[1], source);
    }
  }
  return false;
}

/**
 * Turns every formula span under `root` into MathML, once. A formula that does not parse
 * keeps its source and gets the class `markup__math-source`.
 */
export function renderMath(root: HTMLElement): void {
  const spans = root.querySelectorAll<HTMLElement>('.markup__math:not(.markup__math--done)');
  for (const span of Array.from(spans)) {
    const display = span.classList.contains('markup__math--display');
    const ok = renderFormula(span.textContent ?? '', span, display);
    span.classList.add('markup__math--done');
    if (!ok) span.classList.add('markup__math-source');
  }
}
