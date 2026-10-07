import { Component } from '@angular/core';
import { map } from 'rxjs';
import { LayoutService } from './service/app.layout.service';
import { MenuService } from './app.menu.service';

@Component({
  selector: 'app-footer',
  templateUrl: './app.footer.component.html',
})
export class AppFooterComponent {
  /* The privacy statement of the deploying organisation (setting frontend.privacyStatementUrl), or null.
   * See docs/reference-material/cookies-and-browser-storage.md */
  readonly privacyStatementUrl$ = this.menuService.navbar$.pipe(
    map((navbar) => navbar.privacyStatementUrl ?? null),
  );

  constructor(
    public layoutService: LayoutService,
    private menuService: MenuService,
  ) {}
}
