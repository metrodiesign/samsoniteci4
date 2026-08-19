/*M!999999\- enable the sandbox mode */

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `samsonitetracking` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `samsonitetracking`;
DROP TABLE IF EXISTS `book`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `book` (
  `book_id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `book_detail` varchar(3) NOT NULL,
  `status` int(1) NOT NULL,
  `bunber_limit` int(11) DEFAULT NULL,
  `cdate` datetime NOT NULL,
  PRIMARY KEY (`book_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `branch`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `branch` (
  `branch_id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_type` int(11) NOT NULL,
  `branch_user_name` varchar(100) DEFAULT NULL,
  `branch_name` varchar(250) NOT NULL,
  `branch_details` varchar(250) NOT NULL,
  `default_suffix` char(10) NOT NULL,
  `book_order` char(10) NOT NULL,
  `customer_ref` varchar(50) DEFAULT NULL,
  `cdate` datetime NOT NULL,
  `udate` datetime DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`branch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=115 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `branch_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `branch_type` (
  `branch_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_type_details` varchar(250) NOT NULL,
  `branch_type_image` varchar(250) DEFAULT NULL,
  `cdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`branch_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `brand`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `brand` (
  `brand_id` int(11) NOT NULL AUTO_INCREMENT,
  `brand_details` varchar(250) NOT NULL,
  `cdate` datetime NOT NULL,
  PRIMARY KEY (`brand_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ci_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ci_sessions` (
  `session_id` varchar(40) NOT NULL DEFAULT '0',
  `ip_address` varchar(45) NOT NULL DEFAULT '0',
  `user_agent` varchar(120) NOT NULL,
  `last_activity` int(10) unsigned NOT NULL DEFAULT 0,
  `user_data` text NOT NULL,
  PRIMARY KEY (`session_id`),
  KEY `last_activity_idx` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `condition`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `condition` (
  `condition_id` int(11) NOT NULL AUTO_INCREMENT,
  `condition_details` varchar(250) NOT NULL,
  `cdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`condition_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contact`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(250) NOT NULL,
  `email` varchar(100) NOT NULL,
  `samsoniteid` varchar(100) DEFAULT NULL,
  `phone` varchar(100) NOT NULL,
  `detail` text NOT NULL,
  `cdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6162 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `estimateprice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estimateprice` (
  `estimateprice_id` int(11) NOT NULL AUTO_INCREMENT,
  `estimateprice_details` varchar(250) NOT NULL,
  `cdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`estimateprice_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fixed`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fixed` (
  `fixed_id` int(11) NOT NULL AUTO_INCREMENT,
  `fixed_details` varchar(250) NOT NULL,
  `cdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`fixed_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_menu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_type` varchar(250) NOT NULL,
  `name` varchar(250) NOT NULL,
  `cdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_type` (
  `group_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `group_type_name` varchar(250) NOT NULL,
  `icon_menu` varchar(250) DEFAULT NULL,
  `cdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`group_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `provider`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `provider` (
  `provider_id` int(11) NOT NULL AUTO_INCREMENT,
  `provider_name` varchar(250) NOT NULL,
  `provider_tel` char(50) NOT NULL,
  `provider_datail` text NOT NULL,
  `cdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`provider_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rating`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rating` (
  `rating_id` int(11) NOT NULL AUTO_INCREMENT,
  `add_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `order_id` varchar(250) NOT NULL,
  `branchID` int(11) DEFAULT NULL,
  `cdate` datetime NOT NULL,
  PRIMARY KEY (`rating_id`)
) ENGINE=InnoDB AUTO_INCREMENT=75291 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rating_comment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rating_comment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `track_id` varchar(250) NOT NULL,
  `branch_id` int(11) NOT NULL DEFAULT 0,
  `comment` text NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `track_id` (`track_id`,`branch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `request_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `request_order` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `requestDate` datetime NOT NULL,
  `trackID` varchar(100) NOT NULL,
  `bookID` varchar(100) DEFAULT '',
  `numberID` varchar(100) NOT NULL,
  `orderID` varchar(100) NOT NULL,
  `orderIDShow` varchar(100) DEFAULT NULL,
  `warantyType` int(1) DEFAULT NULL,
  `waranty_cmg` varchar(100) DEFAULT '',
  `customerFullname` varchar(250) DEFAULT NULL,
  `customerTel` varchar(100) DEFAULT NULL,
  `customerEmail` varchar(100) DEFAULT NULL,
  `detailAgent` int(1) DEFAULT NULL,
  `detailSKUName` varchar(100) DEFAULT NULL,
  `detailTypeId` int(11) DEFAULT NULL,
  `detailBrandId` int(11) DEFAULT NULL,
  `detailNumberWaranty` varchar(100) DEFAULT NULL,
  `detailDatePurchase` datetime DEFAULT '0000-00-00 00:00:00',
  `detailCondition` varchar(250) DEFAULT NULL,
  `detailConditionOther` varchar(250) DEFAULT NULL,
  `detailEstimatePrice` varchar(250) DEFAULT NULL,
  `detailEstimatePriceOther` varchar(250) DEFAULT NULL,
  `detailFixed` varchar(250) DEFAULT NULL,
  `detailFixedOther` varchar(250) DEFAULT NULL,
  `detailEquipment` text DEFAULT NULL,
  `detailNote` text DEFAULT NULL,
  `customerTel2` varchar(100) DEFAULT NULL,
  `branchID` int(11) DEFAULT NULL,
  `branch_type_id` int(11) DEFAULT NULL,
  `UserID` int(11) DEFAULT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `logistics_etc_detail` text DEFAULT NULL,
  `detailImage` varchar(500) DEFAULT NULL,
  `date_create` datetime DEFAULT NULL,
  `date_repair` datetime DEFAULT NULL,
  `date_repair_complete` datetime DEFAULT NULL,
  `date_repair_waranty` datetime DEFAULT NULL,
  `date_update_status` datetime DEFAULT NULL,
  `date_deliver` datetime DEFAULT NULL,
  `date_complete` datetime DEFAULT NULL,
  `action_status` int(11) DEFAULT NULL,
  `customer_noti` int(1) DEFAULT NULL,
  `uploadTextnew` text DEFAULT NULL,
  `RepairPrice` decimal(8,2) DEFAULT NULL,
  `number_cmg` varchar(100) DEFAULT NULL,
  `create_by_user` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `trackID` (`trackID`)
) ENGINE=InnoDB AUTO_INCREMENT=107771 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `request_order_delete`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `request_order_delete` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `requestDate` datetime NOT NULL,
  `trackID` varchar(100) NOT NULL,
  `bookID` varchar(100) DEFAULT '',
  `numberID` varchar(100) NOT NULL,
  `orderID` varchar(100) NOT NULL,
  `orderIDShow` varchar(100) DEFAULT NULL,
  `warantyType` int(1) DEFAULT NULL,
  `waranty_cmg` varchar(100) DEFAULT '',
  `customerFullname` varchar(250) DEFAULT NULL,
  `customerTel` varchar(100) DEFAULT NULL,
  `customerEmail` varchar(100) DEFAULT NULL,
  `detailAgent` int(1) DEFAULT NULL,
  `detailSKUName` varchar(100) DEFAULT NULL,
  `detailTypeId` int(11) DEFAULT NULL,
  `detailBrandId` int(11) DEFAULT NULL,
  `detailNumberWaranty` varchar(100) DEFAULT NULL,
  `detailDatePurchase` datetime DEFAULT NULL,
  `detailCondition` varchar(250) DEFAULT NULL,
  `detailConditionOther` varchar(250) DEFAULT NULL,
  `detailEstimatePrice` varchar(250) DEFAULT NULL,
  `detailEstimatePriceOther` varchar(250) DEFAULT NULL,
  `detailFixed` varchar(250) DEFAULT NULL,
  `detailFixedOther` varchar(250) DEFAULT NULL,
  `detailEquipment` text DEFAULT NULL,
  `detailNote` text DEFAULT NULL,
  `customerTel2` varchar(100) DEFAULT NULL,
  `branchID` int(11) DEFAULT NULL,
  `branch_type_id` int(11) DEFAULT NULL,
  `UserID` int(11) DEFAULT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `logistics_etc_detail` text DEFAULT NULL,
  `detailImage` varchar(500) DEFAULT NULL,
  `date_create` datetime DEFAULT NULL,
  `date_repair` datetime DEFAULT NULL,
  `date_repair_complete` datetime DEFAULT NULL,
  `date_repair_waranty` datetime DEFAULT NULL,
  `date_update_status` datetime DEFAULT NULL,
  `date_deliver` datetime DEFAULT NULL,
  `date_complete` datetime DEFAULT NULL,
  `action_status` int(11) DEFAULT NULL,
  `customer_noti` int(1) DEFAULT NULL,
  `uploadTextnew` text DEFAULT NULL,
  `RepairPrice` decimal(8,2) DEFAULT NULL,
  `number_cmg` varchar(100) NOT NULL,
  `create_by_user` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`request_id`)
) ENGINE=InnoDB AUTO_INCREMENT=49720 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `status_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `status_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` varchar(100) NOT NULL,
  `action_id` int(11) DEFAULT NULL,
  `update_id` int(11) DEFAULT NULL,
  `cdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=751269 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `statusaction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `statusaction` (
  `status_id` int(11) NOT NULL AUTO_INCREMENT,
  `status_name` varchar(250) NOT NULL,
  `status_name_th` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_background_web`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_background_web` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `image_track_laptop` varchar(250) DEFAULT NULL,
  `image_track_mobile` varchar(250) DEFAULT NULL,
  `image_trackstatus_laptop` varchar(250) DEFAULT NULL,
  `image_trackstatus_mobile` varchar(250) DEFAULT NULL,
  `image_contact_laptop` varchar(250) DEFAULT NULL,
  `image_contact_mobile` varchar(250) DEFAULT NULL,
  `image_track_laptop_th` varchar(250) DEFAULT NULL,
  `image_track_mobile_th` varchar(250) DEFAULT NULL,
  `image_trackstatus_laptop_th` varchar(250) DEFAULT NULL,
  `image_trackstatus_mobile_th` varchar(250) DEFAULT NULL,
  `image_contact_laptop_th` varchar(250) DEFAULT NULL,
  `image_contact_mobile_th` varchar(250) DEFAULT NULL,
  `status` int(1) DEFAULT NULL,
  `date` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_last_login`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_last_login` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `userId` bigint(20) NOT NULL,
  `sessionData` varchar(2048) NOT NULL,
  `machineIp` varchar(1024) NOT NULL,
  `userAgent` varchar(128) NOT NULL,
  `agentString` varchar(1024) NOT NULL,
  `platform` varchar(128) NOT NULL,
  `createdDtm` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=117379 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_menu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_name` varchar(250) NOT NULL,
  `menu_link` varchar(250) NOT NULL,
  `group_type` int(11) NOT NULL,
  `cdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_reset_password`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_reset_password` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `email` varchar(128) NOT NULL,
  `activation_id` varchar(32) NOT NULL,
  `agent` varchar(512) NOT NULL,
  `client_ip` varchar(32) NOT NULL,
  `isDeleted` tinyint(4) NOT NULL DEFAULT 0,
  `createdBy` bigint(20) NOT NULL DEFAULT 1,
  `createdDtm` datetime NOT NULL,
  `updatedBy` bigint(20) DEFAULT NULL,
  `updatedDtm` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_roles` (
  `roleId` tinyint(4) NOT NULL AUTO_INCREMENT COMMENT 'role id',
  `role` varchar(50) NOT NULL COMMENT 'role text',
  PRIMARY KEY (`roleId`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_users` (
  `userId` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(128) NOT NULL COMMENT 'login email',
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(128) NOT NULL COMMENT 'hashed login password',
  `name` varchar(128) DEFAULT NULL COMMENT 'full name of user',
  `mobile` varchar(20) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `roleId` tinyint(4) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `branch_type_id` int(11) DEFAULT NULL,
  `isDeleted` tinyint(4) NOT NULL DEFAULT 0,
  `createdBy` int(11) DEFAULT NULL,
  `createdDtm` datetime NOT NULL,
  `updatedBy` int(11) DEFAULT NULL,
  `updatedDtm` datetime DEFAULT NULL,
  PRIMARY KEY (`userId`)
) ENGINE=InnoDB AUTO_INCREMENT=215 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `temp_status_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `temp_status_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` varchar(100) NOT NULL,
  `action_id` int(11) DEFAULT NULL,
  `update_id` int(11) DEFAULT NULL,
  `cdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2802459 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `temp_updatestatus_neworder`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `temp_updatestatus_neworder` (
  `temp_request_id` int(11) NOT NULL AUTO_INCREMENT,
  `temp_trackID` varchar(100) DEFAULT '',
  `temp_orderID` varchar(100) NOT NULL,
  `temp_orderIDShow` varchar(100) DEFAULT NULL,
  `temp_customerFullname` varchar(250) DEFAULT NULL,
  `temp_customerTel` varchar(100) DEFAULT NULL,
  `temp_Status` varchar(100) DEFAULT NULL,
  `temp_Update` varchar(100) DEFAULT NULL,
  `temp_recripUpdate` varchar(100) DEFAULT NULL,
  `temp_pic` varchar(100) DEFAULT NULL,
  `temp_waranty_cmg` varchar(100) DEFAULT NULL,
  `seq` int(11) NOT NULL,
  PRIMARY KEY (`temp_request_id`)
) ENGINE=InnoDB AUTO_INCREMENT=37173 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `temp_updatestatus_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `temp_updatestatus_order` (
  `temp_request_id` int(11) NOT NULL AUTO_INCREMENT,
  `temp_trackID` varchar(100) DEFAULT '',
  `temp_orderID` varchar(100) NOT NULL,
  `temp_orderIDShow` varchar(100) DEFAULT NULL,
  `temp_customerFullname` varchar(250) DEFAULT NULL,
  `temp_customerTel` varchar(100) DEFAULT NULL,
  `temp_Status` varchar(100) DEFAULT NULL,
  `temp_Update` varchar(100) DEFAULT NULL,
  `temp_recripUpdate` varchar(100) DEFAULT NULL,
  `temp_pic` varchar(100) DEFAULT NULL,
  `temp_waranty_cmg` varchar(100) DEFAULT NULL,
  `temp_number_cmg` varchar(100) DEFAULT NULL,
  `seq` int(11) NOT NULL,
  PRIMARY KEY (`temp_request_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1795342 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `temp_updatestatus_price_order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `temp_updatestatus_price_order` (
  `temp_request_id` int(11) NOT NULL AUTO_INCREMENT,
  `temp_trackID` varchar(100) DEFAULT '',
  `temp_orderID` varchar(100) NOT NULL,
  `temp_orderIDShow` varchar(100) DEFAULT NULL,
  `temp_customerFullname` varchar(250) DEFAULT NULL,
  `temp_customerTel` varchar(100) DEFAULT NULL,
  `temp_Status` varchar(100) DEFAULT NULL,
  `temp_Update` varchar(100) DEFAULT NULL,
  `temp_recripUpdate` varchar(100) DEFAULT NULL,
  `temp_pic` varchar(100) DEFAULT NULL,
  `temp_waranty_cmg` varchar(100) DEFAULT NULL,
  `temp_number_cmg` varchar(100) DEFAULT NULL,
  `seq` int(11) NOT NULL,
  PRIMARY KEY (`temp_request_id`)
) ENGINE=InnoDB AUTO_INCREMENT=715 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tracking_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracking_status` (
  `status_id` int(11) NOT NULL AUTO_INCREMENT,
  `description_th` varchar(250) NOT NULL,
  `description_en` varchar(250) NOT NULL,
  `success` int(2) DEFAULT NULL,
  `cdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `type` (
  `type_id` int(11) NOT NULL AUTO_INCREMENT,
  `type_details` varchar(250) NOT NULL,
  `cdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `uploadstaus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `uploadstaus` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tracking_id` varchar(250) NOT NULL,
  `customerRef` varchar(100) DEFAULT '',
  `bookID` varchar(100) DEFAULT '',
  `Listname` varchar(250) NOT NULL,
  `Telephone` varchar(100) NOT NULL,
  `updatetime` varchar(100) NOT NULL,
  `startdate` varchar(250) NOT NULL,
  `Userstatus` varchar(100) NOT NULL,
  `tracking_status` int(11) NOT NULL,
  `cdate` date NOT NULL,
  `user_id` int(11) DEFAULT 0,
  `RepairPrice` decimal(8,2) DEFAULT 0.00,
  `waranty_cmg` varchar(100) DEFAULT NULL,
  `number_cmg` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tracking_id` (`tracking_id`),
  KEY `Telephone` (`Telephone`)
) ENGINE=InnoDB AUTO_INCREMENT=478135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;
