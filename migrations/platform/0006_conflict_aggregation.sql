-- =====================================================================
-- 0006 · platform · conflict management overhaul
-- =====================================================================
-- Safe to re-run: ADD COLUMN / ADD KEY / MODIFY all carry IF (NOT) EXISTS, and
-- conflict_media uses CREATE TABLE IF NOT EXISTS. Requires MariaDB 10.0+.
--
-- Deliberately does NOT drop the legacy columns — bin/migrate-conflicts.php
-- does that behind --drop-legacy-columns, once the backlog has been aggregated.
-- --------------------------------------------------------

-- Import conflicts are aggregated: one `conflict` row per unique issue
-- (ConflictGroupKey = sha1(type|parliament|entityKey)), with the affected
-- media stored one-row-per-occurrence in `conflict_media`. New columns are
-- purely additive; rows created before the overhaul keep ConflictGroupKey
-- NULL until `data/migrateConflicts.php` aggregates them (the UNIQUE index
-- permits multiple NULLs). The legacy columns (ConflictEntity,
-- ConflictIdentifier, ConflictRival, ConflictSubject, ConflictDescription,
-- ConflictDate, ConflictTimestamp, ConflictResolved) are dropped by that
-- script's --drop-legacy-columns flag, NOT here, so this file stays safe to
-- run before the backlog has been migrated.

-- The legacy NOT NULL columns block inserts from the new aggregating code
-- until they are dropped — give them defaults for the transition window
-- (IF EXISTS keeps this re-runnable after --drop-legacy-columns removed them).
ALTER TABLE `conflict`
  MODIFY IF EXISTS `ConflictEntity` varchar(255) NOT NULL DEFAULT '',
  MODIFY IF EXISTS `ConflictSubject` varchar(255) NOT NULL DEFAULT '',
  MODIFY IF EXISTS `ConflictDate` varchar(255) NOT NULL DEFAULT '';

ALTER TABLE `conflict`
  ADD COLUMN IF NOT EXISTS `ConflictType` varchar(64) NOT NULL DEFAULT '' AFTER `ConflictID`,
  ADD COLUMN IF NOT EXISTS `ConflictGroupKey` varchar(64) DEFAULT NULL AFTER `ConflictType`,
  ADD COLUMN IF NOT EXISTS `ConflictEntityType` varchar(32) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `ConflictEntityLabel` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `ConflictEntityWid` varchar(64) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `ConflictParliament` varchar(16) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `ConflictData` longtext DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `ConflictStatus` enum('open','ignored','resolved') NOT NULL DEFAULT 'open',
  ADD COLUMN IF NOT EXISTS `ConflictFirstSeen` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `ConflictLastSeen` int(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `ConflictResolvedDate` timestamp NULL DEFAULT NULL,
  ADD UNIQUE KEY IF NOT EXISTS `uniq_conflict_group` (`ConflictGroupKey`),
  ADD KEY IF NOT EXISTS `idx_conflict_status` (`ConflictStatus`),
  ADD KEY IF NOT EXISTS `idx_conflict_type` (`ConflictType`);

-- --------------------------------------------------------
-- `conflict_media` — affected media per conflict group (one row per
-- group x media). MediaID holds a platform media ID (e.g. DE-0200210004)
-- or an "origin:<originID>/<originMediaID>" key for conflicts reported
-- before a media row exists (import/validation errors).
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `conflict_media` (
  `ConflictID` int(11) NOT NULL,
  `MediaID` varchar(128) NOT NULL,
  PRIMARY KEY (`ConflictID`,`MediaID`),
  KEY `idx_conflictmedia_media` (`MediaID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
