# Asking for cookie consent

A generated prototype stores only what it needs to work: a session cookie and two items in the browser's session storage. It asks no consent for them, because European law exempts storage that is strictly necessary for the service the user asked for. [Cookies and browser storage](../reference-material/cookies-and-browser-storage.md) lists them and explains the exemption.

This guide is for the developer whose project stores more. Web analytics, a video or a map from another site, or a preference kept after the session ends all put something in the user's browser that the application could work without. Article 5(3) of the ePrivacy Directive (2002/58/EC) then requires the user's consent before anything is stored. The guide adds a consent banner to a project with the open-source library [CookieConsent](https://github.com/orestbida/cookieconsent) by Orest Bida, from the project's own Dockerfile. The framework itself does not change.

## Does your project need consent?

Ask of each cookie or storage item whether the application could deliver what the user asked for without it. If it could, the item needs consent.

| No consent needed | Consent needed |
| --- | --- |
| The session cookie and the session-storage items of the framework | Web analytics, such as Matomo or Google Analytics, when they set cookies |
| A cookie that keeps a user logged in | A video, map or social-media post embedded from another site, which sets that site's cookies |
| A cookie that stores the consent choice itself | Advertising and tracking of any kind |

A choice the user makes explicitly, such as a language, may count as necessary; a preference the application remembers on its own for months usually does not. The privacy officer of the organisation that deploys the application decides the cases in between.

## Which library

The guide uses CookieConsent version 3. It was weighed against three alternatives on 29 September 2026:

| Option | License | Stars on GitHub | Latest release / last commit | Note |
| --- | --- | --- | --- | --- |
| [orestbida/cookieconsent](https://github.com/orestbida/cookieconsent) | MIT | 5688 | v3.1.0 (Feb 2025) / Jul 2026 | ES module without dependencies; 10 kB JavaScript and 5 kB CSS after gzip |
| [tarteaucitron.js](https://github.com/AmauriC/tarteaucitron.js) | MIT | 1058 | v1.35.0 (Sep 2026) | built-in catalogue of services, French-oriented setup |
| [Klaro](https://github.com/kiprotect/klaro) | not recognised by GitHub | 1519 | last commit Mar 2025 | check the license before use |
| Consent as a relation on `SESSION` or on the account, in the Ampersand model | – | – | – | An anonymous user would be asked again in every session, and it takes more to build |

CookieConsent wins because it is MIT-licensed, fits an Angular build as an ES module, is maintained, and needs no external service.

## Adding it to your project

A project changes the frontend in its own Dockerfile, after the compiler has generated the frontend and before `npx ng build`. The Dockerfile in `test/regression/` marks that spot. Four additions are needed there.

First, a configuration file in the project, for instance `customizations/cookie-consent.ts`. It names the categories of storage and the texts of the banner:

```ts
// Cookie consent for this project. main.ts imports this file (see the Dockerfile).
import * as CookieConsent from 'vanilla-cookieconsent';

CookieConsent.run({
  categories: {
    // The session cookie PHPSESSID: the application needs it, so the user cannot switch it off.
    necessary: { enabled: true, readOnly: true },
    // Everything the project adds on top. autoClear removes these cookies when consent is withdrawn.
    analytics: {
      autoClear: { cookies: [{ name: /^_pk_/ }] },
    },
  },
  language: {
    default: 'en',
    translations: {
      en: {
        consentModal: {
          title: 'Cookies',
          description:
            'This application needs a session cookie to work. With your consent it also measures ' +
            'which screens are used. See the <a href="https://www.example.org/privacy">privacy statement</a>.',
          acceptAllBtn: 'Accept all',
          acceptNecessaryBtn: 'Only necessary',
          showPreferencesBtn: 'Choose',
        },
        preferencesModal: {
          title: 'Cookie settings',
          acceptAllBtn: 'Accept all',
          acceptNecessaryBtn: 'Only necessary',
          savePreferencesBtn: 'Save my choice',
          sections: [
            {
              title: 'Necessary',
              description: 'The session cookie, which keeps you logged in and holds your roles.',
              linkedCategory: 'necessary',
            },
            {
              title: 'Analytics',
              description: 'Counts which screens are used, so that we can improve them.',
              linkedCategory: 'analytics',
            },
          ],
        },
      },
    },
  },
});

// Angular renders the footer after run(), so its "Cookie settings" button is not bound by the
// library itself. Listen on the document instead.
document.addEventListener('click', (event) => {
  if ((event.target as Element).closest('[data-cc="show-preferencesModal"]')) {
    CookieConsent.showPreferences();
  }
});
```

The pattern `/^_pk_/` matches the cookies of Matomo; use the names of the cookies your own services set. The library's [documentation](https://cookieconsent.orestbida.com/) describes every option.

Second, a footer with a button that opens the settings again, for instance `customizations/app.footer.component.html`. The GDPR requires that withdrawing consent is as easy as giving it (article 7(3)):

```html
<div class="layout-footer">
    Generated with <a href="https://ampersandtarski.github.io/" target="_blank">Ampersand</a>
    &nbsp;·&nbsp;<button type="button" class="p-link" data-cc="show-preferencesModal">Cookie settings</button>
</div>
```

Third, the snippet of each third-party service, for instance `customizations/analytics.html`. The attributes `type="text/plain"` and `data-category` keep the browser from running it until the user accepts that category:

```html
<script type="text/plain" data-category="analytics">
  // the snippet your analytics service gives you
</script>
```

Fourth, the lines in the Dockerfile that put these files in place. They install the library, import the configuration from `main.ts` and its stylesheet from `styles.scss`, add the snippet to `index.html`, and replace the footer:

```dockerfile
WORKDIR /var/www/frontend

RUN npm install --no-audit --no-fund vanilla-cookieconsent@3.1.0
COPY customizations/cookie-consent.ts src/cookie-consent.ts
RUN printf "\nimport './cookie-consent';\n" >> src/main.ts \
 && printf '\n@import "../node_modules/vanilla-cookieconsent/dist/cookieconsent";\n' >> src/styles.scss
RUN sed -i '/<\/head>/r /usr/local/project/customizations/analytics.html' src/index.html
COPY customizations/app.footer.component.html src/app/layout/app.footer.component.html

RUN npx ng build
```

The stylesheet is imported without its extension `.css`, so that Sass copies its content into the bundle. The generated code stays untouched, so a new compiler run does not undo any of this.

## Your own code

Code in the project that stores something optional asks first whether the user accepted its category:

```ts
import * as CookieConsent from 'vanilla-cookieconsent';

if (CookieConsent.acceptedCategory('analytics')) {
  // store or send
}
```

To act the moment the user changes the choice, give `run()` an `onChange` callback.

## The consent cookie

CookieConsent remembers the choice in a cookie of its own, `cc_cookie`, for 182 days. That cookie is needed to respect the choice, so it needs no consent itself, but it belongs in the privacy statement. It is `Secure` by default: a browser stores it only over HTTPS (and on `localhost`), so over plain HTTP the banner would return on every page. Serve the application over HTTPS.

## The privacy statement

Add the consent cookie and the cookies of every optional service to the privacy statement, next to the rows from [Cookies and browser storage](../reference-material/cookies-and-browser-storage.md). The setting `frontend.privacyStatementUrl` links the statement from the footer; a project with its own footer keeps that link as the reference page describes.

## Checking the result

Open the application in a fresh private browser window, with the developer tools on the tab that shows cookies:

1. The banner appears, and the only cookie is `PHPSESSID`.
2. After "Accept all", `cc_cookie` and the cookies of the optional services appear.
3. After "Cookie settings" and "Only necessary", the cookies of the optional services are gone and `cc_cookie` records the new choice.

CookieConsent shows no banner to a browser that identifies itself as automated (the option `hideFromBots`, on by default), so that search engines do not index the banner. An automated end-to-end test of these steps therefore makes its browser look like a person's: with Puppeteer, `navigator.webdriver` returns `false` and the user agent reads `Chrome` instead of `HeadlessChrome`.

These steps were followed on 29 September 2026 with such a Puppeteer script, on a project built on `ampersandtarski/prototype-framework:v2.10.0` with CookieConsent 3.1.0 and a stand-in for an analytics snippet that sets the cookie `_pk_id.demo`. All three steps behaved as described, and after a reload the banner stayed away and the stand-in did not run.
