import { Inject, Injectable, Optional } from '@angular/core';
import {
  ActivatedRouteSnapshot,
  CanActivate,
  Router,
  UrlTree,
} from '@angular/router';
import { InterfaceRouteMap, INTERFACE_ROUTE_MAPPING_TOKEN } from '../../config';

/**
 * Guard for the catch-all ':segment/:id' route.
 *
 * SESSION/ONE-scope interfaces have no /:id variant in their route config
 * (e.g. 'clinics' is registered, but never 'clinics/:id'). When a URL like
 * '/clinics/{uuid}' is produced (e.g. by an old routerLink or a directly typed
 * URL), Angular finds no direct match and falls through to ':segment/:id'. This
 * guard redirects it to the base path '/clinics' so the user lands on the
 * correct page rather than a 404.
 *
 * OBJECT-scope routes (e.g. 'prototypecontext-editinterface/:id') match their
 * own route directly and never reach this catch-all.
 *
 * Uses INTERFACE_ROUTE_MAPPING_TOKEN as the primary source of known paths, with
 * a fallback to router.config for robustness. Both are available by the time
 * this guard fires because all eagerly-loaded module routes are registered
 * before the router processes its initial navigation.
 */
@Injectable({ providedIn: 'root' })
export class SessionRouteRedirectGuard implements CanActivate {
  private readonly knownBasePaths: Set<string>;

  constructor(
    private router: Router,
    @Optional()
    @Inject(INTERFACE_ROUTE_MAPPING_TOKEN)
    routeMap: InterfaceRouteMap | null,
  ) {
    // Build the set of known base paths from the interface-to-route map.
    // e.g. '/clinics' → 'clinics'. Object-scope routes like
    // '/prototypecontext-editinterface' are included too, but those URLs arrive
    // as 'prototypecontext-editinterface/:id' and match that route before this
    // guard ever sees them.
    this.knownBasePaths = routeMap
      ? new Set(
          Object.values(routeMap)
            .filter((p): p is string => typeof p === 'string' && p.length > 0)
            .map((p) => (p.startsWith('/') ? p.slice(1) : p)),
        )
      : new Set();
  }

  canActivate(route: ActivatedRouteSnapshot): boolean | UrlTree {
    const segment = route.params['segment'];

    // Primary: token-derived set. Fallback: router.config at call-time
    // (all forChild routes are registered by now even if the set is empty).
    const isKnown =
      this.knownBasePaths.has(segment) ||
      this.router.config.some((r) => r.path === segment);

    return isKnown
      ? this.router.createUrlTree(['/' + segment])
      : this.router.createUrlTree(['/404']);
  }
}
