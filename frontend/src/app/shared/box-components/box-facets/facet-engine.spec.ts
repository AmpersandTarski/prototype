import {
  EMPTY_KEY,
  FacetField,
  chosenKinds,
  FacetKind,
  FacetState,
  decodeState,
  encodeState,
  evaluate,
  fieldsOf,
  kindOf,
  locateBox,
  paramName,
  rowText,
  selectFields,
  sortBuckets,
  toggleDate,
  valuesAt,
} from './facet-engine';

const item = (name: string, concept: string, sub?: any, isIdent = false) => ({
  name,
  label: name,
  type: 'ObjExpression',
  expr: { tgtConceptName: concept, isUni: false, isIdent },
  subinterfaces: sub ?? null,
});

const interfaces = [
  {
    name: 'Issues',
    ifcObject: {
      subinterfaces: {
        ifcObjects: [
          item('Project', 'Project', {
            ifcObjects: [item('Owner', 'Person'), item('Secret', 'Pwd')],
          }),
          item('Status', 'Status'),
          item('Labels', 'Label'),
          item('Date', 'Day'),
          item('Size', 'Int'),
          item('Done', 'Issue'),
          item('Text', 'Big'),
          item('Ref', 'Project', {
            refSubInterfaceName: 'ProjectBox',
            refIsLinkTo: false,
          }),
          item('Link', 'Project', {
            refSubInterfaceName: 'ProjectBox',
            refIsLinkTo: true,
          }),
          item(
            'Issue',
            'Issue',
            { refSubInterfaceName: 'IssueDetail', refIsLinkTo: true },
            true,
          ),
          item('One', 'ONE'),
        ],
      },
    },
  },
  {
    name: 'ProjectBox',
    ifcObject: { subinterfaces: { ifcObjects: [item('Owner', 'Person')] } },
  },
];

const types = new Map<string, string>([
  ['Project', 'OBJECT'],
  ['Person', 'ALPHANUMERIC'],
  ['Pwd', 'PASSWORD'],
  ['Status', 'ALPHANUMERIC'],
  ['Label', 'ALPHANUMERIC'],
  ['Day', 'DATE'],
  ['Int', 'INTEGER'],
  ['Issue', 'OBJECT'],
  ['Big', 'BIGALPHANUMERIC'],
]);

const rows = [
  {
    _id_: 'i1',
    Project: { _id_: 'p1', _label_: 'Alpha', Owner: 'ann' },
    Status: 'open',
    Labels: ['bug', 'ui'],
    Date: '2026-09-01',
    Size: 3,
    Done: true,
    Text: 'The login fails',
  },
  {
    _id_: 'i2',
    Project: { _id_: 'p2', _label_: 'Beta', Owner: 'bob' },
    Status: 'closed',
    Labels: ['bug'],
    Date: '2026-08-15',
    Size: 5,
    Done: false,
    Text: 'Slow page',
  },
  {
    _id_: 'i3',
    Project: { _id_: 'p1', _label_: 'Alpha', Owner: 'ann' },
    Status: null,
    Labels: [],
    Date: '2025-12-31',
    Size: 8,
    Done: false,
    Text: 'Typo',
  },
];

function allFields(): FacetField[] {
  const box = locateBox(interfaces, 'Issues', []);
  return selectFields(fieldsOf(box, interfaces, types), undefined, undefined);
}

function flat(fields: FacetField[]): FacetField[] {
  return fields.flatMap((f) => [f, ...flat(f.children)]);
}

function kindsOf(fields: FacetField[]): Map<string, FacetKind> {
  return new Map(fields.map((f) => [f.id, kindOf(f, rows)]));
}

const noState = (): FacetState => ({ search: '', selections: new Map() });

describe('facet-engine', () => {
  describe('schema', () => {
    it('reads the TType of every item from its target concept', () => {
      const f = allFields();
      expect(f.map((x) => [x.label, x.ttype])).toEqual([
        ['Project', 'OBJECT'],
        ['Status', 'ALPHANUMERIC'],
        ['Labels', 'ALPHANUMERIC'],
        ['Date', 'DATE'],
        ['Size', 'INTEGER'],
        ['Done', 'OBJECT'],
        ['Text', 'BIGALPHANUMERIC'],
        ['Ref', 'OBJECT'],
        ['Link', 'OBJECT'],
        ['Issue', 'OBJECT'],
      ]);
    });

    it('gives an OBJECT item with a sub-box its sub-items as child facets, recursively', () => {
      const project = allFields()[0];
      expect(project.children.map((c) => c.label)).toEqual(['Project › Owner']);
      expect(project.children[0].path).toEqual(['Project', 'Owner']);
    });

    it('drops a PASSWORD sub-item, and follows an inlined interface reference but not a LINKTO', () => {
      const f = allFields();
      expect(f[0].children.some((c) => c.ttype === 'PASSWORD')).toBe(false);
      expect(f[7].children.map((c) => c.label)).toEqual(['Ref › Owner']);
      expect(f[8].children).toEqual([]);
    });

    it('selects facets by label, with A.B for a sub-item, and warns about unknown or non-facet names', () => {
      const warnings: string[] = [];
      const all = fieldsOf(
        locateBox(interfaces, 'Issues', []),
        interfaces,
        types,
      );
      const chosen = selectFields(
        all,
        'Status, Project.Owner, Nope, Project.Secret',
        'Labels',
        (w) => warnings.push(w),
      );
      expect(chosen.map((c) => c.label)).toEqual([
        'Status',
        'Project › Owner',
        'Labels',
      ]);
      expect(warnings).toEqual([
        "BOX<FACETS>: no box item 'Nope'",
        "BOX<FACETS>: 'Project › Secret' has type PASSWORD, which is never a facet",
      ]);
    });

    it('finds a nested box, skipping the atom ids in a resource path', () => {
      const node = locateBox(interfaces, 'Issues', ['i1', 'Project', 'p1']);
      expect(node.name).toBe('Project');
    });
  });

  describe('values', () => {
    it('reads scalars, objects by id with their label, and lists', () => {
      expect(valuesAt(rows[0], ['Status'], 'ALPHANUMERIC')).toEqual([
        { key: 'open', label: 'open' },
      ]);
      expect(valuesAt(rows[0], ['Project'], 'OBJECT')).toEqual([
        { key: 'p1', label: 'Alpha' },
      ]);
      expect(
        valuesAt(rows[0], ['Labels'], 'ALPHANUMERIC').map((v) => v.key),
      ).toEqual(['bug', 'ui']);
      expect(valuesAt(rows[2], ['Status'], 'ALPHANUMERIC')).toEqual([]);
      expect(valuesAt(rows[1], ['Project', 'Owner'], 'ALPHANUMERIC')).toEqual([
        { key: 'bob', label: 'bob' },
      ]);
    });

    it('chooses the kind of facet from the TType', () => {
      const k = kindsOf(flat(allFields()));
      expect(k.get('Status')).toBe('values');
      expect(k.get('Date')).toBe('date');
      expect(k.get('Size')).toBe('values'); // three distinct numbers: a value list
      expect(k.get('Done')).toBe('values'); // booleans (a PROP relation)
      expect(k.get('Text')).toBe('text');
      expect(k.get('Issue')).toBe('text'); // the identity column: searched, not listed
    });

    it('labels an object without a label by its id, and sorts numbers by value', () => {
      expect(
        valuesAt({ P: { _id_: 'T%2013', _label_: '' } }, ['P'], 'OBJECT'),
      ).toEqual([{ key: 'T%2013', label: 'T 13' }]);
      const b = (key: string, count: number) => ({ key, label: key, count });
      expect(
        sortBuckets([b('10', 1), b(EMPTY_KEY, 5), b('2', 3)], 'INTEGER').map(
          (x) => x.key,
        ),
      ).toEqual(['2', '10', EMPTY_KEY]);
      expect(
        sortBuckets([b('a', 1), b('b', 3)], 'ALPHANUMERIC').map((x) => x.key),
      ).toEqual(['b', 'a']);
    });

    it('turns many distinct numbers into a range, and a nearly unique label into a text field', () => {
      const many = Array.from({ length: 50 }, (_, i) => ({
        n: i,
        id: `row ${i}`,
      }));
      const num: FacetField = {
        id: 'n',
        path: ['n'],
        labelPath: ['n'],
        label: 'n',
        ttype: 'INTEGER',
        isIdent: false,
        children: [],
      };
      const txt: FacetField = {
        id: 'id',
        path: ['id'],
        labelPath: ['id'],
        label: 'id',
        ttype: 'ALPHANUMERIC',
        isIdent: false,
        children: [],
      };
      expect(kindOf(num, many)).toBe('range');
      expect(kindOf(txt, many)).toBe('text');
    });

    it('infers the kind from the values when the TType is unknown', () => {
      const f: FacetField = {
        id: 'd',
        path: ['d'],
        labelPath: ['d'],
        label: 'd',
        ttype: 'UNKNOWN',
        isIdent: false,
        children: [],
      };
      expect(
        kindOf(f, [{ d: '2026-01-02' }, { d: '2026-02-03T10:00:00+00:00' }]),
      ).toBe('date');
    });
  });

  describe('evaluate', () => {
    const fields = flat(allFields());
    const kinds = kindsOf(fields);

    it('without a selection passes every row and counts every value', () => {
      const out = evaluate(rows, fields, kinds, noState());
      expect(out.rows.length).toBe(3);
      expect(sortBuckets(out.buckets.get('Status')!)).toEqual([
        { key: 'closed', label: 'closed', count: 1 },
        { key: 'open', label: 'open', count: 1 },
        { key: EMPTY_KEY, label: '(empty)', count: 1 },
      ]);
      expect(
        out.buckets.get('Labels')!.find((b) => b.key === 'bug')!.count,
      ).toBe(2);
    });

    it('ORs within a facet, ANDs across facets, and counts a facet without its own selection', () => {
      const state = noState();
      state.selections.set('Status', {
        kind: 'values',
        keys: new Set(['open', EMPTY_KEY]),
      });
      state.selections.set('Project', {
        kind: 'values',
        keys: new Set(['p1']),
      });
      const out = evaluate(rows, fields, kinds, state);
      expect(out.rows.map((r) => r._id_)).toEqual(['i1', 'i3']);
      // Status counts ignore the Status selection but respect the Project selection:
      const status = new Map(
        out.buckets.get('Status')!.map((b) => [b.key, b.count]),
      );
      expect(status.get('open')).toBe(1);
      expect(status.get(EMPTY_KEY)).toBe(1);
      expect(status.has('closed')).toBe(false);
      // Project counts respect the Status selection:
      expect(
        out.buckets.get('Project')!.find((b) => b.key === 'p2'),
      ).toBeUndefined();
    });

    it('filters on a child facet of an OBJECT item', () => {
      const state = noState();
      state.selections.set('Project/Owner', {
        kind: 'values',
        keys: new Set(['bob']),
      });
      expect(
        evaluate(rows, fields, kinds, state).rows.map((r) => r._id_),
      ).toEqual(['i2']);
    });

    it('matches a multi-valued item when any of its values is chosen', () => {
      const state = noState();
      state.selections.set('Labels', { kind: 'values', keys: new Set(['ui']) });
      expect(
        evaluate(rows, fields, kinds, state).rows.map((r) => r._id_),
      ).toEqual(['i1']);
    });

    it('filters dates on a year, month or day prefix, and counts each level', () => {
      const state = noState();
      state.selections.set('Date', {
        kind: 'date',
        prefixes: new Set(['2026']),
      });
      const out = evaluate(rows, fields, kinds, state);
      expect(out.rows.map((r) => r._id_)).toEqual(['i1', 'i2']);
      const keys = out.buckets
        .get('Date')!
        .map((b) => b.key)
        .sort();
      expect(keys).toEqual([
        '2025',
        '2025-12',
        '2025-12-31',
        '2026',
        '2026-08',
        '2026-08-15',
        '2026-09',
        '2026-09-01',
      ]);
    });

    it('filters a range and a text field, and searches all text of a row', () => {
      const state = noState();
      state.selections.set('Size', { kind: 'range', min: 4 });
      expect(
        evaluate(rows, fields, kinds, state).rows.map((r) => r._id_),
      ).toEqual(['i2', 'i3']);
      state.selections.set('Text', { kind: 'text', text: 'TYPO' });
      expect(
        evaluate(rows, fields, kinds, state).rows.map((r) => r._id_),
      ).toEqual(['i3']);
      const search = { search: 'beta', selections: new Map() };
      expect(
        evaluate(rows, fields, kinds, search).rows.map((r) => r._id_),
      ).toEqual(['i2']);
    });
  });

  describe('edge cases', () => {
    it('never makes an item on the singleton ONE a facet, although concepts.json calls it OBJECT', () => {
      const all = fieldsOf(
        locateBox(interfaces, 'Issues', []),
        interfaces,
        new Map([...types, ['ONE', 'OBJECT']]),
      );
      expect(all.find((f) => f.label === 'One')?.ttype).toBe('TYPEOFONE');
      expect(allFields().some((f) => f.label === 'One')).toBe(false);
    });

    it('shows a named sub-item once when its parent item is named too', () => {
      const all = fieldsOf(
        locateBox(interfaces, 'Issues', []),
        interfaces,
        types,
      );
      const chosen = selectFields(all, 'Project, Project.Owner', undefined);
      expect(chosen.map((f) => f.label)).toEqual(['Project']);
      expect(chosen[0].children.map((c) => c.label)).toEqual([
        'Project › Owner',
      ]);
    });

    it('names a URL parameter by item name where a label contains a period', () => {
      const f: FacetField = {
        id: 'v_46_1/x',
        path: ['v_46_1', 'x'],
        labelPath: ['v.1', 'x'],
        label: 'v.1 › x',
        ttype: 'ALPHANUMERIC',
        isIdent: false,
        children: [],
      };
      expect(paramName(f, '')).toBe('f.v_46_1.x');
    });

    it('keeps a range bookmark on a number facet that became a value list, and vice versa', () => {
      const fields = flat(allFields());
      const kinds = kindsOf(fields); // Size: three values, so a value list
      const range = decodeState(
        fields,
        kinds,
        (n) => (n === 'f.Size' ? ['4..'] : []),
        '',
      );
      expect(range.selections.get('Size')).toEqual({
        kind: 'range',
        min: 4,
        max: undefined,
      });
      kinds.set('Size', 'range');
      const list = decodeState(
        fields,
        kinds,
        (n) => (n === 'f.Size' ? ['3', '5'] : []),
        '',
      );
      expect(list.selections.get('Size')).toEqual({
        kind: 'values',
        keys: new Set(['3', '5']),
      });
    });

    it('keeps a text bookmark on a facet that became a value list, and a range on an untyped number', () => {
      const fields = flat(allFields());
      const kinds = kindsOf(fields); // Status: a value list
      const params = encodeState(
        fields,
        {
          search: '',
          selections: new Map([['Status', { kind: 'text', text: 'pen' }]]),
        },
        '',
      );
      expect(params['f.Status']).toEqual(['~pen']);
      const back = decodeState(fields, kinds, (n) => params[n] ?? [], '');
      expect(back.selections.get('Status')).toEqual({
        kind: 'text',
        text: 'pen',
      });
      const untyped: FacetField = {
        ...fields[0],
        id: 'H',
        path: ['H'],
        labelPath: ['H'],
        ttype: 'UNKNOWN',
        children: [],
      };
      const r = decodeState(
        [untyped],
        new Map([['H', 'values']]),
        (n) => (n === 'f.H' ? ['5..'] : []),
        '',
      );
      expect(r.selections.get('H')).toEqual({
        kind: 'range',
        min: 5,
        max: undefined,
      });
    });

    it('lets facetKind choose the kind, and files a text that starts with a date under that date', () => {
      const all = fieldsOf(
        locateBox(interfaces, 'Issues', []),
        interfaces,
        types,
      );
      const warnings: string[] = [];
      const kinds = chosenKinds(
        all,
        'Status=date, Text=list, Project.Owner=range, Nope=list, Size=wrong',
        (w) => warnings.push(w),
      );
      expect([...kinds]).toEqual([
        ['Status', 'date'],
        ['Text', 'values'],
        ['Project/Owner', 'range'],
      ]);
      expect(warnings.length).toBe(2);
      const state = noState();
      state.selections.set('Status', {
        kind: 'date',
        prefixes: new Set(['2026-08']),
      });
      const status: FacetField = { ...allFields()[1] };
      const dated = [
        { Status: '2026-08-10, revised 2026-09-17' },
        { Status: '2026-09-01' },
        { Status: 'open' },
      ];
      const out = evaluate(
        dated,
        [status],
        new Map([['Status', 'date']]),
        state,
      );
      expect(out.rows).toEqual([dated[0]]);
      const buckets = new Map(
        out.buckets.get('Status')!.map((b) => [b.key, b.count]),
      );
      expect(buckets.get(EMPTY_KEY)).toBe(1); // "open" starts with no date: (empty)
      state.selections.set('Status', {
        kind: 'date',
        prefixes: new Set([EMPTY_KEY]),
      });
      expect(
        evaluate(dated, [status], new Map([['Status', 'date']]), state).rows,
      ).toEqual([dated[2]]);
    });

    it('keeps the kind of an untyped item as before: only whole dates and JSON numbers count', () => {
      const f: FacetField = {
        id: 'u',
        path: ['u'],
        labelPath: ['u'],
        label: 'u',
        ttype: 'UNKNOWN',
        isIdent: false,
        children: [],
      };
      expect(
        kindOf(f, [{ u: '2026-08-10, revised' }, { u: '2026-08-11' }]),
      ).toBe('values');
      const codes = Array.from({ length: 45 }, (_, i) => ({
        u: String(i).padStart(4, '0'),
      }));
      expect(kindOf(f, codes)).toBe('text');
      const numbers = Array.from({ length: 20 }, (_, i) => ({ u: i }));
      expect(kindOf(f, numbers)).toBe('range');
    });

    it('searches the current text of a row after an edit in place', () => {
      const row = { Status: 'open' };
      expect(rowText(row)).toContain('open');
      row.Status = 'closed';
      expect(rowText(row)).toContain('closed');
    });

    it('evaluates 5 000 rows and 10 facets well within a frame (logs the time)', () => {
      const many = Array.from({ length: 5000 }, (_, i) => ({
        Status: ['open', 'closed', 'merged'][i % 3],
        Labels: ['a', 'b', 'c', 'd'].slice(0, i % 4),
        Date: `202${i % 6}-0${1 + (i % 9)}-1${i % 9}`,
        Size: i % 97,
        Project: {
          _id_: 'p' + (i % 40),
          _label_: 'P' + (i % 40),
          Owner: 'o' + (i % 7),
        },
        Text: 'text ' + i,
      }));
      const fields = flat(allFields()).slice(0, 10);
      const kinds = new Map(fields.map((f) => [f.id, kindOf(f, many)]));
      const state = noState();
      state.selections.set('Status', {
        kind: 'values',
        keys: new Set(['open']),
      });
      state.selections.set('Date', {
        kind: 'date',
        prefixes: new Set(['2023']),
      });
      const t = performance.now();
      for (let i = 0; i < 10; i++) evaluate(many, fields, kinds, state);
      const ms = (performance.now() - t) / 10;
      console.log(
        `facet-engine: 5000 rows, ${fields.length} facets: ${ms.toFixed(
          1,
        )} ms per evaluate`,
      );
      expect(ms).toBeLessThan(100);
    });
  });

  describe('date tree', () => {
    it('replaces a year by a month chosen inside it, and drops the months when the year is chosen again', () => {
      let p = toggleDate(new Set(), '2026');
      p = toggleDate(p, '2026-09');
      expect([...p]).toEqual(['2026-09']);
      p = toggleDate(p, '2026');
      expect([...p]).toEqual(['2026']);
      p = toggleDate(p, '2026');
      expect([...p]).toEqual([]);
    });
  });

  describe('URL state', () => {
    it('round-trips through readable query parameters', () => {
      const fields = flat(allFields());
      const kinds = kindsOf(fields);
      const state: FacetState = {
        search: 'login',
        selections: new Map<string, any>([
          ['Status', { kind: 'values', keys: new Set(['open', EMPTY_KEY]) }],
          ['Project/Owner', { kind: 'values', keys: new Set(['ann']) }],
          ['Date', { kind: 'date', prefixes: new Set(['2026-09']) }],
          ['Text', { kind: 'text', text: 'fail' }],
        ]),
      };
      const params = encodeState(fields, state, '');
      expect(params['q']).toEqual(['login']);
      expect(params['f.Status']).toEqual(['open', EMPTY_KEY]);
      expect(params['f.Project.Owner']).toEqual(['ann']);
      expect(params['f.Labels']).toBeNull();
      const back = decodeState(fields, kinds, (n) => params[n] ?? [], '');
      expect(back).toEqual(state);
    });
  });
});
