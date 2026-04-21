# Write Tier — Design Document

**Status:** Design. No write endpoints exist yet. This document is the authoritative spec for what the v1 write tier must look like before any route is registered.

**Audience:** Anyone about to implement `POST/PUT/DELETE` routes on this plugin. If you are about to add a write endpoint and haven't read this end-to-end, stop.

## Why this exists

The plugin's read tier enforces safety through a single principle: **the REST layer cannot mutate state**. Every route registers `'methods' => 'GET'`, and the permission model assumes that a bug anywhere in the auth chain still cannot corrupt the site. That's a small, auditable blast radius.

Write endpoints break that invariant. The moment we ship a `POST` route, a bug in auth — or a misconfiguration by the site owner — can trash a post, revoke a plugin, or flip an option that breaks `wp-admin`. We need the write tier designed with that failure mode first, not retrofitted after the fact.

The rest of this doc is what that design looks like.

## Scope

### In scope for v1

| # | Operation | Required capability | Notes |
|---|-----------|---------------------|-------|
| 1 | Create post / page (status `draft` or `publish`) | `edit_posts` (and `publish_posts` for `publish`) | Proxies `wp_insert_post()`. Sanitization via `wp_kses_post()`. |
| 2 | Update post / page (content, status, meta) | `edit_post` (per-post cap check) | `wp_update_post()`. Returns the before/after diff in the audit row. |
| 3 | Trash post / page (soft delete) | `delete_post` (per-post cap check) | Soft delete only. Hard delete is out of scope. |
| 4 | Activate / deactivate plugin | `activate_plugins` | `activate_plugin()` / `deactivate_plugins()`. Must check `is_plugin_active()` first and no-op if already in the target state. |
| 5 | Update WordPress option (allowlisted keys only) | `manage_options` + option on allowlist | Allowlist enforced in code, **not** settings. See "Option allowlist" below. |
| 6 | Flush rewrite rules / delete transients / flush object cache | `manage_options` | `flush_rewrite_rules()`, `delete_expired_transients()`, `wp_cache_flush()`. Idempotent. |
| 7 | Create user | `create_users` | `wp_insert_user()` with a generated password emailed to the new user. Role must be in a hard-coded allowlist (no `administrator` via this route). |
| 8 | Delete user | `delete_users` | `wp_delete_user()` with `$reassign` required. Refuse if target is the only administrator. |

### Option allowlist (v1)

Writable option keys. Anything not on this list returns `400 option_not_writable` regardless of capability.

```
blogname
blogdescription
default_comment_status
default_ping_status
default_pingback_flag
timezone_string
date_format
time_format
start_of_week
```

The allowlist lives as a constant in code. Extending it requires a code change + plugin release — it is not a settings-level toggle. Rationale: a runtime-editable allowlist is indistinguishable from "any option is writable" once it's in the hands of whoever can flip a checkbox in `wp-admin`.

### Out of scope for v1 (document why)

| Operation | Why not |
|-----------|---------|
| Filesystem writes (theme / plugin file edits, uploads, anything using `WP_Filesystem`) | Crosses the trust boundary out of the database and into code that runs on the next request. Needs a separate design pass covering sandboxing, backups, and a rollback story. |
| Database schema changes (`ALTER TABLE`, `CREATE TABLE` from the API) | Too easy to corrupt the site irrecoverably. If a use case emerges, it belongs behind a human-approved migration runner, not an API key. |
| WordPress core updates | Core upgrades must run through `wp-admin` so WordPress can handle maintenance mode, DB migrations, and rollback prompts. |
| Credential changes (user passwords, other plugin API keys, `wp_users.user_pass`) | Privilege escalation surface is too big. Password resets go through WordPress's existing flow. |
| `eval()`-style endpoints ("run this SQL", "run this PHP") | Permanent. Non-negotiable. |

## Permission model

Five gates. Every write request passes **all five** or is rejected. Failing at any gate logs to the audit table and returns a `4xx`.

### Gate 1 — Read-tier gate (reused)

`API_Handler::check_permissions()` still runs: enabled flag → API key → rate limit. The per-endpoint allowlist check is skipped for write routes (see "Endpoint naming convention" below — write endpoints aren't in `$endpoint_map`). Rate limit applies to write requests the same way it applies to reads.

### Gate 2 — Per-key write access

The API key used for the request must have `write_enabled: true`. This is a **per-key flag**, not a global toggle:

- A site owner enabling "global write mode" does not turn existing read-only keys into write-capable keys. Privilege escalation must be deliberate.
- A key with `write_enabled: false` attempting a write endpoint gets `403 write_not_enabled_for_key` — logged as a write-auth failure in the audit table.

### Gate 3 — Per-key scope

Each key also carries `write_scopes: ['posts', 'options', 'users', 'cache']` (any subset). The scope required for the endpoint must be in the key's list. Scope → endpoint mapping:

| Scope | Covers |
|-------|--------|
| `posts` | Create / update / trash posts and pages |
| `plugins` | Activate / deactivate plugins |
| `options` | Update allowlisted options |
| `users` | Create / delete users |
| `cache` | Flush rewrite rules, transients, object cache |

A key with `write_enabled: true` but `write_scopes: ['posts']` can draft a post but gets `403 scope_not_granted` when hitting `/write/options/blogname`.

### Gate 4 — One-time confirmation token

Every write request must include `X-WP-LLM-Write-Token`: a 32-byte random hex generated per-session in the admin UI.

- Token is stored as a transient keyed by its own SHA-256 hash, TTL **15 minutes**.
- Token is **single-use**: consumed (deleted) on the first request that successfully passes all gates. A second write in the same session requires a new token.
- Missing or expired token → `403 missing_or_expired_write_token`.
- Token present but not found in transients → `403 invalid_write_token`. Treated identically to expired, logged the same way.
- Tokens are generated from `Write_Permission_Manager::generate_write_token()` and are never stored in cleartext — only the hash of the token is persisted in the transient, just like the API key pattern.

Rationale for single-use: a leaked token is catastrophic, so the blast radius must be bounded to one write. Fifteen minutes is long enough for a CLI tool or AI assistant to build a request; short enough that the token doesn't live forever in `~/.bash_history` or a stale env var.

### Gate 5 — WordPress capability check

Map every endpoint to its required WP capability **in code**, never in settings. The capability check runs against the WordPress user tied to the API key (see "API key schema changes" for how users get associated with keys). Capability failures return `403 insufficient_capability`.

Endpoint → capability mapping is the same table as "In scope for v1" above. There is no inheritance: `administrator` gets everything implicitly via WordPress's capability map, but we never short-circuit the check based on role.

## Audit trail

Writes log to the same `wp_llm_connector_audit_log` table as reads. Schema does not need a bump — the existing columns cover it, with the convention below:

- `http_method`: `POST` / `PUT` / `DELETE` as appropriate.
- `endpoint`: write slug (e.g. `write_post_update`, `write_option_set`, `write_plugin_activate`).
- `response_code`: standard HTTP code.
- `request_data`: JSON-encoded request params **with sensitive fields redacted** — e.g. a `create_user` request must log the email + role but not the generated password.
- `request_data` additionally carries a `_diff` key on successful mutations: `{ "before": {...}, "after": {...} }` for posts and options. Plugin state changes log `{ "before": "inactive", "after": "active" }`. User creation logs only the created fields (no diff — there's nothing to compare to).

**Failure logging is mandatory** at every gate. A denied write is more operationally interesting than a successful one, and a missing failure row is a sign of a silent auth bypass.

Entries older than 90 days are still pruned by the existing daily cleanup cron; no change there.

## API key schema changes needed

Current per-key record shape (in `wp_llm_connector_settings.api_keys[<uuid>]`):

```php
array(
    'name'       => string,
    'key_hash'   => string,  // sha256
    'key_prefix' => string,  // first 12 chars of plaintext
    'created'    => int,     // unix ts
    'active'     => bool,
)
```

New fields required for the write tier:

```php
array(
    // ...existing fields...
    'write_enabled'  => bool,     // default false
    'write_scopes'   => string[], // subset of ['posts','plugins','options','users','cache']; default []
    'owner_user_id'  => int,      // WP user ID whose capabilities apply; default = key generator's user ID
)
```

### Migration path (no DB schema bump)

Keys live in an `options` row, not a custom table. Migration is forward-compatible and options-only:

1. `Security_Manager::validate_api_key()` backfills `write_enabled: false`, `write_scopes: []`, and `owner_user_id` from the current user (or `0` if none) **on read**, returning the enriched record.
2. The next time settings are written (any admin save, any new key, any revoke), the enriched shape is persisted.
3. No rewrite of existing rows at upgrade time. No `Activator::DB_VERSION` bump. No user action required.

Critically: **existing keys remain `write_enabled: false` forever** unless the site owner explicitly flips the flag in the admin. There is no code path that silently upgrades a read-only key into a write-capable one.

`owner_user_id` is required because the capability check needs a WP user context. Currently keys are created by whoever is logged in when they click "Generate"; we record that user ID at creation time for new keys, and backfill it at read time for old keys (defaulting to the generating admin's ID if available, else `0` which short-circuits all capability checks to `false`).

## Endpoint naming convention

Writes live under their own path prefix to make infrastructure-level enforcement trivial:

```
Read:   /wp-json/wp-llm-connector/v1/{resource}
Write:  /wp-json/wp-llm-connector/v1/write/{resource}
```

Concrete routes for v1:

```
POST   /wp-llm-connector/v1/write/posts
PUT    /wp-llm-connector/v1/write/posts/{id}
DELETE /wp-llm-connector/v1/write/posts/{id}

POST   /wp-llm-connector/v1/write/plugins/{slug}/activate
POST   /wp-llm-connector/v1/write/plugins/{slug}/deactivate

PUT    /wp-llm-connector/v1/write/options/{key}

POST   /wp-llm-connector/v1/write/cache/flush
POST   /wp-llm-connector/v1/write/rewrite/flush
POST   /wp-llm-connector/v1/write/transients/purge

POST   /wp-llm-connector/v1/write/users
DELETE /wp-llm-connector/v1/write/users/{id}
```

A site operator who wants to enforce read-only at the infrastructure level can drop a single rule:

```
# nginx
location ~ ^/wp-json/wp-llm-connector/v1/write/ {
    return 403;
}
```

The MCP manifest (`/mcp`) advertises write tools under a separate top-level array (TBD: `write_tools[]` or a `mode: "write"` flag per tool). Design to be finalized when the first write route is wired up.

## UI changes needed in `Admin_Interface.php`

Do **not** build these in the same PR as the permission scaffold. They depend on the scaffold being in place; they're listed here so the scaffold's shape doesn't paint the UI into a corner.

### Per-key row additions

Each row in the "Existing API Keys" table grows three new controls, all defaulting to off:

- **Enable write access** — single checkbox. Enabling it reveals the scope checkboxes. Disabling it hides and clears them.
- **Write scopes** — four checkboxes: Posts, Plugins, Options, Users, Cache. Disabled (greyed) while "Enable write access" is off.
- **Owner user** — dropdown of admin-capable users. Defaults to the key's current `owner_user_id`. Read-only for non-super-admins on multisite.

### New section — "Write session tokens"

Visible only when the currently selected key has `write_enabled: true`. Contents:

- A prominent red callout: **"Write operations modify your live site. Use on staging first. Every token is single-use and expires in 15 minutes."**
- A **Generate write session token** button. On click: calls `Write_Permission_Manager::generate_write_token()`, displays the plaintext token once (same pattern as API keys), then the token is never retrievable again.
- A **Copy token** button (same reveal/copy dance as the API key row).

### Audit filter additions

The Access Log panel gains:

- A new status filter option: **Writes only** (matches `http_method IN ('POST','PUT','DELETE')`).
- A new column — or maybe a toggle — to show/hide the `_diff` field inline.

## Non-goals for this design doc

- **Webhook emission on writes.** Out of scope; covered by the separate webhook design doc (future).
- **Bulk writes.** v1 is single-resource per request. Bulk is a v2 conversation.
- **Async / queued writes.** v1 is synchronous. If an operation takes >5s (e.g. plugin activation with a slow `init` hook), that's on the caller to handle via timeouts.
- **Multi-site network admin operations.** Scope is single-site for v1. Network-level writes need their own permission model.

## Implementation order

When the write tier is actually built, the order matters:

1. **This document, reviewed.** (You are here.)
2. `Write_Permission_Manager` scaffold with stubbed method bodies — shipped in 0.4.0-dev. Security-critical methods (`generate_write_token`, `validate_and_consume_token`) fully implemented; scope/capability methods stubbed with TODOs.
3. `Security_Manager` backfill of `write_enabled: false` on read, via `validate_api_key()` — shipped in 0.4.0-dev.
4. Admin UI for per-key toggles, generated token display, and scope checkboxes — *next session*.
5. First write route (`POST /write/cache/flush` — lowest-risk, idempotent, no diff logging needed) — *next session after UI*.
6. The rest of the write routes, one at a time, with each one wired through the same permission chain.

Any deviation from this order is a red flag. In particular: never ship a write route before the UI exists to scope keys — that creates a window where keys are either uniformly allowed or uniformly denied, neither of which is the right default.
