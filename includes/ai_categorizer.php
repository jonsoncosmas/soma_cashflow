<?php
declare(strict_types=1);

/**
 * Soma Cashflow - AI transaction categorizer (Phase 6)
 *
 * Tries OpenAI (ChatGPT) first; if that fails or isn't configured, falls
 * back to Claude. Every attempt is logged to ai_suggestions_log for later
 * accuracy auditing, including which provider actually answered.
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
 * Calls OpenAI's chat completions endpoint. Returns the raw assistant text
 * on success, or null on any failure (network, timeout, non-200, malformed).
 */
function ai_call_openai(string $apiKey, string $prompt): ?string
{
    if ($apiKey === '') {
        return null;
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

    if ($response === false || $curlError !== '' || $status !== 200) {
        return null;
    }

    $decoded = json_decode($response, true);
    return $decoded['choices'][0]['message']['content'] ?? null;
}

/**
 * Calls Anthropic's messages endpoint. Same contract as ai_call_openai().
 */
function ai_call_anthropic(string $apiKey, string $prompt): ?string
{
    if ($apiKey === '') {
        return null;
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

    if ($response === false || $curlError !== '' || $status !== 200) {
        return null;
    }

    $decoded = json_decode($response, true);
    return $decoded['content'][0]['text'] ?? null;
}

/**
 * Orchestrates: try OpenAI, fall back to Claude, log the outcome either way.
 *
 * $config is the app config array (must contain $config['ai']['openai_api_key']
 * and $config['ai']['anthropic_api_key']).
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

    $result = null;
    $provider = null;
    $errorMessage = null;

    $raw = ai_call_openai($openaiKey, $prompt);
    if ($raw !== null) {
        $parsed = ai_parse_json_response($raw, $allowedTypes);
        if ($parsed !== null) {
            $result = $parsed;
            $provider = 'openai';
        }
    }

    if ($result === null) {
        $raw = ai_call_anthropic($anthropicKey, $prompt);
        if ($raw !== null) {
            $parsed = ai_parse_json_response($raw, $allowedTypes);
            if ($parsed !== null) {
                $result = $parsed;
                $provider = 'anthropic';
            }
        }
    }

    if ($result === null) {
        $errorMessage = ($openaiKey === '' && $anthropicKey === '')
            ? 'No AI provider configured.'
            : 'Both providers failed or returned an unusable response.';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO ai_suggestions_log
            (user_id, business_id, context, description, amount, suggested_type, suggested_category, confidence, provider, success, error_message)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId, $businessId, $context, $description, $amount,
        $result['type'] ?? null, $result['category'] ?? null, $result['confidence'] ?? null,
        $provider, $result !== null ? 1 : 0, $errorMessage,
    ]);

    return [
        'type' => $result['type'] ?? null,
        'category' => $result['category'] ?? null,
        'confidence' => $result['confidence'] ?? null,
        'provider' => $provider,
        'success' => $result !== null,
    ];
}
