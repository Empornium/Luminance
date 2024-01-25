-- MySQL dump 10.16  Distrib 10.1.17-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: gazelle
-- ------------------------------------------------------
-- Server version	10.1.17-MariaDB-1~jessie

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `users_info`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `users_info` (
  `UserID` int(10) unsigned NOT NULL,
  `StyleID` int(10) unsigned NOT NULL,
  `Info` text NOT NULL,
  `Avatar` varchar(255) NOT NULL,
  `Country` int(10) unsigned NOT NULL,
  `TimeZone` varchar(128) NOT NULL,
  `AdminComment` mediumtext NOT NULL,
  `SeedHistory` mediumtext NOT NULL,
  `SiteOptions` text NOT NULL,
  `ViewAvatars` enum('0','1') NOT NULL DEFAULT '1',
  `Donor` enum('0','1') NOT NULL DEFAULT '0',
  `DownloadAlt` enum('0','1') NOT NULL DEFAULT '0',
  `DeletePMs` enum('0','1') NOT NULL DEFAULT '1',
  `SaveSentPMs` enum('0','1') NOT NULL DEFAULT '0',
  `BlockPMs` enum('0','1','2') NOT NULL DEFAULT '0',
  `BlockGifts` enum('0','1','2') NOT NULL DEFAULT '0',
  `CommentsNotify` enum('0','1') NOT NULL DEFAULT '1',
  `SupportFor` varchar(255) NOT NULL,
  `TorrentGrouping` enum('0','1','2') NOT NULL COMMENT '0=Open,1=Closed,2=Off',
  `ShowTags` enum('0','1') NOT NULL DEFAULT '1',
  `JoinDate` datetime DEFAULT NULL,
  `Inviter` int(10) DEFAULT NULL,
  `SuppressConnPrompt` enum('0','1') NOT NULL DEFAULT '0',
  `RatioWatchEnds` datetime DEFAULT NULL,
  `RatioWatchDownload` bigint(20) unsigned NOT NULL DEFAULT '0',
  `RatioWatchTimes` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `Recruiter` enum('0','1') NOT NULL DEFAULT '0',
  `BBcodeTitle` enum('0','1') NOT NULL DEFAULT '0',
  `BanDate` datetime DEFAULT NULL,
  `BanReason` enum('0','1','2','3','4','5') NOT NULL DEFAULT '0',
  `CatchupTime` datetime DEFAULT NULL,
  `LastReadNews` int(10) NOT NULL DEFAULT '0',
  `LastReadBlog` int(10) NOT NULL DEFAULT '0',
  `LastReadContests` int(10) NOT NULL DEFAULT '0',
  `LastBrowse` datetime DEFAULT NULL,
  `RestrictedForums` varchar(150) NOT NULL DEFAULT '',
  `PermittedForums` varchar(150) NOT NULL DEFAULT '',
  `UnseededAlerts` enum('0','1') NOT NULL DEFAULT '0',
  `TorrentSignature` text NOT NULL,
  `InactivityException` datetime DEFAULT NULL,
  `RunHour` tinyint(2) unsigned NOT NULL DEFAULT '0',
  UNIQUE KEY `UserID` (`UserID`),
  KEY `SupportFor` (`SupportFor`),
  KEY `Donor` (`Donor`),
  KEY `JoinDate` (`JoinDate`),
  KEY `Inviter` (`Inviter`),
  KEY `RatioWatchEnds` (`RatioWatchEnds`),
  KEY `RatioWatchDownload` (`RatioWatchDownload`),
  KEY `RunHour` (`RunHour`),
  KEY `InactivityException` (`InactivityException`),
  CONSTRAINT `users_info_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users_main` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2017-01-30 15:02:59
