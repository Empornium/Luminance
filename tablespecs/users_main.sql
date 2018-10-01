-- MySQL dump 10.16  Distrib 10.1.22-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: gazelle_starbuck
-- ------------------------------------------------------
-- Server version	10.1.22-MariaDB-1~jessie

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `users_main`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE IF NOT EXISTS `users_main` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Username` varchar(30) DEFAULT NULL,
  `PassHash` char(40) DEFAULT NULL,
  `Secret` char(32) DEFAULT NULL,
  `LastLogin` datetime DEFAULT NULL,
  `LastAccess` datetime DEFAULT NULL,
  `Uploaded` bigint(20) unsigned NOT NULL DEFAULT '0',
  `Downloaded` bigint(20) unsigned NOT NULL DEFAULT '0',
  `UploadedDaily` bigint(20) unsigned NOT NULL DEFAULT '0',
  `DownloadedDaily` bigint(20) unsigned NOT NULL DEFAULT '0',
  `Title` varchar(128) DEFAULT NULL,
  `Enabled` enum('0','1','2') NOT NULL DEFAULT '0',
  `Paranoia` text,
  `Visible` enum('1','0') NOT NULL DEFAULT '1',
  `Invites` int(10) unsigned NOT NULL DEFAULT '0',
  `PermissionID` int(10) unsigned NOT NULL,
  `GroupPermissionID` int(10) unsigned NOT NULL DEFAULT '0',
  `CustomPermissions` text,
  `can_leech` tinyint(4) NOT NULL DEFAULT '1',
  `track_ipv6` enum('1','0') NOT NULL DEFAULT '0',
  `torrent_pass` char(32) NOT NULL,
  `RequiredRatio` double(10,8) NOT NULL DEFAULT '0.00000000',
  `RequiredRatioWork` double(10,8) NOT NULL DEFAULT '0.00000000',
  `Language` char(2) NOT NULL DEFAULT '',
  `ipcc` char(2) NOT NULL DEFAULT '',
  `FLTokens` int(10) NOT NULL DEFAULT '0',
  `personal_freeleech` datetime DEFAULT NULL,
  `personal_doubleseed` datetime DEFAULT NULL,
  `SeedHoursDaily` double(11,2) NOT NULL DEFAULT '0.00',
  `SeedHours` double(11,2) NOT NULL DEFAULT '0.00',
  `CreditsDaily` double(11,2) NOT NULL DEFAULT '0.00',
  `Credits` double(11,2) NOT NULL DEFAULT '0.00',
  `Signature` text,
  `Flag` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Username` (`Username`),
  KEY `PassHash` (`PassHash`),
  KEY `LastAccess` (`LastAccess`),
  KEY `Uploaded` (`Uploaded`),
  KEY `Downloaded` (`Downloaded`),
  KEY `Enabled` (`Enabled`),
  KEY `Invites` (`Invites`),
  KEY `torrent_pass` (`torrent_pass`),
  KEY `RequiredRatio` (`RequiredRatio`),
  KEY `SeedHoursDaily` (`SeedHoursDaily`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
