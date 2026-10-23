// The component imports Angular libraries that are compiled when they are loaded.
import '@angular/compiler';
import { Subject } from 'rxjs';
import { MenuItem } from 'primeng/api';
import { AppMenuComponent } from './app.menu.component';
import { Navbar } from '../shared/interfacing/navbar.interface';

describe('AppMenuComponent', () => {
  /** The navbar of a prototype with one menu root, two screens and one 'New' button. */
  const navbar = {
    productionEnv: false,
    navs: [
      {
        id: 'MainMenu',
        label: 'Main menu',
        parent: null,
        ifc: null,
        url: null,
        seqNr: null,
      },
      {
        id: 'Claims',
        label: 'Claims',
        parent: 'MainMenu',
        ifc: 'Claims',
        url: null,
        seqNr: 1,
      },
      {
        id: 'Issues',
        label: 'Issues',
        parent: 'MainMenu',
        ifc: 'Issues',
        url: null,
        seqNr: 2,
      },
    ],
    new: [
      {
        label: 'Claim',
        ifcs: [
          { id: 'Claim', label: 'Claim', link: '', resourceType: 'Claim' },
        ],
      },
    ],
  } as unknown as Navbar;

  let refresh: Subject<unknown>;
  let pending: Subject<Navbar>[];

  /** A menu whose navbar requests stay open until the test answers them. */
  function menu(): AppMenuComponent {
    const menuService = {
      refreshSource$: refresh.asObservable(),
      adminMode: false,
      getNavbar: () => {
        const answer = new Subject<Navbar>();
        pending.push(answer);
        return answer.asObservable();
      },
      setSessionStorageItem: (name: string, data: string) =>
        sessionStorage.setItem(name, data),
    };
    const layoutService = { isHorizontal: () => false, config: {} };
    return new AppMenuComponent(
      layoutService as any,
      menuService as any,
      { nativeElement: document.createElement('div') } as any,
      { run: (f: () => void) => f() } as any,
      { Claims: '/claims', Issues: '/issues', Claim: '/claim' },
    );
  }

  /** Answers every open navbar request, the newest first, as a slow server may. */
  function answerNewestFirst() {
    [...pending].reverse().forEach((answer) => {
      answer.next(navbar);
      answer.complete();
    });
    pending = [];
  }

  const labels = (items: MenuItem[]) => items.map((item) => item.label);
  const stored = () =>
    labels(JSON.parse(sessionStorage.getItem('menuItems') ?? '[]'));

  beforeEach(() => {
    sessionStorage.clear();
    refresh = new Subject();
    pending = [];
  });

  it('shows the menu root once after one load', () => {
    const component = menu();
    component.ngOnInit();
    answerNewestFirst();
    expect(labels(component.model)).toEqual(['New', 'Main menu']);
    expect(labels(component.model[1].items ?? [])).toEqual([
      'Claims',
      'Issues',
    ]);
  });

  it('shows the menu root and the New section once after refreshes that overlap', () => {
    const component = menu();
    component.ngOnInit();
    refresh.next(true);
    refresh.next(true);
    answerNewestFirst();
    expect(labels(component.model)).toEqual(['New', 'Main menu']);
    expect(stored()).toEqual(['Main menu']);
  });

  it('restores one menu root from session storage after overlapping refreshes', () => {
    const first = menu();
    first.ngOnInit();
    refresh.next(true);
    answerNewestFirst();
    first.ngOnDestroy();

    const reloaded = menu();
    reloaded.ngOnInit();
    answerNewestFirst();
    expect(labels(reloaded.model)).toEqual(['New', 'Main menu']);
  });
});
