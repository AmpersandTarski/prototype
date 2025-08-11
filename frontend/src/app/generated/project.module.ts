/*
 * Generated code by Ampersand compiler
 * File:        project.module.ts
 * Template:    project.module.ts.txt
 * Context:     HelloWorld
 */

// Imports
import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Routes } from '@angular/router';
import { MenuItem } from 'primeng/api';
import { BackendService } from './backend.service';
import { SharedModule } from '../shared/shared.module';
import { InterfaceRouteMap, INTERFACE_ROUTE_MAPPING_TOKEN } from '../config';
import { TabViewModule } from 'primeng/tabview';
import { TableModule } from 'primeng/table';

import { PaginametdropwdownComponent } from './paginametdropwdown/paginametdropwdown.component';
import { PrototypecontextEditinterfaceComponent } from './prototypecontext-editinterface/prototypecontext-editinterface.component';
import { PrototypecontextEditmenuitemComponent } from './prototypecontext-editmenuitem/prototypecontext-editmenuitem.component';
import { PrototypecontextEditnavigationmenuComponent } from './prototypecontext-editnavigationmenu/prototypecontext-editnavigationmenu.component';
import { PrototypecontextListallinterfacesComponent } from './prototypecontext-listallinterfaces/prototypecontext-listallinterfaces.component';

// Routes
const routes: Routes = [
  {
    path: 'paginametdropwdown',
    component: PaginametdropwdownComponent,
    title: 'PaginaMetDropwdown',
  },
  {
    path: 'prototypecontext-editinterface/:id',
    component: PrototypecontextEditinterfaceComponent,
    title: 'Edit interface',
  },
  {
    path: 'prototypecontext-editmenuitem/:id',
    component: PrototypecontextEditmenuitemComponent,
    title: 'Edit menu item',
  },
  {
    path: 'prototypecontext-editnavigationmenu',
    component: PrototypecontextEditnavigationmenuComponent,
    title: 'Edit navigation menu',
  },
  {
    path: 'prototypecontext-listallinterfaces',
    component: PrototypecontextListallinterfacesComponent,
    title: 'List all interfaces',
  },
  
];

// Menu
export const menuItems: MenuItem[] = [
  {
    label: 'Project',
    items: [
      {
        label: 'PaginaMetDropwdown',
        icon: 'pi pi-fw pi-bars',
        routerLink: ['/paginametdropwdown'],
      },
      {
        label: 'Edit navigation menu',
        icon: 'pi pi-fw pi-bars',
        routerLink: ['/prototypecontext-editnavigationmenu'],
      },
      {
        label: 'List all interfaces',
        icon: 'pi pi-fw pi-bars',
        routerLink: ['/prototypecontext-listallinterfaces'],
      },
    ],
  },
];

// Interface to route mapping
const INTERFACE_ROUTE_MAP: InterfaceRouteMap = {
  'PaginaMetDropwdown': '/paginametdropwdown',
  'PrototypeContext.Editinterface': '/prototypecontext-editinterface',
  'PrototypeContext.Editmenuitem': '/prototypecontext-editmenuitem',
  'PrototypeContext.Editnavigationmenu': '/prototypecontext-editnavigationmenu',
  'PrototypeContext.Listallinterfaces': '/prototypecontext-listallinterfaces',
};

// Module
@NgModule({
  declarations: [
    PaginametdropwdownComponent,
    PrototypecontextEditinterfaceComponent,
    PrototypecontextEditmenuitemComponent,
    PrototypecontextEditnavigationmenuComponent,
    PrototypecontextListallinterfacesComponent,
  ],
  exports: [
    PaginametdropwdownComponent,
    PrototypecontextEditinterfaceComponent,
    PrototypecontextEditmenuitemComponent,
    PrototypecontextEditnavigationmenuComponent,
    PrototypecontextListallinterfacesComponent,
  ],
  imports: [
    CommonModule,
    SharedModule,
    RouterModule.forChild(routes),
    TabViewModule,
    TableModule,
  ],
  providers: [
    { provide: BackendService, useClass: BackendService },
    { provide: INTERFACE_ROUTE_MAPPING_TOKEN, useValue: INTERFACE_ROUTE_MAP },
  ],
})
export class ProjectModule {}