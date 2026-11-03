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

## Conditions of the deployment

The framework believes the groups header because the proxy is the only way in.
The prototype therefore publishes no port of its own and shares a network with the proxy only; `session.door.trustedProxies` narrows the trust further where the network cannot guarantee it.
Apache drops a header whose name contains an underscore, so the underscore spelling of a header does not reach PHP.

Apache passes the `Authorization` header to PHP through the line `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1`, which the framework images carry in `apache-conf/.htaccess` (production) and `docker/apache/000-default.conf` (development).

A role name is also the name of a group and of a permission in the identity service, so it holds no space and no comma.

The keys of the identity service are cached for ten minutes in the temporary directory of PHP.
A token with a key id that the cache does not know fetches the keys again, at most once a minute.
A machine request without a cookie starts a new session, which the garbage collection of sessions removes after `session.expirationTime`.
