# Deployment instructions for middleware app

These steps show how to upload and run the middleware on the Ubuntu server (user: kotelhms).

0) **Before deploying, configure the kernel UDP receive buffer** (required for HD/multi-program UDP ingest):

The middleware requests a 32 MB socket receive buffer (`buffer_size=33554432`) on every UDP ingest. The kernel silently clamps it to `net.core.rmem_max` — if that is small (Linux defaults to ~213 KB), a 7 Mbps HD mux overflows the socket during bursts and the channel plays ~1 s then stalls ~5 s repeatedly. Set it once on the server:

```bash
echo "net.core.rmem_max = 33554432" | sudo tee /etc/sysctl.d/90-iptv-udp.conf
sudo sysctl --system
```

Verify it took effect: `sysctl net.core.rmem_max` should print `33554432`.

1) From your local machine, create an archive of the workspace (exclude large vendor dirs):

```bash
tar --exclude vendor --exclude node_modules -czf middleware.tar.gz .
scp middleware.tar.gz kotelhms@10.0.0.10:~/
```

2) SSH to the server (prefer SSH key auth):

```bash
ssh kotelhms@10.0.0.10
```

3) On the server, prepare the app folder and extract:

```bash
mkdir -p ~/middleware
tar -xzf ~/middleware.tar.gz -C ~/middleware
cd ~/middleware
chmod +x deploy.sh
```

4) Edit `.env` (created from `.env.example`) to set `APP_URL` and any DB credentials. Ensure you do not point to databases used by the hotel system.

5) Run the deploy script:

```bash
./deploy.sh
```

6) Verify the hotel management system remains available (check its original ports/URLs). To rollback the middleware only:

```bash
docker compose -p middleware_app down
```

Notes
- The deploy script uses Docker Compose with project name `middleware_app` to avoid container/name collisions.
- Do not run the script as root; run as `kotelhms` to keep artifacts isolated to the user home.
- If Docker is missing, install it first using the steps in the earlier assistant message.

Alternative: Non-Docker (user-local) installation
------------------------------------------------
If you prefer a normal installation without Docker (non-destructive), use the provided `install_no_docker.sh` script. It installs required packages (via apt), runs `composer install`, builds assets, and creates simple `start_server.sh`/`stop_server.sh` scripts that run the application using PHP's built-in server bound to `127.0.0.1:8081`.

Quick steps (on the server as `kotelhms`):

```bash
# extract the code to ~/middleware (if not already)
mkdir -p ~/middleware
tar -xzf ~/middleware.tar.gz -C ~/middleware
cd ~/middleware
chmod +x install_no_docker.sh
./install_no_docker.sh
# then start the app
./start_server.sh
```

This approach is intentionally conservative:
- It avoids editing or adding system-wide webserver configuration (nginx), so it won't overwrite or remove the existing hotel management system.
- It's suitable for staging or low-traffic production only. For robust production use, I recommend Docker or a proper webserver+php-fpm/nginx configuration with separate sockets and careful nginx vhost additions.

