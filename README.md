# Simple Accounting (CodeIgniter 4)

A double-entry bookkeeping application: chart of accounts, multi-currency journals
with an approval (draft → posted) flow, AR/AP subledgers, period locking, and the
core financial statements (Trial Balance, General Ledger, Neraca / Balance Sheet,
Laba Rugi / Income Statement, AR & AP aging).

Built on **CodeIgniter 4.7** + **CodeIgniter Shield** (authentication & roles),
MySQL/MariaDB, no front-end build step.

---

## Requirements

- PHP 8.2+ with `intl`, `mbstring`, `mysqli`
- MySQL 5.7+ / MariaDB 10.4+
- Composer

## Install

```bash
composer install
cp env .env
```

Edit `.env` — set the database block and `app.baseURL`:

```
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8080/'

database.default.hostname = 127.0.0.1
database.default.database  = simple_accounting
database.default.username  = root
database.default.password  =
database.default.DBDriver  = MySQLi
```

Create the schema and seed the starter data:

```bash
php spark key:generate
php spark migrate --all
php spark db:seed InitialSeeder
```

`InitialSeeder` loads:

- **Currencies** — IDR (base), USD, EUR
- **Chart of accounts** — a generic Indonesian-style COA (~80 accounts, codes 1000–8400)
- **Admin user** — `admin@example.com` / `admin12345` &nbsp;→ **change this password immediately**

Run it:

```bash
php spark serve --port 8080
```

Open <http://localhost:8080> and log in.

### Reset to a clean database

```bash
php spark migrate:refresh --all
php spark db:seed InitialSeeder
```

---

## Roles

| Role  | Can |
|-------|-----|
| **admin** | everything — post & void journals, close periods, manage the COA, customers, suppliers, currencies, settings and users |
| **staff** | create & edit **draft** journals, view all reports. Cannot post, void, or close periods. |

New self-registered users land in `staff`. Manage roles under **Users** (admin only).
Permissions are defined in `app/Config/AuthGroups.php`.

---

## How it works

### Journals

- A journal has one **currency** and one **exchange rate** to the base currency (IDR).
  Each line stores the amount twice: transaction currency (`debit`/`credit`) and
  base currency (`debit_base`/`credit_base`). **All reports use the base amounts.**
- Journals are created as **draft**, then **posted**. Only posted (and voided) journals
  hit the ledger; drafts never do.
- Posting is refused unless: debits = credits, at least two lines, no header/inactive
  accounts, the period is open, and every line on an AR/AP control account carries a
  customer / supplier.
- **Void** marks the journal `void` and books an automatic, posted **reversing entry**
  dated today, so the ledger stays balanced even when the original sits in a closed period.
- Draft journals can be edited or deleted; posted journals cannot — correct them with a void.

### Control accounts

`app/Config/Accounting.php` (overridable at runtime from **Settings**) names the special
accounts the reports depend on: AR control, AP control, retained earnings, FX gain/loss,
rounding. An account becomes an AR or AP subledger by setting its **Subledger** field to
*Customer* or *Supplier* in the COA editor.

### Reports

Trial Balance, General Ledger, Neraca / Balance Sheet, Laba Rugi / Income Statement,
and AR/AP aging — all derived from posted (and voided-with-reversal) journal lines in
base currency. Every report is print-friendly.

The **Balance Sheet** and **Income Statement** have a **Compare by: month / quarter /
year** selector: the From–To span is split into one column per period (income
statement = movement in each period; balance sheet = snapshot at each period end),
so you get side-by-side comparatives like the old workbook's `LABA RUGI` 2023-vs-2022.

### Dashboard

The dashboard has an editable **From / To** period (defaults to year-to-date) driving
the KPI tiles and four inline-SVG charts — Revenue vs Expense, Net Income, Cash & Bank
trend (month-end), and Expense breakdown. Charts are rendered server-side by
[`App\Libraries\Chart\Svg`](simple-accounting/app/Libraries/Chart/Svg.php) — no
JavaScript, no CDN.

### Periods

**Periods** lists each month of a year. Closing a month blocks new postings and voids
dated in it. Reopen at any time (admin).

### Importing journals from a spreadsheet

**Journals → Import from spreadsheet** loads a `.xlsx` / `.xls` / `.csv` as draft journals:

1. **Upload** the file (needs `phpoffice/phpspreadsheet`, already required).
2. **Map columns** — point the importer's logical fields (`Journal group / no.`,
   `Date`, `Account`, `Debit`, `Credit`, and optional `Description`, `Reference`,
   `Currency`, `Rate`, `Customer / Supplier name`, `Memo`, `Source`) at the sheet's
   columns. Pick the sheet, header row, and date format.
3. **Match accounts** — every distinct account label in the file is mapped once to a
   COA account (exact code/name matches are pre-selected). Mappings are saved in
   `account_aliases` and reused on later imports.
4. **Preview** — shows each journal it built, flags unbalanced ones and unknown
   accounts, and lists customers/suppliers it will auto-create from the name column.
5. **Commit** — creates the valid journals as **draft** under an import batch.
   Journals with errors, and any `Journal #` that already exists, are skipped.

On the **batch page** you can *Post all drafts* (admin) or *Delete draft journals*
to undo the import. Rows that share a value in the mapped *Journal group / no.*
column become one journal; the header (date, description, currency, rate) is taken
from that group's first row.

### REST API

A token-authenticated JSON API (`/api/v1`) lets a third-party system push sales and
purchase invoices into the ledger, with read-only lookups for accounts, customers,
suppliers and jobs. Each token is bound to one company. Generate and revoke tokens
under **Setup → API Tokens** (`/api-tokens`, admin).

Full reference: [`docs/API.md`](docs/API.md) &nbsp;·&nbsp; styled version: `docs/api.html`.

---

## Project layout

```
app/
  Config/Accounting.php        company profile + control-account codes
  Config/AuthGroups.php        roles & permissions (Shield)
  Config/Routes.php
  Controllers/                 Dashboard, Journal, Account, Party (Customer/Supplier),
                               Currency, Period, Report, Settings, User
  Libraries/Accounting/
    JournalPoster.php          validate / save / post / void
    Ledger.php                 all report math (balances, TB, GL, BS, P&L, aging)
  Models/                      thin query models
  Database/Migrations/         schema (currencies, accounts, journals, ...)
  Database/Seeds/              CurrencySeeder, ChartOfAccountsSeeder, AdminUserSeeder
  Views/                       server-rendered, one layout (app/Views/layout.php)
public/assets/app.css          the entire stylesheet (no CDN, print-friendly)
```

## Notes

- CSRF protection is off by default (see `app/Config/Filters.php`). Enable the `csrf`
  global filter before deploying anywhere non-local.
- Automatic FX gain/loss on settlement is **not** posted for you in this version — add
  the difference as a manual line to the FX Gain / FX Loss account when settling a
  foreign-currency receivable or payable at a different rate.
- Aging buckets are measured from the journal date (no invoice due-date/terms model yet).
