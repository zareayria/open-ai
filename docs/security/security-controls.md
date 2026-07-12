# Security Controls

- Enforce HTTPS and HSTS in production.
- Use exact redirect URI allow-lists; wildcard redirect URIs are rejected in production.
- Require PKCE for public clients.
- Disable implicit and resource-owner-password flows.
- Store password hashes only through ASP.NET Core Identity `PasswordHasher`.
- Store client secrets, token handles, OTPs, and recovery codes as hashes or encrypted values as appropriate.
- Persist Data Protection keys to shared storage for multi-instance deployments.
- Add CSP, frame-ancestors, X-Content-Type-Options, Referrer-Policy, and Permissions-Policy headers.
- Apply Redis-backed rate limits by IP, username hash, client id, and sensitive endpoint.
- Emit tamper-evident audit records with previous/current hash chaining.
