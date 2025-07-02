-- Campaigns table for email campaign management
CREATE TABLE IF NOT EXISTS campaigns (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    user_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(500) NOT NULL,
    from_name VARCHAR(255),
    from_email VARCHAR(255),
    reply_to VARCHAR(255),
    html_content TEXT,
    text_content TEXT,
    template_id INTEGER,
    status VARCHAR(20) DEFAULT 'draft', -- draft, scheduled, sending, sent, paused, cancelled
    type VARCHAR(50) DEFAULT 'regular', -- regular, ab_test, automation, sequence
    send_at DATETIME,
    timezone VARCHAR(50) DEFAULT 'UTC',
    
    -- Campaign settings
    track_opens BOOLEAN DEFAULT 1,
    track_clicks BOOLEAN DEFAULT 1,
    google_analytics BOOLEAN DEFAULT 0,
    
    -- Sending configuration
    batch_size INTEGER DEFAULT 100,
    send_delay INTEGER DEFAULT 1, -- seconds between sends
    smtp_server VARCHAR(255),
    
    -- A/B Testing
    ab_test_type VARCHAR(50), -- subject, content, send_time, from_name
    ab_test_percentage INTEGER DEFAULT 50,
    ab_test_winner_criteria VARCHAR(50), -- open_rate, click_rate, conversion_rate
    ab_test_duration_hours INTEGER DEFAULT 24,
    
    -- Personalization and dynamic content
    personalization_enabled BOOLEAN DEFAULT 1,
    spintax_enabled BOOLEAN DEFAULT 0,
    dynamic_content TEXT, -- JSON for dynamic content rules
    
    -- Targeting and segmentation
    segment_criteria TEXT, -- JSON for segment criteria
    tags TEXT, -- JSON array of tags
    
    -- Performance metrics (calculated fields)
    total_recipients INTEGER DEFAULT 0,
    emails_sent INTEGER DEFAULT 0,
    emails_delivered INTEGER DEFAULT 0,
    emails_bounced INTEGER DEFAULT 0,
    emails_opened INTEGER DEFAULT 0,
    unique_opens INTEGER DEFAULT 0,
    clicks INTEGER DEFAULT 0,
    unique_clicks INTEGER DEFAULT 0,
    unsubscribes INTEGER DEFAULT 0,
    spam_complaints INTEGER DEFAULT 0,
    
    -- Calculated rates
    delivery_rate DECIMAL(5,2) DEFAULT 0,
    open_rate DECIMAL(5,2) DEFAULT 0,
    click_rate DECIMAL(5,2) DEFAULT 0,
    click_to_open_rate DECIMAL(5,2) DEFAULT 0,
    unsubscribe_rate DECIMAL(5,2) DEFAULT 0,
    spam_rate DECIMAL(5,2) DEFAULT 0,
    
    -- Revenue tracking
    revenue DECIMAL(10,2) DEFAULT 0,
    conversions INTEGER DEFAULT 0,
    conversion_rate DECIMAL(5,2) DEFAULT 0,
    
    -- Metadata
    notes TEXT,
    archived BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME,
    completed_at DATETIME,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE SET NULL
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_campaigns_user_id ON campaigns(user_id);
CREATE INDEX IF NOT EXISTS idx_campaigns_uuid ON campaigns(uuid);
CREATE INDEX IF NOT EXISTS idx_campaigns_status ON campaigns(status);
CREATE INDEX IF NOT EXISTS idx_campaigns_type ON campaigns(type);
CREATE INDEX IF NOT EXISTS idx_campaigns_send_at ON campaigns(send_at);
CREATE INDEX IF NOT EXISTS idx_campaigns_created_at ON campaigns(created_at);
CREATE INDEX IF NOT EXISTS idx_campaigns_archived ON campaigns(archived);

-- Create trigger to update updated_at timestamp
CREATE TRIGGER IF NOT EXISTS update_campaigns_timestamp 
    AFTER UPDATE ON campaigns
    FOR EACH ROW
BEGIN
    UPDATE campaigns SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;