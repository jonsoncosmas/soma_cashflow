-- Soma Cashflow - Phase 2: transactions
--
-- type meanings for balance calculation:
--   income        -> increases business cash
--   expense       -> decreases business cash
--   loan_received -> increases business cash (money borrowed in)
--   loan_given    -> decreases business cash (money lent out)

CREATE TABLE IF NOT EXISTS transactions (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id       INT UNSIGNED    NOT NULL,
    user_id           INT UNSIGNED    NOT NULL,
    type              ENUM('income','expense','loan_received','loan_given') NOT NULL,
    category          VARCHAR(100)    NOT NULL,
    amount            DECIMAL(14,2)   NOT NULL,
    description       TEXT            NULL,
    transaction_date  DATE            NOT NULL,
    created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_txn_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_txn_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT chk_txn_amount_positive CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_txn_business ON transactions(business_id);
CREATE INDEX idx_txn_date ON transactions(transaction_date);
