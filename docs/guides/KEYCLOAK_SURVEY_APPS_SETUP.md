# Keycloak Setup for KLAES Survey Apps

## Purpose and status

Use this guide to prepare a self-hosted Keycloak realm for staff SSO in the three Survey field apps. This is setup guidance only: do not enable SSO in KLAES until the implementation, callbacks, and client registrations have been reviewed.

Keycloak is open-source and has no software license fee. Hosting, TLS certificates, backups, upgrades, monitoring, and administration are still operational costs.

The SSO scope is limited to active staff accounts in KLAES's `users` table that already have Survey/app access. External Surveyor Portal accounts and general KLAES web login are not included. KLAES's existing OTP remains enabled for the initial rollout.

## Before starting

1. Assign an administrator to provision and operate Keycloak. Use a supported Keycloak release and keep it patched.
2. Provide a stable HTTPS hostname, TLS, backups, monitoring, and a recovery procedure. Do not expose the administration console publicly without appropriate network and administrator protections.
3. Decide how staff identities will be created in Keycloak or securely federated from an existing directory. Do not assume that email addresses are unique or durable.
4. Wait for the KLAES implementation to define the exact OIDC callback and mobile redirect URIs. Redirect URI matching must be exact; never use `*` wildcards in production.
5. Keep Keycloak client secrets in protected server-side deployment configuration. Do not put secrets in Configurable Entries, Flutter source, mobile builds, URLs, source control, or chat.

## Create the realm

1. Sign in to the Keycloak Admin Console over HTTPS.
2. Create a dedicated realm, for example `klaes-survey`. Do not use the built-in `master` realm for application users.
3. Set the realm's display name to a clear organizational label such as `KLAES Survey Apps`.
4. Configure the realm's HTTPS hostname and issuer consistently. The final issuer URL will have the form:

   `https://<keycloak-host>/realms/klaes-survey`

5. Keep public self-registration and open account recovery disabled unless the organization separately approves and secures those flows.
6. Configure password policy and administrator recovery. Enroll staff MFA only under the approved Keycloak policy; the initial KLAES rollout still requires the existing KLAES OTP.
7. Create a small test cohort of staff accounts. Do not bulk import or auto-link users until the identity-linking method has been reviewed.

## Register OIDC clients

The final number and settings of clients must match the implemented flow. Plan for separate clients per app so each has an independent redirect allowlist and can be disabled or rotated without affecting the others.

| App | Suggested client ID | Android application ID |
|---|---|---|
| SCF Mobile | `klaes-survey-scf` | `com.survey.app` |
| Field Observations | `klaes-survey-field-observations` | `com.survey.fieldobs` |
| Plot Confirmation | `klaes-survey-plot-confirmation` | `com.survey.plotconfirm` |

For each native Flutter app client:

1. Create an OpenID Connect client with the suggested unique client ID.
2. Use the Authorization Code flow (Standard Flow) and require PKCE with `S256`.
3. Configure it as a public/native client with no client secret. Native apps cannot protect a shared secret.
4. Disable Direct Access Grants (password grant) and Implicit Flow.
5. Register only the exact redirect URI supplied by the app implementation. Use a verified HTTPS App Link/Universal Link where supported, or a uniquely controlled application redirect. The application IDs above are identifiers, not final redirect URIs.
6. Set post-logout redirects only to the exact app-owned destinations approved by the implementation.
7. Do not add broad web origins or wildcard redirect patterns. A native redirect is not a substitute for server-side validation.

If the approved architecture also requires a confidential KLAES backend client, create it separately:

1. Enable client authentication and Authorization Code flow only.
2. Disable Direct Access Grants and Implicit Flow.
3. Register the exact HTTPS callback route supplied by the implemented KLAES backend.
4. Store its generated secret only in the server's protected environment/secret store, then rotate it under the operations policy.
5. Never reuse this secret in any Flutter app or public client.

Do not create a backend client or invent callback URLs before the KLAES implementation defines and tests them. Client IDs and redirect URIs must match the actual code exactly.

## Claims and account linking

- Provide the standard OIDC `openid` scope and only the profile claims KLAES needs.
- Do not use email alone as the account key. KLAES must bind identities using the validated issuer and immutable subject (`iss` + `sub`) through an auditable linking process.
- Do not add a Keycloak group or role mapping that grants Survey permissions. KLAES remains authoritative for active status, Survey-module access, and record permissions.
- Do not trust an MFA claim to skip KLAES OTP until the claim semantics and Keycloak assurance policy are independently reviewed and approved.

## Values to hand to the KLAES implementer

Provide these through the approved secure operations channel; never send client secrets in this document:

- Keycloak HTTPS base URL and exact realm issuer URL.
- Keycloak release/version and the operations owner.
- Client IDs for each app and, if required, the confidential backend client.
- Exact redirect URI registered for each client.
- Confirmation that PKCE `S256` is required for native clients and Direct Access Grants are disabled.
- Protected secret-store location for any confidential backend client.
- Test staff account identifiers, provisioned separately and without sharing passwords.
- Keycloak backup, recovery, administrator MFA, monitoring, and change-management owners.

## Verify before enabling SSO

- Confirm the realm's OpenID discovery document is reachable over HTTPS at:
  `https://<keycloak-host>/realms/klaes-survey/.well-known/openid-configuration`
- Confirm the discovery document's `issuer` exactly matches the configured issuer URL.
- Verify signing keys, token issuer/audience, expiry, state, nonce, PKCE verifier, and exact redirect handling in a non-production environment.
- Test inactive users, users without Survey permissions, unknown identities, callback replay, failed/cancelled login, session expiry, logout, and provider outage.
- Confirm the 8-hour maximum session, 30-minute idle timeout, existing KLAES OTP, and temporary legacy-login expiry are enforced by KLAES policy.
- Build and test each Flutter app against the non-production realm before any production rollout.

## Official documentation

- [Keycloak Server Administration Guide](https://www.keycloak.org/docs/latest/server_admin/)
- [Keycloak Securing Applications and Services Guide](https://www.keycloak.org/docs/latest/securing_apps/)
