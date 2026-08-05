-- =====================================================================
-- 0007 · platform · playlists
-- =====================================================================
-- Safe to re-run: both tables use CREATE TABLE IF NOT EXISTS.
-- --------------------------------------------------------

-- A playlist is a user-curated queue for the media player. Two kinds share the
-- table: `custom` rows own their items in playlist_item, `live` rows own none
-- and store search criteria that are resolved at play time (same JSON shape and
-- the same parameter names as AlertCriteria, plus the sort key).
--
-- PlaylistToken is the only public handle — playlists are never addressed by id
-- from outside, so there is nothing to enumerate. PlaylistShared gates whether
-- anyone but the owner may resolve it.
CREATE TABLE IF NOT EXISTS `playlist` (
  `PlaylistID` int(10) NOT NULL AUTO_INCREMENT,
  `PlaylistUserID` int(10) NOT NULL,
  `PlaylistType` enum('custom','live') NOT NULL DEFAULT 'custom',
  `PlaylistTitle` varchar(200) NOT NULL,
  `PlaylistDescription` text DEFAULT NULL,
  `PlaylistToken` char(32) NOT NULL,
  `PlaylistShared` tinyint(1) NOT NULL DEFAULT 0,
  `PlaylistCriteria` text DEFAULT NULL,
  `PlaylistCreated` timestamp NOT NULL DEFAULT current_timestamp(),
  `PlaylistLastChanged` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`PlaylistID`),
  UNIQUE KEY `idx_playlist_token` (`PlaylistToken`),
  KEY `idx_playlist_user` (`PlaylistUserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per speech in a custom playlist. PlaylistItemParliament is carried per
-- item rather than per playlist so a list can mix parliaments; the resolver
-- groups by it before hitting the search index.
--
-- PlaylistItemSegments is a JSON array [{start,end},…] in seconds, or NULL for
-- "play the whole speech" — the same distinction the player already makes
-- between a speech with search finds and one without.
CREATE TABLE IF NOT EXISTS `playlist_item` (
  `PlaylistItemID` int(10) NOT NULL AUTO_INCREMENT,
  `PlaylistItemPlaylistID` int(10) NOT NULL,
  `PlaylistItemParliament` varchar(16) NOT NULL,
  `PlaylistItemMediaID` varchar(255) NOT NULL,
  `PlaylistItemSegments` text DEFAULT NULL,
  `PlaylistItemPosition` int(10) NOT NULL DEFAULT 0,
  `PlaylistItemCreated` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`PlaylistItemID`),
  KEY `idx_playlist_item_playlist` (`PlaylistItemPlaylistID`,`PlaylistItemPosition`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
