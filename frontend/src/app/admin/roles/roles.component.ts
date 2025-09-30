import { Component, OnInit } from '@angular/core';
import { takeUntil } from 'rxjs/operators';
import { MenuItem } from 'primeng/api';
import { SessionRole } from 'src/app/shared/interfacing/navbar.interface';
import { BaseComponent } from 'src/app/shared/BaseComponent.class';
import { RolesService } from './roles.service';

@Component({
  selector: 'app-roles',
  templateUrl: './roles.component.html',
  styleUrls: ['./roles.component.scss'],
})
export class RolesComponent extends BaseComponent implements OnInit {
  public menuItems!: MenuItem[];

  constructor(private rolesService: RolesService) {
    super();
  }

  ngOnInit() {
    this.loadOrCreateMenu();
  }

  private loadOrCreateMenu() {
    this.rolesService.getRoles().pipe(takeUntil(this.destroy$)).subscribe((roles) => {
      // maps the roles into menuItems
      this.menuItems = roles.map((role, index) => ({
        label: role.label,
        icon: role.active ? 'pi pi-check-circle' : 'pi pi-circle-off',
        command: () => this.patchRole(roles, index),
      }));
    });
  }

  private patchRole(roles: Array<SessionRole>, index: number): void {
    this.rolesService.patchRole(roles, index).pipe(takeUntil(this.destroy$)).subscribe((x) =>
      // updates menuItems' icon
      x[index].active
        ? (this.menuItems[index].icon = 'pi pi-check-circle')
        : (this.menuItems[index].icon = 'pi pi-circle-off'),
    );
    this.loadOrCreateMenu();
  }
}
