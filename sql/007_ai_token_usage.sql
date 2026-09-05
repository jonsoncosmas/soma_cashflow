-- Soma Cashflow - Phase 6 follow-up: track token usage per provider attempt
--
-- Both OpenAI and Anthropic columns exist because a single categorization
-- request can genuinely call BOTH providers and consume real tokens on
-- each: if OpenAI returns a response that fails to parse, tokens were
-- still spent on that call even though we then fell back to Anthropic.
-- Tracking both lets a future daily-cap check sum real spend accurately,
-- per provider, per day.

ALTER TABLE ai_suggestions_log
    ADD COLUMN openai_input_tokens     INT UNSIGNED NULL AFTER provider,
    ADD COLUMN openai_output_tokens    INT UNSIGNED NULL AFTER openai_input_tokens,
    ADD COLUMN anthropic_input_tokens  INT UNSIGNED NULL AFTER openai_output_tokens,
    ADD COLUMN anthropic_output_tokens INT UNSIGNED NULL AFTER anthropic_input_tokens;
