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
use Ampersand\Exception\AtomNotFoundException;
use Ampersand\Exception\BadRequestException;
use Ampersand\Exception\MetaModelException;
use Ampersand\Exception\NotDefined\RelationNotDefined;
use Ampersand\Exception\SessionExpiredException;
use Ampersand\Misc\ProtoContext;
use Ampersand\Misc\Settings;

/**
 * Class of session objects
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Session
{
    
    /**
     * Logger
     */
    private LoggerInterface $logger;

    /**
     * Reference to Ampersand app for which this session is defined
     */
    protected AmpersandApp $ampersandApp;

    /**
     * Reference to Ampersand app settings object
     */
    protected Settings $settings;
    
    /**
     * Session identifier
     */
    protected string $id;
    
    /**
     * Reference to corresponding session object (Atom) in &-domain
     */
    protected Atom $sessionAtom;
    
    /**
     * Constructor
     */
    public function __construct(LoggerInterface $logger, AmpersandApp $app)
    {
        $this->logger = $logger;
        $this->ampersandApp = $app;
        $this->settings = $app->getSettings(); // shortcut to settings object
       
        $this->setId();
    }

    /**
     * Get session identifier
     */
    public function getId(): string
    {
        return $this->id;
    }

    private function setId(): void
    {
        $this->id = session_id();
        $this->logger->debug("Session id set to: {$this->id}");
    }
    
    public function initSessionAtom(): void
    {
        $this->sessionAtom = $this->ampersandApp->getModel()->getSessionConcept()->makeAtom($this->id);
        $now = time();
        
        // Create a new Ampersand session atom if not yet in SESSION table (i.e. new php session)
        if (!$this->sessionAtom->exists()) {
            $this->sessionAtom->add();

            // If login functionality is not enabled, add all defined roles as allowed roles
            if (!$this->settings->get('session.loginEnabled')) {
                foreach ($this->ampersandApp->getModel()->getRoleConcept()->getAllAtomObjects() as $roleAtom) {
                    $this->sessionAtom->link($roleAtom, ProtoContext::REL_SESSION_ALLOWED_ROLES)->add();
                    // Activate all allowed roles by default
                    $this->toggleActiveRole($roleAtom, true);
                }
            }
        // When session atom already exists check if session is expired
        } else {
            $experationTimeStamp = $now - $this->settings->get('session.expirationTime');
            
            $links = $this->sessionAtom->getLinks(ProtoContext::REL_SESSION_LAST_ACCESS);
            foreach ($links as $link) {
                if (strtotime($link->tgt()->getId()) < $experationTimeStamp) {
                    $this->logger->debug("Session expired");
                    // $this->deleteSessionAtom();
                    throw new SessionExpiredException("Your session has expired");
                }
            }
        }
        
        /**
         * We use the database to lookup last access timestamp of a session. This is because in a containerized application
         * landscape, the user isn't redirected to the same container for every request. Php session records are stored locally,
         * so we cannot use these (anymore).
         * Further more, a container restart would "logout" every user by removing the session records, which is unwanted behaviour.
         *
         * NOTE! Comment below is not applicable anymore because we've changed the 'session.use_strict_mode' to 0.
         * That means, if a user provides a php session id which is uninitialized for this php server, it is assigned that session id anyway.
         *
         * OLD COMMENT:
         * Update session variable. This is needed because windows platform doesn't seem to update the read time of the session file
         * which will cause a php session timeout after the default timeout of (24min), regardless of user activity. By updating the
         * session file (updating 'lastAccess' variable) we ensure the the session file timestamps are updated on every request.
         */
        // $_SESSION['lastAccess'] = $now;
        
        // Update lastAccess time also in plug/database to allow to use this aspect in Ampersand models
        $this->sessionAtom->link(date(DATE_ATOM, $now), ProtoContext::REL_SESSION_LAST_ACCESS, false)->add();
    }

    /**
     * Get ampersand atom representation of this session object
     */
    public function getSessionAtom(): Atom
    {
        return $this->sessionAtom;
    }

    /**
     * Delete Ampersand representation of session
     */
    public function deleteSessionAtom(): void
    {
        $this->sessionAtom->delete();
    }

    /**
     * (De)activate a session role
     *
     * This function to (de)activate roles depends on the invariant as defined in SystemContext.adl
     * RULE sessionActiveRole |- sessionAllowedRole
     */
    public function toggleActiveRole(Atom $roleAtom, ?bool $setActive = null): Atom
    {
        // Check/prevent unexisting role atoms
        if (!$roleAtom->exists()) {
            throw new BadRequestException("Role {$roleAtom} is not defined");
        }

        $link = $this->sessionAtom->link($roleAtom, ProtoContext::REL_SESSION_ACTIVE_ROLES);
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
    public function getSessionAllowedRoles(): array
    {
        return array_map(function (Link $link) {
            return $link->tgt();
        }, $this->sessionAtom->getLinks(ProtoContext::REL_SESSION_ALLOWED_ROLES));
    }

    /**
     * Get active roles for this session
     *
     * @return \Ampersand\Core\Atom[]
     */
    public function getSessionActiveRoles(): array
    {
        return array_map(function (Link $link) {
            return $link->tgt();
        }, $this->sessionAtom->getLinks(ProtoContext::REL_SESSION_ACTIVE_ROLES));
    }
    
    /**
     * Get session account or false
     *
     * Returns Ampersand account atom when there is a session account or false otherwise
     */
    public function getSessionAccount(): Atom|false
    {
        $this->logger->debug("Getting sessionAccount");

        if (!$this->settings->get('session.loginEnabled')) {
            $this->logger->debug("No session account, because login functionality is not enabled");
            return false;
        } else {
            try {
                $sessionAccounts = $this->sessionAtom->getLinks('sessionAccount[SESSION*Account]');
            } catch (RelationNotDefined $e) {
                throw new MetaModelException("Relation sessionAccount[SESSION*Account] is not defined. You SHOULD include the SIAM module to use the login functionality.", previous: $e);
            }
            
            // Relation sessionAccount is UNI
            if (empty($sessionAccounts)) {
                $this->logger->debug("No session account, because user is not logged in");
                return false;
            } else {
                $account = current($sessionAccounts);
                $this->logger->debug("Session account is: '{$account}'");
                return $account->tgt();
            }
        }
    }

    /**
     * Set session account and register login timestamps
     */
    public function setSessionAccount(Atom $accountAtom): Atom
    {
        try {
            if (!$accountAtom->exists()) {
                throw new AtomNotFoundException("Account '{$accountAtom}' does not exist");
            }

            $this->sessionAtom->link($accountAtom, 'sessionAccount[SESSION*Account]')->add();
            
            // Login timestamps
            $ts = date(DATE_ISO8601);
            $accountAtom->link($ts, 'accMostRecentLogin[Account*DateTime]')->add();
            $accountAtom->link($ts, 'accLoginTimestamps[Account*DateTime]')->add();

            return $accountAtom;
        } catch (RelationNotDefined $e) {
            throw new MetaModelException("Required meta model for login functionality is not defined. You SHOULD include the SIAM module", previous: $e);
        }
    }
    
    /**
     * Determine is there is a loggedin user (account)
     */
    public function sessionUserLoggedIn(): bool
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
     *
     * Returns session variables (if interface 'SessionVars' is defined in &-script) or false otherwise
     */
    public function getSessionVars(): mixed
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
    public static function resetPhpSessionId(): void
    {
        session_regenerate_id(true);
    }
    
    public static function deleteExpiredSessions(AmpersandApp $ampersandApp): void
    {
        $experationTimeStamp = time() - $ampersandApp->getSettings()->get('session.expirationTime');
        
        $links = $ampersandApp->getRelation(ProtoContext::REL_SESSION_LAST_ACCESS)->getAllLinks();
        foreach ($links as $link) {
            if (strtotime($link->tgt()->getId()) < $experationTimeStamp) {
                $link->src()->delete();
            }
        }
    }
}
