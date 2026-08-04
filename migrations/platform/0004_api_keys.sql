-- =====================================================================
-- 0004 · platform · public API keys
-- =====================================================================
-- Table name: $config["platform"]["sql"]["tbl"]["ApiKey"]. Keys are issued by
-- admins under /manage/settings.
--
-- Safe to re-run: CREATE TABLE IF NOT EXISTS.
-- --------------------------------------------------------

-- A key lets a client get a RAISED rate limit on the public read endpoints.
-- It is NOT an authentication mechanism — it does not unlock admin/non-public
-- actions (those stay session-gated in api/v1/index.php). When a valid key is
-- presented the limiter keys its window on 'key:<ApiKeyID>' in `apiratelimit`
-- and uses ApiKeyRateLimit (NULL = the configured default keyed tier).
-- Only a hash of the secret key is stored (sha256 hex); the raw key is shown
-- to the owner once at creation and never persisted. Revoke via ApiKeyActive
-- = 0 and/or ApiKeyExpires.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `apikey` (
  `ApiKeyID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ApiKeyHash` char(64) NOT NULL,                   -- sha256(hex) of the secret key; raw key never stored
  `ApiKeyPrefix` varchar(16) DEFAULT NULL,          -- non-secret prefix shown in management UIs to identify a key
  `ApiKeyLabel` varchar(191) NOT NULL DEFAULT '',   -- human label / owner description
  `ApiKeyOwnerUserID` int(10) DEFAULT NULL,         -- optional platform user that owns the key
  `ApiKeyRateLimit` int(10) unsigned DEFAULT NULL,  -- per-window request limit for this key (NULL = config default keyed tier)
  `ApiKeyActive` tinyint(1) NOT NULL DEFAULT 1,     -- revoke without deleting
  `ApiKeyCreated` timestamp NOT NULL DEFAULT current_timestamp(),
  `ApiKeyExpires` timestamp NULL DEFAULT NULL,      -- optional hard expiry
  `ApiKeyLastUsed` timestamp NULL DEFAULT NULL,     -- usage tracking
  `ApiKeyLastChanged` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ApiKeyID`),
  UNIQUE KEY `uniq_apikey_hash` (`ApiKeyHash`),
  KEY `idx_apikey_active` (`ApiKeyActive`),
  KEY `idx_apikey_owner` (`ApiKeyOwnerUserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
