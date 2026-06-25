# WordClaw — SSH Access Setup Guide
## How to give OpenClaw WP-CLI access to your WPMU DEV hosted sites

---

## Why the Bridge plugin alone wasn't enough

The WordClaw Bridge REST endpoint calls `shell_exec()` to run WP-CLI —
but WPMU DEV hosting (and most managed hosts) **disables `shell_exec` in PHP**
for security reasons. The REST endpoint gets hit, but the command never runs.

The right solution is to give OpenClaw **direct SSH access** to each server,
so WP-CLI runs over SSH natively — no PHP involved at all.

WP-CLI has this built in via the `--ssh` flag:
```bash
wp plugin list --ssh=user@host/path/to/wordpress
```

This is exactly what we'll configure for WordClaw.

---

## Architecture: how it works after setup

```
You (WhatsApp/Telegram)
        ↓
   OpenClaw agent
        ↓
  wordclaw/v1/cli/ssh  ← new Bridge endpoint (just passes the SSH command)
        ↓  (OR)
  Direct SSH tunnel    ← OpenClaw SSHes into the server itself
        ↓
  WP-CLI on server     ← runs the actual command
        ↓
  Output back to chat
```

The cleanest approach for OpenClaw: **store SSH credentials in workspace memory**
and have the agent construct `wp --ssh=...` commands directly.

---

## STEP 1 — Create a dedicated SSH user on WPMU DEV Hub

Do this for EACH client site.

1. Log into **WPMU DEV Hub** → click your site → **Hosting** tab → **SFTP/SSH**
2. Click **Add User** → choose **SSH User**
3. Set:
   - **Username**: `wordclaw` (use the same username on every site for simplicity)
   - **Authentication**: choose **Public Key** (more secure, no password needed)
   - **Path Restriction**: `None` (WordClaw needs full WP-CLI access)
   - **Environment**: `Production`
4. Click **Save** — note down the **Connection Address (Host)** shown on the page

> ⚠️ WPMU DEV uses a unique host per site — it's NOT the domain name.
> It looks like: `ssh.wpmudev.host` or a unique subdomain.
> Copy it exactly from the Hub SFTP/SSH page.

---

## STEP 2 — Generate an SSH key pair for WordClaw

This key pair is what OpenClaw uses to authenticate. Generate it once on your
local machine (or any server you control):

```bash
# Generate a key with no passphrase (required for automated/programmatic use)
ssh-keygen -t ed25519 -C "wordclaw-openclaw" -f ~/.ssh/wordclaw_id -N ""

# View your public key (you'll paste this into WPMU DEV Hub)
cat ~/.ssh/wordclaw_id.pub

# View your private key (you'll paste this into OpenClaw memory — keep it secret)
cat ~/.ssh/wordclaw_id
```

> **Why no passphrase?** OpenClaw needs to connect programmatically without
> human input. A passphrase would block automated connections.

---

## STEP 3 — Add the public key to WPMU DEV Hub

1. In Hub → your site → **Hosting** → **SFTP/SSH** → click the `wordclaw` user → **Edit**
2. In the **Public Key** field, paste the full contents of `~/.ssh/wordclaw_id.pub`
   (starts with `ssh-ed25519 AAAA...`)
3. Click **Save / Update**
4. Test the connection from your terminal:

```bash
ssh -i ~/.ssh/wordclaw_id wordclaw@YOUR_WPMUDEV_HOST_ADDRESS

# Once connected, test WP-CLI:
wp core version

# Should output something like: 6.5.2
# Type 'exit' to disconnect
```

If that works — you're in. Repeat Steps 1–3 for each client site,
using the same public key each time (just add it to each site's SSH user).

---

## STEP 4 — Configure WP-CLI SSH aliases (wp-cli.yml)

WP-CLI supports named SSH aliases so you don't have to type full connection
strings every time. Create a `wp-cli.yml` file:

```bash
nano ~/.wp-cli/config.yml
```

Add an alias for each site:
```yaml
# ~/.wp-cli/config.yml

@acmecorp:
  ssh: wordclaw@ssh.acmecorp.wpmudev.host/home/wordclaw/public_html
  path: /home/wordclaw/public_html

@bestshop:
  ssh: wordclaw@ssh.bestshop.wpmudev.host/home/wordclaw/public_html
  path: /home/wordclaw/public_html

@clientthree:
  ssh: wordclaw@ssh.clientthree.wpmudev.host/home/wordclaw/public_html
  path: /home/wordclaw/public_html
```

Test an alias:
```bash
wp @acmecorp plugin list
# Should return the plugin list for acmecorp without prompting for a password
```

---

## STEP 5 — Save SSH credentials in OpenClaw workspace memory

Send this to WordClaw in chat (one message per site):

```
Remember SSH access for acmecorp.com:
- ssh_host: ssh.acmecorp.wpmudev.host
- ssh_user: wordclaw
- ssh_key: [paste the PRIVATE key contents here — the wordclaw_id file]
- wp_path: /home/wordclaw/public_html
- alias: @acmecorp
```

WordClaw saves this to workspace memory as:
```
ssh:acmecorp.com | host:ssh.acmecorp.wpmudev.host | user:wordclaw | path:/home/wordclaw/public_html
```

The private key is stored encrypted in the OpenClaw workspace.
It is never sent to any third party — only used to construct SSH connections.

---

## STEP 6 — Update the Bridge plugin (new SSH-aware CLI endpoint)

Replace the old `/cli/run` endpoint in `wordclaw-bridge.php` with this
improved version that uses SSH when available, with PHP fallback:

```php
register_rest_route( $ns, '/cli/run', [
    'methods'             => 'POST',
    'callback'            => 'wordclaw_cli_run_v2',
    'permission_callback' => '__return_true',
]);

function wordclaw_cli_run_v2( WP_REST_Request $req ): WP_REST_Response {
    if ( ! wordclaw_auth( $req ) ) return wordclaw_deny();

    $body = $req->get_json_params();
    $cmd  = trim( sanitize_text_field( $body['cmd'] ?? '' ) );

    if ( ! preg_match( '/^wp /', $cmd ) || preg_match( '/[;&|`$\(\)\{\}]/', $cmd ) ) {
        return new WP_REST_Response( ['error' => 'Invalid or unsafe command'], 400 );
    }

    // Try shell_exec (works on VPS/dedicated, fails on most shared/managed hosts)
    if ( function_exists('shell_exec') && ! in_array('shell_exec', array_map('trim', explode(',', ini_get('disable_functions')))) ) {
        $output = shell_exec( $cmd . ' --allow-root 2>&1' );
        return new WP_REST_Response([
            'method'  => 'shell_exec',
            'command' => $cmd,
            'output'  => trim( (string) $output ),
        ]);
    }

    // shell_exec disabled — tell OpenClaw to use SSH directly
    return new WP_REST_Response([
        'method'  => 'ssh_required',
        'command' => $cmd,
        'output'  => null,
        'note'    => 'shell_exec is disabled on this host. Use SSH mode — OpenClaw will run this command via SSH directly.',
    ], 503 );
}
```

When OpenClaw gets a `503 / ssh_required` response, it automatically
switches to SSH mode using the credentials stored in workspace memory.

---

## STEP 7 — How OpenClaw uses SSH mode

When running a WP-CLI command in SSH mode, the agent:

1. Looks up `ssh:{domain}` in workspace memory to get host, user, path
2. Writes the private key to a temp file: `/tmp/wordclaw_key_{random}`
3. Sets permissions: `chmod 600 /tmp/wordclaw_key_{random}`
4. Constructs the command:
   ```bash
   wp plugin list \
     --ssh=wordclaw@ssh.acmecorp.wpmudev.host/home/wordclaw/public_html \
     --key=/tmp/wordclaw_key_{random}
   ```
   Or using the alias if `wp-cli.yml` is configured:
   ```bash
   wp @acmecorp plugin list
   ```
5. Runs the command, captures output
6. Deletes the temp key file immediately
7. Returns the output to chat

---

## STEP 8 — Test it end to end

Once everything is set up, send these to WordClaw:

```
list plugins acmecorp.com
```
→ Should return the plugin list via SSH

```
WP version acmecorp.com
```
→ Should return the WordPress version

```
check core updates acmecorp.com
```
→ Should tell you if WP core has updates available

If it works — every WP-CLI command in the skill is now fully functional.

---

## SSH config file (optional but recommended)

Create `~/.ssh/config` to make connections faster and avoid key conflicts:

```
Host *.wpmudev.host
    User wordclaw
    IdentityFile ~/.ssh/wordclaw_id
    IdentitiesOnly yes
    StrictHostKeyChecking accept-new
    ConnectTimeout 10
```

With this in place, the SSH commands shorten to just:
```bash
ssh ssh.acmecorp.wpmudev.host
wp @acmecorp plugin list
```

---

## WPMU DEV specific: allowed SSH commands

WPMU DEV hosting has an integration module with WP-CLI that includes special hosting-specific commands. These work over your SSH connection in addition to standard WP-CLI:

```bash
# Clear all caches (WPMU DEV specific — better than wp cache flush alone)
wp hosting clearcache all

# Clear only static cache
wp hosting clearcache static

# Clear static cache for a specific page
wp hosting clearcache static \/

# View today's access logs
accesslog-view

# View logs from N days ago (1-7)
accesslog-view -7

# Restore a backup by ID
wp hosting backup restore {backup_id}

# List backups
wp hosting backup list
```

Add these to the WP-CLI Manager skill as extra commands available on WPMU DEV hosted sites.

---

## Troubleshooting

**"Permission denied (publickey)"**
```bash
# Check the key is being offered
ssh -v -i ~/.ssh/wordclaw_id wordclaw@YOUR_HOST 2>&1 | grep "Offering"
# If key isn't offered, check permissions:
chmod 600 ~/.ssh/wordclaw_id
chmod 644 ~/.ssh/wordclaw_id.pub
```

**"Host key verification failed"**
```bash
# Accept the host key on first connection
ssh-keyscan YOUR_WPMUDEV_HOST >> ~/.ssh/known_hosts
```

**WP-CLI not found on remote server**
```bash
# Check if wp is in PATH on the remote server
ssh wordclaw@YOUR_HOST "which wp"
# WPMU DEV hosting has WP-CLI pre-installed — it should be at /usr/local/bin/wp
```

**"shell_exec disabled" from Bridge endpoint**
- This is expected on WPMU DEV hosting — OpenClaw will automatically switch to SSH mode
- Make sure SSH credentials are saved in workspace memory (Step 5)

**SSH connection times out**
- Check the Connection Address from Hub — it's NOT your domain name
- It's a unique internal WPMU DEV host address from the SFTP/SSH page

---

## Security checklist

- [ ] Private key stored ONLY in OpenClaw workspace (not in plain text files)
- [ ] Public key added to WPMU DEV Hub (not the private key)
- [ ] Key has no passphrase (required for automation)
- [ ] SSH user has `None` path restriction (needed for WP-CLI)
- [ ] `~/.ssh/config` uses `IdentitiesOnly yes` (prevents key confusion)
- [ ] Temp key files deleted immediately after each command
- [ ] Rotate the SSH key every 90 days (generate new pair, update Hub + OpenClaw memory)

---

## Quick reference: memory format per site

```
ssh:acmecorp.com
  host: ssh.acmecorp.wpmudev.host
  user: wordclaw
  wp_path: /home/wordclaw/public_html
  alias: @acmecorp
  key: [encrypted private key]
  added: 2026-06-24
```

Send to WordClaw: `"show ssh sites"` to list all registered SSH connections.
Send to WordClaw: `"test ssh acmecorp.com"` to verify the connection is working.
