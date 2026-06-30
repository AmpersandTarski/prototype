import { Component, Inject, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { takeUntil } from 'rxjs/operators';
import { MenuItem } from 'primeng/api';
import {
  Navbar,
  SessionRole,
} from 'src/app/shared/interfacing/navbar.interface';
import { BaseComponent } from 'src/app/shared/BaseComponent.class';
import { RolesService } from './roles.service';
import { MenuService } from 'src/app/layout/app.menu.service';
import {
  InterfaceRouteMap,
  INTERFACE_ROUTE_MAPPING_TOKEN,
} from '../../config';

@Component({
  selector: 'app-roles',
  templateUrl: './roles.component.html',
  styleUrls: ['./roles.component.scss'],
})
export class RolesComponent extends BaseComponent implements OnInit {
  public menuItems!: MenuItem[];
  // The picker is shown only when there is something to choose, i.e. more than one
  // selectable role (the user's allowed roles excluding Anonymous).
  public showPicker = false;

  constructor(
    private rolesService: RolesService,
    private menuService: MenuService,
    private router: Router,
    @Inject(INTERFACE_ROUTE_MAPPING_TOKEN)
    private interfaceRouteMap: InterfaceRouteMap,
  ) {
    super();
  }

  ngOnInit() {
    this.loadOrCreateMenu();
  }

  private loadOrCreateMenu() {
    this.rolesService
      .getRoles()
      .pipe(takeUntil(this.destroy$))
      .subscribe((roles) => {
        // The picker offers only the roles the user may choose: their allowed
        // roles except Anonymous (Anonymous is reached via the separate Logout
        // action, not the picker).
        const choosable = roles.filter((role) => role.id !== 'Anonymous');
        // Hide the picker entirely when there is nothing to choose.
        this.showPicker = choosable.length > 1;
        // A session has exactly one active role, so the role menu reads as a
        // single-choice list: the active role is marked, the others are not.
        this.menuItems = choosable.map((role) => ({
          label: role.label,
          icon: role.active ? 'pi pi-check-circle' : 'pi pi-circle-off',
          command: () => this.selectRole(roles, roles.indexOf(role)),
        }));
      });
  }

  private selectRole(roles: Array<SessionRole>, index: number): void {
    if (roles[index].active) {
      return; // already the active role; switching changes nothing
    }
    this.rolesService
      .activateRole(roles, index)
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => {
        this.loadOrCreateMenu(); // refresh the single-active marker
        this.menuService.refresh(); // rebuild the side menu for the new role
        this.redirectIfPageNotAllowed();
      });
  }

  /**
   * After switching role, the current page may no longer be visible to the new
   * role. If the current route is not among the routes the new role may reach,
   * navigate back to the home page.
   */
  private redirectIfPageNotAllowed(): void {
    this.rolesService
      .getNavbar()
      .pipe(takeUntil(this.destroy$))
      .subscribe((navbar) => {
        const allowed = this.allowedRoutes(navbar);
        const current = this.router.url.split('?')[0];
        const fits =
          current === navbar.home ||
          allowed.some(
            (route) => current === route || current.startsWith(route + '/'),
          );
        if (!fits) {
          this.router.navigateByUrl(navbar.home);
        }
      });
  }

  /** The base routes the active role may reach, per the (role-filtered) navbar. */
  private allowedRoutes(navbar: Navbar): string[] {
    const fromNavs = navbar.navs
      .filter((nav) => nav.ifc)
      .map((nav) => this.interfaceRouteMap[nav.ifc as string]);
    const fromNew = (navbar.new ?? []).flatMap((btn) =>
      btn.ifcs.map((ifc) => this.interfaceRouteMap[ifc.id]),
    );
    return [...fromNavs, ...fromNew, navbar.home].filter(
      (route): route is string => Boolean(route),
    );
  }
}
