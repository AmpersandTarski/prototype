import {
  AfterViewChecked,
  AfterViewInit,
  ElementRef,
  Inject,
  NgZone,
  OnDestroy,
  OnInit,
} from '@angular/core';
import { Component } from '@angular/core';
import { startWith, switchMap, takeUntil, tap } from 'rxjs/operators';
import { MenuItem } from 'primeng/api';
import { menuItems as adminMenuItems } from '../admin/admin.module';
import { BaseComponent } from '../shared/BaseComponent.class';
import { LayoutService } from './service/app.layout.service';
import { MenuService } from './app.menu.service';
import { Navbar } from '../shared/interfacing/navbar.interface';
import { InterfaceRouteMap, INTERFACE_ROUTE_MAPPING_TOKEN } from '../config';
import { v4 as uuidv4 } from 'uuid';

@Component({
  selector: 'app-menu',
  templateUrl: './app.menu.component.html',
})
export class AppMenuComponent
  extends BaseComponent
  implements OnInit, AfterViewInit, AfterViewChecked, OnDestroy
{
  model: MenuItem[] = [];

  /* True while the full bar is rendered for width measurement; the bar clips
   * its overflow during that frame (see _menu.scss) */
  measuring = false;

  /* Width (px) reserved for the "More" overflow item in horizontal menu mode */
  private static readonly MORE_RESERVE_PX = 110;

  private menuModeApplied = false;
  private lastHorizontal = false;
  private lastBarWidth = 0;
  private fullBarWidths: number[] = [];
  private moreSection: MenuItem | null = null;
  private resizeObserver?: ResizeObserver;

  constructor(
    public layoutService: LayoutService,
    public menuService: MenuService,
    private el: ElementRef,
    private zone: NgZone,
    @Inject(INTERFACE_ROUTE_MAPPING_TOKEN)
    private interfaceRouteMap: InterfaceRouteMap,
  ) {
    super();
  }

  /* One build per load or refresh. switchMap drops the navbar answer of a build that a
   * newer refresh overtook, and each build assigns a complete model, so refreshes that
   * overlap (e.g. two clicks on the admin switch) cannot stack their menus into one. */
  ngOnInit() {
    this.menuService.refreshSource$
      .pipe(
        tap(() => {
          sessionStorage.removeItem('menuItems');
          this.model = [];
          this.moreSection = null;
          this.fullBarWidths = [];
        }),
        startWith(null),
        switchMap(() => this.menuService.getNavbar()),
        takeUntil(this.destroy$),
      )
      .subscribe((navbar) => {
        this.model = this.buildMenu(navbar);
        // Menu content is complete; (re)compute the horizontal overflow.
        this.updateOverflow(true);
      });
  }

  ngAfterViewInit() {
    // Recompute the "More" overflow when the available width changes
    this.resizeObserver = new ResizeObserver(() => {
      const width = (this.el.nativeElement as HTMLElement).offsetWidth;
      if (width !== this.lastBarWidth) {
        this.lastBarWidth = width;
        // ResizeObserver callbacks run outside the Angular zone
        this.zone.run(() => this.updateOverflow(false));
      }
    });
    this.resizeObserver.observe(this.el.nativeElement);
  }

  ngAfterViewChecked() {
    // Re-measure when the menu mode switches to/from horizontal.
    // Deferred: updating bindings synchronously here is not allowed
    const horizontal = this.layoutService.isHorizontal();
    if (horizontal !== this.lastHorizontal) {
      this.lastHorizontal = horizontal;
      setTimeout(() => this.updateOverflow(true));
    }
  }

  override ngOnDestroy() {
    this.resizeObserver?.disconnect();
    super.ngOnDestroy();
  }

  /* Apply the project's default menu mode (backend setting frontend.menuMode) once */
  private applyDefaultMenuMode(navbar: Navbar) {
    if (this.menuModeApplied) {
      return;
    }
    this.menuModeApplied = true;
    if (
      navbar.menuMode != null &&
      ['static', 'overlay', 'horizontal'].includes(navbar.menuMode)
    ) {
      this.layoutService.config.menuMode = navbar.menuMode;
    }
  }

  /* The complete menu for one navbar answer: the menu items from session storage when an
   * earlier build stored them, else from the navbar; then the admin items and the 'New' section. */
  private buildMenu(navbar: Navbar): MenuItem[] {
    const isProduction = !!navbar.productionEnv;
    this.applyDefaultMenuMode(navbar);

    const navbarItems = sessionStorage.getItem('menuItems');
    let model: MenuItem[];
    if (navbarItems != null) {
      model = (JSON.parse(navbarItems) as Array<MenuItem>).filter(
        (item) => item.label !== 'Admin',
      );
    } else {
      model = this.menuItemsFrom(navbar);
      this.menuService.setSessionStorageItem(
        'menuItems',
        JSON.stringify(model),
      );
    }

    // Add admin menu items if admin mode is active and not in production
    if (!isProduction && this.menuService.adminMode) {
      adminMenuItems.forEach((item) => model.push(item));
    }

    // Add 'New' buttons for new instance of the defined entities
    const newSection = this.newSection(navbar);
    if (newSection != null) {
      model.unshift(newSection);
    }
    return model;
  }

  /* The menu items of the navbar, as a tree ordered by seqNr */
  private menuItemsFrom(navbar: Navbar): MenuItem[] {
    const model: MenuItem[] = [];
    const childItems = new Array<MenuItem>(); // Storage for child items where parent is not added yet.
    const seqNrs = new WeakMap<MenuItem, number>(); // seqNr per item, to order siblings after tree assembly
    const isProduction = !!navbar.productionEnv;
    const navs = navbar.navs;

    // Filter out developer / model-debugging interfaces when in production mode,
    // or when not in admin mode (in development)
    const displayNavs =
      isProduction || !this.menuService.adminMode
        ? navs.filter((nav) => !nav.ifc?.startsWith('PrototypeContext.'))
        : navs;

    // Add fetched menu items
    displayNavs.forEach((nav) => {
      let menuItem: MenuItem;

      /* Create a variable to determine the type of menu item */
      const itemType = 2 * Number(nav.ifc != null) + Number(nav.url != null);
      switch (itemType) {
        case 0: {
          // An item without interface or url: a menu root (no parent),
          // or a submenu container grouping other items (has a parent).
          // No routerLink: a container is a toggle, not a navigation target
          menuItem = {
            id: nav.id,
            label: nav.label,
            icon: 'pi pi-fw pi-bars',
            items: [],
          };
          if (nav.seqNr != null) {
            seqNrs.set(menuItem, nav.seqNr);
          }

          if (nav.parent == null) {
            model.unshift(menuItem);
          } else {
            menuItem.fragment = nav.parent;
            childItems.push(menuItem);
          }
          break;
        }
        case 1: {
          // External URL
          menuItem = {
            label: nav.label,
            icon: 'pi pi-fw pi-bars',
            url: nav.url ?? undefined,
          };

          model.push(menuItem);
          break;
        }
        default: {
          // Direct link to interface
          menuItem = {
            id: nav.id,
            label: nav.label,
            icon: 'pi pi-fw pi-bars',
            routerLink: [this.interfaceRouteMap[nav.ifc ?? 'undefined']],
          };
          if (nav.seqNr != null) {
            seqNrs.set(menuItem, nav.seqNr);
          }

          // If item has a parent, add it to the parent items.
          // Else try adding it at the end.
          if (nav.parent == null) {
            break;
          }
          menuItem.fragment = nav.parent;
          childItems.push(menuItem);
          break;
        }
      }
    });

    // Attach child items to their parent, which may itself be a child
    // (e.g. an interface item inside a submenu container). Repeat until no
    // progress is made; whatever remains has no reachable parent and is
    // added at root level so no item silently disappears.
    let remaining = childItems;
    while (remaining.length > 0) {
      const deferred: MenuItem[] = [];
      for (const childItem of remaining) {
        const parentItem = this.findItemById(model, childItem.fragment);
        parentItem == null
          ? deferred.push(childItem)
          : this.addItemToParent(parentItem, childItem);
      }
      if (deferred.length === remaining.length) {
        // No parent found for these items; show them at root level
        deferred.forEach((item) => {
          item.fragment = undefined;
          model.push(item);
        });
        break;
      }
      remaining = deferred;
    }

    // Order siblings by seqNr on every level (stable: items without a
    // seqNr keep their position at the end); the attach order above
    // depends on parent availability, not on seqNr
    const sortTree = (items: MenuItem[]) => {
      items.sort(
        (a, b) =>
          (seqNrs.get(a) ?? Number.MAX_SAFE_INTEGER) -
          (seqNrs.get(b) ?? Number.MAX_SAFE_INTEGER),
      );
      items.forEach((item) => {
        if (item.items != null) {
          sortTree(item.items);
        }
      });
    };
    sortTree(model);
    return model;
  }

  /* The 'New' section with a button per interface that creates an instance, or null when there is none */
  private newSection(navbar: Navbar): MenuItem | null {
    const items: MenuItem[] = [];

    (navbar.new ?? []).forEach((addBtn) => {
      const ifc = addBtn.ifcs[0];
      // SESSION/ONE-scope interfaces are a single shared session view, not
      // per-resource instances. "New" has no meaning for them, and the
      // UUID-based route (/clinics/{uuid}) the old code generated does not exist.
      if (ifc.resourceType === 'SESSION' || ifc.resourceType === 'ONE') {
        return;
      }
      const link = (this.interfaceRouteMap[ifc.id] ?? '') + '/' + uuidv4();
      items.push({
        id: ifc.id,
        label: addBtn.label,
        icon: 'pi pi-fw pi-plus',
        routerLink: [link],
      });
    });

    // Only show the "New" section when there are applicable interfaces.
    return items.length > 0 ? { label: 'New', items } : null;
  }

  /* The items that appear side by side in the horizontal menu bar: children of
   * root sections (e.g. Main menu, New, Admin) plus loose root-level items */
  private barItems(): MenuItem[] {
    const out: MenuItem[] = [];
    for (const entry of this.model) {
      if (entry === this.moreSection) {
        continue;
      }
      if (entry.items != null) {
        out.push(...entry.items);
      } else {
        out.push(entry);
      }
    }
    return out;
  }

  /* Priority+ overflow for horizontal menu mode: items that do not fit the bar
   * width move into a "More" dropdown at the end, so every item stays reachable.
   * With remeasure, item widths are read from the DOM again (after the full bar
   * has rendered); otherwise the widths of the previous measurement are reused. */
  private updateOverflow(remeasure: boolean) {
    // Restore the full bar first
    if (this.moreSection != null) {
      const index = this.model.indexOf(this.moreSection);
      if (index !== -1) {
        this.model.splice(index, 1);
      }
      this.moreSection = null;
    }
    this.barItems().forEach((item) => {
      if (item.visible === false) {
        item.visible = true;
      }
    });

    if (!this.layoutService.isHorizontal()) {
      this.fullBarWidths = [];
      this.measuring = false;
      return;
    }

    // Measure and hide overflowing items after the full bar has rendered.
    // While measuring, the bar clips its overflow (CSS), so nothing wraps
    // out of view meanwhile.
    this.measuring = true;
    setTimeout(() => {
      this.measuring = false;
      if (!this.layoutService.isHorizontal()) {
        return;
      }
      const bar = (this.el.nativeElement as HTMLElement).querySelector(
        'ul.layout-menu',
      );
      if (bar == null) {
        return;
      }
      if (remeasure || this.fullBarWidths.length === 0) {
        const itemElements = bar.querySelectorAll(
          ':scope > li:not(.layout-root-menuitem), :scope > li.layout-root-menuitem > ul > li',
        );
        this.fullBarWidths = Array.from(itemElements).map(
          (itemEl) => (itemEl as HTMLElement).offsetWidth,
        );
      }
      const items = this.barItems();
      if (items.length !== this.fullBarWidths.length) {
        return; // DOM and model out of sync; skip this round
      }

      const barWidth = (bar as HTMLElement).clientWidth;
      const totalWidth = this.fullBarWidths.reduce((sum, w) => sum + w, 0);
      if (totalWidth <= barWidth) {
        return; // everything fits
      }

      const budget = barWidth - AppMenuComponent.MORE_RESERVE_PX;
      let cumulative = 0;
      let fit = 0;
      for (const width of this.fullBarWidths) {
        if (cumulative + width > budget) {
          break;
        }
        cumulative += width;
        fit++;
      }

      const overflowed = items.slice(fit);
      overflowed.forEach((item) => (item.visible = false));
      this.moreSection = {
        items: [
          {
            label: 'More',
            icon: 'pi pi-fw pi-ellipsis-h',
            items: overflowed.map((item) => ({ ...item, visible: true })),
          },
        ],
      };
      this.model.push(this.moreSection);
    });
  }

  /* Depth-first search for an item by id, over the whole menu tree */
  private findItemById(
    items: MenuItem[],
    id: string | undefined,
  ): MenuItem | undefined {
    if (id == null) {
      return undefined;
    }
    for (const item of items) {
      if (item.id === id) {
        return item;
      }
      const found = item.items ? this.findItemById(item.items, id) : undefined;
      if (found != null) {
        return found;
      }
    }
    return undefined;
  }

  addItemToParent(parentItem: MenuItem, menuItem: MenuItem) {
    menuItem.fragment = undefined; // removes temporary fragment
    if (parentItem.items == null) {
      // items was still undefined
      parentItem.items = [menuItem];
    } else {
      // items has been defined. Add to array
      parentItem.items.push(menuItem);
    }
  }
}
