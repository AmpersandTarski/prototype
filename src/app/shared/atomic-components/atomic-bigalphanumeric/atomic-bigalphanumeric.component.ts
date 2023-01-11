import { Component, OnInit } from '@angular/core';
import { FormControl } from '@angular/forms';
import { AtomicComponentType } from '../../models/atomic-component-types';
import { BaseAtomicComponent } from '../BaseAtomicComponent.class';
import { BaseAtomicFormControlComponent } from '../BaseAtomicFormControlComponent.class';

@Component({
  selector: 'app-atomic-bigalphanumeric',
  templateUrl: './atomic-bigalphanumeric.component.html',
  styleUrls: ['./atomic-bigalphanumeric.component.css'],
})
export class AtomicBigalphanumericComponent extends BaseAtomicFormControlComponent<string> implements OnInit {
  override ngOnInit(): void {
    super.ngOnInit();
    if (!this.isUni) {
      this.initNewItemControl(AtomicComponentType.BigAlphanumeric);
    }
    if (this.isUni) {
      this.initFormControl('change');
    }
  }
}
