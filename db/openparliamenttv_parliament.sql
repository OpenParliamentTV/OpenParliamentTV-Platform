SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `agendaitem` (
  `AgendaItemID` int(10) NOT NULL,
  `AgendaItemSessionID` varchar(255) NOT NULL,
  `AgendaItemOrder` int(4) DEFAULT NULL,
  `AgendaItemOfficialTitle` varchar(255) NOT NULL,
  `AgendaItemTitle` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `annotation` (
  `AnnotationID` int(10) NOT NULL,
  `AnnotationMediaID` varchar(255) NOT NULL,
  `AnnotationType` varchar(255) NOT NULL,
  `AnnotationResourceID` varchar(255) DEFAULT NULL,
  `AnnotationContext` varchar(255) NOT NULL,
  `AnnotationFrametrailType` varchar(255) NOT NULL,
  `AnnotationTimeStart` float DEFAULT NULL,
  `AnnotationTimeEnd` float DEFAULT NULL,
  `AnnotationCreator` varchar(255) NOT NULL,
  `AnnotationTags` varchar(1024) DEFAULT NULL,
  `AnnotationAdditionalInformation` varchar(1024) DEFAULT NULL,
  `AnnotationLastChanged` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `electoralperiod` (
  `ElectoralPeriodID` varchar(191) NOT NULL,
  `ElectoralPeriodNumber` int(3) NOT NULL,
  `ElectoralPeriodDateStart` date DEFAULT NULL,
  `ElectoralPeriodDateEnd` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `media` (
  `MediaID` varchar(191) NOT NULL,
  `MediaOriginID` varchar(255) DEFAULT NULL,
  `MediaOriginMediaID` varchar(255) DEFAULT NULL,
  `MediaAgendaItemID` int(10) NOT NULL,
  `MediaCreator` varchar(255) NOT NULL,
  `MediaLicense` varchar(255) NOT NULL,
  `MediaOrder` int(11) DEFAULT NULL,
  `MediaAligned` tinyint(1) NOT NULL DEFAULT 0,
  `MediaPublic` tinyint(1) NOT NULL DEFAULT 0,
  `MediaDateStart` varchar(255) NOT NULL,
  `MediaDateEnd` varchar(255) NOT NULL,
  `MediaDuration` float DEFAULT NULL,
  `MediaVideoFileURI` text NOT NULL,
  `MediaAudioFileURI` text DEFAULT NULL,
  `MediaSourcePage` text NOT NULL,
  `MediaThumbnailURI` varchar(1024) DEFAULT NULL,
  `MediaThumbnailCreator` varchar(255) DEFAULT NULL,
  `MediaThumbnailLicense` varchar(255) DEFAULT NULL,
  `MediaAdditionalInformation` varchar(1024) DEFAULT NULL,
  `MediaLastChanged` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `session` (
  `SessionID` varchar(191) NOT NULL,
  `SessionNumber` int(4) NOT NULL,
  `SessionElectoralPeriodID` varchar(255) NOT NULL,
  `SessionDateStart` varchar(255) DEFAULT NULL,
  `SessionDateEnd` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `text` (
  `TextID` int(10) NOT NULL,
  `TextOriginTextID` varchar(255) DEFAULT NULL,
  `TextMediaID` varchar(255) NOT NULL,
  `TextType` varchar(255) NOT NULL,
  `TextBody` mediumtext NOT NULL,
  `TextSourceURI` varchar(255) DEFAULT NULL,
  `TextCreator` varchar(255) NOT NULL,
  `TextLicense` varchar(255) NOT NULL,
  `TextHash` varchar(255) NOT NULL,
  `TextLanguage` varchar(255) NOT NULL,
  `TextLastChanged` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `agendaitem`
  ADD PRIMARY KEY (`AgendaItemID`),
  ADD KEY `AgendaItemSessionID` (`AgendaItemSessionID`);

ALTER TABLE `annotation`
  ADD PRIMARY KEY (`AnnotationID`),
  ADD KEY `AnnotationMediaID` (`AnnotationMediaID`,`AnnotationResourceID`),
  ADD KEY `AnnotationContext` (`AnnotationContext`);

ALTER TABLE `electoralperiod`
  ADD PRIMARY KEY (`ElectoralPeriodID`);

ALTER TABLE `media`
  ADD PRIMARY KEY (`MediaID`),
  ADD KEY `MediaSourcePage` (`MediaSourcePage`(768));

ALTER TABLE `session`
  ADD PRIMARY KEY (`SessionID`),
  ADD KEY `SessionElectoralPeriodID` (`SessionElectoralPeriodID`);

ALTER TABLE `text`
  ADD PRIMARY KEY (`TextID`),
  ADD KEY `TextMediaID` (`TextMediaID`);


ALTER TABLE `agendaitem`
  MODIFY `AgendaItemID` int(10) NOT NULL AUTO_INCREMENT;

ALTER TABLE `annotation`
  MODIFY `AnnotationID` int(10) NOT NULL AUTO_INCREMENT;

ALTER TABLE `text`
  MODIFY `TextID` int(10) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
