-- NeXLace fresh-install schema, reconstructed from the PHP application on 2026-10-05.
-- No original database backup was available. This is not a production database export.
-- Select an empty database first; there are no DROP statements or seeded accounts.
SET NAMES utf8mb4;

CREATE TABLE register (
  Id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  Name VARCHAR(255) NOT NULL,
  Email VARCHAR(255) NOT NULL UNIQUE,
  Password VARCHAR(255) NOT NULL,
  `Professional Headline` VARCHAR(255) DEFAULT '',
  Bio TEXT,
  image VARCHAR(1024),
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE developers (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  rate DECIMAL(10,2) NOT NULL,
  availability VARCHAR(50) DEFAULT 'more_than_30',
  location VARCHAR(255), phone VARCHAR(50), languages TEXT,
  work_history TEXT, education TEXT, project_link VARCHAR(2048),
  bio TEXT, skills TEXT, years_experience VARCHAR(100),
  image_path VARCHAR(1024), portfolio_images TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES register(Id) ON DELETE CASCADE,
  CHECK (rate >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post_jobs (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  job_title VARCHAR(255) NOT NULL, job_details TEXT NOT NULL,
  skills_required TEXT, estimated_budget INT, project_timeline VARCHAR(100),
  category VARCHAR(100) DEFAULT 'Web Development',
  project_type VARCHAR(50) DEFAULT 'Fixed Price',
  experience_level VARCHAR(50) DEFAULT 'Intermediate',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_job_owner_created (user_id,created_at),
  FOREIGN KEY (user_id) REFERENCES register(Id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_applications (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL, developer_id INT NOT NULL, client_id INT NOT NULL,
  cover_letter TEXT, proposed_rate DECIMAL(10,2) DEFAULT 0,
  status ENUM('pending','accepted','rejected','withdrawn') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_application (job_id,developer_id),
  INDEX idx_developer (developer_id), INDEX idx_client (client_id),
  FOREIGN KEY (job_id) REFERENCES post_jobs(id) ON DELETE CASCADE,
  FOREIGN KEY (developer_id) REFERENCES register(Id) ON DELETE CASCADE,
  FOREIGN KEY (client_id) REFERENCES register(Id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invitations (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL, receiver_id INT NOT NULL,
  work_type VARCHAR(255) NOT NULL, work_email VARCHAR(255), work_details TEXT,
  job_id INT, application_id INT,
  status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  responded_at TIMESTAMP NULL,
  INDEX idx_invitation_sender (sender_id,created_at),
  INDEX idx_invitation_receiver (receiver_id,status),
  FOREIGN KEY (sender_id) REFERENCES register(Id) ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES register(Id) ON DELETE CASCADE,
  FOREIGN KEY (job_id) REFERENCES post_jobs(id) ON DELETE SET NULL,
  FOREIGN KEY (application_id) REFERENCES job_applications(id) ON DELETE SET NULL,
  CHECK (sender_id <> receiver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL, receiver_id INT NOT NULL, message TEXT NOT NULL,
  attachment_path VARCHAR(1024), attachment_name VARCHAR(255),
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_message_pair (sender_id,receiver_id,created_at),
  FOREIGN KEY (sender_id) REFERENCES register(Id) ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES register(Id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL, type VARCHAR(50) NOT NULL,
  title VARCHAR(255) NOT NULL, message TEXT, link VARCHAR(2048),
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notification_user_read (user_id,is_read,created_at),
  FOREIGN KEY (user_id) REFERENCES register(Id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  reviewer_id INT NOT NULL, reviewee_id INT NOT NULL,
  rating TINYINT NOT NULL, review_text TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reviewee (reviewee_id),
  FOREIGN KEY (reviewer_id) REFERENCES register(Id) ON DELETE CASCADE,
  FOREIGN KEY (reviewee_id) REFERENCES register(Id) ON DELETE CASCADE,
  CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_sessions (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL, session_token CHAR(64) NOT NULL UNIQUE,
  ip_address VARCHAR(45), user_agent TEXT, device_type VARCHAR(50),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_session_user_created (user_id,created_at),
  FOREIGN KEY (user_id) REFERENCES register(Id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL, email VARCHAR(255) NOT NULL,
  attempts INT NOT NULL DEFAULT 1,
  last_attempt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ip_email (ip_address,email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE liked_jobs (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL, job_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_like (user_id,job_id),
  INDEX idx_job (job_id),
  FOREIGN KEY (user_id) REFERENCES register(Id) ON DELETE CASCADE,
  FOREIGN KEY (job_id) REFERENCES post_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_users (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL,
  full_name VARCHAR(100),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
