-- Server-enforced visibility controls for public editorial/service pages.
INSERT INTO site_settings (setting_key, setting_value) VALUES
  ('page_visible_netsuite-optimization', '1'),
  ('page_visible_ai-agents', '1'),
  ('page_visible_case-studies', '1'),
  ('page_visible_managed-support', '1'),
  ('page_visible_insights', '1'),
  ('page_visible_about', '1'),
  ('page_visible_integrations-development', '1'),
  ('page_visible_faq', '1')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
