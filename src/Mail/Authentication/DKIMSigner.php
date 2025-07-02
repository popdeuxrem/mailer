<?php

declare(strict_types=1);

namespace EmailPlatform\Mail\Authentication;

/**
 * DKIM Email Authentication Signer
 * 
 * Implements DKIM (DomainKeys Identified Mail) signing for improved
 * email deliverability and authentication.
 */
class DKIMSigner
{
    private string $domain;
    private string $selector;
    private string $privateKey;
    private array $canonicalization = ['simple', 'simple'];
    private array $headers = ['from', 'to', 'subject', 'date', 'mime-version', 'content-type'];

    /**
     * Set DKIM configuration
     */
    public function configure(array $config): void
    {
        $this->domain = $config['domain'] ?? '';
        $this->selector = $config['selector'] ?? 'default';
        $this->privateKey = $config['private_key'] ?? '';
        
        if (!empty($config['headers'])) {
            $this->headers = $config['headers'];
        }
        
        if (!empty($config['canonicalization'])) {
            $this->canonicalization = $config['canonicalization'];
        }
    }

    /**
     * Sign email headers with DKIM
     */
    public function signHeaders(array $headers): string
    {
        if (empty($this->domain) || empty($this->privateKey)) {
            throw new \Exception('DKIM domain and private key must be configured');
        }

        // Normalize headers for signing
        $normalizedHeaders = $this->normalizeHeaders($headers);
        
        // Create DKIM signature header (without signature value)
        $dkimHeader = $this->createDKIMHeader($normalizedHeaders);
        
        // Canonicalize headers and body
        $canonicalizedHeaders = $this->canonicalizeHeaders($normalizedHeaders, $dkimHeader);
        
        // Create signature
        $signature = $this->createSignature($canonicalizedHeaders);
        
        // Add signature to DKIM header
        $dkimHeader .= 'b=' . $signature;
        
        return $dkimHeader;
    }

    /**
     * Normalize email headers
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        
        foreach ($headers as $name => $value) {
            $normalizedName = strtolower(trim($name));
            $normalizedValue = trim(preg_replace('/\s+/', ' ', $value));
            $normalized[$normalizedName] = $normalizedValue;
        }
        
        return $normalized;
    }

    /**
     * Create DKIM header structure
     */
    private function createDKIMHeader(array $headers): string
    {
        $headerList = implode(':', array_keys(array_intersect_key($headers, array_flip($this->headers))));
        
        $dkimParams = [
            'v=1',
            'a=rsa-sha256',
            'c=' . implode('/', $this->canonicalization),
            'd=' . $this->domain,
            's=' . $this->selector,
            'h=' . $headerList,
            'bh=' . $this->getBodyHash(''), // Empty body hash for now
            't=' . time(),
        ];

        return 'DKIM-Signature: ' . implode('; ', $dkimParams) . '; ';
    }

    /**
     * Canonicalize headers for signing
     */
    private function canonicalizeHeaders(array $headers, string $dkimHeader): string
    {
        $canonicalized = '';
        
        // Add selected headers in order
        foreach ($this->headers as $headerName) {
            if (isset($headers[strtolower($headerName)])) {
                $canonicalized .= $headerName . ':' . $headers[strtolower($headerName)] . "\r\n";
            }
        }
        
        // Add DKIM signature header (without signature value)
        $canonicalized .= trim($dkimHeader);
        
        return $canonicalized;
    }

    /**
     * Create cryptographic signature
     */
    private function createSignature(string $data): string
    {
        $privateKey = openssl_pkey_get_private($this->privateKey);
        
        if (!$privateKey) {
            throw new \Exception('Invalid DKIM private key');
        }

        $signature = '';
        $success = openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        
        if (!$success) {
            throw new \Exception('Failed to create DKIM signature');
        }

        return base64_encode($signature);
    }

    /**
     * Generate body hash for DKIM
     */
    private function getBodyHash(string $body): string
    {
        // Canonicalize body
        $canonicalizedBody = $this->canonicalizeBody($body);
        
        // Create hash
        return base64_encode(hash('sha256', $canonicalizedBody, true));
    }

    /**
     * Canonicalize email body
     */
    private function canonicalizeBody(string $body): string
    {
        if ($this->canonicalization[1] === 'relaxed') {
            // Relaxed canonicalization
            $body = preg_replace('/[ \t]+/', ' ', $body);
            $body = preg_replace('/[ \t]*\r?\n/', "\r\n", $body);
            $body = rtrim($body, "\r\n") . "\r\n";
        } else {
            // Simple canonicalization
            $body = str_replace("\n", "\r\n", $body);
            if (!str_ends_with($body, "\r\n")) {
                $body .= "\r\n";
            }
        }
        
        return $body;
    }

    /**
     * Generate DKIM key pair
     */
    public static function generateKeyPair(): array
    {
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $resource = openssl_pkey_new($config);
        
        if (!$resource) {
            throw new \Exception('Failed to generate DKIM key pair');
        }

        // Export private key
        openssl_pkey_export($resource, $privateKey);

        // Export public key
        $publicKeyDetails = openssl_pkey_get_details($resource);
        $publicKey = $publicKeyDetails['key'];

        // Format public key for DNS record
        $publicKeyForDNS = self::formatPublicKeyForDNS($publicKey);

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
            'dns_record' => $publicKeyForDNS
        ];
    }

    /**
     * Format public key for DNS TXT record
     */
    private static function formatPublicKeyForDNS(string $publicKey): string
    {
        // Extract the key material from PEM format
        $publicKey = str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----'], '', $publicKey);
        $publicKey = str_replace(["\r", "\n", " "], '', $publicKey);

        return "v=DKIM1; k=rsa; p=$publicKey";
    }

    /**
     * Verify DKIM signature (for testing)
     */
    public function verifySignature(string $headers, string $body, string $publicKey): bool
    {
        try {
            // Extract DKIM signature header
            if (!preg_match('/DKIM-Signature:\s*(.+)/i', $headers, $matches)) {
                return false;
            }

            $dkimHeader = $matches[1];
            
            // Parse DKIM parameters
            $params = $this->parseDKIMHeader($dkimHeader);
            
            if (!isset($params['b'])) {
                return false;
            }

            $signature = base64_decode($params['b']);
            
            // Recreate signed data
            $signedData = $this->recreateSignedData($headers, $dkimHeader);
            
            // Verify signature
            $publicKeyResource = openssl_pkey_get_public($publicKey);
            $result = openssl_verify($signedData, $signature, $publicKeyResource, OPENSSL_ALGO_SHA256);
            
            return $result === 1;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Parse DKIM header parameters
     */
    private function parseDKIMHeader(string $header): array
    {
        $params = [];
        $pairs = explode(';', $header);
        
        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if (strpos($pair, '=') !== false) {
                [$key, $value] = explode('=', $pair, 2);
                $params[trim($key)] = trim($value);
            }
        }
        
        return $params;
    }

    /**
     * Recreate signed data for verification
     */
    private function recreateSignedData(string $headers, string $dkimHeader): string
    {
        // This is a simplified implementation
        // In practice, you'd need to properly parse and canonicalize
        // the headers according to the DKIM specification
        
        $headerLines = explode("\r\n", $headers);
        $signedData = '';
        
        foreach ($headerLines as $line) {
            if (preg_match('/^(from|to|subject|date):/i', $line)) {
                $signedData .= $line . "\r\n";
            }
        }
        
        // Add DKIM header without signature
        $dkimWithoutSig = preg_replace('/b=[^;]+/', 'b=', $dkimHeader);
        $signedData .= 'DKIM-Signature: ' . trim($dkimWithoutSig);
        
        return $signedData;
    }

    /**
     * Get DNS record for DKIM setup
     */
    public function getDNSRecord(): string
    {
        if (empty($this->domain) || empty($this->selector)) {
            throw new \Exception('Domain and selector must be configured');
        }

        return "{$this->selector}._domainkey.{$this->domain}";
    }

    /**
     * Validate DKIM configuration
     */
    public function validateConfiguration(): array
    {
        $errors = [];

        if (empty($this->domain)) {
            $errors[] = 'Domain is required for DKIM signing';
        }

        if (empty($this->selector)) {
            $errors[] = 'Selector is required for DKIM signing';
        }

        if (empty($this->privateKey)) {
            $errors[] = 'Private key is required for DKIM signing';
        } elseif (!openssl_pkey_get_private($this->privateKey)) {
            $errors[] = 'Invalid private key format';
        }

        return $errors;
    }
}