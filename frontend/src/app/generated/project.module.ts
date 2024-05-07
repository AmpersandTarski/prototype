/*
 * This is a dummy module. It will be overwritten by the compiler when
 * generating a prototype application.
 *
 * This module and exported routes and menuItems constants are needed to
 * integrate with other non-generated parts of the framework.
 */

// Imports
import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Routes } from '@angular/router';
import { MenuItem } from 'primeng/api';
import { InterfaceRouteMap, INTERFACE_ROUTE_MAPPING_TOKEN } from '../config';

// Routes
const routes: Routes = [];

// Menu
export const menuItems: MenuItem[] = [];

// Interface to route mapping
const INTERFACE_ROUTE_MAP: InterfaceRouteMap = {};

// Module
@NgModule({
  declarations: [],
  exports: [],
  imports: [CommonModule, RouterModule.forChild(routes)],
  providers: [{ provide: INTERFACE_ROUTE_MAPPING_TOKEN, useValue: INTERFACE_ROUTE_MAP }],
})
export class ProjectModule {}
