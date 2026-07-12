#!/usr/bin/env bash
set -euo pipefail
mkdir -p .certs
openssl req -x509 -newkey rsa:3072 -sha256 -days 30 -nodes -keyout .certs/dev-signing.key -out .certs/dev-signing.crt -subj "/CN=Enterprise SSO Development Signing"
echo "Development certificate generated under .certs. Do not commit generated private keys."
