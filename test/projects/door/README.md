# door — regression vehicle

**Guards:** the door (OK-32): with `session.door: proxy` a session holds exactly the roles that the request presents, from the groups header of a proxy or from a bearer token that the framework checks itself; the framework's own roles and undeclared names never count, a role taken away is gone at the next request, and a refused token gives 401.

**Origin:** passwordless security for prototypes on the internet (Werkplaats#132), with Pocket ID as identity service and oauth2-proxy in front of the prototype.

**Run:** `test/run-regression.sh door`.
`regression.vars` switches the door on.
The spec plays the identity service itself: it makes an RSA key, serves the OpenID configuration and the key set on port 9473 of the host, and signs the tokens it sends.

**Green means:** no header gives Anonymous; a header gives exactly its declared roles; `PublicIfc` (the wildcard Any) is open with an authenticated role and closed with Anonymous; a later request on the same session with fewer groups loses the role; a valid token gives the roles in its `scp`; an expired token, a token for another audience and a token signed with another key give 401.
