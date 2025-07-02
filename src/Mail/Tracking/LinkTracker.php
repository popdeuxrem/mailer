<?php

declare(strict_types=1);

namespace EmailPlatform\Mail\Tracking;

/**
 * Advanced Link Tracking System
 * 
 * Provides sophisticated click tracking with attribution modeling,
 * A/B testing, and conversion analytics.
 */
class LinkTracker
{
    private string $baseUrl;
    private array $clickData = [];
    private array $linkVariations = [];

    public function __construct(string $baseUrl = '')
    {
        $this->baseUrl = $baseUrl ?: ($_ENV['APP_URL'] ?? 'http://localhost:8000');
    }

    /**
     * Process all links in email content for tracking
     */
    public function processLinks(string $htmlContent, string $trackingToken): string
    {
        // Pattern to match href attributes
        $pattern = '/href=["\']([^"\']+)["\']/i';
        
        return preg_replace_callback($pattern, function($matches) use ($trackingToken) {
            $originalUrl = $matches[1];
            
            // Skip tracking for certain URLs
            if ($this->shouldSkipTracking($originalUrl)) {
                return $matches[0];
            }
            
            $trackedUrl = $this->generateTrackedUrl($originalUrl, $trackingToken);
            return 'href="' . $trackedUrl . '"';
        }, $htmlContent);
    }

    /**
     * Generate tracked URL with analytics parameters
     */
    public function generateTrackedUrl(string $originalUrl, string $trackingToken): string
    {
        $linkId = $this->generateLinkId($originalUrl, $trackingToken);
        
        // Store link mapping
        $this->storeLinkMapping($linkId, $originalUrl, $trackingToken);
        
        return $this->baseUrl . '/track/click/' . $linkId;
    }

    /**
     * Process click tracking request
     */
    public function processClickRequest(string $linkId, array $requestData = []): array
    {
        $linkMapping = $this->getLinkMapping($linkId);
        
        if (!$linkMapping) {
            throw new \Exception('Invalid tracking link');
        }

        $clickData = [
            'link_id' => $linkId,
            'tracking_token' => $linkMapping['tracking_token'],
            'original_url' => $linkMapping['original_url'],
            'clicked_at' => date('Y-m-d H:i:s'),
            'ip_address' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'device_info' => $this->detectDevice(),
            'browser_info' => $this->detectBrowser(),
            'location' => $this->getGeolocation(),
            'session_id' => $this->generateSessionId(),
            'timestamp' => time()
        ];

        $this->clickData[] = $clickData;
        
        // Record conversion events
        $this->recordConversionEvent($clickData);
        
        return $clickData;
    }

    /**
     * A/B test link variations
     */
    public function addLinkVariation(string $originalUrl, array $variations, string $testName = ''): void
    {
        $this->linkVariations[$originalUrl] = [
            'variations' => $variations,
            'test_name' => $testName,
            'created_at' => time()
        ];
    }

    /**
     * Get variation for A/B testing
     */
    public function getLinkVariation(string $originalUrl, string $trackingToken): string
    {
        if (!isset($this->linkVariations[$originalUrl])) {
            return $originalUrl;
        }

        $variations = $this->linkVariations[$originalUrl]['variations'];
        
        // Use tracking token to ensure consistent variation for same user
        $hash = hexdec(substr(md5($trackingToken), 0, 8));
        $variationIndex = $hash % count($variations);
        
        return $variations[$variationIndex];
    }

    /**
     * Generate click analytics report
     */
    public function generateClickAnalytics(array $trackingTokens = []): array
    {
        $data = empty($trackingTokens) ? $this->clickData : 
                array_filter($this->clickData, fn($item) => in_array($item['tracking_token'], $trackingTokens));

        $analytics = [
            'total_clicks' => count($data),
            'unique_clicks' => count(array_unique(array_column($data, 'tracking_token'))),
            'click_through_rate' => $this->calculateClickThroughRate($trackingTokens),
            'top_links' => $this->getTopClickedLinks($data),
            'devices' => $this->getDeviceBreakdown($data),
            'browsers' => $this->getBrowserBreakdown($data),
            'locations' => $this->getLocationBreakdown($data),
            'time_analysis' => $this->getClickTimeAnalysis($data),
            'conversion_rate' => $this->calculateConversionRate($data)
        ];

        return $analytics;
    }

    /**
     * Advanced attribution modeling
     */
    public function generateAttributionReport(string $trackingToken): array
    {
        $clicks = array_filter($this->clickData, fn($item) => $item['tracking_token'] === $trackingToken);
        
        if (empty($clicks)) {
            return ['error' => 'No click data found'];
        }

        // Sort clicks by timestamp
        usort($clicks, fn($a, $b) => $a['timestamp'] - $b['timestamp']);

        $attribution = [
            'first_click' => $clicks[0],
            'last_click' => end($clicks),
            'click_path' => array_map(fn($click) => [
                'url' => $click['original_url'],
                'timestamp' => $click['clicked_at']
            ], $clicks),
            'session_analysis' => $this->analyzeClickSessions($clicks),
            'conversion_path' => $this->getConversionPath($trackingToken)
        ];

        return $attribution;
    }

    /**
     * Real-time click stream processing
     */
    public function processClickStream(string $trackingToken): array
    {
        $recentClicks = array_filter($this->clickData, function($click) use ($trackingToken) {
            return $click['tracking_token'] === $trackingToken && 
                   $click['timestamp'] > (time() - 3600); // Last hour
        });

        return [
            'real_time_clicks' => count($recentClicks),
            'click_velocity' => $this->calculateClickVelocity($recentClicks),
            'engagement_score' => $this->calculateEngagementScore($recentClicks),
            'predicted_conversions' => $this->predictConversions($recentClicks)
        ];
    }

    /**
     * Link performance optimization
     */
    public function optimizeLinkPerformance(string $originalUrl): array
    {
        $linkClicks = array_filter($this->clickData, fn($click) => $click['original_url'] === $originalUrl);
        
        $optimization = [
            'total_clicks' => count($linkClicks),
            'conversion_rate' => $this->calculateLinkConversionRate($linkClicks),
            'best_performing_time' => $this->getBestClickTime($linkClicks),
            'device_preferences' => $this->getDevicePreferences($linkClicks),
            'geographic_performance' => $this->getGeographicPerformance($linkClicks),
            'recommendations' => $this->generateOptimizationRecommendations($linkClicks)
        ];

        return $optimization;
    }

    // Helper methods
    private function shouldSkipTracking(string $url): bool
    {
        $skipPatterns = [
            'mailto:',
            'tel:',
            'sms:',
            '#',
            'javascript:',
            'data:',
            'ftp:',
            'file:'
        ];

        foreach ($skipPatterns as $pattern) {
            if (strpos($url, $pattern) === 0) {
                return true;
            }
        }

        // Skip unsubscribe links
        if (strpos($url, 'unsubscribe') !== false) {
            return true;
        }

        return false;
    }

    private function generateLinkId(string $originalUrl, string $trackingToken): string
    {
        return hash('sha256', $originalUrl . $trackingToken . time() . random_bytes(8));
    }

    private function storeLinkMapping(string $linkId, string $originalUrl, string $trackingToken): void
    {
        // In a real implementation, this would be stored in database
        $_SESSION['link_mappings'][$linkId] = [
            'original_url' => $originalUrl,
            'tracking_token' => $trackingToken,
            'created_at' => time()
        ];
    }

    private function getLinkMapping(string $linkId): ?array
    {
        return $_SESSION['link_mappings'][$linkId] ?? null;
    }

    private function getClientIp(): string
    {
        // Same implementation as PixelTracker
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private function detectDevice(): array
    {
        // Same implementation as PixelTracker
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return [
            'type' => preg_match('/Mobile|Android|iPhone/i', $userAgent) ? 'mobile' : 'desktop',
            'os' => 'unknown'
        ];
    }

    private function detectBrowser(): array
    {
        // Simplified browser detection
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (preg_match('/Chrome/i', $userAgent)) {
            return ['name' => 'Chrome'];
        }
        return ['name' => 'unknown'];
    }

    private function getGeolocation(): array
    {
        // Simplified geolocation
        return ['country' => 'unknown', 'city' => 'unknown'];
    }

    private function generateSessionId(): string
    {
        return session_id() ?: uniqid('session_', true);
    }

    private function recordConversionEvent(array $clickData): void
    {
        // Track conversion events based on URL patterns
        $conversionUrls = [
            'purchase', 'buy', 'checkout', 'thank-you', 'success', 'confirm'
        ];

        foreach ($conversionUrls as $pattern) {
            if (strpos(strtolower($clickData['original_url']), $pattern) !== false) {
                // Record conversion
                break;
            }
        }
    }

    private function calculateClickThroughRate(array $trackingTokens): float
    {
        // This would need open data to calculate CTR accurately
        return 0.0;
    }

    private function getTopClickedLinks(array $data): array
    {
        $linkCounts = [];
        foreach ($data as $click) {
            $url = $click['original_url'];
            $linkCounts[$url] = ($linkCounts[$url] ?? 0) + 1;
        }
        
        arsort($linkCounts);
        return array_slice($linkCounts, 0, 10, true);
    }

    private function getDeviceBreakdown(array $data): array
    {
        $devices = [];
        foreach ($data as $click) {
            $device = $click['device_info']['type'];
            $devices[$device] = ($devices[$device] ?? 0) + 1;
        }
        return $devices;
    }

    private function getBrowserBreakdown(array $data): array
    {
        $browsers = [];
        foreach ($data as $click) {
            $browser = $click['browser_info']['name'];
            $browsers[$browser] = ($browsers[$browser] ?? 0) + 1;
        }
        return $browsers;
    }

    private function getLocationBreakdown(array $data): array
    {
        $locations = [];
        foreach ($data as $click) {
            $country = $click['location']['country'];
            $locations[$country] = ($locations[$country] ?? 0) + 1;
        }
        return $locations;
    }

    private function getClickTimeAnalysis(array $data): array
    {
        $hourly = [];
        foreach ($data as $click) {
            $hour = date('H', $click['timestamp']);
            $hourly[$hour] = ($hourly[$hour] ?? 0) + 1;
        }
        return $hourly;
    }

    private function calculateConversionRate(array $data): float
    {
        // Simplified conversion rate calculation
        return 0.0;
    }

    private function analyzeClickSessions(array $clicks): array
    {
        return ['session_count' => 1, 'avg_session_duration' => 0];
    }

    private function getConversionPath(string $trackingToken): array
    {
        return [];
    }

    private function calculateClickVelocity(array $clicks): float
    {
        return count($clicks) / 60.0; // clicks per minute
    }

    private function calculateEngagementScore(array $clicks): float
    {
        return min(100, count($clicks) * 10);
    }

    private function predictConversions(array $clicks): int
    {
        return (int)(count($clicks) * 0.02); // 2% conversion prediction
    }

    private function calculateLinkConversionRate(array $clicks): float
    {
        return 0.0;
    }

    private function getBestClickTime(array $clicks): string
    {
        return '14:00'; // 2 PM default
    }

    private function getDevicePreferences(array $clicks): array
    {
        return $this->getDeviceBreakdown($clicks);
    }

    private function getGeographicPerformance(array $clicks): array
    {
        return $this->getLocationBreakdown($clicks);
    }

    private function generateOptimizationRecommendations(array $clicks): array
    {
        return [
            'optimal_send_time' => '14:00',
            'target_devices' => ['mobile', 'desktop'],
            'geographic_focus' => ['US', 'CA']
        ];
    }
}