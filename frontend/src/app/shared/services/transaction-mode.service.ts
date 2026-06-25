import { Injectable } from '@angular/core';

/**
 * Per-interface transaction behaviour.
 * - `Transactional`: edits are buffered in the interface; a DB transaction is only
 *   committed when the user clicks SAVE (and no invariant rules are violated).
 *   CANCEL or navigating away discards the buffer (rollback).
 * - `Direct`: every edit is committed immediately (the framework's original behaviour).
 */
export type TransactionMode = 'Transactional' | 'Direct';

/**
 * Resolves the transaction mode for an interface.
 *
 * Resolution order (most specific wins):
 *   1. a runtime override set via {@link setOverride} (changeable during operation),
 *   2. the mode declared on the interface (later supplied by the compiler via
 *      `BoxHeader.keyVals`, passed in as `declared`),
 *   3. the global default (initialised to `Transactional`).
 */
@Injectable({ providedIn: 'root' })
export class TransactionModeService {
  private defaultMode: TransactionMode = 'Transactional';
  private overrides = new Map<string, TransactionMode>();

  public getDefault(): TransactionMode {
    return this.defaultMode;
  }

  public setDefault(mode: TransactionMode): void {
    this.defaultMode = mode;
  }

  public getMode(
    interfaceName?: string,
    declared?: TransactionMode,
  ): TransactionMode {
    if (interfaceName && this.overrides.has(interfaceName)) {
      return this.overrides.get(interfaceName)!;
    }
    return declared ?? this.defaultMode;
  }

  public setOverride(interfaceName: string, mode: TransactionMode): void {
    this.overrides.set(interfaceName, mode);
  }

  public clearOverride(interfaceName: string): void {
    this.overrides.delete(interfaceName);
  }
}
