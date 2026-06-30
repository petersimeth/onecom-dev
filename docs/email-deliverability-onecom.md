# Email Deliverability Setup — onecom.io (IONOS)

A practical guide to getting ShopSignal's transactional email (verification
links, password resets, the "existing account" notice) reliably into inboxes
instead of spam. It covers SPF, DKIM, and DMARC for **onecom.io**, sending
through **smtp.ionos.com** as **no-reply@onecom.io**.

Do these in order. Realistically it's ~20 minutes of work plus DNS propagation
time (up to 48 hours, usually much faster).

---

## Why this matters

ShopSignal's whole auth flow depends on email arriving. If `no-reply@onecom.io`
isn't authenticated, Gmail/Outlook will either junk it or silently drop it — and
a user who never gets their verification or reset link is simply stuck. The
three records below are what mailbox providers check:

- **SPF** — says "these servers are allowed to send for onecom.io." Authorises IONOS.
- **DKIM** — cryptographically signs each message so it can't be forged or altered.
- **DMARC** — tells receivers what to do if SPF/DKIM fail, and sends you reports.

Gmail and Yahoo now effectively **require** SPF + DKIM + DMARC for bulk senders,
and increasingly scrutinise everyone else. All three together is the goal.

---

## Step 0 — Find out where onecom.io's DNS lives

Everything branches on this. Your database and SMTP are on IONOS, so the domain
is very likely registered and DNS-managed at IONOS too — but confirm:

1. Sign in at <https://my.ionos.com> → **Domains & SSL** → click **onecom.io**.
2. If you see a **DNS** tab with editable records there, IONOS manages your DNS →
   follow the **"IONOS DNS"** instructions below (the easy path — mostly one-click).
3. If DNS is elsewhere (Cloudflare, GoDaddy, etc.), follow the **"External DNS"**
   instructions, where you paste records manually.

Quick check from a terminal:

```bash
# Shows the authoritative name servers for the domain
dig NS onecom.io +short
```

If the name servers contain `ui-dns` / `ionos`, IONOS runs your DNS.

---

## Step 1 — SPF

### If onecom.io DNS is at IONOS

SPF is **on by default** for IONOS-hosted domains, so you may already be done.
If mail is still failing SPF, add it explicitly:

1. IONOS account → **Domains & SSL** → **onecom.io** → **DNS**.
2. **Add record** → choose **IONOS SPF (TXT)** → **Save**.

That's it — IONOS maintains the value for you. If an SPF record already exists
for another sender, IONOS merges its mail servers into it automatically.

### If onecom.io DNS is external

Add a single TXT record:

| Field | Value |
|-------|-------|
| Type  | `TXT` |
| Name / Host | `@` (the root, i.e. `onecom.io`) |
| Value | `v=spf1 include:_spf-us.ionos.com ~all` |

If you **already have** an SPF record, do **not** add a second one (multiple SPF
records is invalid). Instead, add IONOS's include into the existing record:

```text
# before
v=spf1 ip4:203.0.113.10 ~all
# after (just insert the include)
v=spf1 ip4:203.0.113.10 include:_spf-us.ionos.com ~all
```

Keep the qualifier at the end as `~all` (soft-fail) while testing; you can move
to `-all` (hard-fail) later once you're confident every legitimate sender for
the domain is listed.

---

## Step 2 — DKIM

### If onecom.io DNS is at IONOS

DKIM signing is **configured automatically** when the domain uses IONOS name
servers — there's usually nothing to do. To confirm it's active (or to switch it
on if it was ever removed):

1. IONOS account → **Email** (or **Domains & SSL** → **onecom.io** → **DNS**).
2. Look for the **DKIM** setting for the domain and ensure it's **enabled**.
3. If it was deactivated, IONOS will show **three CNAME records** to re-add — add
   each one (Type `CNAME`, copy the **Hostname** and **Points to** values exactly,
   leave TTL at 1 hour).

### If onecom.io DNS is external

IONOS generates three DKIM **CNAME** records for your domain (visible in the
IONOS DNS/email settings for onecom.io). Copy all three into your external DNS
zone exactly as shown:

| Type  | Host (example shape)        | Points to (from IONOS)             |
|-------|-----------------------------|------------------------------------|
| CNAME | `s1-ionos._domainkey`       | `s1-onecom-io.dkim1.ionos.com` *(example)* |
| CNAME | `s2-ionos._domainkey`       | `s2-onecom-io.dkim1.ionos.com` *(example)* |
| CNAME | `s42582905._domainkey`      | `s42582905.dkim1.ionos.com` *(example)*    |

> The exact hostnames/targets above are **placeholders** — use the precise three
> values IONOS shows in your account for onecom.io. If your DNS host has a "proxy"
> toggle (Cloudflare), make sure these CNAMEs are **DNS-only / proxy OFF**.

---

## Step 3 — DMARC

DMARC ties SPF and DKIM together and gives you visibility. Add **one** TXT record.
Start in monitor-only mode so nothing legitimate gets blocked while you watch.

| Field | Value |
|-------|-------|
| Type  | `TXT` |
| Name / Host | `_dmarc` (i.e. `_dmarc.onecom.io`) |
| Value | `v=DMARC1; p=none; rua=mailto:dmarc@onecom.io; ruf=mailto:dmarc@onecom.io; fo=1; adkim=s; aspf=s` |

What the tags mean:

- `p=none` — monitor only; don't change delivery yet. **Start here.**
- `rua=mailto:dmarc@onecom.io` — where aggregate reports are sent. Use a real
  mailbox you'll check (create `dmarc@onecom.io` if needed).
- `adkim=s; aspf=s` — strict alignment; the visible From domain must match the
  DKIM/SPF domain. Fine because you send as `@onecom.io` via IONOS.

### Tighten over time

After ~1–2 weeks of clean reports (everything passing SPF **or** DKIM with
alignment), ratchet the policy up:

```text
# week 2–3: quarantine a small %
v=DMARC1; p=quarantine; pct=25; rua=mailto:dmarc@onecom.io; adkim=s; aspf=s
# once confident: enforce fully
v=DMARC1; p=reject; rua=mailto:dmarc@onecom.io; adkim=s; aspf=s
```

`p=reject` is the end goal — it's what gives you the strongest anti-spoofing
protection and the best long-term inbox reputation.

---

## Step 4 — Make sure the app's From address aligns

DKIM/SPF alignment only works if ShopSignal sends **as `@onecom.io`** through
IONOS. Check `config.local.php`:

```php
'mail_from'      => 'no-reply@onecom.io',   // must be @onecom.io
'mail_from_name' => 'ShopSignal',
'smtp_host'      => 'smtp.ionos.com',
'smtp_username'  => 'a-real-onecom.io-mailbox',  // a mailbox you created in IONOS
'smtp_password'  => '••••••••',
'smtp_security'  => 'tls',                   // port 587
```

Two things that quietly break alignment:

- **`mail_from` on a different domain** (e.g. a gmail.com address) → DKIM/SPF
  won't align → DMARC fails. Keep it `@onecom.io`.
- **Sending as an address IONOS won't authorise** — the `smtp_username` mailbox
  must be allowed to send as `no-reply@onecom.io` (simplest: make
  `no-reply@onecom.io` itself the SMTP user, or an alias of it).

---

## Step 5 — Verify it works

**1. Send a test from the app.** Visit `mail-test.php` (admin only) and send
yourself a message. It reports whether SMTP or PHP `mail()` was used.

**2. Inspect the headers.** In Gmail, open the test mail → **⋮ → Show original**.
You want to see:

```text
SPF:   PASS  with domain onecom.io
DKIM:  PASS  with domain onecom.io
DMARC: PASS
```

**3. Check the DNS records directly:**

```bash
dig TXT onecom.io +short            # should include v=spf1 ... _spf-us.ionos.com
dig TXT _dmarc.onecom.io +short     # should show your v=DMARC1 record
# DKIM (selector varies — use the one from your IONOS CNAMEs):
dig CNAME s1-ionos._domainkey.onecom.io +short
```

**4. Use a checker.** Send an email to the address shown at
<https://www.mail-tester.com> for a 0–10 score, or run the lookups at
<https://easydmarc.com/tools/domain-scanner>.

---

## Rollout checklist

- [ ] Confirmed where onecom.io DNS is managed (Step 0)
- [ ] SPF present and passing (Step 1)
- [ ] DKIM enabled / 3 CNAMEs in place and passing (Step 2)
- [ ] DMARC `p=none` record added with a real `rua` mailbox (Step 3)
- [ ] `config.local.php` sends as `no-reply@onecom.io` via `smtp.ionos.com` (Step 4)
- [ ] `mail-test.php` delivers and Gmail "Show original" shows SPF/DKIM/DMARC = PASS (Step 5)
- [ ] Watched DMARC reports ~1–2 weeks, then moved `p=none` → `p=quarantine` → `p=reject`

---

## Troubleshooting

- **Still landing in spam with all three passing?** It's likely *reputation* — a
  brand-new sending domain has none. Volume of wanted, opened mail builds it over
  weeks. Make sure content isn't spammy (avoid link shorteners; keep a plain,
  clear body — which the app's emails already do).
- **SPF "too many DNS lookups" (permerror).** SPF allows max 10 includes. If you
  add more senders later (e.g. a marketing tool), watch the count.
- **DMARC failing despite SPF+DKIM passing.** That's an *alignment* problem — the
  From domain doesn't match the authenticated domain. Re-check Step 4.
- **Changes not visible yet.** DNS propagation can take up to 48 hours; re-run the
  `dig` checks rather than guessing.

---

## Sources

- [Using IONOS SPF to Improve Email Delivery — IONOS Help](https://www.ionos.com/help/domains/configuring-mail-servers-and-other-related-records/using-ionos-spf-to-improve-email-delivery/)
- [Email Authentication with DKIM — IONOS Help](https://www.ionos.com/help/domains/configuring-mail-servers-and-other-related-records/email-authentication-with-dkim/)
- [Configuring a DMARC Record for a Domain — IONOS Help](https://www.ionos.com/help/domains/configuring-mail-servers-and-other-related-records/configuring-a-dmarc-record-for-a-domain/)
- [SPF and DKIM setup for IONOS — EasyDMARC](https://easydmarc.com/blog/spf-and-dkim-setup-for-ionos/)
