import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { EMPTY, Observable } from 'rxjs';
import { PatchResponse } from 'src/app/shared/interfacing/patch-response.interface';
import { IPopulationService } from './population.service.interface';

@Injectable()
export class PopulationService implements IPopulationService {
  private importUrl = 'admin/import';

  constructor(private http: HttpClient) {}

  public getExportPopulation(): Observable<object> {
    return this.http.get<object>('admin/exporter/export/all');
  }

  public getExportPopulationMetaModel(): Observable<object> {
    return this.http.get<object>('admin/exporter/export/metamodel');
  }

  public exportPopulation(jsonResponse: object): void {
    const currentDate = new Date().toISOString();

    // Creates a fake DOM and simulates the onClick to download the json file
    const theJSON = JSON.stringify(jsonResponse);
    const element = document.createElement('a');
    element.setAttribute(
      'href',
      'data:text/json;charset=UTF-8,' + encodeURIComponent(theJSON),
    );
    element.setAttribute(
      'download',
      `ProjectAdministatrion_population_${currentDate}.json`,
    );
    element.style.display = 'none';
    document.body.appendChild(element);
    element.click(); // simulate click
    document.body.removeChild(element);
  }

  /* Send one file to API. */
  public importPopulation(
    file: File | undefined,
  ): Observable<PatchResponse<JSON>> {
    if (file === undefined) {
      return EMPTY;
    }

    const formData = new FormData();
    formData.append('file', file, file.name);
    return this.http.post<PatchResponse<JSON>>(this.importUrl, formData);
  }
}
