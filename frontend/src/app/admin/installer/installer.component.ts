import { Component } from '@angular/core';
import { finalize, takeUntil } from 'rxjs';
import { ButtonState } from 'src/app/shared/helper/button-state';
import { BaseComponent } from 'src/app/shared/BaseComponent.class';
import { InstallerService } from './installer.service';
import { MenuService } from 'src/app/layout/app.menu.service';

@Component({
  selector: 'app-installer',
  templateUrl: './installer.component.html',
  styleUrls: ['./installer.component.scss'],
})
export class InstallerComponent extends BaseComponent {
  buttonState1: ButtonState = new ButtonState();
  buttonState2: ButtonState = new ButtonState();
  buttonState3: ButtonState = new ButtonState();
  buttonState4: ButtonState = new ButtonState();

  constructor(
    private installerService: InstallerService,
    private menuService: MenuService,
  ) {
    super();
  }

  /**
   * Method to determine if there is a 'installer' call to the backend in progress, triggered by any of the buttons
   * We capture this state in the associated buttonState(s)
   */
  isLoading(): boolean {
    return [
      this.buttonState1,
      this.buttonState2,
      this.buttonState3,
      this.buttonState4,
    ].some((state) => state.isLoading());
  }

  /**
   * Set the buttonStates to their initial value
   */
  initButtonStates(): void {
    [
      this.buttonState1,
      this.buttonState2,
      this.buttonState3,
      this.buttonState4,
    ].forEach((state) => state.init());
  }

  /**
   * Call to the backend to (re)install the application and database
   *
   * @param defaultPop determines if default population of the application should be installed or not
   * @param ignoreInvariants determines if invariant violations of the default population should be ignored or not
   * @param buttonState the ButtonState associated with the button that triggers this method
   */
  reinstall(
    defaultPop: boolean,
    ignoreInvariants: boolean,
    buttonState: ButtonState,
  ): void {
    this.initButtonStates();
    buttonState.loading = true;
    this.installerService
      .getReinstall(defaultPop, ignoreInvariants)
      .pipe(finalize(() => (buttonState.loading = false)), takeUntil(this.destroy$))
      .subscribe({
        error: (_err) => (buttonState.error = true),
        complete: () => {
          buttonState.success = true;
          this.menuService.refresh();
        },
      });
  }

  updateChecksum(buttonState: ButtonState): void {
    this.installerService
      .getChecksumUpdate()
      .pipe(finalize(() => (buttonState.loading = false)), takeUntil(this.destroy$))
      .subscribe({
        error: (_err) => (buttonState.error = true),
        complete: () => (buttonState.success = true),
      });
  }
}
