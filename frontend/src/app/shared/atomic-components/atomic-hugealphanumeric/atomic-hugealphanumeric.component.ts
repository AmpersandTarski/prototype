import { Component, OnDestroy, OnInit } from '@angular/core';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
import { ObjectBase } from '../../objectBase.interface';

@Component({
  selector: 'app-atomic-hugealphanumeric',
  templateUrl: './atomic-hugealphanumeric.component.html',
  styleUrls: ['./atomic-hugealphanumeric.component.css'],
})
export class AtomicHugealphanumericComponent<
    I extends ObjectBase | ObjectBase[],
  >
  extends BaseAtomicComponent<string, I>
  implements OnInit, OnDestroy
{
  // Monaco reports a blur asynchronously (after the click that caused it), so
  // committing only on blur loses a race with an immediately following action
  // (e.g. a Compile button that flushes the transaction). We therefore also
  // commit shortly after the user stops typing, so the value is persisted
  // before any such action runs.
  private commitTimer: ReturnType<typeof setTimeout> | undefined;

  public onEditorChange(value: string): void {
    this.resource[this.propertyName] = value;
    this.dirty = true;
    clearTimeout(this.commitTimer);
    this.commitTimer = setTimeout(() => this.updateValue(), 400);
  }

  public onEditorBlur(): void {
    clearTimeout(this.commitTimer);
    this.updateValue();
  }

  override ngOnDestroy(): void {
    clearTimeout(this.commitTimer);
    super.ngOnDestroy();
  }
}
