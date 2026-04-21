# CLAUDE.md

Read at the start of every session. Session-specific notes and in-progress state live in `HANDOFF.md` — read that too.

## Project

**LLM Connector for WordPress** runs in two directions. **Inbound:** a read-only MCP (Model Context Protocol) bridge — AI clients authenticate with a per-plugin API key and fetch site diagnostics (plugin / theme inventory, system status, user counts, content statistics). Verified against Claude.ai Web UI, Claude Code, and Gemini CLI; compatible with Cursor, Windsurf, and VS Code (Cline) via the auto-generated MCP config. **Outbound:** WordPress acts as an AI text-generation client via the WP 7.0 AI Client API and WP 6.9+ Abilities API, with Anthropic, OpenAI, and Google Gemini providers. Current version: **2.1.0** (last shipped: **2.1.0**). See [README.md](README.md) for the full feature tour and [SECURITY.md](SECURITY.md) for the security model.

## Repository layout

```
wp-llm-connector.php          # Main plugin file — header, constants, PSR-4 autoloader, init hook
includes/
  Core/
    Plugin.php                # Singleton orchestrator; wires Security → API → Admin
    Activator.php             # Schema + options on activation; maybe_upgrade() for in-place updates
    Deactivator.php
  API/
    API_Handler.php           # REST routes (/site-info, /plugins, /themes, /system-status,
                              # /user-count, /post-stats, /health, /mcp), permission checks,
                              # static build_mcp_manifest() helper
  Security/
    Security_Manager.php      # API key validation, rate limiting, log_request(), IP extraction
    Write_Permission_Manager.php # Write-tier permission scaffold. NO write endpoints yet
  Admin/
    Admin_Interface.php       # Tabbed settings page (General | AI Providers | API Keys),
                              # API key management, "Connect Your AI Client" MCP config
                              # generator, Access Log page renderer, AJAX handlers
    Access_Log_Table.php      # WP_List_Table implementation powering the Access Log panel
  Providers/                  # Outbound LLM providers (WordPress calls these)
    LLM_Provider_Interface.php
    Provider_Registry.php     # Registry + WP 7.0 AI Client integration
    Anthropic_Provider.php
    OpenAI_Provider.php
    Gemini_Provider.php
  Abilities/
    Abilities_Manager.php     # WP 6.9+ Abilities API registration (function_exists-guarded)
assets/
  css/admin.css               # All admin styling (summary bar, log code badges, MCP snippet, …)
  js/admin.js                 # Key copy, client-snippet renderer, Test Connection, log filters
uninstall.php                 # Options + audit log table cleanup
API_DOCS.md                   # Per-endpoint reference
CHANGELOG.md                  # Keep-a-Changelog format; version is the source of truth
readme.txt                    # WordPress.org format (Stable tag, Description, Changelog)
SECURITY.md                   # Threat model and security claims
HANDOFF.md                    # Session snapshot — read alongside this file
docs/
  WRITE_TIER.md               # Design doc for the premium write tier (no endpoints exist yet)
```

## Architecture rules (non-negotiable)

- **PSR-4 autoloading** under the `WP_LLM_Connector\` namespace. Filename must match class name; subdirectory must match sub-namespace.
- **No `eval()`**, no dynamic code execution, no filesystem writes from REST handlers. (CSV export streams to `php://output`, which is the HTTP response, not disk.)
- **Read-only REST layer.** All routes register `'methods' => 'GET'` only. Write tier is future / premium and does not yet exist.
- **All REST routes go through `API_Handler::check_permissions()`** (never bypass it) — except `/health`, which is public by design. `check_permissions()` enforces: enabled flag → API key → per-endpoint allowlist → rate limit. `/mcp` deliberately has no entry in `$endpoint_map` so the allowlist check is skipped while auth is still required.
- **Audit log every authenticated request** via `Security_Manager::log_request()`. Endpoint slug should match the corresponding `endpoint_map` key (e.g. `site_info`, not `site-info`).
- **WordPress coding standards.** `$wpdb->prepare()` for every parameterized query; whitelist `ORDER BY` columns against an allowlist before interpolation. `esc_html` / `esc_attr` / `esc_url` / `wp_json_encode` for all output. Nonces on every admin form (`wp_nonce_field` + `check_admin_referer` / `check_ajax_referer`).
- **Version constant** `WP_LLM_CONNECTOR_VERSION` lives in `wp-llm-connector.php`. Update it (and the `Version:` header) there first; `CHANGELOG.md` and `readme.txt` follow.
- **Provider API keys** (Anthropic, OpenAI, Gemini) are stored in `wp_llm_connector_settings['providers'][slug]['api_key']`. **Never expose them via REST endpoints.** The AI Providers tab stores them; they travel outbound only inside `generate_text()` via `wp_remote_post()`. SECURITY.md documents this as the single explicit exception to the "no third-party data transmission" principle.

## How to verify changes

- `php -l` on every changed PHP file.
- `node --check assets/js/admin.js` for JS changes.
- Manual test: deactivate + reactivate the plugin to trigger `Activator::maybe_upgrade()` against an existing DB table and confirm no fatal errors. For schema changes, verify against a 1.0-era installation, not just a fresh install.
- After any `API_Handler` change, smoke-test `/health` (no auth) and `/site-info` (with a valid key) and confirm the audit log records both.

## Release workflow

Every version bump — no exceptions — goes through this routine, in order. It exists because skipping steps has bitten us before (shipping with a stale `readme.txt` Stable tag, a missed `CHANGELOG.md` entry, a debug `console.log` in `admin.js`).

### Smoke-test checklist

Run these before the version bump. Any failure is a release blocker.

- `/health` returns `200` with no auth header.
- `/site-info` returns `200` with a valid `X-WP-LLM-API-Key` header; the request shows up in the Access Log.
- `/mcp` manifest returns `200`; its `tools[]` contains **only** the permission slugs currently in `wp_llm_connector_settings.allowed_endpoints` (no ghost entries).
- Admin settings page (`Settings > LLM Connector`) loads without PHP notices, warnings, or fatals. Check `wp-content/debug.log` if `WP_DEBUG_LOG` is enabled.
- Access Log panel (`Settings > LLM Connector Log`) loads; range / status / search filters round-trip through the URL and narrow the result set correctly.

### The routine (run in order, no skipping)

1. `php -l` on every changed PHP file.
2. `node --check assets/js/admin.js`.
3. Run the manual smoke-test checklist above.
4. Version bump, in this order:
    1. `wp-llm-connector.php` — `Version:` header **and** `WP_LLM_CONNECTOR_VERSION` constant.
    2. `CHANGELOG.md` — new dated entry (or `## [Unreleased]` section for `-dev` bumps).
    3. `readme.txt` — `Stable tag` (only on release versions, not `-dev` bumps), `== Changelog ==` entry, `== Upgrade Notice ==` entry.
5. Update `HANDOFF.md`:
    1. Move the current "What was just completed" bullets into the "Decisions log" if any contain decisions worth preserving; otherwise they can be summarized and dropped.
    2. Write the new "What was just completed" section covering this session.
    3. Update "State of each major subsystem" for anything that materially changed.
    4. Re-prioritize "Open tasks" based on what was done and what's now unblocked.
6. Git commit and push:
    1. `git status` — review before staging. Never skip this. Confirm no `.env`, `wp-config.php`, backup files, live site URLs, or credentials are in the diff.
    2. `git add -A`.
    3. `git commit -m "chore: release v{VERSION}` with a HEREDOC body listing the changes. Commit message template:

       ```
       chore: release v{VERSION}

       {One-line summary of what changed}

       - {file changed and why}
       - {file changed and why}
       - ...

       Co-authored-by: Claude <claude@anthropic.com>
       ```
    4. `git push origin main`.
    5. If push fails because the remote diverged: `git pull --rebase origin main` then `git push origin main`. If the rebase produces conflicts, **stop** and surface them — do not `git rebase --skip` or resolve blind.
7. Confirm with `git log --oneline -5`.

### Hard rules — stop and flag, do not proceed

- Never commit credentials, API keys, live site URLs, `wp-config.php`, `.env`, or anything under a path matching `*/private/*`. If any of those appear in `git status`, stop and surface them before staging.
- Never `--no-verify` a commit or `--force-push` to `main` without explicit, in-session user approval for that specific push. A past approval does not carry forward.
- Before the first commit of any session, `git status` and verify no sensitive files are staged. A stray local file from another workflow (editor swap files, debug dumps) must not be included.

## Known deviations / gotchas

- The MCP config snippet and `/mcp` manifest both use `X-WP-LLM-API-Key` as the auth header — **not** `X-API-Key`. Keep admin JS, PHP validation, and generated configs consistent.
- **Plaintext API keys** are only available in a per-user transient (`wp_llm_connector_new_key_<user_id>`) immediately after generation. There is no other code path to retrieve them — hashed-at-rest is the whole point.
- The **`/mcp` manifest filters its tool list** to enabled endpoints. If an integration test returns fewer tools than expected, check the endpoint allowlist in `wp_llm_connector_settings.allowed_endpoints`, not the code.
- **DB schema version** is tracked in the `wp_llm_connector_db_version` option. Bump `Activator::DB_VERSION` whenever you add columns or indexes — `dbDelta()` will apply them via `maybe_upgrade()` on the next request.
- **AI Providers tab is separate from MCP client config.** Providers tab = WordPress calling LLMs (outbound). API Keys tab + Connect section = LLMs calling WordPress (inbound). These are opposite data flows and must not be conflated in UI copy or documentation.

## What NOT to put here

Code-style preferences, linter rules, and one-off task instructions — those belong in the prompt for that specific task, not in durable project memory.
