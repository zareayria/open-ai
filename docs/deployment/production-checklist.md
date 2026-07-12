# Production Checklist

- [ ] Real signing/encryption keys are provisioned outside source control.
- [ ] Data Protection keys are shared across instances.
- [ ] Trusted proxy and forwarded headers are restricted.
- [ ] SQL Server and Redis are highly available.
- [ ] Redirect URI and CORS allow-lists are exact.
- [ ] Swagger is disabled or policy-protected.
- [ ] Rate limiting is enabled for login, MFA, token, introspection, and admin endpoints.
- [ ] Audit chain validation job is scheduled.
- [ ] Backups and disaster recovery are tested.
- [ ] Dependency, container, and secret scans pass without critical findings.
