import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { map, Observable } from 'rxjs';
import {
  Navbar,
  SessionRole,
} from 'src/app/shared/interfacing/navbar.interface';
import { Role } from 'src/app/shared/models/roles';

@Injectable()
export class RolesService {
  constructor(private http: HttpClient) {}

  public getRoles(): Observable<Array<SessionRole>> {
    const navbar: Observable<Navbar> = this.http.get<Navbar>('app/navbar');
    const roles: Observable<Array<SessionRole>> = navbar.pipe(
      map((x) => x.sessionRoles),
    );
    return roles;
  }

  /**
   * Activate exactly one role for the session: the chosen role becomes active
   * and every other role is deactivated. A session therefore always has a
   * single active role.
   */
  public activateRole(
    roles: Array<SessionRole>,
    roleIndex: number,
  ): Observable<Array<SessionRole>> {
    const next = roles.map((role, i) => ({ ...role, active: i === roleIndex }));
    return this.http.patch<Array<SessionRole>>('app/roles', next);
  }

  public getNavbar(): Observable<Navbar> {
    return this.http.get<Navbar>('app/navbar');
  }

  public isRole(role: Role): Observable<boolean> {
    return this.getRoles().pipe(map((x) => x[role].active));
  }

  public setSessionStorageItem(name: string, data: string) {
    sessionStorage.setItem(name, data);
  }
}
