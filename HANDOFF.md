# HANDOFF

Living session snapshot. Updated at the end of every significant work block. Read alongside [CLAUDE.md](CLAUDE.md).

## Current version & status

**Version:** 0.4.0-dev (last shipped: 0.3.0)
**Date:** 2026-04-21
**Status:** Git state resolved — all local 0.2.0 → 0.3.0 → 0.4.0-dev work is now committed on `v0.4.0-dev` branch and pushed to `origin`. `main` is untouched and still holds origin's v2.0.0 "providers" line. A PR description has been produced for the reviewer to paste into GitHub; merge strategy (this branch becomes trunk vs. providers ported onto it vs. cherry-pick) is the reviewer's call.

## What was just completed

- **Branch `v0.4.0-dev` created** from the previously detached `43aaec1` and pushed to `origin`. PR URL: `https://github.com/Sudo-WP/llm-connector-for-wp/pull/new/v0.4.0-dev`.
- **Single squashed commit** (`7bb34ca feat: v0.3.0 + v0.4.0-dev groundwork`) bundles everything that was sitting uncommitted on the detached HEAD — 19 files (13 modified + 6 new), +2523/−59. Covers the full span of work from this session and the three prior sessions (0.2.0 MCP config generator, 0.2.1 Access Log, 0.3.0 `/mcp` endpoint + docs, 0.4.0-dev write-tier groundwork).
- **Pre-commit audit** run per CLAUDE.md's Release workflow:
  - `git log origin/main -10` — confirmed origin is on the "v2.0.0 providers" line (`Anthropic_Provider.php`, `Gemini_Provider.php`, `OpenAI_Provider.php`, `Provider_Registry.php`, `LLM_Provider_Interface.php`, `Abilities_Manager.php`). Different product direction from ours.
  - `git diff --stat origin/main` — 23 files, ~4,640 lines; structural fork (origin adds `Providers/*` + `Abilities/*` subtrees we don't have; we add `Access_Log_Table.php`, `Write_Permission_Manager.php`, `/mcp` endpoint, governance docs origin doesn't have).
  - Secrets scan on `git diff origin/main` — only hits were placeholders (`YOUR_API_KEY_HERE`, `wpllm_your_api_key_here`, `prod_key_here`, `staging_key_here`), header names (`X-WP-LLM-API-Key`), documentation references to credentials. No live keys, no `sk-*`, no AWS keys, no Bearer tokens, no live URLs, no `wp-config` content.
- **PR description produced** for the reviewer. Covers: TL;DR of the divergence (inbound MCP vs. outbound providers), this branch's contents, reconciliation notes (file-level side-by-side of what each side has that the other doesn't), merge recommendation ("use this branch as the new trunk and port origin's Providers + Abilities on top as additive features" with caveats on version numbering and `SECURITY.md` updates if providers land), and a reviewer testing checklist (smoke, upgrade path, write-tier scaffold safety checks, security greps, merge-specific checks).
- **`HANDOFF.md`** — this update (you're reading it).

**Not run this session (intentional):** no version bump, no `readme.txt` changes, no `CHANGELOG.md` entry. This was a git-housekeeping session only. The plugin code is unchanged since the previous session.

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

1. **Review PR, decide on merge strategy with origin/main's providers line, merge to main. (M)** PR is open at `https://github.com/Sudo-WP/llm-connector-for-wp/pull/new/v0.4.0-dev`. Three viable paths (see PR description for full reasoning): (a) make `v0.4.0-dev` the new trunk and port origin's `Providers/*` + `Abilities/*` subtrees on top as additive features — recommended; (b) keep origin's v2.0.0 as trunk and cherry-pick MCP endpoint / Access Log / write-tier scaffold forward; (c) merge both with explicit conflict resolution in `Plugin.php` / `Admin_Interface.php`. Version reconciliation is a hard requirement either way — this branch's `Stable tag: 0.3.0` is lower than origin's `2.0.0` and would regress wp.org users if pushed as-is.
2. **Admin UI for per-key write flags and write-session tokens. (M)** Covers the per-key "Enable write access" checkbox, the scope checkboxes (posts / plugins / options / users / cache), the "Generate write session token" button wired to `Write_Permission_Manager::generate_write_token()`, and the red "Use on staging first" callout. See `docs/WRITE_TIER.md` §"UI changes needed". **Do not ship without also shipping a working write endpoint** — a UI that toggles flags no endpoint respects is a footgun.
3. **First write endpoint: `POST /write/cache/flush`. (S–M)** Lowest-risk v1 write — idempotent, no resource diff, `manage_options` capability. Use it to prove the full permission chain end to end, then move on to `POST /write/posts` and the rest. Fully implement `Write_Permission_Manager::authorize_write_request()` as part of this.
4. **Add `owner_user_id` to the per-key record.** (S) Capture `get_current_user_id()` at key generation; backfill to `0` on read for legacy keys. Required for `check_write_capability()` to run against the right user context rather than `current_user_can()` (which is `WP_User(0)` on REST requests).
5. Webhook support for proactive alerts. (M) Design first: outbound auth, retry policy, payload signing. Separate doc (`docs/WEBHOOKS.md`) before any code.
6. Saved filter presets in the Access Log panel. (S) Pure UI + options storage.
7. Test Connection in the admin should additionally fetch `/mcp` and validate the manifest shape. (S)
8. Custom endpoint builder (GUI). (L) Read-only endpoints only; no raw SQL; sandbox carefully.

## Decisions log

Reverse-chronological.

- **2026-04-21** | Local work landed on `v0.4.0-dev` branch, not force-pushed to `main` | Origin's v2.0.0 "providers" line is genuine upstream work representing a real (if divergent) product direction, not abandoned scratch. Destroying it with a force-push would be irreversible and would lose potentially-valuable code. Opening a PR from `v0.4.0-dev` → `main` puts the reconciliation decision in review, where it belongs.
- **2026-04-21** | Single commit for four sessions' worth of uncommitted work | The uncommitted pile covered 0.2.0 → 0.2.1 → 0.3.0 → 0.4.0-dev. Reconstructing the commit boundaries retroactively would fabricate history; a single honest "feat: v0.3.0 + v0.4.0-dev groundwork" commit with a commit-body manifest of which file changed for which reason is clearer than pretending clean increments existed all along.
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
- **Version numbering trap on merge:** this branch's `Stable tag` is `0.3.0` and `WP_LLM_CONNECTOR_VERSION` is `0.4.0-dev`. Origin/main's `Stable tag` is `2.0.0`. Any merge that lands on `main` and becomes a wp.org update **must** bump to `≥ 2.x` first, or existing v2.0.0 installs receive a "newer" version from wp.org that has a lower Stable tag — which most plugin update systems will refuse, confusingly. Port origin's 2.0.0 changelog entries forward alongside ours so `CHANGELOG.md` doesn't have a history gap.
