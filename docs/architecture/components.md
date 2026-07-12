# Components

| Component | Responsibility |
| --- | --- |
| Domain | Entities, value objects, domain services, security invariants. |
| Application | Commands, queries, validators, authorization abstractions, notification contracts. |
| Infrastructure | EF Core, Identity stores, OpenIddict integration, Redis, outbox, provider implementations. |
| IdentityServer | OIDC/OAuth host, account pages, consent, logout, session integration. |
| Admin API/Web | Tenant, user, role, permission, client, key, session, audit administration. |
| BFF | Server-side token storage, CSRF-protected SPA proxy, logout coordination. |
| Worker | Cleanup, outbox publishing, key rotation, audit integrity, retention jobs. |
