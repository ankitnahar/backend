/* SQLyog Ultimate v8.55 
MySQL - 5.5.5-10.2.8-MariaDB : Database - local_bdmsapi ********************************************************************* */  /*!40101 SET NAMES utf8 */;  /*!40101 SET SQL_MODE=''*/;  /*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */; /*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */; /*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */; /*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */; CREATE DATABASE /*!32312 IF NOT EXISTS*/`local_bdmsapi` /*!40100 DEFAULT CHARACTER SET latin1 */;  USE `local_bdmsapi`;  /*Table structure for table `designation` */  CREATE TABLE `designation` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) unsigned DEFAULT NULL,
  `designation_name` varchar(255) NOT NULL,
  `is_mandatory` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 =>"YES", 2 =>"NO"',
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) unsigned DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=latin1;  /*Table structure for table `designation_field_right` */  CREATE TABLE `designation_field_right` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `designation_id` smallint(5) unsigned NOT NULL,
  `field_id` smallint(5) unsigned NOT NULL,
  `view` tinyint(1) DEFAULT NULL COMMENT '1 =>"YES", 2 =>"NO"',
  `add_edit` tinyint(1) DEFAULT NULL COMMENT '1 =>"YES", 2 =>"NO"',
  `delete` tinyint(1) DEFAULT NULL COMMENT '1 =>"YES", 2 =>"NO"',
  `created_on` datetime NOT NULL,
  `created_by` smallint(5) unsigned DEFAULT NULL,
  `modified_on` datetime NOT NULL,
  `modified_by` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;  /*Table structure for table `designation_tab_right` */  CREATE TABLE `designation_tab_right` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tab_id` mediumint(5) unsigned NOT NULL,
  `designation_id` mediumint(5) unsigned NOT NULL,
  `view` tinyint(1) DEFAULT NULL COMMENT '1 =>"YES", 2 =>"NO"',
  `add_edit` tinyint(1) DEFAULT NULL COMMENT '1 =>"YES", 2 =>"NO"',
  `delete` tinyint(1) DEFAULT NULL COMMENT '1 =>"YES", 2 =>"NO"',
  `export` tinyint(1) DEFAULT NULL,
  `download` tinyint(1) DEFAULT NULL,
  `other_rights` varchar(255) DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `created_by` smallint(5) unsigned DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;  /*Table structure for table `hr_detail` */  CREATE TABLE `hr_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` smallint(5) unsigned DEFAULT NULL,
  `shift_id` smallint(5) unsigned DEFAULT NULL,
  `date` date DEFAULT NULL,
  `punch_in` varchar(20) CHARACTER SET latin1 DEFAULT NULL,
  `punch_out` varchar(20) CHARACTER SET latin1 DEFAULT NULL,
  `working_time` varchar(20) CHARACTER SET latin1 DEFAULT NULL,
  `break_time` varchar(20) CHARACTER SET latin1 DEFAULT NULL,
  `office_location` tinyint(3) DEFAULT NULL,
  `status` tinyint(1) DEFAULT NULL,
  `remark` tinyint(1) DEFAULT NULL,
  `final_remark` tinyint(1) DEFAULT NULL,
  `monthly_email_send` tinyint(1) DEFAULT NULL,
  `daily_email_send` tinyint(1) DEFAULT NULL COMMENT '1=>Mailsend,0=>Pending',
  `reason` varchar(500) CHARACTER SET latin1 DEFAULT NULL,
  `is_exception` tinyint(1) DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `created_by` smallint(5) unsigned DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_hr_late_coming_detail` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=189863 DEFAULT CHARSET=utf8;  /*Table structure for table `hr_detail_comment` */  CREATE TABLE `hr_detail_comment` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `hr_detail_id` int(10) unsigned DEFAULT NULL,
  `status` smallint(5) DEFAULT NULL COMMENT '1=>Approved, 0=>Rejected',
  `type` tinyint(1) DEFAULT NULL COMMENT '1=>First approval,2=>Secound approval',
  `comment` varchar(1000) DEFAULT NULL,
  `comment_by` smallint(5) unsigned DEFAULT NULL,
  `comment_on` datetime DEFAULT NULL,
  `date` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10239 DEFAULT CHARSET=latin1;  /*Table structure for table `hr_detail_history` */  CREATE TABLE `hr_detail_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stage_id` smallint(3) DEFAULT NULL,
  `hr_detail_id` int(11) DEFAULT NULL,
  `user_id` mediumint(5) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `created_by` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=121739 DEFAULT CHARSET=utf8;  /*Table structure for table `hr_exception_shift` */  CREATE TABLE `hr_exception_shift` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shift_id` mediumint(5) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `created_by` smallint(5) unsigned DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8;  /*Table structure for table `hr_holiday` */  CREATE TABLE `hr_holiday` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date DEFAULT NULL COMMENT 'Holiday date',
  `year` year(4) DEFAULT NULL COMMENT 'Holiday year',
  `description` varchar(255) DEFAULT NULL,
  `is_active` int(1) DEFAULT NULL COMMENT '1=>Active,0=>Inactive',
  `created_by` smallint(5) unsigned DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `modified_by` smallint(5) unsigned DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=307 DEFAULT CHARSET=latin1;  /*Table structure for table `hr_holiday_detail` */  CREATE TABLE `hr_holiday_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hr_holiday_id` smallint(5) DEFAULT NULL,
  `shift_id` varchar(255) DEFAULT NULL,
  `created_by` smallint(5) DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1262 DEFAULT CHARSET=latin1;  /*Table structure for table `hr_location` */  CREATE TABLE `hr_location` (
  `id` tinyint(3) unsigned NOT NULL,
  `location_name` varchar(150) DEFAULT NULL,
  `status` tinyint(4) DEFAULT NULL,
  `created_by` smallint(5) DEFAULT NULL,
  `created_on` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;  /*Table structure for table `hr_nojob` */  CREATE TABLE `hr_nojob` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_assign_work` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1717 DEFAULT CHARSET=latin1;  /*Table structure for table `hr_pendingtimesheet` */  CREATE TABLE `hr_pendingtimesheet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hr_detail_id` int(11) DEFAULT NULL,
  `user_id` smallint(5) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;  /*Table structure for table `hr_shift_master` */  CREATE TABLE `hr_shift_master` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shift_name` varchar(150) CHARACTER SET latin1 DEFAULT NULL,
  `from_time` time DEFAULT NULL,
  `to_time` time DEFAULT NULL,
  `grace_period` varchar(100) CHARACTER SET latin1 DEFAULT NULL,
  `late_period` varchar(100) CHARACTER SET latin1 DEFAULT NULL,
  `late_allowed_count` tinyint(2) DEFAULT NULL,
  `break_time` varchar(100) CHARACTER SET latin1 DEFAULT NULL,
  `description` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` mediumint(3) unsigned DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `created_by` smallint(5) unsigned DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8;  /*Table structure for table `hr_user_in_out_time` */  CREATE TABLE `hr_user_in_out_time` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hr_detail_id` int(11) DEFAULT NULL,
  `user_id` smallint(5) unsigned DEFAULT NULL,
  `date` date DEFAULT NULL,
  `punch_time` time DEFAULT NULL,
  `punch_type` tinyint(1) DEFAULT NULL COMMENT '1 => In 0 => Out',
  `office_location` tinyint(3) DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `created_by` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_hr_user_in_out_time` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1635498 DEFAULT CHARSET=utf8;  /*Table structure for table `services` */  CREATE TABLE `services` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` smallint(5) DEFAULT NULL,
  `service_name` varchar(255) DEFAULT NULL,
  `pi_zoho_service` varchar(255) DEFAULT NULL COMMENT 'for zoho service name',
  `pi_zoho_service_request` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1 COMMENT '1=>Yes,2=>no',
  `created_on` datetime DEFAULT NULL,
  `created_by` smallint(5) unsigned DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `service_name` (`service_name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;  /*Table structure for table `tab_button` */  CREATE TABLE `tab_button` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tab_id` int(11) NOT NULL,
  `button_name` varchar(100) NOT NULL,
  `button_label` varchar(100) DEFAULT NULL,
  `button_information` varchar(100) DEFAULT NULL,
  `sort_order` int(5) DEFAULT NULL,
  `visible` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;  /*Table structure for table `tabs` */  CREATE TABLE `tabs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) unsigned NOT NULL,
  `tab_name` varchar(100) NOT NULL,
  `tab_url` varchar(255) DEFAULT NULL,
  `tab_information` varchar(255) DEFAULT NULL,
  `tab_unique_name` varchar(255) DEFAULT NULL,
  `tab_image` varchar(100) DEFAULT NULL,
  `sort_order` mediumint(5) unsigned DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1 COMMENT '1 =>"YES", 2 =>"NO"',
  `view` tinyint(1) DEFAULT 0 COMMENT '1 =>"YES", 2 =>"NO"',
  `add_edit` tinyint(1) DEFAULT 0 COMMENT '1 =>"YES", 2 =>"NO"',
  `delete` tinyint(1) DEFAULT 0 COMMENT '1 =>"YES", 2 =>"NO"',
  `export` tinyint(1) DEFAULT 0,
  `download` tinyint(1) DEFAULT 0,
  `created_on` datetime DEFAULT NULL,
  `created_by` smallint(5) unsigned DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=latin1;  /*Table structure for table `team` */  CREATE TABLE `team` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_id` varchar(50) NOT NULL,
  `team_name` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;  /*Table structure for table `timesheet` */  CREATE TABLE `timesheet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hr_detail_id` int(11) DEFAULT NULL,
  `worksheet_id` int(11) DEFAULT NULL,
  `service_id` smallint(5) DEFAULT NULL,
  `entity_id` smallint(5) unsigned NOT NULL,
  `user_id` smallint(5) unsigned DEFAULT NULL,
  `subactivity_id` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `units` smallint(5) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `bank_cc_name` varchar(100) DEFAULT NULL,
  `bank_cc_account_no` varchar(100) DEFAULT NULL,
  `period_startdate` date DEFAULT NULL,
  `period_enddate` date DEFAULT NULL,
  `number_selection` varchar(255) DEFAULT NULL,
  `frequency_id` smallint(3) DEFAULT NULL,
  `year_selection` smallint(5) DEFAULT NULL,
  `no_of_transation` smallint(5) DEFAULT NULL,
  `no_of_employee` smallint(5) DEFAULT NULL,
  `name_of_employee` varchar(500) DEFAULT NULL,
  `billing_status` smallint(5) DEFAULT NULL,
  `payroll_option_id` smallint(5) DEFAULT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `invoice_desc` varchar(255) DEFAULT NULL,
  `invoice_amt` varchar(255) DEFAULT NULL,
  `invoice_created` varchar(255) DEFAULT NULL,
  `review_subcode` varchar(255) DEFAULT NULL,
  `reviewer_id` varchar(255) DEFAULT NULL,
  `is_reviewed` tinyint(1) DEFAULT NULL,
  `subclient_id` smallint(5) unsigned DEFAULT NULL,
  `related_subactivity_id` int(11) DEFAULT NULL,
  `bk_flag_for_checklist` tinyint(1) DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `created_by` smallint(5) unsigned DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `WorksheetId` (`worksheet_id`),
  KEY `FK_timesheet2` (`entity_id`),
  KEY `FK_timesheet3` (`user_id`),
  KEY `FK_timesheet4` (`subactivity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1668681 DEFAULT CHARSET=utf8;  /*Table structure for table `user` */  CREATE TABLE `user` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_bio_id` smallint(5) unsigned NOT NULL,
  `user_fname` varchar(255) NOT NULL,
  `user_lname` varchar(255) NOT NULL,
  `userfullname` varchar(255) NOT NULL,
  `user_login_name` varchar(100) NOT NULL,
  `zoho_login_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(100) DEFAULT NULL,
  `user_birthdate` date DEFAULT NULL,
  `user_register_date` datetime DEFAULT NULL,
  `user_lastlogin` datetime DEFAULT NULL,
  `shift_id` int(5) DEFAULT NULL,
  `first_approval_user` smallint(5) unsigned DEFAULT NULL,
  `second_approval_user` smallint(5) unsigned DEFAULT NULL,
  `redmine_user_id` smallint(5) unsigned DEFAULT NULL,
  `user_writeoff` int(2) unsigned DEFAULT 10,
  `user_timesheet_fillup_flag` tinyint(1) DEFAULT 1 COMMENT '1=>Yes,2=>No',
  `writeoffstaff` int(11) unsigned DEFAULT NULL COMMENT 'Writeoff staff = He/She can approve befree or review writeoff',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '1=>Active,0=>Inactive',
  `leave_allow` smallint(5) DEFAULT NULL,
  `consucative_leave` smallint(3) DEFAULT NULL,
  `consucative_leave_date` varchar(2) DEFAULT NULL,
  `location_id` smallint(2) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=861 DEFAULT CHARSET=latin1;  /*Table structure for table `user_audit` */  CREATE TABLE `user_audit` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `changes` text DEFAULT NULL,
  `type` enum('user_detail','change_password','user_hierarchy','user_tab_right','user_field_right','user_worksheet_right','user_button_right') DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;  /*Table structure for table `user_field_right` */  CREATE TABLE `user_field_right` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `field_id` int(11) unsigned NOT NULL,
  `user_id` smallint(5) unsigned NOT NULL,
  `view` tinyint(1) DEFAULT NULL COMMENT '1 =>"YES", 2 =>"NO"',
  `add_edit` tinyint(1) DEFAULT NULL COMMENT '1 =>"YES", 2 =>"NO"',
  `delete` tinyint(1) DEFAULT NULL COMMENT '1 =>"YES", 2 =>"NO"',
  PRIMARY KEY (`id`),
  KEY `FK_user_field_right` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=43455 DEFAULT CHARSET=latin1;  /*Table structure for table `user_field_right_audit` */  CREATE TABLE `user_field_right_audit` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `changes` text DEFAULT NULL,
  `modified_by` smallint(5) DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;  /*Table structure for table `user_hierarchy` */  CREATE TABLE `user_hierarchy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` smallint(5) unsigned NOT NULL,
  `parent_user_id` smallint(5) DEFAULT NULL,
  `other_service_rights` varchar(50) NOT NULL,
  `team_id` varchar(50) DEFAULT NULL,
  `designation_id` int(5) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_user_hierarchy` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1031 DEFAULT CHARSET=latin1;  /*Table structure for table `user_hierarchy_audit` */  CREATE TABLE `user_hierarchy_audit` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `changes` text DEFAULT NULL,
  `modified_by` smallint(5) DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;  /*Table structure for table `user_tab_right` */  CREATE TABLE `user_tab_right` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tab_id` smallint(5) unsigned NOT NULL,
  `user_id` smallint(5) unsigned NOT NULL,
  `view` tinyint(1) DEFAULT NULL,
  `add_edit` tinyint(1) DEFAULT NULL,
  `delete` tinyint(1) DEFAULT NULL,
  `export` tinyint(1) DEFAULT NULL,
  `download` tinyint(1) DEFAULT NULL,
  `other_right` varchar(255) DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `created_by` smallint(5) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_user_tab_right` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1;  /*Table structure for table `user_tab_right_audit` */  CREATE TABLE `user_tab_right_audit` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `changes` text DEFAULT NULL,
  `modified_by` int(5) DEFAULT NULL,
  `modified_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;  /*Table structure for table `worksheet` */  CREATE TABLE `worksheet` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `worksheet_master_id` int(11) DEFAULT NULL,
  `master_id` int(11) unsigned NOT NULL,
  `task_id` int(11) unsigned NOT NULL,
  `entity_id` smallint(5) unsigned NOT NULL,
  `service_id` smallint(5) unsigned NOT NULL,
  `freq_id` tinyint(2) unsigned NOT NULL,
  `status_id` smallint(5) unsigned NOT NULL,
  `reminder_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `befree_due_date` datetime DEFAULT NULL,
  `ask_repeat_task` tinyint(4) DEFAULT NULL COMMENT '1 => "Yes", 2 => "No"  ',
  `notes` text DEFAULT NULL,
  `last_report_sent` datetime DEFAULT NULL,
  `software` varchar(100) DEFAULT NULL,
  `last_ready_for_review_date` datetime DEFAULT NULL,
  `is_there_delay` tinyint(1) DEFAULT NULL COMMENT '1 => "Yes", 2 => "No"  ',
  `delay_from` tinyint(1) DEFAULT NULL COMMENT '2 => "Befree", 1 => "Client"',
  `delay_from_befree` varchar(500) DEFAULT NULL,
  `delay_from_client` varchar(500) DEFAULT NULL,
  `delay_from_befree_action` varchar(500) DEFAULT NULL,
  `fixed_unit` smallint(5) unsigned NOT NULL,
  `budgeted_unit` smallint(5) unsigned NOT NULL,
  `timesheet_unit` smallint(5) unsigned NOT NULL COMMENT 'In this field we will insert sum of worksheet total unit',
  `is_peer_review` tinyint(1) DEFAULT 1 COMMENT '1 => "Yes", 2 => "No"  ',
  `lock_worksheet` tinyint(1) DEFAULT 1 COMMENT '1 => "Yes", 2 => "No"  ',
  `knockback_count` smallint(5) DEFAULT NULL,
  `neglience_count` smallint(5) DEFAULT NULL,
  `reportsent_count` smallint(5) DEFAULT NULL,
  `rating` smallint(5) DEFAULT NULL,
  `TM_id` smallint(5) DEFAULT NULL,
  `TAM_id` smallint(5) DEFAULT NULL,
  `reviewer_id` smallint(5) DEFAULT NULL,
  `devision_head` smallint(5) DEFAULT NULL,
  `business_unit_head` smallint(5) DEFAULT NULL,
  `technical_account_manager` smallint(5) DEFAULT NULL,
  `associate_technical_account_manager` smallint(5) DEFAULT NULL,
  `team_lead` smallint(5) DEFAULT NULL,
  `associate_team_ead` smallint(5) DEFAULT NULL,
  `team_member` smallint(5) DEFAULT NULL,
  `reviewer_manager` smallint(5) DEFAULT NULL,
  `reviewer_lead` smallint(5) DEFAULT NULL,
  `reviewer` smallint(5) DEFAULT NULL,
  `completed_on` datetime DEFAULT NULL,
  `completed_by` smallint(5) DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=400240 DEFAULT CHARSET=latin1;  /*Table structure for table `worksheet_status` */  CREATE TABLE `worksheet_status` (
  `id` mediumint(5) NOT NULL AUTO_INCREMENT,
  `status_name` varchar(200) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT NULL,
  `sort_order` mediumint(5) DEFAULT NULL,
  `class_of_color` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=latin1;  /*Table structure for table `worksheet_status_user_right` */  CREATE TABLE `worksheet_status_user_right` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `worksheet_status_id` smallint(3) DEFAULT NULL,
  `user_id` mediumint(5) DEFAULT NULL,
  `right` tinyint(1) DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=61694 DEFAULT CHARSET=utf8;  /* Procedure structure for procedure `get_tab_hierarchy` */  DELIMITER $$  /*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `get_tab_hierarchy`()
BEGIN
WITH RECURSIVE tab_CTE AS ( 
SELECT t.id ,t.tab_name,t.parent_id
FROM tabs t
UNION
SELECT t.id,t.tab_name,t.parent_id
FROM tabs t 
INNER JOIN tab_CTE uhc ON t.id = uhc.parent_id
) 
SELECT *
FROM tab_CTE ORDER BY id;
END */$$ DELIMITER ;  /* Procedure structure for procedure `get_user_hierarchy` */  DELIMITER $$  /*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `get_user_hierarchy`()
BEGIN
WITH RECURSIVE user_hi_CTE AS ( 
SELECT uh.user_id AS id, uh.user_id, uh.parent_user_id, uh.designation_id, 0  AS Seq
FROM user_hierarchy uh
INNER JOIN USER u ON u.id = uh.user_id 
UNION
SELECT uhc.id, u.user_id, u.parent_user_id, u.designation_id, uhc.seq + 1 AS seq
FROM user_hierarchy u 
INNER JOIN user_hi_CTE uhc ON u.user_id = uhc.parent_user_id
) 
SELECT uhc.*,u.userfullname,d.designation_name
FROM user_hi_CTE uhc 
LEFT JOIN `user` u ON u.id = uhc.user_id
LEFT JOIN `designation` d ON d.id = uhc.designation_id
ORDER BY id,Seq;
END */$$ DELIMITER ;  /* Procedure structure for procedure `get_user_tab` */  DELIMITER $$  /*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `get_user_tab`(IN in_user_id BIGINT)
BEGIN
WITH RECURSIVE user_tab_CTE AS ( 
SELECT ur.tab_id AS id ,t.tab_name,t.parent_id,ur.view,ur.add_edit,ur.delete,ur.export,ur.download
FROM tabs t
LEFT JOIN user_tab_right ur ON ur.tab_id = t.id 
WHERE ur.user_id = in_user_id AND (ur.view =1 || ur.add_edit =1)
UNION
SELECT t.id,t.tab_name,t.parent_id,t.view,t.add_edit,t.delete,t.export,t.download
FROM tabs t 
INNER JOIN user_tab_CTE uhc ON t.id = uhc.parent_id
) 
SELECT *
FROM user_tab_CTE ORDER BY id;
END */$$ DELIMITER ;  /*!40101 SET SQL_MODE=@OLD_SQL_MODE */; /*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */; /*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */; /*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */; 
DELIMITER $$

/* Procedure structure for procedure `get_designation_hierarchy` */  DELIMITER $$  /*!50003 CREATE DEFINER=`root`@`localhost` PROCEDURE `get_designation_hierarchy`(IN in_id INT)
BEGIN
WITH RECURSIVE designation_CTE AS ( 
 SELECT d.id ,d.designation_name,d.parent_id
 FROM designation d
 WHERE id = in_id
UNION
 SELECT d.id ,d.designation_name,d.parent_id
 FROM designation d 
 INNER JOIN designation_CTE dc ON d.id = dc.parent_id
) 
SELECT *
FROM designation_CTE;
END */$$ DELIMITER ;  /*!40101 SET SQL_MODE=@OLD_SQL_MODE */; /*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */; /*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */; /*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */; 
DELIMITER $$

