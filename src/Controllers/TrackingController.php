<?php

declare(strict_types=1);

namespace EmailPlatform\Controllers;

use EmailPlatform\Core\Database;
use EmailPlatform\Mail\Tracking\PixelTracker;
use EmailPlatform\Mail\Tracking\LinkTracker;
use Monolog\Logger;

/**
 * Tracking Controller
 * 
 * Handles email open tracking (pixel) and link click tracking
 * with comprehensive analytics and real-time data collection.
 */
class TrackingController
{
    private Database $database;
    private PixelTracker $pixelTracker;
    private LinkTracker $linkTracker;
    private Logger $logger;

    public function __construct(Database $database, PixelTracker $pixelTracker, LinkTracker $linkTracker, Logger $logger)
    {
        $this->database = $database;
        $this->pixelTracker = $pixelTracker;
        $this->linkTracker = $linkTracker;
        $this->logger = $logger;
    }

    /**
     * Handle pixel tracking for email opens
     */
    public function pixel(string $token): void
    {
        try {
            // Process the pixel request
            $trackingData = $this->pixelTracker->processPixelRequest($token);
            
            // Store tracking data in database
            $this->storeEmailOpen($token, $trackingData);
            
            // Update campaign and subscriber metrics
            $this->updateOpenMetrics($token);
            
            // Log the event
            $this->logger->info("Email opened", [
                'token' => $token,
                'ip' => $trackingData['ip_address'],
                'user_agent' => $trackingData['user_agent']
            ]);

        } catch (\Exception $e) {
            $this->logger->error("Pixel tracking error: " . $e->getMessage());
        }

        // Return 1x1 transparent pixel
        $this->outputTrackingPixel();
    }

    /**
     * Handle link click tracking
     */
    public function click(string $linkId): void
    {
        try {
            // Process the click request
            $clickData = $this->linkTracker->processClickRequest($linkId);
            
            // Store click data in database
            $this->storeEmailClick($linkId, $clickData);
            
            // Update campaign and subscriber metrics
            $this->updateClickMetrics($clickData['tracking_token'], $clickData['original_url']);
            
            // Log the event
            $this->logger->info("Email link clicked", [
                'link_id' => $linkId,
                'url' => $clickData['original_url'],
                'ip' => $clickData['ip_address']
            ]);

            // Redirect to original URL
            header('Location: ' . $clickData['original_url'], true, 302);
            exit;

        } catch (\Exception $e) {
            $this->logger->error("Click tracking error: " . $e->getMessage());
            
            // Fallback redirect
            header('Location: /', true, 302);
            exit;
        }
    }

    /**
     * Store email open data in database
     */
    private function storeEmailOpen(string $token, array $trackingData): void
    {
        // Get campaign and subscriber info from token
        $emailLog = $this->getEmailLogByToken($token);
        
        if (!$emailLog) {
            throw new \Exception("Email log not found for token: $token");
        }

        // Check if this is the first open for this email
        $existingOpen = $this->database->fetchOne(
            "SELECT id FROM email_opens WHERE email_log_id = ? AND ip_address = ?",
            [$emailLog['id'], $trackingData['ip_address']]
        );

        $openData = [
            'email_log_id' => $emailLog['id'],
            'campaign_id' => $emailLog['campaign_id'],
            'subscriber_id' => $emailLog['subscriber_id'],
            'tracking_token' => $token,
            'ip_address' => $trackingData['ip_address'],
            'user_agent' => $trackingData['user_agent'],
            'device_type' => $trackingData['device_info']['type'],
            'device_os' => $trackingData['device_info']['os'],
            'browser_name' => $trackingData['browser_info']['name'],
            'browser_version' => $trackingData['browser_info']['version'] ?? '',
            'country' => $trackingData['location']['country'],
            'city' => $trackingData['location']['city'],
            'region' => $trackingData['location']['region'],
            'timezone' => $trackingData['location']['timezone'],
            'latitude' => $trackingData['location']['latitude'],
            'longitude' => $trackingData['location']['longitude'],
            'is_unique' => $existingOpen ? 0 : 1,
            'opened_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->database->insert('email_opens', $openData);
    }

    /**
     * Store email click data in database
     */
    private function storeEmailClick(string $linkId, array $clickData): void
    {
        // Get campaign and subscriber info from tracking token
        $emailLog = $this->getEmailLogByToken($clickData['tracking_token']);
        
        if (!$emailLog) {
            throw new \Exception("Email log not found for token: " . $clickData['tracking_token']);
        }

        // Check if this is the first click for this link
        $existingClick = $this->database->fetchOne(
            "SELECT id FROM email_clicks WHERE email_log_id = ? AND link_url = ? AND ip_address = ?",
            [$emailLog['id'], $clickData['original_url'], $clickData['ip_address']]
        );

        $clickDataDb = [
            'email_log_id' => $emailLog['id'],
            'campaign_id' => $emailLog['campaign_id'],
            'subscriber_id' => $emailLog['subscriber_id'],
            'tracking_token' => $clickData['tracking_token'],
            'link_id' => $linkId,
            'link_url' => $clickData['original_url'],
            'ip_address' => $clickData['ip_address'],
            'user_agent' => $clickData['user_agent'],
            'device_type' => $clickData['device_info']['type'],
            'device_os' => $clickData['device_info']['os'] ?? 'unknown',
            'browser_name' => $clickData['browser_info']['name'],
            'country' => $clickData['location']['country'],
            'city' => $clickData['location']['city'],
            'is_unique' => $existingClick ? 0 : 1,
            'clicked_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->database->insert('email_clicks', $clickDataDb);
    }

    /**
     * Update campaign and subscriber open metrics
     */
    private function updateOpenMetrics(string $token): void
    {
        $emailLog = $this->getEmailLogByToken($token);
        
        if (!$emailLog) {
            return;
        }

        // Update campaign metrics
        $this->database->query(
            "UPDATE campaigns SET 
                emails_opened = emails_opened + 1,
                open_rate = ROUND((emails_opened * 100.0 / NULLIF(emails_sent, 0)), 2)
             WHERE id = ?",
            [$emailLog['campaign_id']]
        );

        // Update unique opens if this is the first open
        $openCount = $this->database->fetchOne(
            "SELECT COUNT(*) as count FROM email_opens WHERE email_log_id = ?",
            [$emailLog['id']]
        );

        if ($openCount['count'] == 1) {
            $this->database->query(
                "UPDATE campaigns SET 
                    unique_opens = unique_opens + 1
                 WHERE id = ?",
                [$emailLog['campaign_id']]
            );
        }

        // Update subscriber metrics
        $this->database->query(
            "UPDATE subscribers SET 
                total_emails_opened = total_emails_opened + 1,
                last_email_opened = ?,
                engagement_score = LEAST(100, engagement_score + 2)
             WHERE id = ?",
            [date('Y-m-d H:i:s'), $emailLog['subscriber_id']]
        );

        // Calculate and update subscriber open rate
        $subscriberStats = $this->database->fetchOne(
            "SELECT total_emails_sent, total_emails_opened FROM subscribers WHERE id = ?",
            [$emailLog['subscriber_id']]
        );

        if ($subscriberStats && $subscriberStats['total_emails_sent'] > 0) {
            $openRate = ($subscriberStats['total_emails_opened'] / $subscriberStats['total_emails_sent']) * 100;
            $this->database->query(
                "UPDATE subscribers SET open_rate = ? WHERE id = ?",
                [round($openRate, 2), $emailLog['subscriber_id']]
            );
        }
    }

    /**
     * Update campaign and subscriber click metrics
     */
    private function updateClickMetrics(string $token, string $url): void
    {
        $emailLog = $this->getEmailLogByToken($token);
        
        if (!$emailLog) {
            return;
        }

        // Update campaign metrics
        $this->database->query(
            "UPDATE campaigns SET 
                clicks = clicks + 1,
                click_rate = ROUND((clicks * 100.0 / NULLIF(emails_sent, 0)), 2),
                click_to_open_rate = ROUND((clicks * 100.0 / NULLIF(unique_opens, 0)), 2)
             WHERE id = ?",
            [$emailLog['campaign_id']]
        );

        // Update unique clicks if this is the first click for this email
        $clickCount = $this->database->fetchOne(
            "SELECT COUNT(*) as count FROM email_clicks WHERE email_log_id = ?",
            [$emailLog['id']]
        );

        if ($clickCount['count'] == 1) {
            $this->database->query(
                "UPDATE campaigns SET 
                    unique_clicks = unique_clicks + 1
                 WHERE id = ?",
                [$emailLog['campaign_id']]
            );
        }

        // Update subscriber metrics
        $this->database->query(
            "UPDATE subscribers SET 
                total_clicks = total_clicks + 1,
                last_click_date = ?,
                engagement_score = LEAST(100, engagement_score + 5)
             WHERE id = ?",
            [date('Y-m-d H:i:s'), $emailLog['subscriber_id']]
        );

        // Calculate and update subscriber click rate
        $subscriberStats = $this->database->fetchOne(
            "SELECT total_emails_sent, total_clicks FROM subscribers WHERE id = ?",
            [$emailLog['subscriber_id']]
        );

        if ($subscriberStats && $subscriberStats['total_emails_sent'] > 0) {
            $clickRate = ($subscriberStats['total_clicks'] / $subscriberStats['total_emails_sent']) * 100;
            $this->database->query(
                "UPDATE subscribers SET click_rate = ? WHERE id = ?",
                [round($clickRate, 2), $emailLog['subscriber_id']]
            );
        }

        // Track conversion events for specific URLs
        $this->trackConversionEvent($emailLog['campaign_id'], $emailLog['subscriber_id'], $url);
    }

    /**
     * Track conversion events based on URL patterns
     */
    private function trackConversionEvent(int $campaignId, int $subscriberId, string $url): void
    {
        $conversionPatterns = [
            'purchase' => ['purchase', 'buy', 'order', 'checkout', 'payment'],
            'signup' => ['signup', 'register', 'join', 'subscribe'],
            'download' => ['download', 'pdf', 'ebook', 'whitepaper'],
            'contact' => ['contact', 'demo', 'consultation', 'meeting']
        ];

        foreach ($conversionPatterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($url, $pattern) !== false) {
                    // Record conversion
                    $this->database->insert('email_conversions', [
                        'campaign_id' => $campaignId,
                        'subscriber_id' => $subscriberId,
                        'conversion_type' => $type,
                        'conversion_url' => $url,
                        'converted_at' => date('Y-m-d H:i:s'),
                        'created_at' => date('Y-m-d H:i:s')
                    ]);

                    // Update campaign conversion metrics
                    $this->database->query(
                        "UPDATE campaigns SET 
                            conversions = conversions + 1,
                            conversion_rate = ROUND((conversions * 100.0 / NULLIF(emails_sent, 0)), 2)
                         WHERE id = ?",
                        [$campaignId]
                    );

                    return; // Only record first matching conversion type
                }
            }
        }
    }

    /**
     * Get email log by tracking token
     */
    private function getEmailLogByToken(string $token): ?array
    {
        return $this->database->fetchOne(
            "SELECT * FROM email_logs WHERE tracking_token = ?",
            [$token]
        );
    }

    /**
     * Output 1x1 transparent tracking pixel
     */
    private function outputTrackingPixel(): void
    {
        // Set headers for image response
        header('Content-Type: image/gif');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Length: 43');

        // Output 1x1 transparent GIF
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }

    /**
     * Get tracking analytics for a campaign
     */
    public function getCampaignAnalytics(int $campaignId): array
    {
        // Open analytics
        $openStats = $this->database->fetchOne(
            "SELECT 
                COUNT(*) as total_opens,
                COUNT(DISTINCT subscriber_id) as unique_opens,
                COUNT(DISTINCT device_type) as device_types,
                COUNT(DISTINCT country) as countries
             FROM email_opens 
             WHERE campaign_id = ?",
            [$campaignId]
        );

        // Click analytics
        $clickStats = $this->database->fetchOne(
            "SELECT 
                COUNT(*) as total_clicks,
                COUNT(DISTINCT subscriber_id) as unique_clicks,
                COUNT(DISTINCT link_url) as unique_links
             FROM email_clicks 
             WHERE campaign_id = ?",
            [$campaignId]
        );

        // Time-based analytics
        $hourlyOpens = $this->database->fetchAll(
            "SELECT 
                HOUR(opened_at) as hour,
                COUNT(*) as opens
             FROM email_opens 
             WHERE campaign_id = ?
             GROUP BY HOUR(opened_at)
             ORDER BY hour",
            [$campaignId]
        );

        // Geographic analytics
        $countryStats = $this->database->fetchAll(
            "SELECT 
                country,
                COUNT(*) as opens,
                COUNT(DISTINCT subscriber_id) as unique_subscribers
             FROM email_opens 
             WHERE campaign_id = ? AND country != 'unknown'
             GROUP BY country
             ORDER BY opens DESC
             LIMIT 10",
            [$campaignId]
        );

        // Device analytics
        $deviceStats = $this->database->fetchAll(
            "SELECT 
                device_type,
                COUNT(*) as opens,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM email_opens WHERE campaign_id = ?), 2) as percentage
             FROM email_opens 
             WHERE campaign_id = ?
             GROUP BY device_type",
            [$campaignId, $campaignId]
        );

        // Top clicked links
        $topLinks = $this->database->fetchAll(
            "SELECT 
                link_url,
                COUNT(*) as clicks,
                COUNT(DISTINCT subscriber_id) as unique_clickers
             FROM email_clicks 
             WHERE campaign_id = ?
             GROUP BY link_url
             ORDER BY clicks DESC
             LIMIT 10",
            [$campaignId]
        );

        return [
            'opens' => $openStats ?: ['total_opens' => 0, 'unique_opens' => 0, 'device_types' => 0, 'countries' => 0],
            'clicks' => $clickStats ?: ['total_clicks' => 0, 'unique_clicks' => 0, 'unique_links' => 0],
            'hourly_opens' => $hourlyOpens ?: [],
            'countries' => $countryStats ?: [],
            'devices' => $deviceStats ?: [],
            'top_links' => $topLinks ?: []
        ];
    }

    /**
     * Get real-time tracking data
     */
    public function getRealtimeData(int $campaignId): array
    {
        $recentOpens = $this->database->fetchAll(
            "SELECT 
                eo.opened_at,
                s.email,
                s.first_name,
                s.last_name,
                eo.country,
                eo.device_type
             FROM email_opens eo
             JOIN subscribers s ON eo.subscriber_id = s.id
             WHERE eo.campaign_id = ? AND eo.opened_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             ORDER BY eo.opened_at DESC
             LIMIT 20",
            [$campaignId]
        );

        $recentClicks = $this->database->fetchAll(
            "SELECT 
                ec.clicked_at,
                s.email,
                s.first_name,
                s.last_name,
                ec.link_url,
                ec.country
             FROM email_clicks ec
             JOIN subscribers s ON ec.subscriber_id = s.id
             WHERE ec.campaign_id = ? AND ec.clicked_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             ORDER BY ec.clicked_at DESC
             LIMIT 20",
            [$campaignId]
        );

        return [
            'recent_opens' => $recentOpens ?: [],
            'recent_clicks' => $recentClicks ?: []
        ];
    }
}