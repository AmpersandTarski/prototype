<?php

namespace Ampersand\Misc;

use Ampersand\Core\Concept;
use Ampersand\Core\Relation;

abstract class ProtoContext
{
    // Concept labels (i.e. the names as used in Ampersand scripts)
    const
        CPT_ROLE            = 'PrototypeContext.Role',
        CPT_IFC             = 'PrototypeContext.Interface',
        CPT_LABEL           = 'PrototypeContext.Label',
        CPT_NAV_MENU        = 'PrototypeContext.NavMenu',
        CPT_NAV_ITEM        = 'PrototypeContext.NavMenuItem';

    const CONCEPTS = [
        self::CPT_ROLE,
        self::CPT_IFC,
        self::CPT_LABEL,
        self::CPT_NAV_MENU,
        self::CPT_NAV_ITEM,
    ];
    
    // Relation signatures with notation: rel[src*tgt]
    const
        REL_IFC_ROLES               = 'PrototypeContext.ifcRoles[PrototypeContext.Interface*PrototypeContext.Role]',
        REL_IFC_IS_PUBLIC           = 'PrototypeContext.isPublic[PrototypeContext.Interface*PrototypeContext.Interface]',
        REL_IFC_IS_API              = 'PrototypeContext.isAPI[PrototypeContext.Interface*PrototypeContext.Interface]',
        REL_IFC_LABEL               = 'PrototypeContext.label[PrototypeContext.Interface*PrototypeContext.Label]',
        REL_ROLE_LABEL              = 'PrototypeContext.label[PrototypeContext.Role*PrototypeContext.Label]',
        REL_SESSION_ALLOWED_ROLES   = 'PrototypeContext.sessionAllowedRoles[SESSION*PrototypeContext.Role]',
        REL_SESSION_ACTIVE_ROLES    = 'PrototypeContext.sessionActiveRoles[SESSION*PrototypeContext.Role]',
        REL_NAV_LABEL               = 'PrototypeContext.label[PrototypeContext.NavMenuItem*PrototypeContext.Label]',
        REL_NAV_IS_VISIBLE          = 'PrototypeContext.isVisible[PrototypeContext.NavMenuItem*PrototypeContext.NavMenuItem]',
        REL_NAV_IS_PART_OF          = 'PrototypeContext.isPartOf[PrototypeContext.NavMenuItem*PrototypeContext.NavMenu]',
        REL_NAV_IFC                 = 'PrototypeContext.ifc[PrototypeContext.NavMenuItem*PrototypeContext.Interface]',
        REL_NAV_SEQ_NR              = 'PrototypeContext.seqNr[PrototypeContext.NavMenuItem*PrototypeContext.SeqNr]',
        REL_NAV_SUB_OF              = 'PrototypeContext.isSubItemOf[PrototypeContext.NavMenuItem*PrototypeContext.NavMenuItem]';

    const RELATIONS = [
        self::REL_IFC_ROLES,
        self::REL_IFC_IS_PUBLIC,
        self::REL_IFC_IS_API,
        self::REL_IFC_LABEL,
        self::REL_ROLE_LABEL,
        self::REL_SESSION_ALLOWED_ROLES,
        self::REL_SESSION_ACTIVE_ROLES,
        self::REL_NAV_LABEL,
        self::REL_NAV_IS_VISIBLE,
        self::REL_NAV_IS_PART_OF,
        self::REL_NAV_IFC,
        self::REL_NAV_SEQ_NR,
        self::REL_NAV_SUB_OF,
    ];

    const
        IFC_MENU_ITEMS      = 'PrototypeContext.MenuItems';

    public static function containsConcept(Concept $cpt): bool
    {
        return in_array($cpt->getLabel(), self::CONCEPTS);
    }

    public static function containsRelation(Relation $rel): bool
    {
        return in_array($rel->getSignature(), self::RELATIONS);
    }
}
