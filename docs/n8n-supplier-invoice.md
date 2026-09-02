# n8n workflow — supplier invoice → actual cost

Reads a supplier invoice from SharePoint, extracts the figures, matches each line
to a budgeted purchase line in Simple Accounting, and pushes the actual cost via
the REST API. Anything it can't match cleanly, or that runs over budget, goes to
a review channel instead of being posted.

```
SharePoint (new file)
   → download → extract (Azure DI or Claude) → normalise
   → GET /purchase/lines/pending  → match locally
   → POST /purchase/lines/costs  {dry_run:true}
   → clean?  yes → POST again (apply) → move file to /Applied
             no  → Teams card + move file to /Review
```

## Setup checklist

| Thing | Where |
|---|---|
| API base URL | e.g. `https://acc.hti.example/api/v1` |
| API token | Setup → API Tokens, abilities **`read`** + **`purchase:write`**. Store as an n8n credential (Header Auth: `Authorization` = `Bearer <token>`). |
| SharePoint site + folder ids | the "Supplier Invoices" library; sub-folders `Inbox`, `Applied`, `Review` |
| Extraction | Azure Document Intelligence *prebuilt-invoice* (you're already on Microsoft) **or** Anthropic API (Claude vision). Either works — it's one node. |
| Review channel | a Teams channel webhook (or Slack) |
| Tolerance | `OVER_BUDGET_TOLERANCE` (IDR) below which an over-budget line still auto-applies — start at `0` (always review overs) |

## Nodes

### 1. Microsoft SharePoint — Trigger
- Resource: **File**, Event: **file created**
- Site + Folder: the `Inbox` folder
- (Poll every 5–15 min, or use a Graph subscription if you have one)

### 2. HTTP Request — download the file
- `GET https://graph.microsoft.com/v1.0/sites/{siteId}/drive/items/{{$json.id}}/content`
- Auth: your Microsoft OAuth2 credential
- Response: **File / Binary**

### 3a. Extraction — Azure Document Intelligence  *(option A)*
- `POST {endpoint}/documentintelligence/documentModels/prebuilt-invoice:analyze?api-version=2024-11-30`
- Header `Ocp-Apim-Subscription-Key: {key}`, body = the binary
- Poll the `Operation-Location` until `status = succeeded`
- You get `documents[0].fields`: `VendorName`, `InvoiceId`, `InvoiceDate`, `InvoiceTotal`, `Items[]` (`Description`, `Amount`, `Date`, `ProductCode`)

### 3b. Extraction — Claude vision  *(option B — drop-in replacement for 3a)*
- `POST https://api.anthropic.com/v1/messages`
- Headers: `x-api-key: {key}`, `anthropic-version: 2023-06-01`
- Body:
```json
{
  "model": "claude-sonnet-4-5",
  "max_tokens": 2000,
  "messages": [{
    "role": "user",
    "content": [
      { "type": "document", "source": { "type": "base64", "media_type": "application/pdf",
        "data": "={{ $binary.data.data }}" } },
      { "type": "text", "text": "Extract this supplier invoice as JSON only, no prose:\n{ \"vendor\": str, \"invoice_no\": str, \"invoice_date\": \"YYYY-MM-DD\", \"currency\": str, \"lines\": [ { \"description\": str, \"guest_or_ref\": str, \"service_date\": \"YYYY-MM-DD\"|null, \"reservation_code\": str|null, \"amount\": number } ] }" }
    ]
  }]
}
```
- Parse `content[0].text` as JSON.

### 4. Code — normalise  (`Run Once for Each Item`, language JavaScript)

```js
// input: the extracted invoice (adapt field names to whichever extractor you used)
const inv = $json;

const lines = (inv.lines || inv.Items || []).map(l => ({
  description:      l.description ?? l.Description ?? '',
  party_name:      l.guest_or_ref ?? l.Description ?? '',
  service_date:    l.service_date ?? l.Date ?? inv.invoice_date ?? inv.InvoiceDate ?? null,
  res_code:        l.reservation_code ?? l.ProductCode ?? null,
  amount:          Number(String(l.amount ?? l.Amount ?? 0).replace(/[^0-9.\-]/g, '')),
}));

return {
  json: {
    vendor:       inv.vendor ?? inv.VendorName ?? '',
    invoice_no:   inv.invoice_no ?? inv.InvoiceId ?? '',
    invoice_date: inv.invoice_date ?? inv.InvoiceDate ?? null,
    currency:     (inv.currency ?? 'IDR').toUpperCase(),
    file_name:    $('Microsoft SharePoint Trigger').item.json.name,
    file_web_url: $('Microsoft SharePoint Trigger').item.json.webUrl,
    lines,
  },
};
```

### 5. HTTP Request — pending lines for this supplier
- `GET {{$env.API_BASE}}/purchase/lines/pending`
- Auth: the API token credential
- Query:
  - `supplier` = `={{ $json.vendor }}`
  - `from` = `={{ $json.invoice_date ? DateTime.fromISO($json.invoice_date).minus({days:10}).toISODate() : '' }}`
  - `to`   = `={{ $json.invoice_date ? DateTime.fromISO($json.invoice_date).plus({days:10}).toISODate()  : '' }}`
  - `limit` = `500`

### 6. Code — match extracted lines to pending lines

```js
const invoice = $('Code - normalise').item.json;
const pending = $json.lines || [];               // from GET /purchase/lines/pending
const norm = s => String(s || '').toLowerCase().replace(/\s+/g, ' ').trim();

const costs = [];
const unmatched = [];

for (const el of invoice.lines) {
  let hit = null;

  // 1. supplier's reservation code (best after booking_ref, which suppliers rarely quote)
  if (el.res_code) {
    const c = pending.filter(p => norm(p.res_code) === norm(el.res_code));
    if (c.length === 1) hit = c[0];
  }
  // 2. guest / booking name + amount close to budget
  if (!hit && el.party_name) {
    const c = pending.filter(p =>
      norm(p.party_name).includes(norm(el.party_name)) ||
      norm(el.party_name).includes(norm(p.party_name)));
    if (c.length === 1) hit = c[0];
    else if (c.length > 1) {
      const byAmt = c.filter(p => Math.abs(p.budget - el.amount) / (p.budget || 1) < 0.25);
      if (byAmt.length === 1) hit = byAmt[0];
    }
  }

  if (hit) {
    costs.push({
      // pass booking_ref so the API matches exactly and skips its own fallback
      booking_ref: hit.booking_ref || undefined,
      res_code:    hit.booking_ref ? undefined : (el.res_code || undefined),
      supplier:    hit.booking_ref ? undefined : invoice.vendor,
      service_date: hit.booking_ref ? undefined : el.service_date,
      party_name:  el.party_name || undefined,
      budget:      hit.budget,
      cost:        el.amount,
      supplier_invoice_ref:  invoice.invoice_no || undefined,
      supplier_invoice_date: invoice.invoice_date || undefined,
      remark: `auto from ${invoice.file_name}`,
    });
  } else {
    unmatched.push({ description: el.description, party_name: el.party_name, amount: el.amount });
  }
}

return { json: { costs, unmatched, invoice } };
```

### 7. HTTP Request — dry run
- `POST {{$env.API_BASE}}/purchase/lines/costs`
- Auth: API token credential
- Body (JSON): `{ "dry_run": true, "window_days": 5, "costs": {{ $json.costs }} }`

### 8. IF — is it clean?
- Condition (all true):
  - `{{ $('Code - match').item.json.unmatched.length }}` **equals** `0`
  - `{{ $json.summary.not_found }}` **equals** `0`
  - `{{ $json.summary.ambiguous }}` **equals** `0`
  - `{{ $json.summary.errors }}` **equals** `0`
  - `{{ $json.results.filter(r => r.over_budget && (r.variance > $env.OVER_BUDGET_TOLERANCE)).length }}` **equals** `0`

### 9a. TRUE → apply
- `POST {{$env.API_BASE}}/purchase/lines/costs`
- Body: `{ "window_days": 5, "costs": {{ $('Code - match').item.json.costs }} }`  *(no `dry_run`)*
- Then **Microsoft SharePoint → move file** to `Applied/`

### 9b. FALSE → review
- **Microsoft Teams / Slack** message:
  ```
  ⚠ Supplier invoice needs review — {{ $('Code - normalise').item.json.vendor }}
  File: {{ $('Code - normalise').item.json.file_web_url }}
  Unmatched lines: {{ JSON.stringify($('Code - match').item.json.unmatched) }}
  Dry-run summary: {{ JSON.stringify($('HTTP - dry run').item.json.summary) }}
  Over budget: {{ JSON.stringify($('HTTP - dry run').item.json.results.filter(r => r.over_budget)) }}
  ```
- **Microsoft SharePoint → move file** to `Review/`
- A person resolves it in the app (or fixes the file and re-drops it in `Inbox` — re-processing is safe: already-actual lines come back `unchanged`).

## Notes

- **Idempotent.** Re-running the same invoice → every line `unchanged`, nothing re-posted.
- **Never auto-posts over paid invoices** — the API returns `error` for those; they land in review.
- **`booking_ref` beats everything.** If your extractor can ever read the Jambix ID-Number off a supplier doc, pass it and skip the fuzzy matching.
- Start with the IF in 8 very strict (tolerance 0). Loosen once you trust the match rate.
