import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ActiveProjectsInterface } from './active-projects/active-projects.interface';
import { ProjectInterface } from './project/project.interface';
import { IBackendService } from './backend.service.interface';
import { ListAllInterfacesInterface } from './list-all-interfaces/list-all-interfaces.interface';
import { PeopleInterface } from './people/people.interface';
import { PersonInterface } from './person/person.interface';
import { InactiveProjectsInterface } from './inactive-projects/inactive-projects.interface';

@Injectable()
export class BackendService implements IBackendService {
  constructor(private http: HttpClient) {}

  public getActiveProjects(): Observable<ActiveProjectsInterface[]> {
    return this.http.get<ActiveProjectsInterface[]>('resource/SESSION/1/Active_32_projects');
  }

  public getInactiveProjects(): Observable<InactiveProjectsInterface[]> {
    return this.http.get<InactiveProjectsInterface[]>('resource/SESSION/1/Inactive_32_projects');
  }

  public getPeople(): Observable<PeopleInterface[]> {
    return this.http.get<PeopleInterface[]>('resource/SESSION/1/People');
  }

  public getProject(id: string): Observable<ProjectInterface> {
    return this.http.get<ProjectInterface>(`resource/Project/${id}/Project`);
  }

  public getPerson(id: string): Observable<PersonInterface> {
    return this.http.get<PersonInterface>(`resource/Person/${id}/Person`);
  }

  getAllInterfaces(): Observable<ListAllInterfacesInterface[]> {
    return this.http.get<ListAllInterfacesInterface[]>('resource/SESSION/1/List_32_all_32_interfaces');
  }

  public patchProject(id: string, data: any): Observable<ProjectInterface> {
    return this.http.patch<ProjectInterface>(`resource/Project/${id}/New_47_edit_32_project`, data);
  }

  public patchPerson(id: string, data: any): Observable<PersonInterface> {
    return this.http.patch<PersonInterface>(`resource/Person/${id}/Person`, data);
  }

  public postPerson(): Observable<{ _id_: string }> {
    return this.http.post<{ _id_: string }>(`resource/Person`, {});
  }
}
