-- Subscribers table for contact management
CREATE TABLE IF NOT EXISTS subscribers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    user_id INTEGER NOT NULL,
    email VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(20),
    company VARCHAR(255),
    job_title VARCHAR(255),
    industry VARCHAR(100),
    website VARCHAR(255),
    
    -- Address information
    address_line1 VARCHAR(255),
    address_line2 VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100),
    timezone VARCHAR(50) DEFAULT 'UTC',
    
    -- Subscription status
    status VARCHAR(20) DEFAULT 'active', -- active, unsubscribed, bounced, complained, pending
    subscription_source VARCHAR(100), -- web_form, import, api, manual
    subscription_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    unsubscription_date DATETIME,
    unsubscribe_reason VARCHAR(255),
    
    -- Email preferences
    email_format VARCHAR(10) DEFAULT 'html', -- html, text, both
    subscription_confirmed BOOLEAN DEFAULT 0,
    confirmation_token VARCHAR(255),
    confirmation_date DATETIME,
    
    -- Engagement metrics
    total_emails_sent INTEGER DEFAULT 0,
    total_emails_opened INTEGER DEFAULT 0,
    total_clicks INTEGER DEFAULT 0,
    last_email_sent DATETIME,
    last_email_opened DATETIME,
    last_click_date DATETIME,
    
    -- Calculated engagement scores
    engagement_score INTEGER DEFAULT 0, -- 0-100 scale
    open_rate DECIMAL(5,2) DEFAULT 0,
    click_rate DECIMAL(5,2) DEFAULT 0,
    
    -- Behavioral data
    average_time_to_open INTEGER DEFAULT 0, -- seconds
    preferred_send_time VARCHAR(5), -- HH:MM format
    preferred_send_day VARCHAR(10), -- monday, tuesday, etc.
    device_preference VARCHAR(20), -- mobile, desktop, tablet
    browser_preference VARCHAR(50),
    
    -- Demographics and psychographics
    age_range VARCHAR(20),
    gender VARCHAR(20),
    income_range VARCHAR(50),
    education_level VARCHAR(50),
    interests TEXT, -- JSON array of interests
    
    -- Custom fields (flexible data storage)
    custom_fields TEXT, -- JSON object for custom data
    
    -- Tags and segmentation
    tags TEXT, -- JSON array of tags
    segments TEXT, -- JSON array of segment IDs
    
    -- Lead scoring
    lead_score INTEGER DEFAULT 0,
    lead_status VARCHAR(50), -- cold, warm, hot, qualified, customer
    lead_source VARCHAR(100),
    
    -- Purchase behavior
    total_purchases INTEGER DEFAULT 0,
    total_revenue DECIMAL(10,2) DEFAULT 0,
    average_order_value DECIMAL(10,2) DEFAULT 0,
    last_purchase_date DATETIME,
    customer_lifetime_value DECIMAL(10,2) DEFAULT 0,
    
    -- Communication preferences
    email_frequency VARCHAR(20) DEFAULT 'default', -- daily, weekly, monthly, default
    communication_preferences TEXT, -- JSON for various comm preferences
    
    -- Compliance and privacy
    gdpr_consent BOOLEAN DEFAULT 0,
    gdpr_consent_date DATETIME,
    data_processing_consent BOOLEAN DEFAULT 0,
    marketing_consent BOOLEAN DEFAULT 1,
    profiling_consent BOOLEAN DEFAULT 0,
    
    -- Social media
    facebook_id VARCHAR(100),
    twitter_handle VARCHAR(100),
    linkedin_url VARCHAR(255),
    instagram_handle VARCHAR(100),
    
    -- Notes and internal data
    notes TEXT,
    internal_notes TEXT,
    created_by INTEGER,
    
    -- Metadata
    ip_address VARCHAR(45), -- IPv6 compatible
    user_agent TEXT,
    referrer VARCHAR(500),
    utm_source VARCHAR(100),
    utm_medium VARCHAR(100),
    utm_campaign VARCHAR(100),
    utm_term VARCHAR(100),
    utm_content VARCHAR(100),
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_subscribers_user_id ON subscribers(user_id);
CREATE INDEX IF NOT EXISTS idx_subscribers_email ON subscribers(email);
CREATE INDEX IF NOT EXISTS idx_subscribers_uuid ON subscribers(uuid);
CREATE INDEX IF NOT EXISTS idx_subscribers_status ON subscribers(status);
CREATE INDEX IF NOT EXISTS idx_subscribers_engagement_score ON subscribers(engagement_score);
CREATE INDEX IF NOT EXISTS idx_subscribers_lead_score ON subscribers(lead_score);
CREATE INDEX IF NOT EXISTS idx_subscribers_subscription_date ON subscribers(subscription_date);
CREATE INDEX IF NOT EXISTS idx_subscribers_last_email_opened ON subscribers(last_email_opened);
CREATE INDEX IF NOT EXISTS idx_subscribers_country ON subscribers(country);

-- Composite indexes for common queries
CREATE INDEX IF NOT EXISTS idx_subscribers_user_status ON subscribers(user_id, status);
CREATE INDEX IF NOT EXISTS idx_subscribers_email_status ON subscribers(email, status);

-- Create trigger to update updated_at timestamp
CREATE TRIGGER IF NOT EXISTS update_subscribers_timestamp 
    AFTER UPDATE ON subscribers
    FOR EACH ROW
BEGIN
    UPDATE subscribers SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;