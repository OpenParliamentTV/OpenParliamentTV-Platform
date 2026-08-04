-- =====================================================================
-- 0003 · platform · public API rate limiting
-- =====================================================================
-- Table name: $config["platform"]["sql"]["tbl"]["ApiRateLimit"].
-- Behaviour is configured under $config["rateLimit"].
--
-- Safe to re-run: CREATE TABLE IF NOT EXISTS.
-- --------------------------------------------------------

-- --------------------------------------------------------
-- `apiratelimit` — fixed-window request counter, one row per client key.
-- RateLimitKey is a salted sha1 of the client IP (raw IP is not stored), or
-- 'key:<ApiKeyID>' once API keys are in use. Rows are reset on each new window
-- and pruned opportunistically by the app.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `apiratelimit` (
  `RateLimitKey` varchar(191) NOT NULL,
  `RateLimitWindowStart` int(10) unsigned NOT NULL,
  `RateLimitCount` int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`RateLimitKey`),
  KEY `idx_ratelimit_window` (`RateLimitWindowStart`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
