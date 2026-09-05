-- Soma Cashflow - Phase 6: AI categorization audit log

CREATE TABLE IF NOT EXISTS ai_suggestions_log (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id            INT UNSIGNED    NOT NULL,
    business_id        INT UNSIGNED    NULL,      -- NULL when suggesting for the personal ledger
    context             ENUM('business','personal') NOT NULL,
    description        TEXT            NOT NULL,
    amount             DECIMAL(14,2)   NULL,
    suggested_type     VARCHAR(30)     NULL,
    suggested_category VARCHAR(100)    NULL,
    confidence         DECIMAL(4,3)    NULL,       -- 0.000 - 1.000
    provider           ENUM('openai','anthropic') NULL,
    success            TINYINT(1)      NOT NULL DEFAULT 0,
    error_message      VARCHAR(255)    NULL,
    created_at         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ai_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ai_log_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_ai_log_user ON ai_suggestions_log(user_id);
CREATE INDEX idx_ai_log_created ON ai_suggestions_log(created_at);
