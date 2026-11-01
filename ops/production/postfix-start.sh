#!/bin/sh
set -eu
case "${MAIL_DOMAIN:-}" in ""|*[!a-zA-Z0-9.-]*) exit 2;; esac
case "${TRUSTED_APP_IP:-}" in ""|*[!0-9.]*) exit 2;; esac
case "${TRUSTED_WORKER_IP:-}" in ""|*[!0-9.]*) exit 2;; esac
postconf -e "myhostname=${MAIL_HOSTNAME}" "mydomain=${MAIL_DOMAIN}" "myorigin=${MAIL_DOMAIN}" \
  "inet_interfaces=all" "inet_protocols=ipv4" \
  "mydestination=localhost" "relay_domains=" \
  "mynetworks=127.0.0.0/8 ${TRUSTED_APP_IP}/32 ${TRUSTED_WORKER_IP}/32" \
  "smtpd_relay_restrictions=permit_mynetworks,reject_unauth_destination" \
  "smtpd_helo_required=yes" "disable_vrfy_command=yes" \
  "smtp_tls_security_level=encrypt" "smtpd_tls_security_level=none" \
  "maillog_file=/dev/stdout" "message_size_limit=10485760"
exec postfix start-fg
