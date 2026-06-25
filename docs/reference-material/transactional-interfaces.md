---
title: Transactional Interfaces
---

# Transactional Interfaces

An interface runs in one of two transaction modes. The mode decides when the user's
edits reach the database.

- **Transactional** (default): the interface buffers the user's edits. Nothing is
  committed until the user presses **SAVE**. **CANCEL**, or navigating away, discards
  the buffer. SAVE only becomes active when the buffered edits violate no invariant
  rules.
- **Direct**: every edit commits immediately once it leaves no invariant rule
  violated. This is the framework's original behaviour.

The transaction boundary is a single interface. One root interface renders per route,
so one transaction is open at a time and one SAVE/CANCEL bar suffices.

## How Transactional mode works

The framework buffers edits on the client. It does **not** hold a database transaction
open across requests.

1. While the user edits a field, the interface accumulates the patch operations
   instead of sending them. The atomic component already shows the new value through
   Angular binding, so the edit is visible.
2. After each edit the interface runs a **dry run**: it sends the buffered operations to
   the backend with `?dryRun=true`. The backend applies them in a transaction, checks
   the invariant rules, and rolls back. The response's `invariantRulesHold` decides
   whether SAVE is enabled.
3. **SAVE** sends the buffered operations as one PATCH request. That is one real
   database transaction: it commits when the invariant rules hold.
4. **CANCEL** clears the buffer and re-fetches the server state, discarding the local
   edits. Navigating away triggers a route guard ("Lose your edits?") that does the
   same on confirm.

A **PROPBUTTON** is an explicit action. In Transactional mode a click buffers the
button's own operation and then flushes the whole buffer — the button acts as the SAVE.
This keeps single-click forms (such as a login form) working: the user fills the fields
and one button click commits everything as one transaction.

### Scope (current)

Buffering covers field and link edits (`patch`). Creating or deleting a whole atom
(`POST` / `DELETE`) still commits immediately, because a buffered create needs a
server-assigned id. List add/remove shows its result after SAVE, not optimistically.

## Setting the mode

`TransactionModeService` resolves the mode for an interface. Resolution order, most
specific first:

1. a runtime override set with `setOverride(interfaceName, mode)`,
2. the mode declared on the interface (later supplied by the compiler through the box
   header), and
3. the global default, initialised to `Transactional` and changeable with
   `setDefault(mode)`.

The override and the default are changeable during the operational life of the
application.

## Where the parts live

| Part | Location |
| --- | --- |
| Buffer, `save`/`cancel`/`commitAction`, dry-run validation | `frontend/src/app/shared/interfacing/ampersand-interface.class.ts` |
| Mode resolution | `frontend/src/app/shared/services/transaction-mode.service.ts` |
| Active-interface registry for the bar | `frontend/src/app/shared/services/transaction.service.ts` |
| Global SAVE/CANCEL bar | `frontend/src/app/layout/transaction-bar/` |
| "Lose your edits?" route guard | `frontend/src/app/shared/guards/unsaved-changes.guard.ts`, attached to every generated route via `frontend/src/app/generated/.templates/project.module.ts.txt` |
| PROPBUTTON flush-on-click | `frontend/src/app/shared/box-components/box-prop-button/box-prop-button.component.ts` |
| Dry-run on the PATCH endpoint | `backend/src/Ampersand/Controller/ResourceController.php` (`?dryRun=`, on the existing `Transaction::dryRun()`) |
