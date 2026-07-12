# Architecture Overview

## Current State

The repository had no existing .NET code. The Enterprise SSO structure is added beside the existing plugin without rewriting unrelated assets.

## Final Structure

```text
src/ Enterprise.Sso.Domain, Application, Infrastructure, Contracts, SharedKernel, IdentityServer, Admin.Api, Admin.Web, Bff, Worker
samples/ MVC client, SPA client, protected API, worker client
tests/ unit, integration, architecture, security, end-to-end
deploy/ docker, kubernetes, helm, nginx, scripts
docs/ architecture, security, deployment, api, runbooks
```

## Phase Plan

1. Architecture and threat model.
2. Base infrastructure: solution, configuration, logging, ProblemDetails, EF Core, Identity, migrations, seed.
3. Identity Server: OpenIddict discovery, authorization, token, userinfo, introspection, revocation, logout, JWKS.
4. Account management: login, registration, verification, password reset, MFA, sessions.
5. Authorization and multi-tenancy.
6. Admin portal.
7. Sample clients.
8. Security hardening.
9. Deployment automation.
10. Final verification.
