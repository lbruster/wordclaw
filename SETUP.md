# WordClaw — Setup Guide
WordPress Agency AI Employee powered by OpenClaw

---

## Prerequisites
- Node.js 22+ installed (Node 24 recommended)
- An Anthropic API key (claude.ai account → Settings → API)
- WhatsApp, Slack, or Telegram account

---

## Step 1 — Install OpenClaw

```bash
npm install -g openclaw@latest
openclaw onboard --install-daemon
```

Follow the prompts. When asked for your AI provider, choose **Anthropic** and paste your API key. Use **claude-sonnet-4-6** as the model (good balance of speed and cost for agency tasks).

---

## Step 2 — Connect your messaging channel

### WhatsApp
```bash
openclaw channel add whatsapp
```
Scan the QR code with your phone. Done.

### Telegram
1. Open Telegram → search @BotFather → `/newbot`
2. Copy the token it gives you
```bash
openclaw channel add telegram --token YOUR_BOT_TOKEN
```

### Slack
1. Go to api.slack.com/apps → Create New App → From Scratch
2. Add Bot Token Scopes: `chat:write`, `channels:read`, `im:write`
3. Install to your workspace and copy the Bot User OAuth Token
```bash
openclaw channel add slack --token xoxb-YOUR-TOKEN
```

---

## Step 3 — Install WordClaw skills

Copy the three skill folders into your OpenClaw workspace:

```bash
# Find your workspace (default location)
ls ~/.openclaw/workspace/skills/

# Copy the WordClaw skills in
cp -r ./wordclaw/lead-audit     ~/.openclaw/workspace/skills/
cp -r ./wordclaw/site-monitor   ~/.openclaw/workspace/skills/
cp -r ./wordclaw/proposal-writer ~/.openclaw/workspace/skills/
```

Verify they loaded:
```bash
openclaw skills list
```
You should see `lead-audit`, `site-monitor`, and `proposal-writer` in the list.

---

## Step 4 — Configure your agency details

Send this to your WordClaw agent on WhatsApp/Telegram/Slack:

```
Remember the following about my agency:
- Agency name: [Your Agency Name]
- My email: [your@email.com]
- My phone: [your number]
- My typical rate for a basic 5-page site: $2,500
- My hourly rate for revisions: $85/hour
```

WordClaw will save this to workspace memory and use it in every proposal going forward.

---

## Step 5 — Set up site monitoring

Add your first client site:
```
add clientname.com to monitoring
```

Add more:
```
add example.com to monitoring
add store.client.com to monitoring
```

Check they're all live:
```
dashboard
```

---

## Step 6 — Configure the heartbeat (auto-monitoring)

Edit your OpenClaw config file:

```bash
nano ~/.openclaw/config.yaml
```

Add or update:
```yaml
heartbeat:
  enabled: true
  interval: 1800        # 1800 seconds = 30 minutes
  channel: whatsapp     # or telegram, slack
```

Restart the daemon:
```bash
openclaw restart
```

From now on, WordClaw checks all your client sites every 30 minutes and messages you only if something breaks.

---

## Quick command reference

| What you want | What to say |
|---|---|
| Audit a prospect | `audit https://theirsite.com` |
| Check if a client site is up | `check clientsite.com` |
| See all site statuses | `dashboard` |
| Add a site to monitoring | `add clientsite.com to monitoring` |
| Write a proposal | `write a proposal for a 10-page business site for a plumber in Austin` |
| Write a proposal from an audit | `proposal for theirsite.com` |
| Adjust a proposal | `make it more casual` / `change the price to $4,000` |
| Save a proposal | `save this proposal for John Smith Plumbing` |
| Pause alerts (e.g. during maintenance) | `silence clientsite.com 2h` |

---

## Estimated API costs

OpenClaw uses your Anthropic API key. Rough estimates:

| Usage level | Monthly cost |
|---|---|
| Light (few audits/day, monitoring 5 sites) | ~$5–$15 |
| Medium (10 audits/day, monitoring 20 sites) | ~$20–$50 |
| Heavy (constant use, 50+ sites) | ~$80–$150 |

The heartbeat checks are very cheap (short prompts). Audits and proposals use more tokens.

---

## Troubleshooting

**Skills not loading:**
```bash
openclaw skills list
# If empty, check the folder names — must match exactly
ls ~/.openclaw/workspace/skills/
```

**WhatsApp disconnected:**
```bash
openclaw channel reconnect whatsapp
# Scan QR again
```

**Heartbeat not running:**
```bash
openclaw status
# Should show daemon: running
# If not:
openclaw start
```

**Agent not responding on Telegram:**
- Make sure you messaged your bot directly (not a group) for first setup
- Check the token is correct: `openclaw channel list`

---

## File locations

| File | Purpose |
|---|---|
| `~/.openclaw/workspace/skills/site-monitor/sites.md` | Your monitored client sites |
| `~/.openclaw/workspace/skills/site-monitor/monitor-state.md` | Last known state of each site |
| `~/.openclaw/workspace/proposals/` | Saved proposals |
| `~/.openclaw/workspace/memory.md` | Agent's long-term memory (agency details, lead history) |

You can edit these files directly in any text editor.

---

## What to build next (Phase 2)

Once Phase 1 is running smoothly, the next step is connecting to the WPMU DEV ecosystem:

- **Hummingbird REST API** → pull real performance scores per client site into monthly reports
- **Defender WP-CLI** → get security scan results and vulnerability alerts automatically
- **Snapshot WP-CLI** → trigger on-demand backups from WhatsApp
- **Beehive** → pull Google Analytics data into client reports
- **Forminator** → get notified when a new lead form submission comes in across any client site

All of this uses the same SKILL.md pattern — each integration is just another skill folder.
