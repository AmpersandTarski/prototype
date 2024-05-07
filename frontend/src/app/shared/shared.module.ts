import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormsModule } from '@angular/forms';

// PrimeNG modules
import { CalendarModule } from 'primeng/calendar';
import { DropdownModule } from 'primeng/dropdown';
import { InputNumberModule } from 'primeng/inputnumber';
import { InputSwitchModule } from 'primeng/inputswitch';
import { InputTextModule } from 'primeng/inputtext';
import { InputTextareaModule } from 'primeng/inputtextarea';
import { SkeletonModule } from 'primeng/skeleton';
import { TableModule } from 'primeng/table';
import { SplitButtonModule } from 'primeng/splitbutton';
import { PasswordModule } from 'primeng/password';
import { TabViewModule } from 'primeng/tabview';
import { ButtonModule } from 'primeng/button';
import { AutoCompleteModule } from 'primeng/autocomplete';

// Components
import { AtomicAlphanumericComponent } from './atomic-components/atomic-alphanumeric/atomic-alphanumeric.component';
import { AtomicBigalphanumericComponent } from './atomic-components/atomic-bigalphanumeric/atomic-bigalphanumeric.component';
import { AtomicBooleanComponent } from './atomic-components/atomic-boolean/atomic-boolean.component';
import { AtomicDateComponent } from './atomic-components/atomic-date/atomic-date.component';
import { AtomicDatetimeComponent } from './atomic-components/atomic-datetime/atomic-datetime.component';
import { AtomicFloatComponent } from './atomic-components/atomic-float/atomic-float.component';
import { AtomicHugealphanumericComponent } from './atomic-components/atomic-hugealphanumeric/atomic-hugealphanumeric.component';
import { AtomicIntegerComponent } from './atomic-components/atomic-integer/atomic-integer.component';
import { AtomicObjectComponent } from './atomic-components/atomic-object/atomic-object.component';
import { AtomicPasswordComponent } from './atomic-components/atomic-password/atomic-password.component';
import { BoxTableComponent } from './box-components/box-table/box-table.component';
import { BoxTableHeaderTemplateDirective } from './box-components/box-table/box-table-header-template.directive';
import { BoxTableRowTemplateDirective } from './box-components/box-table/box-table-row-template.directive';
import { BoxTableLoadingComponent } from './box-components/box-table-loading/box-table-loading.component';
import { BoxTabsComponent } from './box-components/box-tabs/box-tabs.component';
import { BoxTabsDirective } from './box-components/box-tabs/box-tabs.directive';
import { BoxTabsLoadingComponent } from './box-components/box-tabs-loading/box-tabs-loading.component';
import { BoxFormComponent } from './box-components/box-form/box-form.component';
import { BoxFormTemplateDirective } from './box-components/box-form/box-form-template.directive';
import { BoxFormLoadingComponent } from './box-components/box-form-loading/box-form-loading.component';
import { BoxRawComponent } from './box-components/box-raw/box-raw.component';
import { BoxRawTemplateDirective } from './box-components/box-raw/box-raw-template.directive';
import { BoxPropButtonComponent } from './box-components/box-prop-button/box-prop-button.component';
import { AtomicUrlComponent } from './atomic-components/atomic-url/atomic-url.component';
import { AtomicSelectComponent } from './atomic-components/atomic-select/atomic-select.component';

@NgModule({
  declarations: [
    AtomicAlphanumericComponent,
    AtomicBigalphanumericComponent,
    AtomicBooleanComponent,
    AtomicDateComponent,
    AtomicDatetimeComponent,
    AtomicFloatComponent,
    AtomicHugealphanumericComponent,
    AtomicIntegerComponent,
    AtomicObjectComponent,
    AtomicPasswordComponent,
    AtomicSelectComponent,
    AtomicUrlComponent,
    BoxTableComponent,
    BoxTableHeaderTemplateDirective,
    BoxTableRowTemplateDirective,
    BoxTableLoadingComponent,
    BoxTabsComponent,
    BoxTabsDirective,
    BoxTabsLoadingComponent,
    BoxFormComponent,
    BoxFormTemplateDirective,
    BoxFormLoadingComponent,
    BoxRawComponent,
    BoxRawTemplateDirective,
    BoxPropButtonComponent,
  ],
  imports: [
    CommonModule,
    ReactiveFormsModule,
    FormsModule,
    DropdownModule,
    InputTextModule,
    InputSwitchModule,
    InputNumberModule,
    SkeletonModule,
    InputTextareaModule,
    CalendarModule,
    TableModule,
    SplitButtonModule,
    PasswordModule,
    TabViewModule,
    ButtonModule,
    AutoCompleteModule,
  ],
  exports: [
    AtomicAlphanumericComponent,
    AtomicBigalphanumericComponent,
    AtomicBooleanComponent,
    AtomicDateComponent,
    AtomicDatetimeComponent,
    AtomicFloatComponent,
    AtomicHugealphanumericComponent,
    AtomicIntegerComponent,
    AtomicObjectComponent,
    AtomicPasswordComponent,
    AtomicSelectComponent,
    AtomicUrlComponent,
    BoxTableComponent,
    BoxTableHeaderTemplateDirective,
    BoxTableRowTemplateDirective,
    BoxTableLoadingComponent,
    BoxTabsComponent,
    BoxTabsDirective,
    BoxTabsLoadingComponent,
    BoxFormComponent,
    BoxFormTemplateDirective,
    BoxFormLoadingComponent,
    BoxRawComponent,
    BoxRawTemplateDirective,
    BoxPropButtonComponent,
  ],
  providers: [],
})
export class SharedModule {}
