#!/usr/bin/env bash
# Generate a self-signed TLS certificate for the GLPI Audit Dashboard so the
# camera "Scan" page works — browsers only allow camera access (getUserMedia)
# over HTTPS or on localhost.
#
# Usage:  sudo deploy/make-selfsigned-cert.sh <ip-or-hostname> [more names...]
#   e.g.  sudo deploy/make-selfsigned-cert.sh 10.0.0.184
#         sudo deploy/make-selfsigned-cert.sh 10.0.0.184 dashboard.lan
#
# IPs go into the cert as IP SANs, hostnames as DNS SANs, so the browser
# accepts the address you actually type. Valid for 825 days, written to
# /etc/ssl/glpi-dashboard/.
set -euo pipefail

if [ "$#" -lt 1 ]; then
    echo "Usage: $0 <ip-or-hostname> [additional-names...]" >&2
    echo "  e.g. $0 10.0.0.184" >&2
    exit 1
fi

DIR=/etc/ssl/glpi-dashboard
mkdir -p "$DIR"

# Build subjectAltName: IPv4 addresses as IP:, everything else as DNS:.
SAN=""
CN="$1"
for name in "$@"; do
    if [[ "$name" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        SAN+="IP:$name,"
    else
        SAN+="DNS:$name,"
    fi
done
SAN="${SAN%,}"

openssl req -x509 -nodes -newkey rsa:2048 -days 825 \
    -keyout "$DIR/dashboard.key" \
    -out    "$DIR/dashboard.crt" \
    -subj   "/CN=$CN" \
    -addext "subjectAltName=$SAN"

chmod 600 "$DIR/dashboard.key"
chmod 644 "$DIR/dashboard.crt"

echo
echo "Certificate written to $DIR"
echo "   SAN: $SAN"
echo "   nginx: ssl_certificate $DIR/dashboard.crt; ssl_certificate_key $DIR/dashboard.key;"
echo "   Copy dashboard.crt to your phone to trust it (see SETUP.md), or just"
echo "   tap through the browser warning once — the camera still works."
