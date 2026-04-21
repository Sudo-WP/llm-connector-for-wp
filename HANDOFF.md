# HANDOFF

Living session snapshot. Updated at the end of every significant work block. Read alongside [CLAUDE.md](CLAUDE.md).

## Current version & status

**Version:** 2.1.0 (last shipped: 2.1.0)
**Date:** 2026-04-22
**Status:** `main` is now the single active branch. The MCP-bridge / access-log / write-tier line (previously `v0.4.0-dev`) has been merged additively into the v2.0.0 providers line. All code from both sides retained. `v0.4.0-dev` branch deleted locally and on origin. Merge commit pushed to `origin/main`.

## What was just completed

- **Additive merge of `v0.4.0-dev` → `main`** at commit `6402a5c feat: merge v0.4.0-dev MCP bridge line into main (v2.1.0)`, followed by merge commit `e062b9d` that reconciles origin's 2 README "Demo Video" YouTube updates. **Nothing was removed from either side.**
  - From `v0.4.0-dev`: `/mcp` endpoint + `build_mcp_manifest()` + composite `wp_full_diagnostics` tool, Access Log admin panel (`Access_Log_Table.php`), "Connect Your AI Client" MCP config generator, Write_Permission_Manager scaffold (no write endpoints exposed), `CLAUDE.md`, `HANDOFF.md`, `SECURITY.md`, `docs/WRITE_TIER.md`, DB schema 2.1 (`http_method`, `execution_time_ms`, `response_code`/`ip_address` indexes), Security_Manager `write_enabled`/`write_scopes` backfill.
  - From `main` v2.0.0: Providers system (Anthropic / OpenAI / Gemini + `LLM_Provider_Interface` + `Provider_Registry`), `Abilities_Manager`, WP 7.0 AI Client + WP 6.9 Abilities API integration, tabbed admin UI (General | AI Providers | API Keys), optional Composer autoloader, main's Reveal/Hide + Copy-Key JS handlers.
- **Conflict resolutions** applied per the session's rules:
  - `wp-llm-connector.php`: version `2.1.0`; merged description (MCP bridge + AI providers, one sentence each).
  - `Activator.php`: `DB_VERSION = '2.1'`; CREATE TABLE includes both sides' columns (`http_method`, `execution_time_ms`, `provider`) + all indexes; both `maybe_upgrade()` and `create_or_upgrade_table()` public statics retained, `maybe_upgrade()` delegates to `create_or_upgrade_table()`.
  - `Plugin.php`: wire order fixed per spec — Security → Provider_Registry → API_Handler → Admin → rest_api_init → wp_ai_client registration → Abilities_Manager → `Activator::maybe_upgrade()` → cleanup cron.
  - `Admin_Interface.php`: tabbed UI preserved (General | AI Providers | API Keys); Access Log submenu added; Connect Your AI Client + Recent Activity widget placed in the API Keys / General tabs respectively; `handle_provider_settings`, `handle_access_log_actions`, `ajax_test_connection` all wired; `sanitize_settings` preserves `write_enabled`/`write_scopes` with a code-level scope whitelist; providers tab gained the required clarification notice.
  - `Security_Manager.php`: auto-merge produced the correct shape — `apply_write_tier_defaults()` backfill in `validate_api_key()`, `http_method` + `execution_time_ms` in `log_request()`, `mark_request_start()` static.
  - `API_Handler.php`: auto-merge clean — `/mcp` endpoint, `build_mcp_manifest()`, `rest_pre_dispatch` timer all present.
  - `CHANGELOG.md`: new `[2.1.0] - 2026-04-21` entry at top; `[2.0.0]` verbatim from origin; `[0.1.x]` entries preserved.
  - `README.md`: full rewrite merging origin's providers/Abilities feature list with v0.4.0-dev's "Why LLM Connector?" + "Compatibility" table + "/mcp" endpoint + Access Log mentions. YouTube demo embed from origin's 2 README commits preserved once after de-duping on the final merge.
  - `readme.txt`: Stable tag `2.1.0`; inbound + outbound description; changelog + upgrade notice for 2.1.0.
  - `SECURITY.md`: Design Principle #4 extended with the explicit provider-outbound-HTTP exception paragraph.
  - `CLAUDE.md`: version line → `2.1.0`; repo-layout block adds `Providers/`, `Abilities/`, `docs/`; new architecture rule for provider-API-key storage; new gotcha separating provider tab (outbound) from API Keys tab (inbound).
  - `mcp/wordpress_mcp_server.py`: both branches had tools identical in name; used main's refactored version (MCP 1.6+ / Pydantic 2.12+ compat fixes). No tools lost.
  - `assets/css/admin.css` + `assets/js/admin.js`: kept both sides' handlers. JS now has the shared handlers (`.wp-llm-copy-new-key`, `showCopySuccess`, `fallbackCopy`) *plus* main's Reveal/Hide + Copy Key handlers *plus* v0.4.0-dev's Access Log range toggle + Connect Your AI Client snippet renderer + Test Connection.
- **Lint clean:** `php -l` on all 16 PHP files (including the 5 Providers + Abilities_Manager) and `node --check assets/js/admin.js` both pass.
- **Secrets scan clean** on the pre-commit staged diff — only placeholder strings (`YOUR_API_KEY_HERE`, `wpllm_your_api_key_here`, `prod_key_here`, `staging_key_here`) and header names. No live keys, AWS, Bearer, or `wp-config`.
- **Push sequence:**
  1. `git commit` created `6402a5c` on local main.
  2. First attempt `git pull --rebase origin main` tried to linearize 66 commits (the entire v0.4.0-dev history) onto origin's 2-ahead state and stopped on a pre-divergence commit. **Aborted** immediately (`git rebase --abort`) before any destructive action.
  3. Switched to `git pull origin main --no-rebase` — single merge commit `e062b9d` reconciled origin's 2 README updates. Cleaned up a duplicate "Demo Video" section and amended the merge commit.
  4. `git push origin main` — fast-forward `5b73ad5..e062b9d`.
- **Branch cleanup:** `git push origin --delete v0.4.0-dev` (remote gone), `git branch -d v0.4.0-dev` (local gone).

## State of each major subsystem

**REST API (`includes/API/API_Handler.php`)**
Nine routes: `/health` (public), `/mcp` (auth, filtered manifest), and 6 read-only data endpoints. `rest_pre_dispatch` timer hook attaches `execution_time_ms` to audit entries. Read-only surface complete for 2.x. Write routes (under `/wp-llm-connector/v1/write/*`) remain unbuilt; `Write_Permission_Manager::authorize_write_request()` fails closed with `503 write_tier_not_implemented`.

**Security / Auth (`includes/Security/Security_Manager.php`)**
SHA-256 key hashing, constant-time compare, rate-limit window (transient-backed, TTL set only on window entry). `validate_api_key()` returns enriched records with `write_enabled`/`write_scopes` backfilled. `log_request()` captures `http_method` and `execution_time_ms`. Includes table-name regex validation and IP sanitization from main's 0.1.3 hardening.

**Write permission scaffold (`includes/Security/Write_Permission_Manager.php`)**
Token generation + validation production-ready (SHA-256-hashed transient, 15-minute TTL, single-use consume-on-validate). `key_has_write_access()` reads from settings. `check_write_capability()` and `authorize_write_request()` stubbed and default closed. Next: build the UI and the first write endpoint (`POST /write/cache/flush` — idempotent, lowest-risk).

**Providers (`includes/Providers/*`)**
Unchanged from main v2.0.0. `LLM_Provider_Interface`, `Provider_Registry`, `Anthropic_Provider`, `OpenAI_Provider`, `Gemini_Provider`. Auto-registers with the WP 7.0 AI Client API when available. Provider API keys stored in `wp_llm_connector_settings['providers'][slug]['api_key']`. Only travels outbound inside `generate_text()` via `wp_remote_post()`; never exposed via REST.

**Abilities (`includes/Abilities/Abilities_Manager.php`)**
Unchanged from main v2.0.0. 8 abilities registered under the `llm-connector` category when WP 6.9+ Abilities API is available (`function_exists()`-guarded, no-op otherwise).

**Admin UI (`includes/Admin/Admin_Interface.php`)**
Tabbed settings page — General | AI Providers | API Keys — plus Access Log submenu. General tab has the main form + log purge workaround that closes/reopens the options.php form to avoid nested `<form>`. AI Providers tab has per-provider config + clarification notice (outbound != inbound MCP config). API Keys tab has the existing keys table + Connect Your AI Client section with masked snippet + Copy full config + Test Connection. Recent Activity widget on General tab. AJAX Test Connection handler gated by capability + nonce.

**Access Log (`includes/Admin/Access_Log_Table.php`)**
`WP_List_Table` with pagination (50/page), sortable columns, filter bar (range / status / search), summary bar (requests today, unique IPs, error rate), CSV export (includes `provider` column), Clear Log with confirmation. `build_where_clause()` shared by list, summary, and CSV export for consistent filter semantics.

**MCP Python server (`mcp/wordpress_mcp_server.py`)**
Main's refactored version — MCP 1.6+ / Pydantic 2.12+ compatible. Same tool surface as the old one: `wp_health_check`, `wp_get_site_info`, `wp_list_plugins`, `wp_list_themes`, `wp_get_system_status`, `wp_get_user_count`, `wp_get_post_stats`, `wp_full_diagnostics`. Works alongside the new HTTP `/mcp` manifest endpoint (Python = stdio clients like Claude Code; HTTP = remote clients like Claude.ai Web UI).

## Open tasks (prioritised)

1. **Admin UI for per-key write flags and write-session tokens. (M)** Covers the per-key "Enable write access" checkbox, the scope checkboxes (posts / plugins / options / users / cache), the "Generate write session token" button wired to `Write_Permission_Manager::generate_write_token()`, and the red "Use on staging first" callout. See `docs/WRITE_TIER.md` §"UI changes needed". **Do not ship without also shipping a working write endpoint** — a UI that toggles flags no endpoint respects is a footgun.
2. **First write endpoint: `POST /write/cache/flush`. (S–M)** Lowest-risk v1 write — idempotent, no resource diff, `manage_options` capability. Use it to prove the full permission chain end to end, then move on to `POST /write/posts` and the rest. Fully implement `Write_Permission_Manager::authorize_write_request()` as part of this.
3. **Add `owner_user_id` to the per-key record. (S)** Capture `get_current_user_id()` at key generation; backfill to `0` on read for legacy keys. Required for `check_write_capability()` to run against the right user context rather than `current_user_can()` (which is `WP_User(0)` on REST requests).
4. **Rate-limit TTL reconciliation. (S)** Merged code preserves main's 0.1.3 hardening (60-second window) while UI copy says "per hour". Either update UI copy to "per minute", or raise TTL back to `HOUR_IN_SECONDS` — whichever matches operator expectations. Flag for product decision before shipping 2.2.
5. Webhook support for proactive alerts. (M) Design first: outbound auth, retry policy, payload signing. Separate doc (`docs/WEBHOOKS.md`) before any code.
6. Saved filter presets in the Access Log panel. (S) Pure UI + options storage.
7. Test Connection in the admin should additionally fetch `/mcp` and validate the manifest shape. (S)
8. Custom endpoint builder (GUI). (L) Read-only endpoints only; no raw SQL; sandbox carefully.

## Decisions log

Reverse-chronological.

- **2026-04-21** | Providers system retained on merge | Code is clean, WP 7.0 AI Client integration is forward-compatible, providers are disabled by default. UI clarification notice added to prevent conflation with MCP client config.
- **2026-04-21** | Merge, not rebase, when reconciling origin/main | First attempted `git pull --rebase origin main` tried to linearize 66 branch commits onto origin and stopped on a pre-divergence commit. Aborted and used `git pull --no-rebase` instead — one extra merge commit is worth it to preserve the `v0.4.0-dev` history intact on `main`.
- **2026-04-21** | Python MCP server: used main's refactored version | Both sides exposed the same tools; main's is smaller, MCP 1.6+ / Pydantic 2.12+ compatible. No functional regression.
- **2026-04-21** | Single merge commit preferred over 66 separate commits | The previous session had committed 4 sessions worth of work as one "feat:" commit on v0.4.0-dev; merging that into main via a single merge commit keeps both parents addressable (`6402a5c^1` = main tip before merge, `6402a5c^2` = v0.4.0-dev work) without fragmenting history.
- **2026-04-21** | Version 2.1.0 (not 3.0.0, not 0.5.0) | The merge is additive — no removals, no breaking API changes. Minor bump from 2.0.0 is correct per SemVer.
- **2026-04-21** | Local work landed on `v0.4.0-dev` branch, not force-pushed to `main` | Origin's v2.0.0 "providers" line is genuine upstream work representing a real (if divergent) product direction, not abandoned scratch. Destroying it with a force-push would be irreversible and would lose potentially-valuable code. Opening a PR from `v0.4.0-dev` → `main` puts the reconciliation decision in review, where it belongs.
- **2026-04-21** | Write-tier migration is options-only, no DB version bump | Per-key fields live in `wp_llm_connector_settings`. Backfill-on-read in `validate_api_key()` is simpler and safer than an activation-time rewrite that could race with a live site.
- **2026-04-21** | `authorize_write_request()` fails closed with a 503 in the scaffold | Shipping a permission gate whose half-built body returns "allowed by default" is strictly worse than one that 503s. A 503 surfaces the gap loudly; a silent allow hides it.
- **2026-04-21** | Write scopes hard-coded in the sanitizer, not driven by settings | A runtime-editable scope list collapses to "any scope is valid" once it's in the hands of anyone with admin access. Code-level allowlist is the invariant.
- **2026-04-21** | Write tokens are single-use with a 15-minute TTL | A leaked token must have bounded blast radius. 15 min is long enough for a CLI or AI assistant to build a request; short enough that the token doesn't linger in shell history. Single-use is the whole point.
- **2026-04-21** | Write endpoints live under `/wp-llm-connector/v1/write/*` | One `location` block at the nginx/Apache layer enforces read-only at infrastructure level. Worth the slightly longer URL.
- **2026-04-21** | Per-key `write_enabled` flag (not a global toggle) | Site-wide write mode that silently lifts every key out of read-only is a recipe for silent privilege escalation. The flag is per-key, default false, and no code path flips it implicitly.
- **2026-04-21** | `/mcp` route authenticated but not allowlist-gated | A client that has authenticated should always be able to learn which tools exist. Filtering happens inside the manifest (tools array), not at the gate.
- **2026-04-21** | `wp_full_diagnostics` composite omitted unless every step is enabled | Advertising a composite that will fail mid-sequence is worse than not advertising it.
- **2026-04-21** | Manifest logic extracted to static `build_mcp_manifest()` | Unit tests can exercise tool-filtering without booting WordPress's REST server.
- **2026-04-21** | SHA-256 key hashing over WP Application Passwords | Security isolation: keys are scoped to this plugin and revocable without touching WP user auth.
- **2026-04-21** | Read-only enforced at the permission layer, not a toggle | Ensures production safety even if settings are misconfigured.
- **2026-04-21** | `/mcp` manifest filters to enabled endpoints | AI clients get an accurate tool list, not a list of endpoints that will 404.
- **2026-02-08** | PSR-4 autoloading, `WP_LLM_Connector\` namespace | Avoids global function collisions, enables clean unit testing.

## Gotchas encountered

- **Two distinct auth flows in one plugin.** AI Providers tab = WordPress calls LLM APIs (**outbound**). API Keys tab + Connect Your AI Client = LLM clients call WordPress (**inbound**). These are opposite data flows and must not be conflated in UI copy or documentation. The AI Providers tab now has a prominent notice that states this.
- **`X-WP-LLM-API-Key` is the auth header** — not `X-API-Key`. Applies everywhere: admin JS, `API_Handler::check_permissions`, generated MCP config snippets, the `/mcp` manifest's `auth.header` field, and any future write endpoint. A mismatch silently breaks clients reading the manifest.
- **`X-WP-LLM-Write-Token` is the write-session header** — distinct from the API key header above. Both are required on write requests when they ship.
- **`git pull --rebase` on a branch that merged a long-lived feature branch is dangerous.** It tried to linearize 66 commits from v0.4.0-dev onto origin/main and stopped on a pre-divergence ancestor, effectively preparing to unwind the merge work. Use `git pull --no-rebase` (plain merge) for these reconciliations. Only rebase when the local branch is a strict linear descendant of origin.
- **`dbDelta()` on existing tables:** test `Activator::maybe_upgrade()` (or `create_or_upgrade_table()`) against pre-existing 1.0 / 2.0 schemas, not just a fresh install. `dbDelta` is conservative about column ordering and index definitions; fresh installs can hide a migration bug.
- **Transient TTL for plaintext key display** must outlive the admin page load. Post-merge it's `HOUR_IN_SECONDS` (v0.4.0-dev's behavior wins because the UX shows the new key inline in the table with a Copy button). Main's 0.1.3 hardening reduced it to 60s; that's incompatible with the inline-copy UX. Any future tightening needs a UX review.
- **`/mcp` authentication:** allowlist check only runs when `get_endpoint_slug()` returns non-false. `/mcp` isn't in `$endpoint_map`, so allowlist is skipped; auth still runs. Don't add `/mcp` to `$endpoint_map` unless you also want disabling it to lock clients out of discovery.
- **Write-tier fields on legacy keys** are backfilled at read time, not at upgrade time. A `get_option()` call that bypasses `validate_api_key()` will see raw records without the new fields — callers must either go through `validate_api_key()` or apply the defaults themselves.
- **`nav-tab-wrapper` vs. separate submenu:** the Access Log stays as a separate `add_submenu_page` rather than a tab inside the main page. This keeps the options-form and `WP_List_Table` cleanly separated; don't undo that without solving the form-wrapping-a-list-table awkwardness.
- **`render_providers_tab` uses its own form POST handler** (`handle_provider_settings`), not `options.php`. Main's v2.0.0 established that pattern because provider credential fields need their own sanitation pipeline. Don't fold provider fields back into the main `sanitize_settings` callback without understanding why they're separate.
- **Nested form HTML in General tab.** The log purge button inside the Logging row breaks out of the `options.php` form with `</form>` and reopens one below with `<form method="post" action="options.php">`. Main's 0.1.3 hardening introduced this workaround to avoid browser validation errors from nested `<form>` elements. Don't "fix" it by moving the purge button inline — you'll re-nest the forms.
- **Rate-limit TTL semantic mismatch.** Admin UI says "requests per hour" but the underlying transient TTL is 60 seconds. See Open task #4 — this needs a product decision before 2.2.
