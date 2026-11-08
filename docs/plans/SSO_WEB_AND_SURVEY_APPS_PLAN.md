# Single Sign-On Plan: Survey Field Apps

## Status

Provisional planning only. The identity-provider architecture is now awaiting independent expert review; Keycloak is a candidate, not a final selection. Do not provision production clients or begin implementation until the expert has reviewed the handoff in [SURVEY_APPS_SSO_EXPERT_REVIEW.md](../handoff/SURVEY_APPS_SSO_EXPERT_REVIEW.md). No SSO implementation, identity-provider configuration, credential changes, or database changes have been made.

## Goal

Let authorized KLAES staff sign in to the three Survey field apps through an approved identity provider:

- SCF Mobile
- Computation & Field Observations
- Plot Confirmation

The apps must retain their own navigation and KLAES permissions while using one trusted identity and consistent sign-in, MFA, session-expiry, and sign-out policies. General KLAES web SSO and external Surveyor Portal accounts are out of scope.

## Current state

- The Survey field apps are three Flutter WebView clients that share the `/survey-module/mobile` routes. Their app identity is selected by `?app=scf|cfo|plot`, remembered in the web session, and retained in a separate app-context cookie so session-expiry handling can return to the correct app.
- The field-app login verifies the local username and password, checks Survey access, and may hand off to the existing sign-in OTP flow.
- The identity scope is limited to active staff accounts in KLAES's `users` table that already have Survey/app access. External Surveyor Portal accounts are excluded.
- Existing local KLAES OTP remains required during the initial SSO rollout.
- KLAES has separate guards and sign-in flows for other portals, and Sanctum token authentication for selected APIs. Those are separate trust boundaries; they must not be silently merged into Survey field-app SSO.
- The field apps support offline work. Local queues, cached pages, photos, and records must be considered separately from the server-side SSO session.

## Candidate direction (not yet approved)

Use a maintained OpenID Connect provider for the three Survey apps. A self-hosted Keycloak instance is one candidate with no software license fee; hosting, maintenance, backups, TLS, and operations still have costs. Another candidate is a maintained OIDC-provider implementation hosted by KLAES. Laravel Passport supports OAuth 2.0 Authorization Code with PKCE, but does not by itself implement full OIDC. Ask the independent expert to compare these options against this app's requirements and existing Sanctum integration before selecting one.

If OIDC is approved, use the system browser and Authorization Code with PKCE for the three Flutter clients. Do not collect staff passwords in the apps, embed identity-provider sign-in in a WebView, assume WebView cookies are shared across apps, or place tokens in URLs. The expert must review the native app-return and KLAES session handoff before implementation.

KLAES continues to own account activity, module roles, permissions, Survey app access, and record-level authorization. Keycloak authentication must never grant Survey access by itself.

## Agreed scope and initial policy

- Accounts: only existing KLAES staff `users` with Survey/app permissions; no external Surveyor Portal accounts.
- Multi-factor authentication: retain the existing conditional KLAES OTP policy during rollout; it does not currently challenge every account.
- Initial session policy: configurable maximum session age of 8 hours and idle timeout of 30 minutes.
- Legacy login: keep the current password-plus-OTP flow temporarily during a controlled rollout; its expiry must be configured and enforced before SSO is enabled broadly.
- Mobile handoff: system browser with PKCE and a return to the originating app.
- Account linking: persist an immutable provider issuer + subject mapping. Do not automatically link accounts by matching email. Require a reviewed, auditable linking process.
- Configurable Entries may hold non-secret policy choices and durations. Keycloak issuer/client configuration and client secrets belong in protected deployment configuration, never the ordinary settings UI.

## Remaining prerequisites

- Obtain the independent expert's recommendation on the identity provider/protocol and account/password lifecycle.
- If Keycloak is selected, provision its production service and staff identity source; then register clients using only exact, implementation-defined redirect URIs.
- Confirm protected secret storage and the owners for provider operations, account linking, rollout, and rollback.
- Review the external Keycloak setup instructions only if Keycloak is selected: [KEYCLOAK_SURVEY_APPS_SETUP.md](../guides/KEYCLOAK_SURVEY_APPS_SETUP.md).

## Phased implementation

### Phase 0 — Discovery and threat modelling

- Inventory every staff-facing login, guard, OTP gate, mobile deep link, API, and route that depends on the current session.
- Confirm each Flutter app’s supported OS versions, WebView/system-browser capabilities, redirect URI controls, secure-storage support, and offline queue behavior.
- Document existing account identifiers, inactive-user behavior, module roles, audit events, session invalidation, and service-account exceptions.
- Agree on the provider, MFA assurance requirements, claims, redirect URI allowlist, token/session policy, and support flow before code changes.
- Produce a test matrix for login, MFA, authorization, expiry, logout, offline/online transitions, revocation, and failure recovery.

### Phase 1 — Identity mapping and Survey-app pilot

- Add a durable external-identity mapping keyed by issuer and subject, with a reviewed one-time account-linking process and audit trail.
- Add the Survey-app OIDC callback using a maintained OIDC client; validate protocol parameters and exchange codes using the agreed PKCE/backend handoff.
- Preserve existing KLAES module/record permission checks and inactive-account controls after identity authentication.
- Preserve the existing KLAES OTP policy during the pilot.
- Pilot with a small staff cohort and verify login, callback error handling, session fixation protection, idle expiry, forced revocation, app return, and local sign-out.

### Phase 2 — Flutter app authentication

- Register separate OIDC clients/redirect URIs for SCF, Field Observations, and Plot Confirmation; apply PKCE and exact redirect allowlists.
- Replace password collection in each app with system-browser authorization; return only an authorization result to the owning app.
- Establish app-specific, least-privilege backend credentials with short access-token lifetimes, rotation, audience/scope checks, and server-side revocation.
- Retain KLAES app selection and separate feature authorization; an authenticated user must still be denied if they lack the required module/app access.
- Define offline boundaries: queue only the minimum needed data, encrypt local data, handle revoked accounts at next connection, and reliably clear protected caches on sign-out.

### Phase 3 — Rollout and migration

- Run a limited opt-in pilot; keep the existing sign-in available only as an explicitly time-bounded fallback.
- Migrate users through verified identity links; do not auto-link solely on matching email.
- Roll out web first, then each Survey app independently with versioned release notes and help-desk guidance.
- Monitor successful/failed authentication, MFA completion, account-linking exceptions, authorization denials, token refresh/revocation, and logout outcomes without logging credentials or tokens.
- Remove legacy password/mobile login only after the acceptance criteria are met and rollback is no longer required.

## Security and operational requirements

- Use TLS for all callbacks and tokens; store client secrets only server-side in approved secret storage.
- Validate OIDC issuer, audience, signature/algorithms, state, nonce, PKCE verifier, expiry, and exact redirect URI.
- Never put access or refresh tokens in URLs, logs, analytics, crash reports, or WebView local storage.
- Keep identity-provider authentication separate from KLAES authorization; re-check active status and permissions on protected requests.
- Use short-lived access credentials, refresh-token rotation/reuse detection where supported, and a tested server-side revocation path.
- Make logout idempotent and clear local KLAES sessions even when provider logout is unavailable.
- Avoid token/credential exposure in offline queues; provide a documented remote-revocation and device-loss response.
- Retain an auditable record of account linking, sign-in outcome, MFA outcome, revocation, and administrative override, excluding secrets.

## Acceptance criteria

- An authorized staff member can authenticate to each authorized Survey app through Keycloak and return to the originating app during the approved session window.
- Unauthorized or inactive accounts, wrong audience/client, invalid callback state/nonce, expired/replayed authorization results, and revoked credentials are denied.
- Module and action permissions remain enforced exactly as before SSO.
- MFA policy is consistent with the approved assurance decision and recovery is tested.
- Sign-out clears local KLAES state; provider logout and credential revocation behave as documented, including when the provider is unreachable.
- Expired/revoked mobile authentication returns to the correct app-specific sign-in experience without exposing a web login/error page.
- Offline queues behave safely through expiry, sign-out, account disablement, and reconnection.
- Audit and monitoring contain no passwords, authorization codes, access tokens, or refresh tokens.
- Rollback to the legacy flow is documented, time-limited, controlled, and tested before production rollout.

## Not included in this plan

- Any SSO, OIDC, OAuth, identity-provider, database, or credential implementation.
- Changes to the three mobile apps’ business forms or wording; those require a separate approved screen-by-screen scope.
- General KLAES web SSO, external Surveyor Portal accounts, and unrelated API clients.
