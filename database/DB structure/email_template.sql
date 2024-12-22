/*
SQLyog Ultimate v8.55 
MySQL - 5.5.5-10.2.8-MariaDB : Database - local_bdmsapi
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`local_bdmsapi` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `local_bdmsapi`;

/*Table structure for table `email_contents` */

CREATE TABLE `email_contents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `to_email` text DEFAULT NULL,
  `from_name` varchar(255) DEFAULT NULL,
  `from_email` varchar(255) DEFAULT NULL,
  `cc_email` text DEFAULT NULL,
  `bcc_email` text DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `status` int(3) DEFAULT 0,
  `created_on` datetime DEFAULT NULL,
  `created_by` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=latin1;

/*Table structure for table `email_template` */

CREATE TABLE `email_template` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `to` varchar(1500) DEFAULT NULL,
  `cc` varchar(1500) NOT NULL,
  `bcc` varchar(500) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_on` datetime DEFAULT NULL,
  `created_by` smallint(5) DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by` smallint(5) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_code_2` (`code`),
  KEY `event_id` (`id`),
  KEY `event_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=160 DEFAULT CHARSET=latin1;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
