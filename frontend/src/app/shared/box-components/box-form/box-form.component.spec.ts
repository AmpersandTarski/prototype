import { ElementRef } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import {
  FieldMeta,
  InterfacesJsonService,
} from '../../services/interfaces-json.service';
import { BoxFormComponent } from './box-form.component';

/**
 * Which fields a FORM shows, and how it lays them out, from the field
 * metadata in interfaces.json and the values in the record.
 */
const meta = (m: Partial<FieldMeta>): FieldMeta => ({
  crud: { read: true },
  isUni: true,
  isIdent: false,
  tgtConcept: 'Status',
  isBox: false,
  boxAnnotations: [],
  ...m,
});

const metas: Record<string, Map<string, FieldMeta>> = {
  // the fields of the record itself
  Eis: new Map([
    ['Status', meta({})],
    ['Tekst', meta({ tgtConcept: 'Tekst' })],
    ['Issues', meta({ isUni: false, tgtConcept: 'Issue' })],
    ['Toetsen', meta({ isUni: false, tgtConcept: 'Toets' })],
    ['Noot', meta({ crud: { read: true, update: true } })],
    ['Spoor', meta({ isIdent: true, isBox: true, tgtConcept: 'Eis' })],
    [
      'Leeg',
      meta({
        isIdent: true,
        isBox: true,
        tgtConcept: 'Eis',
        boxAnnotations: ['showOnNoRecords'],
      }),
    ],
    ['Invul', meta({ isIdent: true, isBox: true, tgtConcept: 'Eis' })],
  ]),
  // the fields of the group Spoor
  'Eis/Spoor': new Map([
    ['Regels', meta({ isUni: false, tgtConcept: 'Regel' })],
  ]),
  'Eis/Leeg': new Map([['Regels', meta({ isUni: false })]]),
  // a group whose only field is empty but may be filled
  'Eis/Invul': new Map([
    ['Opmerking', meta({ crud: { read: true, update: true } })],
  ]),
};

const record = {
  _id_: 'R1',
  _label_: 'R1',
  _path_: 'resource/Eis/R1/Eis',
  _ifcs_: [],
  Status: 'geldig',
  Tekst:
    'Een nieuwe coördinator schrijft na één uitleg van twee minuten zelfstandig een notitie.',
  Issues: [{ _id_: '#1' }, { _id_: '#2' }],
  Toetsen: [],
  Noot: null,
  Spoor: { _id_: 'R1', _path_: 'resource/Eis/R1/Eis/Spoor/R1', Regels: [] },
  Leeg: { _id_: 'R1', _path_: 'resource/Eis/R1/Eis/Leeg/R1', Regels: [] },
  Invul: {
    _id_: 'R1',
    _path_: 'resource/Eis/R1/Eis/Invul/R1',
    Opmerking: null,
  },
  // a field interfaces.json does not describe (as when a lookup fails)
  Onbekend: null,
};

async function setup(): Promise<BoxFormComponent<any, any>> {
  TestBed.configureTestingModule({
    providers: [
      {
        provide: ElementRef,
        useValue: new ElementRef(document.createElement('div')),
      },
      {
        provide: InterfacesJsonService,
        useValue: {
          fieldMetas: (path: string) => {
            const key = path
              .split('/')
              .slice(3)
              .filter((s) => !s.startsWith('R'))
              .join('/');
            return Promise.resolve(metas[key] ?? new Map());
          },
          conceptTypes: () =>
            Promise.resolve(new Map([['Tekst', 'BIGALPHANUMERIC']])),
        },
      },
    ],
  });
  const c = TestBed.runInInjectionContext(() => new BoxFormComponent());
  c.isRootBox = true;
  c.ngOnInit();
  // The first look at a record asks interfaces.json; the answer comes a tick later.
  for (const name of Object.keys(record)) c.showField(record, name);
  c.showField(record.Spoor, 'Regels');
  c.showField(record.Leeg, 'Regels');
  c.showField(record.Invul, 'Opmerking');
  await new Promise((r) => setTimeout(r));
  return c;
}

describe('BoxFormComponent', () => {
  it('shows a field with a value', async () => {
    const c = await setup();
    expect(c.showField(record, 'Status')).toBe(true);
    expect(c.showField(record, 'Issues')).toBe(true);
  });

  it('leaves out an empty field the user cannot fill', async () => {
    const c = await setup();
    expect(c.showField(record, 'Toetsen')).toBe(false);
  });

  it('shows an empty field the user may fill', async () => {
    const c = await setup();
    expect(c.showField(record, 'Noot')).toBe(true);
  });

  it('leaves out a group whose fields are all empty', async () => {
    const c = await setup();
    expect(c.showField(record, 'Spoor')).toBe(false);
  });

  it('shows an empty group that holds a field the user may fill', async () => {
    const c = await setup();
    expect(c.showField(record, 'Invul')).toBe(true);
  });

  it('shows an empty field whose metadata is unknown, so it never hides a field to fill', async () => {
    const c = await setup();
    expect(c.showField(record, 'Onbekend')).toBe(true);
  });

  it('shows an empty group whose box says showOnNoRecords', async () => {
    const c = await setup();
    expect(c.showField(record, 'Leeg')).toBe(true);
  });

  it('shows every field under showSubOnNoRecords', async () => {
    const c = await setup();
    c.showSubOnNoRecords = true;
    expect(c.showField(record, 'Toetsen')).toBe(true);
  });

  it('hides an empty editable field under hideSubOnNoRecords', async () => {
    const c = await setup();
    c.hideSubOnNoRecords = true;
    expect(c.showField(record, 'Noot')).toBe(false);
  });

  it('tells the kind of each field', async () => {
    const c = await setup();
    await new Promise((r) => setTimeout(r));
    expect(c.fieldKind(record, 'Status')).toBe('meta');
    expect(c.fieldKind(record, 'Tekst')).toBe('content');
    expect(c.fieldKind(record, 'Issues')).toBe('related');
    expect(c.fieldKind(record, 'Spoor')).toBe('group');
  });

  it('counts the values of a field that holds several', async () => {
    const c = await setup();
    expect(c.count(record, 'Issues')).toBe(2);
    expect(c.count(record, 'Status')).toBeNull();
  });
});

describe('BoxFormComponent, a record with nothing to show', () => {
  it('says so, so an empty group shown by showOnNoRecords is not a blank card', async () => {
    const c = await setup();
    expect(c.showsNothing(record.Leeg)).toBe(true);
    expect(c.showsNothing(record)).toBe(false);
  });
});
