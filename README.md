# Quick Craft

Craft CMS 5 website for Quick Craft, built from the Figma design. Twig, Tailwind CSS v4, Vite and Alpine.js; local development runs in [DDEV](https://ddev.com).

Project rules and conventions: [`CLAUDE.md`](CLAUDE.md). Build history and how-tos: [`docs/build-log.md`](docs/build-log.md).

## New developer setup

### Requirements

- **[DDEV](https://ddev.com/get-started/) + Docker** (Docker Desktop, OrbStack, Colima or Docker CE). All PHP, Composer, Node and Craft commands run inside DDEV; nothing else needs to be installed on your machine.
- **Windows:** **WSL2**, with the project **inside the WSL filesystem** (e.g. `~/projects/quick-craft`, not under `/mnt/c/…`), and **Claude Code running inside WSL**. DDEV is installed in WSL too. From the Windows side, `/mnt/c` and `\\wsl.localhost` paths are slow and break file watching.
- **Claude Code** (optional), for the Craft MCP server below.

### Steps

```bash
git clone <repository-url> quick-craft
cd quick-craft
cp .env.example.dev .env           # app settings; DDEV provides the database and site URL
ddev start
ddev composer install
ddev npm install
ddev craft setup/app-id            # fills CRAFT_APP_ID in .env
ddev craft setup/security-key      # fills CRAFT_SECURITY_KEY in .env
ddev import-db --file=path/to/quick-craft.sql.gz   # database snapshot, ask the team for the latest
ddev craft up                      # apply project config + migrations on top of the snapshot
ddev npm run dev                   # Vite dev server (or `ddev npm run build`)
```

- **Uploads aren't in Git** (`web/uploads/` is ignored). Get the `web/uploads/images` folder together with the database snapshot, then run `ddev craft index-assets/one images`.
- **Making a snapshot to share:** `ddev export-db --file=quick-craft.sql.gz`.
- Site: https://quick-craft.ddev.site · Control panel: https://quick-craft.ddev.site/admin · phpMyAdmin: https://quick-craft.ddev.site:8037

### HTTPS (trust DDEV's certificate)

DDEV serves the site over HTTPS with a local [mkcert](https://github.com/FiloSottile/mkcert) certificate. Trust it once, so browsers, and **Playwright screenshots**, accept `https://quick-craft.ddev.site`:

```bash
mkcert -install
ddev restart
```

- **Linux / WSL:** for Chromium-based browsers and Playwright, mkcert also needs the NSS tools (`sudo apt install libnss3-tools`) before you run `mkcert -install`.
- **Windows with WSL2:** run `mkcert -install` on **Windows** too, and share the Windows CA with WSL, as described in [DDEV's WSL2 install guide](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/#wsl2-docker-ce-inside-install-script). Then run `mkcert -install` again inside WSL.
- A Playwright script can also skip the check with `browser.newContext({ ignoreHTTPSErrors: true })`, but trusting the certificate is the proper fix.

## Craft MCP setup

Craft CMS 5.12+ has a **built-in MCP server**, so no plugin is needed. It lets an AI assistant such as Claude Code read the site's sections, fields and entries, and edit content. The server runs over stdio as a Craft user (`php craft mcp/serve --user=<username>`).

### Works automatically: `.mcp.json`

The committed [`.mcp.json`](.mcp.json) starts the server through DDEV. It has no machine-specific paths:

```json
{ "mcpServers": { "craft": { "command": "ddev", "args": ["exec", "php", "craft", "mcp/serve", "--user=${CRAFT_MCP_USER:-admin}"] } } }
```

1. Run Claude Code **in the project root** (macOS, Linux, or **inside WSL** on Windows) with DDEV running.
2. **Set `CRAFT_MCP_USER` to your own Craft username**, e.g. in `~/.bashrc` / `~/.zshrc`:
   ```bash
   export CRAFT_MCP_USER=your-craft-username
   ```
   Without it, the server runs as `admin`. This site is on **Craft Solo, which allows only one user**, so everyone is `admin` until the site moves to Craft Team or Pro.
3. The first time, approve the project server `craft` when Claude Code asks, then check it with `/mcp` or `claude mcp get craft`.

### Windows: Claude Code running outside WSL

Claude Code on Windows can't run `ddev` directly, so add a **local-scope override** with the same name; local scope takes priority over `.mcp.json`. Run it in **Git Bash** from the project folder as Windows sees it (e.g. `\\wsl.localhost\Ubuntu\home\<you>\…\quick-craft`):

```bash
claude mcp add --scope local craft -- wsl -d Ubuntu -- bash -lc "cd ~/path/to/quick-craft && ddev exec php craft mcp/serve --user=your-craft-username"
```

- Change `Ubuntu`, the path and the username to yours.
- **Don't run it in Windows PowerShell:** when `claude` is installed as `claude.ps1`, PowerShell removes the `--` separators (`error: unknown option '-d'`).
- To remove the override: `claude mcp remove craft -s local`.

### Servers without DDEV

**PHP on the same machine** (a local PHP/MySQL setup, or Claude Code running on the server), as a local override:

```bash
claude mcp add --scope local craft -- php /path/to/quick-craft/craft mcp/serve --user=your-craft-username
```

**A remote server over SSH** (needs key-based SSH login; *untested on this project*):

```bash
claude mcp add --scope local craft-staging -- ssh deploy@staging.example.com "cd /var/www/quick-craft && php craft mcp/serve --user=your-craft-username"
```

**HTTP transport** (no shell access needed; *untested on this project*): switch on the authenticated MCP server in the control panel under **Settings › MCP**. It's off by default, uses the `/mcp` route, and signs in with OAuth. Then:

```bash
claude mcp add --scope local --transport http craft-staging https://staging.example.com/mcp
```

### Notes

- **The server acts as your Craft user with that user's rights** (here: admin). Use it to read and inspect the site and to edit content. **Content-model changes** (fields, entry types, sections, plugins, project config) **still go through content migrations**, as `CLAUDE.md` requires. Ask before any delete or uninstall.
- With DDEV, use `ddev exec`, **not** `ddev exec --raw`: `--raw` drops the server's input, so it never answers.
