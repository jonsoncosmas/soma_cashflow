-- Soma Cashflow - Phase 5: multi-user & RBAC
--
-- A row with business_id = NULL means org-wide access (all businesses in
-- that organization). A row with business_id set means access to just
-- that one business.
--
-- status='pending' means invited_email was invited but hasn't registered/
-- linked an account yet (user_id is NULL until they accept). status='active'
-- means user_id is set and they have access.

CREATE TABLE IF NOT EXISTS organization_members (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id   INT UNSIGNED    NOT NULL,
    user_id           INT UNSIGNED    NULL,
    invited_email     VARCHAR(190)    NOT NULL,
    role              ENUM('admin','viewer') NOT NULL,
    business_id       INT UNSIGNED    NULL,
    invited_by        INT UNSIGNED    NOT NULL,
    invite_token      VARCHAR(64)     NULL,
    status            ENUM('pending','active') NOT NULL DEFAULT 'pending',
    created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    accepted_at       TIMESTAMP       NULL,
    CONSTRAINT fk_member_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_member_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_member_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_member_invited_by FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT uq_member_org_email_business UNIQUE (organization_id, invited_email, business_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_member_org ON organization_members(organization_id);
CREATE INDEX idx_member_user ON organization_members(user_id);
CREATE INDEX idx_member_token ON organization_members(invite_token);
