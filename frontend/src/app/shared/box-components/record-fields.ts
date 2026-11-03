import {
  FieldMeta,
  InterfacesJsonService,
} from '../services/interfaces-json.service';

/**
 * Whether a field of a record is empty, read from the field's metadata in
 * interfaces.json. A FORM leaves an empty field out and a TABS box an empty tab
 * with this same notion of empty, so a group (a nested box on I) counts as empty
 * in both when none of its own fields has a value or may be filled.
 */
export class RecordFields {
  /** Field metadata per record path, read asynchronously from interfaces.json. */
  private metas = new Map<string, Map<string, FieldMeta>>();

  constructor(private interfacesJson: InterfacesJsonService) {}

  /**
   * The metadata of field `name` of `item`, or undefined while it is loading or
   * when interfaces.json does not know the field.
   */
  metaOf(item: any, name: string): FieldMeta | undefined {
    const path: string | undefined = item?._path_;
    if (!path) return undefined;
    const known = this.metas.get(path);
    if (known) return known.get(name);
    this.metas.set(path, new Map());
    this.interfacesJson.fieldMetas(path).then((m) => this.metas.set(path, m));
    return undefined;
  }

  /**
   * A value is empty when it is absent, an empty string or an empty list. A
   * group (a nested box on I) is empty when all of its own fields are empty and
   * none of them is one the user may fill.
   */
  isEmptyValue(value: any, meta?: FieldMeta): boolean {
    if (value == null || value === '') return true;
    const isGroup = meta?.isIdent === true && meta.isBox;
    if (Array.isArray(value)) {
      if (value.length === 0) return true;
      return isGroup ? value.every((v) => this.isEmptyGroup(v)) : false;
    }
    if (typeof value === 'object' && isGroup) return this.isEmptyGroup(value);
    return false;
  }

  private isEmptyGroup(group: any): boolean {
    if (group == null) return true;
    return Object.keys(group)
      .filter((k) => !k.startsWith('_'))
      .every((k) => {
        const meta = this.metaOf(group, k);
        const fillable = meta?.crud.create || meta?.crud.update;
        return this.isEmptyValue(group[k], meta) && !fillable;
      });
  }
}
