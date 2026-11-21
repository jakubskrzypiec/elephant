-- Starting schema for PHP + MySQL/MariaDB deployment. No real customer data or passwords.
-- All DATETIME values are UTC; availability rules use Europe/Warsaw.
CREATE TABLE IF NOT EXISTS admin_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(254) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS projects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(190) NOT NULL UNIQUE,
  title VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_images (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  alt_text VARCHAR(500) NOT NULL DEFAULT '',
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS articles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(190) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  excerpt TEXT NOT NULL,
  body MEDIUMTEXT NOT NULL,
  seo_title VARCHAR(255) NOT NULL DEFAULT '',
  seo_description TEXT NOT NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inquiries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  email VARCHAR(254) NOT NULL,
  phone VARCHAR(50) NOT NULL DEFAULT '',
  topic VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  project_details_json TEXT NOT NULL,
  consent_at DATETIME NOT NULL,
  status ENUM('new','read','replied','archived') NOT NULL DEFAULT 'new',
  notes TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX inquiries_status_date (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS availability_rules (
  weekday TINYINT UNSIGNED PRIMARY KEY COMMENT '0=Monday, 6=Sunday',
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blocked_dates (
  local_date DATE PRIMARY KEY,
  reason VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lock the local_date row SELECT ... FOR UPDATE before checking/inserting bookings.
-- A unique start time alone does not protect overlapping bookings or buffer periods.
CREATE TABLE IF NOT EXISTS booking_day_locks (
  local_date DATE PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bookings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  email VARCHAR(254) NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  buffer_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  status ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  notes TEXT NOT NULL,
  consent_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX bookings_interval (starts_at, ends_at, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mail_outbox (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  deduplication_key VARCHAR(190) NOT NULL UNIQUE,
  recipient VARCHAR(254) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  last_error TEXT NULL,
  INDEX mail_pending (sent_at, available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO availability_rules (weekday, enabled, start_time, end_time) VALUES
  (0,1,'09:00','17:00'), (1,1,'09:00','17:00'), (2,1,'09:00','17:00'),
  (3,1,'09:00','17:00'), (4,1,'09:00','15:00'), (5,0,'09:00','17:00'), (6,0,'09:00','17:00');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('timezone','Europe/Warsaw'), ('duration_minutes','60'), ('buffer_minutes','15'),
  ('minimum_notice_hours','24'), ('notification_email','elephant.kontakt@gmail.com');
