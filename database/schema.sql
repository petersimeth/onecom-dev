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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_store_category (category),
    INDEX idx_store_country (country),
    INDEX idx_store_revenue (estimated_monthly_revenue),
    INDEX idx_store_growth (growth_percent)
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
    CONSTRAINT fk_technology_store
        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    INDEX idx_technology_name (technology_name),
    INDEX idx_technology_store (store_id)
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
    CONSTRAINT fk_product_store
        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    INDEX idx_product_store (store_id),
    INDEX idx_product_category (category)
);

CREATE TABLE store_signals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id BIGINT UNSIGNED NOT NULL,
    signal_type VARCHAR(100) NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    occurred_at DATETIME NOT NULL,
    occurred_label VARCHAR(80) NOT NULL,
    CONSTRAINT fk_signal_store
        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    INDEX idx_signal_store_date (store_id, occurred_at),
    INDEX idx_signal_type (signal_type)
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
