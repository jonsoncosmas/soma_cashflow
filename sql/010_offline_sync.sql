-- Soma Cashflow - Phase 9: offline sync support
--
-- client_uuid is generated in the browser BEFORE the request is sent (so it
-- exists even if the request never reaches the server). Retrying the same
-- upload after a dropped connection sends the same UUID, and the unique
-- constraint + "ON DUPLICATE KEY UPDATE id=id" no-op pattern in the API
-- endpoints makes re-sending completely safe - it can never create a
-- second row for the same client-side entry.

ALTER TABLE transactions
    ADD COLUMN client_uuid CHAR(36) NULL UNIQUE AFTER id;

ALTER TABLE personal_transactions
    ADD COLUMN client_uuid CHAR(36) NULL UNIQUE AFTER id;
