# Key Management

Signing uses RSA or ECDSA certificates loaded from environment configuration, secret manager, mounted secret, Azure Key Vault, HashiCorp Vault, or HSM-backed providers. Development certificates are generated locally and excluded from source control. Key metadata tracks `kid`, purpose, activation time, retirement time, thumbprint, and state. Previous keys remain published in JWKS until all issued tokens can no longer validate.
