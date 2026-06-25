# WordClaw Skill: Snapshot Backup Control

## What this skill does
Lets the agency owner trigger, check, and list Snapshot Pro backups on any client site
directly from WhatsApp/Telegram/Slack — no dashboard login required.

Also runs a daily heartbeat check to confirm the latest scheduled backup completed
successfully, and alerts if a backup is overdue or failed.

---

## Prerequisites
- WordClaw Bridge plugin installed and active on each client site
- Snapshot Pro active on each client site
- Bridge secret key in workspace memory: `wordclaw:bridge_secret = YOUR_KEY`
- Client sites in `sites.md` with `snapshot:yes` flag

---

## Trigger phrases
- "backup [domain]"
- "backup [domain] now"
- "run backup on [domain]"
- "snapshot [domain]"
- "list backups for [domain]"
- "backup status [domain]"
- "last backup [domain]"
- "backup all sites"

---

## API calls

### Trigger a backup
```
POST https://{domain}/wp-json/wordclaw/v1/snapshot/run
Headers: X-WordClaw-Key: {bridge_secret}
Body: { "name": "WordClaw on-demand — {date}" }

Response:
{
  "triggered": true,
  "name": "WordClaw on-demand — 2026-06-24",
  "output": "Starting backup... (WP-CLI output)",
  "note": "Poll /snapshot/status to check progress."
}
```

### Check backup progress
```
GET https://{domain}/wp-json/wordclaw/v1/snapshot/status
Headers: X-WordClaw-Key: {bridge_secret}

Response:
{
  "status": "Backup in progress",   // or "No backup in progress"
  "last_trigger": {
    "name": "WordClaw on-demand — 2026-06-24",
    "triggered": "2026-06-24 14:32:00"
  }
}
```

### List all backups
```
GET https://{domain}/wp-json/wordclaw/v1/snapshot/list
Headers: X-WordClaw-Key: {bridge_secret}

Response:
{
  "raw": "ID       | Date                | Name               | Size\nabc123   | 2026-06-24 14:35    | WordClaw on-demand  | 248MB\ndef456   | 2026-06-23 02:00    | Scheduled backup    | 246MB\n..."
}
```

---

## On-demand backup flow

When owner says "backup [domain]" or "backup [domain] now":

1. Look up `sites.md` to confirm domain is registered and has `snapshot:yes`
2. If not found: "I don't have {domain} in your monitored sites. Add it first with 'add {domain} to monitoring'."
3. Call POST `/snapshot/run` with name `"WordClaw on-demand — {today's date}"`
4. Reply immediately:
   ```
   📦 BACKUP STARTED — {domain}
   ━━━━━━━━━━━━━━━━━━━━

   Client: {client name}
   Backup name: WordClaw on-demand — {date}
   Started: {time}

   ━━━━━━━━━━━━━━━━━━━━
   I'll check progress in ~2 minutes.
   Reply "backup status {domain}" to check now.
   ```
5. After ~120 seconds (next heartbeat or timer), call GET `/snapshot/status`
6. Send completion message:
   ```
   ✅ BACKUP COMPLETE — {domain}
   
   Client: {client name}
   Completed: {time}
   Duration: ~{n} minutes
   
   Reply "list backups {domain}" to see all backups.
   ```

---

## List backups flow

When owner says "list backups [domain]":

1. Call GET `/snapshot/list`
2. Parse the raw WP-CLI table output
3. Reply:
   ```
   📋 BACKUPS — {domain}
   ━━━━━━━━━━━━━━━━━━━━

   Client: {client name}

   1. {date} — {name} ({size})
   2. {date} — {name} ({size})
   3. {date} — {name} ({size})
   (showing last 5)

   ━━━━━━━━━━━━━━━━━━━━
   Reply "backup {domain} now" to run a new backup.
   ```

---

## Heartbeat behaviour (daily check)

Once per day (or configurable), on heartbeat:

1. For each site with `snapshot:yes` in `sites.md`
2. Call GET `/snapshot/list`
3. Parse the most recent backup date
4. If most recent backup is **older than 48 hours**: send alert
5. If no backups found: send alert
6. Otherwise: HEARTBEAT_OK (silent)

**Overdue backup alert:**
```
⚠️ BACKUP OVERDUE — {domain}
━━━━━━━━━━━━━━━━━━━━

Client: {client name}
Last backup: {date} ({n} days ago)
Expected: daily

This site's scheduled backup may have failed or been disabled.

━━━━━━━━━━━━━━━━━━━━
Reply "backup {domain} now" to trigger one manually
or check Snapshot settings in the dashboard.
```

---

## "Backup all sites" flow

When owner says "backup all sites":

1. Read `sites.md` — find all sites with `snapshot:yes`
2. Trigger backup on each, sequentially (don't overwhelm servers)
3. Reply with a summary:
   ```
   📦 BACKUP BATCH STARTED
   ━━━━━━━━━━━━━━━━━━━━

   Triggering backups on {n} sites:
   ✅ example.com — triggered
   ✅ store.client.com — triggered
   ⚠️ oldsite.net — bridge not responding (check plugin)

   ━━━━━━━━━━━━━━━━━━━━
   I'll confirm each completion as they finish.
   ```

---

## State file: snapshot-state.md

```
## {domain}
- last_checked: {datetime}
- last_backup_date: {datetime}
- last_backup_name: {name}
- last_backup_size: {size}
- status: ok | overdue | failed | unknown
```

---

## Error handling

| Error | Response |
|-------|----------|
| Bridge not reachable | "Can't reach the WordClaw Bridge on {domain}. Is the plugin active?" |
| Snapshot not installed | "Snapshot Pro doesn't appear to be active on {domain}." |
| WP-CLI not available | "WP-CLI isn't available on this server. Manual backup needed via the dashboard." |
| Backup already in progress | "A backup is already running on {domain}. Check status in a few minutes." |

---

## Tools required
- fetch (REST API calls via WordClaw Bridge)
- Memory / workspace read+write (`sites.md`, `snapshot-state.md`)
- Heartbeat (daily backup freshness check)
- Messaging channel
- Timer or delay (for completion follow-up ~120s after trigger)
