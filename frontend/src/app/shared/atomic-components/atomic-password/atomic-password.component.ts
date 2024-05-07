import { Component, OnInit } from '@angular/core';
import { FormControl } from '@angular/forms';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
import { ObjectBase } from '../../objectBase.interface';

@Component({
  selector: 'app-atomic-password',
  templateUrl: './atomic-password.component.html',
  styleUrls: ['./atomic-password.component.css'],
})
export class AtomicPasswordComponent<I extends ObjectBase | ObjectBase[]>
  extends BaseAtomicComponent<string, I>
  implements OnInit
{
  public formControl!: FormControl<string>;

  override ngOnInit(): void {
    super.ngOnInit();

    // univalent
    if (this.isUni && this.canUpdate()) {
      this.initFormControl();
    }
  }

  private initFormControl(): void {
    this.formControl = new FormControl<string>(this.data[0], {
      nonNullable: true,
    });
  }

  // TODO: show notification when it is successful
  public patchPassword(): void {
    const password: string = this.formControl.value;

    this.interfaceComponent
      .patch(this.resource._path_, [
        {
          op: 'replace',
          path: this.propertyName,
          value: password === '' ? null : password,
        },
      ])
      .subscribe();
  }
}
