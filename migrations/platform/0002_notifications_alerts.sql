-- =====================================================================
-- 0002 · platform · notifications & alerts
-- =====================================================================
-- Standing user subscriptions (`alert`), the messages they produce
-- (`notification`), admin broadcasts (`system_message`) and per-user delivery
-- preferences (`notification_preference`).
--
-- Feature flag: $config["allow"]["notifications"]. Table names come from
-- $config["platform"]["sql"]["tbl"]["Alert"|"Notification"|"SystemMessage"|
-- "NotificationPreference"].
--
-- Safe to re-run: purely additive, all CREATE TABLE IF NOT EXISTS.
-- --------------------------------------------------------

-- --------------------------------------------------------
-- `alert` — user-defined alert rules (one row = one standing subscription)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `alert` (
  `AlertID` int(10) NOT NULL AUTO_INCREMENT,
  `AlertUserID` int(10) NOT NULL,
  `AlertCriteria` text NOT NULL,                                   -- JSON: search-API param names (personID[], q, …)
  `AlertFrequency` enum('realtime','daily','weekly') NOT NULL DEFAULT 'realtime',
  `AlertChannelEmail` tinyint(1) NOT NULL DEFAULT 1,
  `AlertChannelInApp` tinyint(1) NOT NULL DEFAULT 1,
  `AlertActive` tinyint(1) NOT NULL DEFAULT 1,
  `AlertCreated` timestamp NOT NULL DEFAULT current_timestamp(),
  `AlertLastTriggered` timestamp NULL DEFAULT NULL,
  `AlertLastChanged` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`AlertID`),
  KEY `idx_alert_user` (`AlertUserID`),
  KEY `idx_alert_active` (`AlertActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- `notification` — individual delivered messages (alert match / broadcast / event)
-- The unique (AlertID, MediaID) key dedups alert matches across re-imports/updates.
-- System messages keep both NULL, and MySQL allows multiple NULLs in a unique index.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notification` (
  `NotificationID` int(10) NOT NULL AUTO_INCREMENT,
  `NotificationUserID` int(10) NOT NULL,
  `NotificationAlertID` int(10) DEFAULT NULL,                     -- NULL for system messages
  `NotificationType` enum('alert','system_broadcast','system_event') NOT NULL,
  `NotificationTitle` varchar(500) NOT NULL,
  `NotificationBody` text DEFAULT NULL,
  `NotificationLink` varchar(1024) DEFAULT NULL,
  `NotificationMediaID` varchar(255) DEFAULT NULL,
  `NotificationParliament` varchar(16) DEFAULT NULL,
  `NotificationRead` tinyint(1) NOT NULL DEFAULT 0,
  `NotificationEmailSent` tinyint(1) NOT NULL DEFAULT 0,
  `NotificationEmailSentAt` timestamp NULL DEFAULT NULL,
  `NotificationDigested` tinyint(1) NOT NULL DEFAULT 0,
  `NotificationCreated` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`NotificationID`),
  UNIQUE KEY `idx_notif_dedup` (`NotificationAlertID`, `NotificationMediaID`),
  KEY `idx_notification_user_read` (`NotificationUserID`, `NotificationRead`),
  KEY `idx_notification_email` (`NotificationEmailSent`, `NotificationType`),
  KEY `idx_notification_created` (`NotificationCreated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- `system_message` — admin-authored broadcasts / automated events
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_message` (
  `SystemMessageID` int(10) NOT NULL AUTO_INCREMENT,
  `SystemMessageType` enum('broadcast','event') NOT NULL,
  `SystemMessageTitle` varchar(500) NOT NULL,
  `SystemMessageBody` text DEFAULT NULL,
  `SystemMessageLink` varchar(1024) DEFAULT NULL,
  `SystemMessageCreatedBy` int(10) DEFAULT NULL,
  `SystemMessageTargetRole` varchar(255) DEFAULT NULL,            -- NULL = all users, or 'admin'
  `SystemMessageSendEmail` tinyint(1) NOT NULL DEFAULT 0,
  `SystemMessageCreated` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`SystemMessageID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- `notification_preference` — per-user global preferences (one row per user)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notification_preference` (
  `NotificationPreferenceID` int(10) NOT NULL AUTO_INCREMENT,
  `NotificationPreferenceUserID` int(10) NOT NULL,
  `NotificationPreferenceEmailEnabled` tinyint(1) NOT NULL DEFAULT 1,
  `NotificationPreferenceDigestFrequency` enum('daily','weekly') DEFAULT 'daily',
  `NotificationPreferenceDigestDay` tinyint(1) DEFAULT 1,         -- 1=Mon for weekly
  `NotificationPreferenceUnsubscribeToken` varchar(64) NOT NULL,
  `NotificationPreferenceLastChanged` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`NotificationPreferenceID`),
  UNIQUE KEY `idx_pref_user` (`NotificationPreferenceUserID`),
  UNIQUE KEY `idx_pref_token` (`NotificationPreferenceUnsubscribeToken`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
