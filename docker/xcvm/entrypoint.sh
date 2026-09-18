#!/bin/bash
set -e

DIST="/opt/xc_vm_dist"
HOME_DIR="/home/xc_vm"
DB_HOST="${XCVM_DB_HOST:-mysql}"
DB_NAME="${XCVM_DB_NAME:-xcvm}"
DB_USER="${XCVM_DB_USER:-xcvm}"
DB_PASS="${XCVM_DB_PASS:-xcvmsecret}"
HTTP_PORT="${XCVM_HTTP_PORT:-25462}"

# ── First-boot: copy staged install to the volume ─────────────────────────────
if [ ! -f "${HOME_DIR}/service" ]; then
    echo "[xcvm] First boot — copying XC-VM files to volume..."
    cp -a "${DIST}/." "${HOME_DIR}/"
    echo "[xcvm] Copy complete."
fi

# ── Wait for MySQL ─────────────────────────────────────────────────────────────
echo "[xcvm] Waiting for MySQL at ${DB_HOST}:3306..."
for i in $(seq 1 30); do
    if mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" \
        -e "SELECT 1" "${DB_NAME}" >/dev/null 2>&1; then
        echo "[xcvm] MySQL ready."
        break
    fi
    echo "[xcvm] Waiting... ($i/30)"
    sleep 3
done

# ── Import DB schema on first boot (tables won't exist yet) ───────────────────
TABLE_COUNT=$(mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" \
    -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';" \
    -s -N 2>/dev/null || echo "0")

if [ "${TABLE_COUNT}" = "0" ] || [ "${TABLE_COUNT}" -lt "5" ]; then
    echo "[xcvm] Importing database schema..."
    mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
        < "${HOME_DIR}/bin/install/database.sql" && echo "[xcvm] Schema imported." \
        || echo "[xcvm] WARN: schema import had errors (may already exist)"

    # Insert admin access code
    ADMIN_CODE=$(grep "Admin Access Code" /root/credentials.txt 2>/dev/null | awk '{print $NF}')
    if [ -n "$ADMIN_CODE" ]; then
        mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
            -e "INSERT IGNORE INTO access_codes(code,type,enabled,groups) VALUES('${ADMIN_CODE}',0,1,'[1]');" \
            2>/dev/null && echo "[xcvm] Admin code inserted: ${ADMIN_CODE}"
    fi
fi

# ── Patch nginx listen port ───────────────────────────────────────────────────
PORT_FILE="${HOME_DIR}/bin/nginx/conf/ports/http.conf"
if [ -f "$PORT_FILE" ]; then
    echo "listen ${HTTP_PORT};" > "$PORT_FILE"
    echo "[xcvm] nginx port → ${HTTP_PORT}"
fi

# ── Fix ownership ─────────────────────────────────────────────────────────────
chown -R xc_vm:xc_vm "${HOME_DIR}" 2>/dev/null || true

# ── Print access info ─────────────────────────────────────────────────────────
ADMIN_CODE=$(grep "Admin Access Code" /root/credentials.txt 2>/dev/null | awk '{print $NF}')
echo "[xcvm] ================================================"
echo "[xcvm] Admin panel: http://localhost:${HTTP_PORT}/${ADMIN_CODE}/admin"
echo "[xcvm] Access code: ${ADMIN_CODE}"
echo "[xcvm] ================================================"

# ── Start XC-VM ───────────────────────────────────────────────────────────────
echo "[xcvm] Starting XC-VM..."
exec "${HOME_DIR}/service" start
