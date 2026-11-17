-- Run with psql -U postgres -d erupi_demo -f database/schema.sql
-- Database erupi_demo must be created first. All records are fictional.
BEGIN;
CREATE TABLE IF NOT EXISTS consumers (
 id BIGSERIAL PRIMARY KEY, ivrs_no VARCHAR(30) NOT NULL UNIQUE,
 full_name VARCHAR(120) NOT NULL, mobile VARCHAR(20), created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS bills (
 id BIGSERIAL PRIMARY KEY, consumer_id BIGINT NOT NULL REFERENCES consumers(id),
 bill_month DATE NOT NULL, bill_amount NUMERIC(12,2) NOT NULL CHECK(bill_amount >= 0),
 subsidy_amount NUMERIC(12,2) NOT NULL CHECK(subsidy_amount >= 0 AND subsidy_amount <= bill_amount),
 due_date DATE NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'UNPAID'
 CHECK(status IN ('UNPAID','PAID')), UNIQUE(consumer_id,bill_month)
);
CREATE TABLE IF NOT EXISTS vouchers (
 id BIGSERIAL PRIMARY KEY, bill_id BIGINT NOT NULL UNIQUE REFERENCES bills(id),
 voucher_code VARCHAR(60) NOT NULL UNIQUE, amount NUMERIC(12,2) NOT NULL CHECK(amount >= 0),
 expires_at TIMESTAMPTZ NOT NULL, status VARCHAR(24) NOT NULL DEFAULT 'ACTIVE'
 CHECK(status IN ('GENERATED','ACTIVE','REDEEMED','EXPIRED','CLOSED_UNUSED','CANCELLED')),
 issued_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), redeemed_at TIMESTAMPTZ
);
CREATE TABLE IF NOT EXISTS payments (
 id BIGSERIAL PRIMARY KEY, bill_id BIGINT NOT NULL REFERENCES bills(id),
 voucher_id BIGINT REFERENCES vouchers(id), transaction_ref VARCHAR(80) NOT NULL UNIQUE,
 original_amount NUMERIC(12,2) NOT NULL, discount_amount NUMERIC(12,2) NOT NULL DEFAULT 0,
 paid_amount NUMERIC(12,2) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'PENDING'
 CHECK(status IN ('PENDING','SUCCESS','FAILED')),
 created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), paid_at TIMESTAMPTZ
);
CREATE UNIQUE INDEX IF NOT EXISTS one_successful_payment_per_bill ON payments(bill_id) WHERE status='SUCCESS';
CREATE TABLE IF NOT EXISTS voucher_transactions (
 id BIGSERIAL PRIMARY KEY, voucher_id BIGINT NOT NULL REFERENCES vouchers(id),
 payment_id BIGINT REFERENCES payments(id), action VARCHAR(40) NOT NULL,
 created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS audit_logs (
 id BIGSERIAL PRIMARY KEY, actor VARCHAR(80) NOT NULL, action VARCHAR(100) NOT NULL,
 entity_id BIGINT, created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
COMMIT;
