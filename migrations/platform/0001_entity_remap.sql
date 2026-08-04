-- =====================================================================
-- 0001 · platform · entity_remap
-- =====================================================================
-- NER Wikidata id corrections. Named-entity recognition regularly returns the
-- *party* id where the speech means the parliamentary *group*, or a regional
-- branch where the platform tracks the national entity. Each row rewrites one
-- source id to the id the platform actually holds, before the annotation is
-- written (api/v1/modules/entity-remap.php, applied in processTextContent).
--
-- Replaces the former data/ner-matching.php, a JSON blob wrapped in PHP.
--
-- EntityRemapParliament = '' means "applies to every parliament"; a
-- parliament-specific row wins over a global one for the same source id.
--
-- The seeded rows are the former file's contents, scoped to DE: they name
-- Bundestag factions, so they were only ever correct for DE. On an instance
-- that has no DE parliament configured they are inert — entityRemapGetMap()
-- never returns them — and can be deleted under /manage/settings.
--
-- Safe to re-run. IF NOT EXISTS keeps an existing table; INSERT IGNORE keys off
-- UNIQUE (parliament, source), not off row ids, so a re-run neither duplicates
-- nor renumbers anything and leaves an edited row's target/description alone.
-- Note the one asymmetry: a re-run DOES restore a seeded row that was
-- deliberately deleted, because "deleted" and "never inserted" look identical
-- from here. Once bin/migrate.php (Phase 6) records applied files this runs
-- once per instance and the question stops arising.
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `entity_remap` (
  `EntityRemapID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `EntityRemapParliament` varchar(16) NOT NULL DEFAULT '',
  `EntityRemapSourceID` varchar(64) NOT NULL,
  `EntityRemapTargetID` varchar(64) NOT NULL,
  `EntityRemapDescription` varchar(255) NOT NULL DEFAULT '',
  `EntityRemapLastChanged` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`EntityRemapID`),
  UNIQUE KEY `uniq_entityremap_source` (`EntityRemapParliament`,`EntityRemapSourceID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `entity_remap` (`EntityRemapParliament`, `EntityRemapSourceID`, `EntityRemapTargetID`, `EntityRemapDescription`) VALUES
('DE', 'Q49766', 'Q1007353', 'Die Grünen Partei to Faction'),
('DE', 'Q49764', 'Q1826856', 'Die Linke Partei to Faction'),
('DE', 'Q49768', 'Q2207512', 'SPD Partei to Faction'),
('DE', 'Q13124', 'Q1387991', 'FDP Partei to Faction'),
('DE', 'Q6721203', 'Q42575708', 'AfD Partei to Faction'),
('DE', 'Q49762', 'Q1023134', 'CDU Partei to Faction'),
('DE', 'Q49763', 'Q1023134', 'CSU Partei to Faction'),
('DE', 'Q446007', 'Q1007353', 'BÜNDNIS 90'),
('DE', 'Q1020847', 'Q1007353', 'Bündnis 90/Die Grünen (Niedersachsen)'),
('DE', 'Q1020838', 'Q1007353', 'Bündnis 90/Die Grünen (Berlin)'),
('DE', 'Q1020837', 'Q1007353', 'Bündnis 90/Die Grünen (Bayern)'),
('DE', 'Q193178', 'Q1007353', 'GRÜNEN'),
('DE', 'Q1517627', 'Q1007353', 'Geschichte von Bündnis 90/Die Grünen'),
('DE', 'Q708725', 'Q1023134', 'Unionsparteien (wir gehen davon aus, dass Fraktion gemeint wurde)'),
('DE', 'Q1023141', 'Q1023134', 'CDU-Fraktion Hamburg'),
('DE', 'Q1023137', 'Q1023134', 'CDU-Fraktion Berlin'),
('DE', 'Q18334483', 'Q42575708', 'Landesverband der Partei Alternative für Deutschland in Thüringen'),
('DE', 'Q24706445', 'Q42575708', 'Landesverband der Partei Alternative für Deutschland in Mecklenburg-Vorpommern'),
('DE', 'Q31838339', 'Q1826856', 'Die Linke Niedersachsen'),
('DE', 'Q164597', 'Q1826856', 'weltanschauliche Strömungen des linken Flügels im politischen Spektrum'),
('DE', 'Q2207521', 'Q2207512', 'SPD Berlin'),
('DE', 'Q1386226', 'Q2207512', 'SPD Bayern'),
('DE', 'Q202638', 'Q1387991', 'FDP liberale Partei in der Schweiz'),
('DE', 'Q13850128', 'Q1387991', 'Freisinnig-Demokratische Partei der Schweiz'),
('DE', 'Q1388017', 'Q1387991', 'FDP Nordrhein-Westfalen'),
('DE', 'Q1388006', 'Q1387991', 'FDP Hessen'),
('DE', 'Q1387996', 'Q1387991', 'Swiss parliamentary group'),
('DE', 'Q1241288', 'Q488589', 'Allgemeines Wirtschaftsministerium auf Deutsches Ministerium'),
('DE', 'Q1519799', 'Q491566', 'Allgemeines Gesundheitsministerium auf Deutsches Ministerium');
