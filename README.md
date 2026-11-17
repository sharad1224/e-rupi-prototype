# E-RUPI Prototype — Phase 3 (Core PHP + PostgreSQL)

## Setup
1. Extract `e-rupi-prototype` into `C:\xampp\htdocs\`.
2. Start Apache in XAMPP and PostgreSQL service.
3. Create PostgreSQL database `erupi_demo` (if it does not exist).
4. Run `database/schema.sql` then `database/seed.sql` **only on a new database**. Existing Phase 2 schema works as-is; do not re-run seed on an existing database.
5. Check `config/database.php` credentials (`postgres` / `root` is the supplied local demo default).
6. Enable `extension=pdo_pgsql` in `C:\xampp\php\php.ini`, restart Apache.
7. Open `http://localhost/e-rupi-prototype/`.

## Workflow
- IDBI module: generate voucher for an unpaid bill, if not already issued.
- Consumer payment: enter IVRS; PHP loads bill, small AJAX call `api/check_voucher.php` retrieves voucher. Tick Apply Voucher, click Pay Now.
- Gateway: choose simulated SUCCESS or FAILED. PHP recalculates and records transaction in a PostgreSQL transaction. Success marks bill PAID and voucher REDEEMED (or CLOSED_UNUSED if not used). Failed attempt does not consume voucher.
- Result: PDF receipt available for successful payment; Track Your Payment shows history and receipt link.

## Important
- No real payments, IDBI integration, or actual e-RUPI issuance.
- Demo has no authentication and IVRS-only access; **do not deploy publicly or use real consumer data**.
- No JS except voucher fetch and UI amount preview. PHP always recalculates and validates amounts.
- Receipt is a dependency-free simple PDF with ASCII `INR` amounts (not a bank-issued receipt).
- For a fresh test after an already paid bill, use a different unpaid bill or a fresh demo database.
- If vouchers already show ACTIVE, they were issued in prior runs. Do not regenerate them.
