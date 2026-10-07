<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Rule;

/**
 * The shape class of a conjunct's violation query, as the compiler publishes it
 *
 * The compiler derives this from the normalized violation term and the table layout
 * (Ampersand issue #1692, contract DC-17). It says what the query's cost does when the
 * database grows; it does not say what the cost is right now. That second half is the
 * runtime's, and lives in CostGate.
 */
enum CostClass: string
{
    /**
     * The table layout already enforces the property this conjunct checks, so its
     * violation set is empty in every state the schema admits and no query is needed.
     * Rests on proof-track claim PRF-8 (status: stated).
     */
    case Structural = 'structural';

    /**
     * The term holds a Kleene closure. Measured to explode at toy sizes (three orders of
     * magnitude below a production population), so the integral route is never a safe bet.
     */
    case Recursive = 'recursive';

    /**
     * The term is pinned to a named atom, so the query is an index probe: flat in the
     * database size, and never worth the fee of incremental maintenance.
     */
    case Anchored = 'anchored';

    /**
     * The query reads its scan tables in full. Whether that is expensive depends on how
     * large those tables have grown, which only the runtime knows.
     */
    case Scan = 'scan';
}
