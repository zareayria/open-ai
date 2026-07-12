# Enterprise SSO

Enterprise SSO is a production-oriented architecture and implementation scaffold for a multi-tenant OpenID Connect and OAuth 2.1 identity platform based on ASP.NET Core Identity, OpenIddict, EF Core, SQL Server, Redis, Serilog, OpenTelemetry, Docker, Kubernetes, and automated security testing.

## Current Repository Status

This repository originally contained a small WordPress invoice plugin and no .NET solution. The Enterprise SSO work is isolated under `src/`, `samples/`, `tests/`, `deploy/`, and `docs/` so the existing plugin is not modified.

## Architecture

The solution uses Clean Architecture with practical DDD boundaries:

- `Enterprise.Sso.Domain`: identity, tenant, authorization, session, audit, and key-management domain models.
- `Enterprise.Sso.Application`: use cases, validation, authorization policies, notification abstractions, and token/session orchestration contracts.
- `Enterprise.Sso.Infrastructure`: EF Core, ASP.NET Core Identity, OpenIddict stores, Redis, Data Protection, Serilog, OpenTelemetry, outbox, distributed locks, and provider implementations.
- `Enterprise.Sso.IdentityServer`: OIDC/OAuth host and account UI.
- `Enterprise.Sso.Admin.Api` and `Enterprise.Sso.Admin.Web`: administrative APIs and UI protected by the same SSO.
- `Enterprise.Sso.Bff`: SPA backend-for-frontend keeping browser tokens server-side.
- `Enterprise.Sso.Worker`: cleanup, notifications, key rotation, audit integrity, and retention jobs.

## Prerequisites

- .NET SDK LTS available in the deployment environment.
- Docker and Docker Compose.
- SQL Server container/runtime.
- Redis.
- Node.js for Playwright only when running end-to-end tests.
- k6 for load tests.

## Development

```bash
dotnet restore Enterprise.Sso.sln
dotnet build Enterprise.Sso.sln --no-restore
dotnet test Enterprise.Sso.sln --no-build
```

## Docker

```bash
cp .env.example .env
deploy/scripts/generate-dev-cert.sh
docker compose -f deploy/docker/docker-compose.yml up -d --build
```

## Migrations and Seed

Migrations are owned by `Enterprise.Sso.Infrastructure`. Seed data is idempotent and creates development tenants, scopes, permissions, and sample clients from environment-provided secrets only. No real secret is committed.

## Development Account

Development accounts are created only when explicit environment variables are present. Use local secret storage or `.env`; do not commit credentials.

## Service URLs

- Identity Server: `https://localhost:7001`
- Admin API: `https://localhost:7002`
- Admin Web: `https://localhost:7003`
- BFF: `https://localhost:7004`
- Sample API: `https://localhost:7010`
- Seq: `http://localhost:5341`
- MailHog: `http://localhost:8025`

## Production Notes

Production deployments must provide signing/encryption certificates, Data Protection key persistence, trusted proxy configuration, strict redirect URI allow-lists, Redis-backed rate limiting, SQL Server high availability, and centralized logging with secret redaction.

## Troubleshooting

- Discovery endpoint unavailable: verify Identity Server readiness and signing key configuration.
- Token endpoint failures: verify client status, PKCE, grant type, scopes, and secret rotation state.
- Login loops: verify cookie domain, forwarded headers, HTTPS, Data Protection keys, and clock synchronization.
