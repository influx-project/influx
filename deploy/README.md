# Production deployment

Everything runs with Docker Compose on a single server. You can run it with HTTPS, using a free certificate from Let's Encrypt that is issued and renewed automatically, or over plain HTTP.

## What runs

| Service      | What it does                                                                                  |
| ------------ | --------------------------------------------------------------------------------------------- |
| `web`        | The website on ports 80/443 (FrankenPHP with Caddy). Also passes live-update WebSockets to `reverb`. |
| `reverb`     | WebSocket server for live updates. Starts live checks when someone opens a service's live view. |
| `queue`      | Runs background checks and other queued jobs.                                                 |
| `queue-live` | Runs live checks every ~4 s while someone is watching a service; they stop when the last viewer leaves. |
| `scheduler`  | Queues background checks (every 10 s), restarts any stalled live checks (every minute) and prunes old metrics daily. |
| `migrate`    | Runs database migrations on start, then exits. The other app services wait for it.            |
| `postgres`   | Database.                                                                                     |
| `redis`      | Cache, sessions and job queues.                                                               |

All the app services use the same image, `ghcr.io/influx-project/influx-panel`. It is built from [`Dockerfile`](Dockerfile) and published to GitHub Packages for amd64 and arm64 on every commit to `main`, so the server doesn't need to build anything.

## Requirements

- A Linux server with **Docker** and the **Docker Compose plugin** (`docker compose version` should work).
- For HTTPS: a **domain name** whose DNS `A`/`AAAA` record points at the server, with **ports 80 and 443** open to the internet.

## 1. Get the code and create the settings file

```bash
git clone <your-repo-url> influx-project
cd influx-project/deploy
cp .env.example .env
```

Every command below is run from this `deploy` directory.

## 2. Fill in `.env`

Open `.env` and set the required values.

**Secrets** (run each command and paste the output):

| Setting             | Generate with                                                            |
| ------------------- | ------------------------------------------------------------------------ |
| `DB_PASSWORD`       | `openssl rand -hex 24`                                                   |
| `REVERB_APP_ID`     | any number, e.g. `123456`                                                |
| `REVERB_APP_KEY`    | `openssl rand -hex 16`                                                   |
| `REVERB_APP_SECRET` | `openssl rand -hex 32`                                                   |
| `APP_KEY`           | after pulling in step 3: `docker compose run --rm --no-deps migrate php artisan key:generate --show` |

Then choose **one** of the following.

### Option A: with SSL (recommended)

```dotenv
APP_URL=https://monitor.example.com
SERVER_NAME=monitor.example.com
SESSION_SECURE_COOKIE=true
CADDY_GLOBAL_OPTIONS="email you@example.com"
```

Replace `monitor.example.com` with your domain. On first start the web server requests a certificate from Let's Encrypt, then renews it automatically, and redirects HTTP to HTTPS. The email is optional; Let's Encrypt uses it for expiry warnings.

> The certificate can only be issued once the domain's DNS points at this server and ports 80 and 443 are reachable from the internet.

### Option B: without SSL

```dotenv
APP_URL=http://203.0.113.10
SERVER_NAME=:80
SESSION_SECURE_COOKIE=false
CADDY_GLOBAL_OPTIONS=
```

Use the server's IP address or hostname in `APP_URL`. The site is served over plain HTTP on port 80. Use this for a private network or for testing: passwords and session cookies travel unencrypted.

To use a different port, e.g. 8080, set `HTTP_PORT=8080` and include it in the URL: `APP_URL=http://203.0.113.10:8080`.

## 3. Pull and start

```bash
docker compose pull
docker compose run --rm --no-deps migrate php artisan key:generate --show
```

Paste the printed `base64:...` value into `APP_KEY` in `.env`, then start everything:

```bash
docker compose up -d
```

Check that everything is running:

```bash
docker compose ps
```

`migrate` should show as exited (0), and every other service as running or healthy. Open your `APP_URL` in a browser.

## 4. Create the first admin

1. Open the site and **register** an account.
2. Make it an admin and mark its email verified (replace the address):

   ```bash
   docker compose exec web php artisan tinker --execute \
     "App\Models\User::where('email', 'you@example.com')->update(['admin' => true, 'email_verified_at' => now()]);"
   ```

3. Refresh the page. The admin area is at `/admin`.

Accounts must verify their email address before they can use the app. To let other people sign up themselves, configure the `MAIL_*` settings in `.env` (for example, your email provider's SMTP details) and run `docker compose up -d` again. Until mail is set up, verify accounts with the tinker command above, leaving out `'admin' => true`.

## Updating

```bash
git pull
docker compose pull
docker compose up -d
```

Migrations run automatically on start.

### Choosing a version

By default you run `latest`, the newest commit on `main`. To stay on a specific version, set `APP_TAG` in `.env`:

| `APP_TAG`      | What you get                                   |
| -------------- | ---------------------------------------------- |
| `latest`       | The newest commit on `main` (default).         |
| `1.2` / `1.2.3` | A release, from the `v1.2.3` Git tag.          |
| `sha-abc1234`  | One exact commit, e.g. to roll back.           |

### Building the image yourself

To run local changes, or a fork, build from this checkout instead of pulling:

```bash
docker compose build
docker compose up -d
```

Set `APP_IMAGE` in `.env` to use a different image name, e.g. your fork's `ghcr.io/<you>/influx-panel`.

## Everyday operations

| Task                            | Command                                                   |
| ------------------------------- | --------------------------------------------------------- |
| Follow all logs                 | `docker compose logs -f`                                  |
| Logs for one service            | `docker compose logs -f queue`                            |
| Restart everything              | `docker compose restart`                                  |
| Stop everything                 | `docker compose down` (data is kept in volumes)           |
| Run an Artisan command          | `docker compose exec web php artisan <command>`           |
| More background-check workers   | `docker compose up -d --scale queue=3`                    |

After changing `.env`, apply it with `docker compose up -d`, which recreates the affected containers.

### Backups

Everything worth keeping is in the `postgres` volume. To back it up:

```bash
docker compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' > backup-$(date +%F).sql
```

To restore into a fresh deployment, start only the database, load the dump, then start everything else:

```bash
docker compose up -d postgres
docker compose exec -T postgres sh -c 'psql -U "$POSTGRES_USER" "$POSTGRES_DB"' < backup-2026-01-01.sql
docker compose up -d
```

Raw check results are kept for 30 days. Incidents are kept indefinitely.

## Other setups

### Using your own certificate

Put your certificate and key in `deploy/certs/`, then create `deploy/compose.override.yaml`:

```yaml
services:
  web:
    volumes:
      - ./certs:/certs:ro
```

In `.env`, keep `SERVER_NAME` set to your domain and add:

```dotenv
CADDY_SERVER_EXTRA_DIRECTIVES="tls /certs/fullchain.pem /certs/privkey.pem"
```

Then run `docker compose up -d`.

### Behind an existing reverse proxy or load balancer

If something else in front of this stack already handles HTTPS (nginx, Traefik, a cloud load balancer):

1. Use **Option B** (`SERVER_NAME=:80`) and publish a free port, e.g. `HTTP_PORT=8080`.
2. Set `APP_URL` to the public `https://` address and `SESSION_SECURE_COOKIE=true`.
3. Set `TRUSTED_PROXIES` to the proxy's IP address, or `*` if only the proxy can reach the port.
4. Make sure the proxy forwards WebSocket upgrades for the path `/app/` (live updates), and sends `X-Forwarded-For` and `X-Forwarded-Proto`.

## Troubleshooting

**The certificate isn't issued, or the browser shows a certificate error.**
Check `docker compose logs web`. Usually the domain's DNS doesn't point at this server yet, or port 80/443 is blocked by a firewall. Caddy keeps retrying, so fix the cause and wait a minute.

**Live status says it can't reach the live stream.**
Check that `reverb` is running (`docker compose ps`). If you use your own reverse proxy, it must forward WebSocket upgrades on `/app/`.

**Live status connects but says there are no live results.**
Check that `queue-live` and `reverb` are running, and look at `docker compose logs reverb queue-live`. Reverb starts the checks when someone subscribes; if they stopped (for example after a worker restart), `scheduler` restarts them within a minute.

**No background check results appear.**
Check `scheduler` and `queue`. If many checks time out, `queue` can fall behind; add workers with `--scale queue=3`.

**Ping checks always fail.**
Ping needs the server to allow ICMP. Some cloud providers or firewalls block outgoing ICMP.

**The site shows a 500 error.**
`docker compose logs web` shows the exception. A missing `APP_KEY` is the most common cause on first setup.
