# STRIDE Threat Model

| Asset | Threat | Attack Vector | Impact | Likelihood | Mitigation | Detection | Residual Risk |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Credentials | Credential stuffing | Reused leaked passwords | Account takeover | High | rate limits, lockout, MFA, breach-password checks | login-failure metrics, security events | Medium |
| Sessions | Session hijacking | Cookie theft | Unauthorized access | Medium | Secure/HttpOnly/SameSite cookies, CSP, session revocation, device tracking | impossible travel, revoked session checks | Medium |
| Tokens | Refresh token reuse | Stolen refresh token replay | Persistent access | Medium | rotation, family revocation, hash storage | token reuse event | Low |
| Authorization code | Code interception | Missing PKCE or redirect abuse | Token theft | Medium | PKCE required for public clients, exact redirect validation | invalid PKCE events | Low |
| Tenant data | Tenant escape | Forged tenant id/header | Cross-tenant data leak | Medium | TenantContext, membership validation, query filters | tenant isolation tests, audit | Low |
| Admin operations | Privilege escalation | Overbroad roles | Platform compromise | Medium | permission policies, step-up auth, audit | permission-change events | Medium |
| Signing keys | Key compromise | Secret leakage | Token forgery | Low | external key storage, key rotation, `kid`, no private keys in source | key-use audit, integrity checks | Medium |
| Logs | Sensitive data exposure | token/secret logged | Credential theft | Medium | redaction, logging filters, structured policies | log scanning | Low |
| Supply chain | Malicious dependency | vulnerable package/action | code execution | Medium | pinned actions, vulnerability scans, SBOM | CI scans | Medium |
| Recovery | Account recovery abuse | password reset interception | Account takeover | Medium | generic responses, short token lifetime, MFA step-up | reset events | Medium |
