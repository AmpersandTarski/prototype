import { EventEmitter } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, Router, convertToParamMap } from '@angular/router';
import { InterfacesJsonService } from '../../services/interfaces-json.service';
import { BoxFacetsComponent } from './box-facets.component';

/**
 * The component around the engine: which rows the table gets while rows are
 * created, deleted and edited under an active selection.
 */
const interfaces = [
  {
    name: 'Tickets',
    ifcObject: {
      subinterfaces: {
        ifcObjects: [
          {
            name: 'Status',
            label: 'Status',
            type: 'ObjExpression',
            expr: { tgtConceptName: 'Status', isIdent: false },
            subinterfaces: null,
          },
        ],
      },
    },
  },
];

const row = (id: string, status: string) => ({
  _id_: id,
  _label_: id,
  _path_: `x/${id}`,
  _ifcs_: [],
  Status: status,
});

async function setup(query: Record<string, string | string[]> = {}) {
  const navigate = jest.fn();
  TestBed.configureTestingModule({
    providers: [
      { provide: Router, useValue: { navigate } },
      {
        provide: ActivatedRoute,
        useValue: { snapshot: { queryParamMap: convertToParamMap(query) } },
      },
      {
        provide: InterfacesJsonService,
        useValue: {
          getInterfaces: () => interfaces,
          conceptTypes: () =>
            Promise.resolve(new Map([['Status', 'ALPHANUMERIC']])),
        },
      },
    ],
  });
  const c = TestBed.runInInjectionContext(() => new BoxFacetsComponent());
  const data = [row('A', 'open'), row('B', 'closed'), row('C', 'open')];
  const patched = new EventEmitter<void>();
  c.data = data;
  c.propertyName = 'Tickets';
  c.isRootBox = true;
  c.resource = {
    _id_: '1',
    _label_: '1',
    _path_: 'resource/SESSION/1',
    _ifcs_: [],
    data,
  } as any;
  c.interfaceComponent = { interfaceName: 'Tickets', patched } as any;
  c.ngDoCheck();
  await c.ngOnInit();
  c.ngDoCheck();
  return { c, data, patched, navigate };
}

const ids = (rows: any[]) => rows.map((r) => r._id_);

describe('BoxFacetsComponent', () => {
  it('shows only the rows that pass the selection', async () => {
    const { c } = await setup({ 'f.Status': 'closed' });
    expect(ids(c.filtered)).toEqual(['B']);
    expect(c.total).toBe(3);
  });

  it('keeps a created row visible, without bringing back the rows the selection hides', async () => {
    const { c, data } = await setup({ 'f.Status': 'closed' });
    data.unshift(row('N', 'new')); // what createItem does
    c.ngDoCheck();
    expect(ids(c.filtered)).toEqual(['N', 'B']);
    c.toggleValue(c.tree[0], 'open'); // a new selection releases the pinned row
    expect(ids(c.filtered)).toEqual(['A', 'B', 'C']);
  });

  it('drops a deleted row from the table and from the counts', async () => {
    const { c, data } = await setup();
    data.splice(1, 1); // what deleteItem does on the table's data (the full rows)
    c.ngDoCheck();
    expect(ids(c.filtered)).toEqual(['A', 'C']);
    expect(c.total).toBe(2);
    expect(c.view.get('Status')!.values.map((b) => b.key)).toEqual(['open']);
  });

  it('refreshes after an edit that merges into a row in place', async () => {
    const { c, data, patched } = await setup({ 'f.Status': 'open' });
    data[0].Status = 'closed'; // what syncWithServer does after a patch
    patched.emit();
    expect(ids(c.filtered)).toEqual(['C']);
  });

  it('writes the selection to the URL', async () => {
    const { c, navigate } = await setup();
    c.toggleValue(c.tree[0], 'open');
    expect(navigate).toHaveBeenCalledWith(
      [],
      expect.objectContaining({
        queryParams: expect.objectContaining({ 'f.Status': ['open'] }),
      }),
    );
  });

  it('keeps a row created in an empty UNI box visible, although the array is new', async () => {
    const { c } = await setup({ 'f.Status': 'closed' });
    c.data = [undefined as any]; // [resource.X] while X is empty
    c.ngDoCheck();
    c.data = [row('N', 'new')]; // createItem sets X; the template makes a new array
    c.ngDoCheck();
    expect(ids(c.filtered)).toEqual(['N']);
  });

  it('pins nothing when a whole new set of rows arrives', async () => {
    const { c } = await setup({ 'f.Status': 'closed' });
    c.data = [row('X', 'open'), row('Y', 'closed')];
    c.ngDoCheck();
    expect(ids(c.filtered)).toEqual(['Y']);
  });

  it('takes the parameters of the new row when Angular reuses a nested box', async () => {
    const { c } = await setup();
    c.isRootBox = false;
    c.resource = { ...c.resource, _id_: 'red' };
    c.ngOnChanges({ resource: { firstChange: false } as any });
    c.toggleValue(c.tree[0], 'open');
    const params = (c as any).router.navigate.mock.calls.at(-1)[1].queryParams;
    expect(params['Tickets.red.f.Status']).toEqual(['open']);
  });

  it('does not recount on every check when the rows contain empty entries', async () => {
    const { c, data } = await setup();
    data.push(null as any, null as any);
    c.ngDoCheck();
    const spy = jest.spyOn(c as any, 'sourceChanged');
    c.ngDoCheck();
    c.ngDoCheck();
    expect(spy).not.toHaveBeenCalled();
  });
});
