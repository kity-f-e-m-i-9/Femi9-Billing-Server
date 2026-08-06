<?php
/**
 * Classifies OCR'd text from an uploaded "payment proof" screenshot.
 *
 * Deliberately conservative: an ambiguous read (more than one candidate
 * amount/reference, or only one of the two found) never gets guessed — it's
 * downgraded to pending_review for a human to confirm. Only a completely
 * signal-free image (no currency, no payment keyword, no reference-number
 * label anywhere) is rejected outright without review.
 */
class PaymentScreenshotParser {

    private static $paymentKeywords = [
        'upi', 'neft', 'rtgs', 'imps', 'paid to', 'payment successful',
        'transaction successful', 'amount paid', 'you paid', 'money sent',
        'payment success', 'transfer successful', 'debited', 'credited',
        'google pay', 'phonepe', 'paytm', 'bhim', 'amazon pay', 'net banking',
        'utr', 'rrn', 'transaction id', 'txn id', 'reference no', 'ref no',
    ];

    private static $amountLineKeywords = [
        'paid', 'amount', 'sent', 'received', 'debited', 'credited', 'total',
    ];

    // Tier 1: specific bank/NPCI-side reference numbers. Tier 2: generic
    // "Transaction ID"-style labels, which many apps also use for their own
    // internal, non-bank IDs (e.g. Google Pay shows both a "UPI transaction
    // ID" AND a separate "Google transaction ID" on the same screen — only
    // the former is the actual payment reference). Tier 2 is only consulted
    // if nothing in tier 1 matched, so the two never compete and cause a
    // false "found more than one" ambiguity.
    private static $referenceLabelPatternTier1 =
        '/(?:UTR|RRN|UPI\s*(?:Ref(?:erence)?(?:\s*(?:No\.?|Number))?|Transaction\s*(?:ID|No\.?|Number)))\s*[:\-]?\s*([A-Za-z0-9]{6,25})/i';
    private static $referenceLabelPatternTier2 =
        '/(?:Ref(?:erence)?\.?\s*(?:No\.?|Number)?|Transaction\s*ID|Txn\.?\s*ID)\s*[:\-]?\s*([A-Za-z0-9]{6,25})/i';

    private static $currencyLinePattern = '/(?:₹|Rs\.?|INR)\s*[\d,]+\.?\d*/i';

    /**
     * @return array{status:string,amount:?float,reference:?string,reason:?string,raw_text:string}
     */
    public static function classify($text) {
        $text = (string)$text;

        $amounts = self::extractAmounts($text);
        $references = self::extractReferences($text);
        $hasSignal = self::hasPaymentSignal($text);

        if (!$hasSignal) {
            return [
                'status' => 'rejected',
                'amount' => null,
                'reference' => null,
                'reason' => "This doesn't look like a payment screenshot — no amount, UTR, or transaction reference was found.",
                'raw_text' => $text,
            ];
        }

        // Recipient is a hard gate, checked before amount/reference quality:
        // a screenshot paid to some other party is never valid proof no
        // matter how cleanly its amount/reference read, so it's rejected
        // outright rather than routed to pending_review.
        if (!self::hasCompanyRecipientMention($text)) {
            return [
                'status' => 'rejected',
                'amount' => count($amounts) === 1 ? array_values($amounts)[0] : null,
                'reference' => count($references) === 1 ? array_values($references)[0] : null,
                'reason' => "This payment does not appear to have been made to Femi9 — the recipient name wasn't found in this screenshot. Please upload a screenshot showing the payment made to Femi9 / Femi Nayan LLP.",
                'raw_text' => $text,
            ];
        }

        if (count($amounts) === 1 && count($references) === 1) {
            return [
                'status' => 'accepted',
                'amount' => array_values($amounts)[0],
                'reference' => array_values($references)[0],
                'reason' => null,
                'raw_text' => $text,
            ];
        }

        $reason = self::ambiguityReason($amounts, $references);
        return [
            'status' => 'pending_review',
            'amount' => count($amounts) === 1 ? array_values($amounts)[0] : null,
            'reference' => count($references) === 1 ? array_values($references)[0] : null,
            'reason' => $reason,
            'raw_text' => $text,
        ];
    }

    private static function ambiguityReason($amounts, $references) {
        if (empty($amounts) && empty($references)) {
            return 'Looks like a payment screenshot, but the amount and reference number could not be read clearly.';
        }
        if (empty($amounts)) {
            return 'Could not clearly read the paid amount from this screenshot.';
        }
        if (empty($references)) {
            return 'Could not clearly read a UTR/transaction reference number from this screenshot.';
        }
        if (count($amounts) > 1) {
            return 'Found more than one possible amount in this screenshot — needs manual verification.';
        }
        return 'Found more than one possible reference number in this screenshot — needs manual verification.';
    }

    private static function hasPaymentSignal($text) {
        $lower = strtolower($text);
        foreach (self::$paymentKeywords as $kw) {
            if (strpos($lower, $kw) !== false) return true;
        }
        return (bool)preg_match(self::$currencyLinePattern, $text);
    }

    // Confirms the screenshot shows a payment actually made to the company
    // (Femi9 / FEMI NAYAN LLP / FEMI HEALTH CARE all share "femi") rather
    // than to some unrelated third party — without this, any valid UPI
    // screenshot with a readable amount and reference would auto-accept,
    // regardless of who it was actually paid to.
    private static function hasCompanyRecipientMention($text) {
        return stripos($text, 'femi') !== false;
    }

    /** @return array<string,float> keyed by rounded value to dedupe */
    private static function extractAmounts($text) {
        $amounts = [];
        $lines = preg_split('/\r\n|\r|\n/', $text);

        $addCandidate = function ($raw) use (&$amounts) {
            $value = (float)str_replace(',', '', $raw);
            if ($value <= 0 || $value > 1e9) return;
            $amounts[number_format($value, 2, '.', '')] = $value;
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // OCR very often mangles stylized currency glyphs (₹ in
            // particular) — sometimes dropping them entirely, sometimes
            // substituting a stray symbol (seen in production: "*5,446" for
            // "₹5,446"). Either way, a prominent "amount paid" display tends
            // to land on its own line as [0-3 junk chars] + number + [0-3
            // junk chars] with nothing else — that shape alone is a strong
            // enough signal to treat as a candidate regardless of what the
            // currency marker actually decoded to.
            //
            // The junk chars must NOT be letters — a masked bank account
            // line like "X0311" (seen in production, from "Debited from
            // X0311") has exactly this shape with "X" as padding, and was
            // wrongly read as amount 311. A mangled currency symbol is never
            // an actual letter, so excluding letters here fixes that without
            // losing the "*5,446"-style cases this rule exists for.
            if (preg_match('/^[^\dA-Za-z]{0,3}(\d[\d,]*\.?\d{0,2})[^\dA-Za-z]{0,3}$/', $trimmed, $m)) {
                $digitCount = strlen(preg_replace('/[^\d]/', '', $m[1]));
                // A bare digit-only line with no comma/decimal formatting and
                // 8+ digits is never a real rupee amount in this system —
                // it's what UTR/RRN/UPI-transaction-ID values look like when
                // OCR puts the label and the number on separate lines (seen
                // in production: a "UPI transaction ID" label line followed
                // by "621635328456" on its own line, wrongly read as a
                // second amount alongside the real "₹50").
                $looksLikeBareId = $digitCount >= 8 && strpos($m[1], ',') === false && strpos($m[1], '.') === false;
                if ($digitCount >= 2 && !$looksLikeBareId) {
                    $addCandidate($m[1]);
                }
                continue;
            }

            if (preg_match(self::$currencyLinePattern, $line)) {
                // Anchor strictly to the currency symbol so unrelated digits
                // on the same line (dates, times, reward points OCR sometimes
                // merges onto one line) aren't picked up as extra candidates.
                //
                // No lookbehind here: bank apps frequently glue the currency
                // code straight onto the number with no space ("INR100300.0"
                // — seen in production, where a lookbehind requiring a
                // non-letter before the digit rejected the match outright
                // because "R" from "INR" precedes it, silently dropping the
                // real amount and leaving stray page-chrome digits elsewhere
                // in the text to win as the only candidate). The currency
                // symbol/code itself is the anchor, so no lookbehind is
                // needed to avoid false positives.
                if (preg_match_all('/(?:₹|Rs\.?|INR)\s*(\d[\d,]*\.?\d{0,2})/i', $line, $matches)) {
                    foreach ($matches[1] as $raw) $addCandidate($raw);
                }
                continue;
            }

            $lowerLine = strtolower($line);
            foreach (self::$amountLineKeywords as $kw) {
                if (strpos($lowerLine, $kw) === false) continue;
                // Negative lookbehind excludes digits embedded in words
                // (e.g. the "9" in "Femi9") — a real amount is never glued
                // to a letter.
                if (preg_match_all('/(?<![A-Za-z])(\d[\d,]*\.?\d{0,2})/i', $line, $matches)) {
                    foreach ($matches[1] as $raw) $addCandidate($raw);
                }
                break;
            }
        }

        return $amounts;
    }

    /** @return array<string,string> keyed by the reference string to dedupe */
    private static function extractReferences($text) {
        $tier1 = self::matchReferences($text, self::$referenceLabelPatternTier1);
        if (!empty($tier1)) return $tier1;
        return self::matchReferences($text, self::$referenceLabelPatternTier2);
    }

    private static function matchReferences($text, $pattern) {
        $refs = [];
        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[1] as $ref) {
                $ref = trim($ref);
                if ($ref === '') continue;
                $refs[strtoupper($ref)] = $ref;
            }
        }
        return $refs;
    }
}
