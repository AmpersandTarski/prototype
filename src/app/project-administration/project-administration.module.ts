import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActiveProjectsComponent } from './active-projects/active-projects.component';
import { SharedModule } from '../shared/shared.module';
import { ProjectComponent } from './project/project.component';
import { PersonComponent } from './person/person.component';
import { PeopleComponent } from './people/people.component';
import { BackendService } from './backend.service';
import { RouterModule, Routes } from '@angular/router';
import { AppLayoutComponent } from '../layout/app.layout.component';
import { MenuItem } from 'primeng/api';
import { ProjectEditComponent } from './project-edit/project-edit.component';
import { PersonEditComponent } from './person-edit/person-edit.component';

const routes: Routes = [
  {
    path: 'p',
    component: AppLayoutComponent,
    children: [
      { path: 'active-projects', component: ActiveProjectsComponent },
      { path: 'project', component: ProjectComponent },
      { path: 'project/:id', component: ProjectComponent },
      { path: 'people', component: PeopleComponent },
      { path: 'person/:id/detail', component: PersonComponent },
      { path: 'person/:id', component: PersonEditComponent },
      { path: 'new_47_edit_32_project/:id', component: ProjectEditComponent },
    ],
  },
];

export const menuItems: MenuItem[] = [
  {
    label: 'Project administration',
    items: [
      {
        label: 'Active projects',
        icon: 'pi pi-fw pi-bars',
        routerLink: ['/p/active-projects'],
      },
      {
        label: 'Project details',
        icon: 'pi pi-fw pi-id-card',
        routerLink: ['/p/project'],
      },
      {
        label: 'People',
        icon: 'pi pi-fw pi-bars',
        routerLink: ['/p/people'],
      },
    ],
  },
];

@NgModule({
  declarations: [
    ActiveProjectsComponent,
    ProjectComponent,
    PersonEditComponent,
    PersonComponent,
    PeopleComponent,
    ProjectEditComponent,
  ],
  imports: [CommonModule, SharedModule, RouterModule.forChild(routes)],
  providers: [{ provide: BackendService, useClass: BackendService }],
})
export class ProjectAdministrationModule {}
