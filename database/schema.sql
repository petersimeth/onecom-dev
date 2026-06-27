CREATE TABLE stores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    domain VARCHAR(255) NOT NULL UNIQUE,
    category VARCHAR(120) NOT NULL,
    shopify_plan VARCHAR(80) DEFAULT 'Shopify',
    growth_signal ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
    growth_percent DECIMAL(6,2) DEFAULT 0,
    estimated_monthly_revenue DECIMAL(15,2) DEFAULT 0,
    monthly_traffic BIGINT UNSIGNED DEFAULT 0,
    monthly_orders BIGINT UNSIGNED DEFAULT 0,
    conversion_rate DECIMAL(5,2) DEFAULT 0,
    average_price DECIMAL(10,2) DEFAULT 0,
    product_count INT UNSIGNED DEFAULT 0,
    founded_year SMALLINT UNSIGNED NULL,
    headquarters VARCHAR(180) NULL,
    country VARCHAR(100) NULL,
    employee_range VARCHAR(60) NULL,
    public_email VARCHAR(255) NULL,
    public_phone VARCHAR(80) NULL,
    store_language VARCHAR(60) DEFAULT 'English',
    currency VARCHAR(30) DEFAULT 'USD',
    social_total BIGINT UNSIGNED DEFAULT 0,
    instagram_followers BIGINT UNSIGNED DEFAULT 0,
    tiktok_followers BIGINT UNSIGNED DEFAULT 0,
    facebook_followers BIGINT UNSIGNED DEFAULT 0,
    logo_letter VARCHAR(3) NULL,
    logo_class VARCHAR(80) NULL,
    crawler_description TEXT NULL,
    crawler_confidence SMALLINT UNSIGNED DEFAULT 0,
    crawler_signals_json TEXT NULL,
    myshopify_domain VARCHAR(255) NULL,
    crawler_source_url VARCHAR(500) NULL,
    last_crawled_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_store_category (category),
    INDEX idx_store_country (country),
    INDEX idx_store_revenue (estimated_monthly_revenue),
    INDEX idx_store_growth (growth_percent),
    INDEX idx_store_last_crawled (last_crawled_at)
);

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    plan VARCHAR(40) DEFAULT 'free',
    status ENUM('active', 'disabled') DEFAULT 'active',
    email_verified_at DATETIME NULL,
    verification_token_hash VARCHAR(255) NULL,
    verification_sent_at DATETIME NULL,
    last_login_at DATETIME NULL,
    stripe_customer_id VARCHAR(255) NULL,
    stripe_subscription_id VARCHAR(255) NULL,
    subscription_status VARCHAR(50) NULL,
    subscription_current_period_end DATETIME NULL,
    subscription_cancel_at_period_end TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_role (role),
    INDEX idx_user_status (status),
    INDEX idx_user_stripe_customer (stripe_customer_id),
    INDEX idx_user_stripe_subscription (stripe_subscription_id)
);

CREATE TABLE stripe_webhook_events (
    id VARCHAR(255) PRIMARY KEY,
    event_type VARCHAR(120) NOT NULL,
    processed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stripe_event_processed (processed_at),
    INDEX idx_stripe_event_type (event_type)
);

CREATE TABLE crawler_ingest_nonces (
    key_id VARCHAR(120) NOT NULL,
    nonce VARCHAR(100) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (key_id, nonce),
    INDEX idx_ingest_nonce_expiry (expires_at)
);

CREATE TABLE crawler_ingest_batches (
    batch_id VARCHAR(100) PRIMARY KEY,
    request_sha256 CHAR(64) NOT NULL,
    source_name VARCHAR(120) NOT NULL,
    remote_ip VARCHAR(45) NULL,
    status ENUM('processing', 'completed', 'failed') DEFAULT 'processing',
    store_count INT UNSIGNED DEFAULT 0,
    stores_created INT UNSIGNED DEFAULT 0,
    stores_updated INT UNSIGNED DEFAULT 0,
    technologies_upserted INT UNSIGNED DEFAULT 0,
    products_upserted INT UNSIGNED DEFAULT 0,
    signals_upserted INT UNSIGNED DEFAULT 0,
    error_message VARCHAR(500) NULL,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    INDEX idx_ingest_batch_received (received_at),
    INDEX idx_ingest_batch_status (status)
);

CREATE TABLE pending_registrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    verification_token_hash VARCHAR(255) NOT NULL,
    verification_sent_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pending_registration_token (verification_token_hash),
    INDEX idx_pending_registration_email (email)
);

CREATE TABLE pro_access_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    message TEXT NULL,
    decided_by BIGINT UNSIGNED NULL,
    decided_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pro_request_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pro_request_status (status),
    INDEX idx_pro_request_created (created_at)
);

CREATE TABLE store_technologies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id BIGINT UNSIGNED NOT NULL,
    technology_name VARCHAR(140) NOT NULL,
    category VARCHAR(120) NOT NULL,
    short_code VARCHAR(10) NULL,
    detected_at DATE NULL,
    last_seen_at DATE NULL,
    monthly_cost DECIMAL(10,2) DEFAULT 0,
    source_key CHAR(64) NULL,
    ingest_source VARCHAR(80) NULL,
    CONSTRAINT fk_technology_store
        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    INDEX idx_technology_name (technology_name),
    INDEX idx_technology_store (store_id),
    UNIQUE INDEX uq_store_technologies_source (store_id, source_key)
);

CREATE TABLE products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(120) NULL,
    price DECIMAL(10,2) DEFAULT 0,
    currency_symbol VARCHAR(4) DEFAULT '$',
    product_url VARCHAR(500) NULL,
    is_top_product BOOLEAN DEFAULT FALSE,
    first_seen_at DATE NULL,
    last_seen_at DATE NULL,
    source_key CHAR(64) NULL,
    ingest_source VARCHAR(80) NULL,
    CONSTRAINT fk_product_store
        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    INDEX idx_product_store (store_id),
    INDEX idx_product_category (category),
    UNIQUE INDEX uq_products_source (store_id, source_key)
);

CREATE TABLE store_signals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id BIGINT UNSIGNED NOT NULL,
    signal_type VARCHAR(100) NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    occurred_at DATETIME NOT NULL,
    occurred_label VARCHAR(80) NOT NULL,
    source_key CHAR(64) NULL,
    ingest_source VARCHAR(80) NULL,
    CONSTRAINT fk_signal_store
        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    INDEX idx_signal_store_date (store_id, occurred_at),
    INDEX idx_signal_type (signal_type),
    UNIQUE INDEX uq_store_signals_source (store_id, source_key)
);

CREATE TABLE saved_lists (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE saved_list_stores (
    list_id BIGINT UNSIGNED NOT NULL,
    store_id BIGINT UNSIGNED NOT NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (list_id, store_id),
    CONSTRAINT fk_saved_list_store_list
        FOREIGN KEY (list_id) REFERENCES saved_lists(id) ON DELETE CASCADE,
    CONSTRAINT fk_saved_list_store_store
        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    INDEX idx_saved_store_created (created_at),
    INDEX idx_saved_store_store (store_id)
);

CREATE TABLE import_batches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    import_type VARCHAR(40) NOT NULL,
    filename VARCHAR(255) NULL,
    imported_by VARCHAR(160) NULL,
    processed_count INT UNSIGNED DEFAULT 0,
    created_count INT UNSIGNED DEFAULT 0,
    updated_count INT UNSIGNED DEFAULT 0,
    skipped_count INT UNSIGNED DEFAULT 0,
    errors_json TEXT NULL,
    rolled_back_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_import_batch_created (created_at),
    INDEX idx_import_batch_type (import_type)
);

CREATE TABLE import_batch_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id BIGINT UNSIGNED NOT NULL,
    table_name VARCHAR(80) NOT NULL,
    row_id BIGINT UNSIGNED NOT NULL,
    row_label VARCHAR(255) NULL,
    action_type VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_import_item_batch
        FOREIGN KEY (batch_id) REFERENCES import_batches(id) ON DELETE CASCADE,
    INDEX idx_import_item_batch (batch_id),
    INDEX idx_import_item_row (table_name, row_id)
);
