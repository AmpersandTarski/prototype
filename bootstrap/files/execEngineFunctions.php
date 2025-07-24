<?php

/*
   This file defines the functions:
   - 'InsPair', 'DelPair': insert and delete links in relations
   - 'NewStruct': create a new atom and populate relations
   - 'InsAtom', 'DelAtom': create/delete a single atom
   - 'MrgAtoms': unify two atoms that have been discovered to be identical
   - 'SetConcept', 'ClearConcept'
   - 'InsPairCond', 'SetConceptCond': conditionally execute an 'InsPair' or 'SetConcept'
   There are no guarantees with respect to their 100% functioning. Have fun...

   This file has been modified to produce Exceptions rather than that it dies...
*/

use Ampersand\Core\Atom;
use Ampersand\Rule\ExecEngine;
use Ampersand\Exception\InvalidExecEngineCallException;

/*
   Example of rule that automatically inserts pairs into a relation (analogous stuff holds for DelPair):
   ROLE ExecEngine MAINTAINS "New Customers"
   RULE "New Customers": customerOrder[Person*Order];companyOrder[Company*Order]~ |- customerOf[Person*Company]
   MEANING "If a person places an order at a company, the person is a customer of that company"
   VIOLATION (TXT "InsPair;customerOf;Person;", SRC I, TXT";Company;", TGT I)
*/
// Use:  VIOLATION (TXT "InsPair;<relation>;<srcConcept>;<srcAtom>;<tgtConcept>;<tgtAtom>")
/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('InsPair', function ($relationName, $srcConceptName, $srcAtom, $tgtConceptName, $tgtAtom) {
    /** @var \Ampersand\Rule\ExecEngine $this */
    if (func_num_args() != 5) {
        throw new InvalidExecEngineCallException("InsPair() expects 5 arguments, but you have provided " . func_num_args());
    }

    // Check if relation signature exists: $relationName[$srcConceptName*$tgtConceptName]
    $model = $this->getApp()->getModel();
    $srcConcept = $model->getConceptByLabel($srcConceptName);
    $tgtConcept = $model->getConceptByLabel($tgtConceptName);
    $relation = $this->getApp()->getRelation($relationName, $srcConcept, $tgtConcept);
    
    // if either srcAtomIdStr or tgtAtom is not provided by the pairview function (i.e. value set to '_NULL'): skip the insPair
    if ($srcAtom === '_NULL' or $tgtAtom === '_NULL') {
        $this->debug("InsPair ignored because src and/or tgt atom is _NULL");
        return;
    }
    
    // if atom id is specified as _NEW, the latest atom created by NewStruct or InsAtom (in this VIOLATION) is used
    if ($srcAtom === "_NEW") {
        $srcAtom = $this->getCreatedAtom()->getId();
    }
    if ($tgtAtom === "_NEW") {
        $tgtAtom = $this->getCreatedAtom()->getId();
    }
    
    $srcAtomIds = explode('_AND', $srcAtom);
    $tgtAtomIds = explode('_AND', $tgtAtom);
    foreach ($srcAtomIds as $a) {
        $src = new Atom($a, $srcConcept);
        foreach ($tgtAtomIds as $b) {
            $tgt = new Atom($b, $tgtConcept);
            $link = $src->link($tgt, $relation)->add();
            $this->debug("Added {$link}");
        }
    }
});

/*
    Example of a rule that automatically deletes pairs from a relation:
    ROLE ExecEngine MAINTAINS "Remove Customers"
    RULE "Remove Customers": customerOf[Person*Company] |- customerOrder[Person*Order];companyOrder[Company*Order]~
    MEANING "Customers of a company for which no orders exist (any more), are no longer considered customers"
    VIOLATION (TXT "DelPair;customerOf;Person;", SRC I, TXT";Company;", TGT I)
*/
// Use: VIOLATION (TXT "DelPair;<rel>;<srcConcept>;<srcAtom>;<tgtConcept>;<tgtAtom>")
/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('DelPair', function ($relationName, $srcConceptName, $srcAtom, $tgtConceptName, $tgtAtom) {
    /** @var \Ampersand\Rule\ExecEngine $this */
    if (func_num_args() != 5) {
        throw new InvalidExecEngineCallException("DelPair() expects 5 arguments, but you have provided " . func_num_args());
    }
        
    // Check if relation signature exists: $relationName[$srcConceptName*$tgtConceptName]
    $model = $this->getApp()->getModel();
    $srcConcept = $model->getConceptByLabel($srcConceptName);
    $tgtConcept = $model->getConceptByLabel($tgtConceptName);
    $relation = $this->getApp()->getRelation($relationName, $srcConcept, $tgtConcept);
    
    // if either srcAtomIdStr or tgtAtom is not provided by the pairview function (i.e. value set to '_NULL'): skip the insPair
    if ($srcAtom === '_NULL' or $tgtAtom === '_NULL') {
        $this->debug("DelPair ignored because src and/or tgt atom is _NULL");
        return;
    }
    
    // if atom id is specified as _NEW, the latest atom created by NewStruct or InsAtom (in this VIOLATION) is used
    if ($srcAtom === "_NEW") {
        $srcAtom = $this->getCreatedAtom()->getId();
    }
    if ($tgtAtom === "_NEW") {
        $tgtAtom = $this->getCreatedAtom()->getId();
    }
    
    $srcAtoms = explode('_AND', $srcAtom);
    $tgtAtoms = explode('_AND', $tgtAtom);
    
    foreach ($srcAtoms as $a) {
        $src = new Atom($a, $srcConcept);
        foreach ($tgtAtoms as $b) {
            $tgt = new Atom($b, $tgtConcept);
            $link = $src->link($tgt, $relation)->delete();
            $this->debug("Deleted {$link}");
        }
    }
});

/* The function 'NewStruct' creates a new atom in some concept and uses this
   atom to create links (in relations in which the concept is SRC or TGT).

   Example:

   r :: ConceptA * ConceptB
   r1 :: ConceptA * ConceptC [INJ] -- multiplicity must be there (I think...)
   r2 :: ConceptC * ConceptB [UNI] -- multiplicity must be there (I think...)

   RULE "equivalence": r = r1;r2 -- this rule is to be maintained automatically

   ROLE ExecEngine MAINTAINS "insEquivalence" -- Creation of the atom
   RULE "insEquivalence": r |- r1;r2
   VIOLATION (TXT "NewStruct;ConceptC[;AtomC]" -- AtomC is optional. If not provided then create new, else used specified Atom
             ,TXT ";r1;ConceptA;", SRC I, TXT";ConceptC;_NEW"  -- Always use _NEW as ConceptC atom
             ,TXT ";r2;ConceptC;_NEW;ConceptB;atomB;", TGT I   -- Always use _NEW as ConceptC atom
              )

*/
// Arglist: ($ConceptC[,$newAtom][,$relation,$srcConcept,$srcAtom,$tgtConcept,$tgtAtom]+)
/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('NewStruct', function () {
    /** @var \Ampersand\Rule\ExecEngine $this */

    $model = $this->getApp()->getModel();

    // We start with parsing the first one or two arguments
    $c = $model->getConceptByLabel(func_get_arg(0)); // Concept for which atom is to be created
    $atom = $c->createNewAtom(); // Default marker for atom-to-be-created.

    $this->info("Newstruct for concept '{$c}'");
    
    // Check if name of new atom is explicitly specified
    if (func_num_args() % 5 == 2) {
        $atom = $c->makeAtom(func_get_arg(1)); // If so, we'll be using this to create the new atom
    // Check for valid number of arguments
    } elseif (func_num_args() % 5 != 1) {
        throw new InvalidExecEngineCallException("Wrong number of arguments supplied for function Newstruct(): " . func_num_args() . " arguments");
    }
    
    // Add atom to concept
    $atom->add();

    // Make newly created atom available within scope of violation for use of other functions
    $this->setCreatedAtom($atom);

    // Next, for every relation that follows in the argument list, we create a link
    for ($i = func_num_args() % 5; $i < func_num_args(); $i = $i+5) {
        $relation   = func_get_arg($i);
        $srcConcept = $model->getConceptByLabel(func_get_arg($i+1));
        $srcAtomId    = func_get_arg($i+2);
        $tgtConcept = $model->getConceptByLabel(func_get_arg($i+3));
        $tgtAtomId    = func_get_arg($i+4);
        
        if ($srcAtomId === "NULL" or $tgtAtomId === "NULL") {
            throw new InvalidExecEngineCallException("NewStruct: use of keyword NULL is deprecated, use '_NEW'");
        }
        
        // NewStruct requires that atom $srcAtomId or $tgtAtomId must be _NEW
        // Note: when populating a [PROP] relation, both atoms can be new
        if (!($srcAtomId === '_NEW' or $tgtAtomId === '_NEW')) {
            throw new InvalidExecEngineCallException("NewStruct: relation '{$relation}' requires that atom '{$srcAtomId}' or '{$tgtAtomId}' must be '_NEW'");
        }
        
        // NewStruct requires that concept $srcConcept or $tgtConcept must be concept $c
        if (!in_array($srcConcept, $c->getGeneralizationsIncl()) && !in_array($tgtConcept, $c->getGeneralizationsIncl())) {
            throw new InvalidExecEngineCallException("NewStruct: relation '{$relation}' requires that src or tgt concept must be '{$c}' (or any of its generalizations)");
        }
    
        // Replace atom by the newstruct atom if _NEW is used
        if (in_array($srcConcept, $c->getGeneralizationsIncl()) && $srcAtomId === '_NEW') {
            $srcAtomId = $atom->getId();
        }
        if (in_array($tgtConcept, $c->getGeneralizationsIncl()) && $tgtAtomId === '_NEW') {
            $tgtAtomId = $atom->getId();
        }
        
        // Any logging is done by InsPair
        ExecEngine::getFunction('InsPair')->call($this, $relation, $srcConcept->name, $srcAtomId, $tgtConcept->name, $tgtAtomId);
    }
    $this->debug("Newstruct: atom '{$atom}' created");
});

// Use: VIOLATION (TXT "InsAtom;<concept>")
/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('InsAtom', function (string $conceptName) {
    /** @var \Ampersand\Rule\ExecEngine $this */

    if (func_num_args() != 1) {
        throw new InvalidExecEngineCallException("InsAtom() expects 1 argument, but you have provided " . func_num_args());
    }

    $atom = $this->getApp()->getModel()->getConceptByLabel($conceptName)->createNewAtom();

    // Add atom to concept set
    $atom->add();
    
    // Make (newly created) atom available within scope of violation for use of other functions
    $this->setCreatedAtom($atom);

    $this->debug("Atom '{$atom}' added");
});

/*
    ROLE ExecEngine MAINTAINS "delEquivalence" -- Deletion of the atom
    RULE "delEquivalence": I[ConceptC] |- r1~;r;r2~
    VIOLATION (TXT "DelAtom;ConceptC;" SRC I) -- all links in other relations in which the atom occurs are deleted as well.
*/
// Use: VIOLATION (TXT "DelAtom;<concept>;<atom>")
/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('DelAtom', function ($concept, $atomId) {
    /** @var \Ampersand\Rule\ExecEngine $this */
    if (func_num_args() != 2) {
        throw new InvalidExecEngineCallException("DelAtom() expects 2 arguments, but you have provided " . func_num_args());
    }
    
    // if atom id is specified as _NEW, the latest atom created by NewStruct or InsAtom (in this VIOLATION) is used
    if ($atomId === "_NEW") {
        $atom = $this->getCreatedAtom();
    } else {
        $atom = $this->getApp()->getModel()->getConceptByLabel($concept)->makeAtom($atomId);
    }
    
    $atom->delete(); // delete atom + all pairs shared with other atoms
    $this->debug("Atom '{$atom}' deleted");
});

/*
    ROLE ExecEngine MAINTAINS "Person" -- unify two atoms
    RULE Person : name;name~ |- I
    VIOLATION (TXT "{EX} MrgAtoms;Person;", SRC I, TXT ";Person;", TGT I )
     * Parameters
     * @param Concept $conceptA   -- The most specific concept A such that $srcAtomId pop A
     * @param Atom $srcAtomId     -- The atom to be made equal to $tgtAtomId
     * @param Concept $conceptB   -- The most specific concept B such that $tgtAtomId pop B
     * @param Atom $tgtAtomId     -- The atom to be made equal to $srcAtomId
*/
// Use: VIOLATION (TXT "{EX} MrgAtoms;<conceptA>;", SRC I, TXT ";<conceptB>;", TGT I )
/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('MrgAtoms', function ($conceptA, $srcAtomId, $conceptB, $tgtAtomId) {
    /** @var \Ampersand\Rule\ExecEngine $this */
    if (func_num_args() != 4) {
        throw new InvalidExecEngineCallException("MrgAtoms() expects 4 arguments, but you have provided " . func_num_args());
    }
    
    $model = $this->getApp()->getModel();
    $srcAtom = $model->getConceptByLabel($conceptA)->makeAtom($srcAtomId);
    $tgtAtom = $model->getConceptByLabel($conceptB)->makeAtom($tgtAtomId);
    
    // if atom id is specified as _NEW, the latest atom created by NewStruct or InsAtom (in this VIOLATION) is used
    if ($srcAtomId === "_NEW") {
        $srcAtom = $this->getCreatedAtom();
    }
    if ($tgtAtomId === "_NEW") {
        $tgtAtom = $this->getCreatedAtom();
    }

    if (!$srcAtom->exists() || !$tgtAtom->exists()) {
        // I don't want MrgAtoms to fail. It must return silently when one or both atoms do not exist without doing anything.
        // So, I have commented out the following line:
        // $this->notice("Skipping MrgAtoms function of {$srcAtom} and {$tgtAtom}, because one or both of them not exist (anymore)");
        return;
    }
    
    $srcAtom->merge($tgtAtom); // union of two records plus substitution in all occurences in binary relations.
    $this->debug("Atom '{$tgtAtom}' merged into '{$srcAtom}' and then deleted");
});

/*
 ROLE ExecEngine MAINTAINS "SetConcept" -- Adding an atomId[ConceptA] as member to ConceptB set. This can only be done when ConceptA and ConceptB are in the same classification tree.
 RULE "SetConcept": I[ConceptA] |- expr
 VIOLATION (TXT "SetConcept;ConceptA;ConceptB;" SRC I)
 */
// Use: VIOLATION (TXT "SetConcept;<ConceptA>;<ConceptB>;<atomId>")
/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('SetConcept', function ($conceptA, $conceptB, $atomId) {
    /** @var \Ampersand\Rule\ExecEngine $this */
    if (func_num_args() != 3) {
        throw new InvalidExecEngineCallException("SetConcept() expects 3 arguments, but you have provided " . func_num_args());
    }

    // if atom id is specified as _NEW, the latest atom created by NewStruct or InsAtom (in this VIOLATION) is used
    if ($atomId === "_NEW") {
        $atom = $this->getCreatedAtom();
    } else {
        $atom = $this->getApp()->getModel()->getConceptByLabel($conceptA)->makeAtom($atomId);
    }
    
    $conceptB = $this->getApp()->getModel()->getConceptByLabel($conceptB);
    $conceptB->addAtom($atom, false);
    $this->debug("Atom '{$atom}' added as member to concept '{$conceptB}'");
});

/*
 ROLE ExecEngine MAINTAINS "ClearConcept" -- Removing an atom as member from a Concept set. This can only be done when the concept is a specialization of another concept.
 RULE "ClearConcept": I[Concept] |- expr
 VIOLATION (TXT "ClearConcept;Concept;" SRC I)
 */
// Use: VIOLATION (TXT "ClearConcept;<Concept>;<atom>")
/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('ClearConcept', function ($concept, $atomId) {
    /** @var \Ampersand\Rule\ExecEngine $this */
    if (func_num_args() != 2) {
        throw new InvalidExecEngineCallException("ClearConcept() expects 2 arguments, but you have provided " . func_num_args());
    }

    $concept = $this->getApp()->getModel()->getConceptByLabel($concept);
    
    // if atom id is specified as _NEW, the latest atom created by NewStruct or InsAtom (in this VIOLATION) is used
    if ($atomId === "_NEW") {
        $atom = $this->getCreatedAtom();
    } else {
        $atom = $concept->makeAtom($atomId);
    }
    
    $concept->removeAtom($atom);
    $this->debug("Atom '{$atom}' removed as member from concept '{$concept}'");
});


/**************************************************************
 * Conditional variants of the above functions
 *************************************************************/
 
// InsPairCond is skipped when $bool string value equals: "0", "false", "off", "no", "" or "_NULL"
/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('InsPairCond', function ($relationName, $srcConceptName, $srcAtom, $tgtConceptName, $tgtAtom, $bool) {
    /** @var \Ampersand\Rule\ExecEngine $this */
    if (func_num_args() != 6) {
        throw new InvalidExecEngineCallException("InsPairCond() expects 6 arguments, but you have provided " . func_num_args());
    }
    
    // Skip when $bool evaluates to false or equals '_NULL'.
    // _Null is the exec-engine special for zero results from Ampersand expression
    if (filter_var($bool, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === false || $bool === '_NULL') {
        $this->debug("InsPairCond skipped because bool evaluation results in false");
        return;
    }
    
    ExecEngine::getFunction('InsPair')->call($this, $relationName, $srcConceptName, $srcAtom, $tgtConceptName, $tgtAtom);
});

// SetConcept is skipped when $bool string value equals: "0", "false", "off", "no", "" or "_NULL"
/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('SetConceptCond', function ($conceptA, $conceptB, $atom, $bool) {
    /** @var \Ampersand\Rule\ExecEngine $this */
    if (func_num_args() != 4) {
        throw new InvalidExecEngineCallException("SetConceptCond() expects 4 arguments, but you have provided " . func_num_args());
    }
    
    // Skip when $bool evaluates to false or equals '_NULL'.
    // _Null is the exec-engine special for zero results from Ampersand expression
    if (filter_var($bool, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === false || $bool === '_NULL') {
        $this->debug("SetConcept skipped because bool evaluation results in false");
        return;
    }
    
    ExecEngine::getFunction('SetConcept')->call($this, $conceptA, $conceptB, $atom);
});

/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('SetNavToOnCommit', function ($navTo) {
    /** @var \Ampersand\Rule\ExecEngine $this */
    if (func_num_args() != 1) {
        throw new InvalidExecEngineCallException("SetNavToOnCommit() expects 1 argument, but you have provided " . func_num_args());
    }
    if (strpos($navTo, '_NEW') !== false) {
        $navTo = str_replace('_NEW', $this->getCreatedAtom()->getId(), $navTo); // Replace _NEW with latest atom created by NewStruct or InsAtom (in this VIOLATION)
        $this->debug("replaced navTo string with '{$navTo}'");
    }

    if (empty($navTo) || $navTo === '_NULL') {
        $this->debug("navTo was skipped because of '_NULL'-argument");
        return false;
    }

    $this->getApp()->frontend()->setNavToResponse($navTo, 'COMMIT');
});

/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('SetNavToOnRollback', function ($navTo) {
    /** @var \Ampersand\Rule\ExecEngine $this */
    if (func_num_args() != 1) {
        throw new InvalidExecEngineCallException("SetNavToOnRollback() expects 1 argument, but you have provided " . func_num_args());
    }
    if (strpos($navTo, '_NEW') !== false) {
        $navTo = str_replace('_NEW', $this->getCreatedAtom()->getId(), $navTo); // Replace _NEW with latest atom created by NewStruct or InsAtom (in this VIOLATION)
        $this->debug("replaced navTo string with '{$navTo}'");
    }
    
    if (empty($navTo) || $navTo === '_NULL') {
        $this->debug("navTo was skipped because of '_NULL'-argument");
        return false;
    }
    
    $this->getApp()->frontend()->setNavToResponse($navTo, 'ROLLBACK');
});

/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('TerminateThisExecEngine', function (string $userMessage = null) {
    /** @var \Ampersand\Rule\ExecEngine $this */
    if (func_num_args() < 2) {
        throw new InvalidExecEngineCallException("TerminateThisExecEngine() expects at most 1 argument, but you have provided " . func_num_args());
    }
    $this->terminate();
    if (!empty($userMessage)) {
        $this->userLog()->info($userMessage);
    }
});

/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('TriggerService', function (string $roleName) {
    /** @var \Ampersand\Rule\ExecEngine $this */
    if (func_num_args() != 1) {
        throw new InvalidExecEngineCallException("TriggerService() expects 1 argument, but you have provided " . func_num_args());
    }
    $this->triggerService($roleName);
});
