FROM debian:12-slim

ARG DEBIAN_FRONTEND=noninteractive

# ── System deps ────────────────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl wget python3 unzip ca-certificates \
    default-mysql-client \
    libcap2-bin \
    procps net-tools sudo \
    && rm -rf /var/lib/apt/lists/*

# ── Create xc_vm user (installer expects it) ──────────────────────────────────
RUN useradd -m -d /home/xc_vm -s /bin/bash xc_vm

# ── Download latest XC-VM release zip and extract ─────────────────────────────
RUN set -e; \
    LATEST=$(curl -sf https://api.github.com/repos/Vateron-Media/XC_VM/releases/latest \
        | python3 -c "import sys,json; print(json.load(sys.stdin)['tag_name'])"); \
    wget -q "https://github.com/Vateron-Media/XC_VM/releases/download/${LATEST}/XC_VM.zip" \
        -O /tmp/XC_VM.zip; \
    unzip -q /tmp/XC_VM.zip -d /tmp/xcvm_install; \
    rm /tmp/XC_VM.zip; \
    ls /tmp/xcvm_install/

# ── Run the non-interactive installer (installs to /home/xc_vm) ─────────────────
COPY install_patch.py /tmp/install_patch.py
RUN cd /tmp/xcvm_install && python3 /tmp/install_patch.py

# Move installed files to a staging dir so the volume mount doesn't hide them
RUN mv /home/xc_vm /opt/xc_vm_dist

# ── Entrypoint: patch config.ini at runtime, then start XC-VM ─────────────────
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 25462

ENTRYPOINT ["/entrypoint.sh"]
