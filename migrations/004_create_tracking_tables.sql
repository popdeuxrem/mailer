-- Email logs table to track individual email sends
CREATE TABLE IF NOT EXISTS email_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    campaign_id INTEGER NOT NULL,
    subscriber_id INTEGER NOT NULL,
    tracking_token VARCHAR(255) NOT NULL UNIQUE,
    subject VARCHAR(500),
    from_email VARCHAR(255),
    from_name VARCHAR(255),
    to_email VARCHAR(255),
    to_name VARCHAR(255),
    html_content TEXT,
    text_content TEXT,
    status VARCHAR(20) DEFAULT 'pending', -- pending, sent, delivered, bounced, failed
    smtp_server VARCHAR(255),
    message_id VARCHAR(255),
    
    -- Sending details
    sent_at DATETIME,
    delivered_at DATETIME,
    bounce_reason TEXT,
    error_message TEXT,
    retry_count INTEGER DEFAULT 0,
    
    -- Tracking flags
    track_opens BOOLEAN DEFAULT 1,
    track_clicks BOOLEAN DEFAULT 1,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
);

-- Email opens tracking table
CREATE TABLE IF NOT EXISTS email_opens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email_log_id INTEGER NOT NULL,
    campaign_id INTEGER NOT NULL,
    subscriber_id INTEGER NOT NULL,
    tracking_token VARCHAR(255) NOT NULL,
    
    -- Request details
    ip_address VARCHAR(45),
    user_agent TEXT,
    referer VARCHAR(500),
    
    -- Device and browser information
    device_type VARCHAR(20), -- mobile, desktop, tablet
    device_os VARCHAR(50),
    device_brand VARCHAR(50),
    device_model VARCHAR(100),
    browser_name VARCHAR(50),
    browser_version VARCHAR(20),
    browser_engine VARCHAR(50),
    
    -- Geographic information
    country VARCHAR(100),
    country_code VARCHAR(3),
    region VARCHAR(100),
    city VARCHAR(100),
    timezone VARCHAR(50),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    isp VARCHAR(255),
    
    -- Analytics flags
    is_unique BOOLEAN DEFAULT 0, -- First open for this email
    is_mobile BOOLEAN DEFAULT 0,
    
    -- Timing information
    opened_at DATETIME NOT NULL,
    time_to_open INTEGER, -- Seconds from send to open
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (email_log_id) REFERENCES email_logs(id) ON DELETE CASCADE,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
);

-- Email clicks tracking table
CREATE TABLE IF NOT EXISTS email_clicks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email_log_id INTEGER NOT NULL,
    campaign_id INTEGER NOT NULL,
    subscriber_id INTEGER NOT NULL,
    tracking_token VARCHAR(255) NOT NULL,
    
    -- Link details
    link_id VARCHAR(255),
    link_url TEXT NOT NULL,
    link_text VARCHAR(500),
    link_position INTEGER, -- Position in email (1st link, 2nd link, etc.)
    
    -- Request details
    ip_address VARCHAR(45),
    user_agent TEXT,
    referer VARCHAR(500),
    
    -- Device and browser information
    device_type VARCHAR(20),
    device_os VARCHAR(50),
    browser_name VARCHAR(50),
    browser_version VARCHAR(20),
    
    -- Geographic information
    country VARCHAR(100),
    country_code VARCHAR(3),
    region VARCHAR(100),
    city VARCHAR(100),
    timezone VARCHAR(50),
    
    -- Analytics flags
    is_unique BOOLEAN DEFAULT 0, -- First click for this link/email
    is_mobile BOOLEAN DEFAULT 0,
    
    -- Timing information
    clicked_at DATETIME NOT NULL,
    time_to_click INTEGER, -- Seconds from open to click
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (email_log_id) REFERENCES email_logs(id) ON DELETE CASCADE,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
);

-- Email conversions tracking table
CREATE TABLE IF NOT EXISTS email_conversions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email_log_id INTEGER,
    campaign_id INTEGER NOT NULL,
    subscriber_id INTEGER NOT NULL,
    
    -- Conversion details
    conversion_type VARCHAR(50), -- purchase, signup, download, contact, etc.
    conversion_url TEXT,
    conversion_value DECIMAL(10, 2), -- Revenue or value generated
    conversion_currency VARCHAR(3) DEFAULT 'USD',
    
    -- Attribution
    attribution_model VARCHAR(50) DEFAULT 'last_click', -- first_click, last_click, linear
    days_to_convert INTEGER,
    touchpoints INTEGER DEFAULT 1, -- Number of email interactions before conversion
    
    -- External tracking
    external_id VARCHAR(255), -- ID from external system (e.commerce, CRM)
    order_id VARCHAR(255),
    transaction_id VARCHAR(255),
    
    -- Timing
    converted_at DATETIME NOT NULL,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (email_log_id) REFERENCES email_logs(id) ON DELETE SET NULL,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
);

-- Email bounces tracking table
CREATE TABLE IF NOT EXISTS email_bounces (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email_log_id INTEGER NOT NULL,
    campaign_id INTEGER NOT NULL,
    subscriber_id INTEGER NOT NULL,
    
    -- Bounce details
    bounce_type VARCHAR(20), -- hard, soft, transient
    bounce_category VARCHAR(50), -- invalid_recipient, mailbox_full, content_rejection, etc.
    bounce_reason TEXT,
    bounce_code VARCHAR(10),
    remote_mta VARCHAR(255),
    
    -- SMTP response
    smtp_response TEXT,
    diagnostic_code TEXT,
    
    -- Processing
    processed BOOLEAN DEFAULT 0,
    action_taken VARCHAR(50), -- unsubscribed, suppressed, retry_scheduled
    
    bounced_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (email_log_id) REFERENCES email_logs(id) ON DELETE CASCADE,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
);

-- Email complaints/spam reports tracking table
CREATE TABLE IF NOT EXISTS email_complaints (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email_log_id INTEGER,
    campaign_id INTEGER NOT NULL,
    subscriber_id INTEGER NOT NULL,
    
    -- Complaint details
    complaint_type VARCHAR(50), -- spam, abuse, fraud, virus, etc.
    feedback_type VARCHAR(50),
    user_agent TEXT,
    
    -- Source information
    reporting_mta VARCHAR(255),
    source_ip VARCHAR(45),
    incident_date DATETIME,
    
    -- Processing
    processed BOOLEAN DEFAULT 0,
    action_taken VARCHAR(50), -- unsubscribed, suppressed, investigated
    
    complained_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (email_log_id) REFERENCES email_logs(id) ON DELETE SET NULL,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
);

-- Unsubscribes tracking table
CREATE TABLE IF NOT EXISTS email_unsubscribes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email_log_id INTEGER,
    campaign_id INTEGER,
    subscriber_id INTEGER NOT NULL,
    
    -- Unsubscribe details
    unsubscribe_method VARCHAR(50), -- link_click, reply, complaint, manual
    unsubscribe_reason VARCHAR(255),
    unsubscribe_category VARCHAR(50), -- too_frequent, not_relevant, never_signed_up, etc.
    
    -- Source tracking
    source_url TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    
    -- Feedback
    feedback TEXT,
    rating INTEGER, -- 1-5 satisfaction rating
    
    unsubscribed_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (email_log_id) REFERENCES email_logs(id) ON DELETE SET NULL,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
);

-- Link mappings for click tracking
CREATE TABLE IF NOT EXISTS link_mappings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    link_id VARCHAR(255) NOT NULL UNIQUE,
    original_url TEXT NOT NULL,
    tracking_token VARCHAR(255) NOT NULL,
    campaign_id INTEGER,
    
    -- Link metadata
    link_text VARCHAR(500),
    link_position INTEGER,
    link_type VARCHAR(50), -- cta, footer, header, content
    
    -- A/B testing
    variant VARCHAR(50),
    test_group VARCHAR(50),
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
);

-- Create indexes for performance optimization
CREATE INDEX IF NOT EXISTS idx_email_logs_campaign ON email_logs(campaign_id);
CREATE INDEX IF NOT EXISTS idx_email_logs_subscriber ON email_logs(subscriber_id);
CREATE INDEX IF NOT EXISTS idx_email_logs_token ON email_logs(tracking_token);
CREATE INDEX IF NOT EXISTS idx_email_logs_status ON email_logs(status);
CREATE INDEX IF NOT EXISTS idx_email_logs_sent_at ON email_logs(sent_at);

CREATE INDEX IF NOT EXISTS idx_email_opens_campaign ON email_opens(campaign_id);
CREATE INDEX IF NOT EXISTS idx_email_opens_subscriber ON email_opens(subscriber_id);
CREATE INDEX IF NOT EXISTS idx_email_opens_token ON email_opens(tracking_token);
CREATE INDEX IF NOT EXISTS idx_email_opens_opened_at ON email_opens(opened_at);
CREATE INDEX IF NOT EXISTS idx_email_opens_country ON email_opens(country);
CREATE INDEX IF NOT EXISTS idx_email_opens_device ON email_opens(device_type);

CREATE INDEX IF NOT EXISTS idx_email_clicks_campaign ON email_clicks(campaign_id);
CREATE INDEX IF NOT EXISTS idx_email_clicks_subscriber ON email_clicks(subscriber_id);
CREATE INDEX IF NOT EXISTS idx_email_clicks_token ON email_clicks(tracking_token);
CREATE INDEX IF NOT EXISTS idx_email_clicks_clicked_at ON email_clicks(clicked_at);
CREATE INDEX IF NOT EXISTS idx_email_clicks_url ON email_clicks(link_url);

CREATE INDEX IF NOT EXISTS idx_email_conversions_campaign ON email_conversions(campaign_id);
CREATE INDEX IF NOT EXISTS idx_email_conversions_subscriber ON email_conversions(subscriber_id);
CREATE INDEX IF NOT EXISTS idx_email_conversions_type ON email_conversions(conversion_type);
CREATE INDEX IF NOT EXISTS idx_email_conversions_converted_at ON email_conversions(converted_at);

CREATE INDEX IF NOT EXISTS idx_email_bounces_campaign ON email_bounces(campaign_id);
CREATE INDEX IF NOT EXISTS idx_email_bounces_subscriber ON email_bounces(subscriber_id);
CREATE INDEX IF NOT EXISTS idx_email_bounces_type ON email_bounces(bounce_type);

CREATE INDEX IF NOT EXISTS idx_email_complaints_campaign ON email_complaints(campaign_id);
CREATE INDEX IF NOT EXISTS idx_email_complaints_subscriber ON email_complaints(subscriber_id);

CREATE INDEX IF NOT EXISTS idx_email_unsubscribes_subscriber ON email_unsubscribes(subscriber_id);
CREATE INDEX IF NOT EXISTS idx_email_unsubscribes_campaign ON email_unsubscribes(campaign_id);

CREATE INDEX IF NOT EXISTS idx_link_mappings_id ON link_mappings(link_id);
CREATE INDEX IF NOT EXISTS idx_link_mappings_token ON link_mappings(tracking_token);

-- Create triggers to update timestamps
CREATE TRIGGER IF NOT EXISTS update_email_logs_timestamp 
    AFTER UPDATE ON email_logs
    FOR EACH ROW
BEGIN
    UPDATE email_logs SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;