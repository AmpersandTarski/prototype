<?php

namespace Ampersand\Misc;

use Ampersand\Core\Concept;
use Ampersand\Core\Relation;

abstract class ProtoContext
{
    const
        CPT_ROLE            = 'Role',
        CPT_IFC             = 'PF_Interface',
        CPT_LABEL           = 'PF_Label',
        CPT_NAV_MENU        = 'PF_NavMenu',
        CPT_NAV_ITEM        = 'PF_NavMenuItem';

    const CONCEPTS = [
        self::CPT_ROLE,
        self::CPT_IFC,
        self::CPT_LABEL,
        self::CPT_NAV_MENU,
        self::CPT_NAV_ITEM,
    ];
    
    const
        REL_IFC_ROLES               = 'pf_ifcRoles[PF_Interface*Role]',
        REL_IFC_IS_PUBLIC           = 'isPublic[PF_Interface*PF_Interface]',
        REL_IFC_IS_API              = 'isAPI[PF_Interface*PF_Interface]',
        REL_IFC_LABEL               = 'label[PF_Interface*PF_Label]',
        REL_ROLE_LABEL              = 'label[Role*PF_Label]',
        REL_SESSION_ALLOWED_ROLES   = 'sessionAllowedRoles[SESSION*Role]',
        REL_SESSION_ACTIVE_ROLES    = 'sessionActiveRoles[SESSION*Role]',
        REL_NAV_LABEL               = 'label[PF_NavMenuItem*PF_Label]',
        REL_NAV_IS_VISIBLE          = 'isVisible[PF_NavMenuItem*PF_NavMenuItem]',
        REL_NAV_IS_PART_OF          = 'isPartOf[PF_NavMenuItem*PF_NavMenu]',
        REL_NAV_IFC                 = 'ifc[PF_NavMenuItem*PF_Interface]',
        REL_NAV_SEQ_NR              = 'seqNr[PF_NavMenuItem*PF_SeqNr]',
        REL_NAV_SUB_OF              = 'isSubItemOf[PF_NavMenuItem*PF_NavMenuItem]';

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
        IFC_MENU_ITEMS      = 'PF_MenuItems';

    public static function containsConcept(Concept $cpt): bool
    {
        return in_array($cpt->getId(), self::CONCEPTS);
    }

    public static function containsRelation(Relation $rel): bool
    {
        return in_array($rel->getSignature(), self::RELATIONS);
    }
}
