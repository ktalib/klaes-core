# KLAES Survey Apps SSO — Expert Review Brief

**Prepared:** 2026-11-08

**Purpose:** Request an independent architecture and security recommendation before implementing SSO.

**Status:** Assessment only. No SSO package, authentication change, mobile-app change, or database change was made for this brief.

## Executive summary

KLAES has three native Android Flutter apps, but their business screens and current sign-in are Laravel pages rendered inside each app's embedded WebView. All three load the same Laravel Survey mobile route on `https://app.klaes.ng`, distinguished by an `app` query value. Laravel validates staff credentials and establishes a normal Laravel `web` session. The existing OTP step is conditional, not universal.

There is no confirmed organization-wide identity provider. Keycloak 26.8.2 has been downloaded and extracted at `C:\xampp\keycloak`, but no configured realm, client, KLAES integration, or production-ready deployment has been verified. KLAES is Laravel 9.52.21 / PHP 8.2 and already uses Sanctum bearer tokens for other APIs and mobile clients.

**Main recommendation for the expert to assess:** choose a maintained, standards-conformant OpenID Connect (OIDC) identity provider for native-app sign-in, or explicitly decide that a narrower OAuth 2.0 design meets the business need. Do not treat installing Passport, enabling a Keycloak realm, or sharing Laravel cookies as a complete SSO implementation. Keep the existing Survey permissions, local OTP policy, non-Survey login flows, Sanctum clients, and offline behavior intact unless separately approved.

## Scope decisions already stated by the user

- SSO is for the three Survey apps only: SCF, Field Observations, and Plot Confirmation.
- Eligible identities are existing staff accounts in KLAES's `users` table that already have the required Survey/app access.
- External Surveyor Portal accounts and general KLAES web SSO are out of scope.
- The user's preference was to retain the current KLAES OTP during rollout. This means preserving the *current conditional OTP behavior* unless the user approves a stronger universal policy.
- An 8-hour maximum session and 30-minute idle timeout were discussed as desired initial policy values. They are **not implemented**.
- The existing password login was described as a temporary rollout fallback, but no expiry date or enforcement mechanism has been selected.
- System-browser authentication and APK artifacts for the three Survey apps were discussed as the desired native-app direction. No APK changes or SSO builds have been made.
- The user is seeking expert advice before deciding how to proceed. Keycloak versus a KLAES-hosted OIDC provider remains open.

## Verified current sign-in flow

1. Each Flutter app loads the Laravel login page in an embedded WebView over HTTPS.
2. The app key (`scf`, `cfo`, or `plot`) selects app branding and landing page. It is app context, **not an identity credential or authorization grant**.
3. The Survey login POST accepts username or email plus password. Laravel looks up the staff `User`, checks the password hash, active status, verified email, and Survey module permission.
4. If `LoginOtpService::requiredFor()` returns true, Laravel holds a pending login and sends the existing login-code challenge. Otherwise, it completes password-only sign-in.
5. On completion, Laravel logs the user into the `web` guard, regenerates the session ID, marks the session as having passed the OTP policy, and returns to the Survey app's intended page.
6. Protected Survey mobile routes use Laravel's `auth` middleware. The `/survey-module/mobile` routes are not OAuth/OIDC endpoints.
7. Sign-out logs out the Laravel guard, invalidates the session, and returns to the matching app login.
8. A separate encrypted, HTTP-only `survey_mobile_app_context` cookie lasts one year to preserve app branding after session expiry. It only identifies the app context; it is not proof of identity.

Primary source anchors:

- [MobileCaseController.php](../../app/Http/Controllers/Survey/MobileCaseController.php#L32) — Survey login, checks, OTP dispatch, completion, logout, Survey permission check.
- [LoginOtpService.php](../../app/Services/LoginOtpService.php#L84) — conditions for requiring OTP, pending challenge, verification and pass marker.
- [AuthenticatedSessionController.php](../../app/Http/Controllers/Auth/AuthenticatedSessionController.php#L85) — web session establishment and ID regeneration.
- [RequireLoginOtp.php](../../app/Http/Middleware/RequireLoginOtp.php#L23) — global `web`-group OTP backstop.
- [survey_module.php](../../routes/survey_module.php#L545) — mobile login and authenticated route group.
- [auth.php](../../routes/auth.php#L20) — shared staff login-OTP routes.
- [Kernel.php](../../app/Http/Kernel.php#L33) — web middleware order, including 2FA, OTP, profile/phone gates and module-permission enforcement.

## Current technology and app identifiers

| App | Android application ID | Start URL |
|---|---|---|
| SCF Mobile | `com.survey.app` | `/survey-module/mobile/login` (no explicit `app` query; defaults to `scf`) |
| Field Observations | `com.survey.fieldobs` | `/survey-module/mobile/login?app=cfo` |
| Plot Confirmation | `com.survey.plotconfirm` | `/survey-module/mobile/login?app=plot` |

- Backend: Laravel Framework **9.52.21**, with Composer constraint `laravel/framework ^9.11`; `composer.json` also constrains PHP to `^8.0.2`. Production uses PHP 8.2 per the project environment notes.
- Staff identities: `App\Models\User` uses the `sqlsrv` connection. KLAES also has a MySQL connection; any identity mapping or OAuth tables need an explicit database/connection and migration plan.
- Mobile: Flutter with `webview_flutter` and `webview_flutter_android`. The three app start screens load Laravel HTML; they are not currently native OIDC clients.
- App identifiers are separate. Browser/WebView cookie and local-storage sharing between the three installed apps must not be assumed.
- The app projects live outside the Laravel repository under `C:\xampp\htdocs\klaes_app`. At the time checked, the SCF directory had a Git repository with no commit and many staged initial files; Field Observations and Plot Confirmation had no Git repository at their expected project roots. Establish a safe source baseline before editing/building.

Relevant app sources:

- [SCF WebView](../../../klaes_app/SCF/lib/screens/webview_screen.dart#L34)
- [Field Observations WebView](../../../klaes_app/Field_Observations_app/lib/screens/webview_screen.dart#L35)
- [Plot Confirmation WebView](../../../klaes_app/Plot_Confirmation_app/lib/screens/webview_screen.dart#L35)
- [KLAES User model](../../app/Models/User.php#L13)
- [Composer manifest](../../composer.json#L8)

## Authentication policy and session facts

### OTP is not currently universal

Survey password login calls the shared `LoginOtpService`. A code is required only when all policy checks pass, including the OTP feature/configuration and schema readiness, no hard-coded account exemption, a confirmed phone (`is_otp_verified`), an eligible SMS channel, and a usable normalized phone number. Otherwise, `completeLogin()` can establish a session after the password and account checks alone. The code supports SMS/email delivery and a pending-login challenge.

Therefore, “keep the current OTP” preserves a conditional policy; it does **not** mean every Survey user completes a second factor. The expert should confirm whether that is the intended pilot policy, and whether SSO must preserve it exactly or require a separately approved stronger policy. Do not silently interpret an OIDC claim as satisfying KLAES OTP.

### Current session settings need attention

The inspected production `.env` has `SESSION_DRIVER=database` and `SESSION_LIFETIME=2160000` minutes (1,500 days). This is an inactivity/session lifetime, not a hard maximum session age. `config/session.php` defaults `SESSION_AUTO_LOGOUT_ENABLED` to false and its timeout to 180 minutes; no auto-logout override was found in the `.env` file. The agreed 8-hour maximum and 30-minute idle timeout are not configured.

Before changing policy, the expert should verify actual production cookie attributes and the complete session/remember-me behavior. Do not print or share `.env` secrets in the review.

Sources: [production session config](../../config/session.php#L21), [OTP policy config](../../config/phone_verification.php#L187), [LoginOtpService.php](../../app/Services/LoginOtpService.php#L84).

## Important boundaries and risks for the expert to evaluate

### 1. KLAES must remain the authorization authority

An authenticated identity must not automatically gain Survey access. The current mobile login checks active status, verified email and `Survey - Records` or `Supper Admin` view access; protected routes also pass through the `web` middleware stack and module-permission checks. Preserve account disablement, roles, module/action permissions, record ownership and audit behavior after any SSO login.

### 2. Keycloak does not automatically know KLAES users

No shared staff directory was identified. A Keycloak realm requires an explicit identity/password lifecycle:

- Create and manage separate Keycloak accounts (including provisioning, deprovisioning and possible second passwords); or
- Federate from an existing, authoritative directory if one is identified; or
- Build and maintain a Keycloak user-storage integration against KLAES; or
- Make KLAES itself the identity provider using a maintained OIDC implementation.

These are materially different operational and security choices. Do not copy staff passwords into another database or automatically link accounts by email. Prefer a reviewed mapping keyed by issuer + immutable subject (`iss` + `sub`), tied to the existing KLAES user ID and an auditable linking process.

### 3. OAuth 2.0 is not the same protocol as OpenID Connect

Laravel Passport v11 is MIT-licensed and its declared requirements support PHP `^8.0` and Laravel `^9.0|^10.0`; Laravel 9 documentation describes Authorization Code with PKCE. Passport implements OAuth 2.0. It does not by itself provide the complete OIDC identity layer (including standard ID-token semantics and OIDC discovery/UserInfo behavior).

The expert should answer whether OAuth 2.0 plus a carefully designed authenticated user endpoint is adequate for these first-party apps, or whether the requirement calls for full OIDC. If full OIDC is required, review a maintained OIDC provider rather than writing protocol code from scratch.

### 4. Passport can affect existing Sanctum API clients if integrated naively

`User` currently uses `Laravel\Sanctum\HasApiTokens`. KLAES API routes use `auth:sanctum`; existing React Native mobile and SPAS clients call `User::createToken()` and depend on abilities such as `mobile-api` and `spas-mobile`. `config/sanctum.php` sets token expiration to `null`. Passport's conventional setup uses its own `HasApiTokens` trait in the same model, so replacing the Sanctum trait or changing a shared API guard may break unrelated API clients even though web password login is a separate path.

The React Native and SPAS token-login endpoints also authenticate with username/password and do not call `LoginOtpService`. They are outside the requested three-WebView-app SSO scope and must not be changed inadvertently. Ask the expert to propose an isolation approach and regression tests before adding Passport or changing `User`.

Sources: [User.php](../../app/Models/User.php#L13), [auth.php](../../config/auth.php#L38), [Sanctum configuration](../../config/sanctum.php#L20), [MobileAuthController.php](../../app/Http/Controllers/Api/Mobile/MobileAuthController.php#L16), [SpasAuthController.php](../../app/Http/Controllers/Api/Spas/SpasAuthController.php#L27), [API routes](../../routes/api.php#L448).

### 5. Native system-browser handoff is not implemented

Each Flutter app currently has unrestricted JavaScript in a WebView and its navigation delegate allows every URL. No native OIDC, system-browser authentication, app-link callback, or secure-storage dependency was found in the three app manifests. Their Android manifests only had the launcher activity filter when checked.

If the expert recommends system-browser OIDC, the Flutter apps will need a supported browser-authentication flow and carefully verified app-return mechanism (for example, claimed HTTPS App/Universal Links where feasible). The flow must validate state and PKCE, bind the result to the initiating app/session, and avoid sending bearer/refresh tokens or a reusable Laravel session cookie through a redirect URL. Add a strict navigation allowlist; do not let an external sign-in redirect silently turn the embedded WebView into a general browser.

Because each app has its own native application ID/WebView store, do not assume that one app's KLAES WebView cookie signs in another app. SSO should rely on the shared identity-provider/system-browser session and then establish only the intended app's local session through a reviewed handoff.

### 6. Shared middleware and login gates can cause callback loops

The `web` middleware group includes `Verify2FA`, `RequireLoginOtp`, profile-photo and phone-verification gates, and module-permission enforcement. An OIDC callback that simply calls `Auth::login()` may be challenged or redirected by those gates, or may fail to mark OTP as passed. The expert should specify how the callback integrates with the existing gates without bypassing them or sending the user into a loop.

### 7. Offline records survive the current sign-out cache clear

The Survey offline layer stores queued form fields and photos in IndexedDB (`scf-offline`, store `queue`). Sign-out deletes Cache Storage entries with the `scf-` prefix but does not delete that IndexedDB database. Thus, offline work can remain on the device after sign-out. This may be intentional to avoid losing unsynced work, but it is a privacy and account-switching decision that SSO does not solve.

The expert should define how queued records behave on sign-out, account switching, revocation, device loss and subsequent sync; whether local encryption or retention limits are needed; and how sync rechecks server-side identity and authorization. Do not blindly clear unsynced work as a security shortcut.

Sources: [offline database queue](../../public/js/survey-mobile-offline.js#L16), [sign-out handler](../../resources/views/survey_module/mobile/_menu.blade.php#L19).

### 8. Current 419/session handling is not an SSO implementation

The app context cookie and custom mobile 419 screen preserve app branding and route expired sessions back to the right app. They do not authenticate a user, provide a provider session, or revoke OAuth/OIDC tokens. Any SSO logout/expiry design must be separately specified and tested.

### 9. DB and production safety

`User` is on SQL Server, while MySQL is also used by the Laravel app. SQL Server has no test copy in the documented test environment and the feature-test connection is deliberately read-only. Passport migrations/keys/clients or an external-identity mapping must not be run against production by accident. The expert should specify exactly which database owns OAuth/client/token and external-identity data, and provide isolated migration, backup and rollback plans.

Do not install packages, run Passport setup, publish client secrets, run migrations, add staff identities, or change global login middleware in the live application before a reviewed implementation and explicit deployment approval.

## Keycloak and hosting status (observed earlier on this server)

- Keycloak 26.8.2 files were moved from the Apache document root to `C:\xampp\keycloak`; they are not part of the KLAES codebase.
- No realm/client configuration or KLAES integration has been made by this work.
- Windows Server 2022 was observed; Java 26 was on `PATH`. Confirm the selected Keycloak release's supported runtime before using it.
- The existing MySQL80 service was running; no PostgreSQL service was observed. Apache already held ports 80/443; ports 8080/8443 were free at the time checked.
- A production deployment on this host would need an operational design (service account/service wrapper, database/version support, HTTPS/reverse proxy, restricted admin access, backups, monitoring and recovery). Do not use Keycloak `start-dev` for production.
- Keycloak has no software license fee; hosting and operations still cost resources. This brief does not recommend a specific same-host deployment.

## Questions for the identity/security expert

Please answer these explicitly:

1. For three first-party Android apps with no existing central directory, should KLAES use Keycloak OIDC, a maintained OIDC-provider package hosted by KLAES, or OAuth 2.0 only? Why is the chosen option supportable and maintainable with Laravel 9/PHP 8.2?
2. If using Keycloak, how should it authenticate the existing KLAES staff population without unsafe password duplication? Which system is authoritative for account creation, password changes, deactivation and recovery?
3. If evaluating Passport, how can the Survey SSO flow be isolated without replacing/breaking Sanctum's `HasApiTokens`, existing token rows, guards, abilities, SPAS, React Native or other API consumers? What test matrix proves compatibility?
4. What exact native flow and libraries should be used (Authorization Code + PKCE `S256`, system browser, redirect/App Link configuration, backend handoff, token custody, logout)? Is full OIDC required, or is an OAuth API token flow appropriate?
5. How will account linking be verified and audited without trusting email as the sole key? Which KLAES checks must run on every SSO login and protected request?
6. Does the current conditional KLAES OTP satisfy the rollout requirement, or must every eligible staff member complete a second factor? How do OTP, existing 2FA, phone verification and profile-photo gates compose with the callback?
7. How will the 8-hour maximum and 30-minute idle policy be implemented without unexpectedly shortening other users' KLAES web sessions? What is the approved date/policy for ending the legacy Survey password-login fallback?
8. What is the safe behavior for offline IndexedDB records when users sign out, switch accounts, lose access or reconnect?
9. What deployment/database, key rotation, backup, monitoring, recovery, rollback and incident-response requirements apply to the chosen provider?
10. What non-production acceptance tests and independent security review are required before installing packages, migrating data, building APKs or authorizing production rollout?

## Evidence and limits

This is a source-based architecture handoff, not a penetration test or a claim that all KLAES login paths have been exhaustively audited. It reviewed the Survey mobile login/controller/routes, Laravel web OTP/session middleware, Sanctum/API usage, the three Flutter WebView entry points, app identifiers/dependencies, offline queue/sign-out behavior, and the existing SSO planning document. The existing Survey mobile feature test file has seven tests for app context/branding, auth redirect, logout, and mobile 419 responses; it does not exercise a complete password-plus-OTP sign-in or any OIDC flow. No live-user credentials were used, no authentication endpoints were probed, no production data was changed, and no package or mobile build was produced.

The Laravel worktree already contained unrelated and previously requested changes when inspected. Preserve those changes; do not reset or clean the working tree while setting up a review.

## Reference material

- [OAuth 2.0 (RFC 6749)](https://www.rfc-editor.org/rfc/rfc6749)
- [Proof Key for Code Exchange / PKCE (RFC 7636)](https://www.rfc-editor.org/rfc/rfc7636)
- [OAuth 2.0 for Native Apps (RFC 8252)](https://www.rfc-editor.org/rfc/rfc8252)
- [OpenID Connect Core 1.0](https://openid.net/specs/openid-connect-core-1_0.html)
- [Laravel 9 Passport documentation](https://laravel.com/docs/9.x/passport)
- [Passport v11 Composer requirements](https://github.com/laravel/passport/blob/11.x/composer.json)
- [Passport MIT license](https://github.com/laravel/passport/blob/11.x/LICENSE.md)
- [Earlier KLAES Survey-app SSO plan](../plans/SSO_WEB_AND_SURVEY_APPS_PLAN.md)
