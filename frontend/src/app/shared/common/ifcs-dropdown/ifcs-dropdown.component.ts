import { CommonModule } from '@angular/common';
import { Component, Inject, Input, OnInit } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { ButtonModule } from 'primeng/button';
import { OverlayPanelModule } from 'primeng/overlaypanel';
import {
  INTERFACE_ROUTE_MAPPING_TOKEN,
  InterfaceRouteMap,
} from 'src/app/config';
import { ObjectBase } from 'src/app/shared/objectBase.interface';

export type ExternalLink = {
  label: string;
  url: string;
  iconClass?: string;
};

/**
 * Create a dropdown that lists all links in a standard `_ifcs_` array on an Object.
 */
@Component({
  selector: 'app-ifcs-dropdown',
  templateUrl: './ifcs-dropdown.component.html',
  styleUrls: ['./ifcs-dropdown.component.scss'],
  standalone: true,
  imports: [CommonModule, OverlayPanelModule, RouterLink, ButtonModule],
})
export class IfcsDropdownComponent implements OnInit {
  @Input() resource?: ObjectBase;
  @Input() size?: 'small' | 'medium' = 'medium';
  @Input() externalLinks: ExternalLink[] = [];

  // This is a workaround. The values from input property externalLinks are copied into the processedExternalLinks in the ngOnInit()
  // I don't understand why this is needed, but when using the externalLinks property directly in the template, the links don't work
  public processedExternalLinks: ExternalLink[] = [];

  constructor(
    @Inject(INTERFACE_ROUTE_MAPPING_TOKEN) public routeMap: InterfaceRouteMap,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.processedExternalLinks = this.externalLinks ?? [];
  }

  /**
   * Returns the router commands for navigating to the given interface.
   * SESSION/ONE-scope routes have no /:id segment in their config, so we navigate
   * to the base path only. Regular-scope routes include resource._id_.
   */
  getRouteCommands(ifcId: string): (string | undefined)[] {
    const basePath = this.routeMap[ifcId];
    if (!basePath) return ['/'];
    if (this.routeTakesId(basePath)) {
      return ['/' + basePath, this.resource?._id_];
    }
    return ['/' + basePath];
  }

  /**
   * Returns true if the given base route path expects an :id segment,
   * i.e. the route is registered as `{base}/:id` in the router config.
   * SESSION/ONE-scope interfaces have no such route and return false.
   */
  private routeTakesId(basePath: string): boolean {
    const path = basePath.startsWith('/') ? basePath.slice(1) : basePath;
    return this.findRouteWithId(this.router.config, path);
  }

  private findRouteWithId(
    routes: { path?: string; children?: unknown[] }[],
    targetBase: string,
  ): boolean {
    for (const route of routes) {
      if (route.path === `${targetBase}/:id`) return true;
      if (route.children) {
        if (
          this.findRouteWithId(
            route.children as { path?: string; children?: unknown[] }[],
            targetBase,
          )
        )
          return true;
      }
    }
    return false;
  }

  /**
   * @returns only ifcs that don't point to the current page.
   */
  filteredIfcs() {
    if (!this.resource || !this.resource._ifcs_) return [];

    if (!this.resource._ifcs_ || !Array.isArray(this.resource._ifcs_))
      return [];

    return this.resource._ifcs_.filter((ifc) => {
      const commands = this.getRouteCommands(ifc.id);
      const targetPath = commands.filter(Boolean).join('/');
      return document.location.pathname !== targetPath;
    });
  }

  /**
   * If there is exactly one filtered interface and no external links,
   * navigate directly without showing the dropdown.
   * Otherwise toggle the overlay panel.
   */
  handleClick(
    event: Event,
    dropdown: { toggle: (event: Event) => void },
  ): void {
    const ifcs = this.filteredIfcs();
    if (ifcs.length === 1 && this.processedExternalLinks.length === 0) {
      this.router.navigate(this.getRouteCommands(ifcs[0].id));
      return;
    }
    dropdown.toggle(event);
  }
}
