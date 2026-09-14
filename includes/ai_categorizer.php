<?php
declare(strict_types=1);

/**
 * Soma Cashflow - AI transaction categorizer (Phase 6 + OpenRouter)
 *
 * Tries your configured OpenRouter free models first (in the order you list
 * them), then OpenAI, then falls back to Claude. Every attempt is logged to
 * ai_suggestions_log for later accuracy/cost auditing, including which
 * provider - and for OpenRouter, which specific model - actually answered.
 */

const AI_HTTP_TIMEOUT_SECONDS = 6;

/**
 * Builds the instruction prompt for either model. $allowedTypes is e.g.
 * ['income','expense','loan_received','loan_given'] for a business, or
 * ['income','expense'] for personal.
 */
function ai_build_prompt(string $description, ?float $amount, array $allowedTypes, array $categorySuggestions): string
{
    $typesList = implode(', ', $allowedTypes);
    $catList = implode(', ', $categorySuggestions);
    $amountPart = $amount !== null ? "Amount: {$amount}." : '';

    return "A user is logging a financial transaction for a small business/personal finance app in Tanzania. " .
        "Description: \"{$description}\". {$amountPart} " .
        "Classify it. Respond with ONLY a JSON object, no other text, no markdown fences, in exactly this shape: " .
        '{"type": "<one of: ' . $typesList . '>", "category": "<a short category name, prefer one of: ' . $catList . ', or invent a short one if none fit>", "confidence": <number 0 to 1>}';
}

/**
 * Extracts and validates the JSON object from a raw model response string.
 * Kept separate from any HTTP call so it can be unit tested with fixed
 * sample strings, independent of network access.
 */
function ai_parse_json_response(string $raw, array $allowedTypes): ?array
{
    $raw = trim($raw);
    // Strip markdown code fences if the model added them despite instructions.
    $raw = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $raw);
    $raw = trim($raw);

    // If there's leading/trailing prose around the JSON, extract the first {...} block.
    if (!str_starts_with($raw, '{')) {
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $raw = $m[0];
        } else {
            return null;
        }
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['type'], $data['category'])) {
        return null;
    }

    $type = (string) $data['type'];
    if (!in_array($type, $allowedTypes, true)) {
        return null;
    }

    $category = trim((string) $data['category']);
    if ($category === '') {
        return null;
    }

    $confidence = isset($data['confidence']) ? (float) $data['confidence'] : 0.5;
    $confidence = max(0.0, min(1.0, $confidence));

    return ['type' => $type, 'category' => $category, 'confidence' => $confidence];
}

/**
 * Calls OpenAI's chat completions endpoint. Returns
 * ['content'=>?string,'input_tokens'=>?int,'output_tokens'=>?int] - content
 * is null on any failure (network, timeout, non-200, malformed), but token
 * counts are still returned if the API responded with usage data even when
 * the content itself couldn't be used.
 */
function ai_call_openai(string $apiKey, string $prompt): array
{
    $empty = ['content' => null, 'input_tokens' => null, 'output_tokens' => null];
    if ($apiKey === '') {
        return $empty;
    }

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => AI_HTTP_TIMEOUT_SECONDS,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0,
            'max_tokens' => 200,
        ]),
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        return $empty;
    }

    $decoded = json_decode($response, true);
    $usage = [
        'input_tokens' => isset($decoded['usage']['prompt_tokens']) ? (int) $decoded['usage']['prompt_tokens'] : null,
        'output_tokens' => isset($decoded['usage']['completion_tokens']) ? (int) $decoded['usage']['completion_tokens'] : null,
    ];

    if ($status !== 200) {
        return ['content' => null] + $usage;
    }

    return ['content' => $decoded['choices'][0]['message']['content'] ?? null] + $usage;
}

/**
 * Calls Anthropic's messages endpoint. Same contract as ai_call_openai().
 */
function ai_call_anthropic(string $apiKey, string $prompt): array
{
    $empty = ['content' => null, 'input_tokens' => null, 'output_tokens' => null];
    if ($apiKey === '') {
        return $empty;
    }

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => AI_HTTP_TIMEOUT_SECONDS,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 200,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]),
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        return $empty;
    }

    $decoded = json_decode($response, true);
    $usage = [
        'input_tokens' => isset($decoded['usage']['input_tokens']) ? (int) $decoded['usage']['input_tokens'] : null,
        'output_tokens' => isset($decoded['usage']['output_tokens']) ? (int) $decoded['usage']['output_tokens'] : null,
    ];

    if ($status !== 200) {
        return ['content' => null] + $usage;
    }

    return ['content' => $decoded['content'][0]['text'] ?? null] + $usage;
}

/**
 * Calls OpenRouter's chat completions endpoint (OpenAI-compatible format)
 * for a specific model. Same contract as ai_call_openai(). The model to
 * use is passed in rather than hardcoded, since which OpenRouter models are
 * free (and their exact IDs) changes frequently - see config.sample.php.
 */
function ai_call_openrouter(string $apiKey, string $model, string $prompt): array
{
    $empty = ['content' => null, 'input_tokens' => null, 'output_tokens' => null];
    if ($apiKey === '' || $model === '') {
        return $empty;
    }

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => AI_HTTP_TIMEOUT_SECONDS,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'HTTP-Referer: https://soma-cashflow.local',
            'X-Title: Soma Cashflow',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0,
            'max_tokens' => 200,
        ]),
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        return $empty;
    }

    $decoded = json_decode($response, true);
    $usage = [
        'input_tokens' => isset($decoded['usage']['prompt_tokens']) ? (int) $decoded['usage']['prompt_tokens'] : null,
        'output_tokens' => isset($decoded['usage']['completion_tokens']) ? (int) $decoded['usage']['completion_tokens'] : null,
    ];

    if ($status !== 200) {
        return ['content' => null] + $usage;
    }

    return ['content' => $decoded['choices'][0]['message']['content'] ?? null] + $usage;
}

/**
 * Orchestrates: try OpenRouter's configured free models in order (your
 * choice, cheapest-first by design), then OpenAI, then Claude. Every
 * attempt is logged to ai_suggestions_log for later accuracy/cost auditing,
 * including which provider (and for OpenRouter, which specific model)
 * actually answered.
 *
 * $config['ai'] may contain: openai_api_key, anthropic_api_key,
 * openrouter_api_key, and openrouter_models (an ordered array of model IDs
 * to try - the first one that returns a usable response wins).
 *
 * Returns ['type'=>?,'category'=>?,'confidence'=>?,'provider'=>?,'success'=>bool].
 */
function ai_categorize(
    PDO $pdo,
    array $config,
    int $userId,
    ?int $businessId,
    string $context,
    string $description,
    ?float $amount,
    array $allowedTypes,
    array $categorySuggestions
): array {
    $prompt = ai_build_prompt($description, $amount, $allowedTypes, $categorySuggestions);
    $openaiKey = $config['ai']['openai_api_key'] ?? '';
    $anthropicKey = $config['ai']['anthropic_api_key'] ?? '';
    $openrouterKey = $config['ai']['openrouter_api_key'] ?? '';
    $openrouterModels = $config['ai']['openrouter_models'] ?? [];

    $result = null;
    $provider = null;
    $errorMessage = null;
    $openaiInputTokens = null;
    $openaiOutputTokens = null;
    $anthropicInputTokens = null;
    $anthropicOutputTokens = null;
    $openrouterInputTokens = null;
    $openrouterOutputTokens = null;
    $openrouterModelUsed = null;

    // 1. OpenRouter: try each configured model in order until one works.
    if ($openrouterKey !== '' && $openrouterModels) {
        foreach ($openrouterModels as $model) {
            $response = ai_call_openrouter($openrouterKey, $model, $prompt);
            if ($response['input_tokens'] !== null) {
                $openrouterInputTokens = ($openrouterInputTokens ?? 0) + $response['input_tokens'];
            }
            if ($response['output_tokens'] !== null) {
                $openrouterOutputTokens = ($openrouterOutputTokens ?? 0) + $response['output_tokens'];
            }
            if ($response['content'] !== null) {
                $parsed = ai_parse_json_response($response['content'], $allowedTypes);
                if ($parsed !== null) {
                    $result = $parsed;
                    $provider = 'openrouter';
                    $openrouterModelUsed = $model;
                    break;
                }
            }
        }
    }

    // 2. OpenAI, if OpenRouter didn't produce a usable result.
    if ($result === null) {
        $openaiResponse = ai_call_openai($openaiKey, $prompt);
        $openaiInputTokens = $openaiResponse['input_tokens'];
        $openaiOutputTokens = $openaiResponse['output_tokens'];
        if ($openaiResponse['content'] !== null) {
            $parsed = ai_parse_json_response($openaiResponse['content'], $allowedTypes);
            if ($parsed !== null) {
                $result = $parsed;
                $provider = 'openai';
            }
        }
    }

    // 3. Claude, as the last resort.
    if ($result === null) {
        $anthropicResponse = ai_call_anthropic($anthropicKey, $prompt);
        $anthropicInputTokens = $anthropicResponse['input_tokens'];
        $anthropicOutputTokens = $anthropicResponse['output_tokens'];
        if ($anthropicResponse['content'] !== null) {
            $parsed = ai_parse_json_response($anthropicResponse['content'], $allowedTypes);
            if ($parsed !== null) {
                $result = $parsed;
                $provider = 'anthropic';
            }
        }
    }

    if ($result === null) {
        $errorMessage = ($openaiKey === '' && $anthropicKey === '' && $openrouterKey === '')
            ? 'No AI provider configured.'
            : 'All configured providers failed or returned an unusable response.';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO ai_suggestions_log
            (user_id, business_id, context, description, amount, suggested_type, suggested_category, confidence,
             provider, openai_input_tokens, openai_output_tokens, anthropic_input_tokens, anthropic_output_tokens,
             openrouter_input_tokens, openrouter_output_tokens, openrouter_model_used,
             success, error_message)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId, $businessId, $context, $description, $amount,
        $result['type'] ?? null, $result['category'] ?? null, $result['confidence'] ?? null,
        $provider, $openaiInputTokens, $openaiOutputTokens, $anthropicInputTokens, $anthropicOutputTokens,
        $openrouterInputTokens, $openrouterOutputTokens, $openrouterModelUsed,
        $result !== null ? 1 : 0, $errorMessage,
    ]);

    return [
        'type' => $result['type'] ?? null,
        'category' => $result['category'] ?? null,
        'confidence' => $result['confidence'] ?? null,
        'provider' => $provider,
        'model' => $openrouterModelUsed,
        'success' => $result !== null,
    ];
}
