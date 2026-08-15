-- Soma Cashflow - Phase 3: personal ledger + inter-entity fund transfers

CREATE TABLE IF NOT EXISTS personal_transactions (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED    NOT NULL,
    type              ENUM('income','expense') NOT NULL,
    category          VARCHAR(100)    NOT NULL,
    amount            DECIMAL(14,2)   NOT NULL,
    description       TEXT            NULL,
    transaction_date  DATE            NOT NULL,
    created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ptxn_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_ptxn_amount_positive CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_ptxn_user ON personal_transactions(user_id);
CREATE INDEX idx_ptxn_date ON personal_transactions(transaction_date);

-- A transfer moves money from one entity (personal ledger, or a business) to
-- another. from/to _business_id is NULL when the corresponding side is
-- 'personal'.
CREATE TABLE IF NOT EXISTS fund_transfers (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id    INT UNSIGNED    NOT NULL,
    user_id            INT UNSIGNED    NOT NULL,
    from_type          ENUM('personal','business') NOT NULL,
    from_business_id   INT UNSIGNED    NULL,
    to_type            ENUM('personal','business') NOT NULL,
    to_business_id     INT UNSIGNED    NULL,
    amount             DECIMAL(14,2)   NOT NULL,
    description        TEXT            NULL,
    transfer_date      DATE            NOT NULL,
    created_at         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_transfer_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_transfer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_transfer_from_business FOREIGN KEY (from_business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_transfer_to_business FOREIGN KEY (to_business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT chk_transfer_amount_positive CHECK (amount > 0),
    CONSTRAINT chk_transfer_from_consistency CHECK (
        (from_type = 'business' AND from_business_id IS NOT NULL) OR
        (from_type = 'personal' AND from_business_id IS NULL)
    ),
    CONSTRAINT chk_transfer_to_consistency CHECK (
        (to_type = 'business' AND to_business_id IS NOT NULL) OR
        (to_type = 'personal' AND to_business_id IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_transfer_org ON fund_transfers(organization_id);
CREATE INDEX idx_transfer_from_business ON fund_transfers(from_business_id);
CREATE INDEX idx_transfer_to_business ON fund_transfers(to_business_id);
CREATE INDEX idx_transfer_date ON fund_transfers(transfer_date);
