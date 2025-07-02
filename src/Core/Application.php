<?php

declare(strict_types=1);

namespace EmailPlatform\Core;

use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use EmailPlatform\Core\Database;
use EmailPlatform\Core\Router;
use EmailPlatform\Core\Container;
use EmailPlatform\Auth\AuthManager;
use EmailPlatform\Mail\MailManager;
use EmailPlatform\SMS\SMSManager;
use Exception;

/**
 * Main Application Bootstrap Class
 * 
 * Handles application initialization, dependency injection,
 * and request routing for the email marketing platform.
 */
class Application
{
    private string $basePath;
    private Container $container;
    private Router $router;
    private Logger $logger;
    private array $config;

    public function __construct(string $basePath = null)
    {
        $this->basePath = $basePath ?: dirname(__DIR__, 2);
        $this->container = new Container();
        $this->loadEnvironment();
        $this->setupLogging();
        $this->loadConfiguration();
        $this->registerServices();
        $this->setupRoutes();
    }

    /**
     * Load environment variables
     */
    private function loadEnvironment(): void
    {
        if (file_exists($this->basePath . '/.env')) {
            $dotenv = Dotenv::createImmutable($this->basePath);
            $dotenv->load();
        }
    }

    /**
     * Setup application logging
     */
    private function setupLogging(): void
    {
        $this->logger = new Logger('email_platform');
        
        $logPath = $_ENV['LOG_PATH'] ?? $this->basePath . '/storage/logs/application.log';
        $logLevel = match ($_ENV['LOG_LEVEL'] ?? 'info') {
            'debug' => Logger::DEBUG,
            'info' => Logger::INFO,
            'warning' => Logger::WARNING,
            'error' => Logger::ERROR,
            default => Logger::INFO
        };

        $this->logger->pushHandler(new StreamHandler($logPath, $logLevel));
        $this->container->bind('logger', $this->logger);
    }

    /**
     * Load application configuration
     */
    private function loadConfiguration(): void
    {
        $this->config = [
            'app' => [
                'name' => $_ENV['APP_NAME'] ?? 'Email Marketing Platform',
                'url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
                'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'timezone' => $_ENV['APP_TIMEZONE'] ?? 'UTC',
                'key' => $_ENV['APP_KEY'] ?? '',
            ],
            'database' => [
                'type' => $_ENV['DB_TYPE'] ?? 'sqlite',
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => (int)($_ENV['DB_PORT'] ?? 3306),
                'name' => $_ENV['DB_NAME'] ?? 'email_platform',
                'user' => $_ENV['DB_USER'] ?? '',
                'pass' => $_ENV['DB_PASS'] ?? '',
                'path' => $_ENV['DB_PATH'] ?? $this->basePath . '/storage/database.sqlite',
            ],
            'mail' => [
                'driver' => $_ENV['MAIL_DRIVER'] ?? 'smtp',
                'host' => $_ENV['MAIL_HOST'] ?? '',
                'port' => (int)($_ENV['MAIL_PORT'] ?? 587),
                'username' => $_ENV['MAIL_USERNAME'] ?? '',
                'password' => $_ENV['MAIL_PASSWORD'] ?? '',
                'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
                'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? '',
                'from_name' => $_ENV['MAIL_FROM_NAME'] ?? '',
                'servers' => json_decode($_ENV['SMTP_SERVERS'] ?? '[]', true),
            ],
            'sms' => [
                'provider' => $_ENV['SMS_PROVIDER'] ?? 'twilio',
                'api_key' => $_ENV['SMS_API_KEY'] ?? '',
                'api_secret' => $_ENV['SMS_API_SECRET'] ?? '',
                'from_number' => $_ENV['SMS_FROM_NUMBER'] ?? '',
                'gateways' => json_decode($_ENV['SMS_GATEWAYS'] ?? '[]', true),
            ],
            'security' => [
                'jwt_secret' => $_ENV['JWT_SECRET'] ?? '',
                'session_lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 86400),
                'rate_limit_email' => (int)($_ENV['RATE_LIMIT_EMAIL'] ?? 100),
                'rate_limit_api' => (int)($_ENV['RATE_LIMIT_API'] ?? 1000),
                'csrf_protection' => filter_var($_ENV['CSRF_PROTECTION'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
            'analytics' => [
                'enabled' => filter_var($_ENV['ANALYTICS_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'tracking_pixel' => filter_var($_ENV['TRACKING_PIXEL'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'click_tracking' => filter_var($_ENV['CLICK_TRACKING'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'geolocation' => filter_var($_ENV['GEOLOCATION_TRACKING'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
        ];

        $this->container->bind('config', $this->config);
    }

    /**
     * Register core services
     */
    private function registerServices(): void
    {
        // Database
        $this->container->bind('database', function() {
            return new Database($this->config['database']);
        });

        // Authentication
        $this->container->bind('auth', function() {
            return new AuthManager($this->container->get('database'), $this->config['security']);
        });

        // Mail Manager
        $this->container->bind('mail', function() {
            return new MailManager($this->config['mail'], $this->logger);
        });

        // SMS Manager
        $this->container->bind('sms', function() {
            return new SMSManager($this->config['sms'], $this->logger);
        });

        // Router
        $this->router = new Router($this->container);
        $this->container->bind('router', $this->router);
    }

    /**
     * Setup application routes
     */
    private function setupRoutes(): void
    {
        // Authentication routes
        $this->router->post('/api/auth/login', 'EmailPlatform\Controllers\AuthController@login');
        $this->router->post('/api/auth/logout', 'EmailPlatform\Controllers\AuthController@logout');
        $this->router->post('/api/auth/register', 'EmailPlatform\Controllers\AuthController@register');

        // Campaign routes
        $this->router->get('/api/campaigns', 'EmailPlatform\Controllers\CampaignController@index');
        $this->router->post('/api/campaigns', 'EmailPlatform\Controllers\CampaignController@create');
        $this->router->get('/api/campaigns/{id}', 'EmailPlatform\Controllers\CampaignController@show');
        $this->router->put('/api/campaigns/{id}', 'EmailPlatform\Controllers\CampaignController@update');
        $this->router->delete('/api/campaigns/{id}', 'EmailPlatform\Controllers\CampaignController@delete');
        $this->router->post('/api/campaigns/{id}/send', 'EmailPlatform\Controllers\CampaignController@send');

        // Subscriber routes
        $this->router->get('/api/subscribers', 'EmailPlatform\Controllers\SubscriberController@index');
        $this->router->post('/api/subscribers', 'EmailPlatform\Controllers\SubscriberController@create');
        $this->router->get('/api/subscribers/{id}', 'EmailPlatform\Controllers\SubscriberController@show');
        $this->router->put('/api/subscribers/{id}', 'EmailPlatform\Controllers\SubscriberController@update');
        $this->router->delete('/api/subscribers/{id}', 'EmailPlatform\Controllers\SubscriberController@delete');

        // Analytics routes
        $this->router->get('/api/analytics/campaigns/{id}', 'EmailPlatform\Controllers\AnalyticsController@campaignStats');
        $this->router->get('/api/analytics/overview', 'EmailPlatform\Controllers\AnalyticsController@overview');

        // Template routes
        $this->router->get('/api/templates', 'EmailPlatform\Controllers\TemplateController@index');
        $this->router->post('/api/templates', 'EmailPlatform\Controllers\TemplateController@create');
        $this->router->get('/api/templates/{id}', 'EmailPlatform\Controllers\TemplateController@show');
        $this->router->put('/api/templates/{id}', 'EmailPlatform\Controllers\TemplateController@update');

        // Dashboard routes
        $this->router->get('/', 'EmailPlatform\Controllers\DashboardController@index');
        $this->router->get('/dashboard', 'EmailPlatform\Controllers\DashboardController@dashboard');
        $this->router->get('/campaigns', 'EmailPlatform\Controllers\DashboardController@campaigns');
        $this->router->get('/subscribers', 'EmailPlatform\Controllers\DashboardController@subscribers');
        $this->router->get('/analytics', 'EmailPlatform\Controllers\DashboardController@analytics');

        // Tracking routes
        $this->router->get('/track/pixel/{token}', 'EmailPlatform\Controllers\TrackingController@pixel');
        $this->router->get('/track/click/{token}', 'EmailPlatform\Controllers\TrackingController@click');
        $this->router->get('/unsubscribe/{token}', 'EmailPlatform\Controllers\SubscriberController@unsubscribe');

        // SMS routes
        $this->router->post('/api/sms/send', 'EmailPlatform\Controllers\SMSController@send');
        $this->router->get('/api/sms/carriers', 'EmailPlatform\Controllers\SMSController@carriers');
    }

    /**
     * Handle incoming HTTP request
     */
    public function handleRequest(): void
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            
            $this->logger->info("Handling request: {$method} {$uri}");
            
            $response = $this->router->dispatch($method, $uri);
            
            if (is_array($response) || is_object($response)) {
                header('Content-Type: application/json');
                echo json_encode($response);
            } else {
                echo $response;
            }
            
        } catch (Exception $e) {
            $this->logger->error("Request handling error: " . $e->getMessage());
            
            if ($this->config['app']['debug']) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            } else {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Internal Server Error']);
            }
        }
    }

    /**
     * Get container instance
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get logger instance
     */
    public function getLogger(): Logger
    {
        return $this->logger;
    }

    /**
     * Get configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}