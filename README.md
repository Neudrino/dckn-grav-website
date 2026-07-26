# dckn.de — Grav CMS Website

Grav CMS instance for the dckn.de website, running in Docker via the
`linuxserver/grav` image.

## Quick Start (Fresh Deployment)

```bash
git clone <repo-url> grav-website
cd grav-website
docker compose up -d
./setup-plugins.sh
# create package.json for Grav MCP (see below)
npm init -y && npm install grav-mcp
```

Then open `http://localhost:8080`.

## Admin Access

An admin account is pre-configured in `user/accounts/admin.yaml`.
Log in at `http://localhost:8080/admin` with:

- Username: `admin`
- Password: (set during initial setup — see below)

On first boot, if the hashed password needs resetting, delete
`user/accounts/admin.yaml` and complete the admin setup wizard at
`/admin` to create a new admin user.

## Configuration

| File | Purpose |
|---|---|
| `docker-compose.yml` | Container definition, port mapping, bind mounts |
| `user/config/system.yaml` | Grav system config (theme, caching, markdown) |
| `user/config/site.yaml` | Site title, author, metadata |
| `user/config/themes/quark2.yaml` | Theme accent color (`#c6530a`) |
| `user/config/plugins/brevo.yaml` | Brevo newsletter plugin (API key placeholder) |
| `user/themes/quark2/css/custom.css` | CSS overrides (3-column grid, etc.) |
| `setup-plugins.sh` | Installs all required Grav plugins via GPM |

## Brevo Newsletter

The Brevo plugin config (`user/config/plugins/brevo.yaml`) ships with a
placeholder API key. Replace `YOUR_BREVO_V3_API_KEY` with the real key
before the newsletter form will work.

## Grav MCP Server

A local [grav-mcp](https://www.npmjs.com/package/grav-mcp) installation
exposes the Grav REST API as MCP tools, letting AI assistants like opencode
manage site content. Full tool reference: [grav-mcp README](node_modules/grav-mcp/README.md).

### Fresh Setup

1. **Create `package.json` and install the dependency:**

   ```bash
   npm init -y && npm install grav-mcp
   ```

2. **Generate an API key** (printed once — save it):

   ```bash
   docker exec -w /app/www/public grav bin/plugin api keys:generate \
     --user=admin --name="MCP"
   ```

3. **Create `opencode.json`** (gitignored — contains the API key):

   ```json
   {
     "$schema": "https://opencode.ai/config.json",
     "mcp": {
       "grav": {
         "type": "local",
         "command": ["./node_modules/.bin/grav-mcp"],
         "enabled": true,
         "timeout": 10000,
         "environment": {
           "GRAV_API_URL": "http://localhost:8080/api/v1",
           "GRAV_API_KEY": "grav_your_api_key_here"
         }
       }
     }
   }
   ```

   Requires the Grav container running and the API plugin installed (both
   handled by `docker compose up -d` + `./setup-plugins.sh`).

## What's Tracked in Git

- `docker-compose.yml` — container definition
- `user/pages/` — all page content and media (images, PDFs)
- `user/config/` — system, site, theme, and plugin configuration (excluding secrets)
- `user/accounts/` — admin user account
- `user/themes/quark2/css/custom.css` — CSS overrides only
- `setup-plugins.sh` — plugin installation script
## What's NOT Tracked

- `config/` — container runtime (managed by linuxserver image)
- `user/plugins/` — installed via `setup-plugins.sh`
- `user/themes/quark2/` (except `custom.css`) — bundled with image
- `user/data/` — runtime caches, indexes, scheduler data
- `user/config/plugins/api-private.php` — JWT signing secret (auto-generated)
- `user/config/security-private.php` — CSRF nonce secret (auto-generated)
- `node_modules/` — installed via `npm install` (grav-mcp and dependencies)
- `opencode.json` — opencode MCP config (contains API key)
