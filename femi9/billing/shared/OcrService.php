<?php
/**
 * OCR Service — Google Cloud Vision text detection
 * Extracts raw text from an uploaded image via the images:annotate REST API.
 */

class OcrService {
    private $apiKey;

    public function __construct() {
        $this->apiKey = $_ENV['GOOGLE_VISION_API_KEY'] ?? '';

        if (empty($this->apiKey)) {
            throw new Exception('Google Vision API key not configured');
        }
    }

    /**
     * @param string $imagePath Local filesystem path to the image.
     * @return array ['success' => bool, 'text' => string, 'message' => string]
     */
    public function extractText($imagePath) {
        try {
            $imageData = @file_get_contents($imagePath);
            if ($imageData === false) {
                return ['success' => false, 'text' => '', 'message' => 'Could not read image file'];
            }

            $payload = [
                'requests' => [
                    [
                        'image' => ['content' => base64_encode($imageData)],
                        'features' => [['type' => 'TEXT_DETECTION']],
                    ],
                ],
            ];

            $response = $this->makeApiRequest($payload);

            if (!$response['success']) {
                $this->logError('Vision API call failed: ' . $response['message']);
                return $response;
            }

            $text = $response['data']['responses'][0]['fullTextAnnotation']['text']
                ?? $response['data']['responses'][0]['textAnnotations'][0]['description']
                ?? '';

            if (isset($response['data']['responses'][0]['error'])) {
                $err = $response['data']['responses'][0]['error']['message'] ?? 'Unknown Vision API error';
                $this->logError('Vision API returned an error: ' . $err);
                return ['success' => false, 'text' => '', 'message' => $err];
            }

            $this->logMessage('OCR extracted ' . strlen($text) . ' chars from ' . basename($imagePath));
            return ['success' => true, 'text' => $text, 'message' => 'OK'];
        } catch (\Throwable $e) {
            $this->logError('OCR exception: ' . $e->getMessage());
            return ['success' => false, 'text' => '', 'message' => $e->getMessage()];
        }
    }

    private function makeApiRequest($payload) {
        $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . urlencode($this->apiKey);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 20,
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

    private function logMessage($message) {
        $logFile = __DIR__ . '/../logs/ocr.log';
        $this->writeLog($logFile, $message);
    }

    private function logError($message) {
        $logFile = __DIR__ . '/../logs/ocr_error.log';
        $this->writeLog($logFile, "ERROR: $message");
    }

    private function writeLog($logFile, $message) {
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);

        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    }
}
