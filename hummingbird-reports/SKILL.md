# WordClaw Skill: Hummingbird Performance Reports

## What this skill does
Pulls real performance data from Hummingbird Pro on each client site via the WordClaw Bridge.
Generates formatted monthly performance reports and sends proactive alerts when scores drop
significantly. Also allows cache clearing from chat.

This replaces manual Hummingbird dashboard checks and gives the agency owner real data
to include in client reports — without logging into any site.

---

## Prerequisites
- WordClaw Bridge plugin installed and active on each client site
- Hummingbird Pro active on each client site
- Bridge secret in workspace memory: `wordclaw:bridge_secret = YOUR_KEY`
- Client sites in `sites.md` with `hummingbird:yes` flag
- At least one performance test run in Hummingbird on each site (to populate data)

---

## Trigger phrases
- "performance report [domain]"
- "hummingbird [domain]"
- "perf score [domain]"
- "how fast is [domain]?"
- "clear cache [domain]"
- "flush cache [domain]"
- "monthly report for [client name]"
- "performance report all sites"

---

## API calls

### Get performance report
```
GET https://{domain}/wp-json/wordclaw/v1/hummingbird/performance
Headers: X-WordClaw-Key: {bridge_secret}

Response:
{
  "available": true,
  "url": "https://example.com",
  "score_desktop": 87,
  "score_mobile": 74,
  "last_run": "2026-06-20 09:15:00",
  "raw": { ...full Hummingbird report object... }
}
```

### Clear page cache
```
POST https://{domain}/wp-json/wordclaw/v1/hummingbird/clear-cache
Headers: X-WordClaw-Key: {bridge_secret}
Body: {}                         // clears all cache
Body: { "page_id": 42 }         // clears single page cache
```

---

## Performance report format (single site)

```
⚡ PERFORMANCE REPORT — {domain}
━━━━━━━━━━━━━━━━━━━━

Client: {client name}
Last test: {datetime}

📊 SCORES
• Desktop: {score}/100 {rating_emoji} ({rating_label})
• Mobile:  {score}/100 {rating_emoji} ({rating_label})

{if scores differ significantly:}
⚠️ Mobile score is {n} points lower — worth investigating.

{if raw data available, extract top issues:}
🔧 TOP OPPORTUNITIES
• {opportunity 1} — could save ~{estimated_saving}
• {opportunity 2} — could save ~{estimated_saving}
• {opportunity 3} — could save ~{estimated_saving}

━━━━━━━━━━━━━━━━━━━━
Reply "clear cache {domain}" to flush cache
or "monthly report {client}" to generate a full client report.
```

### Score rating scale
| Score | Emoji | Label |
|-------|-------|-------|
| 90–100 | ✅ | Excellent |
| 75–89 | 🟢 | Good |
| 50–74 | 🟡 | Needs improvement |
| Below 50 | 🔴 | Poor — action needed |

---

## Monthly client report

When owner says "monthly report for [client name]" or "performance report all sites":

1. Find all sites matching the client in `sites.md`
2. Pull performance data from each via Hummingbird
3. Generate a combined report:

```
📊 MONTHLY PERFORMANCE REPORT
━━━━━━━━━━━━━━━━━━━━

Prepared for: {client name}
Period: {month year}
Generated: {date}

SITE PERFORMANCE
━━━━━━━━━━━━━━━━
{domain 1}
  Desktop: {score}/100 | Mobile: {score}/100
  Status: {rating}
  
{domain 2}  
  Desktop: {score}/100 | Mobile: {score}/100
  Status: {rating}

SUMMARY
• Avg desktop score: {avg}/100
• Avg mobile score: {avg}/100
• Sites performing well (75+): {n}
• Sites needing attention (<75): {n}

{if any site < 75:}
RECOMMENDED ACTIONS
• {domain}: {top opportunity}
• {domain}: {top opportunity}

━━━━━━━━━━━━━━━━━━━━
Reply "save report for {client}" to store this
or "send report to {client}" if email is configured.
```

---

## Cache clear flow

When owner says "clear cache [domain]":

1. Call POST `/hummingbird/clear-cache`
2. Reply:
   ```
   🧹 CACHE CLEARED — {domain}
   
   Full page cache flushed via Hummingbird.
   Pages will rebuild on next visitor request.
   
   Reply "performance report {domain}" to run a fresh speed test.
   ```

When owner says "clear cache page [url] on [domain]":
- Extract page ID if possible, or note that page-level cache clearing requires a page ID
- Call POST `/hummingbird/clear-cache` with `{ "page_id": X }`

---

## Heartbeat behaviour (weekly score check)

Once per week on heartbeat:

1. For each site with `hummingbird:yes` in `sites.md`
2. Call GET `/hummingbird/performance`
3. Compare scores to `hummingbird-state.md`
4. If desktop or mobile score dropped by **10+ points** vs last check: send alert

**Score drop alert:**
```
📉 PERFORMANCE DROP — {domain}
━━━━━━━━━━━━━━━━━━━━

Client: {client name}

Desktop: {old} → {new} ({delta} drop)
Mobile:  {old} → {new} ({delta} drop)

This may indicate a new plugin, theme update, or server issue.

━━━━━━━━━━━━━━━━━━━━
Reply "clear cache {domain}" to rule out cache issues
or "performance report {domain}" for details.
```

---

## State file: hummingbird-state.md

```
## {domain}
- last_checked: {datetime}
- score_desktop: {n}
- score_mobile: {n}
- last_report_run: {datetime}
- status: ok | drop_detected | no_data
```

---

## Saving and sending reports

When owner says "save report for [client]":
- Write to `reports/{client-slug}-{month}-{year}.md` in workspace
- Add memory entry: `report:{client} | period:{month-year} | status:saved`

When owner says "send report to [client]":
- Requires email integration active
- Drafts the report as an email body
- Confirms before sending

---

## Tools required
- fetch (REST API calls via WordClaw Bridge)
- Memory / workspace read+write (`sites.md`, `hummingbird-state.md`, `reports/`)
- Heartbeat (weekly score monitoring)
- Messaging channel
- Email (optional, for sending reports)
