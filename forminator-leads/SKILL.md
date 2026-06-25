# WordClaw Skill: Forminator Lead Notifications

## What this skill does
Polls Forminator Pro on client sites for new form entries and sends instant notifications
to the agency owner's messaging channel when a new lead comes in — across ALL managed
client sites, not just one.

Especially powerful for agencies running lead-gen sites: the owner gets notified of new
enquiries on client sites without the client having to forward anything, and without
logging into any dashboard.

---

## Prerequisites
- WordClaw Bridge plugin installed and active on each client site
- Forminator Pro active on client sites
- Bridge secret in workspace memory: `wordclaw:bridge_secret = YOUR_KEY`
- Forminator form IDs registered in `sites.md` (or auto-discovered)
- Sites in `sites.md` with `forminator:yes` flag

---

## Trigger phrases (manual)
- "new leads [domain]"
- "form entries [domain]"
- "leads today [domain]"
- "leads this week [domain]"
- "list forms [domain]"
- "how many leads [domain]?"
- "leads summary"

---

## Setup: registering forms to watch

The owner registers which forms to watch per site. In `sites.md`, add:

```
| example.com | Acme Corp | uptime,ssl,defender,forminator | 2026-01-15 |
```

Then the owner tells WordClaw which form IDs to watch:
```
watch form 3 on example.com as "Contact Form"
watch form 7 on example.com as "Quote Request"
```

This is saved to `forminator-forms.md`:
```
## example.com — Acme Corp
- form_id: 3, label: "Contact Form", last_checked: {datetime}
- form_id: 7, label: "Quote Request", last_checked: {datetime}
```

Alternatively: "discover forms on [domain]" — calls GET `/forminator/forms` and
lists available forms so the owner can choose which to watch.

---

## API calls

### List available forms
```
GET https://{domain}/wp-json/wordclaw/v1/forminator/forms
Headers: X-WordClaw-Key: {bridge_secret}

Response:
{
  "forms": [
    { "id": 3, "name": "Contact Form" },
    { "id": 7, "name": "Quote Request Form" },
    { "id": 12, "name": "Newsletter Signup" }
  ]
}
```

### Get entries since a date
```
GET https://{domain}/wp-json/wordclaw/v1/forminator/entries?form_id=3&since=2026-06-20
Headers: X-WordClaw-Key: {bridge_secret}

Response:
{
  "form_id": 3,
  "count": 2,
  "entries": [
    {
      "entry_id": 145,
      "time_created": "2026-06-24 09:12:33",
      "fields": {
        "text-1": "John Smith",
        "email-1": "john@example.com",
        "textarea-1": "I need a new website for my plumbing business..."
      }
    }
  ]
}
```

---

## Heartbeat behaviour (every 30 minutes)

On each heartbeat:

1. Read `forminator-forms.md` — get all watched forms per site
2. For each form, call GET `/forminator/entries?form_id={id}&since={last_checked}`
3. If new entries found → send lead notification immediately
4. Update `last_checked` in `forminator-forms.md` to now

---

## Lead notification format

Send immediately when a new entry is found:

```
🔔 NEW LEAD — {client name}
━━━━━━━━━━━━━━━━━━━━

Site: {domain}
Form: {form label}
Received: {datetime}

{for each field in entry — smart label mapping:}
👤 Name: {value}
📧 Email: {value}
📞 Phone: {value}
💬 Message: {value (truncate at 200 chars)}
{other fields: Field Name: value}

━━━━━━━━━━━━━━━━━━━━
Reply "leads {domain}" to see all recent leads
or "draft reply to this lead" for a response template.
```

### Smart field label mapping
Map Forminator's internal field names to readable labels:
- `text-1`, `name-*`, `first-name`, `full-name` → 👤 Name
- `email-*` → 📧 Email
- `phone-*`, `tel-*` → 📞 Phone
- `textarea-*`, `message-*` → 💬 Message
- `select-*`, `radio-*` → 🔘 {field name}
- `checkbox-*` → ☑️ {field name}
- Anything else → display as-is

---

## Manual lead queries

### "new leads [domain]" / "leads today [domain]"
1. Call GET `/forminator/entries?form_id={all watched forms}&since={today 00:00}`
2. Format as:
```
📋 LEADS TODAY — {domain}
━━━━━━━━━━━━━━━━━━━━

{n} new entries across {m} forms

[Contact Form] — {n} entries
  1. {name} — {email} — {time}
  2. {name} — {email} — {time}

[Quote Request] — {n} entries
  1. {name} — {email} — {time}

━━━━━━━━━━━━━━━━━━━━
Reply "entry {n}" to see full details of any entry.
```

### "leads summary" (all sites)
Compile leads across all sites with `forminator:yes`:
```
📊 LEADS SUMMARY
━━━━━━━━━━━━━━━━━━━━
Today: {datetime}

example.com (Acme Corp)
  • Contact Form: {n} today, {n} this week
  • Quote Request: {n} today, {n} this week

store.client.com (Best Shop)
  • Enquiry Form: {n} today, {n} this week

━━━━━━━━━━━━━━━━━━━━
Total this week: {n} leads across {m} sites
Reply "new leads [domain]" for details on any site.
```

---

## "Draft reply to this lead" flow

When the owner says "draft reply to this lead" after a lead notification:

1. Use the lead data from the most recent notification (stored in memory as `forminator:last_lead`)
2. Draft a professional reply email tailored to the enquiry content
3. Format:
```
Subject: Re: Your enquiry via [site name]

Hi {name},

Thank you for getting in touch! I've received your message and will be in touch shortly.

{if message mentioned a specific topic — e.g. plumbing website:
 "I'd love to help you build a professional website for your plumbing business."}

I'll aim to respond fully within 1 business day.

Best regards,
{agency name}
{email}
{phone}
```

---

## "Discover forms" flow

When owner says "discover forms on [domain]":
1. Call GET `/forminator/forms`
2. Reply:
```
📋 FORMS ON {domain}
━━━━━━━━━━━━━━━━━━━━

Found {n} forms:
  ID 3 — Contact Form
  ID 7 — Quote Request Form
  ID 12 — Newsletter Signup

━━━━━━━━━━━━━━━━━━━━
Which should I watch for leads?
Reply "watch form {id} on {domain} as {label}"
```

---

## State files

### forminator-forms.md
```
## example.com — Acme Corp
- form_id: 3, label: Contact Form, last_checked: 2026-06-24T14:30:00
- form_id: 7, label: Quote Request, last_checked: 2026-06-24T14:30:00
```

### forminator-state.md
```
## example.com
- last_run: {datetime}
- total_leads_today: {n}
- total_leads_week: {n}
- last_lead_id: {entry_id}
```

### memory key: forminator:last_lead
```
{
  "domain": "example.com",
  "form": "Contact Form",
  "entry_id": 145,
  "fields": { "name": "John Smith", "email": "john@example.com", ... },
  "received": "2026-06-24 09:12:33"
}
```

---

## Tools required
- fetch (REST API calls via WordClaw Bridge)
- Memory / workspace read+write (`forminator-forms.md`, `forminator-state.md`, memory keys)
- Heartbeat (every 30 minutes)
- Messaging channel
