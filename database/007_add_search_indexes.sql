-- ================================================================
-- AP7: Search indexes
-- ================================================================
--
-- FULLTEXT indexes (optional - only needed if SEARCH_MODE = 'fulltext' or 'auto')
-- These require InnoDB with MySQL >= 5.6 or MariaDB >= 10.0.5.
--
-- Run these statements to enable FULLTEXT search:
--
--   ALTER TABLE pages ADD FULLTEXT INDEX ft_pages (title, content_md);
--   ALTER TABLE tasks ADD FULLTEXT INDEX ft_tasks (title, description_md);
--
-- If your hosting does not support FULLTEXT on InnoDB, you can
-- leave SEARCH_MODE = 'like' in config.php and skip these indexes.
--
-- ================================================================

-- Title indexes for faster LIKE searches on title columns
-- (These are safe to run on any MySQL/MariaDB setup)

CREATE INDEX IF NOT EXISTS idx_pages_title ON pages (title(191));
CREATE INDEX IF NOT EXISTS idx_tasks_title ON tasks (title(191));
