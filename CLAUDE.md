# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Nextcloud app that integrates ONLYOFFICE Document Server. Requires Nextcloud and PHP. The backend is PHP, the frontend is a multi-entry Vite build. The app registers file actions, a sidebar share tab, a viewer, and a standalone editor page.

## Build & Development Commands

```bash
npm install        # install JS dependencies
npm run build      # production build
npm run dev        # development build (unminified, source maps)

composer install   # install PHP dependencies

# Linting & static analysis
npx eslint *.js              # JavaScript linting
npx stylelint ../css/*.css   # CSS linting
composer run psalm           # Psalm static analysis
```

Build output goes to `js/` (bundles) and `css/` (extracted stylesheets, one per entry point).

## How the Editor Works

The editor page loads `editor.js` inside an `<iframe>`. That script fetches an editor config from the PHP backend via the OCS API (`/ocs/v2.php/apps/onlyoffice/api/v1/config/{fileId}`), then calls `new DocsAPI.DocEditor()` with that config. DocsAPI is an external JS library served by the ONLYOFFICE Document Server itself — it is not bundled.

The Document Server communicates back to the app through callbacks defined in the config (save, error, ready, etc.) and through `postMessage` for actions triggered by the user inside the editor (save-as, insert image, etc.).

## Frontend Structure

Nine Vite entry points — see `vite.config.mjs`. Each is loaded on a specific page via PHP listeners/templates.

| Entry file | Output bundle | Where it runs | Purpose |
|---|---|---|---|
| `src/main.js` | `onlyoffice-main` | Files app | File actions, new-file menu, format conversion |
| `src/editor.js` | `onlyoffice-editor` | Editor iframe | Loads DocsAPI, handles editor callbacks |
| `src/listener.js` | `onlyoffice-listener` | Files app parent window | Handles postMessage from editor iframe |
| `src/viewer.js` | `onlyoffice-viewer` | Viewer app | Embedded viewer sidebar integration |
| `src/settings.js` | `onlyoffice-settings` | Admin settings page | Document server config UI (Vue) |
| `src/share.js` | `onlyoffice-share` | Share sidebar | Collaboration settings in share tab |
| `src/template.js` | `onlyoffice-template` | Files app | Template selection UI |
| `src/directeditor.js` | `onlyoffice-directeditor` | Direct editor | Standalone direct-editing mode |
| `src/desktop.js` | `onlyoffice-desktop` | Desktop app | Nextcloud Desktop integration |

**`editor.js` and `listener.js` are a pair.** When the editor opens in the same tab (not a new window), `listener.js` runs in the Files app parent window and `editor.js` runs in the iframe. They communicate via `postMessage` — `editor.js` posts requests (save-as, insert image, reference source, etc.) and `listener.js` handles them by opening file pickers and posting responses back.

Entry point files use an IIFE + `OCA.Onlyoffice` global namespace pattern. Vue components used by `settings.js` live in `src/views/` (dialogs) and `src/components/` (items).

## PHP Backend

**Routing:** `appinfo/routes.php` defines two groups:
- Web routes at `/apps/onlyoffice/*` — editor page, AJAX endpoints, callbacks
- OCS routes at `/ocs/v2.php/apps/onlyoffice/api/v1/*` — config, sharing, federation

**Controllers** (`lib/Controller/`):

| Controller | Purpose |
|---|---|
| `EditorApiController` | OCS `GET /api/v1/config/{fileId}` — builds and returns editor config with JWT |
| `EditorController` | Editor page rendering, file creation, conversion, history, version restore |
| `CallbackController` | Receives save/track callbacks from the Document Server (`/track`, `/download`) |
| `SettingsController` | Admin settings save/clear endpoints |
| `TemplateController` | Template CRUD endpoints |
| `SharingApiController` | OCS share permissions endpoints |
| `FederationController` | OCS key exchange and healthcheck for federated instances |
| `JobListController` | Background job management, called at boot |

**Listeners** (`lib/Listeners/`) — inject scripts/styles into pages and react to Nextcloud events:

| Listener | Event | What it does |
|---|---|---|
| `FilesListener` | `LoadAdditionalScriptsEvent` | Injects `main`, `desktop`, `template`, `listener` bundles into Files app |
| `FileSharingListener` | `BeforeTemplateRenderedEvent` | Injects `share` bundle into sharing sidebar |
| `ViewerListener` | `LoadViewer` | Injects `viewer` bundle |
| `DirectEditorListener` | `RegisterDirectEditorEvent` | Registers direct editor for supported file types |
| `ContentSecurityPolicyListener` | `AddContentSecurityPolicyEvent` | Adds Document Server domain to CSP |
| `CreateFromTemplateListener` | `FileCreatedFromTemplateEvent` | Populates new files with empty templates |
| `FileListener` | `NodeDeletedEvent`, `NodeWrittenEvent` | Cleans up keys, versions, extra permissions |
| `FileVersionsListener` | `VersionRestoredEvent` | Updates document keys when a version is restored |
| `ShareListener` | `ShareDeletedEvent` | Deletes extra permissions when share is deleted |
| `UserListener` | `UserDeletedEvent` | Cleans up file versions on user deletion |
| `DocumentUnsavedListener` | `DocumentUnsavedEvent` | Sends unsaved-document notifications |
| `WidgetListener` | `HttpBeforeTemplateRenderedEvent` | Injects scripts into Dashboard widget |

**Key classes:**
- `lib/AppConfig.php` — single source of truth for all app configuration (Document Server URL, JWT secret/header/leeway, feature flags, watermark settings, format capabilities, customization options)
- `lib/DocumentService.php` — HTTP communication with the ONLYOFFICE Document Server
- `lib/Crypt.php` / `lib/KeyManager.php` — JWT token generation and document key management
- `lib/FileUtility.php` — file type detection and format capability checks
- `lib/FileVersions.php` — Nextcloud version history integration
- `lib/Cron/EditorsCheck.php` — background job to check active editors
- `lib/Command/DocumentServer.php` — CLI command (`occ onlyoffice:documentserver`)

## Testing

Three layers with distinct responsibilities — do not duplicate assertions across layers.

```bash
composer run test:unit         # PHPUnit unit tests
composer run test:integration  # Behat integration tests
npx playwright test            # Playwright E2E tests
```

**PHPUnit** (`tests/unit/lib/`) — Tests individual classes in isolation with real lightweight dependencies where appropriate (e.g. database). Classes covered include `AppConfig`, `Crypt`, `DocumentService`, `FileUtility`, `FileVersions`, `ExtraPermissions`, `TemplateManager`, etc.

**Behat** (`tests/integration/`) — Tests that system components interact correctly with each other and with external services, primarily ONLYOFFICE Document Server HTTP endpoints. Owns endpoint contracts (e.g. "conversion endpoint returns a valid converted file"). Config in `tests/integration/behat/config/behat.php`.

**Playwright** (`tests/e2e/`) — tests that the UI presents and responds correctly from the user's perspective.

E2E structure:
- `tests/e2e/*.spec.ts` / `tests/e2e/common/*.spec.ts` / `tests/e2e/admin/*.spec.ts` — test suites (open, convert, new, share-link, viewer, advanced-permissions, etc.)
- `tests/e2e/fixtures/` — Page Object Models (`FilesPage`, `EditorPage`, `AdminPage`)
- `tests/e2e/helpers/` — shared API utilities (`auth`, `ocs`, `shares`, `users`, `webdav`, `templates`, `talk`)

## Commit Message Convention

Use **Conventional Commits**: `type(scope): description`

**Types:** `feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `style`

**Scopes** map to feature areas of the app:

| Scope | What it covers |
|---|---|
| `editor` | Editor page — `EditorController`, `EditorApiController`, `editor.js` |
| `callback` | Document Server callbacks — `CallbackController` |
| `settings` | Admin settings — `SettingsController`, `settings.js`, `views/settings/` |
| `share` | Advanced sharing — `SharingApiController`, `share.js`, `ShareTab.vue` |
| `template` | Template management — `TemplateController`, `TemplateManager`, `template.js` |
| `viewer` | Nextcloud Viewer integration — `viewer.js`, `ViewerListener` |
| `preview` | File preview generation — `Preview.php` |
| `direct` | Direct editor — `DirectEditor`, `directeditor.js` |
| `desktop` | Desktop app integration — `desktop.js` |
| `listener` | Same-tab postMessage listener — `listener.js` |
| `main` | Files app integration — `main.js` (file actions, new-file menu) |
| `federation` | Federated sharing — `FederationController`, `RemoteInstance` |
| `config` | App configuration — `AppConfig` |
| `crypt` | Token/encryption — `Crypt.php` |
| `key` | Document key management — `KeyManager` |
| `email` | Email notifications — `EmailManager`, `Notifier` |
| `watermark` | Watermark logic |
| `deps` | Package/dependency changes |
| `build` | Webpack/Vite config |
| `eslint` | Linting config |

## Notes

**CSS bundles are not auto-loaded.** Vite extracts CSS into `css/onlyoffice-*.css`. Each must be explicitly registered in the PHP listener alongside its script via `Util::addStyle()`.

**`appName` is injected at build time.** The Nextcloud Vite config injects `const appName = "onlyoffice"` into every bundle. Never declare it locally — it causes a redeclaration build error.

**`window.parent.OC` in `editor.js` is intentional.** The editor runs in an iframe. Accessing `window.parent.OC` reaches the parent frame's Nextcloud context, not the current window.

**PHP namespace is `OCA\Onlyoffice\*`.** JavaScript global namespace is `OCA.Onlyoffice`.

**JWT leeway is configured.** `Application.php` sets the Firebase JWT library's leeway from `AppConfig` at boot to tolerate clock skew between Nextcloud and the Document Server.
