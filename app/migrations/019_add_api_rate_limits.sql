-- AP19: API rate limiting (DB-based, shared hosting compatible)
CREATE TABLE IF NOT EXISTS api_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_prefix CHAR(8) NOT NULL,
    window_start DATETIME NOT NULL,
    window_seconds INT NOT NULL DEFAULT 300,
    request_count INT NOT NULL DEFAULT 1,
    UNIQUE KEY uq_rate_limit (key_prefix, window_start, window_seconds)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
