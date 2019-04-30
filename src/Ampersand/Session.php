<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand;

use Exception;
use Ampersand\Core\Atom;
use Psr\Log\LoggerInterface;
use Ampersand\Core\Link;
use Ampersand\Interfacing\Options;
use Ampersand\Interfacing\ResourceList;
use Ampersand\AmpersandApp;
use Ampersand\Exception\RelationNotDefined;

/**
 * Class of session objects
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Session
{
    
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * Reference to Ampersand app for which this session is defined
     *
     * @var \Ampersand\AmpersandApp
     */
    protected $ampersandApp;

    /**
     * Reference to Ampersand app settings object
     *
     * @var \Ampersand\Misc\Settings
     */
    protected $settings;
    
    /**
     * @var string $id session identifier
     */
    protected $id;
    
    /**
     * Reference to corresponding session object (Atom) in &-domain
     *
     * @var \Ampersand\Core\Atom $sessionAtom
     */
    protected $sessionAtom;
    
    /**
     * Constructor of Session class
     *
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Ampersand\AmpersandApp $app
     */
    public function __construct(LoggerInterface $logger, AmpersandApp $app)
    {
        $this->logger = $logger;
        $this->ampersandApp = $app;
        $this->settings = $app->getSettings(); // shortcut to settings object
       
        $this->setId();
        $this->initSessionAtom();
    }

    /**
     * Get session identifier
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    private function setId()
    {
        $this->id = session_id();
        $this->logger->debug("Session id set to: {$this->id}");
    }

    public function reset()
    {
        $this->logger->debug("Reset session {$this->id}");
        $this->sessionAtom->delete(); // Delete Ampersand representation of session
        session_regenerate_id(); // Create new php session identifier
        $this->setId();
        $this->initSessionAtom();
    }
    
    protected function initSessionAtom()
    {
        $this->sessionAtom = $this->ampersandApp->getModel()->getSessionConcept()->makeAtom($this->id);
        
        // Create a new Ampersand session atom if not yet in SESSION table (i.e. new php session)
        if (!$this->sessionAtom->exists()) {
            $this->sessionAtom->add();

            // If login functionality is not enabled, add all defined roles as allowed roles
            // TODO: can be removed when meat-grinder populates this meta-relation by itself
            if (!$this->settings->get('session.loginEnabled')) {
                foreach ($this->ampersandApp->getModel()->getRoleConcept()->getAllAtomObjects() as $roleAtom) {
                    $this->sessionAtom->link($roleAtom, 'sessionAllowedRoles[SESSION*Role]')->add();
                    // Activate all allowed roles by default
                    $this->toggleActiveRole($roleAtom, true);
                }
            }
        } else {
            if (isset($_SESSION['lastAccess']) && (time() - $_SESSION['lastAccess'] > $this->settings->get('session.expirationTime'))) {
                $this->logger->debug("Session expired");
                $this->ampersandApp->userLog()->warning("Your session has expired");
                $this->reset();
                return;
            }
        }
        
        // Update session variable. This is needed because windows platform doesn't seem to update the read time of the session file
        // which will cause a php session timeout after the default timeout of (24min), regardless of user activity. By updating the
        // session file (updating 'lastAccess' variable) we ensure the the session file timestamps are updated on every request.
        $_SESSION['lastAccess'] = time();
        // Update lastAccess time also in plug/database to allow to use this aspect in Ampersand models
        $this->sessionAtom->link(date(DATE_ATOM, $_SESSION['lastAccess']), 'lastAccess[SESSION*DateTime]', false)->add();
    }

    /**
     * Get ampersand atom representation of this session object
     *
     * @return \Ampersand\Core\Atom
     */
    public function getSessionAtom(): Atom
    {
        return $this->sessionAtom;
    }

    /**
     * (De)activate a session role
     *
     * This function to (de)activate roles depends on the invariant as defined in SystemContext.adl
     * RULE sessionActiveRole |- sessionAllowedRole
     *
     * @param \Ampersand\Core\Atom $roleAtom
     * @param bool|null $setActive
     * @return \Ampersand\Core\Atom
     */
    public function toggleActiveRole(Atom $roleAtom, bool $setActive = null): Atom
    {
        // Check/prevent unexisting role atoms
        if (!$roleAtom->exists()) {
            throw new Exception("Role {$roleAtom} is not defined", 500);
        }

        $link = $this->sessionAtom->link($roleAtom, 'sessionActiveRoles[SESSION*Role]');
        switch ($setActive) {
            case true:
                $link->add();
                break;
            case false:
                if ($link->exists()) {
                    $link->delete();
                }
                break;
            case null:
                if ($link->exists()) {
                    $link->delete();
                } else {
                    $link->add();
                }
                break;
        }

        return $roleAtom;
    }
    
    /**
     * Get allowed roles for this session
     *
     * @return \Ampersand\Core\Atom[]
     */
    public function getSessionAllowedRoles()
    {
        return array_map(function (Link $link) {
            return $link->tgt();
        }, $this->sessionAtom->getLinks('sessionAllowedRoles[SESSION*Role]'));
    }

    /**
     * Get active roles for this session
     *
     * @return \Ampersand\Core\Atom[]
     */
    public function getSessionActiveRoles()
    {
        return array_map(function (Link $link) {
            return $link->tgt();
        }, $this->sessionAtom->getLinks('sessionActiveRoles[SESSION*Role]'));
    }
    
    /**
     * Get session account or false
     *
     * @return Atom|false returns Ampersand account atom when there is a session account or false otherwise
     */
    public function getSessionAccount()
    {
        $this->logger->debug("Getting sessionAccount");

        if (!$this->settings->get('session.loginEnabled')) {
            $this->logger->debug("No session account, because login functionality is not enabled");
            return false;
        } else {
            try {
                $sessionAccounts = $this->sessionAtom->getLinks('sessionAccount[SESSION*Account]');
            } catch (RelationNotDefined $e) {
                throw new Exception("Relation sessionAccount[SESSION*Account] is not defined. You SHOULD include the SIAM module to use the login functionality.", 500, $e);
            }
            
            // Relation sessionAccount is UNI
            if (empty($sessionAccounts)) {
                $this->logger->debug("No session account, because user is not logged in");
                return false;
            } else {
                $account = current($sessionAccounts);
                $this->logger->debug("Session account is: '{$account}'");
                return $account;
            }
        }
    }

    /**
     * Set session account and register login timestamps
     *
     * @param \Ampersand\Core\Atom $accountAtom
     * @return \Ampersand\Core\Atom
     */
    public function setSessionAccount(Atom $accountAtom): Atom
    {
        try {
            if (!$accountAtom->exists()) {
                throw new Exception("Account does not exist", 500);
            }

            $this->sessionAtom->link($accountAtom, 'sessionAccount[SESSION*Account]')->add();
            
            // Login timestamps
            $ts = date(DATE_ISO8601);
            $accountAtom->link($ts, 'accMostRecentLogin[Account*DateTime]')->add();
            $accountAtom->link($ts, 'accLoginTimestamps[Account*DateTime]')->add();

            return $accountAtom;
        } catch (RelationNotDefined $e) {
            throw new Exception("Relation sessionAccount[SESSION*Account], accMostRecentLogin[Account*DateTime] and/or accLoginTimestamps[Account*DateTime] are not defined. You SHOULD include the SIAM module to use the login functionality.", 500, $e);
        }
    }
    
    /**
     * Determine is there is a loggedin user (account)
     * @return boolean
     */
    public function sessionUserLoggedIn()
    {
        if (!$this->settings->get('session.loginEnabled')) {
            return false;
        } elseif ($this->getSessionAccount() !== false) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Get session variables (from 'SessionVars' interface)
     * @return mixed|false session variables (if interface 'SessionVars' is defined in &-script) or false otherwise
     */
    public function getSessionVars()
    {
        if ($this->ampersandApp->getModel()->interfaceExists('SessionVars')) {
            try {
                $this->logger->debug("Getting interface 'SessionVars' for {$this->sessionAtom}");
                return ResourceList::makeFromInterface($this->id, 'SessionVars')->get(Options::INCLUDE_NOTHING);
            } catch (Exception $e) {
                $this->logger->error("Error while getting SessionVars interface: " . $e->getMessage());
                return false;
            }
        } else {
            return false;
        }
    }
    
    /**********************************************************************************************
     * Static functions
     *********************************************************************************************/
    public static function deleteExpiredSessions(AmpersandApp $ampersandApp): void
    {
        $experationTimeStamp = time() - $ampersandApp->getSettings()->get('session.expirationTime');
        
        $links = $ampersandApp->getRelation('lastAccess[SESSION*DateTime]')->getAllLinks();
        foreach ($links as $link) {
            if (strtotime($link->tgt()->getLabel()) < $experationTimeStamp) {
                $link->src()->delete();
            }
        }
    }
}
