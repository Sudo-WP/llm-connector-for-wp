# HANDOFF

Living session snapshot. Updated at the end of every significant work block. Read alongside [CLAUDE.md](CLAUDE.md).

## Current version & status

**Version:** 0.4.0-dev (last shipped: 0.3.0)
**Date:** 2026-04-21
**Status:** Write-tier groundwork in place — design doc, permission scaffold, and API-key schema additions. **No write endpoints are exposed in this build.** `authorize_write_request()` fails closed with a `503`, so any accidental early wiring of a write route is a safe-fail.

## What was just completed

- **`docs/WRITE_TIER.md`** — new. Authoritative design document for the premium write tier. Covers in/out-of-scope operations, the option allowlist (hard-coded, not settings-driven), the five permission gates (read-tier → per-key write flag → per-key scopes → one-time token → WP capability), audit-trail shape (`_diff` convention on success; mandatory failure logging), API key schema additions, write-route naming convention (`/wp-llm-connector/v1/write/*` — a single firewall rule enforces read-only at the infrastructure level), UI changes needed in `Admin_Interface.php`, and an explicit implementation order.
- **`includes/Security/Write_Permission_Manager.php`** — new. Scaffold class. Security-critical methods fully implemented:
  - `generate_write_token()`: 64-char hex from `random_bytes(32)`; stored as a SHA-256-hashed transient (`wp_llm_connector_write_token_<hash>`) with a 15-minute TTL.
  - `validate_and_consume_token()`: length + charset guard before any storage hit; `delete_transient()` on successful validation so single-use semantics hold across concurrent replays.
  - `key_has_write_access()`: options-table lookup by `key_hash`, requires `active: true` **and** `write_enabled: true`.
  - `check_write_capability()` and `authorize_write_request()` stubbed with TODOs. `authorize_write_request()` returns a `WP_Error` (`503 write_tier_not_implemented`) so a half-wired write endpoint fails closed.
- **`includes/Admin/Admin_Interface.php`** — new keys created through `handle_api_key_actions()` now carry `write_enabled: false` and `write_scopes: []`. `sanitize_settings()` preserves both fields through settings round-trips and whitelists `write_scopes` against `[posts, plugins, options, users, cache]`.
- **`includes/Security/Security_Manager.php`** — `validate_api_key()` now runs every matching key through `apply_write_tier_defaults()`, which backfills `write_enabled: false` / `write_scopes: []` on read. Forward-compatible migration — no DB version bump, no activation hook, no upgrade step required. Legacy (pre-0.4.0) keys are returned to callers with closed write flags regardless of storage state.
- **`CLAUDE.md`** — new permanent **Release workflow** section: smoke-test checklist (`/health`, `/site-info`, `/mcp`, admin settings, Access Log), the ordered release routine (lint → smoke → version bump in the correct file order → HANDOFF update → git status → commit → push → verify), and the hard-rules list (no credentials / live URLs / `wp-config.php` in commits, no `--no-verify`, no force-push to `main` without explicit per-push approval). Version line updated to reflect 0.4.0-dev / last-shipped 0.3.0. Repo-layout block updated to list `Write_Permission_Manager.php` and `docs/WRITE_TIER.md`.
- **`CHANGELOG.md`** — new `## [Unreleased]` section at the top covering all of the above. `readme.txt` Stable tag intentionally stays at `0.3.0` until the write tier ships.
- **Version bump:** `wp-llm-connector.php` header + `WP_LLM_CONNECTOR_VERSION` constant → `0.4.0-dev`.
- **Lint:** `php -l` clean on every new / changed PHP file; `node --check` clean on `assets/js/admin.js` (no JS changes this session, verified anyway).

## State of each major subsystem

**REST API (`includes/API/API_Handler.php`)**
Unchanged this session. Eight routes: six data endpoints, `/health` (public), `/mcp` (auth-gated manifest). All go through `check_permissions()`. Request timer via `rest_pre_dispatch` for execution_time_ms. Read-only tier is feature-complete for the 0.x line; the 0.4.x line adds write routes under `/wp-llm-connector/v1/write/*` per the spec in `docs/WRITE_TIER.md`.

**Security / Auth (`includes/Security/Security_Manager.php`)**
SHA-256 key hashing, constant-time compare, 1-hour rate-limit window with no TTL reset on increment, full audit logging with schema 1.1. `validate_api_key()` now enriches every returned record with `write_enabled` / `write_scopes` defaults. No DB changes needed for the migration.

**Write permission scaffold (`includes/Security/Write_Permission_Manager.php`)** (NEW)
Token generation and validation are production-ready. Scope / capability / authorize methods are stubbed and default closed. When you build the first write endpoint next session, the work is: (1) flesh out `authorize_write_request()` to run the five gates in order, (2) wire `check_write_capability()` to the per-key `owner_user_id` once that field is added, (3) add the admin UI described in `docs/WRITE_TIER.md` §"UI changes needed".

**Admin UI (`includes/Admin/Admin_Interface.php`)**
Unchanged in terms of user-facing UX this session. Plumbing for per-key write-tier fields is in place (defaults on creation, preservation through settings save). The "Enable write access" toggle, write-scope checkboxes, and "Generate write session token" button are **next session's work** — do not build them without also building a working write endpoint behind the same flag, per the implementation-order note in `docs/WRITE_TIER.md`.

**Access Log (`includes/Admin/Access_Log_Table.php`)**
Unchanged this session. Still the single source of truth for log filter semantics via `build_where_clause()`. When write endpoints ship, add a "Writes only" status filter and a column toggle for the `_diff` field per the design doc.

**MCP config generator (`Admin_Interface::render_connect_client_section()` + `assets/js/admin.js`)**
Unchanged this session. Tracks `X-WP-LLM-API-Key` and the `/mcp` manifest correctly.

## Open tasks (prioritised)

1. **Admin UI for per-key write flags and write-session tokens. (M)** Covers the per-key "Enable write access" checkbox, the scope checkboxes (posts / plugins / options / users / cache), the "Generate write session token" button wired to `Write_Permission_Manager::generate_write_token()`, and the red "Use on staging first" callout. See `docs/WRITE_TIER.md` §"UI changes needed". **Do not ship without also shipping a working write endpoint** — a UI that toggles flags no endpoint respects is a footgun.
2. **First write endpoint: `POST /write/cache/flush`. (S–M)** Lowest-risk v1 write — idempotent, no resource diff, `manage_options` capability. Use it to prove the full permission chain end to end, then move on to `POST /write/posts` and the rest. Fully implement `Write_Permission_Manager::authorize_write_request()` as part of this.
3. **Add `owner_user_id` to the per-key record.** (S) Capture `get_current_user_id()` at key generation; backfill to `0` on read for legacy keys. Required for `check_write_capability()` to run against the right user context rather than `current_user_can()` (which is `WP_User(0)` on REST requests).
4. Webhook support for proactive alerts. (M) Design first: outbound auth, retry policy, payload signing. Separate doc (`docs/WEBHOOKS.md`) before any code.
5. Saved filter presets in the Access Log panel. (S) Pure UI + options storage.
6. Test Connection in the admin should additionally fetch `/mcp` and validate the manifest shape. (S)
7. Custom endpoint builder (GUI). (L) Read-only endpoints only; no raw SQL; sandbox carefully.

## Decisions log

Reverse-chronological.

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

- **`X-WP-LLM-API-Key` is the auth header** — not `X-API-Key`. Applies everywhere: admin JS, `API_Handler::check_permissions`, generated MCP config snippets, the `/mcp` manifest's `auth.header` field, and any future write endpoint. A mismatch silently breaks clients reading the manifest.
- **`X-WP-LLM-Write-Token` is the write-session header** — distinct from the API key header above. Both are required on write requests.
- **`dbDelta()` on existing tables:** test `Activator::maybe_upgrade()` against a **pre-existing 1.0 schema**, not just a fresh install. `dbDelta` is conservative about column ordering and index definitions; fresh installs can hide a migration bug.
- **Transient TTL for plaintext key display** must outlive the admin page load. Currently `HOUR_IN_SECONDS` for API keys, 15 minutes for write tokens; do not reduce without a UX review.
- **`/mcp` authentication:** allowlist check only runs when `get_endpoint_slug()` returns non-false. `/mcp` isn't in `$endpoint_map`, so allowlist is skipped; auth still runs. Don't add `/mcp` to `$endpoint_map` unless you also want disabling it to lock clients out of discovery.
- **Write-tier fields on legacy keys** are backfilled at read time, not at upgrade time. A `get_option()` call that bypasses `validate_api_key()` will see raw records without the new fields — callers must either go through `validate_api_key()` or apply the defaults themselves.
- **`nav-tab-wrapper` vs. separate submenu:** we kept a separate `add_submenu_page` for the Access Log rather than tabs inside the main page. Don't undo that without solving the form-wrapping-a-list-table awkwardness.
