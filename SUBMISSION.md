# WordClaw — My OpenClaw Build & Feedback Submission

Hey everyone! I've been deep in this for the past few days and I'm genuinely impressed by what OpenClaw can do when you push it. Here's what I built, why, and my honest feedback.

---

## What I built: WordClaw 🐾

**WordClaw is an AI employee for WordPress agency owners — living in your WhatsApp (or Telegram/Slack) and handling the repetitive work that eats your day.**

I run a WordPress agency. The daily reality is: auditing prospects before sales calls, monitoring client sites, and writing proposals. All of it is manual, all of it is repetitive, and all of it happens while you're also doing actual client work.

WordClaw turns OpenClaw into a full Phase 1 AI assistant for that exact workflow — three skills, all running from a simple chat message.

---

## The three skills I built

### 🔍 Skill 1: Lead Audit
**Trigger:** `audit https://prospect-site.com`

Send it any URL and it runs a full WordPress site audit — performance score, SEO signals, security checks (exposed WP version, login page, XML-RPC, security headers), tech stack fingerprinting, and plugin health.

Then it writes a personalized pitch draft based on the *specific* issues it found.

**Real example I ran during testing on a live site (`https://prospect-site.com`):**

The audit found:
- Author slugs leaking via the Yoast sitemap (`jgqgex`, `tempdev-2`)
- Staging/lorem ipsum content publicly indexable on Google
- `wp-admin/install.php` reachable
- Broad plugin REST namespace exposure (Beehive, Forminator, Hustle, Hummingbird all visible)
- Old domain references (`https://oldprospect-site.com`) still appearing in RSS feeds and schema
- No CSP header
- Missing meta description and image alt tags

And it generated this pitch draft automatically:

> *"Your site is giving away a lot of WordPress footprint — especially author slugs, old domain references, and a bunch of staging/test content that's still publicly indexable. That kind of leftover surface can hurt trust, SEO quality, and security posture at the same time. If you want, I can cleanly trim the exposure and tighten the WordPress surface in one pass, starting with the highest-impact fixes first."*

That pitch was written by the agent based on what it actually found — not a template. I didn't edit a word.

**The use case:** I can now send 10 prospect URLs before bed and wake up to 10 audit reports with pitch drafts ready. That used to take me 30–45 minutes per site.

---

### 📡 Skill 2: Site Monitor
**Trigger:** Runs automatically on heartbeat every 30 minutes + manual `dashboard` or `check [domain]`

Monitors all client sites for uptime, SSL validity, SSL expiry (alerts at 14 days, 7 days, 3 days), and slow response times. Smart deduplication — alerts you once when something breaks, sends a recovery message when it comes back. Never spams.

Manual commands:
- `add clientsite.com to monitoring` — adds to the list
- `dashboard` — full status table across all sites
- `silence clientsite.com 2h` — pause alerts during maintenance windows
- `check clientsite.com` — immediate manual check

**The use case:** I used to find out a client's site was down when *they* emailed me. Now WordClaw tells me first.

---

### 📋 Skill 3: Proposal Writer
**Trigger:** `write a proposal for [plain language description]` or `proposal for [domain]` (uses the prior audit data automatically)

Describe a project in plain language and get a full scoped proposal back: overview, phases, three pricing tiers, timeline, payment terms, what's included/excluded, and next steps.

If you've already audited the site, `proposal for https://prospect-site.com` pulls the audit findings and builds the problem statement around the actual issues found — the proposal is specific to that client, not generic.

Refinement commands work conversationally: `make it more casual`, `change the price to $3,500`, `add a payment plan`, `remove Option C`.

**The use case:** Proposals used to take me 1–2 hours. Now it's 5 minutes of back-and-forth and I have something ready to send.

---

## What makes this work well in OpenClaw specifically

A few things about OpenClaw's architecture made this genuinely powerful rather than just "AI in a chat":

**Heartbeat** is the killer feature for site monitoring. The agent wakes up, checks all sites, compares to last known state, and only messages you if something changed. That's not possible with a one-shot chatbot — it requires persistent state and scheduled execution. OpenClaw does this natively.

**Workspace memory** lets the audit results flow into the proposal writer automatically. When I type `proposal for https://prospect-site.com`, it knows what was found in the audit because it wrote to memory during the audit run. The skills talk to each other through memory without me having to repeat myself.

**WhatsApp/Telegram integration** means this is actually usable on the go. I'm not opening a browser or logging into a dashboard. I send a message from my phone like I would to an assistant, and I get a structured answer back. That's the UX that makes this feel like an employee, not a tool.

---

## Phase 2 idea: WPMU DEV deep integration

This is where it gets exciting for this specific community. The WPMU DEV API and WP-CLI tools open up a much deeper integration layer:

- **Hummingbird REST API** → pull real performance scores per client site directly into the monthly report skill
- **Defender WP-CLI** → hook into security scan results; WordClaw alerts you on WhatsApp when Defender flags a threat
- **Snapshot WP-CLI** → `backup clientsite.com now` from WhatsApp triggers an on-demand backup
- **Beehive** → pull Google Analytics data into client reports automatically
- **Forminator** → get notified when a lead form submission comes in across any client site

The WPMU DEV ecosystem is already installed on most sites you manage through this hosting. WordClaw as a Phase 2 build would make OpenClaw a native control layer for all of it — from your phone.

---

## Honest feedback on OpenClaw

**What worked really well:**
- Skill system is clean and intuitive. Writing a SKILL.md and having the agent just pick it up and follow it is a great pattern — low barrier to creating new behaviours
- Heartbeat + workspace memory is genuinely powerful for anything stateful (monitoring, tracking, follow-ups)
- WhatsApp integration worked first try, no issues
- The agent follows formatting instructions reliably — the audit report came out cleanly formatted for mobile reading without extra prompting

**What could be better:**
- PageSpeed API integration needs clearer documentation — I hit a quota issue during testing and the fallback path wasn't obvious
- Skill discovery (how does the agent decide which skill to use for a given message?) could be more transparent — would love a `debug skill-match` command to see which skill was activated and why
- Workspace memory format could use a structured query option (e.g. `memory.get('lead:domain')`) rather than relying on the agent to parse a flat file — would make multi-skill memory sharing more reliable
- Would love a way to test heartbeat skills on demand (`openclaw heartbeat run now`) without waiting for the interval

**Feature ideas I'd use immediately:**
- Skill marketplace / sharing — I'd publish WordClaw skills for other agency owners instantly
- Multi-channel routing — send audit alerts to Slack but proposal confirmations to WhatsApp
- Webhook trigger support — fire a skill when an external event happens (e.g. a new lead in the CRM)
- Usage dashboard showing token consumption per skill per day

---

## Bottom line

OpenClaw hit a real use case for me. The combination of heartbeat scheduling, persistent memory, and messaging channel integration makes it genuinely useful for async, ongoing workflows — not just one-shot questions.

For a WordPress agency owner, WordClaw is the AI employee I didn't know I could have. Everything I built here took a few days of focused work. The skill pattern scales — Phase 2 WPMU DEV integrations are the obvious next step and I'll be building those regardless of this competition.

Happy to answer questions or go deeper on any part of the build.

---

*Built by Leroy Bruster | WordPress agency owner | WordClaw Phase 1 skills available on request*
