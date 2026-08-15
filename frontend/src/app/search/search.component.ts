import { CommonModule } from '@angular/common';
import { Component, Inject, OnDestroy, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { ProgressSpinnerModule } from 'primeng/progressspinner';
import {
  Subject,
  Subscription,
  catchError,
  debounceTime,
  distinctUntilChanged,
  of,
  switchMap,
  tap,
} from 'rxjs';
import {
  INTERFACE_ROUTE_MAPPING_TOKEN,
  InterfaceRouteMap,
} from 'src/app/config';
import { InterfaceRef } from 'src/app/shared/objectBase.interface';
import {
  SearchResponse,
  SearchResult,
  SearchResultGroup,
} from './search.model';
import { SearchService } from './search.service';

/**
 * Full-text search feature for the application home screen.
 *
 * Standalone and self-contained (requirement 5): it queries the framework's `/search` endpoint,
 * groups the found atoms by concept, and lets the user open each atom in any interface that can
 * display it (requirement 4) by reusing the application's interface route map — the same single
 * source of truth used by the rest of the UI.
 */
@Component({
  selector: 'app-search',
  templateUrl: './search.component.html',
  styleUrls: ['./search.component.scss'],
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    InputTextModule,
    ButtonModule,
    ProgressSpinnerModule,
    RouterLink,
  ],
})
export class SearchComponent implements OnInit, OnDestroy {
  /** Terms shorter than this are not searched (kept in sync with the backend). */
  private static readonly MIN_TERM_LENGTH = 2;

  public term = '';
  public loading = false;
  public response?: SearchResponse;
  public groups: Array<SearchResultGroup> = [];

  private readonly terms$ = new Subject<string>();
  private subscription?: Subscription;

  constructor(
    private service: SearchService,
    @Inject(INTERFACE_ROUTE_MAPPING_TOKEN) public routeMap: InterfaceRouteMap,
  ) {}

  ngOnInit(): void {
    this.subscription = this.terms$
      .pipe(
        debounceTime(300),
        distinctUntilChanged(),
        tap(() => (this.loading = true)),
        switchMap((term) =>
          this.service.search(term).pipe(
            catchError(() => of<SearchResponse>({ term, truncated: false, results: [] })),
          ),
        ),
      )
      .subscribe((response) => {
        this.response = response;
        this.groups = this.groupByConcept(response.results);
        this.loading = false;
      });
  }

  ngOnDestroy(): void {
    this.subscription?.unsubscribe();
  }

  /** Called on every keystroke; debounced searching happens through the stream. */
  onTermChange(term: string): void {
    if (term.trim().length < SearchComponent.MIN_TERM_LENGTH) {
      this.reset();
      return;
    }
    this.terms$.next(term.trim());
  }

  /** Immediate search (e.g. on Enter or the search button). */
  searchNow(): void {
    const term = this.term.trim();
    if (term.length < SearchComponent.MIN_TERM_LENGTH) {
      this.reset();
      return;
    }
    this.loading = true;
    this.service.search(term).subscribe({
      next: (response) => {
        this.response = response;
        this.groups = this.groupByConcept(response.results);
        this.loading = false;
      },
      error: () => {
        this.response = { term, truncated: false, results: [] };
        this.groups = [];
        this.loading = false;
      },
    });
  }

  /** Interfaces in which a result atom can actually be opened (those with a known route). */
  openableInterfaces(result: SearchResult): Array<InterfaceRef> {
    return result._ifcs_.filter((ifc) => !!this.routeMap[ifc.id]);
  }

  /**
   * Build a router link to open the given atom in the given interface. Normalises the route to a
   * single leading slash so it works regardless of whether the generated route map already
   * includes one.
   */
  routerLinkFor(ifc: InterfaceRef, atomId: string): Array<string> {
    const path = this.routeMap[ifc.id] ?? '';
    const normalized = path.startsWith('/') ? path : `/${path}`;
    return [normalized, atomId];
  }

  /** Comma-separated list of fields in which the term was found, for context. */
  matchSummary(result: SearchResult): string {
    return result.matches.map((m) => m.field).join(', ');
  }

  private reset(): void {
    this.response = undefined;
    this.groups = [];
    this.loading = false;
  }

  private groupByConcept(results: Array<SearchResult>): Array<SearchResultGroup> {
    const groups = new Map<string, SearchResultGroup>();
    for (const result of results) {
      let group = groups.get(result.concept);
      if (!group) {
        group = { concept: result.concept, items: [] };
        groups.set(result.concept, group);
      }
      group.items.push(result);
    }
    return Array.from(groups.values());
  }
}
