---
title: The door
description: How a prototype takes the roles of its visitor from an identity service in front of it, from the groups header of a proxy or from a bearer token.
---

# The door

The door lets a prototype run on the internet behind an identity service.
A person passes the identity service with a passkey and a machine with a token; the prototype learns the roles of its visitor and does not need to know who the visitor is.
The roles in your script decide what each visitor may do, as they always do: an `INTERFACE ... FOR` a role is open to that role only.
The guide "Securing your prototype without passwords" in the Ampersand documentation describes the whole set-up with Pocket ID, oauth2-proxy and Caddy.
This page describes the part of the framework (design choice OK-32).

## Switching it on

The door is off by default, and then nothing changes.

| Setting | Environment variable | Meaning |
| --- | --- | --- |
| `session.door` | `AMPERSAND_DOOR` | `proxy` switches the door on; `null` (default) keeps it off |
| `session.door.groupsHeader` | `AMPERSAND_DOOR_GROUPS_HEADER` | the header in which the proxy passes the names of the visitor's groups; default `X-Auth-Request-Groups` |
| `session.door.tokenIssuer` | `AMPERSAND_DOOR_TOKEN_ISSUER` | the address of the identity service that signs machine tokens, such as `https://id.example.org` |
| `session.door.tokenAudience` | `AMPERSAND_DOOR_TOKEN_AUDIENCE` | the address under which the prototype is registered as an API at the identity service |
| `session.door.trustedProxies` | `AMPERSAND_DOOR_TRUSTED_PROXIES` | optional: comma-separated addresses or ranges (`172.18.0.0/16`) from which the groups header is believed |

`session.loginEnabled` stays `false`: the door needs no account and no SIAM module.

## Which roles a request gets

With the door on, every request decides the roles of its session.

- A request with `Authorization: Bearer <token>` gets the roles in the claim `scp` of the token, or in the space-separated claim `scope` when `scp` is absent.
  The framework first checks the signature against the keys that the OpenID configuration of `session.door.tokenIssuer` names, the algorithm (asymmetric only), the issuer, the audience and the expiry.
  A token that fails a check ends the request with 401 and a message that names the kind of failure.
- A request without a token gets the names in the groups header, separated by commas.
  When `session.door.trustedProxies` is set, the header counts only from those addresses.
- A request that presents neither gets the role Anonymous.

Of the presented names, only roles that the script declares count.
ExecEngine and SYSTEM, the roles with which the framework itself restores rules, never count, nor do the wildcards Any and Anonymous.

The allowed roles of the session become exactly the presented roles; Anonymous stays allowed as well, because the PrototypeContext of the compiler allows it to every session.
A session has one active role at a time.
The first presented role becomes active, and a role that the visitor chose in the role menu stays active as long as the request presents it.
Because this happens on every request, a group that the administrator removes in the identity service closes the door at the next request.

## Access from every allowed role

By default, a session opens the interfaces of its one active role, and a role-less interface opens for every role except Anonymous.
A person with two roles then switches in the role menu, and a machine role opens every role-less interface.
A script that wants access from every allowed role, with machine roles that reach only the interfaces that name them, declares an interface that lists the accessible interfaces and names it in `rbac.accessibleInterfacesIfcId` (environment variable `AMPERSAND_ACCESSIBLE_INTERFACES_IFC`; design choice OK-34):

```
INTERFACE Access FOR ExecEngine : PrototypeContext.sessionAllowedRoles;PrototypeContext.ifcRoles~
   \/ PrototypeContext.sessionAllowedRoles;(I[PrototypeContext.Role] - ("Anonymous"[PrototypeContext.Role] \/ "Stampreporter"[PrototypeContext.Role]));V[PrototypeContext.Role*PrototypeContext.Role];"Any"[PrototypeContext.Role];PrototypeContext.ifcRoles~
   cRud BOX<TABLE> [ "interface" : I[PrototypeContext.Interface] cRud ]
```

The first line gives the interfaces of every allowed role.
The second gives the role-less interfaces to every allowed role except Anonymous and the machine roles, here `Stampreporter`; a script lists its own machine roles there.
With the door off, every role is allowed, so every interface opens, whichever role is active.
The rules that a session maintains still follow the active role.

## Conditions of the deployment

The framework believes the groups header because the proxy is the only way in.
The prototype therefore publishes no port of its own and shares a network with the proxy only; `session.door.trustedProxies` narrows the trust further where the network cannot guarantee it.
Apache drops a header whose name contains an underscore, so the underscore spelling of a header does not reach PHP.

Apache passes the `Authorization` header to PHP through the line `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1`, which the framework images carry in `apache-conf/.htaccess` (production) and `docker/apache/000-default.conf` (development).

A role name is also the name of a group in the identity service, so it holds no comma and has at least two characters.
A role that a machine can get is also a permission, and Pocket ID accepts as permission only an OAuth scope token: printable ASCII without space, double quote or backslash, and none of `openid`, `profile`, `email`, `email_verified`, `groups` and `offline_access`.
A role such as `Coördinator` can therefore be given to people, and not to machines.

The keys of the identity service are cached for ten minutes in the temporary directory of PHP.
A token with a key id that the cache does not know fetches the keys again, at most once a minute.
A machine request without a cookie starts a new session, which the garbage collection of sessions removes after `session.expirationTime`.

## The role synchroniser

The identity service has to know every role of the script, as a group for people and as a permission for machines (design choice OK-33).
The program `backend/bin/door-sync-roles.php`, which every prototype image carries, sees to that.
It reads the roles from `backend/generics/roles.json`, which the compiler writes with every prototype, and adds to Pocket ID what is missing: a group per role, the API of the prototype with `session.door.tokenAudience` as its resource, and a permission per role on that API.
It removes nothing, because Pocket ID may know groups of other applications, and a permission that stays keeps its grants to machines.
ExecEngine, SYSTEM, Any and Anonymous get no group and no permission.

A deployment runs it after the prototype has started:

```sh
docker exec <prototype> php /var/www/backend/bin/door-sync-roles.php --dry-run   # says what it would do
docker exec <prototype> php /var/www/backend/bin/door-sync-roles.php
```

It reads `AMPERSAND_DOOR_TOKEN_ISSUER` (the address of Pocket ID), `AMPERSAND_DOOR_TOKEN_AUDIENCE` and `POCKET_ID_API_KEY`, an API key that an administrator makes in Pocket ID under Settings, API keys.
That key can change every group and permission in Pocket ID, so it lives only in the `.env` of the server.
The program prints a line per role and exits 0 when every role has its group, 1 when a role could not get one (a comma in its name, or fewer than two characters), and 2 when it could not run.
A role that cannot be a permission is no error: the report names it as a role that machines cannot get.
