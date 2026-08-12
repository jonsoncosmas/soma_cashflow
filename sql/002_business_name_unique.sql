-- Soma Cashflow - Migration: prevent duplicate business names within an organization
--
-- If you already created duplicate business names while testing, clean them up
-- first, e.g.:
--   SELECT organization_id, name, COUNT(*) FROM businesses GROUP BY organization_id, name HAVING COUNT(*) > 1;
-- then delete/rename the extras before running this migration.

ALTER TABLE businesses
    ADD CONSTRAINT uq_business_org_name UNIQUE (organization_id, name);
