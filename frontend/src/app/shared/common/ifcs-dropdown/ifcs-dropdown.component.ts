import { CommonModule } from '@angular/common';
import { Component, Inject, Input, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ButtonModule } from 'primeng/button';
import { OverlayPanelModule } from 'primeng/overlaypanel';
import { INTERFACE_ROUTE_MAPPING_TOKEN, InterfaceRouteMap } from 'src/app/config';
import { ObjectBase } from 'src/app/shared/objectBase.interface';

export type ExternalLink = {
  label: string;
  url: string;
  iconClass?: string;
};

/**
 * Create a dropdown that lists all links in a standard `_ifcs_ array on an Object.
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

  constructor(@Inject(INTERFACE_ROUTE_MAPPING_TOKEN) public routeMap: InterfaceRouteMap) {}

  ngOnInit(): void {
    this.processedExternalLinks = this.externalLinks ?? [];
  }

  /**
   * @returns only ifcs that don't point to current page.
   */
  filteredIfcs() {
    if (!this.resource) return [];

    return this.resource._ifcs_.filter((ifc) => {
      return document.location.pathname != `${this.routeMap[ifc.id]}/${this.resource?._id_}`;
    });
  }
}
