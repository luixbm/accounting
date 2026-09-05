# Simple Accounting — REST API v1

A small, token-authenticated JSON API for pushing **sales and purchase invoices**
into the ledger from a third‑party system (e.g. Jambix), plus read‑only lookups
for mapping your data to this chart of accounts.

- **Base URL:** `https://YOUR-HOST/api/v1`
- **Format:** JSON request and response bodies (`Content-Type: application/json`)
- **Auth:** bearer token, one token per company
- **Versioning:** the version is in the path (`/api/v1`). Breaking changes ship as `/api/v2`.

---

## 1. Authentication

Every request must carry an API token:

```
Authorization: Bearer sa_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

`X-Api-Key: sa_...` is accepted as an alternative header.

### Getting a token

An administrator creates tokens in the app under **Setup → API Tokens**
(`/api-tokens`). When a token is generated its raw value is shown **once** — copy
it immediately; only a hash is stored and it cannot be retrieved later. Revoking a
token takes effect immediately.

### Company scoping

A token is **bound to the company that was active when it was created**. Every
request made with that token reads and writes only that company's data — there is
no company parameter and no way to reach another company with the same token. Use
one token per company you integrate.

### Abilities

Each token grants one or more abilities:

| Ability          | Allows                                                        |
|------------------|--------------------------------------------------------------|
| `read`           | `GET /ping`, `/accounts`, `/customers`, `/suppliers`, `/jobs`, `/jobs/{code}`, `/{kind}/invoices/{ref}`, `/purchase/lines/pending` |
| `sales:write`    | `POST /sales/invoices` (including posting)                    |
| `purchase:write` | `POST /purchase/invoices`, `POST /purchase/lines/costs` (including posting) |
| `job:write`      | `POST /jobs` (create / update jobs from Jambix dossier data)  |

A request that needs an ability the token lacks returns **403**.

---

## 2. Conventions

**Amounts.** Line `amount` values are in the invoice's transaction currency.
Response objects also include `*_base` fields converted to the base currency
(IDR) at `exchange_rate`. `total = subtotal + ppn_amount − pph_amount`.

**Currency.** Omit `currency` to use the base currency (rate `1`). For any other
currency, send `currency` (ISO code, e.g. `"USD"`) **and** a positive
`exchange_rate` (units of base currency per 1 unit of the invoice currency).

**Dates.** `YYYY-MM-DD`.

**Idempotency.** Every invoice you push must carry a unique `external_id` (your
system's ID for it, ≤ 80 chars). It is unique per company. Re‑`POST`ing the same
`external_id` does **not** create a duplicate or modify anything — it returns the
existing invoice with `"created": false` and HTTP `200`. Corrections are done in
the app UI, not through the API.

**Posting.** New invoices are created as **drafts**. Send `"post": true` to post
them to the ledger in the same call. If posting fails (e.g. a closed period) the
invoice is still saved as a draft and the reasons are returned in
`invoice.post_errors`.

---

## 3. Errors

Non‑2xx responses have this shape:

```json
{
  "error": {
    "code": "invalid_request",
    "message": "The invoice could not be accepted.",
    "details": ["Line 1: unknown account_code '9999'."]
  }
}
```

| HTTP | `error.code`      | When                                                        |
|------|------------------|------------------------------------------------------------|
| 401  | `missing_token`  | No `Authorization` / `X-Api-Key` header                     |
| 401  | `invalid_token`  | Token unknown or revoked                                    |
| 403  | `forbidden`      | Token lacks the ability the endpoint needs                  |
| 404  | `not_found`      | No matching invoice                                         |
| 422  | `invalid_request`| Payload rejected; see `details[]` for the specific reasons  |

`200` is returned for a successful read and for an idempotent no‑op create.
`201` is returned only when a new invoice is actually created.

---

## 4. Endpoints

### `GET /ping`

Health / auth check. Ability: none beyond a valid token.

```json
{
  "ok": true,
  "company": { "id": 1, "code": "C7", "name": "Company Name 2" },
  "token": "Jambix production",
  "abilities": ["read", "sales:write", "purchase:write"]
}
```

---

### `GET /accounts`

Postable chart‑of‑accounts entries, for mapping your GL codes. Ability: `read`.

Query parameters (optional): `type` (e.g. `revenue`, `expense`, `cogs`, `asset`,
`liability`), `q` (substring match on code or name). Max 500 rows.

```json
{
  "accounts": [
    {
      "code": "4100",
      "name": "Pendapatan Jasa",
      "type": "revenue",
      "normal_balance": "K",
      "is_cash": false,
      "subledger": "none"
    }
  ]
}
```

---

### `GET /customers` · `GET /suppliers` · `GET /jobs`

Ability: `read`. `customers`/`suppliers` accept `?q=` (code or name substring).

```json
{ "customers": [ { "code": "C001", "name": "Customer 1", "npwp": null, "is_active": true } ] }
```

```json
{ "jobs": [ { "code": "JOB-1", "name": "Bali Tour Batch A", "status": "open" } ] }
```

---

### `GET /jobs/{code}`

Fetch one job by its **dossier number** (job code). Ability: `read`. Returns the
Jambix reference figures alongside the job header. `net_ref` / `margin_ref` are
`sales_ref − buy_ref` and its margin — **quoted** figures, not the posted ledger;
the job P&L reports are always built from the posted transactions.

```json
{
  "job": {
    "code": "26216", "name": "Ruud Van Stijn H112250419", "status": "open",
    "customer_id": 11, "arrival_date": "2026-01-06", "end_date": "2026-02-08",
    "created_on": "2025-08-22", "pax": 2, "category": "RTN", "jambix_status": "Booking",
    "sales_ref": 96889892.00, "buy_ref": 69760722.24, "net_ref": 27129169.76, "margin_ref": 28.0
  }
}
```

`404 not_found` if no job has that code in the token's company.

---

### `POST /jobs`

Create or update jobs from a Jambix job / dossier report. Ability: `job:write`.
Upsert is keyed on `code` (the dossier number) within the token's company: a new
code creates a job, an existing one is patched with the fields you send.

**These are reference figures only.** Nothing here posts to the ledger — the job
P&L reports keep reading the posted purchase / sales transactions. `sales` / `buy`
are stored as `sales_ref` / `buy_ref` for over/under-quote analysis.

#### Request body

A single job object, or `{ "jobs": [ … ] }` for up to 1000 at once.

| Field                         | Req | Notes |
|-------------------------------|-----|-------|
| `code`                        | ✓   | Dossier number → job code (max 30). Also accepts `dossier_nr`. |
| `name`                        |     | Dossier name → job name. Kept unchanged on update if omitted. |
| `customer` / `client`         |     | Name (string) or `{ "code": … }` / `{ "name": … }`. Resolved case-insensitively, created if new. |
| `arrival_date` / `travel_date`|     | Travel / arrival date (`YYYY-MM-DD`). Stored as `start_date`. Not overwritten on update if omitted. |
| `end_date`                    |     | `YYYY-MM-DD`. |
| `created_on`                  |     | When the dossier was created in Jambix (`YYYY-MM-DD`). |
| `pax`                         |     | Integer traveller count. |
| `sales`                       |     | Jambix quoted sales total → `sales_ref`. |
| `buy`                         |     | Jambix quoted buy / cost total → `buy_ref`. |
| `category` / `cat`            |     | e.g. `B2C`, `B2B`, `RTN` (max 10). |
| `status`                      |     | Raw Jambix status. `cancelled` / `done` / `complete` / `closed` / `finished` / `archived` → job `closed`, anything else → `open`. Stored verbatim in `jambix_status`. |

```bash
curl -X POST "$BASE/jobs" -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -d '{
  "jobs": [
    { "code": "26216", "name": "Ruud Van Stijn H112250419", "customer": "Riksja Indonesie",
      "arrival_date": "2026-01-06", "end_date": "2026-02-08", "created_on": "2025-08-22",
      "pax": 2, "sales": 96889892, "buy": 69760722.24, "category": "RTN", "status": "Booking" }
  ]
}'
```

#### Response — `200` if at least one job was accepted, `422` if none were

```json
{
  "summary": { "created": 0, "updated": 1, "failed": 0, "customers": 0 },
  "results": [
    { "result": "updated", "code": "26216", "job_id": 2574, "index": 0 }
  ]
}
```

Each `results` entry is `{ "result": "created" | "updated" | "error", "code", "index", "job_id"?, "errors"? }`.
Rows are processed independently — a bad row fails on its own without rolling back the good ones.

---

### `POST /sales/invoices` · `POST /purchase/invoices`

Create (or idempotently return) an invoice. Ability: `sales:write` /
`purchase:write`.

#### Request body

| Field            | Type            | Required | Notes |
|------------------|-----------------|----------|-------|
| `external_id`    | string ≤ 80     | **yes**  | Your unique ID for this invoice. Idempotency key. |
| `customer` / `supplier` | object or string | **yes** | See *Party* below. Use `customer` for sales, `supplier` for purchase (`party` also accepted). |
| `invoice_date`   | date            | **yes**  | |
| `lines`          | array           | **yes**  | ≥ 1 line. See *Line* below. |
| `due_date`       | date            | no       | |
| `currency`       | string (ISO)    | no       | Defaults to base currency. |
| `exchange_rate`  | number > 0      | cond.    | Required when `currency` is not the base currency. |
| `reference`      | string          | no       | Stored as the supplier/customer reference (e.g. their PO no). |
| `description`    | string          | no       | Invoice memo. |
| `ppn_amount`     | number          | no       | VAT amount. Default `0`. |
| `pph_amount`     | number          | no       | Withholding amount. Default `0`. |
| `post`           | boolean         | no       | `true` = post to the ledger immediately. Default `false` (draft). |
| `custom_fields`  | object          | no       | `{ "<field_key>": value }` — invoice-level custom fields defined for this company (Setup → Custom Fields). Only defined keys are stored; unknown keys are ignored. Values are **merged** onto whatever is already stored, so a partial payload does not clear the others. Sent on a repeat `external_id` push too — this is the one part of the invoice a repeat call can still change. Any custom field marked *required* must be present when `custom_fields` is included. |

**Party** (`customer` / `supplier`):

- `{ "code": "C001" }` — must already exist, else `422`.
- `{ "name": "PT Contoh" }` — matched by exact name (case‑insensitive); **created
  automatically** if not found.
- `{ "code": "C001", "name": "PT Contoh" }` — tries `code` first, falls back to
  matching/creating by `name`.
- A bare string (`"C001"`) is treated as a `code`.

**Line** (each element of `lines`):

| Field         | Type        | Required | Notes |
|---------------|-------------|----------|-------|
| `amount`      | number > 0  | **yes**  | Line amount in the invoice currency. |
| `account_code`| string      | no       | Postable (non‑header, active) account. **Omitted → the company's Jambix fallback account** (`Accounting.jambixSalesAcct`, default `42000`, for sales; `Accounting.jambixFallbackAcct`, default `52000`, for purchase). |
| `job_code`    | string      | no       | **Auto‑created if new** (open job; linked to this invoice's customer for sales). |
| `job_name`    | string      | no       | Name to give an auto‑created job (defaults to the code). |
| `description` | string      | no       | |
| `booking_ref` | string      | no       | External booking / dossier id (Jambix). |
| `service_date`| date        | no       | |
| `party_name`  | string      | no       | Traveller / booking name. |
| `units`, `nights` | string  | no       | Free‑text descriptors. |
| `pax`, `duration` | string  | no       | Sales only — Ttl Pax / trip duration. |
| `remark`      | string      | no       | Sales only — free‑text line note. |

#### Response

`201 Created` (new) or `200 OK` (already existed):

```json
{
  "created": true,
  "invoice": {
    "id": 6,
    "kind": "sales",
    "internal_no": "SI-2608-0002",
    "external_id": "JMBX-INV-42",
    "status": "posted",
    "source": "api",
    "customer_id": 4,
    "reference": "PO-123",
    "invoice_date": "2026-08-15",
    "due_date": "2026-09-14",
    "currency_id": 1,
    "exchange_rate": 1,
    "subtotal": 3500000,
    "ppn_amount": 0,
    "pph_amount": 0,
    "total": 3500000,
    "total_base": 3500000,
    "journal_id": 61,
    "custom_fields": { "promise_date": "2026-09-25", "po_number": "PO-123" },
    "lines": [
      { "account_id": 44, "description": "Consulting July", "job_id": null, "amount": 3000000, "amount_base": 3000000 }
    ]
  }
}
```

Purchase responses use `supplier_id` instead of `customer_id`. `journal_id` is
`null` while the invoice is a draft. `custom_fields` echoes the stored values
(`{}` when none are defined). If `post` was requested but failed,
`invoice.post_errors` (array of strings) is present and `status` is `"draft"`.

---

### `GET /sales/invoices/{ref}` · `GET /purchase/invoices/{ref}`

Fetch one invoice by **`external_id` or `internal_no`**. Ability: `read`.
`404` if nothing matches.

```json
{ "invoice": { "...": "same shape as the create response's invoice object" } }
```

---

### `GET /purchase/lines/pending`

Purchase-invoice lines still on a **budget** figure (`cost_source != actual`) on a
live invoice — i.e. what you're still expecting a supplier invoice for. Ability:
`read`. Use it to feed the matcher: fetch the lines for a supplier + date window,
match your scanned invoice against them locally, then call `POST …/costs`.

**Query:** `supplier` (code or name), `from` / `to` (service-date window,
`YYYY-MM-DD`), `booking_ref`, `res_code`, `limit` (≤ 500, default 200), `offset`.

```json
{
  "total": 3042,
  "count": 2,
  "limit": 200,
  "offset": 0,
  "lines": [
    {
      "line_id": 1973,
      "booking_ref": "26348-5717003",
      "res_code": null,
      "service_date": "2026-05-06",
      "party_name": "Sofie Marie DK1496259",
      "description": "KOT-03-Gili-Komodo Adventure Open Trip",
      "budget": 16500000,
      "current_amount": 16500000,
      "cost_source": "budget",
      "currency": "IDR",
      "supplier": { "code": "S0006", "name": "Happy Trails Indonesia" },
      "dossier": { "code": "26348", "name": "Sofie Marie DK1496259" },
      "invoice": { "id": 787, "internal_no": "PI-2609-0783", "external_id": "26348-happy-trails-indonesia", "status": "posted", "paid": false }
    }
  ]
}
```

---

### `POST /purchase/lines/costs`

Apply **actual** supplier costs onto purchase-invoice lines that were imported
from Jambix with a **budget** figure, then re-post each affected invoice.
Ability: `purchase:write`.

Built for an n8n flow that reads a supplier's own invoice and pushes the numbers
back. Each item is matched to **exactly one** line — anything that resolves to
zero or several lines is reported, never guessed.

#### Request body

```json
{
  "dry_run": false,
  "window_days": 2,
  "costs": [
    {
      "booking_ref": "29593-6096761",
      "cost": 2100000,
      "supplier_invoice_ref": "PURI-8841",
      "supplier_invoice_date": "2026-05-03",
      "account_code": "52000"
    },
    {
      "supplier": "Margo Utomo Eco Resort",
      "service_date": "2026-05-01",
      "party_name": "Smith H12345",
      "budget": 500000,
      "cost": 480000
    }
  ]
}
```

| Field | Req. | Notes |
|---|---|---|
| `costs[]` | ✔ | 1–2000 items. `lines[]` is accepted as an alias. |
| `cost` | ✔ | Actual cost, transaction currency, `>= 0`. `amount` is an alias. |
| `booking_ref` | — | The Jambix `ID-Number`. **Preferred** — exact, unambiguous. |
| `res_code` | — | The **supplier's own reservation code** (stored on the line at import from Jambix "Res. Code"). Used only on a unique hit; a miss falls through to `supplier` + `service_date`. `reservation_ref` accepted as an alias. |
| `supplier` | — | Fallback match key (with `service_date`). Supplier **code or name**. `supplier_ref` / `supplier_name` accepted as aliases. |
| `service_date` | — | `YYYY-MM-DD`. Used with `supplier`. Tried exact first, then ± `window_days`. |
| `party_name` | — | Narrows a multi-line `supplier`+`service_date` match (substring, either direction). |
| `budget` | — | Narrows by the line's **current** amount (exact). |
| `account_code` | — | Re-point the line to this cost account (non-header, active). Default: leave as-is. |
| `remark` | — | Free-text note stored on the line (`cost_remark`) — e.g. why the actual ran over budget. |
| `supplier_invoice_ref` / `supplier_invoice_date` | — | Recorded on the line (`supp_inv_ref` / `supp_inv_date`) — the audit trail behind the budget→actual flip. |
| `promise_date` | — | Planned payment date (often a few days before/after `service_date`, not the invoice's formal due date). Stored on the line; left unset if omitted (existing value carried through, never cleared implicitly). On the Invoice Review page this defaults to the matched line's `service_date` and is editable per row before confirming. |
| `window_days` | — | Top-level default (0–15, default `2`); per-item override allowed. |
| `dry_run` | — | Resolve and report only; write nothing. |

On a match: the line's amount becomes `cost`, `cost_source` flips to `actual`,
the supplier-invoice fields are stored. Changes are grouped per invoice and
applied with **one** re-post each. A **draft** invoice (all-budget-zero on
import) that now carries a real cost is **posted**.

#### Response — always `200` unless the body itself is malformed

```json
{
  "dry_run": false,
  "summary": {
    "received": 2, "applied": 1, "would_apply": 0, "unchanged": 0,
    "ambiguous": 1, "not_found": 0, "errors": 0, "over_budget": 1, "invoices_reposted": 1
  },
  "results": [
    {
      "index": 0, "match": "booking_ref", "status": "applied",
      "booking_ref": "29593-6096761",
      "invoice": { "id": 5, "internal_no": "PI-2609-0001", "external_id": "29593-hotel-puri-rai", "status": "posted" },
      "budget": 2000000, "old_amount": 2000000, "new_amount": 2350000,
      "variance": 350000, "over_budget": true
    },
    {
      "index": 1, "match": "supplier+service_date~2d", "status": "ambiguous",
      "message": "3 lines matched; add party_name / budget, or use booking_ref.",
      "candidates": ["25785-5647201", "25785-5647202", "25785-5647208"]
    }
  ],
  "invoices": [
    { "id": 5, "internal_no": "PI-2609-0001", "status": "posted", "lines_changed": 1 }
  ]
}
```

Per-item `status`: `applied` · `would_apply` (dry run) · `unchanged` (already
that actual figure) · `ambiguous` · `not_found` · `error`. Re-send freely —
already-actual lines at the same figure come back `unchanged` with nothing
re-posted.

Re-post failures (period locked, invoice already has a payment) mark that
item `error` with the reason; other items in the same request still apply.

---

### `POST /purchase/lines/review`

Same idea as `POST /purchase/lines/costs`, but instead of writing immediately
it **queues the batch for human review** in the app (Purchases -> Invoice
Review) — a resolved-but-unwritten inbox, in place of (or alongside) posting
to a Teams/Slack channel. Ability: `purchase:write`.

Each item is resolved with a dry run at intake time and stored with its match
result. A person then confirms individual lines or a whole batch from the
Invoice Review page; confirming re-resolves and applies for real at that
point (not at intake time), so drift — a booking updated after you POSTed,
a line already actualised another way — is caught rather than blindly
replayed.

#### Request body

```json
{
  "source": "n8n",
  "file_name": "black-horse-weekly-recap.pdf",
  "file_url": "https://.../black-horse-weekly-recap.pdf",
  "vendor": "Black Horse Bali",
  "window_days": 5,
  "items": [
    { "booking_ref": "26495-6106793", "cost": 550002, "party_name": "Carola van Roon H184250128",
      "description": "Munduk - Pemuteran (transfer)", "supplier_invoice_ref": "WKT-260501" }
  ]
}
```

| Field | Req. | Notes |
|---|---|---|
| `items[]` | ✔ | 1–2000 items, same shape as `costs[]` on `POST /purchase/lines/costs` (`costs` accepted as an alias for the array itself). `dry_run` is ignored — intake is always a dry run. |
| `source` | — | Free text, e.g. `n8n`. Defaults to `n8n`. |
| `file_name` / `file_url` | — | The source document, shown on the review page. |
| `vendor` | — | Label for the batch header; falls back to each item's own match if omitted. |
| `window_days` | — | Same meaning as on `.../costs`. |

#### Response — `201`

```json
{
  "batch_id": 42,
  "summary": {
    "received": 4, "applied": 0, "would_apply": 3, "unchanged": 0,
    "ambiguous": 0, "not_found": 1, "errors": 0, "over_budget": 1, "invoices_reposted": 0
  }
}
```

`summary` mirrors `.../costs`'s dry-run summary — useful for an immediate
Teams/Slack "N lines need a look" notice — but nothing is written; the
`batch_id` is only a reference for your own logs, there's no GET for it (open
the app to review).

---

## 5. Worked example

```bash
BASE="https://your-host/api/v1"
TOKEN="sa_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"

# 1. Confirm the token and see which company it targets
curl -s "$BASE/ping" -H "Authorization: Bearer $TOKEN"

# 2. Look up the revenue account you'll book sales to
curl -s "$BASE/accounts?type=revenue" -H "Authorization: Bearer $TOKEN"

# 3. Push an invoice and post it
curl -s -X POST "$BASE/sales/invoices" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "external_id": "JMBX-INV-42",
    "customer": { "code": "C001", "name": "PT Contoh" },
    "invoice_date": "2026-08-15",
    "due_date": "2026-09-14",
    "reference": "PO-123",
    "ppn_amount": 0,
    "post": true,
    "lines": [
      { "account_code": "4100", "description": "Consulting", "amount": 3000000, "job_code": "JOB-1" },
      { "account_code": "4100", "description": "Expenses",   "amount": 500000 }
    ]
  }'

# 4. Safe to retry — same external_id returns the existing invoice (created: false, HTTP 200)
curl -s "$BASE/sales/invoices/JMBX-INV-42" -H "Authorization: Bearer $TOKEN"

# 5. Later: push actual supplier costs onto Jambix-imported purchase lines
curl -s -X POST "$BASE/purchase/lines/costs" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "costs": [
      { "booking_ref": "29593-6096761", "cost": 2100000, "supplier_invoice_ref": "PURI-8841" }
    ]
  }'
```

---

## 6. Limitations (v1)

- **Invoices are create + read only.** No invoice update or delete over the API —
  corrections are made in the app. A repeated `external_id` never mutates the
  existing invoice, **except `custom_fields`**, which a repeat push still writes
  (merged over the stored values). (`POST /purchase/lines/costs` also edits
  Jambix-imported lines in place.)
- No payment / receipt endpoints yet.
- No pagination on the lookup endpoints (capped at 500 rows; use `q`).
- No per‑token rate limiting.
- CORS is not enabled — the API is intended for server‑to‑server use.
