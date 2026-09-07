-- Soma Cashflow - Phase 7: savings & investments (e.g. UTT-style unit trusts)
--
-- An account belongs either to the personal ledger (owner_type='personal',
-- business_id NULL) or to a business (owner_type='business', business_id
-- set) - same access-control pattern as everything else: personal accounts
-- are owner-only, business accounts follow the business's RBAC (owner/admin
-- can edit, viewer is read-only).
--
-- Entries are either a 'deposit' (money added - principal, never counted as
-- growth) or a 'valuation' (what the account is worth as of that date, as
-- the user observed it - e.g. checking the UTT app). Growth is derived from
-- the gap between two valuations, MINUS any deposits made in between - see
-- includes/investment_helpers.php for the calculation.

CREATE TABLE IF NOT EXISTS investment_accounts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_type      ENUM('personal','business') NOT NULL,
    business_id     INT UNSIGNED    NULL,
    user_id         INT UNSIGNED    NOT NULL,   -- creator (for personal: the only viewer)
    name            VARCHAR(150)    NOT NULL,
    institution     VARCHAR(150)    NULL,        -- e.g. "UTT AMIS", "CRDB Bank"
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_invacc_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_invacc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_invacc_consistency CHECK (
        (owner_type = 'business' AND business_id IS NOT NULL) OR
        (owner_type = 'personal' AND business_id IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS investment_entries (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id      INT UNSIGNED    NOT NULL,
    entry_type      ENUM('deposit','valuation') NOT NULL,
    amount          DECIMAL(14,2)   NOT NULL,   -- deposit: amount added. valuation: total worth at that date.
    entry_date      DATE            NOT NULL,
    note            TEXT            NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventry_account FOREIGN KEY (account_id) REFERENCES investment_accounts(id) ON DELETE CASCADE,
    CONSTRAINT chk_inventry_amount_nonneg CHECK (amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_invacc_business ON investment_accounts(business_id);
CREATE INDEX idx_invacc_user ON investment_accounts(user_id);
CREATE INDEX idx_inventry_account_date ON investment_entries(account_id, entry_date);
