-- Portfolio: database schema and demo data
-- Import: mysql -u root -p < database/schema.sql  (or phpMyAdmin → Import)

CREATE DATABASE IF NOT EXISTS portfolio
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE portfolio;

-- Profile (a single row with id = 1)
CREATE TABLE IF NOT EXISTS profile (
  id          TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  full_name   VARCHAR(120) NOT NULL,
  headline    VARCHAR(200) NOT NULL,
  about       TEXT         NOT NULL,
  location    VARCHAR(120) NULL,
  email       VARCHAR(190) NULL,
  github_url  VARCHAR(255) NULL,
  telegram_url VARCHAR(255) NULL,
  linkedin_url VARCHAR(255) NULL,
  avatar_url  VARCHAR(255) NULL,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS skills (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(80)  NOT NULL,
  category   VARCHAR(80)  NOT NULL DEFAULT 'Other',
  level      TINYINT UNSIGNED NOT NULL DEFAULT 50,   -- 0..100
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS projects (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(150) NOT NULL,
  summary     VARCHAR(300) NOT NULL,
  description TEXT NULL,
  tech        VARCHAR(255) NOT NULL DEFAULT '',     -- comma-separated: "PHP, MySQL, JS"
  image_url   VARCHAR(255) NULL,
  demo_url    VARCHAR(255) NULL,
  repo_url    VARCHAR(255) NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order  INT NOT NULL DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS messages (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120) NOT NULL,
  email      VARCHAR(190) NOT NULL,
  body       TEXT NOT NULL,
  ip         VARCHAR(45) NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_read (is_read)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admins (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- Demo data (replace with your own via the admin panel) ----------

INSERT INTO profile (id, full_name, headline, about, location, email, github_url, telegram_url, linkedin_url)
VALUES (1,
  'John Doe',
  'Full-stack Web Developer',
  'I build fast, user-friendly web applications with PHP and JavaScript. I care about clean code, clear interfaces and projects where I get to dig deep into the problem domain. Open to new projects and collaboration.',
  'New York, USA',
  'hello@example.com',
  'https://github.com/',
  NULL,
  'https://www.linkedin.com/')
ON DUPLICATE KEY UPDATE id = id;

INSERT INTO skills (name, category, level, sort_order) VALUES
  ('PHP 8',        'Backend',  90, 1),
  ('MySQL',        'Backend',  85, 2),
  ('REST API',     'Backend',  80, 3),
  ('HTML / CSS',   'Frontend', 90, 1),
  ('JavaScript',   'Frontend', 80, 2),
  ('Vue.js',       'Frontend', 65, 3),
  ('Git',          'Tools',    85, 1),
  ('Docker',       'Tools',    60, 2),
  ('Linux',        'Tools',    70, 3);

INSERT INTO projects (title, summary, description, tech, image_url, demo_url, repo_url, is_featured, sort_order) VALUES
  ('Online Store',
   'Product catalog, cart, checkout and admin panel.',
   'An e-commerce site with product filtering, a session-based cart, checkout and email notifications. Includes an admin panel for managing products and orders.',
   'PHP, MySQL, JavaScript, Bootstrap', NULL, NULL, 'https://github.com/', 1, 1),
  ('Task Tracker',
   'Kanban board with drag-and-drop cards.',
   'A single-page app with boards, columns and drag-and-drop cards, user authentication and a REST API written in PHP.',
   'PHP, REST API, Vue.js, MySQL', NULL, NULL, 'https://github.com/', 1, 2),
  ('Blog Platform',
   'Articles, tags, comments and search.',
   'Publish articles with a Markdown editor, tags, MySQL full-text search and comment moderation.',
   'PHP, MySQL, FULLTEXT, CSS Grid', NULL, NULL, NULL, 0, 3);

-- Create an administrator with:  php create_admin.php <username> <password>
