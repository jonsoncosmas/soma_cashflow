-- Soma Cashflow - Add OpenRouter as an AI categorization provider
--
-- OpenRouter gives access to many free-tier models. Since which models are
-- free (and their exact IDs) changes frequently, the specific model list is
-- configured in config.php by the user, not hardcoded - this migration just
-- adds the tracking columns and the new provider option.

ALTER TABLE ai_suggestions_log
    MODIFY COLUMN provider ENUM('openai','anthropic','openrouter') NULL,
    ADD COLUMN openrouter_input_tokens INT UNSIGNED NULL AFTER anthropic_output_tokens,
    ADD COLUMN openrouter_output_tokens INT UNSIGNED NULL AFTER openrouter_input_tokens,
    ADD COLUMN openrouter_model_used VARCHAR(150) NULL AFTER openrouter_output_tokens;
