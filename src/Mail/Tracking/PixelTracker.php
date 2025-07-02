<?php

declare(strict_types=1);

namespace EmailPlatform\Mail\Tracking;

/**
 * Advanced Pixel Tracking System
 * 
 * Provides sophisticated email open tracking with geolocation,
 * device detection, and real-time analytics.
 */
class PixelTracker
{
    private string $baseUrl;
    private array $trackingData = [];

    public function __construct(string $baseUrl = '')
    {
        $this->baseUrl = $baseUrl ?: ($_ENV['APP_URL'] ?? 'http://localhost:8000');
    }

    /**
     * Generate tracking pixel URL
     */
    public function generatePixelUrl(string $trackingToken): string
    {
        return $this->baseUrl . '/track/pixel/' . $trackingToken;
    }

    /**
     * Generate HTML for tracking pixel
     */
    public function getPixelHtml(string $pixelUrl): string
    {
        return sprintf(
            '<img src="%s" width="1" height="1" style="display:none;border:0;outline:none;" alt="" />',
            $pixelUrl
        );
    }

    /**
     * Process pixel tracking request
     */
    public function processPixelRequest(string $trackingToken, array $requestData = []): array
    {
        $tracking = [
            'token' => $trackingToken,
            'opened_at' => date('Y-m-d H:i:s'),
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'device_info' => $this->detectDevice(),
            'browser_info' => $this->detectBrowser(),
            'location' => $this->getGeolocation(),
            'timestamp' => time()
        ];

        $this->trackingData[] = $tracking;
        return $tracking;
    }

    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Detect device information
     */
    private function detectDevice(): array
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $device = [
            'type' => 'desktop',
            'os' => 'unknown',
            'brand' => 'unknown',
            'model' => 'unknown'
        ];

        // Mobile detection
        if (preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $userAgent)) {
            $device['type'] = 'mobile';
        }

        // Tablet detection
        if (preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $userAgent)) {
            $device['type'] = 'tablet';
        }

        // OS detection
        if (preg_match('/Windows NT/i', $userAgent)) {
            $device['os'] = 'Windows';
        } elseif (preg_match('/Mac OS X/i', $userAgent)) {
            $device['os'] = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $device['os'] = 'Linux';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $device['os'] = 'Android';
        } elseif (preg_match('/iOS|iPhone OS/i', $userAgent)) {
            $device['os'] = 'iOS';
        }

        // Brand detection for mobile devices
        if (preg_match('/iPhone/i', $userAgent)) {
            $device['brand'] = 'Apple';
            $device['model'] = 'iPhone';
        } elseif (preg_match('/iPad/i', $userAgent)) {
            $device['brand'] = 'Apple';
            $device['model'] = 'iPad';
        } elseif (preg_match('/Samsung/i', $userAgent)) {
            $device['brand'] = 'Samsung';
        } elseif (preg_match('/Huawei/i', $userAgent)) {
            $device['brand'] = 'Huawei';
        }

        return $device;
    }

    /**
     * Detect browser information
     */
    private function detectBrowser(): array
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $browser = [
            'name' => 'unknown',
            'version' => 'unknown',
            'engine' => 'unknown'
        ];

        // Browser detection
        if (preg_match('/Chrome\/([0-9\.]+)/i', $userAgent, $matches)) {
            $browser['name'] = 'Chrome';
            $browser['version'] = $matches[1];
            $browser['engine'] = 'Blink';
        } elseif (preg_match('/Firefox\/([0-9\.]+)/i', $userAgent, $matches)) {
            $browser['name'] = 'Firefox';
            $browser['version'] = $matches[1];
            $browser['engine'] = 'Gecko';
        } elseif (preg_match('/Safari\/([0-9\.]+)/i', $userAgent, $matches)) {
            $browser['name'] = 'Safari';
            $browser['version'] = $matches[1];
            $browser['engine'] = 'WebKit';
        } elseif (preg_match('/Edge\/([0-9\.]+)/i', $userAgent, $matches)) {
            $browser['name'] = 'Edge';
            $browser['version'] = $matches[1];
            $browser['engine'] = 'EdgeHTML';
        } elseif (preg_match('/Opera\/([0-9\.]+)/i', $userAgent, $matches)) {
            $browser['name'] = 'Opera';
            $browser['version'] = $matches[1];
            $browser['engine'] = 'Presto';
        }

        return $browser;
    }

    /**
     * Get geolocation data based on IP
     */
    private function getGeolocation(): array
    {
        $ip = $this->getClientIp();
        
        // Default location data
        $location = [
            'country' => 'unknown',
            'country_code' => 'unknown',
            'region' => 'unknown',
            'city' => 'unknown',
            'timezone' => 'UTC',
            'latitude' => null,
            'longitude' => null,
            'isp' => 'unknown'
        ];

        try {
            // Try to get geolocation from free service
            $response = @file_get_contents("http://ip-api.com/json/{$ip}");
            
            if ($response) {
                $data = json_decode($response, true);
                
                if ($data && $data['status'] === 'success') {
                    $location = [
                        'country' => $data['country'] ?? 'unknown',
                        'country_code' => $data['countryCode'] ?? 'unknown',
                        'region' => $data['regionName'] ?? 'unknown',
                        'city' => $data['city'] ?? 'unknown',
                        'timezone' => $data['timezone'] ?? 'UTC',
                        'latitude' => $data['lat'] ?? null,
                        'longitude' => $data['lon'] ?? null,
                        'isp' => $data['isp'] ?? 'unknown'
                    ];
                }
            }
        } catch (\Exception $e) {
            // Fallback to default location
        }

        return $location;
    }

    /**
     * Generate analytics report for tracking data
     */
    public function generateAnalytics(array $trackingTokens = []): array
    {
        $data = empty($trackingTokens) ? $this->trackingData : 
                array_filter($this->trackingData, fn($item) => in_array($item['token'], $trackingTokens));

        $analytics = [
            'total_opens' => count($data),
            'unique_opens' => count(array_unique(array_column($data, 'token'))),
            'devices' => [],
            'browsers' => [],
            'locations' => [],
            'open_times' => [],
            'user_agents' => []
        ];

        foreach ($data as $tracking) {
            // Device analytics
            $deviceType = $tracking['device_info']['type'];
            $analytics['devices'][$deviceType] = ($analytics['devices'][$deviceType] ?? 0) + 1;

            // Browser analytics
            $browserName = $tracking['browser_info']['name'];
            $analytics['browsers'][$browserName] = ($analytics['browsers'][$browserName] ?? 0) + 1;

            // Location analytics
            $country = $tracking['location']['country'];
            $analytics['locations'][$country] = ($analytics['locations'][$country] ?? 0) + 1;

            // Time analytics
            $hour = date('H', $tracking['timestamp']);
            $analytics['open_times'][$hour] = ($analytics['open_times'][$hour] ?? 0) + 1;

            // User agent analytics
            $userAgent = substr($tracking['user_agent'], 0, 50) . '...';
            $analytics['user_agents'][$userAgent] = ($analytics['user_agents'][$userAgent] ?? 0) + 1;
        }

        return $analytics;
    }

    /**
     * Get tracking data for specific token
     */
    public function getTrackingData(string $trackingToken): array
    {
        return array_filter($this->trackingData, fn($item) => $item['token'] === $trackingToken);
    }

    /**
     * Export tracking data as CSV
     */
    public function exportToCsv(array $trackingTokens = []): string
    {
        $data = empty($trackingTokens) ? $this->trackingData : 
                array_filter($this->trackingData, fn($item) => in_array($item['token'], $trackingTokens));

        $csv = "Token,Opened At,IP Address,Device Type,OS,Browser,Country,City,User Agent\n";

        foreach ($data as $tracking) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,\"%s\"\n",
                $tracking['token'],
                $tracking['opened_at'],
                $tracking['ip_address'],
                $tracking['device_info']['type'],
                $tracking['device_info']['os'],
                $tracking['browser_info']['name'],
                $tracking['location']['country'],
                $tracking['location']['city'],
                str_replace('"', '""', $tracking['user_agent'])
            );
        }

        return $csv;
    }

    /**
     * Clear tracking data
     */
    public function clearTrackingData(): void
    {
        $this->trackingData = [];
    }

    /**
     * Get tracking statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_tracking_records' => count($this->trackingData),
            'memory_usage' => memory_get_usage(),
            'unique_tokens' => count(array_unique(array_column($this->trackingData, 'token')))
        ];
    }
}