import { Injectable } from '@angular/core';
import {
  ActivatedRouteSnapshot,
  Router,
  RouterStateSnapshot,
} from '@angular/router';
import { Observable } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { BaseComponent } from '../../shared/BaseComponent.class';
import { RolesService } from './roles.service';

@Injectable({
  providedIn: 'root',
})
export class RolesGuard extends BaseComponent {
  constructor(private rolesService: RolesService, private router: Router) {
    super();
  }

  // can only activate if it has the required role
  canActivate(
    route: ActivatedRouteSnapshot,
    _state: RouterStateSnapshot,
  ): Observable<boolean> | boolean {
    const isRole$: Observable<boolean> = this.rolesService.isRole(
      route.data['role'],
    );
    isRole$.pipe(takeUntil(this.destroy$)).subscribe((x) => {
      if (!x) {
        this.router.navigate(['']);
      }
    });
    return isRole$;
  }
}
