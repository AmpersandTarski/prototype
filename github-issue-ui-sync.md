## Problem

Users sometimes need to press Cmd+R (browser refresh) to make the screen reflect the actual database state after a mutation (create, update, or delete). This happens because Angular's data binding only covers part of the synchronisation story. Four gaps in the current implementation cause stale UI state.

### Gap 1 — `sessionRefreshAdvice` triggers a full-page reload

**Backend** (`backend/src/Ampersand/Frontend/AngularJSApp.php`, line 141):

```php
public function getSessionRefreshAdvice(): bool
{
    // Returns true when ANY committed transaction modifies a relation
    // whose src or tgt concept is SESSION
    foreach (array_unique($affectedRelations) as $relation) {
        if ($relation->srcConcept->isSession() || $relation->tgtConcept->isSession()) {
            return true;
        }
    }
    return false;
}
```

**Frontend** (`frontend/src/app/backend/http-interceptors/logging-interceptor.ts`, line 54):

```typescript
if (patchResponse.sessionRefreshAdvice) {
    sessionStorage.clear();
    await new Promise((f) => setTimeout(f, 4000));
    window.location.reload();   // ← full browser reload, outside Angular
}
```

This class is named `AngularJSApp` — a leftover from the AngularJS era. The full-page reload pattern was ported to Angular without rethinking it. It fires for every operation that touches a SESSION relation (role toggle, login/logout, or any ExecEngine rule writing to SESSION). Angular discards all component state and refetches everything from scratch.

This is intentional for _some_ cases (role changes genuinely require a full refresh of the menu and permissions). But it is overly broad: not every SESSION write requires a full teardown of the app.

### Gap 2 — `post()` does not merge exec-engine side effects

`AmpersandInterfaceComponent.patch()` uses `mergeDeep` to update the full resource after every PATCH:

```typescript
// ampersand-interface.class.ts, line 149
mergeDeep(this.resource.data, resp.content);
```

`AmpersandInterfaceComponent.post()` does not — it just returns the Observable:

```typescript
// ampersand-interface.class.ts, line 67
public post(path: string): Observable<CreateResponse> {
    return this.http.post<CreateResponse>(path, {}).pipe(takeUntil(this.destroy$));
}
```

`BaseBoxComponent.createItem()` then manually appends only the new item:

```typescript
// BaseBoxComponent.class.ts, line 74
this.interfaceComponent.post(path).pipe(takeUntil(this.destroy$)).subscribe((x) => {
    this.resource[propertyField].unshift(x.content as TItem);
});
```

If the ExecEngine modifies other fields in the interface after the POST (derived attributes, counters, related objects), those changes are invisible until the user refreshes.

### Gap 3 — `patch()` updates the patched resource only, not sibling items in a list

When the interface data is a list, `patch()` finds the patched item by `_path_` and merges only that item:

```typescript
// ampersand-interface.class.ts, line 141
if (Array.isArray(this.resource.data)) {
    mergeDeep(
        this.resource.data.find((obj) => obj._path_ === rootPath.toString()),
        resp.content,
    );
}
```

If the ExecEngine modifies a _different_ item in the same list (e.g., a status flag on a sibling row), that change is invisible until the user refreshes.

### Gap 4 — `delete()` re-fetches data but cannot remove items from the array

`AmpersandInterfaceComponent.delete()` does issue a GET after every DELETE to retrieve fresh data, then calls `mergeDeep`. However, `mergeDeep` _updates_ existing items — it does not _remove_ items that are no longer present in the backend response:

```typescript
// ampersand-interface.class.ts, line 186
for (const fresh of freshData as unknown as ObjectBase[]) {
    const existing = (this.resource.data as ObjectBase[]).find(
        (obj) => obj._path_ === fresh._path_,
    );
    if (existing) mergeDeep(existing, fresh);
    // ← items absent from freshData are never removed from the array
}
```

If the ExecEngine deletes additional items as a cascade or side effect, they remain in the UI.

### Gap 5 — `navTo` is defined but never acted upon

Every PATCH/POST/DELETE response includes a `navTo` field. The backend can set this via `$this->frontend->setNavToResponse(...)` to redirect the user after a commit or rollback. The Angular frontend defines the field in its response interfaces but never routes to it:

```typescript
// patch-response.interface.ts
export interface PatchResponse<T> {
    // ...
    navTo: string | null;   // ← read nowhere in the Angular codebase
}
```

## Requirements

- After every PATCH, POST, or DELETE — including ExecEngine side effects — Angular renders the current database state without requiring a manual refresh.
- SESSION-related full-page reloads are limited to operations that actually require a full app teardown (role/permission changes, login/logout). Data-only SESSION writes do not trigger a reload.
- Items removed by a DELETE or a cascading ExecEngine rule disappear from the UI without a manual refresh.
- `navTo` directs Angular Router to the specified route when the backend sets it.

## Design Choices

### Fetching the full interface after every mutation

The root cause of gaps 2, 3, and 4 is that the frontend updates the UI from the mutation response content, which covers only the directly mutated resource. The `delete()` method already shows the right approach: it issues a GET of the full interface after the mutation and uses the result to update Angular's data.

The question is scope. Two options:

**Option A — Always re-fetch the full interface after mutation.**
After every POST/PATCH/DELETE, issue a GET for the root resource path and replace `this.resource.data` entirely (using `mergeDeep` for PATCH/POST, and a full replacement for DELETE so removed items disappear).

Advantage: simple, always correct.
Disadvantage: one extra HTTP round-trip per mutation; the PATCH response already contains the mutated resource's new state (the extra GET is redundant for the simple case).

**Option B — Widen the PATCH response to include the full interface.**
Modify `ResourceController::putPatchPostResource()` to `$entry->get()` (the root) instead of `$resource->get()` (the patched leaf). The response content then covers all sibling items. For `post()`, apply the same change.

Advantage: no extra round-trip.
Disadvantage: requires backend change; the response payload grows for large interfaces.

**Option C — Hybrid: use Option B for the common case, keep the GET fallback for DELETE.**
PATCH and POST already trigger the ExecEngine and return content; widening that content costs nothing extra. DELETE already issues a GET (it has no meaningful response content).

This is the recommended approach.

### Limiting `sessionRefreshAdvice` to permission-relevant changes

`getSessionRefreshAdvice()` should return `true` only when the committed transaction affects relations that change _what the user can see or do_ — i.e., `sessionActiveRoles`, `sessionAllowedRoles`, or `sessionAccount`. Writes to other SESSION relations (e.g., `sessionLastAccess`, or application-specific session variables) should not trigger a reload.

The existing `$skipRels` list already excludes `sessionLastAccess`. Extend it to a whitelist approach: only trigger on the known permission-relevant relations.

### Implementing `navTo`

Add handling in `logging-interceptor.ts` (or a dedicated service): when `patchResponse.navTo` is non-null, call `this.router.navigateByUrl(patchResponse.navTo)`.

## Solution

### 1. Backend: widen PATCH/POST response to cover the root resource

In `ResourceController::putPatchPostResource()`, replace:

```php
// Current (returns only the leaf resource)
$content = $resource->get($options, $depth);
```

with:

```php
// Proposed (returns the root resource, covering all sibling items)
$content = $entry->get($options, $depth);
```

This makes the PATCH/POST response contain the full interface data, equivalent to what a GET of `resource/{type}/{id}/{ifcPath}` would return.

### 2. Frontend: replace `mergeDeep` with a full replace for list responses after mutation

In `AmpersandInterfaceComponent.patch()`, when `this.resource.data` is an array, replace the single-item `mergeDeep` with a strategy that:
- Updates existing items that are still present (via `mergeDeep`)
- Adds items that are new in the response
- Removes items that are absent from the response

```typescript
// Proposed replacement for the Array branch in patch() tap operator:
if (Array.isArray(this.resource.data)) {
    const freshList = resp.content as unknown as ObjectBase[];
    // Remove items no longer present
    this.resource.data = (this.resource.data as ObjectBase[]).filter(
        (existing) => freshList.some((fresh) => fresh._path_ === existing._path_)
    );
    // Update or add
    for (const fresh of freshList) {
        const existing = (this.resource.data as ObjectBase[]).find(
            (obj) => obj._path_ === fresh._path_
        );
        if (existing) {
            mergeDeep(existing, fresh);
        } else {
            (this.resource.data as ObjectBase[]).push(fresh);
        }
    }
}
```

### 3. Frontend: apply the same full-replace strategy in `delete()`

The `delete()` method already issues a GET. Apply the same list-replace strategy (step 2) there, replacing the current `mergeDeep`-only loop.

### 4. Frontend: make `post()` merge the full response into `resource.data`

Move the merge logic from `BaseBoxComponent.createItem()` into `AmpersandInterfaceComponent.post()`, equivalent to how `patch()` works. Apply the list-replace strategy from step 2.

### 5. Backend: narrow `sessionRefreshAdvice` to permission-relevant SESSION relations

In `AngularJSApp::getSessionRefreshAdvice()`, replace the current `$skipRels` denylist with an allowlist of relations that genuinely require a full app reload:

```php
// Proposed
static $reloadRels = [
    ProtoContext::REL_SESSION_ACTIVE_ROLES,
    ProtoContext::REL_SESSION_ALLOWED_ROLES,
    'sessionAccount[SESSION*Account]',
];

foreach (array_unique($affectedRelations) as $relation) {
    if (in_array($relation->getSignature(), $reloadRels)) {
        return true;
    }
}
return false;
```

### 6. Frontend: implement `navTo` routing

In `logging-interceptor.ts`, inject `Router` and navigate when `navTo` is set:

```typescript
if (patchResponse.navTo) {
    this.router.navigateByUrl(patchResponse.navTo);
}
```

## Alternatives

**Do nothing about `sessionRefreshAdvice`** — keep the full-page reload for all SESSION changes. Acceptable if SESSION writes in application scripts are rare. Low effort.

**WebSocket / server-sent events for live sync** — replace the request-response model with a push channel so backend changes (e.g., from another user) are reflected immediately. More powerful, but far more complex and out of scope for this issue.

**Polling** — periodically re-fetch the interface. Simple but causes unnecessary server load and introduces a lag.

## Files Modified/Created

**Backend:**
- `backend/src/Ampersand/Controller/ResourceController.php` — widen PATCH/POST response content to root resource (solution 1)
- `backend/src/Ampersand/Frontend/AngularJSApp.php` — narrow `sessionRefreshAdvice` to permission-relevant relations (solution 5)

**Frontend:**
- `frontend/src/app/shared/interfacing/ampersand-interface.class.ts` — full list-replace strategy in `patch()`, `post()`, and `delete()` (solutions 2, 3, 4)
- `frontend/src/app/shared/box-components/BaseBoxComponent.class.ts` — remove manual `unshift` from `createItem()` once `post()` handles the merge (solution 4)
- `frontend/src/app/backend/http-interceptors/logging-interceptor.ts` — implement `navTo` routing (solution 6)

## Impact

- Users no longer need to press Cmd+R to see exec-engine side effects after mutations.
- Role changes and login/logout still trigger a full-page reload (intentional).
- Application-specific SESSION writes no longer cause a disruptive full-page reload.
- `navTo` works as designed in the Ampersand language spec.
- The fix requires one backend change and targeted changes to three frontend files; no new dependencies.
