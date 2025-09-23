import {
  Component,
  Input,
  OnInit,
  booleanAttribute,
} from '@angular/core';
import { takeUntil } from 'rxjs/operators';
import { AmpersandInterfaceComponent } from '../interfacing/ampersand-interface.class';
import { ObjectBase } from '../objectBase.interface';
import { BaseComponent } from '../BaseComponent.class';
@Component({
  template: '',
})
export abstract class BaseAtomicComponent<
    T,
    I extends ObjectBase | ObjectBase[],
  >
  extends BaseComponent
  implements OnInit
{
  @Input({ required: true }) property: T | Array<T> | null = null;

  @Input({ required: true }) resource: any;

  @Input({ required: true }) propertyName: string;

  // We require a AmpersandInterfaceComponent reference that implements the required methods (like patch)
  // Most likely this is a top-level component for a specific application interface (e.g. ProjectComponent)
  @Input({ required: true }) interfaceComponent: AmpersandInterfaceComponent<I>;

  @Input({ transform: booleanAttribute }) isUni = false;
  @Input({ transform: booleanAttribute }) isTot = false;

  @Input() crud = 'cRud';

  /**
   * Remember if the value was changed, so that we know if we have to patch on blur,
   * or if we can skip it.
   */
  dirty = false;

  /**
   * New value for non-isUni
   */
  newValue: T | undefined;

  ngOnInit(): void {
    if (!(this.propertyName in this.resource)) {
      throw new Error(
        `Property '${this.propertyName}' not defined for object in '${this.resource._path_}'. It is likely that the backend data model is not in sync with the generated frontend.`,
      );
    }
  }

  public canCreate(): boolean {
    return this.crud[0] == 'C';
  }
  public canRead(): boolean {
    return this.crud[1] == 'R';
  }
  public canUpdate(): boolean {
    return this.crud[2] == 'U';
  }
  public canDelete(): boolean {
    return this.crud[3] == 'D';
  }

  get data(): T[] {
    return this.requireArray(this.resource[this.propertyName]);
  }

  public requireArray(property: T | Array<T> | null) {
    if (Array.isArray(property)) {
      return property;
    } else if (property === null) {
      return [];
    } else {
      return [property];
    }
  }

  // Remove for not isUni atomic-components
  public removeItem(index: number) {
    const val = this.data[index] as any;

    this.interfaceComponent
      .patch(this.resource._path_, [
        {
          op: 'remove',
          path: this.propertyName,
          value: val._id_ ? val._id_ : val,
        },
      ])
      .pipe(takeUntil(this.destroy$))
      .subscribe();
  }

  public isNewItemInputRequired() {
    return this.isTot && this.data.length === 0;
  }

  public isNewItemInputDisabled() {
    return this.isUni && this.data.length > 0;
  }

  public updateValue() {
    if (!this.dirty) return;

    // transform empty string to null value
    if (this.resource[this.propertyName] === '') {
      this.resource[this.propertyName] = null;
    }

    const val = this.resource[this.propertyName];

    this.interfaceComponent
      .patch(this.resource._path_, [
        {
          op: 'replace',
          path: this.propertyName,
          // Send _id_ of object when present, primitive value otherwise
          value: val?._id_ ? val._id_ : val,
        },
      ])
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => {
        this.dirty = false;
      });
  }

  public addValue() {
    if (!this.newValue) return;

    const val = this.newValue as any;

    this.interfaceComponent
      .patch(this.resource._path_, [
        {
          op: 'add',
          path: this.propertyName,
          value: val?._id_ ? val._id_ : val,
        },
      ])
      .pipe(takeUntil(this.destroy$))
      .subscribe((x) => {
        if (x.isCommitted && x.invariantRulesHold) {
          this.newValue = undefined;
        }
      });
  }
}
