<?php
/**
 * TP Order Agent — turns a TP's free-text (typed or voice-transcribed)
 * request like "350 covers of 330mm XL 6 pcs, 2 cartons" into structured
 * {product_id, quantity} lines picked from a given product catalog, using
 * Groq's free API (OpenAI-compatible chat completions + tool calling) —
 * tried Claude (no usage credit) and Gemini (daily free quota far too low
 * for real use, 20 requests/day shared across every TP) first; Groq's free
 * tier has a much more usable daily limit and needs no card.
 *
 * Deliberately only resolves WHICH product + HOW MUCH — price, stock
 * availability, carton-multiple rules, CP-vs-company routing etc. stay
 * enforced by each page's own existing JS/PHP exactly as before; the
 * widget that calls this just programmatically fills the same product
 * select + qty field a human would, so none of that validation is bypassed.
 */

class TpOrderAgentService {
    private $apiKey;
    private $model = 'openai/gpt-oss-120b';
    private $db;

    // Bump this whenever the prompt or tool schema changes shape (new
    // field added, response format changed) — folded into the cache key so
    // old cached answers from a previous prompt version are automatically
    // treated as a miss instead of served back in a now-stale shape. Caught
    // live: adding the "options" field left old cached replies silently
    // missing it until this existed.
    private $promptVersion = 2;

    // $db_conn is optional so existing callers/tests that don't need caching
    // keep working — caching is simply skipped (every call hits the API)
    // when it's not supplied.
    public function __construct(?mysqli $db_conn = null) {
        $this->apiKey = $_ENV['GROQ_API_KEY'] ?? '';
        if (empty($this->apiKey)) {
            throw new Exception('Groq API key not configured');
        }
        $this->db = $db_conn;
        if ($this->db) {
            $this->ensureCacheTable();
        }
    }

    /**
     * @param string $message Latest TP message (typed or voice-transcribed).
     * @param array $history Prior turns: [['role' => 'user'|'assistant', 'text' => '...'], ...]
     * @param array $catalog [['id' => int, 'productName' => string], ...]
     * @param string $flow 'po' (purchase order) or 'invoice' (shop invoice)
     * @return array{success:bool, reply:string, lines:array<array{product_id:int,quantity:int,product_name:string}>, message:string}
     */
    public function interpret(string $message, array $history, array $catalog, string $flow): array {
        try {
            // Caching only applies to a fresh question (no conversation
            // history yet) — a follow-up like "I mean 3 box not 3 piece"
            // only makes sense in light of what was just said, so it's
            // never cached/reused across different conversations. Many TPs
            // end up typing the exact same common requests ("3 box of X"),
            // so this alone cuts real API usage a lot without ever
            // guessing — it's an exact match on the message text only.
            // The cache key also folds in every product's current CP stock
            // number (see cacheKey()) when the catalog carries one, so
            // several TPs under the SAME CP asking the same thing reuse one
            // cached answer, but the moment that CP's stock actually
            // changes, the key changes too and it's a fresh lookup again —
            // never a stale stock number served from cache.
            $cacheKey = empty($history) ? $this->cacheKey($message, $flow, $catalog) : null;
            if ($cacheKey !== null) {
                $cachedArgs = $this->getCachedArgs($cacheKey);
                if ($cachedArgs !== null) {
                    [$cachedLines, , $cachedEmergencyLines] = $this->resolveLines($cachedArgs, $catalog);
                    return [
                        'success' => true,
                        'reply' => (string)($cachedArgs['reply'] ?? ''),
                        'lines' => $cachedLines,
                        'emergency_lines' => $cachedEmergencyLines,
                        'options' => $this->extractOptions($cachedArgs),
                        'message' => 'OK (cached)',
                    ];
                }
            }

            $payload = [
                'model' => $this->model,
                'messages' => $this->buildMessages($history, $message, $catalog, $flow),
                'tools' => [['type' => 'function', 'function' => $this->toolSchema()]],
                'tool_choice' => ['type' => 'function', 'function' => ['name' => 'set_order_lines']],
            ];

            // Free-tier capacity errors are transient — a couple of quick
            // retries clears most of them without the TP ever seeing a
            // failure for something that would've worked on the next try.
            $response = null;
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                $response = $this->makeApiRequest($payload);
                if ($response['success']) break;
                $isTransient = stripos($response['message'], 'rate limit') !== false
                    || stripos($response['message'], 'overloaded') !== false
                    || stripos($response['message'], 'try again') !== false
                    || stripos($response['message'], 'unavailable') !== false
                    // Seen once in testing — the model ignored the forced
                    // tool_choice and replied in plain text instead;
                    // succeeded immediately on retry.
                    || stripos($response['message'], 'did not call a tool') !== false;
                if (!$isTransient || $attempt === 3) break;
                usleep(400000 * $attempt); // 0.4s, then 0.8s
            }
            if (!$response['success']) {
                $this->logError('TP order agent API call failed: ' . $response['message']);
                return $this->failure($response['message']);
            }

            $toolCall = $response['data']['choices'][0]['message']['tool_calls'][0] ?? null;
            if (!$toolCall || ($toolCall['function']['name'] ?? '') !== 'set_order_lines') {
                return $this->failure('Model did not return a structured response');
            }
            $args = json_decode($toolCall['function']['arguments'] ?? '', true);
            if (!is_array($args)) {
                return $this->failure('Could not parse model response');
            }

            if ($cacheKey !== null) {
                $this->storeCachedArgs($cacheKey, $args);
            }

            [$lines, $rejections, $emergencyLines] = $this->resolveLines($args, $catalog);
            $reply = (string)($args['reply'] ?? '');
            if (!empty($rejections)) {
                $reply = trim($reply . ' ' . implode(' ', $rejections));
            }

            return [
                'success' => true,
                'reply' => $reply,
                'lines' => $lines,
                'emergency_lines' => $emergencyLines,
                'options' => $this->extractOptions($args),
                'message' => 'OK',
            ];
        } catch (\Throwable $e) {
            $this->logError('TP order agent exception: ' . $e->getMessage());
            return $this->failure($e->getMessage());
        }
    }

    // Sanitized to plain non-empty strings, capped at 4 — a model that
    // ignores the "2-4 max" instruction or returns junk (numbers, nulls)
    // shouldn't turn into broken buttons in the widget.
    private function extractOptions(array $args): array {
        $options = [];
        foreach ($args['options'] ?? [] as $opt) {
            $text = trim((string) $opt);
            if ($text !== '') $options[] = $text;
            if (count($options) >= 4) break;
        }
        return $options;
    }

    // The model only decides WHICH unit the TP meant (pieces vs
    // cartons/boxes) — the actual multiplication happens here in PHP
    // against the real, CURRENT packs_per_carton value (not whatever it
    // was when a cached answer was first generated), rather than trusting
    // the model to multiply correctly or a stale cached number.
    /** @return array{0: array, 1: string[], 2: array} [accepted lines, rejection notes to append to the reply, emergency-request lines] */
    private function resolveLines(array $args, array $catalog): array {
        $catalogById = [];
        foreach ($catalog as $c) {
            $catalogById[(int)$c['id']] = [
                'name' => $c['productName'],
                'ppc' => (int)($c['packs_per_carton'] ?? 0),
                // null = no cap info (company/unrestricted routing); an int
                // (possibly 0) = this CP's real, current closing_qty.
                'avail' => array_key_exists('available_qty', $c) ? (int)$c['available_qty'] : null,
            ];
        }

        $lines = [];
        $rejections = [];
        $emergencyLines = [];
        foreach ($args['lines'] ?? [] as $line) {
            $pid = (int)($line['product_id'] ?? 0);
            $rawQty = (int)($line['quantity'] ?? 0);
            $unit = ($line['unit'] ?? 'pieces') === 'cartons' ? 'cartons' : 'pieces';
            $emergency = !empty($line['emergency']);
            if ($pid <= 0 || $rawQty <= 0 || !isset($catalogById[$pid])) continue;

            $entry = $catalogById[$pid];
            $ppc = $entry['ppc'];
            $finalQty = ($unit === 'cartons' && $ppc > 0) ? $rawQty * $ppc : $rawQty;

            // Deterministic safety net — even if the model ignores the
            // prompt's stock numbers and tries to add more than this CP
            // actually has, the line is dropped here rather than silently
            // failing later in the page's own cart logic while the chat
            // still claims it was added.
            if ($entry['avail'] !== null && $finalQty > $entry['avail']) {
                if ($emergency) {
                    // The TP explicitly flagged this as urgent — instead of
                    // just refusing, this becomes a request Company reviews
                    // manually (see TpEmergencyStockRequest.php). Never
                    // auto-approved here; the line itself is still NOT added
                    // to the order until Company signs off.
                    $emergencyLines[] = [
                        'product_id' => $pid,
                        'product_name' => $entry['name'],
                        'quantity' => $finalQty,
                        'cp_available_qty' => $entry['avail'],
                    ];
                } else {
                    $rejections[] = $entry['avail'] > 0
                        ? "({$entry['name']}: only {$entry['avail']} available at this CP, not {$finalQty} — not added.)"
                        : "({$entry['name']}: this CP has no stock of this — not added.)";
                }
                continue;
            }

            $lines[] = ['product_id' => $pid, 'quantity' => $finalQty, 'product_name' => $entry['name']];
        }
        return [$lines, $rejections, $emergencyLines];
    }

    // Keyed on the message + flow + which products are on offer, each
    // tagged with its current CP stock number when the catalog carries one
    // (id:avail, e.g. "9:271") rather than just the bare id — so several
    // TPs under the same CP asking the same thing share one cached answer,
    // but the instant that CP's stock actually changes, the key changes
    // too and it's treated as a fresh question, never a stale number
    // served from cache. For company-routed (non-CP) catalogs there's no
    // avail figure to fold in, same as before — just the id list.
    private function cacheKey(string $message, string $flow, array $catalog): string {
        $parts = array_map(function ($c) {
            $id = (int)$c['id'];
            return array_key_exists('available_qty', $c) ? "{$id}:{$c['available_qty']}" : (string)$id;
        }, $catalog);
        sort($parts);
        $normalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', $message)));
        return hash('sha256', 'v' . $this->promptVersion . '|' . $normalized . '|' . $flow . '|' . implode(',', $parts));
    }

    private function ensureCacheTable(): void {
        $this->db->query("CREATE TABLE IF NOT EXISTS tp_order_agent_cache (
            cache_key VARCHAR(64) PRIMARY KEY,
            response_json TEXT NOT NULL,
            hit_count INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }

    private function getCachedArgs(string $cacheKey): ?array {
        $stmt = $this->db->prepare("SELECT response_json FROM tp_order_agent_cache WHERE cache_key = ?");
        $stmt->bind_param('s', $cacheKey);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return null;

        $decoded = json_decode($row['response_json'], true);
        if (!is_array($decoded)) return null;

        $upd = $this->db->prepare("UPDATE tp_order_agent_cache SET hit_count = hit_count + 1, last_used_at = NOW() WHERE cache_key = ?");
        $upd->bind_param('s', $cacheKey);
        $upd->execute();
        $upd->close();

        return $decoded;
    }

    private function storeCachedArgs(string $cacheKey, array $args): void {
        $json = json_encode($args);
        $stmt = $this->db->prepare(
            "INSERT INTO tp_order_agent_cache (cache_key, response_json) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE response_json = VALUES(response_json), last_used_at = NOW()"
        );
        $stmt->bind_param('ss', $cacheKey, $json);
        $stmt->execute();
        $stmt->close();
    }

    private function buildMessages(array $history, string $message, array $catalog, string $flow): array {
        $messages = [['role' => 'system', 'content' => $this->buildSystemPrompt($catalog, $flow)]];
        foreach ($history as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $text = trim((string)($turn['text'] ?? ''));
            if ($text === '') continue;
            $messages[] = ['role' => $role, 'content' => $text];
        }
        $messages[] = ['role' => 'user', 'content' => $message];
        return $messages;
    }

    private function buildSystemPrompt(array $catalog, string $flow): string {
        $isCpScoped = !empty($catalog) && array_key_exists('available_qty', $catalog[0]);
        $catalogLines = [];
        foreach ($catalog as $c) {
            $ppc = (int)($c['packs_per_carton'] ?? 0);
            $line = (int)$c['id'] . ': ' . $c['productName'];
            if ($ppc > 0) $line .= ' (1 carton/box = ' . $ppc . ' pcs)';
            if ($isCpScoped) {
                $avail = (int)($c['available_qty'] ?? 0);
                $line .= $avail > 0 ? " — CP stock: {$avail} available" : " — CP stock: 0 (out of stock at this CP)";
            }
            $catalogLines[] = $line;
        }
        $catalogText = implode("\n", $catalogLines);

        $flowLabel = $flow === 'invoice'
            ? "a Territory Partner creating a SHOP INVOICE (selling their own held stock to a shop)"
            : "a Territory Partner placing a PURCHASE ORDER (ordering fresh stock from the company)";

        $stockRule = $isCpScoped ? <<<STOCK

- This order is going to a specific Channel Partner (CP), not the company
  directly — each catalog line shows "CP stock: N available", which is the
  ONLY stock figure that exists in this conversation. If the TP asks how
  much stock/qty is available for a product (not trying to order), answer
  using exactly that CP-stock number and nothing else — never state or imply
  any larger, unlimited, or company-wide figure, since none is known here.
- If the TP wants MORE of a product than its "CP stock" figure, or a product
  shows "CP stock: 0", do NOT add that line — instead say in "reply" that
  this CP doesn't have enough (or any) of it, giving the exact number
  available, e.g. "இந்த CP கிட்ட 330mm XL (SW-9 PCS) 5 மட்டும்தான் இருக்கு,
  9 இல்ல." Only include a line for a product when the requested quantity is
  within that CP's actual stock.
- EXCEPTION: if the TP explicitly says this is urgent — using a word like
  "emergency", "urgent", "அவசரம்" — AND wants more than this CP's stock, set
  "emergency": true on that line and report the FULL quantity they actually
  need (do not cap it to the CP figure). This does NOT add the product to
  the order — it is logged as a request for Company to manually approve. In
  "reply", clearly say the request has been sent to Company for approval
  and they need to wait, in Tamil script, e.g. "அவசர கோரிக்கை Company-க்கு
  அனுப்பப்பட்டது, approval வரும்வரை காத்திருங்க." Without the word
  emergency/urgent/அவசரம், never set this flag — just refuse normally as
  above.
STOCK
            : '';

        return <<<PROMPT
You are an ordering assistant helping {$flowLabel} for Femi9, a sanitary napkin
and baby diaper distributor. The TP will type or speak in Tamil, English, or
Tanglish (mixed Tamil-English). Your only job is to figure out WHICH
product(s) from the catalog below they mean, WHAT quantity, and whether that
quantity is in individual pieces/packs or in whole cartons/boxes, then call
the set_order_lines function.

PRODUCT CATALOG (id: name, and carton size where the product is sold in
cartons) — you may ONLY use product_id values from this exact list, never
invent one:
{$catalogText}
{$stockRule}

Rules:
- Match loosely: product names are often abbreviated, misspelled, or
  described by size/pack count (e.g. "XL 6 pcs", "330 six", "NB diaper")
  rather than typed exactly as in the catalog. Use your judgement to find
  the single best match.
- For each matched product, set "unit" to "cartons" if the TP's wording says
  box/boxes/carton/cartons/peti (in any of the three languages), or "pieces"
  if they just gave a plain number with no box/carton word (that number is
  then the literal Qty value, NOT multiplied). Do NOT do the multiplication
  yourself — just report the number the TP said and which unit it was in;
  the exact math is handled separately.
- WATCH OUT for a specific ambiguity: many product names already end in a
  pack-size number (e.g. "290mm L (3 PCS)", "330mm XL (6 PCS)"). If the TP
  gives ONLY that one number (e.g. "290 3pcs", "330 xl 6pcs") with nothing
  else indicating a separate quantity, it is genuinely unclear whether that
  number is (a) just identifying WHICH pack-size variant they want, with no
  quantity stated yet, or (b) an actual order quantity for some other
  variant of that product. Do NOT silently reuse that same number as both
  the product-matcher AND the quantity. In this exact situation: return
  empty "lines" and ask ONE short, closed-choice question in "reply" — not
  an open-ended question — written in proper Tamil script (தமிழ் எழுத்து),
  not romanized Tanglish, e.g. "3pcs பேக் சைஸா? எவ்வளவு Qty வேண்டும்?" —
  and set "options" to the 2 short tappable choices this implies (see the
  options rule below). Only skip this check when the message clearly gives
  a SEPARATE number for quantity (e.g. "290 3pcs 5 venum", "3 box of 290
  3pcs") — then proceed
  normally using that separate number as the quantity.
- ANOTHER common ambiguity: the catalog has two or more products that could
  plausibly match what the TP said (e.g. they said "330 xl 9pcs" and both
  "330mm XL (DW-9 PCS)" and "330mm XL (SW-9 PCS)" fit). Don't silently pick
  one — return empty "lines", ask which one in "reply" (Tamil script), and
  list every matching product's exact catalog name in "options".
- If the TP's message clearly names one or more products with quantities,
  return one line per product in "lines", and a short one-sentence
  confirmation in "reply" (e.g. "Added 2 cartons of 330mm XL (6 PCS)."), and
  leave "options" empty — there's nothing left to choose.
- If a product can't be confidently matched, or a quantity is missing/
  unclear, return an EMPTY "lines" array and ask a short, specific
  clarifying question in "reply", written in proper Tamil script (தமிழ்
  எழுத்து) — do not guess a product or a quantity, and do not write the
  question in romanized Tanglish.
- OPTIONS: whenever "reply" is a closed-choice question (asking the TP to
  pick between 2-4 specific things — a product variant, a pack size, a
  yes/no), set "options" to those exact choices as short strings the TP
  could tap instead of typing — each one plain text that, if sent back
  as-is, would be enough on its own to answer the question (e.g. the full
  catalog product name for a "which variant" question, or short labels like
  "3pcs பேக்" / "வேற பேக் சைஸ்" for a pack-size-vs-qty question). Leave
  "options" as an empty array for anything that isn't this kind of
  closed-choice question — a plain confirmation, an open-ended "how many do
  you need" question, or a greeting all get options: [].
- If the message isn't about ordering/invoicing at all (greeting, unrelated
  chat), return empty "lines" and empty "options", and a brief, friendly
  "reply".
- Keep "reply" to one short sentence. Confirmations (when lines is
  non-empty) match whatever language/script the TP used. Clarifying
  QUESTIONS (when lines is empty) are always written in Tamil script, even
  if the TP typed in English or Tanglish.
- Quantities must be whole numbers greater than 0.
- You must always call set_order_lines — never respond with plain text.
PROMPT;
    }

    private function toolSchema(): array {
        return [
            'name' => 'set_order_lines',
            'description' => 'Report the product/quantity lines matched from the TP\'s message, plus a short reply.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'lines' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'product_id' => ['type' => 'integer'],
                                'quantity' => ['type' => 'integer', 'description' => 'The number the TP said, as-is — do not multiply by carton size yourself.'],
                                'unit' => ['type' => 'string', 'enum' => ['pieces', 'cartons'], 'description' => '"cartons" if the TP said box/carton/peti, otherwise "pieces".'],
                                'emergency' => ['type' => 'boolean', 'description' => 'true only if the TP explicitly said emergency/urgent/அவசரம் AND wants more than the CP stock figure shown.'],
                            ],
                            'required' => ['product_id', 'quantity', 'unit'],
                        ],
                    ],
                    'reply' => ['type' => 'string'],
                    'options' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Short tappable choices when "reply" is a closed-choice question (2-4 max). Empty array otherwise.',
                    ],
                ],
                'required' => ['reply', 'lines', 'options'],
            ],
        ];
    }

    private function makeApiRequest(array $payload): array {
        $url = 'https://api.groq.com/openai/v1/chat/completions';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        // Local XAMPP/Windows PHP often has no trusted CA bundle configured
        // for cURL, which fails every HTTPS call with "unable to get local
        // issuer certificate" — point at the bundle already shipped in
        // shared/ for exactly this. Harmless where the system CA store is
        // already fine (e.g. production), since this only fills in a path
        // cURL would otherwise resolve to its own default.
        $caBundle = __DIR__ . '/cacert.pem';
        if (is_file($caBundle)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
        }

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

    private function failure(string $message): array {
        return [
            'success' => false,
            'reply' => "Sorry, I couldn't process that right now.",
            'lines' => [],
            'emergency_lines' => [],
            'options' => [],
            'message' => $message,
        ];
    }

    private function logError(string $message): void {
        $logFile = __DIR__ . '/../logs/tp_order_agent_error.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] ERROR: $message\n", FILE_APPEND);
    }
}
