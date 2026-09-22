-- Admin-only AI editorial generation audit records. Safe to run repeatedly.
CREATE TABLE IF NOT EXISTS article_ai_generations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id BIGINT UNSIGNED NOT NULL,
  article_id BIGINT UNSIGNED NULL,
  model_name VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  brief_json MEDIUMTEXT NOT NULL,
  output_json MEDIUMTEXT NOT NULL,
  prompt_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('generated','imported','published','discarded') NOT NULL DEFAULT 'generated',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_article_ai_admin (admin_id, created_at),
  KEY idx_article_ai_article (article_id),
  CONSTRAINT fk_article_ai_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE RESTRICT,
  CONSTRAINT fk_article_ai_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
