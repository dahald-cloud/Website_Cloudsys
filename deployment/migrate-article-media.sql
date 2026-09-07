-- Article inline media. Safe to run repeatedly after migrate-insights.sql.
CREATE TABLE IF NOT EXISTS article_media (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  article_id BIGINT UNSIGNED NOT NULL,
  file_path VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  alt_text VARCHAR(255) NOT NULL,
  uploaded_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_article_media_path (file_path),
  KEY idx_article_media_article (article_id, id),
  CONSTRAINT fk_article_media_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
  CONSTRAINT fk_article_media_admin FOREIGN KEY (uploaded_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
