import { Marked } from 'marked';

/**
 * Formats a text in a markup language, for app-atomic-markup (DesignChoices OK-24).
 *
 * The result is an HTML string that Angular binds with [innerHTML]; Angular's sanitiser
 * then removes scripts, event handlers and javascript: links, also from the HTML that
 * Markdown produces. Plain text is escaped here and keeps its line breaks through the
 * CSS class `markup--text` (white-space: pre-wrap).
 */

export type MarkupFormat = 'MARKDOWN' | 'GFM' | 'HTML' | 'TEXT';

/** Names a modeller may use for each format, in upper case. */
const ALIASES: Record<string, MarkupFormat> = {
  MARKDOWN: 'MARKDOWN',
  MD: 'MARKDOWN',
  COMMONMARK: 'MARKDOWN',
  GFM: 'GFM',
  'GITHUB-MARKDOWN': 'GFM',
  GITHUB_MARKDOWN: 'GFM',
  HTML: 'HTML',
  TEXT: 'TEXT',
  PLAIN: 'TEXT',
  ASCII: 'TEXT',
};

const warned = new Set<string>();

/**
 * The format a name stands for. An empty name gives TEXT. An unknown name, such as RST,
 * also gives TEXT, with one console warning per name, so the text stays readable.
 */
export function markupFormat(name: unknown): MarkupFormat {
  const key = formatName(name).toUpperCase();
  if (key === '') return 'TEXT';
  const format = ALIASES[key];
  if (format) return format;
  if (!warned.has(key)) {
    warned.add(key);
    console.warn(
      `MARKUP: unknown format '${formatName(name)}'; the text is shown as plain text. ` +
        `Known formats: ${Object.keys(ALIASES).join(', ')}.`,
    );
  }
  return 'TEXT';
}

/**
 * The format name in a value from the API: a string for a scalar concept, an object with
 * `_id_` for an OBJECT concept, and an array when the format item is not univalent (the
 * first value counts).
 */
export function formatName(value: unknown): string {
  if (Array.isArray(value)) return value.length > 0 ? formatName(value[0]) : '';
  if (value !== null && typeof value === 'object' && '_id_' in value) {
    return String((value as { _id_: unknown })._id_);
  }
  return value === null || value === undefined ? '' : String(value).trim();
}

const markdown = new Marked({ gfm: false });
const githubMarkdown = new Marked({ gfm: true });

/** The HTML for a text in a format. */
export function renderMarkup(text: unknown, format: MarkupFormat): string {
  const source = text === null || text === undefined ? '' : String(text);
  switch (format) {
    case 'MARKDOWN':
      return markdown.parse(source, { async: false }) as string;
    case 'GFM':
      return githubMarkdown.parse(source, { async: false }) as string;
    case 'HTML':
      return source;
    case 'TEXT':
      return escapeHtml(source);
  }
}

function escapeHtml(text: string): string {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
