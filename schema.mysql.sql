CREATE DATABASE IF NOT EXISTS donatenow_tz
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE donatenow_tz;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE DEFAULT (UUID()),
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255),
    phone VARCHAR(25),
    region VARCHAR(100),
    role ENUM('donor','ngo','admin') NOT NULL DEFAULT 'donor',
    google_id VARCHAR(255) UNIQUE,
    avatar_url TEXT,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    verify_token VARCHAR(255),
    reset_token VARCHAR(255),
    reset_expires TIMESTAMP NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_email (email),
    INDEX idx_users_google (google_id),
    INDEX idx_users_role (role),
    INDEX idx_users_active (is_active)
);

CREATE TABLE IF NOT EXISTS campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE DEFAULT (UUID()),
    created_by INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    description TEXT NOT NULL,
    category ENUM('education','health','water','food','emergency','other') NOT NULL,
    region VARCHAR(100),
    goal_amount BIGINT NOT NULL,
    raised_amount BIGINT NOT NULL DEFAULT 0,
    donor_count INT NOT NULL DEFAULT 0,
    cover_image_url TEXT,
    status ENUM('pending','active','completed','suspended','rejected') NOT NULL DEFAULT 'pending',
    is_urgent TINYINT(1) NOT NULL DEFAULT 0,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    start_date DATE,
    end_date DATE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_campaigns_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_campaigns_status (status),
    INDEX idx_campaigns_category (category),
    INDEX idx_campaigns_creator (created_by),
    INDEX idx_campaigns_featured (is_featured)
);

CREATE TABLE IF NOT EXISTS donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE DEFAULT (UUID()),
    donor_id INT NULL,
    campaign_id INT NOT NULL,
    amount BIGINT NOT NULL,
    currency VARCHAR(5) NOT NULL DEFAULT 'TZS',
    payment_method ENUM('mpesa','airtel','tigo','halo','crdb','nmb','card','other') NOT NULL,
    phone_used VARCHAR(25),
    gateway_ref VARCHAR(255) UNIQUE,
    transaction_id VARCHAR(255),
    status ENUM('pending','processing','confirmed','failed','refunded') NOT NULL DEFAULT 'pending',
    is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
    receipt_sent TINYINT(1) NOT NULL DEFAULT 0,
    sms_sent TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT,
    donated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_donations_donor FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_donations_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    INDEX idx_donations_donor (donor_id),
    INDEX idx_donations_campaign (campaign_id),
    INDEX idx_donations_status (status),
    INDEX idx_donations_method (payment_method),
    INDEX idx_donations_ref (gateway_ref),
    INDEX idx_donations_date (donated_at)
);

CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donation_id INT NOT NULL,
    provider VARCHAR(30) NOT NULL,
    provider_ref VARCHAR(255),
    checkout_req_id VARCHAR(255),
    request_payload JSON,
    response_payload JSON,
    callback_payload JSON,
    http_status INT,
    result_code VARCHAR(20),
    result_desc TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ptx_donation FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE CASCADE,
    INDEX idx_ptx_donation (donation_id),
    INDEX idx_ptx_ref (provider_ref)
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('donation','campaign','system','alert','achievement') NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT,
    icon VARCHAR(20),
    action_url TEXT,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user (user_id),
    INDEX idx_notif_unread (user_id, is_read)
);

CREATE TABLE IF NOT EXISTS campaign_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    author_id INT NOT NULL,
    title VARCHAR(255),
    body TEXT NOT NULL,
    images JSON,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_updates_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_updates_author FOREIGN KEY (author_id) REFERENCES users(id),
    INDEX idx_updates_campaign (campaign_id)
);

CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_date (created_at)
);

CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_active TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sessions_user (user_id),
    INDEX idx_sessions_active (last_active)
);

INSERT INTO users (first_name, last_name, email, password_hash, role, is_verified, is_active)
VALUES ('Mkinga', 'Israel', 'mkingaisrael@gmail.com',
        '$2y$12$/83rZO/Nm1xBHJCy4jScIeECem.R3vVbxrX7rKKED7NkfBxdK0w1.', 'admin', 1, 1)
ON DUPLICATE KEY UPDATE
    first_name=VALUES(first_name),
    last_name=VALUES(last_name),
    password_hash=VALUES(password_hash),
    role='admin',
    is_verified=1,
    is_active=1;

INSERT INTO campaigns (created_by, title, slug, description, category, region,
                       goal_amount, raised_amount, donor_count, cover_image_url, status, is_verified, is_featured)
SELECT 1, 'Dodoma Primary School Classrooms', 'dodoma-primary-classrooms',
       'Help build 3 new classrooms for 120 students currently studying under trees.',
       'education', 'Dodoma', 50000000, 36000000, 842, 'assets/campaigns/dodoma-primary-school.png', 'active', 1, 1
WHERE NOT EXISTS (
    SELECT 1 FROM campaigns WHERE slug='dodoma-primary-classrooms'
);

UPDATE campaigns
SET cover_image_url='assets/campaigns/dodoma-primary-school.png'
WHERE slug='dodoma-primary-classrooms' AND (cover_image_url IS NULL OR cover_image_url='');

INSERT INTO campaigns (created_by, title, slug, description, category, region,
                       goal_amount, raised_amount, donor_count, cover_image_url, status, is_verified, is_featured)
SELECT 1, 'Mwanza Clean Water Project', 'mwanza-clean-water',
       'Provide safe drinking water to families through boreholes and purification systems.',
       'water', 'Mwanza', 50000000, 22500000, 534, 'assets/campaigns/mwanza-clean-water.png', 'active', 1, 1
WHERE NOT EXISTS (
    SELECT 1 FROM campaigns WHERE slug='mwanza-clean-water'
);

INSERT INTO campaigns (created_by, title, slug, description, category, region,
                       goal_amount, raised_amount, donor_count, cover_image_url, status, is_verified, is_featured)
SELECT 1, 'Morogoro Flood Emergency Relief', 'morogoro-flood-emergency-relief',
       'Support families displaced by floods with food, shelter, and medical aid.',
       'emergency', 'Morogoro', 80000000, 8200000, 310, 'assets/campaigns/morogoro-floods.png', 'active', 1, 0
WHERE NOT EXISTS (
    SELECT 1 FROM campaigns WHERE slug='morogoro-flood-emergency-relief'
);

INSERT INTO campaigns (created_by, title, slug, description, category, region,
                       goal_amount, raised_amount, donor_count, cover_image_url, status, is_verified, is_featured)
SELECT 1, 'Tabora Food Security Program', 'tabora-food-security-program',
       'Support smallholder farmers to grow food and feed vulnerable families.',
       'food', 'Tabora', 30000000, 9300000, 289, 'assets/campaigns/tabora-food-security.png', 'active', 1, 0
WHERE NOT EXISTS (
    SELECT 1 FROM campaigns WHERE slug='tabora-food-security-program'
);

INSERT INTO campaigns (created_by, title, slug, description, category, region,
                       goal_amount, raised_amount, donor_count, cover_image_url, status, is_verified, is_featured)
SELECT 1, 'Zanzibar Maternal Health Support', 'zanzibar-maternal-health-support',
       'Improve maternal health outcomes through clinic supplies and mother support.',
       'health', 'Zanzibar', 25000000, 18000000, 421, 'assets/campaigns/zanzibar-maternal-health.png', 'active', 1, 0
WHERE NOT EXISTS (
    SELECT 1 FROM campaigns WHERE slug='zanzibar-maternal-health-support'
);
