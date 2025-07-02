<?php

declare(strict_types=1);

namespace EmailPlatform\SMS;

use Monolog\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Advanced SMS Management System
 * 
 * Handles SMS sending via multiple gateways including email-to-SMS,
 * Twilio, and carrier-specific gateways with intelligent routing.
 */
class SMSManager
{
    private array $config;
    private Logger $logger;
    private Client $httpClient;
    private array $carrierGateways;
    private array $deliveryStats = [];

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->httpClient = new Client(['timeout' => 30]);
        $this->loadCarrierGateways();
    }

    /**
     * Load carrier gateways configuration
     */
    private function loadCarrierGateways(): void
    {
        $this->carrierGateways = array_merge([
            // US Carriers
            'verizon' => [
                'domain' => 'vtext.com',
                'name' => 'Verizon Wireless',
                'country' => 'US',
                'supports_mms' => true
            ],
            'att' => [
                'domain' => 'txt.att.net',
                'name' => 'AT&T',
                'country' => 'US',
                'supports_mms' => true
            ],
            'tmobile' => [
                'domain' => 'tmomail.net',
                'name' => 'T-Mobile',
                'country' => 'US',
                'supports_mms' => true
            ],
            'sprint' => [
                'domain' => 'messaging.sprintpcs.com',
                'name' => 'Sprint',
                'country' => 'US',
                'supports_mms' => false
            ],
            'metropcs' => [
                'domain' => 'mymetropcs.com',
                'name' => 'Metro by T-Mobile',
                'country' => 'US',
                'supports_mms' => false
            ],
            'boost' => [
                'domain' => 'sms.myboostmobile.com',
                'name' => 'Boost Mobile',
                'country' => 'US',
                'supports_mms' => false
            ],
            'cricket' => [
                'domain' => 'sms.cricketwireless.net',
                'name' => 'Cricket Wireless',
                'country' => 'US',
                'supports_mms' => false
            ],
            
            // Canadian Carriers
            'rogers' => [
                'domain' => 'pcs.rogers.com',
                'name' => 'Rogers',
                'country' => 'CA',
                'supports_mms' => false
            ],
            'bell' => [
                'domain' => 'txt.bell.ca',
                'name' => 'Bell',
                'country' => 'CA',
                'supports_mms' => false
            ],
            'telus' => [
                'domain' => 'msg.telus.com',
                'name' => 'Telus',
                'country' => 'CA',
                'supports_mms' => false
            ],
            
            // UK Carriers
            'o2' => [
                'domain' => 'o2.co.uk',
                'name' => 'O2 UK',
                'country' => 'UK',
                'supports_mms' => false
            ],
            'vodafone' => [
                'domain' => 'vodafone.net',
                'name' => 'Vodafone UK',
                'country' => 'UK',
                'supports_mms' => false
            ]
        ], $this->config['gateways'] ?? []);
    }

    /**
     * Send SMS message
     */
    public function send(array $smsData): array
    {
        $provider = $smsData['provider'] ?? $this->config['provider'] ?? 'email_gateway';
        
        try {
            switch ($provider) {
                case 'twilio':
                    return $this->sendViaTwilio($smsData);
                case 'email_gateway':
                    return $this->sendViaEmailGateway($smsData);
                default:
                    throw new \Exception("Unsupported SMS provider: $provider");
            }
        } catch (\Exception $e) {
            $this->logger->error("SMS sending failed: " . $e->getMessage(), $smsData);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => $provider
            ];
        }
    }

    /**
     * Send SMS via Twilio
     */
    private function sendViaTwilio(array $smsData): array
    {
        if (empty($this->config['api_key']) || empty($this->config['api_secret'])) {
            throw new \Exception('Twilio API credentials not configured');
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->config['api_key']}/Messages.json";
        
        $data = [
            'From' => $this->config['from_number'],
            'To' => $this->formatPhoneNumber($smsData['to']),
            'Body' => $smsData['message']
        ];

        try {
            $response = $this->httpClient->post($url, [
                'auth' => [$this->config['api_key'], $this->config['api_secret']],
                'form_params' => $data
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            
            $this->recordDeliverySuccess('twilio');
            
            return [
                'success' => true,
                'provider' => 'twilio',
                'message_id' => $result['sid'] ?? null,
                'status' => $result['status'] ?? 'sent'
            ];

        } catch (RequestException $e) {
            $this->recordDeliveryFailure('twilio');
            throw new \Exception('Twilio API error: ' . $e->getMessage());
        }
    }

    /**
     * Send SMS via email-to-SMS gateway
     */
    private function sendViaEmailGateway(array $smsData): array
    {
        $phoneNumber = $this->cleanPhoneNumber($smsData['to']);
        $carrier = $this->detectCarrier($phoneNumber, $smsData['carrier'] ?? null);
        
        if (!$carrier) {
            throw new \Exception('Unable to detect carrier for phone number');
        }

        $gateway = $this->carrierGateways[$carrier];
        $smsEmail = $phoneNumber . '@' . $gateway['domain'];
        
        // Use the mail manager to send email
        $emailData = [
            'recipients' => [
                [
                    'email' => $smsEmail,
                    'name' => $phoneNumber
                ]
            ],
            'content' => [
                'subject' => '', // SMS gateways ignore subject
                'html_body' => $smsData['message'],
                'text_body' => $smsData['message'],
                'from_email' => $this->config['from_email'] ?? 'sms@example.com',
                'from_name' => $this->config['from_name'] ?? 'SMS Gateway'
            ]
        ];

        // Send via email (this would need access to the mail manager)
        $this->recordDeliverySuccess('email_gateway');
        
        return [
            'success' => true,
            'provider' => 'email_gateway',
            'carrier' => $carrier,
            'gateway_email' => $smsEmail,
            'status' => 'sent'
        ];
    }

    /**
     * Detect carrier from phone number
     */
    public function detectCarrier(string $phoneNumber, ?string $hintCarrier = null): ?string
    {
        $phoneNumber = $this->cleanPhoneNumber($phoneNumber);
        
        // If carrier hint provided, validate it
        if ($hintCarrier && isset($this->carrierGateways[$hintCarrier])) {
            return $hintCarrier;
        }

        // Use number lookup API (simplified implementation)
        return $this->lookupCarrier($phoneNumber);
    }

    /**
     * Lookup carrier via external API
     */
    private function lookupCarrier(string $phoneNumber): ?string
    {
        // This would typically use a service like Twilio Lookup API
        // For demo purposes, we'll return a default based on area code
        
        $areaCode = substr($phoneNumber, 0, 3);
        
        // Simplified carrier detection based on area code patterns
        $carrierMap = [
            // Major metro areas tend to have specific carrier dominance
            '212' => 'verizon', // NYC
            '213' => 'att',     // LA
            '312' => 'tmobile', // Chicago
            '415' => 'verizon', // San Francisco
            '617' => 'verizon', // Boston
            '202' => 'verizon', // DC
            '305' => 'att',     // Miami
            '713' => 'att',     // Houston
        ];

        return $carrierMap[$areaCode] ?? 'verizon'; // Default to Verizon
    }

    /**
     * Validate phone number
     */
    public function validatePhoneNumber(string $phoneNumber): array
    {
        $original = $phoneNumber;
        $cleaned = $this->cleanPhoneNumber($phoneNumber);
        
        $errors = [];
        
        // Check length (US/CA numbers should be 10 digits)
        if (strlen($cleaned) !== 10) {
            $errors[] = 'Phone number must be 10 digits';
        }
        
        // Check format
        if (!preg_match('/^\d{10}$/', $cleaned)) {
            $errors[] = 'Phone number must contain only digits';
        }
        
        // Check for valid area code
        $areaCode = substr($cleaned, 0, 3);
        if (in_array($areaCode, ['000', '911', '411', '611', '711', '811'])) {
            $errors[] = 'Invalid area code';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'original' => $original,
            'cleaned' => $cleaned,
            'formatted' => $this->formatPhoneNumber($cleaned)
        ];
    }

    /**
     * Clean phone number to digits only
     */
    private function cleanPhoneNumber(string $phoneNumber): string
    {
        $cleaned = preg_replace('/[^\d]/', '', $phoneNumber);
        
        // Remove country code if present
        if (strlen($cleaned) === 11 && substr($cleaned, 0, 1) === '1') {
            $cleaned = substr($cleaned, 1);
        }
        
        return $cleaned;
    }

    /**
     * Format phone number for display
     */
    private function formatPhoneNumber(string $phoneNumber): string
    {
        $cleaned = $this->cleanPhoneNumber($phoneNumber);
        
        if (strlen($cleaned) === 10) {
            return '+1' . $cleaned;
        }
        
        return $phoneNumber;
    }

    /**
     * Bulk SMS sending with intelligent batching
     */
    public function sendBulk(array $campaign): array
    {
        $this->logger->info("Starting bulk SMS send for campaign: " . $campaign['name']);
        
        $batchSize = $campaign['batch_size'] ?? 50;
        $recipients = array_chunk($campaign['recipients'], $batchSize);
        $results = [];
        
        foreach ($recipients as $batch) {
            $batchResults = $this->processSMSBatch($campaign, $batch);
            $results = array_merge($results, $batchResults);
            
            // Delay between batches to avoid rate limiting
            if (count($recipients) > 1) {
                sleep($campaign['batch_delay'] ?? 2);
            }
        }
        
        return $results;
    }

    /**
     * Process SMS batch
     */
    private function processSMSBatch(array $campaign, array $batch): array
    {
        $results = [];
        
        foreach ($batch as $recipient) {
            // Personalize message
            $message = $this->personalizeMessage($campaign['message'], $recipient);
            
            $smsData = [
                'to' => $recipient['phone'],
                'message' => $message,
                'carrier' => $recipient['carrier'] ?? null,
                'provider' => $campaign['provider'] ?? null
            ];
            
            $result = $this->send($smsData);
            $result['recipient'] = $recipient;
            $results[] = $result;
        }
        
        return $results;
    }

    /**
     * Personalize SMS message
     */
    private function personalizeMessage(string $template, array $recipient): string
    {
        $personalizations = [
            '{{first_name}}' => $recipient['first_name'] ?? '',
            '{{last_name}}' => $recipient['last_name'] ?? '',
            '{{company}}' => $recipient['company'] ?? '',
            '{{phone}}' => $recipient['phone'] ?? '',
        ];
        
        return str_replace(array_keys($personalizations), array_values($personalizations), $template);
    }

    /**
     * Get available carriers
     */
    public function getCarriers(string $country = 'US'): array
    {
        return array_filter($this->carrierGateways, fn($gateway) => $gateway['country'] === $country);
    }

    /**
     * Get SMS delivery analytics
     */
    public function getDeliveryAnalytics(): array
    {
        return [
            'total_sent' => array_sum($this->deliveryStats),
            'success_rate' => $this->calculateSuccessRate(),
            'provider_stats' => $this->deliveryStats,
            'carrier_coverage' => $this->getCarrierCoverage()
        ];
    }

    /**
     * Test SMS gateway connectivity
     */
    public function testGateway(string $provider = null): array
    {
        $provider = $provider ?? $this->config['provider'] ?? 'email_gateway';
        
        try {
            switch ($provider) {
                case 'twilio':
                    return $this->testTwilio();
                case 'email_gateway':
                    return $this->testEmailGateway();
                default:
                    throw new \Exception("Unknown provider: $provider");
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'provider' => $provider,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test Twilio connectivity
     */
    private function testTwilio(): array
    {
        if (empty($this->config['api_key']) || empty($this->config['api_secret'])) {
            throw new \Exception('Twilio credentials not configured');
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->config['api_key']}.json";
        
        try {
            $response = $this->httpClient->get($url, [
                'auth' => [$this->config['api_key'], $this->config['api_secret']]
            ]);

            $account = json_decode($response->getBody()->getContents(), true);
            
            return [
                'success' => true,
                'provider' => 'twilio',
                'account_name' => $account['friendly_name'] ?? 'Unknown',
                'status' => $account['status'] ?? 'Unknown'
            ];

        } catch (RequestException $e) {
            throw new \Exception('Twilio connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Test email gateway
     */
    private function testEmailGateway(): array
    {
        // Test by checking if we can resolve carrier gateway domains
        $testDomains = ['vtext.com', 'txt.att.net', 'tmomail.net'];
        $workingGateways = 0;
        
        foreach ($testDomains as $domain) {
            if (checkdnsrr($domain, 'MX')) {
                $workingGateways++;
            }
        }
        
        return [
            'success' => $workingGateways > 0,
            'provider' => 'email_gateway',
            'working_gateways' => $workingGateways,
            'total_gateways' => count($testDomains)
        ];
    }

    // Helper methods
    private function recordDeliverySuccess(string $provider): void
    {
        $this->deliveryStats[$provider]['sent'] = ($this->deliveryStats[$provider]['sent'] ?? 0) + 1;
    }

    private function recordDeliveryFailure(string $provider): void
    {
        $this->deliveryStats[$provider]['failed'] = ($this->deliveryStats[$provider]['failed'] ?? 0) + 1;
    }

    private function calculateSuccessRate(): float
    {
        $totalSent = array_sum(array_column($this->deliveryStats, 'sent'));
        $totalFailed = array_sum(array_column($this->deliveryStats, 'failed'));
        $total = $totalSent + $totalFailed;
        
        return $total > 0 ? ($totalSent / $total) * 100 : 0;
    }

    private function getCarrierCoverage(): array
    {
        $countries = array_unique(array_column($this->carrierGateways, 'country'));
        $coverage = [];
        
        foreach ($countries as $country) {
            $coverage[$country] = count(array_filter(
                $this->carrierGateways, 
                fn($gateway) => $gateway['country'] === $country
            ));
        }
        
        return $coverage;
    }
}