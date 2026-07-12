# OpenID Connect and OAuth API

Standard endpoints:

- `/.well-known/openid-configuration`
- `/.well-known/jwks`
- `/connect/authorize`
- `/connect/token`
- `/connect/userinfo`
- `/connect/introspect`
- `/connect/revocation`
- `/connect/logout`
- `/connect/device` when device flow is explicitly enabled

Allowed flows are Authorization Code with PKCE, Client Credentials, Refresh Token, and optional Device Authorization Flow. Implicit and Resource Owner Password Credentials are disabled.
