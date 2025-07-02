# Advanced Email Marketing Platform

A comprehensive, enterprise-grade email marketing platform built with PHP 8+ featuring campaign management, analytics, SMS integration, and a responsive dashboard.

## 🚀 Features

### Core Email Marketing
- **Campaign Management**: Create, schedule, and manage email campaigns
- **Template Editor**: WYSIWYG responsive HTML email templates
- **List Management**: Subscriber segmentation and list building
- **A/B Testing**: Subject line and content testing
- **Analytics**: Open rates, click tracking, geolocation data
- **Bounce Handling**: Automatic bounce and complaint processing

### Advanced Features
- **SMTP Rotation**: Multiple SMTP server management with failover
- **SMS Gateway**: Email-to-SMS integration for notifications
- **Dynamic Content**: Spintax and personalization support
- **Pixel Tracking**: Real-time engagement tracking
- **Multi-tenant**: User roles and campaign isolation
- **API Integration**: RESTful API for external integrations

### Security & Compliance
- **Authentication**: Secure user management with JWT
- **Rate Limiting**: Prevent abuse and ensure deliverability
- **CSRF Protection**: Cross-site request forgery prevention
- **Input Validation**: Comprehensive data sanitization
- **Encryption**: Secure log storage and sensitive data handling
- **Compliance**: GDPR, CAN-SPAM compliance features

## 📋 Requirements

- PHP 8.0+
- MySQL 5.7+ or SQLite 3.8+
- Composer
- Web server (Apache/Nginx)
- SSL certificate (recommended)

## 🛠️ Installation

### Quick Install

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd email-marketing-platform
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Run the installer**
   ```bash
   php installer/install.php
   ```

4. **Configure web server**
   - Point document root to `public/` directory
   - Enable URL rewriting

### Manual Installation

1. **Environment Setup**
   ```bash
   cp .env.example .env
   nano .env  # Configure your settings
   ```

2. **Database Setup**
   ```bash
   php console/migrate.php
   ```

3. **Create Admin User**
   ```bash
   php console/create-user.php --email=admin@example.com --password=secure123
   ```

## 🔧 Configuration

### Environment Variables

```env
# Database Configuration
DB_TYPE=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=email_platform
DB_USER=username
DB_PASS=password

# Mail Configuration
MAIL_DRIVER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-pass
MAIL_ENCRYPTION=tls

# Application Settings
APP_URL=https://your-domain.com
APP_KEY=your-secret-key
APP_DEBUG=false

# SMS Gateway
SMS_PROVIDER=twilio
SMS_API_KEY=your-sms-key
SMS_API_SECRET=your-sms-secret
```

### SMTP Configuration

The platform supports multiple SMTP providers with automatic rotation:

- **Gmail/Google Workspace**
- **SendGrid**
- **Mailgun**
- **Amazon SES**
- **Custom SMTP servers**

## 📖 Usage

### Dashboard Access

1. Navigate to your domain
2. Login with your credentials
3. Access the dashboard at `/dashboard`

### Creating Campaigns

1. **Campaign Setup**
   - Go to Campaigns → New Campaign
   - Configure sender details and subject
   - Select recipient lists

2. **Template Design**
   - Use the built-in editor
   - Choose responsive templates
   - Add dynamic content and personalization

3. **Testing & Launch**
   - Send test emails
   - Review analytics
   - Schedule or send immediately

### API Usage

```php
// Authentication
$token = authenticate('username', 'password');

// Create Campaign
$campaign = createCampaign([
    'name' => 'My Campaign',
    'subject' => 'Special Offer',
    'template' => 'responsive_template',
    'lists' => ['subscribers', 'vips']
]);

// Send Campaign
sendCampaign($campaign['id']);
```

## 🧪 Testing

### Unit Tests
```bash
composer test
```

### Code Analysis
```bash
composer analyse
```

### Test Email Delivery
```bash
php tests/test-delivery.php --email=test@example.com
```

## 📊 Analytics & Reporting

- **Campaign Performance**: Open rates, click rates, conversions
- **Subscriber Analytics**: Engagement patterns, demographics
- **Deliverability Reports**: Bounce rates, spam complaints
- **Revenue Tracking**: E-commerce integration metrics

## 🔐 Security Features

- **JWT Authentication**: Secure token-based authentication
- **Role-based Access**: Granular permission system
- **Rate Limiting**: API and email sending limits
- **Input Sanitization**: XSS and SQL injection prevention
- **Audit Logging**: Complete action history
- **Encryption**: Database and file encryption

## 🌐 Internationalization

- Multi-language support
- Timezone handling
- Currency formatting
- Regional compliance features

## 🔌 Integrations

### Supported Services
- **CRM**: Salesforce, HubSpot, Pipedrive
- **E-commerce**: WooCommerce, Shopify, Magento
- **Analytics**: Google Analytics, Mixpanel
- **Storage**: AWS S3, Google Cloud Storage

### Webhooks
- Real-time event notifications
- Custom endpoint configuration
- Retry mechanisms

## 📚 API Documentation

Complete API documentation available at `/docs/api` after installation.

### Key Endpoints

- `POST /api/auth/login` - User authentication
- `GET /api/campaigns` - List campaigns
- `POST /api/campaigns` - Create campaign
- `GET /api/analytics/{id}` - Campaign analytics
- `POST /api/subscribers` - Add subscribers

## 🚀 Performance

### Optimization Features
- **Database Indexing**: Optimized queries
- **Caching**: Redis/Memcached support
- **CDN Integration**: Asset delivery optimization
- **Background Jobs**: Queue-based email processing
- **Connection Pooling**: SMTP connection reuse

### Scalability
- Horizontal scaling support
- Load balancer compatibility
- Microservice architecture ready
- Container deployment (Docker)

## 🛡️ Compliance

### GDPR Compliance
- Data portability
- Right to be forgotten
- Consent management
- Privacy policy integration

### CAN-SPAM Compliance
- Unsubscribe mechanisms
- Sender identification
- Subject line accuracy
- Physical address inclusion

## 🔧 Troubleshooting

### Common Issues

1. **Email Delivery Problems**
   - Check SMTP credentials
   - Verify DNS records (SPF, DKIM, DMARC)
   - Review bounce logs

2. **Dashboard Access Issues**
   - Clear browser cache
   - Check file permissions
   - Verify database connection

3. **Performance Issues**
   - Enable caching
   - Optimize database queries
   - Check server resources

### Support Resources
- Documentation: `/docs`
- Log files: `/storage/logs`
- Debug mode: Set `APP_DEBUG=true`

## 🤝 Contributing

1. Fork the repository
2. Create feature branch
3. Commit changes
4. Push to branch
5. Create pull request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🙏 Acknowledgments

- PHPMailer for email delivery
- Twig for templating
- Bootstrap for UI components
- All open-source contributors

## 📞 Support

- Email: support@emailplatform.com
- Documentation: https://docs.emailplatform.com
- Community: https://community.emailplatform.com
