# WordClaw Skill: Site Monitor

## What this skill does
Proactively monitors a list of client WordPress sites stored in the workspace. Runs on a heartbeat (every 30–60 minutes by default) and alerts the agency owner via their messaging channel when something goes wrong — before the client notices.

Also responds to manual commands to check a specific site or view the full status dashboard.

---

## Trigger phrases (manual)
- "check all sites"
- "site status"
- "is [domain] up?"
- "monitor status"
- "dashboard"
- "add [domain] to monitoring"
- "remove [domain] from monitoring"
- "check [domain]"

---

## Client site registry
Sites are stored in `sites.md` in the workspace. Format:

```
# Monitored Sites

| Domain | Client Name | Alert On | Added |
|--------|-------------|----------|-------|
| example.com | Acme Corp | uptime,ssl,plugins | 2026-01-15 |
| store.client.com | Best Shop | uptime,ssl | 2026-01-20 |
```

When the owner says "add [domain] to monitoring", append a new row. When they say "remove [domain]", delete the row and confirm.

---

## Heartbeat behaviour
This skill runs automatically on every heartbeat cycle. On each run:

1. Read the site list from `sites.md`
2. For each site, run the checks below
3. Compare results to the last known state in `monitor-state.md`
4. Only send an alert if something has **changed** (new issue detected) — never spam repeat alerts for the same ongoing issue
5. If everything is fine across all sites, respond with HEARTBEAT_OK (silent)

---

## Checks to run per site

### Uptime check
```
GET https://{domain}/ — timeout 10s
```
- If response code is 200–399: site is UP
- If 4xx/5xx or timeout: site is DOWN
- Record response time in ms

### SSL certificate check
```
Fetch https://{domain}/ and inspect TLS headers
```
Check:
- Certificate is valid (not expired)
- Certificate expiry date — alert if expiring within 14 days
- HTTPS redirect working (HTTP → HTTPS)

### WordPress core / plugin vulnerability signals
```
GET https://{domain}/wp-json/wp/v2/ 
```
- If the REST API is open, check the WordPress version in the response header (`X-WP-Total`, generator tags)
- Cross-reference with known vulnerable versions (maintain a short list of critical CVEs in skill memory or note to check wpvulndb.com manually)

### Login page exposure check (once per day only — not every heartbeat)
```
GET https://{domain}/wp-login.php
```
- 200 response = exposed (alert once, then silence until resolved)

### Page load time
- Record the response time from the uptime check
- Alert if load time exceeds 5 seconds (likely server issue, not just slow site)

---

## Alert message format

Send immediately when an issue is detected:

```
🚨 SITE ALERT — {domain}
━━━━━━━━━━━━━━━━━━━━

Client: {client name}
Issue: {issue type}
Detected: {time}

{issue-specific detail below}

━━━━━━━━━━━━━━━━━━━━
Reply "check {domain}" for full status or "silence {domain} 2h" to pause alerts.
```

### Issue-specific detail blocks:

**Site down:**
```
❌ SITE DOWN
Status: {HTTP code or "No response / timeout"}
Down since: {first detected time}
Last seen up: {previous successful check time}
```

**SSL expiring:**
```
⚠️ SSL EXPIRING SOON
Expires: {date} ({n} days left)
Action needed: Renew certificate before {date}
```

**SSL invalid/expired:**
```
🔴 SSL CERTIFICATE INVALID
Visitors will see a browser security warning.
This is urgent — contact hosting or renew immediately.
```

**Slow response:**
```
🐢 SLOW RESPONSE TIME
Load time: {n}ms (threshold: 5000ms)
This may indicate a server or hosting issue.
```

---

## Manual status dashboard
When the owner asks "site status" or "dashboard", reply with a full overview:

```
📡 SITE MONITOR DASHBOARD
{datetime}
━━━━━━━━━━━━━━━━━━━━

✅ example.com — UP (212ms) | SSL OK (87d left)
✅ store.client.com — UP (380ms) | SSL OK (42d left)
⚠️ agency.client.net — UP (4820ms) | ⚠️ Slow response
❌ oldclient.com — DOWN since 14:23 | SSL OK

━━━━━━━━━━━━━━━━━━━━
{n} sites monitored | {n} issues active
Reply "check [domain]" for details on any site.
```

---

## State file: monitor-state.md
After every heartbeat run, update `monitor-state.md` with:

```
# Monitor State — last updated: {datetime}

## example.com
- status: up
- last_checked: {datetime}
- response_ms: 212
- ssl_expiry: 2026-09-15
- ssl_days_left: 87
- active_alerts: none

## store.client.com
- status: up
- last_checked: {datetime}
- response_ms: 380
- ssl_expiry: 2026-08-02
- ssl_days_left: 42
- active_alerts: none
```

This prevents duplicate alerts and lets the agent answer "how long has it been down?" accurately.

---

## Alert deduplication rules
- **DOWN alerts**: send once when first detected. Resend if site comes back UP and goes DOWN again.
- **SSL expiry**: alert at 14 days, 7 days, and 3 days — not every heartbeat.
- **Slow response**: alert once per 4-hour window per site.
- **Recovery**: always send a recovery message when an issue resolves:

```
✅ RECOVERED — {domain}
Issue: {what was wrong}
Resolved at: {time}
Downtime: ~{duration}
```

---

## Commands reference
| Command | Action |
|---------|--------|
| `add [domain] to monitoring` | Add site to sites.md, confirm |
| `remove [domain] from monitoring` | Remove from sites.md, confirm |
| `check [domain]` | Run all checks immediately for one site |
| `check all sites` | Run all checks for every site now |
| `site status` / `dashboard` | Show full status table |
| `silence [domain] [duration]` | Pause alerts for that site (e.g. during maintenance) |
| `unsilence [domain]` | Resume alerts |

---

## Tools required
- fetch / browser (HTTP checks)
- Memory / workspace read+write (sites.md, monitor-state.md)
- Heartbeat (automatic scheduling — configured in openclaw config)
- Messaging channel (send alerts)
