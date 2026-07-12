# Authentication Flows

## Browser Web Application

Confidential clients use Authorization Code Flow. Public clients require PKCE. Cookies are `HttpOnly`, `Secure`, scoped by path/domain, and regenerated after sign-in.

## SPA

SPA clients use the BFF. Tokens are never stored in LocalStorage or SessionStorage. The browser receives only a secure session cookie plus anti-forgery tokens for state-changing requests.

## Machine-to-Machine

Machine clients use Client Credentials with narrow scopes, secret rotation metadata, and future support for private-key JWT or mTLS.

## Refresh Tokens

Refresh tokens are rotated. Reuse marks the token family compromised and revokes descendants.
