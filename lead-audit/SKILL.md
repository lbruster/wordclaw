# WordClaw Skill: Lead Audit

## What this skill does
When given a website URL, this skill performs a full WordPress site audit and returns a structured report covering performance, SEO, security signals, and plugin health. It ends with a ready-to-send sales pitch paragraph tailored to the prospect's specific weaknesses.

Use this skill proactively: the agency owner can send a URL before a sales call, at the end of the day for a batch of leads, or triggered by a heartbeat job overnight.

---

## Trigger phrases
- "audit [url]"
- "check [url]"
- "analyse this site: [url]"
- "run a lead audit on [url]"
- "what's wrong with [url]"
- "prospect check [url]"

---

## Steps to execute

### 1. Fetch and parse the page
Use the browser tool or fetch to load the URL. Capture:
- HTTP status code and redirect chain
- Page title and meta description (check if missing or duplicated)
- Whether WordPress is detectable (look for /wp-content/, generator meta tag, common WP patterns)
- Active theme name if visible in source

### 2. Performance check
Run a Lighthouse/PageSpeed Insights API call:
```
GET https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url={URL}&strategy=mobile
```
Extract and store:
- Performance score (0–100)
- LCP (Largest Contentful Paint) in seconds
- CLS (Cumulative Layout Shift)
- FCP (First Contentful Paint)
- Total page weight (KB)
- Number of render-blocking resources

If API key is unavailable, use GTmetrix public summary or note "manual check needed".

### 3. SEO signals
From the fetched HTML check:
- Meta title: present? Under 60 chars?
- Meta description: present? Under 160 chars?
- H1 tag: present? Only one?
- Image alt tags: count images missing alt text
- robots.txt: fetch {URL}/robots.txt — is it blocking crawlers?
- Sitemap: check {URL}/sitemap.xml or {URL}/sitemap_index.xml — present?
- Schema markup: any JSON-LD or microdata present?

### 4. Security signals
Check:
- SSL certificate: HTTPS? Valid? (check response headers)
- Security headers present? Check response for: X-Frame-Options, X-Content-Type-Options, Content-Security-Policy
- WordPress version exposed in meta generator tag or readme.html — fetch {URL}/readme.html
- Login page exposed: fetch {URL}/wp-login.php — returns 200?
- XML-RPC exposed: fetch {URL}/xmlrpc.php — returns 200?

### 5. Plugin / tech stack signals
From HTML source identify:
- Any obvious outdated JS libraries (jQuery version in source)
- Cookie consent banner present?
- Analytics tag present? (GA4, GTM, Facebook Pixel)
- Live chat widget?
- Caching plugin signals (look for cache headers: X-Cache, CF-Cache-Status)
- CDN in use? (Cloudflare, BunnyCDN, etc.)

### 6. Compose the audit report
Format the reply as follows — keep it clean and scannable for WhatsApp/Slack/Telegram:

```
🔍 LEAD AUDIT: {domain}
━━━━━━━━━━━━━━━━━━━━

📊 PERFORMANCE
• Score: {score}/100 ({rating})
• LCP: {lcp}s | CLS: {cls} | FCP: {fcp}s
• Page size: {size}KB

🔎 SEO
• Meta title: {✅ OK / ⚠️ Missing / ⚠️ Too long}
• Meta description: {✅ OK / ⚠️ Missing}
• H1: {✅ OK / ⚠️ Missing / ⚠️ Multiple found}
• Sitemap: {✅ Found / ❌ Missing}
• Images missing alt: {n}

🔐 SECURITY
• SSL: {✅ Valid / ❌ Issue}
• WP version exposed: {✅ Hidden / ⚠️ Visible — v{x.x.x}}
• Login page exposed: {✅ Protected / ⚠️ Open}
• XML-RPC: {✅ Disabled / ⚠️ Enabled}
• Security headers: {✅ Present / ⚠️ Missing}

🧩 TECH STACK
• Caching: {✅ Detected / ❌ Not detected}
• CDN: {name / ❌ None}
• Analytics: {✅ Present / ❌ Missing}
• Cookie consent: {✅ Present / ❌ Missing}

⚡ TOP 3 ISSUES
1. {most critical issue}
2. {second issue}
3. {third issue}

💬 PITCH DRAFT
"{personalised 2-3 sentence pitch based on the specific issues found, written as if from the agency owner to the prospect. Professional but approachable tone. Reference their actual problems.}"

━━━━━━━━━━━━━━━━━━━━
Want me to audit another URL or draft a full proposal for this lead?
```

### 7. Scoring logic for ratings
- Performance score 90–100 → "Excellent"
- 70–89 → "Good — room to improve"
- 50–69 → "Needs work"
- Below 50 → "Critical issues"

### 8. Pitch draft guidance
The pitch should:
- Open by mentioning one specific problem found (e.g. slow load time, exposed WP version)
- Briefly mention the business impact (lost leads, Google ranking, security risk)
- End with a soft CTA (offer a free call or quick fix)
- Never sound templated — vary the opening line based on the dominant issue

---

## Memory to save after each audit
Save to workspace memory:
```
lead:{domain} | audited:{date} | score:{n} | top_issue:{issue} | pitched:{yes/no}
```
This lets the agent recall "have I audited this site before?" and track which leads have been followed up.

---

## Error handling
- If the URL is unreachable: "Site appears to be down or blocking bots. I've noted it — want me to try again later?"
- If PageSpeed API fails: run the structural checks and note "Performance score unavailable — manual Lighthouse check recommended"
- If not a WordPress site: still run the audit but omit WP-specific checks, note "This doesn't appear to be a WordPress site — audit covers general web health"

---

## Tools required
- fetch / browser (HTTP requests and HTML parsing)
- PageSpeed Insights API (optional, falls back gracefully)
- Memory (workspace write)
