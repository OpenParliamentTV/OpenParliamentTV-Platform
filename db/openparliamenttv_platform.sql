SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `auth` (
  `AuthID` int(8) NOT NULL,
  `AuthUserID` int(8) NOT NULL,
  `AuthAction` varchar(255) NOT NULL,
  `AuthEntity` varchar(255) DEFAULT NULL,
  `AuthEntityKey` varchar(255) DEFAULT NULL,
  `AuthEntityValue` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `conflict` (
  `ConflictID` int(11) NOT NULL,
  `ConflictEntity` varchar(255) NOT NULL,
  `ConflictIdentifier` varchar(255) DEFAULT NULL,
  `ConflictRival` varchar(255) DEFAULT NULL,
  `ConflictSubject` varchar(255) NOT NULL,
  `ConflictDescription` text DEFAULT NULL,
  `ConflictDate` varchar(255) NOT NULL,
  `ConflictTimestamp` int(11) DEFAULT NULL,
  `ConflictResolved` int(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `document` (
  `DocumentID` int(10) NOT NULL,
  `DocumentType` varchar(255) NOT NULL,
  `DocumentWikidataID` varchar(255) DEFAULT NULL,
  `DocumentLabel` varchar(255) NOT NULL,
  `DocumentLabelAlternative` text DEFAULT NULL,
  `DocumentAbstract` text NOT NULL,
  `DocumentThumbnailURI` varchar(1024) DEFAULT NULL,
  `DocumentThumbnailCreator` varchar(255) DEFAULT NULL,
  `DocumentThumbnailLicense` varchar(255) DEFAULT NULL,
  `DocumentSourceURI` varchar(255) NOT NULL,
  `DocumentEmbedURI` varchar(255) DEFAULT NULL,
  `DocumentAdditionalInformation` text DEFAULT NULL,
  `DocumentLastChanged` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `entitysuggestion` (
  `EntitysuggestionID` int(11) NOT NULL,
  `EntitysuggestionExternalID` varchar(255) NOT NULL,
  `EntitysuggestionType` varchar(255) NOT NULL,
  `EntitysuggestionLabel` varchar(255) NOT NULL,
  `EntitysuggestionContent` text NOT NULL,
  `EntitysuggestionContext` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `organisation` (
  `OrganisationID` varchar(191) NOT NULL,
  `OrganisationType` varchar(255) DEFAULT NULL,
  `OrganisationLabel` varchar(255) NOT NULL,
  `OrganisationLabelAlternative` text DEFAULT NULL,
  `OrganisationAbstract` text DEFAULT NULL,
  `OrganisationThumbnailURI` varchar(1024) DEFAULT NULL,
  `OrganisationThumbnailCreator` varchar(255) DEFAULT NULL,
  `OrganisationThumbnailLicense` varchar(255) DEFAULT NULL,
  `OrganisationEmbedURI` varchar(255) DEFAULT NULL,
  `OrganisationWebsiteURI` varchar(255) DEFAULT NULL,
  `OrganisationSocialMediaIDs` varchar(1024) DEFAULT NULL,
  `OrganisationColor` varchar(255) DEFAULT NULL,
  `OrganisationFilterable` int(1) DEFAULT NULL,
  `OrganisationOrder` int(2) DEFAULT NULL,
  `OrganisationAdditionalInformation` text DEFAULT NULL,
  `OrganisationLastChanged` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `person` (
  `PersonID` varchar(191) NOT NULL,
  `PersonType` varchar(255) DEFAULT NULL,
  `PersonLabel` varchar(255) NOT NULL,
  `PersonLabelAlternative` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '[]',
  `PersonFirstName` varchar(255) DEFAULT NULL,
  `PersonLastName` varchar(255) DEFAULT NULL,
  `PersonDegree` varchar(255) DEFAULT NULL,
  `PersonBirthDate` date DEFAULT NULL,
  `PersonGender` varchar(255) DEFAULT NULL,
  `PersonAbstract` text DEFAULT NULL,
  `PersonThumbnailURI` varchar(1024) DEFAULT NULL,
  `PersonThumbnailCreator` varchar(255) DEFAULT NULL,
  `PersonThumbnailLicense` varchar(255) DEFAULT NULL,
  `PersonEmbedURI` varchar(255) DEFAULT NULL,
  `PersonWebsiteURI` varchar(255) DEFAULT NULL,
  `PersonOriginID` varchar(255) DEFAULT NULL,
  `PersonPartyOrganisationID` varchar(255) DEFAULT NULL,
  `PersonFactionOrganisationID` varchar(255) DEFAULT NULL,
  `PersonSocialMediaIDs` text DEFAULT NULL,
  `PersonAdditionalInformation` text DEFAULT NULL,
  `PersonLastChanged` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `term` (
  `TermID` varchar(191) NOT NULL,
  `TermType` varchar(255) NOT NULL,
  `TermLabel` varchar(255) NOT NULL,
  `TermLabelAlternative` text DEFAULT NULL,
  `TermAbstract` text NOT NULL,
  `TermThumbnailURI` varchar(1024) DEFAULT NULL,
  `TermThumbnailCreator` varchar(255) DEFAULT NULL,
  `TermThumbnailLicense` varchar(255) DEFAULT NULL,
  `TermWebsiteURI` varchar(255) DEFAULT NULL,
  `TermEmbedURI` varchar(255) DEFAULT NULL,
  `TermAdditionalInformation` text DEFAULT NULL,
  `TermLastChanged` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user` (
  `UserID` int(10) NOT NULL,
  `UserName` varchar(255) NOT NULL,
  `UserMail` varchar(255) NOT NULL,
  `UserPasswordHash` varchar(255) NOT NULL,
  `UserPasswordPepper` varchar(255) NOT NULL,
  `UserRole` varchar(255) NOT NULL,
  `UserRegisterDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `UserLastLogin` timestamp NULL DEFAULT NULL,
  `UserActive` tinyint(1) NOT NULL DEFAULT 0,
  `UserRegisterConfirmation` varchar(255) DEFAULT NULL,
  `UserPasswordReset` varchar(255) NOT NULL DEFAULT '0',
  `UserBlocked` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- Notifications & Alerts, API rate limiting, API keys (feature tables)
-- ---------------------------------------------------------------------

CREATE TABLE `alert` (
  `AlertID` int(10) NOT NULL,
  `AlertUserID` int(10) NOT NULL,
  `AlertCriteria` text NOT NULL,
  `AlertFrequency` enum('realtime','daily','weekly') NOT NULL DEFAULT 'realtime',
  `AlertChannelEmail` tinyint(1) NOT NULL DEFAULT 1,
  `AlertChannelInApp` tinyint(1) NOT NULL DEFAULT 1,
  `AlertActive` tinyint(1) NOT NULL DEFAULT 1,
  `AlertCreated` timestamp NOT NULL DEFAULT current_timestamp(),
  `AlertLastTriggered` timestamp NULL DEFAULT NULL,
  `AlertLastChanged` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification` (
  `NotificationID` int(10) NOT NULL,
  `NotificationUserID` int(10) NOT NULL,
  `NotificationAlertID` int(10) DEFAULT NULL,
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
  `NotificationCreated` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_message` (
  `SystemMessageID` int(10) NOT NULL,
  `SystemMessageType` enum('broadcast','event') NOT NULL,
  `SystemMessageTitle` varchar(500) NOT NULL,
  `SystemMessageBody` text DEFAULT NULL,
  `SystemMessageLink` varchar(1024) DEFAULT NULL,
  `SystemMessageCreatedBy` int(10) DEFAULT NULL,
  `SystemMessageTargetRole` varchar(255) DEFAULT NULL,
  `SystemMessageSendEmail` tinyint(1) NOT NULL DEFAULT 0,
  `SystemMessageCreated` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification_preference` (
  `NotificationPreferenceID` int(10) NOT NULL,
  `NotificationPreferenceUserID` int(10) NOT NULL,
  `NotificationPreferenceEmailEnabled` tinyint(1) NOT NULL DEFAULT 1,
  `NotificationPreferenceDigestFrequency` enum('daily','weekly') DEFAULT 'daily',
  `NotificationPreferenceDigestDay` tinyint(1) DEFAULT 1,
  `NotificationPreferenceUnsubscribeToken` varchar(64) NOT NULL,
  `NotificationPreferenceLastChanged` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `apiratelimit` (
  `RateLimitKey` varchar(191) NOT NULL,
  `RateLimitWindowStart` int(10) unsigned NOT NULL,
  `RateLimitCount` int(10) unsigned NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `apikey` (
  `ApiKeyID` int(10) unsigned NOT NULL,
  `ApiKeyHash` char(64) NOT NULL,
  `ApiKeyPrefix` varchar(16) DEFAULT NULL,
  `ApiKeyLabel` varchar(191) NOT NULL DEFAULT '',
  `ApiKeyOwnerUserID` int(10) DEFAULT NULL,
  `ApiKeyRateLimit` int(10) unsigned DEFAULT NULL,
  `ApiKeyActive` tinyint(1) NOT NULL DEFAULT 1,
  `ApiKeyCreated` timestamp NOT NULL DEFAULT current_timestamp(),
  `ApiKeyExpires` timestamp NULL DEFAULT NULL,
  `ApiKeyLastUsed` timestamp NULL DEFAULT NULL,
  `ApiKeyLastChanged` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `auth`
  ADD PRIMARY KEY (`AuthID`);

ALTER TABLE `conflict`
  ADD PRIMARY KEY (`ConflictID`);

ALTER TABLE `document`
  ADD PRIMARY KEY (`DocumentID`),
  ADD KEY `DocumentLabel` (`DocumentLabel`),
  ADD KEY `DocumentWikidataID` (`DocumentWikidataID`);
ALTER TABLE `document` ADD FULLTEXT KEY `DocumentLabel_2` (`DocumentLabel`,`DocumentLabelAlternative`,`DocumentAbstract`);

ALTER TABLE `entitysuggestion`
  ADD PRIMARY KEY (`EntitysuggestionID`),
  ADD KEY `EntitysuggestionExternalID` (`EntitysuggestionExternalID`,`EntitysuggestionLabel`);

ALTER TABLE `organisation`
  ADD PRIMARY KEY (`OrganisationID`),
  ADD KEY `OrganisationLabel` (`OrganisationLabel`),
  ADD KEY `OrganisationType` (`OrganisationType`);
ALTER TABLE `organisation` ADD FULLTEXT KEY `OrganisationLabel_2` (`OrganisationLabel`,`OrganisationLabelAlternative`,`OrganisationAbstract`);

ALTER TABLE `person`
  ADD PRIMARY KEY (`PersonID`),
  ADD KEY `PersonType` (`PersonType`),
  ADD KEY `PersonLabel` (`PersonLabel`),
  ADD KEY `PersonLabelAlternative` (`PersonLabelAlternative`(768)),
  ADD KEY `PersonPartyOrganisationID` (`PersonPartyOrganisationID`),
  ADD KEY `PersonFactionOrganisationID` (`PersonFactionOrganisationID`),
  ADD KEY `PersonFirstName` (`PersonFirstName`),
  ADD KEY `PersonLastName` (`PersonLastName`);
ALTER TABLE `person` ADD FULLTEXT KEY `PersonLabel_2` (`PersonLabel`,`PersonFirstName`,`PersonLastName`);

ALTER TABLE `term`
  ADD PRIMARY KEY (`TermID`),
  ADD KEY `TermLabel` (`TermLabel`);
ALTER TABLE `term` ADD FULLTEXT KEY `TermLabel_2` (`TermLabel`,`TermLabelAlternative`);
ALTER TABLE `term` ADD FULLTEXT KEY `TermLabel_3` (`TermLabel`,`TermLabelAlternative`,`TermAbstract`);

ALTER TABLE `user`
  ADD PRIMARY KEY (`UserID`);

ALTER TABLE `alert`
  ADD PRIMARY KEY (`AlertID`),
  ADD KEY `idx_alert_user` (`AlertUserID`),
  ADD KEY `idx_alert_active` (`AlertActive`);

ALTER TABLE `notification`
  ADD PRIMARY KEY (`NotificationID`),
  ADD UNIQUE KEY `idx_notif_dedup` (`NotificationAlertID`,`NotificationMediaID`),
  ADD KEY `idx_notification_user_read` (`NotificationUserID`,`NotificationRead`),
  ADD KEY `idx_notification_email` (`NotificationEmailSent`,`NotificationType`),
  ADD KEY `idx_notification_created` (`NotificationCreated`);

ALTER TABLE `system_message`
  ADD PRIMARY KEY (`SystemMessageID`);

ALTER TABLE `notification_preference`
  ADD PRIMARY KEY (`NotificationPreferenceID`),
  ADD UNIQUE KEY `idx_pref_user` (`NotificationPreferenceUserID`),
  ADD UNIQUE KEY `idx_pref_token` (`NotificationPreferenceUnsubscribeToken`);

ALTER TABLE `apiratelimit`
  ADD PRIMARY KEY (`RateLimitKey`),
  ADD KEY `idx_ratelimit_window` (`RateLimitWindowStart`);

ALTER TABLE `apikey`
  ADD PRIMARY KEY (`ApiKeyID`),
  ADD UNIQUE KEY `uniq_apikey_hash` (`ApiKeyHash`),
  ADD KEY `idx_apikey_active` (`ApiKeyActive`),
  ADD KEY `idx_apikey_owner` (`ApiKeyOwnerUserID`);


ALTER TABLE `auth`
  MODIFY `AuthID` int(8) NOT NULL AUTO_INCREMENT;

ALTER TABLE `conflict`
  MODIFY `ConflictID` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `document`
  MODIFY `DocumentID` int(10) NOT NULL AUTO_INCREMENT;

ALTER TABLE `entitysuggestion`
  MODIFY `EntitysuggestionID` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `user`
  MODIFY `UserID` int(10) NOT NULL AUTO_INCREMENT;

ALTER TABLE `alert`
  MODIFY `AlertID` int(10) NOT NULL AUTO_INCREMENT;

ALTER TABLE `notification`
  MODIFY `NotificationID` int(10) NOT NULL AUTO_INCREMENT;

ALTER TABLE `system_message`
  MODIFY `SystemMessageID` int(10) NOT NULL AUTO_INCREMENT;

ALTER TABLE `notification_preference`
  MODIFY `NotificationPreferenceID` int(10) NOT NULL AUTO_INCREMENT;

ALTER TABLE `apikey`
  MODIFY `ApiKeyID` int(10) unsigned NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
