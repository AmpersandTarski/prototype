<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 * Reference to other interface
 * e.g.:
 * INTERFACE "A" : expr1 INTERFACE "B"
 * INTERFACE "B" : expr2 BOX ["label" : expr3]
 *
 * is interpreted as:
 * INTERFACE "A" : expr1;epxr2 BOX ["label" : expr3]
 */

namespace Ampersand\Interfacing;

use Ampersand\Core\Atom;
use Ampersand\Exception\AccessDeniedException;
use Ampersand\Exception\BadRequestException;
use Ampersand\Exception\FatalException;
use Ampersand\Exception\MethodNotAllowedException;
use Ampersand\Interfacing\Ifc;
use Ampersand\Interfacing\InterfaceExprObject;
use Ampersand\Interfacing\InterfaceObjectInterface;
use Ampersand\Plugs\IfcPlugInterface;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class InterfaceExprObjectRef extends InterfaceExprObject implements InterfaceObjectInterface
{
    protected string $refInterfaceId;
    protected bool $isLinkTo = false;

    public function __construct(array $ifcDef, IfcPlugInterface $plug, Ifc $rootIfc, ?InterfaceObjectInterface $parent = null)
    {
        parent::__construct($ifcDef, $plug, $rootIfc, $parent);

        $subIfcsDef = $ifcDef['subinterfaces'];

        if (is_null($subIfcsDef)) {
            throw new FatalException("Sub interface definition is required to instantiate InterfaceExprObjectRef object");
        }

        if (!isset($subIfcsDef['refSubInterfaceId'])) {
            throw new FatalException("refSubInterfaceId not specified but required to instantiate InterfaceExprObjectRef");
        }

        $this->refInterfaceId = $subIfcsDef['refSubInterfaceId'];
        $this->isLinkTo = $subIfcsDef['refIsLinkTo'];
    }

    /**
     * Returns referenced interface object
     */
    protected function getRefToIfc(): Ifc
    {
        return $this->rootIfc->getModel()->getInterface($this->refInterfaceId);
    }

    public function crudC(): bool
    {
        return $this->getRefToIfc()->getIfcObject()->crudC();
    }
    
    public function crudR(): bool
    {
        return $this->getRefToIfc()->getIfcObject()->crudR();
    }
    
    public function crudU(): bool
    {
        return $this->getRefToIfc()->getIfcObject()->crudU();
    }
    
    public function crudD(): bool
    {
        return $this->getRefToIfc()->getIfcObject()->crudD();
    }

    /**
     * Undocumented function
     * @return \Ampersand\Interfacing\InterfaceObjectInterface[]
     */
    public function getSubinterfaces(int $options = Options::DEFAULT_OPTIONS): array
    {
        if (($options & Options::INCLUDE_REF_IFCS) // option to include refs must be set
            && (!$this->isLinkTo || ($options & Options::INCLUDE_LINKTO_IFCS)) // this ref ifc must not be a LINKTO ór option is set to explicitly include linkto ifcs
        ) {
            /* Return the subinterfaces of the reference interface. This skips the referenced toplevel interface.
             * e.g.:
             * INTERFACE "A" : expr1 INTERFACE "B"
             * INTERFACE "B" : expr2 BOX ["label" : expr3]
             *
             * is interpreted as:
             * INTERFACE "A" : expr1;epxr2 BOX ["label" : expr3]
             */
            return $this->getRefToIfc()->getIfcObject()->getSubinterfaces($options);
        } else {
            return [];
        }
    }

    /**
     * Return list of target atoms
     *
     * @return \Ampersand\Core\Atom[]
     */
    public function getTgtAtoms(Atom $src, ?string $selectTgt = null): array
    {
        if (!$this->crudR()) {
            throw new MethodNotAllowedException("Read not allowed for " . $this->getPath());
        }
        
        // Evaluate interface expression
        $tgts = array_map(function (array $row) {
            return (new Atom($row['tgt'], $this->tgtConcept))->setQueryData($row);
        }, (array) $this->plug->executeIfcExpression($this, $src));

        // Integrity check
        if ($this->isUni() && count($tgts) > 1) {
            throw new FatalException("Univalent (sub)interface returns more than 1 resource: " . $this->getPath());
        }

        // If specific target is specified, pick that one out
        if (!is_null($selectTgt)) {
            return array_filter($tgts, function (Atom $item) use ($selectTgt) {
                return $item->getId() === $selectTgt;
            });
        }
        
        return $tgts;
    }

    public function getTechDetails(): array
    {
        return [
            'path' => $this->getPath(),
            'label' => $this->getIfcLabel(),
            'crudR' => $this->crudR(),
            'crudU' => $this->crudU(),
            'crudD' => $this->crudD(),
            'crudC' => $this->crudC(),
            'src' => $this->srcConcept->name,
            'tgt' => $this->tgtConcept->name,
            'view' => $this->view->label ?? '',
            'relation' => $this->relation?->signature,
            'flipped' => $this->relationIsFlipped,
            'ref' => $this->refInterfaceId
        ];
    }

    /**
     * @return \Ampersand\Interfacing\Ifc[]
     */
    protected function getNavInterfacesForTgt(): array
    {
        /** @var \Ampersand\AmpersandApp $ampersandApp */
        global $ampersandApp; // TODO: remove dependency on global var
        $ifcs = [];
        if ($this->isLinkTo) {
            $refIfc = $this->getRefToIfc();

            // Check if referenced interface is accessible for current session
            if (!$ampersandApp->isAccessibleIfc($refIfc)) {
                throw new AccessDeniedException("Specified interface '{$this->getPath()}/{$refIfc->getLabel()}' is not accessible");
            }
            
            $ifcs[] = $refIfc;
        } else {
            $ifcs = $ampersandApp->getInterfacesToReadConcept($this->tgtConcept);
        }
        
        return array_filter($ifcs, function (Ifc $ifc) {
            return !$ifc->isAPI();
        });
    }

    protected function getResourceContent(Atom $tgt, string $pathToSrc, $options, $depth, $recursionArr): string|array
    {
        // Prevent infinite loops for reference interfaces when no depth is provided
        // We only need to check LINKTO ref interfaces, because cycles may not exist in regular references (enforced by Ampersand generator)
        // If $depth is provided, no check is required, because recursion is finite
        if ($this->isLinkTo && is_null($depth)) {
            if (in_array($tgt->getId(), $recursionArr[$this->refInterfaceId] ?? [])) {
                throw new BadRequestException("Infinite loop detected for {$tgt} in " . $this->getPath());
            } else {
                $recursionArr[$this->refInterfaceId][] = $tgt->getId();
            }
        }

        // Call parent method to reuse functionality to get resource content
        return parent::getResourceContent($tgt, $pathToSrc, $options, $depth, $recursionArr);
    }
}
