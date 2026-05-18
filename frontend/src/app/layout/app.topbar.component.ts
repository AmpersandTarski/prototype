import { Component, ElementRef, ViewChild } from '@angular/core';
import { map } from 'rxjs/operators';
import { LayoutService } from './service/app.layout.service';
import { SignalService } from 'src/app/shared/services/signal.service';

@Component({
  selector: 'app-topbar',
  templateUrl: './app.topbar.component.html',
  styleUrls: ['./app.topbar.component.scss'],
})
export class AppTopBarComponent {
  @ViewChild('menubutton') menuButton!: ElementRef;

  @ViewChild('topbarmenubutton') topbarMenuButton!: ElementRef;

  @ViewChild('topbarmenu') menu!: ElementRef;

  /** Total number of individual violations across all signal rules */
  readonly signalViolationCount$ = this.signalService.signals$.pipe(
    map((signals) => signals.reduce((sum, s) => sum + s.violations.length, 0)),
  );

  constructor(
    public layoutService: LayoutService,
    public signalService: SignalService,
  ) {}
}
