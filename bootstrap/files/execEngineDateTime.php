<?php
/* This file defines a limited number of functions that deal with dates and times. They include functions for comparing dates and times (equal, less than etc.), for setting today's date, and more. This file may be extended, but only with functions that can be used generically.

The date and time formats that can be used are pretty much arbitrary. A precise description is given at:
   http://www.php.net/manual/en/datetime.formats.date.php
   http://www.php.net/manual/en/datetime.formats.time.php
*/

use Ampersand\Exception\InvalidExecEngineCallException;
use Ampersand\Rule\ExecEngine;

/* sessionToday :: SESSION * Date -- or whatever the DateTime concept is called
   ROLE ExecEngine MAINTAINS "Initialize today's date"
   RULE "Initialize today's date": I[SESSION] |- sessionToday;sessionToday~
   VIOLATION (TXT "{EX} SetToday;sessionToday;SESSION;", SRC I, TXT ";Date;", TGT sessionToday)

   For $formatSpec see http://php.net/manual/en/function.date.php
   Default is 'd-m-Y' -> e.g: "01-01-2015", other examples include time, like 'd-m-Y G:i:s' -> e.g.: "01-01-2015 1:00:00"
*/
/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('SetToday', function ($relation, $srcConcept, $srcAtom, $dateConcept, $formatSpec = 'd-m-Y') {
    /** @var \Ampersand\Rule\ExecEngine $this the ExecEngine instance is bound to this closure */
    $curdate = date($formatSpec);
    ExecEngine::getFunction('InsPair')->call($this, $relation, $srcConcept, $srcAtom, $dateConcept, $curdate);
});


// VIOLATION (TXT "{EX} datimeStdFormat;standardizeDateTime;DateTime;", SRC I, TXT ";DateTimeStdFormat;", TGT I)
/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('datimeStdFormat', function ($relation, $DateConcept, $srcAtom, $StdFormatConcept, $formatSpec) {
    /** @var \Ampersand\Rule\ExecEngine $this the ExecEngine instance is bound to this closure */
    $date = new DateTime($srcAtom);
    ExecEngine::getFunction('InsPair')->call($this, $relation, $DateConcept, $srcAtom, $StdFormatConcept, $date->format($formatSpec));
});


/* (Example taken from EURent):
VIOLATION (TXT "{EX} DateDifferencePlusOne" -- Result = 1 + MAX(0, (RentalEndDate - RentalStartDate))
               , TXT ";computedRentalPeriod;DateDifferencePlusOne;", SRC I, TXT ";Integer"
               , TXT ";", SRC earliestDate -- = Rental start date
               , TXT ";", SRC latestDate   -- = Rental end date
          )
*/
/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('DateDifferencePlusOne', function ($relation, $srcConcept, $srcAtom, $integerConcept, $earliestDate, $latestDate) {
    /** @var \Ampersand\Rule\ExecEngine $this the ExecEngine instance is bound to this closure */
    $datediff = strtotime($latestDate) - strtotime($earliestDate);
    if ($datediff < 0) {
        throw new InvalidExecEngineCallException("First arg (earliestDate) must be smaller than second arg (latestDate).");
    }
    
    $result = 1 + max(0, floor($datediff/(60*60*24)));
    ExecEngine::getFunction('InsPair')->call($this, $relation, $srcConcept, $srcAtom, $integerConcept, $result);
});


/* (Example taken from EURent):
VIOLATION (TXT "{EX} DateDifference"
               , TXT ";compExcessPeriod;DateDifference;", SRC I, TXT ";Integer"
               , TXT ";", SRC firstDate
               , TXT ";", SRC lastDate
          )
*/
/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('DateDifference', function ($relation, $srcConcept, $srcAtom, $integerConcept, $firstDate, $lastDate) {
    /** @var \Ampersand\Rule\ExecEngine $this the ExecEngine instance is bound to this closure */
    $datediff = strtotime($lastDate) - strtotime($firstDate);
    if ($datediff < 0) {
        throw new InvalidExecEngineCallException("First arg (earliestDate) must be smaller than second arg (latestDate).");
    }
    
    $result = max(0, floor($datediff/(60*60*24)));
    ExecEngine::getFunction('InsPair')->call($this, $relation, $srcConcept, $srcAtom, $integerConcept, $result);
});


/* COMPARING DATES AND TIMES (used e.g. in Arbeidsduur)
   Whenever you need to compare dates/times, you must define your own relation(s) for that, and consequently also a concept for the date/time.
   The functions provided in this file allow you to fill such relations.

>> EXAMPLES OF USE:
   stdDateTime :: DateTime * DateTimeStdFormat [UNI] PRAGMA "Standard output format for " " is "

   ROLE ExecEngine MAINTAINS "compute DateTime std values"
   RULE "compute DateTime std values": I[DateTime] |- stdDateTime;stdDateTime~
   VIOLATION (TXT "{EX} datimeStdFormat;stdDateTime;DateTime;", SRC I, TXT ";DateTimeStdFormat;Y-m-d") -- The text 'Y-m-d' may be replaced by any other format specification, see the 'Parameters' section on http://www.php.net/manual/en/function.date.php

   eqlDateTime :: DateTime * DateTime PRAGMA "" " occurred simultaneously "
   neqDateTime :: DateTime * DateTime PRAGMA "" " occurred either before or after "
    ltDateTime :: DateTime * DateTime PRAGMA "" " has occurred before "
    gtDateTime :: DateTime * DateTime PRAGMA "" " has occurred after "

   ROLE ExecEngine MAINTAINS "compute DateTime comparison relations"
   RULE "compute DateTime comparison relations": V[DateTime] |- eqlDateTime \/ neqDateTime
   VIOLATION (TXT "{EX} datimeEQL;eqlDateTime;DateTime;", SRC I, TXT ";", TGT I
             ,TXT "{EX} datimeNEQ;neqDateTime;DateTime;", SRC I, TXT ";", TGT I
             ,TXT "{EX} datimeLT;ltDateTime;DateTime;", SRC I, TXT ";", TGT I
             ,TXT "{EX} datimeGT;gtDateTime;DateTime;", SRC I, TXT ";", TGT I
             )

>> LIMITATIONS OF USE:
   If you use many atoms in DateTime, this will take increasingly more time
   to check for violations. So do not use that many...
*/
// VIOLATION (TXT "{EX} datimeEQL;DateTime;" SRC I, TXT ";", TGT I)
/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('datimeEQL', function ($eqlRelation, $DateConcept, $srcAtom, $tgtAtom) {
    /** @var \Ampersand\Rule\ExecEngine $this the ExecEngine instance is bound to this closure */
    if (($dt1 = strtotime($srcAtom)) === false) {
        throw new InvalidExecEngineCallException("Illegal date '{$srcAtom}' specified in srcAtom (3rd arg)");
    }
    if (($dt2 = strtotime($tgtAtom)) === false) {
        throw new InvalidExecEngineCallException("Illegal date '{$tgtAtom}' specified in tgtAtom (4th arg)");
    }
    
    if ($dt1 == $dt2) {
        ExecEngine::getFunction('InsPair')->call($this, $eqlRelation, $DateConcept, $srcAtom, $DateConcept, $tgtAtom);
        
        // Accommodate for different representations of the same time:
        if ($srcAtom != $tgtAtom) {
            ExecEngine::getFunction('InsPair')->call($this, $eqlRelation, $DateConcept, $tgtAtom, $DateConcept, $srcAtom);
        }
    }
});


// VIOLATION (TXT "{EX} datimeNEQ;DateTime;" SRC I, TXT ";", TGT I)
/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('datimeNEQ', function ($neqRelation, $DateConcept, $srcAtom, $tgtAtom) {
    /** @var \Ampersand\Rule\ExecEngine $this the ExecEngine instance is bound to this closure */
    if (($dt1 = strtotime($srcAtom)) === false) {
        throw new InvalidExecEngineCallException("Illegal date '{$srcAtom}' specified in srcAtom (3rd arg)");
    }
    if (($dt2 = strtotime($tgtAtom)) === false) {
        throw new InvalidExecEngineCallException("Illegal date '{$tgtAtom}' specified in tgtAtom (4th arg)");
    }
    
    if ($dt1 != $dt2) {
        ExecEngine::getFunction('InsPair')->call($this, $neqRelation, $DateConcept, $srcAtom, $DateConcept, $tgtAtom);
        ExecEngine::getFunction('InsPair')->call($this, $neqRelation, $DateConcept, $tgtAtom, $DateConcept, $srcAtom);
    }
});


// VIOLATION (TXT "{EX} datimeLT;DateTime;" SRC I, TXT ";", TGT I)
/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('datimeLT', function ($ltRelation, $DateConcept, $srcAtom, $tgtAtom) {
    /** @var \Ampersand\Rule\ExecEngine $this the ExecEngine instance is bound to this closure */
    if (($dt1 = strtotime($srcAtom)) === false) {
        throw new InvalidExecEngineCallException("Illegal date '{$srcAtom}' specified in srcAtom (3rd arg)");
    }
    if (($dt2 = strtotime($tgtAtom)) === false) {
        throw new InvalidExecEngineCallException("Illegal date '{$tgtAtom}' specified in tgtAtom (4th arg)");
    }
    if ($dt1 == $dt2) {
        return;
    }
    
    if ($dt1 < $dt2) {
        ExecEngine::getFunction('InsPair')->call($this, $ltRelation, $DateConcept, $srcAtom, $DateConcept, $tgtAtom);
    } else {
        ExecEngine::getFunction('InsPair')->call($this, $ltRelation, $DateConcept, $tgtAtom, $DateConcept, $srcAtom);
    }
});

// VIOLATION (TXT "{EX} datimeGT;DateTime;" SRC I, TXT ";", TGT I)
/**
 * @phan-closure-scope \Ampersand\Rule\ExecEngine
 * Phan analyzes the inner body of this closure as if it were a closure declared in ExecEngine.
 */
ExecEngine::registerFunction('datimeGT', function ($gtRelation, $DateConcept, $srcAtom, $tgtAtom) {
    /** @var \Ampersand\Rule\ExecEngine $this the ExecEngine instance is bound to this closure */
    if (($dt1 = strtotime($srcAtom)) === false) {
        throw new InvalidExecEngineCallException("Illegal date '{$srcAtom}' specified in srcAtom (3rd arg)");
    }
    if (($dt2 = strtotime($tgtAtom)) === false) {
        throw new InvalidExecEngineCallException("Illegal date '{$tgtAtom}' specified in tgtAtom (4th arg)");
    }
    if ($dt1 == $dt2) {
        return;
    }
    
    if ($dt1 > $dt2) {
        ExecEngine::getFunction('InsPair')->call($this, $gtRelation, $DateConcept, $srcAtom, $DateConcept, $tgtAtom);
    } else {
        ExecEngine::getFunction('InsPair')->call($this, $gtRelation, $DateConcept, $tgtAtom, $DateConcept, $srcAtom);
    }
});
