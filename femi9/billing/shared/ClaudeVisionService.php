<?php
/**
 * Claude Vision Service — reads a PO payment screenshot directly (no OCR
 * middle-step) and reasons about the paid amount, payment reference, and
 * recipient in one call. Given a handful of past reviewer corrections, it
 * can also generalize from previously-seen misread patterns instead of only
 * matching identical images.
 */

class ClaudeVisionService {
    private $apiKey;
    private $model = 'claude-sonnet-4-5-20250929';

    public function __construct() {
        $this->apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? '';

        if (empty($this->apiKey)) {
            throw new Exception('Anthropic API key not configured');
        }
    }

    /**
     * @param string $imagePath Local filesystem path to the image (JPEG/PNG/WEBP/GIF).
     * @param array $priorCorrections Recent reviewer corrections to few-shot from:
     *        [['wrong' => '35', 'correct' => '5', 'field' => 'amount'], ...]
     * @param ?string $expectedRecipient When given, the recipient check verifies the
     *        screenshot's payee against THIS exact UPI ID/VPA instead of the generic
     *        "Femi9 / Femi Nayan LLP / Anand Praveen" name list — used by courier
     *        payment, where the actual receiving account is whatever the company has
     *        currently configured (a personal name, not literally "Femi9"), so the
     *        generic name check would wrongly reject a genuinely correct payment.
     * @return array{
     *   success: bool,
     *   amount: ?float,
     *   reference: ?string,
     *   recipient_matches: bool,
     *   confidence: string,   // 'high' | 'low'
     *   looks_like_payment_screenshot: bool,
     *   reasoning: string,
     *   message: string
     * }
     */
    public function analyzeScreenshot($imagePath, array $priorCorrections = [], ?string $expectedRecipient = null) {
        try {
            $imageData = @file_get_contents($imagePath);
            if ($imageData === false) {
                return $this->failure('Could not read image file');
            }

            $mediaType = $this->detectMediaType($imagePath);
            if (!$mediaType) {
                return $this->failure('Unsupported image type');
            }

            $prompt = $this->buildPrompt($priorCorrections, $expectedRecipient);

            $payload = [
                'model' => $this->model,
                'max_tokens' => 1024,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => [
                            'type' => 'base64',
                            'media_type' => $mediaType,
                            'data' => base64_encode($imageData),
                        ]],
                        ['type' => 'text', 'text' => $prompt],
                    ],
                ]],
            ];

            $response = $this->makeApiRequest($payload);

            if (!$response['success']) {
                $this->logError('Claude API call failed: ' . $response['message']);
                return $this->failure($response['message']);
            }

            $text = $response['data']['content'][0]['text'] ?? '';
            $parsed = $this->parseModelResponse($text);

            if ($parsed === null) {
                $this->logError('Could not parse Claude response as JSON: ' . substr($text, 0, 500));
                return $this->failure('Could not parse model response');
            }

            $this->logMessage('Analyzed ' . basename($imagePath) . ' — amount=' . ($parsed['amount'] ?? 'null')
                . ' reference=' . ($parsed['reference'] ?? 'null') . ' confidence=' . ($parsed['confidence'] ?? '?'));

            return [
                'success' => true,
                'amount' => $parsed['amount'],
                'reference' => $parsed['reference'],
                'recipient_matches' => (bool)($parsed['recipient_matches'] ?? false),
                'payment_date' => $parsed['payment_date'] ?? null,
                'payment_succeeded' => $parsed['payment_succeeded'] ?? true,
                'confidence' => $parsed['confidence'] ?? 'low',
                'looks_like_payment_screenshot' => $parsed['looks_like_payment_screenshot'],
                'reasoning' => $parsed['reasoning'] ?? '',
                'message' => 'OK',
            ];
        } catch (\Throwable $e) {
            $this->logError('Claude vision exception: ' . $e->getMessage());
            return $this->failure($e->getMessage());
        }
    }

    private function buildPrompt(array $priorCorrections, ?string $expectedRecipient = null) {
        $recipientInstruction = $expectedRecipient !== null
            ? "Whether the payment recipient shown in the screenshot (their UPI ID/VPA, "
              . "e.g. \"name@bank\") is exactly \"{$expectedRecipient}\" (recipient_matches: "
              . "true) or a different UPI ID/account entirely (recipient_matches: false). "
              . "Match on the UPI ID itself, not the display name next to it (the display "
              . "name shown by the paying app is the payer's own saved contact name and may "
              . "not match)."
            : "Whether the payment recipient shown in the screenshot is Femi9 / Femi "
              . "Nayan LLP / Femi Health Care / Anand Praveen (recipient_matches: true) "
              . "or someone else entirely (recipient_matches: false).";

        $prompt = <<<PROMPT
You are verifying a payment proof screenshot uploaded by a distributor as
proof of payment to the company "Femi9" (also written as "Femi Nayan LLP",
"Femi Health Care", or "Anand Praveen" — a company representative who also
receives payments directly on Femi9's behalf — any of these count as a
match).

Read the screenshot carefully and identify:
1. Whether this image is actually a payment confirmation / payment proof
   screenshot at all (a UPI app success screen, a bank transfer receipt, an
   SMS/notification showing a completed transaction, etc.) as opposed to
   something else entirely — a screenshot of an unrelated app screen, an
   error message, an invoice/order form, a chat conversation, a random
   photo, or anything else that isn't proof of a payment having been made.
2. The amount actually paid (not a masked account number, not a transaction
   ID, not a phone number, not a date/time, not any other number on screen).
3. The payment reference number — this is usually labeled UTR, RRN, UPI
   Reference No, UPI Transaction ID, or similar. It is normally 6-25
   alphanumeric characters. Do not confuse a "Google transaction ID" (an
   internal app ID) with the actual bank/UPI reference if both appear —
   prefer the UPI/bank-side one when both are present.
4. {$recipientInstruction}
5. The date the payment was made, if shown on screen (as YYYY-MM-DD; if only
   a relative label like "Today"/"Yesterday" is shown with no absolute date,
   or no date at all is visible, return null for this field rather than
   guessing).
6. Whether the payment actually SUCCEEDED (payment_succeeded: true) or the
   screen shows a FAILED/incomplete/error transaction (payment_succeeded:
   false) — look for explicit failure language such as "not been debited",
   "transaction failed", "payment unsuccessful", "try again", "retry", a
   pending/processing spinner with no completion confirmation, or any bank/
   UPI-limit error message. A completed transaction always shows a clear
   success indicator (a green checkmark, "Completed", "Successful", a real
   transaction ID) — if that's genuinely missing and you cannot confirm
   completion, treat it as payment_succeeded: false rather than assuming
   success. This is separate from question 1: a failed-payment screen still
   looks_like_payment_screenshot: true (it's real payment app UI, not proof
   money actually moved).

Be conservative: if the amount or reference is genuinely unclear, blurry,
ambiguous, or you are guessing, say so honestly with confidence "low" rather
than inventing a value. A wrong auto-accepted payment is worse than one that
needs a human to double check it.

PROMPT;

        if (!empty($priorCorrections)) {
            $prompt .= "For reference, here are real past cases where automatic reading got it "
                . "wrong and a human reviewer corrected it — use these to recognize similar "
                . "traps (e.g. a masked account number or transaction ID being mistaken for the "
                . "amount), but do NOT assume this new screenshot has the same values:\n\n";
            foreach ($priorCorrections as $c) {
                $prompt .= "- Field \"{$c['field']}\": automatic reading said "
                    . ($c['wrong'] === null || $c['wrong'] === '' ? '(nothing detected)' : "\"{$c['wrong']}\"")
                    . ", but the correct value was \"{$c['correct']}\"\n";
            }
            $prompt .= "\n";
        }

        $prompt .= <<<PROMPT
Respond with ONLY a JSON object, no other text, in exactly this shape:
{
  "looks_like_payment_screenshot": <true or false>,
  "amount": <number or null>,
  "reference": "<string or null>",
  "recipient_matches": <true or false>,
  "payment_date": "<YYYY-MM-DD or null>",
  "payment_succeeded": <true or false>,
  "confidence": "high" or "low",
  "reasoning": "<one short sentence explaining your read, or why confidence is low, or why this isn't a payment screenshot>"
}
PROMPT;

        return $prompt;
    }

    private function parseModelResponse($text) {
        $text = trim($text);
        // Strip markdown code fences if the model wrapped the JSON in one.
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $text, $m)) {
            $text = $m[1];
        }
        $decoded = json_decode($text, true);
        if (!is_array($decoded) || !array_key_exists('amount', $decoded)) {
            return null;
        }
        return [
            'amount' => is_numeric($decoded['amount'] ?? null) ? (float)$decoded['amount'] : null,
            'reference' => !empty($decoded['reference']) ? trim((string)$decoded['reference']) : null,
            'recipient_matches' => $decoded['recipient_matches'] ?? false,
            // Defaults to true (assume success) when the model omits this
            // field entirely, same "don't invent a negative the model never
            // actually said" posture as looks_like_payment_screenshot above
            // — an older prompt response or a model that ignores the new
            // field shouldn't suddenly start rejecting every screenshot.
            'payment_succeeded' => array_key_exists('payment_succeeded', $decoded)
                ? (bool)$decoded['payment_succeeded']
                : true,
            'payment_date' => (!empty($decoded['payment_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $decoded['payment_date']))
                ? $decoded['payment_date'] : null,
            'confidence' => in_array($decoded['confidence'] ?? '', ['high', 'low'], true) ? $decoded['confidence'] : 'low',
            // Defaults to true (rather than assuming the negative) when the
            // model omits the field entirely, so older/odd responses fall
            // back to today's generic "could not be read clearly" message
            // instead of wrongly claiming it isn't a payment screenshot.
            'looks_like_payment_screenshot' => array_key_exists('looks_like_payment_screenshot', $decoded)
                ? (bool)$decoded['looks_like_payment_screenshot']
                : true,
            'reasoning' => (string)($decoded['reasoning'] ?? ''),
        ];
    }

    private function detectMediaType($imagePath) {
        $info = @getimagesize($imagePath);
        if (!$info) return null;
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        return in_array($info['mime'], $allowed, true) ? $info['mime'] : null;
    }

    private function makeApiRequest($payload) {
        $url = 'https://api.anthropic.com/v1/messages';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($curlError) {
            return ['success' => false, 'message' => "cURL error: $curlError", 'data' => null];
        }

        $data = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'message' => 'OK', 'data' => $data];
        }

        $errorMessage = $data['error']['message'] ?? "HTTP $httpCode";
        return ['success' => false, 'message' => $errorMessage, 'data' => $data];
    }

    private function failure($message) {
        return [
            'success' => false,
            'amount' => null,
            'reference' => null,
            'recipient_matches' => false,
            'payment_date' => null,
            'payment_succeeded' => true,
            'confidence' => 'low',
            'looks_like_payment_screenshot' => true,
            'reasoning' => '',
            'message' => $message,
        ];
    }

    private function logMessage($message) {
        $this->writeLog(__DIR__ . '/../logs/claude_vision.log', $message);
    }

    private function logError($message) {
        $this->writeLog(__DIR__ . '/../logs/claude_vision_error.log', "ERROR: $message");
    }

    private function writeLog($logFile, $message) {
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);

        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    }
}
