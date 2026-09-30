import {
  Component,
  ContentChild,
  Input,
  OnInit,
  TemplateRef,
  booleanAttribute,
  inject,
} from '@angular/core';
import { ObjectBase } from '../../objectBase.interface';
import {
  FieldMeta,
  InterfacesJsonService,
} from '../../services/interfaces-json.service';
import { BaseBoxComponent } from '../BaseBoxComponent.class';
import { BoxFormTemplateDirective } from './box-form-template.directive';

/**
 * How a FORM lays out one field. The kind follows from the model, so the
 * modeler writes no annotation for it:
 *  - group:   a nested box on I, a block of fields the modeler put together;
 *  - meta:    a UNI field with a short value, such as a status or a project;
 *  - content: a UNI field with a long value, such as a text;
 *  - related: a field that can hold several values.
 */
export type FieldKind = 'group' | 'meta' | 'content' | 'related';

const LONG_TEXT_TYPES = new Set(['BIGALPHANUMERIC', 'HUGEALPHANUMERIC']);
const SHORT_VALUE = 40;

@Component({
  selector: 'app-box-form',
  templateUrl: './box-form.component.html',
  styleUrls: ['./box-form.component.scss'],
})
export class BoxFormComponent<
    TItem extends ObjectBase,
    I extends ObjectBase | ObjectBase[],
  >
  extends BaseBoxComponent<TItem, I>
  implements OnInit
{
  @ContentChild(BoxFormTemplateDirective, { read: TemplateRef })
  template?: TemplateRef<unknown>;

  /** Show every field, also an empty one that cannot be edited. */
  @Input({ transform: booleanAttribute }) showSubOnNoRecords = false;
  /** Hide every empty field, also one that can be edited. */
  @Input({ transform: booleanAttribute }) hideSubOnNoRecords = false;

  private interfacesJson = inject(InterfacesJsonService);
  /** Field metadata per record path, read asynchronously from interfaces.json. */
  private metas = new Map<string, Map<string, FieldMeta>>();
  private conceptTypes = new Map<string, string>();

  override ngOnInit(): void {
    super.ngOnInit();
    this.interfacesJson.conceptTypes().then((types) => {
      this.conceptTypes = types;
    });
  }

  private metaOf(item: any, name: string): FieldMeta | undefined {
    const path: string | undefined = item?._path_;
    if (!path) return undefined;
    const known = this.metas.get(path);
    if (known) return known.get(name);
    this.metas.set(path, new Map());
    this.interfacesJson.fieldMetas(path).then((m) => this.metas.set(path, m));
    return undefined;
  }

  /**
   * Whether a field of `item` appears. A field with a value always appears.
   * An empty field appears when the user may fill it (C or U), when this box
   * says showSubOnNoRecords, or when the field is a group whose own box says
   * showOnNoRecords. Any other empty field stays out of sight, so a record
   * shows what it has. While the metadata of a field is unknown (still loading,
   * or not found in interfaces.json), an empty field appears: hiding it could
   * hide a field the user has to fill.
   */
  showField(item: any, name: string): boolean {
    if (this.showSubOnNoRecords) return true;
    const meta = this.metaOf(item, name);
    if (!this.isEmptyValue(item?.[name], meta)) return true;
    if (this.hideSubOnNoRecords) return false;
    if (meta === undefined) return true;
    if (meta.crud.create || meta.crud.update) return true;
    return meta?.boxAnnotations.includes('showOnNoRecords') ?? false;
  }

  /** Whether none of the fields of `item` appears. */
  showsNothing(item: any): boolean {
    if (item == null || typeof item !== 'object') return false;
    const fields = Object.keys(item).filter((k) => !k.startsWith('_'));
    return fields.length > 0 && fields.every((k) => !this.showField(item, k));
  }

  /** The CSS class for a field, from its kind. */
  fieldClass(item: any, name: string): string {
    return `box-form-field--${this.fieldKind(item, name)}`;
  }

  fieldKind(item: any, name: string): FieldKind {
    const meta = this.metaOf(item, name);
    const value = item?.[name];
    if (meta?.isIdent && meta.isBox) return 'group';
    if (meta && !meta.isUni) return 'related';
    if (Array.isArray(value) && value.length > 1) return 'related';
    if (
      meta &&
      LONG_TEXT_TYPES.has(this.conceptTypes.get(meta.tgtConcept) ?? '')
    )
      return 'content';
    return this.labelLength(value) > SHORT_VALUE ? 'content' : 'meta';
  }

  /** The number of values of a field that holds more than one, else null. */
  count(item: any, name: string): number | null {
    const value = item?.[name];
    return Array.isArray(value) && value.length > 1 ? value.length : null;
  }

  private labelLength(value: any): number {
    if (value == null) return 0;
    if (Array.isArray(value))
      return value.length ? this.labelLength(value[0]) : 0;
    if (typeof value === 'object') return String(value._label_ ?? '').length;
    return String(value).length;
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
