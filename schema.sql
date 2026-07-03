-- ============================================================
-- DONATE NOW TANZANIA — PostgreSQL Database Schema v1.0
-- Database : donatenow_tz
-- Run in pgAdmin 4 Query Tool after creating the database
-- ============================================================

CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- ── TABLE 1: users ────────────────────────────────────────────
CREATE TABLE users (
    id            SERIAL PRIMARY KEY,
    uuid          UUID DEFAULT gen_random_uuid() UNIQUE NOT NULL,
    first_name    VARCHAR(80)  NOT NULL,
    last_name     VARCHAR(80)  NOT NULL,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255),                    -- NULL for Google OAuth
    phone         VARCHAR(25),
    region        VARCHAR(100),
    role          VARCHAR(20)  NOT NULL DEFAULT 'donor'
                  CHECK (role IN ('donor','ngo','admin')),
    google_id     VARCHAR(255) UNIQUE,
    avatar_url    TEXT,
    is_verified   BOOLEAN NOT NULL DEFAULT FALSE,
    verify_token  VARCHAR(255),
    reset_token   VARCHAR(255),
    reset_expires TIMESTAMP,
    is_active     BOOLEAN NOT NULL DEFAULT TRUE,
    last_login    TIMESTAMP,
    created_at    TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_users_email    ON users(email);
CREATE INDEX idx_users_google   ON users(google_id);
CREATE INDEX idx_users_role     ON users(role);
CREATE INDEX idx_users_active   ON users(is_active);

-- ── TABLE 2: campaigns ────────────────────────────────────────
CREATE TABLE campaigns (
    id              SERIAL PRIMARY KEY,
    uuid            UUID DEFAULT gen_random_uuid() UNIQUE NOT NULL,
    created_by      INT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    title           VARCHAR(255) NOT NULL,
    slug            VARCHAR(255) UNIQUE,
    description     TEXT NOT NULL,
    category        VARCHAR(50)  NOT NULL
                    CHECK (category IN ('education','health','water','food','emergency','other')),
    region          VARCHAR(100),
    goal_amount     BIGINT NOT NULL CHECK (goal_amount > 0),   -- TZS, integer only
    raised_amount   BIGINT NOT NULL DEFAULT 0,
    donor_count     INT    NOT NULL DEFAULT 0,
    cover_image_url TEXT,
    status          VARCHAR(20)  NOT NULL DEFAULT 'pending'
                    CHECK (status IN ('pending','active','completed','suspended','rejected')),
    is_urgent       BOOLEAN NOT NULL DEFAULT FALSE,
    is_featured     BOOLEAN NOT NULL DEFAULT FALSE,
    is_verified     BOOLEAN NOT NULL DEFAULT FALSE,
    start_date      DATE,
    end_date        DATE,
    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_campaigns_status   ON campaigns(status);
CREATE INDEX idx_campaigns_category ON campaigns(category);
CREATE INDEX idx_campaigns_creator  ON campaigns(created_by);
CREATE INDEX idx_campaigns_featured ON campaigns(is_featured);

-- ── TABLE 3: donations ───────────────────────────────────────
CREATE TABLE donations (
    id              SERIAL PRIMARY KEY,
    uuid            UUID DEFAULT gen_random_uuid() UNIQUE NOT NULL,
    donor_id        INT REFERENCES users(id) ON DELETE SET NULL,
    campaign_id     INT NOT NULL REFERENCES campaigns(id) ON DELETE RESTRICT,
    amount          BIGINT NOT NULL CHECK (amount >= 1000),    -- min TZS 1,000
    currency        VARCHAR(5)   NOT NULL DEFAULT 'TZS',
    payment_method  VARCHAR(30)  NOT NULL
                    CHECK (payment_method IN ('mpesa','airtel','tigo','halo','crdb','nmb','card','other')),
    phone_used      VARCHAR(25),
    gateway_ref     VARCHAR(255) UNIQUE,                       -- unique reference sent to gateway
    transaction_id  VARCHAR(255),                              -- returned by gateway on success
    status          VARCHAR(20)  NOT NULL DEFAULT 'pending'
                    CHECK (status IN ('pending','processing','confirmed','failed','refunded')),
    is_anonymous    BOOLEAN NOT NULL DEFAULT FALSE,
    receipt_sent    BOOLEAN NOT NULL DEFAULT FALSE,
    sms_sent        BOOLEAN NOT NULL DEFAULT FALSE,
    notes           TEXT,
    donated_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    confirmed_at    TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_donations_donor    ON donations(donor_id);
CREATE INDEX idx_donations_campaign ON donations(campaign_id);
CREATE INDEX idx_donations_status   ON donations(status);
CREATE INDEX idx_donations_method   ON donations(payment_method);
CREATE INDEX idx_donations_ref      ON donations(gateway_ref);
CREATE INDEX idx_donations_date     ON donations(donated_at);

-- ── TABLE 4: payment_transactions ────────────────────────────
CREATE TABLE payment_transactions (
    id               SERIAL PRIMARY KEY,
    donation_id      INT NOT NULL REFERENCES donations(id) ON DELETE CASCADE,
    provider         VARCHAR(30) NOT NULL,
    provider_ref     VARCHAR(255),
    checkout_req_id  VARCHAR(255),
    request_payload  JSONB,
    response_payload JSONB,
    callback_payload JSONB,
    http_status      INT,
    result_code      VARCHAR(20),
    result_desc      TEXT,
    created_at       TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_ptx_donation ON payment_transactions(donation_id);
CREATE INDEX idx_ptx_ref      ON payment_transactions(provider_ref);

-- ── TABLE 5: notifications ───────────────────────────────────
CREATE TABLE notifications (
    id          SERIAL PRIMARY KEY,
    user_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type        VARCHAR(30) NOT NULL
                CHECK (type IN ('donation','campaign','system','alert','achievement')),
    title       VARCHAR(255) NOT NULL,
    body        TEXT,
    icon        VARCHAR(10),
    action_url  TEXT,
    is_read     BOOLEAN NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_notif_user   ON notifications(user_id);
CREATE INDEX idx_notif_unread ON notifications(user_id, is_read);

-- ── TABLE 6: campaign_updates ────────────────────────────────
CREATE TABLE campaign_updates (
    id          SERIAL PRIMARY KEY,
    campaign_id INT NOT NULL REFERENCES campaigns(id) ON DELETE CASCADE,
    author_id   INT NOT NULL REFERENCES users(id)    ON DELETE RESTRICT,
    title       VARCHAR(255),
    body        TEXT NOT NULL,
    images      TEXT[],
    created_at  TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_updates_campaign ON campaign_updates(campaign_id);

-- ── TABLE 7: audit_log ───────────────────────────────────────
CREATE TABLE audit_log (
    id          SERIAL PRIMARY KEY,
    user_id     INT REFERENCES users(id) ON DELETE SET NULL,
    action      VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id   INT,
    old_values  JSONB,
    new_values  JSONB,
    ip_address  INET,
    user_agent  TEXT,
    created_at  TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_audit_user   ON audit_log(user_id);
CREATE INDEX idx_audit_action ON audit_log(action);
CREATE INDEX idx_audit_date   ON audit_log(created_at);

-- ── TABLE 8: sessions ────────────────────────────────────────
CREATE TABLE sessions (
    id          VARCHAR(128) PRIMARY KEY,
    user_id     INT REFERENCES users(id) ON DELETE CASCADE,
    ip_address  INET,
    user_agent  TEXT,
    payload     TEXT,
    last_active TIMESTAMP NOT NULL DEFAULT NOW(),
    created_at  TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_sessions_user   ON sessions(user_id);
CREATE INDEX idx_sessions_active ON sessions(last_active);

-- ── AUTO-UPDATE updated_at TRIGGER ───────────────────────────
CREATE OR REPLACE FUNCTION trg_set_updated_at()
RETURNS TRIGGER AS $$
BEGIN NEW.updated_at = NOW(); RETURN NEW; END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_users_upd       BEFORE UPDATE ON users        FOR EACH ROW EXECUTE FUNCTION trg_set_updated_at();
CREATE TRIGGER trg_campaigns_upd   BEFORE UPDATE ON campaigns     FOR EACH ROW EXECUTE FUNCTION trg_set_updated_at();
CREATE TRIGGER trg_donations_upd   BEFORE UPDATE ON donations      FOR EACH ROW EXECUTE FUNCTION trg_set_updated_at();
CREATE TRIGGER trg_ptx_upd         BEFORE UPDATE ON payment_transactions FOR EACH ROW EXECUTE FUNCTION trg_set_updated_at();

-- ── SEED: default admin ───────────────────────────────────────
-- Password below is bcrypt of "admin@123" — CHANGE BEFORE PRODUCTION
INSERT INTO users (first_name, last_name, email, password_hash, role, is_verified, is_active)
VALUES ('Mkinga', 'Israel', 'mkingaisrael@gmail.com',
        '$2y$12$/83rZO/Nm1xBHJCy4jScIeECem.R3vVbxrX7rKKED7NkfBxdK0w1.', 'admin', TRUE, TRUE)
ON CONFLICT (email) DO UPDATE SET
    first_name=EXCLUDED.first_name,
    last_name=EXCLUDED.last_name,
    password_hash=EXCLUDED.password_hash,
    role='admin',
    is_verified=TRUE,
    is_active=TRUE;

-- ── SEED: sample campaign ─────────────────────────────────────
INSERT INTO campaigns (created_by, title, slug, description, category, region,
                       goal_amount, raised_amount, donor_count, status, is_verified, is_featured)
VALUES (1, 'Dodoma Primary School Classrooms', 'dodoma-primary-classrooms',
        'Help build 3 new classrooms for 120 students currently studying under trees.',
        'education', 'Dodoma', 50000000, 36000000, 842, 'active', TRUE, TRUE);

-- ── COMMENTS ─────────────────────────────────────────────────
COMMENT ON TABLE users                IS 'Platform accounts: donors, NGOs, admins';
COMMENT ON TABLE campaigns            IS 'Fundraising campaigns with full lifecycle';
COMMENT ON TABLE donations            IS 'Individual donation transaction records';
COMMENT ON TABLE payment_transactions IS 'Raw gateway API request/response audit log';
COMMENT ON TABLE notifications        IS 'In-app notifications per user';
COMMENT ON TABLE campaign_updates     IS 'NGO progress updates on campaigns';
COMMENT ON TABLE audit_log            IS 'Admin action trail for compliance';
COMMENT ON TABLE sessions             IS 'PHP session persistence in PostgreSQL';
