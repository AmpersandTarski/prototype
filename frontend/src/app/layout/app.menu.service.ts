import { Injectable } from '@angular/core';
import {
  Subject,
  map,
  Observable,
  BehaviorSubject,
  ReplaySubject,
  tap,
} from 'rxjs';
import { Navbar, Navs } from '../shared/interfacing/navbar.interface';
import { MenuChangeEvent } from './api/menuchangeevent';
import { HttpClient } from '@angular/common/http';

@Injectable({
  providedIn: 'root',
})
export class MenuService {
  constructor(private http: HttpClient) {
    const storedAdminMode = sessionStorage.getItem('adminMode');
    if (storedAdminMode !== null) {
      this.adminModeSource.next(storedAdminMode === 'true');
    }
  }

  private menuSource = new Subject<MenuChangeEvent>();
  private resetSource = new Subject();
  private refreshSource = new Subject();
  private adminModeSource = new BehaviorSubject<boolean>(false);
  private navbarSource = new ReplaySubject<Navbar>(1);

  menuSource$ = this.menuSource.asObservable();
  resetSource$ = this.resetSource.asObservable();
  refreshSource$ = this.refreshSource.asObservable();
  adminMode$ = this.adminModeSource.asObservable();
  navbar$ = this.navbarSource.asObservable();

  get adminMode(): boolean {
    return this.adminModeSource.value;
  }

  setAdminMode(value: boolean) {
    this.adminModeSource.next(value);
    sessionStorage.setItem('adminMode', String(value));
    this.refresh();
  }

  onMenuStateChange(event: MenuChangeEvent) {
    this.menuSource.next(event);
  }

  reset() {
    this.resetSource.next(true);
  }

  refresh() {
    this.refreshSource.next(true);
  }

  /* Obtain navbar navs and convert them to MenuItems */
  getMenuItems(): Observable<Array<Navs>> {
    return this.http.get<Navbar>('app/navbar').pipe(map((x) => x.navs));
  }

  getAddButtons(): Observable<Navbar['new']> {
    return this.http.get<Navbar>('app/navbar').pipe(map((x) => x.new));
  }

  /* Every navbar response is also published on navbar$, so that other layout parts (the footer)
   * read it without a request of their own: the navbar endpoint checks the process rules. */
  getNavbar(): Observable<Navbar> {
    return this.http
      .get<Navbar>('app/navbar')
      .pipe(tap((navbar) => this.navbarSource.next(navbar)));
  }

  public setSessionStorageItem(name: string, data: string) {
    sessionStorage.setItem(name, data);
  }
}
