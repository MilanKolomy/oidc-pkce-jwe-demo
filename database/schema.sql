-- ---------------------------------------------------------------------------
-- oidc-pkce-jwe-demo — database schema
--
-- Target: MySQL 8.0.34
-- Reference: docs/04-datovy-model.md
--
-- Conventions:
--   * table prefix `oidc_`, singular names, snake_case
--   * primary key always `id`, BIGINT UNSIGNED AUTO_INCREMENT
--   * DATETIME in UTC, never TIMESTAMP (see docs/04-datovy-model.md, section 5)
--   * InnoDB, utf8mb4 / utf8mb4_0900_ai_ci
--
-- The script is idempotent for a fresh database. It does not migrate existing data.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET time_zone = '+00:00';


-- ---------------------------------------------------------------------------
-- oidc_user — application user, paired to an external identity provider account
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `oidc_user` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `google_sub`    VARCHAR(255)    NOT NULL
                    COMMENT 'Immutable subject identifier from the provider. Pairing key.',
    `email`         VARCHAR(320)    NOT NULL
                    COMMENT 'Informational only; deliberately NOT unique (see FR-02).',
    `display_name`  VARCHAR(255)        NULL,
    `created_at`    DATETIME        NOT NULL,
    `last_login_at` DATETIME            NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_google_sub` (`google_sub`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------------
-- oidc_certificate_authority — shared lookup table, has no owner
-- Rows are created automatically on first occurrence of an issuer.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `oidc_certificate_authority` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subject_dn`  VARCHAR(512)    NOT NULL
                  COMMENT 'Distinguished name of the authority. Pairing key.',
    `common_name` VARCHAR(255)    NOT NULL
                  COMMENT 'Extracted from subject_dn, for display.',
    `created_at`  DATETIME        NOT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_authority_subject_dn` (`subject_dn`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------------
-- oidc_key_usage — shared lookup table of X.509 key usage values
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `oidc_key_usage` (
    `id`    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`  VARCHAR(64)     NOT NULL COMMENT 'Technical code as defined by X.509.',
    `label` VARCHAR(128)    NOT NULL COMMENT 'Human-readable description.',

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_key_usage_code` (`code`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------------
-- oidc_certificate — owned data
--
-- serial_number is stored as hexadecimal text: an X.509 serial may be up to
-- 20 bytes and would overflow any integer type.
--
-- Uniqueness is per user, not global — the same public certificate may legitimately
-- be registered by several users.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `oidc_certificate` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`              BIGINT UNSIGNED NOT NULL,
    `authority_id`         BIGINT UNSIGNED NOT NULL,
    `subject_dn`           VARCHAR(512)    NOT NULL,
    `common_name`          VARCHAR(255)    NOT NULL,
    `serial_number`        VARCHAR(64)     NOT NULL
                           COMMENT 'Hexadecimal. Never store as an integer.',
    `valid_from`           DATETIME        NOT NULL,
    `valid_to`             DATETIME        NOT NULL,
    `fingerprint_sha256`   CHAR(64)        NOT NULL
                           COMMENT 'Lowercase hexadecimal SHA-256 of the DER form.',
    `signature_algorithm`  VARCHAR(64)         NULL,
    `public_key_algorithm` VARCHAR(32)         NULL,
    `public_key_bits`      INT UNSIGNED        NULL,
    `pem`                  TEXT            NOT NULL
                           COMMENT 'Original public part as supplied. Never a private key.',
    `created_at`           DATETIME        NOT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_certificate_owner_fingerprint` (`user_id`, `fingerprint_sha256`),

    -- Every query over owned data filters by owner, so user_id leads each index.
    KEY `ix_certificate_owner_created` (`user_id`, `created_at`),
    KEY `ix_certificate_owner_valid_to` (`user_id`, `valid_to`),
    KEY `ix_certificate_authority` (`authority_id`),

    CONSTRAINT `fk_certificate_user`
        FOREIGN KEY (`user_id`) REFERENCES `oidc_user` (`id`)
        ON DELETE CASCADE
        ON UPDATE RESTRICT,

    -- RESTRICT, not CASCADE: deleting a shared authority must never remove
    -- certificates belonging to several users.
    CONSTRAINT `fk_certificate_authority`
        FOREIGN KEY (`authority_id`) REFERENCES `oidc_certificate_authority` (`id`)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------------
-- oidc_certificate_key_usage — M:N link table
-- Carries no attributes of its own, so the pair of foreign keys is the primary key.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `oidc_certificate_key_usage` (
    `certificate_id` BIGINT UNSIGNED NOT NULL,
    `key_usage_id`   BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (`certificate_id`, `key_usage_id`),
    KEY `ix_cku_key_usage` (`key_usage_id`),

    CONSTRAINT `fk_cku_certificate`
        FOREIGN KEY (`certificate_id`) REFERENCES `oidc_certificate` (`id`)
        ON DELETE CASCADE
        ON UPDATE RESTRICT,

    CONSTRAINT `fk_cku_key_usage`
        FOREIGN KEY (`key_usage_id`) REFERENCES `oidc_key_usage` (`id`)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------------
-- oidc_certificate_check — validity check history
--
-- A separate entity because the outcome depends on when the check was run:
-- a certificate valid at registration may later be expired (FR-09).
--
-- `result` is plain text, not ENUM — extending an ENUM requires altering the table,
-- which is impractical on hosting without shell access. The value set lives in
-- the application.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `oidc_certificate_check` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `certificate_id` BIGINT UNSIGNED NOT NULL,
    `checked_at`     DATETIME        NOT NULL,
    `result`         VARCHAR(32)     NOT NULL
                     COMMENT 'valid | not_yet_valid | expired',
    `detail`         VARCHAR(255)        NULL,

    PRIMARY KEY (`id`),
    KEY `ix_check_certificate_time` (`certificate_id`, `checked_at`),

    CONSTRAINT `fk_check_certificate`
        FOREIGN KEY (`certificate_id`) REFERENCES `oidc_certificate` (`id`)
        ON DELETE CASCADE
        ON UPDATE RESTRICT
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;


-- ---------------------------------------------------------------------------
-- Seed data — standard X.509 key usage values
-- Unknown values encountered later are inserted automatically with code as label.
-- ---------------------------------------------------------------------------

INSERT INTO `oidc_key_usage` (`code`, `label`) VALUES
    ('digitalSignature', 'Digital signature'),
    ('nonRepudiation',   'Non-repudiation'),
    ('keyEncipherment',  'Key encipherment'),
    ('dataEncipherment', 'Data encipherment'),
    ('keyAgreement',     'Key agreement'),
    ('keyCertSign',      'Certificate signing'),
    ('cRLSign',          'CRL signing'),
    ('encipherOnly',     'Encipher only'),
    ('decipherOnly',     'Decipher only')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);
