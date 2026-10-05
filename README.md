# WordClaw 🐾

**An AI employee for WordPress agency owners — living in WhatsApp, Telegram, or Slack.**

WordClaw automates the three things that eat a WordPress agency owner's day: auditing prospects before sales calls, monitoring client sites, and writing proposals. Built on [OpenClaw](https://github.com/openclaw/openclaw), it runs as a set of skills your agent executes from a simple chat message — no dashboard, no browser tab, just the phone in your pocket.

## Why

Running a WordPress agency means the same manual work on repeat: audit a prospect's site, monitor client sites for uptime/SSL/performance issues, and write scoped proposals. All of it is repetitive, and all of it happens while you're also doing actual client work. WordClaw turns that workflow into a chat-based employee that works while you sleep.

## What it does

### 🔍 Lead Audit
`audit https://prospect-site.com`

Runs a full WordPress site audit — performance score, SEO signals, security checks (exposed WP version, login page, XML-RPC, security headers), tech stack fingerprinting, and plugin health — then writes a personalized pitch draft based on what it actually found, not a template.

**Example output** (anonymized from real testing):
> Found: author slugs leaking via the sitemap, staging/placeholder content still publicly indexable, the WordPress install script reachable, broad REST API namespace exposure, and no CSP header.
>
> Generated pitch: *"Your site is giving away a lot of WordPress footprint — especially author slugs and leftover staging content that's still publicly indexable. That kind of exposure can hurt trust, SEO quality, and security posture at the same time. I can clean that up in one pass, starting with the highest-impact fixes first."*

What used to take 30–45 minutes per site now runs unattended — send 10 prospect URLs before bed, wake up to 10 audit reports with pitch drafts ready.

### 📡 Site Monitor
Runs automatically on a 30-minute heartbeat, plus on-demand via `dashboard` or `check [domain]`.

Monitors uptime, SSL validity/expiry (alerts at 14/7/3 days out), and slow response times. Smart deduplication means you're notified once when something breaks and once when it recovers — never spammed.

```
add clientsite.com to monitoring
dashboard
silence clientsite.com 2h
check clientsite.com
```

### 📋 Proposal Writer
`write a proposal for [plain-language description]` or `proposal for [domain]` (pulls prior audit data automatically)

Describe a project in plain language, get back a full scoped proposal: overview, phases, three pricing tiers, timeline, payment terms, inclusions/exclusions, next steps. If the site was already audited, the proposal builds its problem statement around the actual findings — specific to that client, not generic. Refine conversationally: `make it more casual`, `change the price to $3,500`, `remove Option C`.

What used to take 1–2 hours now takes about 5 minutes of back-and-forth.

## Why this works (architecture notes)

- **Heartbeat scheduling** is what makes monitoring possible without a one-shot chatbot: the agent wakes up on an interval, checks state, and only messages you when something changed.
- **Workspace memory** lets skills share context — the proposal writer automatically knows what the audit skill found, without repeating yourself.
- **Messaging-native** (WhatsApp/Telegram/Slack) means this is usable from a phone, not a dashboard you have to remember to open.

## Phase 2 — WPMU DEV integration

Four additional skills connect WordClaw to the WPMU DEV ecosystem already installed on most managed WordPress sites, through a small bridge plugin (`wordclaw-bridge`) that exposes authenticated REST endpoints:

| Skill | What it does | Requires on site |
|---|---|---|
| `defender-alerts` | Security scan results + threat alerts | Bridge + Defender Pro |
| `snapshot-backup` | Trigger/check backups from chat | Bridge + Snapshot Pro |
| `hummingbird-reports` | Performance scores + cache clearing | Bridge + Hummingbird Pro |
| `forminator-leads` | New lead notifications across sites | Bridge + Forminator Pro |

```
defender scan example.com
backup example.com now
performance report example.com
new leads example.com
```

Full command references and setup steps are in [`SETUP.md`](./SETUP.md) (Phase 1) and [`SETUP-PHASE2.md`](./SETUP-PHASE2.md) (Phase 2).

## Tech stack

- **Runtime:** [OpenClaw](https://github.com/openclaw/openclaw) (skill-based AI agent framework) + Anthropic API (Claude)
- **Bridge plugin:** PHP, WordPress REST API, WP-CLI
- **Channels:** WhatsApp, Telegram, Slack
- **State:** Flat-file workspace memory (`sites.md`, `monitor-state.md`, `memory.md`)

## Security

- The bridge plugin authenticates every request with a shared secret (`X-WordClaw-Key` header); requests without a valid key return `403`.
- The secret should be set via environment/`wp-config.php`, not hardcoded, and rotated periodically.
- **Known gap:** the bridge plugin does not currently log requests — no audit trail is written on the WordPress side. On the roadmap: request logging and optional IP allowlisting for the bridge REST routes.

## Quick start

```bash
npm install -g openclaw@latest
openclaw onboard --install-daemon
openclaw channel add whatsapp   # or telegram / slack
```

See [`SETUP.md`](./SETUP.md) for the full walkthrough, including skill installation, agency configuration, and heartbeat setup.

## Status

Built as a focused, end-to-end project exploring what a skill-based AI agent framework can do for a real, recurring business workflow. Phase 1 (audit, monitor, proposals) is working; Phase 2 (WPMU DEV integration) is the active build.

---

Built by Leroy Bruster — Full Stack Developer, WordPress agency background.
