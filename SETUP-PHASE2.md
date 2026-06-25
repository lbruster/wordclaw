# WordClaw Phase 2 — WPMU DEV Integration Setup

This guide covers installing and configuring the four Phase 2 skills:
Defender Alerts, Snapshot Backup Control, Hummingbird Reports, and Forminator Leads.

---

## Step 1 — Install the WordClaw Bridge plugin on each client site

The Bridge is a small WordPress plugin that exposes secure REST endpoints
for all Phase 2 skills. It must be installed on every site you want to connect.

### Install via WP-CLI (fastest for multiple sites)
```bash
# Copy the plugin to your site
scp wordclaw-bridge.php user@yourserver.com:/var/www/site/wp-content/plugins/wordclaw-bridge/wordclaw-bridge.php

# Activate it
wp plugin activate wordclaw-bridge --allow-root
```

### Or install manually via WordPress admin
1. Zip the `wordclaw-bridge` folder
2. Go to WordPress admin → Plugins → Add New → Upload Plugin
3. Upload `wordclaw-bridge.zip` and activate

### Set your secret key
Edit `wordclaw-bridge.php` and change:
```php
define( 'WORDCLAW_SECRET', 'CHANGE_THIS_TO_A_LONG_RANDOM_STRING' );
```

Or set it as a server environment variable (more secure):
```bash
# In your server's environment or wp-config.php:
define( 'WORDCLAW_SECRET', 'your-long-random-key-here' );
```

Generate a strong key:
```bash
openssl rand -hex 32
```

### Verify the bridge is working
```bash
curl -H "X-WordClaw-Key: YOUR_SECRET" https://yourclientsite.com/wp-json/wordclaw/v1/defender/issues
# Should return JSON, not a 403
```

---

## Step 2 — Save the bridge secret in OpenClaw

Send this to your WordClaw agent:
```
Remember: wordclaw bridge secret is YOUR_SECRET_KEY_HERE
```

WordClaw will save it to workspace memory as `wordclaw:bridge_secret`
and use it for all API calls automatically.

---

## Step 3 — Install Phase 2 skills

```bash
cp -r ./wordclaw/defender-alerts       ~/.openclaw/workspace/skills/
cp -r ./wordclaw/snapshot-backup       ~/.openclaw/workspace/skills/
cp -r ./wordclaw/hummingbird-reports   ~/.openclaw/workspace/skills/
cp -r ./wordclaw/forminator-leads      ~/.openclaw/workspace/skills/
```

Verify:
```bash
openclaw skills list
# Should now show all 7 WordClaw skills
```

---

## Step 4 — Update sites.md with Phase 2 flags

Tell your WordClaw agent which services are active on each site:
```
update monitoring for clientsite.com: defender yes, snapshot yes, hummingbird yes, forminator yes
```

Or edit `~/.openclaw/workspace/skills/site-monitor/sites.md` directly:
```
| Domain           | Client Name | Alert On                              | Added      |
|------------------|-------------|---------------------------------------|------------|
| example.com      | Acme Corp   | uptime,ssl,defender,snapshot,forminator | 2026-01-15 |
| store.client.com | Best Shop   | uptime,ssl,hummingbird,forminator     | 2026-01-20 |
```

---

## Step 5 — Register Forminator forms to watch

For each site using Forminator, discover and register forms:
```
discover forms on example.com
```

Then watch the ones that matter:
```
watch form 3 on example.com as "Contact Form"
watch form 7 on example.com as "Quote Request"
```

---

## Step 6 — Test each integration

### Test Defender
```
defender scan example.com
```
→ Should trigger a scan and confirm it started

```
defender status example.com
```
→ Should show scan progress or last results

### Test Snapshot
```
backup example.com now
```
→ Should trigger a backup and confirm

```
list backups example.com
```
→ Should show your backup history

### Test Hummingbird
```
performance report example.com
```
→ Should show desktop/mobile scores from the last Hummingbird test

```
clear cache example.com
```
→ Should flush the page cache

### Test Forminator
```
new leads example.com
```
→ Should show recent form entries (or "no leads yet" if none)

```
leads summary
```
→ Should show a cross-site overview

---

## Phase 2 command reference

| What you want | What to say |
|---|---|
| Run a Defender scan | `defender scan [domain]` |
| Check security issues | `defender status [domain]` |
| Get full security report | `defender report [domain]` |
| Backup a site now | `backup [domain] now` |
| Check backup progress | `backup status [domain]` |
| See all backups | `list backups [domain]` |
| Backup all sites | `backup all sites` |
| Performance score | `performance report [domain]` |
| Flush cache | `clear cache [domain]` |
| Monthly perf report | `monthly report for [client name]` |
| New leads today | `new leads [domain]` |
| All leads this week | `leads this week [domain]` |
| Cross-site lead summary | `leads summary` |
| Discover forms on a site | `discover forms on [domain]` |
| Watch a form for leads | `watch form [id] on [domain] as [label]` |
| Draft a lead reply | `draft reply to this lead` |

---

## Troubleshooting Phase 2

**"Bridge not responding" errors:**
```bash
# Verify plugin is active
wp plugin list --allow-root | grep wordclaw-bridge

# Test the endpoint directly
curl -H "X-WordClaw-Key: YOUR_KEY" https://yoursite.com/wp-json/wordclaw/v1/defender/issues
```

**Defender issues returning empty:**
- Make sure Defender has run at least one scan via the WP dashboard first
- Check that the scan result is stored: `wp option get wp_defender_scan_result --allow-root`

**Snapshot WP-CLI not working:**
```bash
# Test WP-CLI directly on the server
wp snapshot backup list --allow-root
```
If this fails, WP-CLI may not be available on the hosting — check with the host.

**Hummingbird performance data empty:**
- Open Hummingbird in WordPress admin → Performance → Run a test
- This populates the report data that WordClaw reads

**Forminator entries not appearing:**
- Verify the form ID is correct: `discover forms on [domain]`
- Check that entries exist in Forminator admin → Submissions
- Entries created before the bridge was installed may not appear (API reads from DB directly)

---

## Security notes

- The WordClaw Bridge uses a shared secret (`X-WordClaw-Key` header) — keep it private
- All endpoints return 403 if the key is missing or wrong
- Consider restricting the Bridge REST routes to known IP ranges via server firewall
  for extra security (OpenClaw agent will always call from a consistent IP)
- The Bridge plugin does not log requests — no audit trail is created on the WP side
- Rotate the secret key periodically: update in `wordclaw-bridge.php` and resave in OpenClaw memory

---

## Full WordClaw skills list (Phase 1 + 2)

| Skill | What it does | Required on site |
|-------|-------------|-----------------|
| `lead-audit` | Full site audit + pitch draft | Nothing |
| `site-monitor` | Uptime, SSL, speed monitoring | Nothing |
| `proposal-writer` | Proposal generation from chat | Nothing |
| `defender-alerts` | Security scan alerts | Bridge + Defender Pro |
| `snapshot-backup` | Backup control from chat | Bridge + Snapshot Pro |
| `hummingbird-reports` | Performance reports + cache clear | Bridge + Hummingbird Pro |
| `forminator-leads` | Lead notifications from forms | Bridge + Forminator Pro |
