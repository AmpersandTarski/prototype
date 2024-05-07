import { Observable } from 'rxjs';

export interface IPopulationService {
  getExportPopulation(): Observable<object>;

  getExportPopulationMetaModel(): Observable<object>;

  exportPopulation(jsonResponse: object): void;

  importPopulation(file: File): void;
}
