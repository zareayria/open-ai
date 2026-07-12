# Data Model

Core aggregates include `ApplicationUser`, `ApplicationRole`, `Permission`, `Tenant`, `UserTenant`, `Group`, `ClientApplication`, protocol entities from OpenIddict, `UserSession`, `LoginAttempt`, `SecurityEvent`, `AuditEvent`, `UserConsent`, `MfaMethod`, `RecoveryCode`, `PasswordHistory`, `BlockedIp`, `TrustedDevice`, `SigningKeyMetadata`, `OutboxMessage`, `DistributedLock`, and `ConfigurationSetting`.

Sensitive values are stored as hashes or encrypted values. Query indexes prioritize normalized user identifiers, tenant boundaries, client identifiers, session identifiers, token identifiers, status, expiration, creation time, login IP hash, and audit event type.
