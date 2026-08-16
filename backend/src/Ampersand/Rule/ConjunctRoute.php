<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Rule;

/**
 * How a conjunct's violations are obtained at the close of a transaction
 */
enum ConjunctRoute: string
{
    /**
     * Run no query: the table layout makes the violation set empty by construction
     */
    case Skip = 'skip';

    /**
     * Run the full violation query, as the framework has always done
     */
    case Integral = 'integral';

    /**
     * Maintain the violations from the transaction's changes instead of re-reading the
     * whole population. Chosen only where the integral query has outgrown the fixed fee
     * of that protocol. A build without delta maintenance falls back to Integral, so this
     * value is a recommendation the runtime may not yet be able to honour.
     */
    case Incremental = 'incremental';
}
