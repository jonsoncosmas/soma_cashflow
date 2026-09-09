-- Soma Cashflow - Phase 8: business photo gallery
--
-- Photos are stored outside the public web root (in /storage, not /public)
-- and always served through public/photo.php, which enforces the same
-- business access control as everything else - so a viewer can see photos
-- for businesses shared with them, but nobody can access another
-- organization's photos by guessing a URL.
--
-- file_name is a randomized, unguessable name on disk (never the user's
-- original filename) - a second layer of defense alongside the access
-- check itself.

CREATE TABLE IF NOT EXISTS business_media (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id     INT UNSIGNED    NOT NULL,
    uploaded_by     INT UNSIGNED    NOT NULL,
    transaction_id  INT UNSIGNED    NULL,        -- optional: "bought chickens" photo linked to that transaction
    caption         VARCHAR(255)    NULL,
    taken_date      DATE            NOT NULL,     -- what the photo represents (e.g. "week 3"), editable, defaults to upload date
    file_name       VARCHAR(64)     NOT NULL,     -- randomized name on disk
    original_name   VARCHAR(255)    NULL,
    mime_type       VARCHAR(100)    NOT NULL,
    file_size       INT UNSIGNED    NOT NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_media_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_media_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_media_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_media_business_date ON business_media(business_id, taken_date);
