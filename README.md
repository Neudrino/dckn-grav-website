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
| `user/config/themes/dckn.yaml` | Theme accent color (`#c6530a`) |
| `user/config/plugins/brevo.yaml` | Brevo newsletter plugin (API key placeholder) |
| `user/themes/dckn/` | Child theme: all our theme customizations |
| `setup-plugins.sh` | Installs all required Grav plugins via GPM |

## Child Theme `dckn`

All theme customizations (templates, CSS, languages, the event page
blueprint) live in `user/themes/dckn/`, a Grav child theme that
inherits from the bundled Quark 2 theme:

- **Templates/CSS:** resolved via the `theme://` stream chain — files
  present in `dckn/` win, everything else falls through to `quark2`.
- **Admin form:** `blueprints.yaml` inherits Quark 2's form fields via
  `extends@: themes://quark2/blueprints.yaml`.
- **PHP class:** `dckn.php` extends `Grav\Theme\Quark2`.
- Active theme: `pages.theme: dckn` in `user/config/system.yaml`.

### After an Image/Quark 2 Update

Nothing tracked gets overwritten anymore. `docker compose pull` +
restart only updates Quark 2. If a Quark 2 release changes one of the
templates we also ship in `dckn/templates/`, compare and patch:

```bash
docker run --rm lscr.io/linuxserver/grav:latest \
  cat /app/www/public/user/themes/quark2/templates/partials/base.html.twig \
  > /tmp/upstream-base.html.twig
diff /tmp/upstream-base.html.twig user/themes/dckn/templates/partials/base.html.twig
```

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


