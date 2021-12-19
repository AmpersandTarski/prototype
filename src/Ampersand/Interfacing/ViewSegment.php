<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Interfacing;

use Ampersand\Core\Atom;
use Ampersand\Interfacing\View;
use Exception;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class ViewSegment
{

    /**
     * The view to which this segment belongs to
     */
    protected View $view;
    
    protected int $seqNr;

    protected string $label;

    protected string $segType;

    protected ?string $text;

    protected ?string $expADL;

    protected ?string $expSQL;

    /**
     * Constructor
     */
    public function __construct(array $viewSegmentDef, View $view)
    {
        $this->view = $view;
        $this->seqNr = $viewSegmentDef['seqNr'];
        $this->label = $viewSegmentDef['label'] ?? (string) $viewSegmentDef['seqNr'];
        $this->segType = $viewSegmentDef['segType'];
        $this->text = $viewSegmentDef['text'];
        $this->expADL = $viewSegmentDef['expADL'];
        $this->expSQL = $viewSegmentDef['expSQL'];
        
        if (!($this->segType === 'Text' || $this->segType === 'Exp')) {
            throw new Exception("Unsupported segmentType '{$this->segType}' in VIEW segment <{$this}>", 501); // 501: Not implemented
        }
    }
    
    public function __toString(): string
    {
        return $this->view->getLabel() . ":{$this->label}";
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getType(): string
    {
        return $this->segType;
    }
    
    public function getData(Atom $srcAtom): mixed
    {
        switch ($this->segType) {
            case "Text":
                return $this->text;
                break;
            case "Exp":
                // Try to get view segment from atom query data
                $data = $srcAtom->getQueryData('view_' . $this->label, $exists); // column is prefixed with view_
                if ($exists) {
                    return $data;
                } else {
                    $tgtAtoms = $this->view->getPlug()->executeViewExpression($this, $srcAtom);
                    return count($tgtAtoms) ? $tgtAtoms[0] : null;
                }
                break;
            default:
                throw new Exception("Unsupported segmentType '{$this->segType}' in VIEW segment <{$this}>", 501); // 501: Not implemented
                break;
        }
    }

    /**
     * Returns query of view segment
     */
    public function getQuery(): string
    {
        if (is_null($this->expSQL)) {
            throw new Exception("Query not specified for view segment '{$this}'", 500);
        }
        
        return str_replace('_SESSION', session_id(), $this->expSQL); // Replace _SESSION var with current session id.
    }

    /**
     * Function to manually set optimized query expression
     */
    public function setQuery(string $query): void
    {
        $this->segType = 'Exp';
        $this->expSQL = $query;
    }
}
