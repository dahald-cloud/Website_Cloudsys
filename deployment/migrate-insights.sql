-- Insights Phase 1. Import after schema.sql; safe to re-run.
-- All article timestamps are UTC. No existing accounts or content are replaced.
CREATE TABLE IF NOT EXISTS article_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name VARCHAR(100) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_article_category_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS articles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id BIGINT UNSIGNED NOT NULL,
  author_admin_id BIGINT UNSIGNED NULL,
  author_name VARCHAR(100) NOT NULL DEFAULT 'CloudSys',
  slug VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  title VARCHAR(200) NOT NULL,
  summary VARCHAR(500) NOT NULL DEFAULT '',
  body_text MEDIUMTEXT NOT NULL,
  status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
  cover_image_path VARCHAR(255) NULL,
  cover_image_alt VARCHAR(255) NOT NULL DEFAULT '',
  seo_title VARCHAR(200) NOT NULL DEFAULT '',
  seo_description VARCHAR(500) NOT NULL DEFAULT '',
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_article_slug (slug),
  KEY idx_article_publication (status, published_at, id),
  KEY idx_article_category_publication (category_id, status, published_at, id),
  CONSTRAINT fk_article_category FOREIGN KEY (category_id) REFERENCES article_categories(id) ON DELETE RESTRICT,
  CONSTRAINT fk_article_author FOREIGN KEY (author_admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO article_categories (slug, name, sort_order) VALUES
  ('netsuite', 'NetSuite', 10),
  ('ai-automation', 'AI & Automation', 20),
  ('problems-solved', 'Problems We Have Solved', 30)
ON DUPLICATE KEY UPDATE slug = VALUES(slug);
