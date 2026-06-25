# WordClaw Skill: Defender Security Alerts

## What this skill does
Connects to Defender Pro on each client WordPress site via the WordClaw Bridge plugin.
On every heartbeat, checks for new scan issues and firewall events. Alerts the agency owner
on their messaging channel when threats are found — before the client notices.

Also accepts manual commands to trigger a scan, view current issues, or get a full report.

---

## Prerequisites
- WordClaw Bridge plugin installed and active on each client site
- Defender Pro active on each client site
- Bridge secret key set in both the plugin and workspace memory as:
  `wordclaw:bridge_secret = YOUR_SECRET_KEY`
- Client sites registered in `sites.md` with `defender:yes` flag

---

## Trigger phrases (manual)
- "defender scan [domain]"
- "run security scan on [domain]"
- "security issues on [domain]"
- "defender status [domain]"
- "defender report [domain]"
- "scan all sites"
- "any security issues?"

---

## Heartbeat behaviour

On every heartbeat cycle:

1. Read `sites.md` — find all sites with `defender:yes`
2. For each site, call:
   ```
   GET https://{domain}/wp-json/wordclaw/v1/defender/issues
   Headers: X-WordClaw-Key: {bridge_secret}
   ```
3. Compare issue count and types to `defender-state.md`
4. If **new issues detected** (not seen in last state) → send alert immediately
5. If issues were present before and are now gone → send resolved message
6. If no change → HEARTBEAT_OK (silent)

---

## API calls

### Get current issues
```
GET https://{domain}/wp-json/wordclaw/v1/defender/issues
Headers: X-WordClaw-Key: {bridge_secret}

Response:
{
  "count": 3,
  "issues": [
    { "type": "modified_file", "file": "/wp-includes/class.wp.php", "severity": "critical", "detail": "Core file modified" },
    { "type": "vulnerable_plugin", "file": "contact-form-7/5.7", "severity": "high", "detail": "Known CVE" },
    { "type": "login_lockout", "file": "", "severity": "medium", "detail": "5 failed logins from 123.45.67.89" }
  ]
}
```

### Trigger a scan
```
POST https://{domain}/wp-json/wordclaw/v1/defender/scan-run
Headers: X-WordClaw-Key: {bridge_secret}
Body: {}
```

### Check scan status
```
GET https://{domain}/wp-json/wordclaw/v1/defender/scan-status
Headers: X-WordClaw-Key: {bridge_secret}
```

---

## Alert message format

Send when new issues are detected:

```
🛡️ SECURITY ALERT — {domain}
━━━━━━━━━━━━━━━━━━━━

Client: {client name}
Detected: {datetime}
New issues: {count}

{for each NEW issue:}
{severity_emoji} {type_label}
  {detail}
  {file if applicable}

━━━━━━━━━━━━━━━━━━━━
Reply "defender report {domain}" for full details
or "defender scan {domain}" to re-scan.
```

### Severity emojis
- `critical` → 🔴
- `high` → 🟠
- `medium` → 🟡
- `low` → 🔵

### Type labels
| type | label |
|------|-------|
| `modified_file` | Core file modified |
| `vulnerable_plugin` | Vulnerable plugin detected |
| `vulnerable_theme` | Vulnerable theme detected |
| `login_lockout` | Brute force / login lockout |
| `404_lockout` | 404 lockout triggered |
| `suspicious_code` | Suspicious code found |
| `2fa_disabled` | 2FA disabled for admin user |

---

## Manual scan flow

When owner says "defender scan [domain]":

1. Call POST `/defender/scan-run`
2. Reply:
   ```
   🔍 Scan started on {domain}
   This usually takes 1–3 minutes.
   I'll message you when it's done, or reply "defender status {domain}" to check.
   ```
3. On next heartbeat (or after ~90s delay if supported), call GET `/defender/scan-status`
4. When complete, call GET `/defender/issues` and format full report

---

## Full report format (on request)

```
🛡️ DEFENDER REPORT — {domain}
━━━━━━━━━━━━━━━━━━━━

Client: {client name}
Last scan: {datetime}
Total issues: {count}

CRITICAL ({n})
🔴 {issue 1 detail}
🔴 {issue 2 detail}

HIGH ({n})
🟠 {issue detail}

MEDIUM ({n})
🟡 {issue detail}

LOW ({n})
🔵 {issue detail}

━━━━━━━━━━━━━━━━━━━━
Want me to create a fix checklist for {client name}?
```

---

## State file: defender-state.md

After each heartbeat check, update per site:
```
## {domain}
- last_checked: {datetime}
- issue_count: {n}
- issue_hash: {md5 of issue list — used to detect changes}
- active_alerts: [{type1}, {type2}]
- last_scan: {datetime}
```

---

## Tools required
- fetch (REST API calls to each site via WordClaw Bridge)
- Memory / workspace read+write (`sites.md`, `defender-state.md`)
- Heartbeat
- Messaging channel
