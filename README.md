# Quick Craft

Craft CMS 5 website for Quick Craft, built from the Figma design. Twig, Tailwind CSS v4, Vite and Alpine.js; local development runs in [DDEV](https://ddev.com).

Project rules and conventions: [`CLAUDE.md`](CLAUDE.md). Build history and how-tos: [`docs/build-log.md`](docs/build-log.md).

## Quick start (DDEV)

```bash
ddev start
ddev composer install
ddev npm install
ddev craft up          # apply project config + migrations
ddev npm run dev       # Vite dev server (or: ddev npm run build)
```

Site: https://quick-craft.ddev.site · Control panel: https://quick-craft.ddev.site/admin

## Craft MCP setup

Craft CMS 5.12+ has a **built-in MCP server**, so no plugin is needed. It lets an AI assistant such as Claude Code read the site's sections, fields and entries, and edit content. It's registered **per developer, in Claude Code's local scope**. There's no shared `.mcp.json`: the command depends on your machine, and the server runs as a Craft admin. `.mcp.json` is gitignored.

The server runs over stdio as a Craft user:

```bash
php craft mcp/serve --user=admin    # --user takes a user ID, username or email
```

Run the commands below **from the project root**, because local scope is tied to the project folder. Then start a new Claude Code session and check it:

```bash
claude mcp get craft                # Status: ✔ Connected
# or type /mcp in Claude Code
```

### macOS / Linux (DDEV)

```bash
ddev start
claude mcp add --scope local craft -- bash -lc "cd $(pwd) && ddev exec php craft mcp/serve --user=admin"
```

`$(pwd)` is expanded when you run the command, so the saved command contains the project's absolute path.

If Claude Code runs inside WSL (installed in Ubuntu, not on Windows), use this command too.

### Windows with WSL (DDEV inside WSL, Claude Code on Windows)

Run this in **Git Bash** from the project folder as Windows sees it (e.g. `\\wsl.localhost\Ubuntu\home\<you>\my-project\quick-craft`, the folder VS Code has open):

```bash
claude mcp add --scope local craft -- wsl -d Ubuntu -- bash -lc "cd ~/my-project/quick-craft && ddev exec php craft mcp/serve --user=admin"
```

- Change `Ubuntu` and `~/my-project/quick-craft` if your distro or path differ. DDEV must be running (`ddev start` in WSL).
- **Don't run it in Windows PowerShell.** When `claude` is installed as `claude.ps1`, PowerShell removes the `--` separators (`error: unknown option '-d'`). It also strips the quotes inside JSON for `claude mcp add-json`.

### Servers without DDEV

**PHP on the same machine** (a local PHP/MySQL setup, or Claude Code running on the server):

```bash
claude mcp add --scope local craft -- php /path/to/quick-craft/craft mcp/serve --user=admin
```

**A remote server over SSH** (needs key-based SSH login; *untested on this project*):

```bash
claude mcp add --scope local craft-staging -- ssh deploy@staging.example.com "cd /var/www/quick-craft && php craft mcp/serve --user=admin"
```

**HTTP transport** (no shell access needed; *untested on this project*): switch on the authenticated MCP server in the control panel under **Settings › MCP**. It's off by default, uses the `/mcp` route, and signs in with OAuth. Then:

```bash
claude mcp add --scope local --transport http craft https://staging.example.com/mcp
```

Claude Code opens the Craft login in your browser the first time.

### Notes

- **It acts as a Craft admin** (Craft Solo allows only one user). Use it to read and inspect the site and to edit content. **Content-model changes** (fields, entry types, sections, plugins, project config) **still go through content migrations**, as `CLAUDE.md` requires. Ask before any delete or uninstall.
- With DDEV, use `ddev exec`, **not** `ddev exec --raw`: `--raw` drops the server's input, so it never answers.
- To remove it: `claude mcp remove craft -s local`.
