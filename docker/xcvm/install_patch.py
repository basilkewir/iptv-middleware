#!/usr/bin/env python3
"""
Non-interactive driver for the XC-VM installer.

The install logic lives in `if __name__ == "__main__":`. We:
  1. exec() the whole script with __name__ = "_loading_" to define all functions
  2. Apply all monkey-patches
  3. Re-exec only the __main__ block with __name__ = "__main__"
"""

import os, sys, builtins, re, types

# ── Env-var config ─────────────────────────────────────────────────────────────
DB_HOST    = os.environ.get("XCVM_DB_HOST",   "mysql")
DB_NAME    = os.environ.get("XCVM_DB_NAME",   "xcvm")
DB_USER    = os.environ.get("XCVM_DB_USER",   "xcvm")
DB_PASS    = os.environ.get("XCVM_DB_PASS",   "xcvmsecret")
HTTP_PORT  = os.environ.get("XCVM_HTTP_PORT", "25462")
ADMIN_CODE = os.environ.get("XCVM_ADMIN_CODE","")

# ── Locate install script ──────────────────────────────────────────────────────
install_path = os.path.join(os.getcwd(), "install")
if not os.path.exists(install_path):
    print(f"[xcvm-patch] ERROR: 'install' not found in {os.getcwd()}")
    sys.exit(1)

with open(install_path, "r", encoding="utf-8") as fh:
    source = fh.read()

# ── Split source into pre-main and main block ──────────────────────────────────
main_marker = '\nif __name__ == "__main__":'
split_idx = source.find(main_marker)
if split_idx == -1:
    print("[xcvm-patch] ERROR: __main__ block not found")
    sys.exit(1)

pre_source  = source[:split_idx]
# Strip the `if __name__ == "__main__":` line and dedent the body
main_body   = source[split_idx + len(main_marker):]
# Dedent by 4 spaces (the block is indented one level)
main_source = re.sub(r"^    ", "", main_body, flags=re.MULTILINE)

print(f"[xcvm-patch] Loaded installer ({len(source)} bytes), main block at line "
      f"{source[:split_idx].count(chr(10))+1}")

# ── Patch input() ─────────────────────────────────────────────────────────────
_answers = {
    "continue anyway":        "Y",
    "continue and overwrite": "Y",
    "continue with provided": "Y",
    "overwrite sysctl":       "N",
    "http port":              HTTP_PORT,
    "https port":             "443",
    "mariadb root password":  "DockerRoot1234",
    "root password":          "DockerRoot1234",
}

def _auto_input(prompt=""):
    p = str(prompt).lower()
    for k, v in _answers.items():
        if k in p:
            print(f"  [input] {str(prompt)[:70]!r} → {v!r}")
            return v
    print(f"  [input] {str(prompt)[:70]!r} → 'Y'")
    return "Y"

builtins.input = _auto_input

# ── Execute pre-main (defines all functions) with __name__ != "__main__" ───────
ns = {"__name__": "_loading_", "__file__": install_path}
exec(compile(pre_source, install_path, "exec"), ns)
print("[xcvm-patch] Functions loaded.")

# ── Apply monkey-patches to the namespace ─────────────────────────────────────
_orig_run = ns["run_command"]

SKIP_RE = re.compile(
    r"systemctl|sysctl\s+-p|mount\s+-a|modprobe|^service\s+xc_vm"
    r"|apt.*(install|get).*mariadb-server|apt.*(install|get).*mysql-server",
    re.IGNORECASE
)

def _patched_run(cmd, shell=True, capture_output=False):
    cmd_str = cmd if isinstance(cmd, str) else " ".join(cmd)
    if SKIP_RE.search(cmd_str):
        print(f"  [skip]  {cmd_str[:100]}")
        return (0, "", "") if capture_output else (0, "", "")
    if re.search(r"\b(mariadb|mysql)\b", cmd_str):
        cmd_str = re.sub(r"\bmariadb\b", "mysql", cmd_str)
        cmd_str = re.sub(r"-h\s+(?:127\.0\.0\.1|localhost)\b", f"-h {DB_HOST}", cmd_str)
        if f"-h {DB_HOST}" not in cmd_str:
            cmd_str = re.sub(r"\b(mysql)\b", rf"\1 -h {DB_HOST}", cmd_str, count=1)
        print(f"  [db]    {cmd_str[:120]}")
        return _orig_run(cmd_str, shell=shell, capture_output=capture_output)
    return _orig_run(cmd, shell=shell, capture_output=capture_output)

ns["run_command"]                  = _patched_run
ns["install_mariadb_repo"]         = lambda dist_info: None
ns["secure_mariadb_installation"]  = lambda root_password, dist_info=None: None

# Strip mariadb-server from deb installs
_orig_deb = ns["install_deb_packages"]
def _patched_deb(dist_info):
    _saved = ns["run_command"]
    def _filtered(cmd, **kw):
        c = cmd if isinstance(cmd, str) else " ".join(cmd)
        c = re.sub(r"\bmariadb-server\S*", "", c)
        return _saved(c, **kw)
    ns["run_command"] = _filtered
    try:
        _orig_deb(dist_info)
    finally:
        ns["run_command"] = _patched_run
ns["install_deb_packages"] = _patched_deb

# Patch config template and DB host in SQL grants
ns["rConfig"] = ns["rConfig"] \
    .replace('hostname    =   "127.0.0.1"', f'hostname    =   "{DB_HOST}"') \
    .replace('database    =   "xc_vm"',     f'database    =   "{DB_NAME}"')

# Patch run_command to rewrite @'localhost'/@'127.0.0.1' grants to @'%' for Docker
_patched_run_pre = ns["run_command"]
def _grant_patched_run(cmd, shell=True, capture_output=False):
    if isinstance(cmd, str) and ("CREATE USER" in cmd or "GRANT" in cmd):
        cmd = cmd.replace("@'localhost'", "@'%'").replace("@'127.0.0.1'", "@'%'")
    return _patched_run_pre(cmd, shell=shell, capture_output=capture_output)
ns["run_command"] = _grant_patched_run

# Force fixed DB credentials so config.enc matches the MySQL user.
# Call order in __main__: 1st length=32 → rUsername, 2nd length=32 → rPassword, length=8 → admin_code
_orig_gen = ns["generate_random_password"]
_gen_calls = []
def _fixed_gen(length=32):
    if length == 32:
        _gen_calls.append(length)
        return DB_USER if len(_gen_calls) == 1 else DB_PASS
    if length == 8 and ADMIN_CODE:
        return ADMIN_CODE
    return _orig_gen(length)
ns["generate_random_password"] = _fixed_gen

# ── Execute the __main__ block ─────────────────────────────────────────────────
print("[xcvm-patch] Running __main__ block...")
ns["__name__"] = "__main__"
exec(compile(main_source, install_path + ":__main__", "exec"), ns)
print("[xcvm-patch] Installation complete.")
