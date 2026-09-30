# Cookies and browser storage

A generated prototype sets one cookie and keeps two small items in the browser's session storage. On the server it records the session of each visitor and logs the IP address of requests. This page lists all of it, with lifetime and purpose, for the organisation that deploys a prototype. Its privacy officer can copy the tables into the privacy statement, which European law requires: article 13 of the GDPR obliges the organisation to tell users which personal data it processes.

The page describes the framework. A model can add relations on `SESSION`, such as the account of a logged-in user, and a project can add code of its own. Those belong in the privacy statement as well, and the project documents them.

## In the browser

| Name | Kind | Set by | Lifetime | Purpose |
| --- | --- | --- | --- | --- |
| `PHPSESSID` | cookie | `backend/bootstrap/framework.php` (`session_start()`) | until the browser closes | Identifies the session of the user. |
| `menuItems` | sessionStorage | `frontend/src/app/layout/app.menu.component.ts` | until the tab closes | Caches the navigation menu, so that it is not rebuilt on every page. |
| `adminMode` | sessionStorage | `frontend/src/app/layout/app.menu.service.ts` | until the tab closes | Remembers whether the user switched on the admin menu. |

The cookie is `HttpOnly`, so scripts in the page cannot read it. It is `SameSite=Lax`, so the browser leaves it out of requests that another site starts, except when the user follows a link. It is `Secure`, which keeps it off plain HTTP, as the setting `session.cookieSecure` says: `auto` (the default) sets it when the request arrives over HTTPS, also behind a reverse proxy that sends `X-Forwarded-Proto: https`, and `true` sets it always, which is what a production deployment should use. Its value is the identifier of the user's session on the server.

No third party receives a request from a generated prototype. The font (Inter) and the icons are served from the application's own origin, and there is no content delivery network and no web analytics.

## On the server

| What | Where | Lifetime | Purpose |
| --- | --- | --- | --- |
| A `SESSION` atom whose identifier is the cookie value, with the relations `lastAccess`, `sessionAllowedRoles` and `sessionActiveRoles` | the application database | until `session.expirationTime` (3600 seconds by default) has passed without a request; it is removed when the next new session starts | Access control: which roles the session holds, and whether it has expired. |
| A PHP session file named after the cookie value | the session directory of the PHP container | until PHP's own garbage collection removes it | Required by `session_start()`; the framework stores nothing in it. |
| IP address, HTTP method, URL and a request id, in every log line of level NOTICE and higher | standard output and standard error of the container | as long as the deployment keeps container logs | Error analysis. When an error occurs, the last 500 lines of level DEBUG are written as well; they include the session identifier. |
| The text of every database query, including the session identifier and data values | the OpenTelemetry collector, only when tracing is switched on (it is off by default, see [measuring performance](../guides/measuring-performance-with-opentelemetry.md)) | as long as the collector keeps traces | Performance measurement. |

## Why the prototype asks no consent

Article 5(3) of the ePrivacy Directive (2002/58/EC) requires consent before anything is stored in the user's browser, except for storage that is strictly necessary to provide the service the user asked for. The Netherlands implements this in article 11.7a of the Telecommunicatiewet.

The session cookie meets that exception. Every interface query takes the session from the cookie, and the roles and the login of the user hang off that session (see the [architecture of an Ampersand application](https://ampersandtarski.github.io/ampersand/reference-material/architecture-of-an-ampersand-application)). Without the cookie no interface opens. The two items in session storage hold state of the user interface and disappear with the tab.

A project that stores more, such as analytics or an embedded video, does need consent. That is up to the project, not the framework.

This is a reading of the rules, not legal advice. The privacy officer of the deploying organisation confirms it.

## Linking the privacy statement

Set the URL of the privacy statement in `backend/config/project.yaml`:

```yaml
settings:
  frontend.privacyStatementUrl: https://www.example.org/privacy
```

The footer of every screen then shows a link "Privacy and cookies" that opens the statement in a new tab. Without the setting the footer shows no link.

A project that replaces `frontend/src/app/layout/app.footer.component.html` with a footer of its own keeps the link by including this element:

```html
<ng-container *ngIf="privacyStatementUrl$ | async as privacyStatementUrl">
    <a class="privacy-statement-link" [href]="privacyStatementUrl" target="_blank" rel="noopener">Privacy and cookies</a>
</ng-container>
```

## Keeping this page true

The regression project `test/projects/privacy-and-cookies` reads the table "In the browser" on this page. Its spec fails when the frontend or backend code writes a cookie or storage item that the table does not list, and when a browser visit leaves one behind. Whoever adds storage therefore adds a row here first.
