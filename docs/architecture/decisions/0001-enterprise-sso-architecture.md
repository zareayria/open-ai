# ADR 0001: Enterprise SSO Architecture

## Status

Accepted

## Context

The platform must implement enterprise SSO using standards rather than custom protocols. It must support browser, machine-to-machine, administrative, and background-worker clients while remaining multi-tenant and deployable across multiple stateless application instances.

## Decision

Use ASP.NET Core Identity for local accounts and password hashing, OpenIddict for OpenID Connect and OAuth endpoints, EF Core with SQL Server for durable state, Redis for distributed cache/session coordination/rate limiting, and ASP.NET Core Data Protection with shared key persistence. Browser applications use Authorization Code with PKCE. SPA traffic goes through a BFF so access and refresh tokens are kept server-side.

## Consequences

- Custom token formats and custom cryptographic protocols are prohibited.
- Presentation projects depend on Application contracts rather than DbContext.
- OpenIddict entities remain in the EF Core model for protocol compatibility.
- Tenant-aware entities carry `TenantId` and are protected by query filters plus explicit global-admin paths.
- Security-sensitive workflows write audit/security events through an outbox so login availability is not coupled to notification or audit sinks.
