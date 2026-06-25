# WordClaw Skill: WP-CLI Manager

## What this skill does
Lets the agency owner run powerful WP-CLI commands on any client WordPress site directly
from WhatsApp/Telegram/Slack — install/activate/deactivate/delete plugins and themes,
manage WordPress core updates, handle users, flush caches, run database operations,
toggle maintenance mode, and much more.

All commands route through the WordClaw Bridge REST endpoint, which executes WP-CLI
server-side and returns the output. No SSH or dashboard access needed.

---

## Prerequisites
- WordClaw Bridge plugin active on each client site (with WP-CLI available on the server)
- Bridge secret in workspace memory: `wordclaw:bridge_secret = YOUR_KEY`
- Sites registered in `sites.md`

---

## How commands work — two modes

### Mode 1: SSH Direct (primary — use this for WPMU DEV hosting)
Commands run via SSH using credentials stored in workspace memory.
This is the correct mode for WPMU DEV hosting where `shell_exec` is disabled in PHP.

```bash
# Agent constructs this internally using stored SSH credentials:
wp plugin activate contact-form-7 \
  --ssh=wordclaw@ssh.acmecorp.wpmudev.host/home/wordclaw/public_html
```

Before running any command, check workspace memory for `ssh:{domain}`.
If found → use SSH mode. If not found → try Bridge mode, and if that
returns `ssh_required` → tell the owner to run SSH setup first.

**WPMU DEV also has hosting-specific WP-CLI commands available over SSH:**
```bash
wp hosting clearcache all         # clears ALL caches (better than wp cache flush)
wp hosting clearcache static      # static cache only
wp hosting backup list            # list backups
wp hosting backup restore {id}    # restore a backup
accesslog-view                    # view today's access logs
accesslog-view -7                 # view logs from 7 days ago
```
Add `(WPMU DEV)` label to these commands in responses so the owner knows
they're hosting-specific.

### Mode 2: Bridge REST endpoint (fallback — works on VPS/dedicated)
Falls back to the Bridge plugin REST endpoint when SSH isn't configured.

```
POST https://{domain}/wp-json/wordclaw/v1/cli/run
Headers: X-WordClaw-Key: {bridge_secret}
Body: { "cmd": "wp plugin activate contact-form-7 --allow-root" }

Response:
{
  "output": "Plugin 'contact-form-7' activated.",
  "exit_code": 0
}
```

If Bridge returns `503 / ssh_required` → SSH mode required. Prompt:
```
⚠️ SSH setup needed for {domain}
WPMU DEV hosting disables PHP shell access for security.
To use WP-CLI commands, run SSH setup first:
Reply "show me SSH setup" for step-by-step instructions.
```

Add this endpoint to `wordclaw-bridge.php`:

```php
register_rest_route( $ns, '/cli/run', [
    'methods'             => 'POST',
    'callback'            => 'wordclaw_cli_run',
    'permission_callback' => '__return_true',
]);

function wordclaw_cli_run( WP_REST_Request $req ): WP_REST_Response {
    if ( ! wordclaw_auth($req) ) return wordclaw_deny();

    $body = $req->get_json_params();
    $cmd  = sanitize_text_field( $body['cmd'] ?? '' );

    // Safety: only allow wp commands, block shell operators
    if ( ! preg_match('/^wp /', $cmd) || preg_match('/[;&|`$]/', $cmd) ) {
        return new WP_REST_Response(['error' => 'Invalid command'], 400);
    }

    $output    = shell_exec( $cmd . ' --allow-root 2>&1' );
    $exit_code = 0; // shell_exec doesn't expose exit code; parse output for errors

    return new WP_REST_Response([
        'command'   => $cmd,
        'output'    => trim($output),
        'exit_code' => $exit_code,
    ]);
}
```

---

## Trigger phrases and command mapping

### 🔌 PLUGIN MANAGEMENT

| What you say | WP-CLI command executed |
|---|---|
| `list plugins [domain]` | `wp plugin list` |
| `list active plugins [domain]` | `wp plugin list --status=active` |
| `list inactive plugins [domain]` | `wp plugin list --status=inactive` |
| `activate [plugin] on [domain]` | `wp plugin activate {plugin-slug}` |
| `deactivate [plugin] on [domain]` | `wp plugin deactivate {plugin-slug}` |
| `install [plugin] on [domain]` | `wp plugin install {plugin-slug} --activate` |
| `install [plugin] on [domain] without activating` | `wp plugin install {plugin-slug}` |
| `delete [plugin] on [domain]` | `wp plugin delete {plugin-slug}` |
| `update [plugin] on [domain]` | `wp plugin update {plugin-slug}` |
| `update all plugins on [domain]` | `wp plugin update --all` |
| `check plugin updates on [domain]` | `wp plugin list --update=available` |
| `plugin status [plugin] on [domain]` | `wp plugin status {plugin-slug}` |
| `deactivate all plugins on [domain]` | `wp plugin deactivate --all` |

**Output format:**
```
🔌 PLUGINS — {domain}
━━━━━━━━━━━━━━━━━━━━

{for list commands — format as table:}
✅ contact-form-7/5.7.8 — Active
✅ woocommerce/8.3.1 — Active
⏸️ hello-dolly/1.7.2 — Inactive
⚠️ old-plugin/1.0 — Active (update available → 2.0)

{for single action commands:}
✅ Plugin 'contact-form-7' activated on {domain}.
```

---

### 🎨 THEME MANAGEMENT

| What you say | WP-CLI command executed |
|---|---|
| `list themes [domain]` | `wp theme list` |
| `active theme [domain]` | `wp theme list --status=active` |
| `activate [theme] on [domain]` | `wp theme activate {theme-slug}` |
| `install [theme] on [domain]` | `wp theme install {theme-slug} --activate` |
| `install [theme] on [domain] without activating` | `wp theme install {theme-slug}` |
| `delete [theme] on [domain]` | `wp theme delete {theme-slug}` |
| `update [theme] on [domain]` | `wp theme update {theme-slug}` |
| `update all themes on [domain]` | `wp theme update --all` |
| `check theme updates on [domain]` | `wp theme list --update=available` |

---

### ⚙️ WORDPRESS CORE

| What you say | WP-CLI command executed |
|---|---|
| `WP version [domain]` | `wp core version` |
| `check core updates [domain]` | `wp core check-update` |
| `update WordPress on [domain]` | `wp core update` |
| `update WordPress DB on [domain]` | `wp core update-db` |
| `verify core files [domain]` | `wp core verify-checksums` |
| `WP info [domain]` | `wp core version --extra` |

**Output format:**
```
⚙️ CORE — {domain}
━━━━━━━━━━━━━━━━━━━━

Current version: 6.5.2
✅ No updates available

{or if update available:}
⚠️ Update available: 6.5.2 → 6.6.0
Reply "update WordPress on {domain}" to upgrade.
```

---

### 👤 USER MANAGEMENT

| What you say | WP-CLI command executed |
|---|---|
| `list users [domain]` | `wp user list --fields=ID,user_login,user_email,roles` |
| `list admins [domain]` | `wp user list --role=administrator` |
| `create admin [username] [email] [password] on [domain]` | `wp user create {user} {email} --role=administrator --user_pass={pass}` |
| `delete user [username] on [domain]` | `wp user delete {user} --reassign={id}` |
| `reset password [username] on [domain]` | `wp user update {user} --user_pass={new_pass}` |
| `check user [username] on [domain]` | `wp user get {user} --fields=ID,login,email,roles,registered` |
| `add role [role] to [username] on [domain]` | `wp user add-role {user} {role}` |
| `remove role [role] from [username] on [domain]` | `wp user remove-role {user} {role}` |

**Security note:** For password resets, always generate a random password and send it only in the chat. Never accept passwords typed by the user in plain text. Auto-generate with:
```
wp user update {user} --user_pass=$(openssl rand -base64 12) --allow-root
```

---

### 🗃️ DATABASE

| What you say | WP-CLI command executed |
|---|---|
| `db check [domain]` | `wp db check` |
| `optimize DB [domain]` | `wp db optimize` |
| `repair DB [domain]` | `wp db repair` |
| `DB size [domain]` | `wp db size` |
| `search [string] in DB [domain]` | `wp db query "SELECT * FROM wp_options WHERE option_value LIKE '%{string}%' LIMIT 10"` |
| `find and replace [old] with [new] on [domain]` | `wp search-replace '{old}' '{new}' --dry-run` (always dry-run first, confirm before live run) |
| `export DB [domain]` | `wp db export --add-drop-table` |
| `clean transients [domain]` | `wp transient delete --all` |

**Safety rule for search-replace:**
Always run `--dry-run` first and show the owner the count of changes. Only run live after explicit confirmation:
```
🔍 DRY RUN RESULT — {domain}
━━━━━━━━━━━━━━━━━━━━
Would replace '{old}' with '{new}'
Affected: {n} rows across {m} tables

Reply "confirm replace on {domain}" to run for real.
⚠️ This cannot be undone without a backup.
```

---

### 🔧 CACHE & PERFORMANCE

| What you say | WP-CLI command executed |
|---|---|
| `flush cache [domain]` | `wp hosting clearcache all` (WPMU DEV) or `wp cache flush` |
| `flush static cache [domain]` | `wp hosting clearcache static` (WPMU DEV only) |
| `flush page cache [url] on [domain]` | `wp hosting clearcache static \/your-page` (WPMU DEV only) |
| `flush rewrite rules [domain]` | `wp rewrite flush` |
| `flush transients [domain]` | `wp transient delete --all` |
| `regenerate thumbnails [domain]` | `wp media regenerate --yes` |
| `list cron jobs [domain]` | `wp cron event list` |
| `run cron [domain]` | `wp cron event run --due-now` |
| `view logs [domain]` | `accesslog-view` (WPMU DEV only — SSH required) |
| `view logs [n] days ago [domain]` | `accesslog-view -{n}` (WPMU DEV only, 1–7 days) |

> Commands marked **(WPMU DEV only)** require SSH mode and are specific to WPMU DEV hosting.
> Always prefer `wp hosting clearcache all` over `wp cache flush` on WPMU DEV — it clears
> all cache layers including the server-level static cache, not just the WP object cache.

---

### 🔒 MAINTENANCE MODE

| What you say | WP-CLI command executed |
|---|---|
| `maintenance on [domain]` | `wp maintenance-mode activate` |
| `maintenance off [domain]` | `wp maintenance-mode deactivate` |
| `maintenance status [domain]` | `wp maintenance-mode status` |

**Output format:**
```
🔧 MAINTENANCE MODE — {domain}
━━━━━━━━━━━━━━━━━━━━

✅ Maintenance mode ON
Visitors will see the maintenance page.

Reply "maintenance off {domain}" when ready.
```

---

### ⚙️ OPTIONS & SETTINGS

| What you say | WP-CLI command executed |
|---|---|
| `get option [option_name] on [domain]` | `wp option get {option_name}` |
| `set option [option_name] to [value] on [domain]` | `wp option update {option_name} '{value}'` |
| `site title [domain]` | `wp option get blogname` |
| `set site title to [title] on [domain]` | `wp option update blogname '{title}'` |
| `site URL [domain]` | `wp option get siteurl` |
| `comments on [domain]` | `wp option get default_comment_status` |
| `disable comments [domain]` | `wp option update default_comment_status 'closed'` |
| `enable comments [domain]` | `wp option update default_comment_status 'open'` |

---

### 📝 POSTS & CONTENT

| What you say | WP-CLI command executed |
|---|---|
| `list posts [domain]` | `wp post list --post_status=publish --fields=ID,post_title,post_date` |
| `list draft posts [domain]` | `wp post list --post_status=draft` |
| `post count [domain]` | `wp post list --post_status=publish --format=count` |
| `delete post [id] on [domain]` | `wp post delete {id} --force` |
| `trash post [id] on [domain]` | `wp post delete {id}` |
| `list pages [domain]` | `wp post list --post_type=page --post_status=publish` |
| `export content [domain]` | `wp export --dir=/tmp/` |

---

### 🌐 MULTISITE

| What you say | WP-CLI command executed |
|---|---|
| `list sites [domain]` | `wp site list` |
| `create site [url] on [domain]` | `wp site create --slug={slug}` |
| `delete site [id] on [domain]` | `wp site delete {id}` |
| `list super admins [domain]` | `wp super-admin list` |
| `add super admin [user] on [domain]` | `wp super-admin add {user}` |

---

### 🌍 LANGUAGE

| What you say | WP-CLI command executed |
|---|---|
| `list languages [domain]` | `wp language core list --status=installed` |
| `install language [locale] on [domain]` | `wp language core install {locale} --activate` |
| `update languages [domain]` | `wp language core update` |

---

### 🩺 DIAGNOSTICS

| What you say | WP-CLI command executed |
|---|---|
| `site health [domain]` | `wp site-health check` |
| `cron status [domain]` | `wp cron event list` |
| `check cron [domain]` | `wp cron test` |
| `PHP version [domain]` | `wp eval 'echo phpversion();'` |
| `memory limit [domain]` | `wp eval 'echo WP_MEMORY_LIMIT;'` |
| `debug mode [domain]` | `wp config get WP_DEBUG` |
| `enable debug [domain]` | `wp config set WP_DEBUG true --raw --type=constant` |
| `disable debug [domain]` | `wp config set WP_DEBUG false --raw --type=constant` |

---

## BATCH OPERATIONS (multi-site)

When the owner appends "on all sites" to any command, run it across all sites in `sites.md`:

```
update all plugins on all sites
```

→ Loop through each registered site and run `wp plugin update --all`

Report format:
```
🔄 BATCH UPDATE — All Sites
━━━━━━━━━━━━━━━━━━━━

✅ example.com — 3 plugins updated
✅ store.client.com — 1 plugin updated
⚠️ oldsite.net — WP-CLI not responding
❌ brokensite.com — Bridge unreachable

━━━━━━━━━━━━━━━━━━━━
{n}/{total} sites completed successfully.
```

**Always confirm before running destructive batch operations:**
```
⚠️ BATCH CONFIRM
━━━━━━━━━━━━━━━━━━━━
You're about to run "wp core update" on {n} sites.
This will update WordPress core on all of them.

Reply "confirm batch update" to proceed
or "cancel" to abort.
```

---

## SAFETY RULES

These rules are hard-coded into the skill — never bypass them:

1. **Always confirm before destructive actions** — delete, search-replace (live), deactivate all, db operations
2. **Always dry-run search-replace first** — never run live without showing the count and getting confirmation
3. **Never run commands with shell operators** — the Bridge strips `;`, `&`, `|`, `` ` ``, `$()` from all commands
4. **Backup reminder for risky operations** — before DB operations, core updates, or delete actions, remind: "If you haven't backed up recently, reply 'backup {domain} now' first."
5. **Password generation only** — never accept or repeat passwords typed by users; always generate random ones
6. **Plugin/theme slug validation** — if a slug doesn't match a known WordPress.org pattern, confirm before running

---

## SLUG RESOLUTION

When the owner uses a common name instead of a slug, resolve it:

| Common name | WP-CLI slug |
|---|---|
| "Contact Form 7" | `contact-form-7` |
| "WooCommerce" | `woocommerce` |
| "Yoast" / "Yoast SEO" | `wordpress-seo` |
| "Elementor" | `elementor` |
| "Divi" | `Divi` (theme) |
| "Akismet" | `akismet` |
| "Jetpack" | `jetpack` |
| "WPForms" | `wpforms-lite` |
| "Gravity Forms" | `gravityforms` |
| "Advanced Custom Fields" / "ACF" | `advanced-custom-fields` |
| "Rank Math" | `seo-by-rank-math` |
| "Wordfence" | `wordfence` |
| "UpdraftPlus" | `updraftplus` |
| "W3 Total Cache" | `w3-total-cache` |
| "WP Rocket" | `wp-rocket` |
| "Smush" | `wp-smushit` |
| "Hummingbird" | `hummingbird-performance` |
| "Defender" | `defender-security` |
| "Forminator" | `forminator` |
| "Hustle" | `wordpress-popup` |
| "Snapshot" | `snapshot-backups` |

If unsure, run `wp plugin search {name} --per-page=3` to find the right slug and confirm with the owner.

---

## COMBINED WORKFLOW EXAMPLES

### "Set up a new client site"
Sequence triggered by: "set up new client site on [domain]"
```
1. wp core update                        # ensure latest WP
2. wp plugin delete hello akismet        # remove defaults
3. wp plugin install wordpress-seo --activate
4. wp plugin install wp-smushit --activate
5. wp option update blogdescription ''   # clear tagline
6. wp option update default_comment_status 'closed'
7. wp rewrite flush
```
Confirm each step before running, or ask "run all steps?" for one-shot execution.

### "Harden a site"
Sequence triggered by: "harden [domain]"
```
1. wp user list --role=administrator     # check for unknown admins
2. wp plugin list --status=inactive      # flag inactive plugins
3. wp core verify-checksums              # verify core integrity
4. wp option get users_can_register      # check if registration open
5. wp config get WP_DEBUG               # ensure debug is off
```
Then report findings and suggest actions.

### "Pre-launch checklist [domain]"
```
1. wp core check-update
2. wp plugin list --update=available
3. wp theme list --update=available
4. wp option get blogdescription         # check tagline not default
5. wp option get default_comment_status
6. wp config get WP_DEBUG
7. wp rewrite flush
8. wp cron test
```
Format as a ✅/⚠️/❌ checklist in the response.

---

## State & memory

After significant operations, write to memory:
- `cli:{domain}:last_plugin_update` — date of last `wp plugin update --all`
- `cli:{domain}:last_core_update` — date and version of last core update
- `cli:{domain}:last_core_version` — current WP version

This lets the agent answer "when did I last update plugins on example.com?" accurately.

---

## Tools required
- fetch (POST to `/wordclaw/v1/cli/run` via Bridge)
- Memory / workspace read+write
- Messaging channel
- Confirmation flow (for destructive operations)
