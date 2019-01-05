<?php
/* This file defines the (php) function 'TransitiveClosure', that computes the transitive closure of a relation.

   Suppose you have a relation r :: C * C, and that you need the transitive closure r+ of that relation.
   Since r* is not supported in the prototype generator as is, we need a way to instruct the ExecEngine
   to populate a relation rPlus :: C * C that contains the same population as r+
   Maintaining the population of rPlus correctly is not trivial, particularly when r is depopulated.
   The easiest way around this is to compute rPlus from scratch (using Warshall's algorithm).
   However, you then need to know that r is being (de)populated, so we need a copy of r.

   This leads to the following pattern:

   relation :: Concept*Concept
   relationCopy :: Concept*Concept -- copied value of 'relation' allows for detecting modification events
   relationPlus :: Concept*Concept -- transitive closure, i.e.: the irreflexive transitive closure!

   ROLE ExecEngine MAINTAINS "relationCompTransitiveClosure"
   RULE "relationCompTransitiveClosure": relation = relationCopy
   VIOLATION (TXT "{EX} TransitiveClosure;relation;Concept;relationCopy;relationPlus")

   NOTES:
   1) The above example is made for ease of use. This is what you need to do:
      a) copy and paste the above example into your own ADL script;
      b) replace the names of 'relation' and 'Concept' (cases sensitive, also as part of a word) with what you need
   2) Of course, there are all sorts of alternative ways in which 'TransitiveClosure' can be used.
   3) There are ways to optimize the below code, e.g. by splitting the function into an 'InsTransitiveClosure'
      and a 'DelTransitiveClosure'
   4) Rather than defining/computing rStar (for r*), you may use the expression (I \/ rPlus)
*/

use Ampersand\Core\Concept;
use Ampersand\Core\Relation;
use Ampersand\Rule\ExecEngine;
use Ampersand\Core\Link;

/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('TransitiveClosure', function ($r, $C, $rCopy, $rPlus) {
    static $calculatedRelations = [];
    static $runCount = 0;

    /** @var \Ampersand\Rule\ExecEngine $this */
    if (func_num_args() != 4) {
        throw new Exception("TransitiveClosure() expects 4 arguments, but you have provided ".func_num_args(), 500);
    }
    
    // Quit if a relation $r is already calculated in a specific exec-engine run
    if (in_array($r, $calculatedRelations)) {
        if ($runCount === $this->getRunCount()) {
            return;
        }
    } else {
        $calculatedRelations[] = $r;
    }
    $runCount = $this->getRunCount();

    // Get concept and relation objects
    $concept = Concept::getConceptByLabel($C);
    $relationR = Relation::getRelation($r, $concept, $concept);
    $relationRCopy = Relation::getRelation($rCopy, $concept, $concept);
    $relationRPlus = Relation::getRelation($rPlus, $concept, $concept);

    // Empty rCopy and rPlus
    $relationRCopy->empty();
    $relationRPlus->empty();

    // Get adjacency matrix
    $closure = [];
    $atoms = [];
    foreach ($relationR->getAllLinks() as $link) {
        /** @var \Ampersand\Core\Link $link */
        $src = $link->src();
        $tgt = $link->tgt();
        $closure[$src->id][$tgt->id] = true;
        $atoms[$src->id] = $src;
        $atoms[$tgt->id] = $tgt;
        
        // Store a copy in rCopy relation
        (new Link($relationRCopy, $src, $tgt))->add();
    }
    $atoms = array_unique($atoms);
    
    // Compute transitive closure following Warshall's algorithm
    foreach ($atoms as $k => $kAtom) {
        foreach ($atoms as $i => $iAtom) {
            if ($closure[$i][$k]) {
                foreach ($atoms as $j => $jAtom) {
                    if ($closure[$i][$j] || $closure[$k][$j]) {
                        // Write to rPlus
                        (new Link($relationRPlus, $iAtom, $jAtom))->add();
                    }
                }
            }
        }
    }
});
