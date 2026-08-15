import { InterfaceRef, ObjectBase } from 'src/app/shared/objectBase.interface';

/**
 * One field in which the search term was found, kept for UI context.
 */
export interface SearchMatch {
  /** Name of the relation (field) whose value matched. */
  field: string;
  /** The matched value. */
  value: string;
}

/**
 * A single search result: an entity atom that contains the search term in one of its fields.
 *
 * Extends {@link ObjectBase} (`_id_`, `_label_`, `_ifcs_`, …) so it plugs into the existing
 * interface-navigation components without translation.
 */
export interface SearchResult extends ObjectBase {
  /** Human-readable label of the atom's concept (e.g. "Product"). */
  concept: string;
  /** Technical name of the atom's concept. */
  conceptId: string;
  /** Interfaces in which this atom can be opened. */
  _ifcs_: Array<InterfaceRef>;
  /** Fields in which the term was found. */
  matches: Array<SearchMatch>;
}

export interface SearchResponse {
  /** The search term as interpreted by the backend. */
  term: string;
  /** True when the result set was capped; the user should refine the term. */
  truncated: boolean;
  results: Array<SearchResult>;
}

/**
 * Search results grouped by concept for presentation.
 */
export interface SearchResultGroup {
  concept: string;
  items: Array<SearchResult>;
}
