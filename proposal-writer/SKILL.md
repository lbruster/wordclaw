# WordClaw Skill: Proposal Writer

## What this skill does
Generates a full, professional WordPress project proposal from a plain-language description sent over chat. No forms, no templates to fill in — the owner just describes the project conversationally and the agent asks 2–3 follow-up questions if needed, then produces a complete scoped proposal ready to send or refine.

Can also pull from a previous Lead Audit to pre-fill project context.

---

## Trigger phrases
- "write a proposal for [description]"
- "draft a proposal"
- "create a quote for [client]"
- "proposal for [domain]" (will look up audit memory if available)
- "scope this project: [description]"
- "how much should I charge for [description]"

---

## Information gathering

### From the message itself, extract:
- Client name or domain (if mentioned)
- Project type (new build, redesign, plugin dev, maintenance, WooCommerce, etc.)
- Key features or requirements mentioned
- Any budget or timeline hints

### If key information is missing, ask ONE combined question:
```
Quick questions before I write this up:
1. Is this a new build or a redesign?
2. Any rough timeline or deadline?
3. What's the client's industry / what does their business do?
```

Never ask more than 3 questions in one go. If you have enough to work with, skip asking and note assumptions at the end.

### If triggered by "proposal for [domain]":
Check workspace memory for `lead:{domain}` — if a prior audit exists, pre-fill the problem statement from the audit findings and tailor the proposal to those specific issues.

---

## Proposal structure to generate

Produce a clean, professional proposal in this structure. Use plain text formatting that works well in both chat (for preview) and when copied to a doc.

---

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
PROJECT PROPOSAL
Prepared for: {Client Name}
Prepared by: {Agency Name — pull from workspace memory or use "[Your Agency]"}
Date: {today's date}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

PROJECT OVERVIEW
{2–3 sentence summary of what the project is, what problem it solves for the client, and what success looks like. Written in plain language, not jargon.}

---

SCOPE OF WORK

Phase 1 — {phase name, e.g. "Discovery & Design"}
• {deliverable 1}
• {deliverable 2}
• {deliverable 3}
Estimated time: {n} days

Phase 2 — {phase name, e.g. "Development"}
• {deliverable 1}
• {deliverable 2}
• {deliverable 3}
Estimated time: {n} days

Phase 3 — {phase name, e.g. "Testing & Launch"}
• {deliverable 1}
• {deliverable 2}
Estimated time: {n} days

---

INVESTMENT

Option A — Core Package
{Brief description — what's included}
Investment: ${amount}

Option B — Full Package (recommended)
{Brief description — what's added vs Option A}
Investment: ${amount}

Option C — Premium (with ongoing support)
{Brief description — what's added vs Option B}
Investment: ${amount} + ${monthly}/month retainer

---

TIMELINE
• Project kickoff: {estimated start}
• Design approval: Week {n}
• Development complete: Week {n}
• Testing & revisions: Week {n}
• Launch: Week {n}
Total project duration: {n} weeks

---

WHAT'S INCLUDED IN ALL PACKAGES
• {standard item, e.g. "Mobile-responsive design"}
• {standard item, e.g. "Basic SEO setup (Yoast / RankMath)"}
• {standard item, e.g. "Speed optimisation"}
• {standard item, e.g. "30 days post-launch support"}
• {standard item, e.g. "Training session for your team"}

WHAT'S NOT INCLUDED
• Content writing (unless specified)
• Stock photography / paid assets
• Third-party plugin licences
• Ongoing hosting (we can recommend / manage this separately)

---

TERMS
• 50% deposit required to begin
• Remaining 50% due on launch day
• Revisions: up to 2 rounds included per phase
• Additional revisions billed at ${rate}/hour
• Proposal valid for 30 days

---

NEXT STEPS
To move forward, reply to this proposal or contact us:
📧 {email from workspace memory or "[your email]"}
📞 {phone from workspace memory or "[your phone]"}

We're excited to work with you on this project.

{Agency Name}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

## Pricing guidance by project type

Use these as starting anchors — adjust based on client signals, complexity, and market:

| Project Type | Option A | Option B | Option C |
|---|---|---|---|
| Simple brochure site (5 pages) | $1,500 | $2,500 | $3,500 + retainer |
| Business site (10–15 pages) | $3,000 | $5,000 | $7,000 + retainer |
| WooCommerce store (basic) | $4,000 | $7,000 | $10,000 + retainer |
| WooCommerce store (complex) | $8,000 | $14,000 | $20,000 + retainer |
| Site redesign | $2,000 | $4,000 | $6,000 + retainer |
| Plugin development | $2,500 | $5,000 | $8,000 + retainer |
| Maintenance plan only | — | — | $150–$500/month |
| Performance optimisation | $500 | $1,200 | $2,000 |
| Security hardening | $400 | $900 | $1,500 |

If the owner gives a different pricing preference or mentions their typical rates, use those instead and remember them in workspace memory as `agency:pricing_style`.

---

## After generating the proposal

Append this footer to the chat message (not to the proposal itself):

```
━━━━━━━━━━━━━━━━━━━━
✅ Proposal ready. You can:
• "refine the proposal — add hosting management"
• "make it shorter / more formal / more casual"
• "change the price to $X"
• "save this proposal for [client name]"
• "send this proposal" (if email integration is active)
```

---

## Saving proposals
When the owner says "save this proposal for [client]":
- Write the proposal to `proposals/{client-slug}-{date}.md` in the workspace
- Add an entry to memory: `proposal:{client} | date:{date} | value:{amount} | status:draft`

When they say "mark [client] proposal as sent":
- Update memory to `status:sent`

When they say "mark [client] proposal as won" or "lost":
- Update memory accordingly and congratulate (or note for follow-up)

---

## Refinement commands
| Command | Action |
|---------|--------|
| `make it shorter` | Condense scope details, keep pricing and terms |
| `make it more formal` | Remove casual language, use more professional phrasing |
| `make it more casual` | Soften the tone, add friendly opening |
| `add [feature] to scope` | Insert the feature into the right phase |
| `remove Option C` | Drop the premium tier |
| `change the price to $X` | Recalculate all tiers proportionally or as instructed |
| `add a payment plan` | Insert a payment schedule section |

---

## Workspace memory to read/write

Read on startup:
- `agency:name` — agency name for the proposal header
- `agency:email`, `agency:phone` — contact details
- `agency:pricing_style` — any custom pricing preferences
- `lead:{domain}` — prior audit data if triggered by domain

Write after generation:
- `proposal:{client} | date:{date} | value:{amount} | status:draft`

---

## Tools required
- Memory / workspace read+write
- Date/time (for proposal date and timeline estimates)
- fetch (optional — only if pulling from a prior audit URL)
