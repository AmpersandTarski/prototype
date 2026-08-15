import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { SearchResponse } from './search.model';

/**
 * Talks to the framework's `/api/v1/search` endpoint. The `api/v1/` prefix is added by the
 * global backend interceptor, so only the `search` path is given here.
 */
@Injectable({
  providedIn: 'root',
})
export class SearchService {
  constructor(private http: HttpClient) {}

  search(term: string): Observable<SearchResponse> {
    return this.http.get<SearchResponse>('search', {
      params: new HttpParams().set('q', term),
    });
  }
}
