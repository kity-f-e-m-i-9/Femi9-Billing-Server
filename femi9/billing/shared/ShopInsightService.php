<?php
/**
 * Shop Insight Service — a single text-only Claude call that reads a
 * pre-aggregated summary of report figures (totals, band counts, and a
 * handful of top/bottom lists) and writes a short plain-English narrative
 * for a PDF report's "AI inference" section.
 *
 * Despite the name (kept for the original caller,
 * shop-purchase-frequency-report.php), this is a general-purpose
 * analyst-briefing generator — analyze() takes the domain framing
 * (who the analyst is, what the key terms mean, what to cover) as a
 * parameter rather than assuming shop/restock-gap data, so other reports
 * (e.g. tp-sales-firka-report.php) can reuse it with their own framing
 * instead of getting a narrative confused about what data it's looking at.
 *
 * Modeled on ClaudeVisionService (same API key env var, same endpoint,
 * same cURL shape) but sends no image — just a JSON blob of numbers the
 * report already computed. Never throws to the caller: on a missing key
 * or any API failure it returns success=false with a human-readable
 * message so the report can print an "AI summary unavailable" note and
 * still render.
 */

class ShopInsightService {
    private $apiKey;
    private $model = 'claude-sonnet-4-5-20250929';

    public function __construct() {
        $this->apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? '';
    }

    public function isConfigured() {
        return !empty($this->apiKey);
    }

    /**
     * @param array $summary Pre-aggregated report figures. Free-form —
     *        whatever totals/bands/top-lists the calling report computed.
     * @param string|null $framing Domain-specific prompt override: who the
     *        analyst is, what the data covers, what the key terms mean,
     *        and what to cover in the briefing. When null, falls back to
     *        the original shop-purchase-frequency framing (backward
     *        compatible with the first caller). Build this with
     *        defaultFraming() as a starting point and adjust the "covers"
     *        list rather than writing one from scratch.
     * @return array{success: bool, narrative: string, message: string}
     */
    public function analyze(array $summary, $framing = null) {
        if (!$this->isConfigured()) {
            return $this->failure('Anthropic API key not configured');
        }

        try {
            $payload = [
                'model' => $this->model,
                'max_tokens' => 900,
                'messages' => [[
                    'role' => 'user',
                    'content' => [[
                        'type' => 'text',
                        'text' => $this->buildPrompt($summary, $framing ?? $this->defaultFraming()),
                    ]],
                ]],
            ];

            $response = $this->makeApiRequest($payload);

            if (!$response['success']) {
                $this->logError('Claude API call failed: ' . $response['message']);
                return $this->failure($response['message']);
            }

            $text = trim($response['data']['content'][0]['text'] ?? '');
            if ($text === '') {
                return $this->failure('Model returned an empty response');
            }

            $this->logMessage('Generated shop insight narrative (' . strlen($text) . ' chars)');
            return ['success' => true, 'narrative' => $text, 'message' => 'OK'];
        } catch (\Throwable $e) {
            $this->logError('Shop insight exception: ' . $e->getMessage());
            return $this->failure($e->getMessage());
        }
    }

    /**
     * The original shop-purchase-frequency framing, kept as the default
     * for backward compatibility and as a template for new callers.
     */
    public function defaultFraming() {
        return <<<FRAMING
You are a sales analyst for "Femi9", a company selling sanitary/hygiene
products through a distribution network (stockists, distributors, super
stockists, territory partners) that in turn sell to retail shops.

Below is a pre-computed summary of how often retail shops re-order stock
from the network, covering the entire history of the billing system. Key
terms:
- "sell-in": quantity/value the shop bought from the network.
- "restock gap": days between one purchase and the shop's next purchase.
  A widening gap means slowing offtake; a shrinking gap or bigger orders
  mean the shop is growing.
- "overdue": the shop has gone longer than 1.5x its own average restock
  gap without re-ordering — a churn-risk signal.
- "one-time" shops bought exactly once and never repeated.

Write a tight briefing of about 180-220 words, in plain business English,
for a sales manager. Cover, in flowing prose (not headings):
1. The overall restocking cadence — which frequency bands hold most shops,
   and whether the network's repeat-purchase behaviour looks healthy.
2. Which segments are accelerating (shorter gaps / larger orders) versus
   stretching their cycle.
3. The size of the churn-risk pool (overdue + one-time shops) and roughly
   what it represents in lost or at-risk sell-in.
4. Any sign that sell-in volume and re-order frequency are out of step
   (e.g. large orders but long gaps = possible overstocking; small
   frequent orders = possible understocking / lost sales).
5. Three concrete, specific actions the field team should take.
FRAMING;
    }

    private function buildPrompt(array $summary, $framing) {
        $json = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $framing . <<<PROMPT


Be concrete and use the actual numbers from the data. Do not invent
figures that aren't derivable from what's given. If the data is too thin
to support a claim, say so briefly rather than guessing. Only discuss
what the DATA below actually contains — do not assume it includes figures
from a different kind of report.

DATA:
$json
PROMPT;
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
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

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
        return ['success' => false, 'narrative' => '', 'message' => $message];
    }

    private function logMessage($message) {
        $this->writeLog(__DIR__ . '/../logs/shop_insight.log', $message);
    }

    private function logError($message) {
        $this->writeLog(__DIR__ . '/../logs/shop_insight_error.log', "ERROR: $message");
    }

    private function writeLog($logFile, $message) {
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    }
}
