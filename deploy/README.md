# Staging deploy

## DNS

`app.cors-port.ru` must point to the Finland VPS public IP.

## GitHub secrets

Add these repository secrets before running the manual `Deploy` workflow:

- `VPS_HOST` - `89.125.169.38`
- `VPS_USER` - `deployer`
- `VPS_SSH_KEY` - private SSH key with access to the VPS
- `GHCR_USERNAME` - GitHub username, usually `rrmini1`
- `GHCR_TOKEN` - GitHub token with `read:packages`

## First server setup

Create the deploy directory:

```bash
mkdir -p /home/deployer/vpn-billing
cd /home/deployer/vpn-billing
```

Create `.env` from `deploy/.env.example` and fill real secrets:

```bash
cp deploy/.env.example .env
php -r "echo 'APP_KEY=base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Put the generated `APP_KEY` into `.env`.

Required production values:

- `DB_PASSWORD`
- `REDIS_PASSWORD`
- `TELEGRAM_BOT_TOKEN`
- `MARZBAN_USERNAME`
- `MARZBAN_PASSWORD`

## HTTPS

Run Caddy on the VPS and use:

```caddyfile
app.cors-port.ru {
    reverse_proxy 127.0.0.1:8080
}
```

The billing compose file publishes nginx only on `127.0.0.1:8080`, so the app is exposed through Caddy/HTTPS only.

## Deploy

After CI builds and publishes images, run GitHub Actions workflow:

`Actions -> Deploy -> Run workflow`

The workflow will:

1. upload `deploy/docker-compose.yml`;
2. pull latest GHCR images;
3. restart containers;
4. run migrations.
