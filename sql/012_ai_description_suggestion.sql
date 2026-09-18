-- Soma Cashflow - AI also rewrites/cleans the transaction description
--
-- Previously the AI only filled Type and Category, leaving whatever the
-- user typed (even shorthand/gibberish) untouched in the Description
-- field. Now it also returns a cleaned-up description, logged here for the
-- same future accuracy auditing as suggested_type/suggested_category.

ALTER TABLE ai_suggestions_log
    ADD COLUMN suggested_description TEXT NULL AFTER suggested_category;
