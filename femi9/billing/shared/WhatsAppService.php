<?php
/**
 * WhatsApp Cloud API Service
 * Sends messages via Meta WhatsApp Business Platform
 */

class WhatsAppService {
    private $apiBase;
    private $apiToken;
    private $phoneNumberId;
    
    public function __construct() {
        // Load environment variables
        $this->apiBase = $_ENV['WHATSAPP_API_BASE'] ?? '';
        $this->apiToken = $_ENV['WHATSAPP_API_TOKEN'] ?? '';
        $this->phoneNumberId = $_ENV['WHATSAPP_PHONE_NUMBER_ID'] ?? '';
        
        // Validate configuration
        if (empty($this->apiToken) || empty($this->phoneNumberId)) {
            throw new Exception('WhatsApp API credentials not configured');
        }
    }
    
    /**
     * Send password reset message with URL button
     * 
     * @param string $phone Phone number (10 digits or with country code)
     * @param string $password The temporary password to send
     * @return array ['success' => true/false, 'message' => '', 'data' => []]
     */
    public function sendPasswordReset($phone, $password) {
        $templateName = 'password_reset_billing';
        
        try {
            // Format phone number
            $formattedPhone = $this->formatPhoneNumber($phone);
            
            if (!$formattedPhone) {
                return [
                    'success' => false,
                    'message' => 'Invalid phone number format'
                ];
            }
            
            // Try with URL button parameter
            // The URL will likely be: https://femi9billing.com/... with dynamic token
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $formattedPhone,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => 'en_US'
                    ],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $password
                                ]
                            ]
                        ],
                        [
                            'type' => 'button',
                            'sub_type' => 'url',
                            'index' => '0',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $password
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            
            // Send API request
            $response = $this->makeApiRequest($payload);
            
            // Log the response
            $this->logMessage($formattedPhone, $templateName, $response);
            
            return $response;
            
        } catch (Exception $e) {
            $this->logError('WhatsApp send failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Format phone number to international format
     * Accepts: 9876543210 or 919876543210
     * Returns: 919876543210
     */
    private function formatPhoneNumber($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // If 10 digits, add country code (91 for India)
        if (strlen($phone) == 10) {
            return '91' . $phone;
        }
        
        // If already has country code (12 digits for India)
        if (strlen($phone) == 12 && substr($phone, 0, 2) == '91') {
            return $phone;
        }
        
        // Invalid format
        return false;
    }
    
    /**
     * Make cURL request to WhatsApp API
     */
    private function makeApiRequest($payload) {
        $url = "{$this->apiBase}/{$this->phoneNumberId}/messages";
        
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiToken,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Handle cURL errors
        if ($curlError) {
            throw new Exception("cURL error: $curlError");
        }
        
        // Parse response
        $responseData = json_decode($response, true);
        
        // Check HTTP status
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => $responseData,
                'message_id' => $responseData['messages'][0]['id'] ?? null
            ];
        } else {
            $errorMessage = $responseData['error']['message'] ?? 'Unknown error';
            $errorCode = $responseData['error']['code'] ?? $httpCode;
            
            return [
                'success' => false,
                'message' => $errorMessage,
                'error_code' => $errorCode,
                'data' => $responseData
            ];
        }
    }
    
    /**
     * Log successful message
     */
    private function logMessage($phone, $template, $response) {
        $logFile = __DIR__ . '/../logs/whatsapp.log';
        $logDir = dirname($logFile);
        
        // Create logs directory if it doesn't exist
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $status = $response['success'] ? 'SUCCESS' : 'FAILED';
        $messageId = $response['message_id'] ?? 'N/A';
        
        $logEntry = "[{$timestamp}] {$status} | Phone: {$phone} | Template: {$template} | Message ID: {$messageId}\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Log errors
     */
    private function logError($message) {
        $logFile = __DIR__ . '/../logs/whatsapp_error.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] ERROR: {$message}\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}
?>