# token revocation Runbook

## Trigger

Operational or security event requiring token revocation.

## Steps

1. Identify tenant, user, client, key, sessions, tokens, and correlation identifiers.
2. Preserve audit evidence and relevant logs with sensitive values redacted.
3. Apply the least disruptive containment action first.
4. Verify revocation, rotation, or recovery using health checks and protocol endpoints.
5. Record final state, residual risk, and follow-up actions.
