<<<<<<< HEAD
# Soma Cashflow

Multi-tenant finance tracking web app (PHP + MariaDB) supporting multiple
businesses, personal income tracking, inter-entity fund flows, savings &
investment growth tracking, AI-assisted transaction categorization, and
offline-first PWA support.

## Local setup (XAMPP)

1. Copy this project into `C:\xampp\htdocs\soma_cashflow`
2. Create the database:
   ```sql
   CREATE DATABASE soma_cashflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Import the schema:
   ```
   mysql -u root soma_cashflow < sql/001_init_schema.sql
   ```
4. Copy `config/config.sample.php` to `config/config.php` and fill in your DB credentials.
5. Start Apache + MySQL in the XAMPP control panel.
6. Visit `http://localhost/soma_cashflow/public/` in your browser.

## Development phases

This project is built in phases, each with its own test checklist before
moving to the next:

- **Phase 0** — Repo & scaffold (this phase)
- **Phase 1** — Auth & core entities (users, organizations, businesses)
- **Phase 2** — Manual transactions
- **Phase 3** — Personal ledger + inter-entity funding
- **Phase 4** — Statement engine (income statement, cash flow, custom date ranges)
- **Phase 5** — Multi-user & RBAC
- **Phase 6** — AI categorization (ChatGPT primary, Claude fallback)
- **Phase 7** — Savings & investments (deposit vs. growth tracking)
- **Phase 8** — Business photo gallery
- **Phase 9** — Offline sync (IndexedDB + idempotent upload)
- **Phase 10** — PWA (manifest, service worker, installability)
=======
# soma_cashflow
>>>>>>> 47f011f33ef0f37b7405b147dfdae957eea20fc9
