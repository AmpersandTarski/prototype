import { mergeEdit, textToStore } from './markdown-merge';

describe('textToStore', () => {
  const same = () => true;
  const different = () => false;

  it('stores nothing when the document did not change', () => {
    expect(textToStore('A\\.', 'A.\n', 'A.\n', same)).toBeNull();
  });

  it('stores the merge when it means what the editor shows', () => {
    expect(textToStore('A\\.\n\nB', 'A.\n\nB\n', 'A.\n\nB, more\n', same)).toBe(
      'A\\.\n\nB, more',
    );
  });

  it("stores the editor's Markdown when the merge means something else", () => {
    expect(
      textToStore('A\\.\n\nB', 'A.\n\nB\n', 'A.\n\nB, more\n', different),
    ).toBe('A.\n\nB, more\n');
  });

  it('compares the merge with the edited text', () => {
    const calls: string[][] = [];
    textToStore(
      'A\\.\n\nB',
      'A.\n\nB\n',
      'A.\n\nC\n',
      (a, b) => (calls.push([a, b]), true),
    );
    expect(calls).toEqual([['A\\.\n\nC', 'A.\n\nC\n']]);
  });
});

describe('mergeEdit', () => {
  // What the editor makes of a stored text without anyone typing (measured on Milkdown 7.23).
  const stored =
    'Een regel met -toets\\.(py|sh)$ erin.\n\n- een\n- twee\n\nSlot met snake\\_case.';
  const before =
    'Een regel met -toets.(py|sh)$ erin.\n\n* een\n* twee\n\nSlot met snake_case.\n';

  it('keeps the stored text when nothing was typed', () => {
    expect(mergeEdit(stored, before, before)).toBe(stored);
  });

  it('takes an edit and keeps every untouched line as stored', () => {
    const after = before.replace('* twee', '* twee\n* drie');
    const merged = mergeEdit(stored, before, after);
    expect(merged).toContain('-toets\\.(py|sh)$');
    expect(merged).toContain('snake\\_case');
    expect(merged).toContain('* drie');
    expect(merged.split('\n')[0]).toBe(stored.split('\n')[0]);
  });

  it('gives the editor spelling only to the lines that were edited', () => {
    const after = before.replace(
      'Slot met snake_case.',
      'Slot met snake_case en meer.',
    );
    const merged = mergeEdit(stored, before, after);
    expect(merged).toContain('-toets\\.(py|sh)$');
    expect(merged).toContain('- een\n- twee');
    expect(merged).toContain('Slot met snake_case en meer.');
  });

  it('keeps one sentence per line in a paragraph', () => {
    const s = 'Eerste zin.\nTweede zin.\nDerde\\_zin.';
    const b = 'Eerste zin.\nTweede zin.\nDerde_zin.\n';
    const a = 'Eerste zin.\nTweede zin, langer.\nDerde_zin.\n';
    expect(mergeEdit(s, b, a)).toBe(
      'Eerste zin.\nTweede zin, langer.\nDerde\\_zin.',
    );
  });

  it('keeps Windows line ends', () => {
    const s = 'een\r\ntwee\r\n';
    const b = 'een\ntwee\n';
    const a = 'een\ntwee, nieuw\n';
    expect(mergeEdit(s, b, a)).toBe('een\r\ntwee, nieuw\r\n');
  });

  it('fills an empty text', () => {
    expect(mergeEdit('', '', 'Nieuw\n')).toBe('Nieuw');
  });

  it('empties a text', () => {
    expect(mergeEdit('Weg\\.\n', 'Weg.\n', '')).toBe('');
  });

  it('keeps an untouched neighbour of an edited line as stored', () => {
    const s = 'Kop\n\n- een \\_x\n- twee\n- drie \\.y';
    const b = 'Kop\n\n* een _x\n* twee\n* drie .y\n';
    const a = 'Kop\n\n* een _x\n* twee, nieuw\n* drie .y\n';
    expect(mergeEdit(s, b, a)).toBe(
      'Kop\n\n- een \\_x\n* twee, nieuw\n- drie \\.y',
    );
  });

  it('takes the whole conflict when stored and editor lines do not pair up', () => {
    const s = 'Een\nalinea\nover drie regels';
    const b = 'Een alinea over drie regels\n';
    const a = 'Een alinea over vier regels\n';
    expect(mergeEdit(s, b, a)).toBe('Een alinea over vier regels');
  });
});
