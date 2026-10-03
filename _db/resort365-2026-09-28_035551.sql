/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-12.1.2-MariaDB, for osx10.19 (x86_64)
--
-- Host: localhost    Database: resort365
-- ------------------------------------------------------
-- Server version	12.1.2-MariaDB

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

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `log_name` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `event` varchar(255) DEFAULT NULL,
  `causer_type` varchar(255) DEFAULT NULL,
  `causer_id` bigint(20) unsigned DEFAULT NULL,
  `attribute_changes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attribute_changes`)),
  `properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`properties`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  KEY `activity_log_tenant_id_subject_type_subject_id_index` (`tenant_id`,`subject_type`,`subject_id`),
  KEY `activity_log_log_name_index` (`log_name`),
  CONSTRAINT `activity_log_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=87 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_log`
--

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `activity_log` VALUES
(1,1,'role','Role created','Modules\\IAM\\Models\\Role',1,'created',NULL,NULL,'{\"attributes\":{\"name\":\"tenant-owner\",\"guard_name\":\"web\",\"description\":\"Everything in the tenant, including subscription and billing.\",\"is_system\":true}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(2,1,'role','Role created','Modules\\IAM\\Models\\Role',2,'created',NULL,NULL,'{\"attributes\":{\"name\":\"general-manager\",\"guard_name\":\"web\",\"description\":\"All operational and financial modules for assigned properties; approvals.\",\"is_system\":true}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(3,1,'role','Role created','Modules\\IAM\\Models\\Role',3,'created',NULL,NULL,'{\"attributes\":{\"name\":\"front-office-manager\",\"guard_name\":\"web\",\"description\":\"Reservations, front office, guests, folios, rates (view), reports.\",\"is_system\":true}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(4,1,'role','Role created','Modules\\IAM\\Models\\Role',4,'created',NULL,NULL,'{\"attributes\":{\"name\":\"front-desk-agent\",\"guard_name\":\"web\",\"description\":\"Create and modify reservations, check-in\\/out, take payments.\",\"is_system\":true}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(5,1,'role','Role created','Modules\\IAM\\Models\\Role',5,'created',NULL,NULL,'{\"attributes\":{\"name\":\"reservation-agent\",\"guard_name\":\"web\",\"description\":\"Create and modify reservations, quotes, deposits.\",\"is_system\":true}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(6,1,'role','Role created','Modules\\IAM\\Models\\Role',6,'created',NULL,NULL,'{\"attributes\":{\"name\":\"housekeeping-supervisor\",\"guard_name\":\"web\",\"description\":\"Room status, housekeeping tasks, lost & found.\",\"is_system\":true}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(7,1,'role','Role created','Modules\\IAM\\Models\\Role',7,'created',NULL,NULL,'{\"attributes\":{\"name\":\"maintenance-technician\",\"guard_name\":\"web\",\"description\":\"Work orders assigned to them.\",\"is_system\":true}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(8,1,'role','Role created','Modules\\IAM\\Models\\Role',8,'created',NULL,NULL,'{\"attributes\":{\"name\":\"fnb-manager\",\"guard_name\":\"web\",\"description\":\"Everything in the Restaurant module for assigned outlets.\",\"is_system\":true}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(9,1,'role','Role created','Modules\\IAM\\Models\\Role',9,'created',NULL,NULL,'{\"attributes\":{\"name\":\"outlet-cashier\",\"guard_name\":\"web\",\"description\":\"POS sessions, settling bills, payments, charge to room.\",\"is_system\":true}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(10,1,'role','Role created','Modules\\IAM\\Models\\Role',10,'created',NULL,NULL,'{\"attributes\":{\"name\":\"waiter\",\"guard_name\":\"web\",\"description\":\"Tables, orders, kitchen tickets and bills.\",\"is_system\":true}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(11,1,'role','Role created','Modules\\IAM\\Models\\Role',11,'created',NULL,NULL,'{\"attributes\":{\"name\":\"chef\",\"guard_name\":\"web\",\"description\":\"Kitchen display and wastage.\",\"is_system\":true}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(12,1,'role','Role created','Modules\\IAM\\Models\\Role',12,'created',NULL,NULL,'{\"attributes\":{\"name\":\"bartender\",\"guard_name\":\"web\",\"description\":\"Bar display and bar orders.\",\"is_system\":true}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(13,1,'role','Role created','Modules\\IAM\\Models\\Role',13,'created',NULL,NULL,'{\"attributes\":{\"name\":\"store-keeper\",\"guard_name\":\"web\",\"description\":\"Inventory: receive, issue, transfer, stock count.\",\"is_system\":true}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(14,1,'role','Role created','Modules\\IAM\\Models\\Role',14,'created',NULL,NULL,'{\"attributes\":{\"name\":\"procurement-officer\",\"guard_name\":\"web\",\"description\":\"Requisitions, RFQs, purchase orders, vendors.\",\"is_system\":true}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(15,1,'role','Role created','Modules\\IAM\\Models\\Role',15,'created',NULL,NULL,'{\"attributes\":{\"name\":\"accountant\",\"guard_name\":\"web\",\"description\":\"Accounting, expenses, vendor bills, payments, bank reconciliation.\",\"is_system\":true}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(16,1,'role','Role created','Modules\\IAM\\Models\\Role',16,'created',NULL,NULL,'{\"attributes\":{\"name\":\"hr-manager\",\"guard_name\":\"web\",\"description\":\"Employees, attendance, leave, shifts.\",\"is_system\":true}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(17,1,'role','Role created','Modules\\IAM\\Models\\Role',17,'created',NULL,NULL,'{\"attributes\":{\"name\":\"payroll-officer\",\"guard_name\":\"web\",\"description\":\"Payroll runs, payslips, loans.\",\"is_system\":true}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(18,1,'role','Role created','Modules\\IAM\\Models\\Role',18,'created',NULL,NULL,'{\"attributes\":{\"name\":\"auditor\",\"guard_name\":\"web\",\"description\":\"Read-only access to everything, including the audit log.\",\"is_system\":true}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(19,1,'document_sequence','Document Sequence created','Modules\\Core\\Models\\DocumentSequence',1,'created',NULL,NULL,'{\"attributes\":{\"property_id\":null,\"document_type\":\"reservation\",\"prefix\":\"RSV\",\"format\":\"{PREFIX}-{YYYY}-{SEQ:5}\",\"next_number\":1,\"reset\":\"yearly\",\"period\":null}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(20,1,'document_sequence','Document Sequence created','Modules\\Core\\Models\\DocumentSequence',2,'created',NULL,NULL,'{\"attributes\":{\"property_id\":null,\"document_type\":\"invoice\",\"prefix\":\"INV\",\"format\":\"{PREFIX}-{YYYY}-{SEQ:5}\",\"next_number\":1,\"reset\":\"yearly\",\"period\":null}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(21,1,'document_sequence','Document Sequence created','Modules\\Core\\Models\\DocumentSequence',3,'created',NULL,NULL,'{\"attributes\":{\"property_id\":null,\"document_type\":\"purchase_order\",\"prefix\":\"PO\",\"format\":\"{PREFIX}-{YYYY}-{SEQ:5}\",\"next_number\":1,\"reset\":\"yearly\",\"period\":null}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(22,1,'document_sequence','Document Sequence created','Modules\\Core\\Models\\DocumentSequence',4,'created',NULL,NULL,'{\"attributes\":{\"property_id\":null,\"document_type\":\"goods_receipt\",\"prefix\":\"GRN\",\"format\":\"{PREFIX}-{YYYY}-{SEQ:5}\",\"next_number\":1,\"reset\":\"yearly\",\"period\":null}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(23,1,'document_sequence','Document Sequence created','Modules\\Core\\Models\\DocumentSequence',5,'created',NULL,NULL,'{\"attributes\":{\"property_id\":null,\"document_type\":\"journal\",\"prefix\":\"JV\",\"format\":\"{PREFIX}-{YYYY}-{SEQ:5}\",\"next_number\":1,\"reset\":\"yearly\",\"period\":null}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(24,1,'document_sequence','Document Sequence created','Modules\\Core\\Models\\DocumentSequence',6,'created',NULL,NULL,'{\"attributes\":{\"property_id\":null,\"document_type\":\"payment\",\"prefix\":\"PAY\",\"format\":\"{PREFIX}-{YYYY}-{SEQ:5}\",\"next_number\":1,\"reset\":\"yearly\",\"period\":null}}','[]','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(25,1,'user','User created','Modules\\IAM\\Models\\User',1,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Rahim Uddin\",\"email\":\"owner@sunrise.test\",\"email_verified_at\":\"2026-09-27T17:24:55.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:24:55','2026-09-27 11:24:55'),
(26,1,'user','User created','Modules\\IAM\\Models\\User',2,'created',NULL,NULL,'{\"attributes\":{\"name\":\"General Manager\",\"email\":\"gm@sunrise.test\",\"email_verified_at\":\"2026-09-27T17:24:55.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:24:55','2026-09-27 11:24:55'),
(27,1,'user','User created','Modules\\IAM\\Models\\User',3,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Front Office Manager\",\"email\":\"fomanager@sunrise.test\",\"email_verified_at\":\"2026-09-27T17:24:55.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:24:55','2026-09-27 11:24:55'),
(28,1,'user','User created','Modules\\IAM\\Models\\User',4,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Nusrat Jahan\",\"email\":\"frontdesk@sunrise.test\",\"email_verified_at\":\"2026-09-27T17:24:55.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:24:55','2026-09-27 11:24:55'),
(29,1,'user','User created','Modules\\IAM\\Models\\User',5,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Reservation Agent\",\"email\":\"reservations@sunrise.test\",\"email_verified_at\":\"2026-09-27T17:24:56.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:24:56','2026-09-27 11:24:56'),
(30,1,'user','User created','Modules\\IAM\\Models\\User',6,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Housekeeping Supervisor\",\"email\":\"housekeeping@sunrise.test\",\"email_verified_at\":\"2026-09-27T17:24:56.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:24:56','2026-09-27 11:24:56'),
(31,1,'user','User created','Modules\\IAM\\Models\\User',7,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Maintenance Technician\",\"email\":\"maintenance@sunrise.test\",\"email_verified_at\":\"2026-09-27T17:24:56.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:24:56','2026-09-27 11:24:56'),
(32,1,'user','User created','Modules\\IAM\\Models\\User',8,'created',NULL,NULL,'{\"attributes\":{\"name\":\"F&B Manager\",\"email\":\"fnb@sunrise.test\",\"email_verified_at\":\"2026-09-27T17:24:56.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:24:56','2026-09-27 11:24:56'),
(33,1,'user','User created','Modules\\IAM\\Models\\User',9,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Outlet Cashier\",\"email\":\"cashier@sunrise.test\",\"email_verified_at\":\"2026-09-27T17:24:57.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:24:57','2026-09-27 11:24:57'),
(34,1,'user','User created','Modules\\IAM\\Models\\User',10,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Waiter \\/ Captain\",\"email\":\"waiter@sunrise.test\",\"email_verified_at\":\"2026-09-27T17:24:57.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:24:57','2026-09-27 11:24:57'),
(35,1,'user','User created','Modules\\IAM\\Models\\User',11,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Chef \\/ Kitchen Staff\",\"email\":\"chef@sunrise.test\",\"email_verified_at\":\"2026-09-27T17:24:57.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:24:57','2026-09-27 11:24:57'),
(36,1,'user','User created','Modules\\IAM\\Models\\User',12,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Bartender\",\"email\":\"bartender@sunrise.test\",\"email_verified_at\":\"2026-09-27T17:24:57.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:24:57','2026-09-27 11:24:57'),
(37,1,'user','User created','Modules\\IAM\\Models\\User',13,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Store Keeper\",\"email\":\"store@sunrise.test\",\"email_verified_at\":\"2026-09-27T17:24:58.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:24:58','2026-09-27 11:24:58'),
(38,1,'user','User created','Modules\\IAM\\Models\\User',14,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Procurement Officer\",\"email\":\"procurement@sunrise.test\",\"email_verified_at\":\"2026-09-27T17:24:58.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:24:58','2026-09-27 11:24:58'),
(39,1,'user','User created','Modules\\IAM\\Models\\User',15,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Farzana Akter\",\"email\":\"accountant@sunrise.test\",\"email_verified_at\":\"2026-09-27T17:24:58.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:24:58','2026-09-27 11:24:58'),
(40,1,'user','User created','Modules\\IAM\\Models\\User',16,'created',NULL,NULL,'{\"attributes\":{\"name\":\"HR Manager\",\"email\":\"hr@sunrise.test\",\"email_verified_at\":\"2026-09-27T17:24:58.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:24:58','2026-09-27 11:24:58'),
(41,1,'user','User created','Modules\\IAM\\Models\\User',17,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Payroll Officer\",\"email\":\"payroll@sunrise.test\",\"email_verified_at\":\"2026-09-27T17:24:59.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(42,1,'user','User created','Modules\\IAM\\Models\\User',18,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Auditor\",\"email\":\"auditor@sunrise.test\",\"email_verified_at\":\"2026-09-27T17:24:59.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(43,1,'exchange_rate','Exchange Rate created','Modules\\Core\\Models\\ExchangeRate',1,'created',NULL,NULL,'{\"attributes\":{\"base_currency\":\"USD\",\"quote_currency\":\"BDT\",\"rate\":\"122.00000000\",\"effective_date\":\"2026-01-01T00:00:00.000000Z\",\"source\":\"demo\"}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(44,2,'role','Role created','Modules\\IAM\\Models\\Role',19,'created',NULL,NULL,'{\"attributes\":{\"name\":\"tenant-owner\",\"guard_name\":\"web\",\"description\":\"Everything in the tenant, including subscription and billing.\",\"is_system\":true}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(45,2,'role','Role created','Modules\\IAM\\Models\\Role',20,'created',NULL,NULL,'{\"attributes\":{\"name\":\"general-manager\",\"guard_name\":\"web\",\"description\":\"All operational and financial modules for assigned properties; approvals.\",\"is_system\":true}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(46,2,'role','Role created','Modules\\IAM\\Models\\Role',21,'created',NULL,NULL,'{\"attributes\":{\"name\":\"front-office-manager\",\"guard_name\":\"web\",\"description\":\"Reservations, front office, guests, folios, rates (view), reports.\",\"is_system\":true}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(47,2,'role','Role created','Modules\\IAM\\Models\\Role',22,'created',NULL,NULL,'{\"attributes\":{\"name\":\"front-desk-agent\",\"guard_name\":\"web\",\"description\":\"Create and modify reservations, check-in\\/out, take payments.\",\"is_system\":true}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(48,2,'role','Role created','Modules\\IAM\\Models\\Role',23,'created',NULL,NULL,'{\"attributes\":{\"name\":\"reservation-agent\",\"guard_name\":\"web\",\"description\":\"Create and modify reservations, quotes, deposits.\",\"is_system\":true}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(49,2,'role','Role created','Modules\\IAM\\Models\\Role',24,'created',NULL,NULL,'{\"attributes\":{\"name\":\"housekeeping-supervisor\",\"guard_name\":\"web\",\"description\":\"Room status, housekeeping tasks, lost & found.\",\"is_system\":true}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(50,2,'role','Role created','Modules\\IAM\\Models\\Role',25,'created',NULL,NULL,'{\"attributes\":{\"name\":\"maintenance-technician\",\"guard_name\":\"web\",\"description\":\"Work orders assigned to them.\",\"is_system\":true}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(51,2,'role','Role created','Modules\\IAM\\Models\\Role',26,'created',NULL,NULL,'{\"attributes\":{\"name\":\"fnb-manager\",\"guard_name\":\"web\",\"description\":\"Everything in the Restaurant module for assigned outlets.\",\"is_system\":true}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(52,2,'role','Role created','Modules\\IAM\\Models\\Role',27,'created',NULL,NULL,'{\"attributes\":{\"name\":\"outlet-cashier\",\"guard_name\":\"web\",\"description\":\"POS sessions, settling bills, payments, charge to room.\",\"is_system\":true}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(53,2,'role','Role created','Modules\\IAM\\Models\\Role',28,'created',NULL,NULL,'{\"attributes\":{\"name\":\"waiter\",\"guard_name\":\"web\",\"description\":\"Tables, orders, kitchen tickets and bills.\",\"is_system\":true}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(54,2,'role','Role created','Modules\\IAM\\Models\\Role',29,'created',NULL,NULL,'{\"attributes\":{\"name\":\"chef\",\"guard_name\":\"web\",\"description\":\"Kitchen display and wastage.\",\"is_system\":true}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(55,2,'role','Role created','Modules\\IAM\\Models\\Role',30,'created',NULL,NULL,'{\"attributes\":{\"name\":\"bartender\",\"guard_name\":\"web\",\"description\":\"Bar display and bar orders.\",\"is_system\":true}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(56,2,'role','Role created','Modules\\IAM\\Models\\Role',31,'created',NULL,NULL,'{\"attributes\":{\"name\":\"store-keeper\",\"guard_name\":\"web\",\"description\":\"Inventory: receive, issue, transfer, stock count.\",\"is_system\":true}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(57,2,'role','Role created','Modules\\IAM\\Models\\Role',32,'created',NULL,NULL,'{\"attributes\":{\"name\":\"procurement-officer\",\"guard_name\":\"web\",\"description\":\"Requisitions, RFQs, purchase orders, vendors.\",\"is_system\":true}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(58,2,'role','Role created','Modules\\IAM\\Models\\Role',33,'created',NULL,NULL,'{\"attributes\":{\"name\":\"accountant\",\"guard_name\":\"web\",\"description\":\"Accounting, expenses, vendor bills, payments, bank reconciliation.\",\"is_system\":true}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(59,2,'role','Role created','Modules\\IAM\\Models\\Role',34,'created',NULL,NULL,'{\"attributes\":{\"name\":\"hr-manager\",\"guard_name\":\"web\",\"description\":\"Employees, attendance, leave, shifts.\",\"is_system\":true}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(60,2,'role','Role created','Modules\\IAM\\Models\\Role',35,'created',NULL,NULL,'{\"attributes\":{\"name\":\"payroll-officer\",\"guard_name\":\"web\",\"description\":\"Payroll runs, payslips, loans.\",\"is_system\":true}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(61,2,'role','Role created','Modules\\IAM\\Models\\Role',36,'created',NULL,NULL,'{\"attributes\":{\"name\":\"auditor\",\"guard_name\":\"web\",\"description\":\"Read-only access to everything, including the audit log.\",\"is_system\":true}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(62,2,'document_sequence','Document Sequence created','Modules\\Core\\Models\\DocumentSequence',7,'created',NULL,NULL,'{\"attributes\":{\"property_id\":null,\"document_type\":\"reservation\",\"prefix\":\"RSV\",\"format\":\"{PREFIX}-{YYYY}-{SEQ:5}\",\"next_number\":1,\"reset\":\"yearly\",\"period\":null}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(63,2,'document_sequence','Document Sequence created','Modules\\Core\\Models\\DocumentSequence',8,'created',NULL,NULL,'{\"attributes\":{\"property_id\":null,\"document_type\":\"invoice\",\"prefix\":\"INV\",\"format\":\"{PREFIX}-{YYYY}-{SEQ:5}\",\"next_number\":1,\"reset\":\"yearly\",\"period\":null}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(64,2,'document_sequence','Document Sequence created','Modules\\Core\\Models\\DocumentSequence',9,'created',NULL,NULL,'{\"attributes\":{\"property_id\":null,\"document_type\":\"purchase_order\",\"prefix\":\"PO\",\"format\":\"{PREFIX}-{YYYY}-{SEQ:5}\",\"next_number\":1,\"reset\":\"yearly\",\"period\":null}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(65,2,'document_sequence','Document Sequence created','Modules\\Core\\Models\\DocumentSequence',10,'created',NULL,NULL,'{\"attributes\":{\"property_id\":null,\"document_type\":\"goods_receipt\",\"prefix\":\"GRN\",\"format\":\"{PREFIX}-{YYYY}-{SEQ:5}\",\"next_number\":1,\"reset\":\"yearly\",\"period\":null}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(66,2,'document_sequence','Document Sequence created','Modules\\Core\\Models\\DocumentSequence',11,'created',NULL,NULL,'{\"attributes\":{\"property_id\":null,\"document_type\":\"journal\",\"prefix\":\"JV\",\"format\":\"{PREFIX}-{YYYY}-{SEQ:5}\",\"next_number\":1,\"reset\":\"yearly\",\"period\":null}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(67,2,'document_sequence','Document Sequence created','Modules\\Core\\Models\\DocumentSequence',12,'created',NULL,NULL,'{\"attributes\":{\"property_id\":null,\"document_type\":\"payment\",\"prefix\":\"PAY\",\"format\":\"{PREFIX}-{YYYY}-{SEQ:5}\",\"next_number\":1,\"reset\":\"yearly\",\"period\":null}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(68,2,'user','User created','Modules\\IAM\\Models\\User',19,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Tanvir Ahmed\",\"email\":\"owner@greenvalley.test\",\"email_verified_at\":\"2026-09-27T17:24:59.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(69,2,'user','User created','Modules\\IAM\\Models\\User',20,'created',NULL,NULL,'{\"attributes\":{\"name\":\"General Manager\",\"email\":\"gm@greenvalley.test\",\"email_verified_at\":\"2026-09-27T17:25:00.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:25:00','2026-09-27 11:25:00'),
(70,2,'user','User created','Modules\\IAM\\Models\\User',21,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Front Office Manager\",\"email\":\"fomanager@greenvalley.test\",\"email_verified_at\":\"2026-09-27T17:25:00.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:25:00','2026-09-27 11:25:00'),
(71,2,'user','User created','Modules\\IAM\\Models\\User',22,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Front Desk Agent\",\"email\":\"frontdesk@greenvalley.test\",\"email_verified_at\":\"2026-09-27T17:25:00.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:25:00','2026-09-27 11:25:00'),
(72,2,'user','User created','Modules\\IAM\\Models\\User',23,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Reservation Agent\",\"email\":\"reservations@greenvalley.test\",\"email_verified_at\":\"2026-09-27T17:25:00.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:25:00','2026-09-27 11:25:00'),
(73,2,'user','User created','Modules\\IAM\\Models\\User',24,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Housekeeping Supervisor\",\"email\":\"housekeeping@greenvalley.test\",\"email_verified_at\":\"2026-09-27T17:25:01.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:25:01','2026-09-27 11:25:01'),
(74,2,'user','User created','Modules\\IAM\\Models\\User',25,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Maintenance Technician\",\"email\":\"maintenance@greenvalley.test\",\"email_verified_at\":\"2026-09-27T17:25:01.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:25:01','2026-09-27 11:25:01'),
(75,2,'user','User created','Modules\\IAM\\Models\\User',26,'created',NULL,NULL,'{\"attributes\":{\"name\":\"F&B Manager\",\"email\":\"fnb@greenvalley.test\",\"email_verified_at\":\"2026-09-27T17:25:01.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:25:01','2026-09-27 11:25:01'),
(76,2,'user','User created','Modules\\IAM\\Models\\User',27,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Outlet Cashier\",\"email\":\"cashier@greenvalley.test\",\"email_verified_at\":\"2026-09-27T17:25:01.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:25:01','2026-09-27 11:25:01'),
(77,2,'user','User created','Modules\\IAM\\Models\\User',28,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Waiter \\/ Captain\",\"email\":\"waiter@greenvalley.test\",\"email_verified_at\":\"2026-09-27T17:25:02.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:25:02','2026-09-27 11:25:02'),
(78,2,'user','User created','Modules\\IAM\\Models\\User',29,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Chef \\/ Kitchen Staff\",\"email\":\"chef@greenvalley.test\",\"email_verified_at\":\"2026-09-27T17:25:02.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:25:02','2026-09-27 11:25:02'),
(79,2,'user','User created','Modules\\IAM\\Models\\User',30,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Bartender\",\"email\":\"bartender@greenvalley.test\",\"email_verified_at\":\"2026-09-27T17:25:02.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:25:02','2026-09-27 11:25:02'),
(80,2,'user','User created','Modules\\IAM\\Models\\User',31,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Store Keeper\",\"email\":\"store@greenvalley.test\",\"email_verified_at\":\"2026-09-27T17:25:02.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:25:02','2026-09-27 11:25:02'),
(81,2,'user','User created','Modules\\IAM\\Models\\User',32,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Procurement Officer\",\"email\":\"procurement@greenvalley.test\",\"email_verified_at\":\"2026-09-27T17:25:03.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:25:03','2026-09-27 11:25:03'),
(82,2,'user','User created','Modules\\IAM\\Models\\User',33,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Accountant\",\"email\":\"accountant@greenvalley.test\",\"email_verified_at\":\"2026-09-27T17:25:03.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:25:03','2026-09-27 11:25:03'),
(83,2,'user','User created','Modules\\IAM\\Models\\User',34,'created',NULL,NULL,'{\"attributes\":{\"name\":\"HR Manager\",\"email\":\"hr@greenvalley.test\",\"email_verified_at\":\"2026-09-27T17:25:03.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:25:03','2026-09-27 11:25:03'),
(84,2,'user','User created','Modules\\IAM\\Models\\User',35,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Payroll Officer\",\"email\":\"payroll@greenvalley.test\",\"email_verified_at\":\"2026-09-27T17:25:03.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:25:03','2026-09-27 11:25:03'),
(85,2,'user','User created','Modules\\IAM\\Models\\User',36,'created',NULL,NULL,'{\"attributes\":{\"name\":\"Auditor\",\"email\":\"auditor@greenvalley.test\",\"email_verified_at\":\"2026-09-27T17:25:04.000000Z\",\"status\":\"active\",\"locale\":\"en\",\"theme\":null,\"two_factor_confirmed_at\":null,\"invited_at\":null,\"invited_by\":null,\"deactivated_at\":null}}','[]','2026-09-27 11:25:04','2026-09-27 11:25:04'),
(86,2,'exchange_rate','Exchange Rate created','Modules\\Core\\Models\\ExchangeRate',2,'created',NULL,NULL,'{\"attributes\":{\"base_currency\":\"USD\",\"quote_currency\":\"BDT\",\"rate\":\"122.00000000\",\"effective_date\":\"2026-01-01T00:00:00.000000Z\",\"source\":\"demo\"}}','[]','2026-09-27 11:25:04','2026-09-27 11:25:04');
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `countries`
--

DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `code` char(2) NOT NULL,
  `iso3` char(3) NOT NULL,
  `numeric_code` char(3) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`code`),
  UNIQUE KEY `countries_iso3_unique` (`iso3`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `countries`
--

LOCK TABLES `countries` WRITE;
/*!40000 ALTER TABLE `countries` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `countries` VALUES
('AD','AND','020','Andorra','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AE','ARE','784','United Arab Emirates','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AF','AFG','004','Afghanistan','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AG','ATG','028','Antigua & Barbuda','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AI','AIA','660','Anguilla','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AL','ALB','008','Albania','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AM','ARM','051','Armenia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AO','AGO','024','Angola','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AQ','ATA','010','Antarctica','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AR','ARG','032','Argentina','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AS','ASM','016','American Samoa','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AT','AUT','040','Austria','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AU','AUS','036','Australia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AW','ABW','533','Aruba','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AX','ALA','248','Åland Islands','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AZ','AZE','031','Azerbaijan','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BA','BIH','070','Bosnia & Herzegovina','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BB','BRB','052','Barbados','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BD','BGD','050','Bangladesh','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BE','BEL','056','Belgium','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BF','BFA','854','Burkina Faso','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BG','BGR','100','Bulgaria','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BH','BHR','048','Bahrain','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BI','BDI','108','Burundi','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BJ','BEN','204','Benin','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BL','BLM','652','St. Barthélemy','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BM','BMU','060','Bermuda','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BN','BRN','096','Brunei','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BO','BOL','068','Bolivia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BQ','BES','535','Caribbean Netherlands','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BR','BRA','076','Brazil','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BS','BHS','044','Bahamas','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BT','BTN','064','Bhutan','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BV','BVT','074','Bouvet Island','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BW','BWA','072','Botswana','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BY','BLR','112','Belarus','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BZ','BLZ','084','Belize','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CA','CAN','124','Canada','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CC','CCK','166','Cocos (Keeling) Islands','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CD','COD','180','Congo - Kinshasa','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CF','CAF','140','Central African Republic','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CG','COG','178','Congo - Brazzaville','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CH','CHE','756','Switzerland','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CI','CIV','384','Côte d’Ivoire','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CK','COK','184','Cook Islands','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CL','CHL','152','Chile','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CM','CMR','120','Cameroon','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CN','CHN','156','China','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CO','COL','170','Colombia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CR','CRI','188','Costa Rica','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CU','CUB','192','Cuba','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CV','CPV','132','Cape Verde','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CW','CUW','531','Curaçao','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CX','CXR','162','Christmas Island','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CY','CYP','196','Cyprus','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CZ','CZE','203','Czechia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('DE','DEU','276','Germany','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('DJ','DJI','262','Djibouti','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('DK','DNK','208','Denmark','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('DM','DMA','212','Dominica','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('DO','DOM','214','Dominican Republic','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('DZ','DZA','012','Algeria','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('EC','ECU','218','Ecuador','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('EE','EST','233','Estonia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('EG','EGY','818','Egypt','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('EH','ESH','732','Western Sahara','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ER','ERI','232','Eritrea','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ES','ESP','724','Spain','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ET','ETH','231','Ethiopia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('FI','FIN','246','Finland','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('FJ','FJI','242','Fiji','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('FK','FLK','238','Falkland Islands','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('FM','FSM','583','Micronesia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('FO','FRO','234','Faroe Islands','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('FR','FRA','250','France','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GA','GAB','266','Gabon','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GB','GBR','826','United Kingdom','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GD','GRD','308','Grenada','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GE','GEO','268','Georgia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GF','GUF','254','French Guiana','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GG','GGY','831','Guernsey','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GH','GHA','288','Ghana','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GI','GIB','292','Gibraltar','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GL','GRL','304','Greenland','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GM','GMB','270','Gambia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GN','GIN','324','Guinea','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GP','GLP','312','Guadeloupe','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GQ','GNQ','226','Equatorial Guinea','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GR','GRC','300','Greece','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GS','SGS','239','South Georgia & South Sandwich Islands','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GT','GTM','320','Guatemala','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GU','GUM','316','Guam','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GW','GNB','624','Guinea-Bissau','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GY','GUY','328','Guyana','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('HK','HKG','344','Hong Kong SAR China','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('HM','HMD','334','Heard & McDonald Islands','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('HN','HND','340','Honduras','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('HR','HRV','191','Croatia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('HT','HTI','332','Haiti','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('HU','HUN','348','Hungary','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ID','IDN','360','Indonesia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('IE','IRL','372','Ireland','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('IL','ISR','376','Israel','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('IM','IMN','833','Isle of Man','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('IN','IND','356','India','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('IO','IOT','086','British Indian Ocean Territory','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('IQ','IRQ','368','Iraq','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('IR','IRN','364','Iran','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('IS','ISL','352','Iceland','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('IT','ITA','380','Italy','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('JE','JEY','832','Jersey','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('JM','JAM','388','Jamaica','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('JO','JOR','400','Jordan','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('JP','JPN','392','Japan','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KE','KEN','404','Kenya','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KG','KGZ','417','Kyrgyzstan','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KH','KHM','116','Cambodia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KI','KIR','296','Kiribati','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KM','COM','174','Comoros','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KN','KNA','659','St. Kitts & Nevis','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KP','PRK','408','North Korea','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KR','KOR','410','South Korea','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KW','KWT','414','Kuwait','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KY','CYM','136','Cayman Islands','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KZ','KAZ','398','Kazakhstan','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LA','LAO','418','Laos','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LB','LBN','422','Lebanon','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LC','LCA','662','St. Lucia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LI','LIE','438','Liechtenstein','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LK','LKA','144','Sri Lanka','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LR','LBR','430','Liberia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LS','LSO','426','Lesotho','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LT','LTU','440','Lithuania','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LU','LUX','442','Luxembourg','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LV','LVA','428','Latvia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LY','LBY','434','Libya','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MA','MAR','504','Morocco','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MC','MCO','492','Monaco','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MD','MDA','498','Moldova','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ME','MNE','499','Montenegro','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MF','MAF','663','St. Martin','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MG','MDG','450','Madagascar','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MH','MHL','584','Marshall Islands','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MK','MKD','807','North Macedonia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ML','MLI','466','Mali','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MM','MMR','104','Myanmar (Burma)','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MN','MNG','496','Mongolia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MO','MAC','446','Macao SAR China','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MP','MNP','580','Northern Mariana Islands','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MQ','MTQ','474','Martinique','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MR','MRT','478','Mauritania','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MS','MSR','500','Montserrat','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MT','MLT','470','Malta','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MU','MUS','480','Mauritius','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MV','MDV','462','Maldives','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MW','MWI','454','Malawi','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MX','MEX','484','Mexico','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MY','MYS','458','Malaysia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MZ','MOZ','508','Mozambique','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('NA','NAM','516','Namibia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('NC','NCL','540','New Caledonia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('NE','NER','562','Niger','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('NF','NFK','574','Norfolk Island','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('NG','NGA','566','Nigeria','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('NI','NIC','558','Nicaragua','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('NL','NLD','528','Netherlands','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('NO','NOR','578','Norway','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('NP','NPL','524','Nepal','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('NR','NRU','520','Nauru','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('NU','NIU','570','Niue','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('NZ','NZL','554','New Zealand','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('OM','OMN','512','Oman','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PA','PAN','591','Panama','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PE','PER','604','Peru','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PF','PYF','258','French Polynesia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PG','PNG','598','Papua New Guinea','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PH','PHL','608','Philippines','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PK','PAK','586','Pakistan','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PL','POL','616','Poland','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PM','SPM','666','St. Pierre & Miquelon','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PN','PCN','612','Pitcairn Islands','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PR','PRI','630','Puerto Rico','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PS','PSE','275','Palestinian Territories','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PT','PRT','620','Portugal','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PW','PLW','585','Palau','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PY','PRY','600','Paraguay','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('QA','QAT','634','Qatar','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('RE','REU','638','Réunion','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('RO','ROU','642','Romania','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('RS','SRB','688','Serbia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('RU','RUS','643','Russia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('RW','RWA','646','Rwanda','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SA','SAU','682','Saudi Arabia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SB','SLB','090','Solomon Islands','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SC','SYC','690','Seychelles','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SD','SDN','729','Sudan','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SE','SWE','752','Sweden','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SG','SGP','702','Singapore','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SH','SHN','654','St. Helena','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SI','SVN','705','Slovenia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SJ','SJM','744','Svalbard & Jan Mayen','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SK','SVK','703','Slovakia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SL','SLE','694','Sierra Leone','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SM','SMR','674','San Marino','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SN','SEN','686','Senegal','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SO','SOM','706','Somalia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SR','SUR','740','Suriname','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SS','SSD','728','South Sudan','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ST','STP','678','São Tomé & Príncipe','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SV','SLV','222','El Salvador','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SX','SXM','534','Sint Maarten','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SY','SYR','760','Syria','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SZ','SWZ','748','Eswatini','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TC','TCA','796','Turks & Caicos Islands','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TD','TCD','148','Chad','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TF','ATF','260','French Southern Territories','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TG','TGO','768','Togo','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TH','THA','764','Thailand','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TJ','TJK','762','Tajikistan','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TK','TKL','772','Tokelau','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TL','TLS','626','Timor-Leste','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TM','TKM','795','Turkmenistan','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TN','TUN','788','Tunisia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TO','TON','776','Tonga','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TR','TUR','792','Türkiye','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TT','TTO','780','Trinidad & Tobago','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TV','TUV','798','Tuvalu','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TW','TWN','158','Taiwan','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TZ','TZA','834','Tanzania','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('UA','UKR','804','Ukraine','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('UG','UGA','800','Uganda','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('UM','UMI','581','U.S. Outlying Islands','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('US','USA','840','United States','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('UY','URY','858','Uruguay','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('UZ','UZB','860','Uzbekistan','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('VA','VAT','336','Vatican City','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('VC','VCT','670','St. Vincent & Grenadines','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('VE','VEN','862','Venezuela','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('VG','VGB','092','British Virgin Islands','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('VI','VIR','850','U.S. Virgin Islands','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('VN','VNM','704','Vietnam','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('VU','VUT','548','Vanuatu','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('WF','WLF','876','Wallis & Futuna','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('WS','WSM','882','Samoa','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('YE','YEM','887','Yemen','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('YT','MYT','175','Mayotte','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ZA','ZAF','710','South Africa','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ZM','ZMB','894','Zambia','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ZW','ZWE','716','Zimbabwe','2026-09-27 11:24:54','2026-09-27 11:24:54');
/*!40000 ALTER TABLE `countries` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `currencies`
--

DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies` (
  `code` char(3) NOT NULL,
  `name` varchar(255) NOT NULL,
  `symbol` varchar(10) NOT NULL,
  `decimals` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `currencies`
--

LOCK TABLES `currencies` WRITE;
/*!40000 ALTER TABLE `currencies` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `currencies` VALUES
('ADP','Andorran Peseta','ADP',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AED','United Arab Emirates Dirham','AED',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AFA','Afghan Afghani (1927–2002)','AFA',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AFN','Afghan Afghani','AFN',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ALK','Albanian Lek (1946–1965)','ALK',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ALL','Albanian Lek','ALL',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AMD','Armenian Dram','AMD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ANG','Netherlands Antillean Guilder','ANG',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AOA','Angolan Kwanza','AOA',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AOK','Angolan Kwanza (1977–1991)','AOK',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AON','Angolan New Kwanza (1990–2000)','AON',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AOR','Angolan Readjusted Kwanza (1995–1999)','AOR',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ARA','Argentine Austral','ARA',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ARL','Argentine Peso Ley (1970–1983)','ARL',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ARM','Argentine Peso (1881–1970)','ARM',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ARP','Argentine Peso (1983–1985)','ARP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ARS','Argentine Peso','ARS',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ATS','Austrian Schilling','ATS',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AUD','Australian Dollar','A$',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AWG','Aruban Florin','AWG',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AZM','Azerbaijani Manat (1993–2006)','AZM',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('AZN','Azerbaijani Manat','AZN',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BAD','Bosnia-Herzegovina Dinar (1992–1994)','BAD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BAM','Bosnia-Herzegovina Convertible Mark','BAM',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BAN','Bosnia-Herzegovina New Dinar (1994–1997)','BAN',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BBD','Barbadian Dollar','BBD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BDT','Bangladeshi Taka','BDT',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BEC','Belgian Franc (convertible)','BEC',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BEF','Belgian Franc','BEF',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BEL','Belgian Franc (financial)','BEL',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BGL','Bulgarian Hard Lev','BGL',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BGM','Bulgarian Socialist Lev','BGM',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BGN','Bulgarian Lev','BGN',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BGO','Bulgarian Lev (1879–1952)','BGO',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BHD','Bahraini Dinar','BHD',3,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BIF','Burundian Franc','BIF',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BMD','Bermudan Dollar','BMD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BND','Brunei Dollar','BND',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BOB','Bolivian Boliviano','BOB',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BOL','Bolivian Boliviano (1863–1963)','BOL',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BOP','Bolivian Peso','BOP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BOV','Bolivian Mvdol','BOV',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BRB','Brazilian New Cruzeiro (1967–1986)','BRB',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BRC','Brazilian Cruzado (1986–1989)','BRC',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BRE','Brazilian Cruzeiro (1990–1993)','BRE',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BRL','Brazilian Real','R$',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BRN','Brazilian New Cruzado (1989–1990)','BRN',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BRR','Brazilian Cruzeiro (1993–1994)','BRR',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BRZ','Brazilian Cruzeiro (1942–1967)','BRZ',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BSD','Bahamian Dollar','BSD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BTN','Bhutanese Ngultrum','BTN',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BUK','Burmese Kyat','BUK',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BWP','Botswanan Pula','BWP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BYB','Belarusian Ruble (1994–1999)','BYB',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BYN','Belarusian Ruble','BYN',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BYR','Belarusian Ruble (2000–2016)','BYR',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('BZD','Belize Dollar','BZD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CAD','Canadian Dollar','CA$',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CDF','Congolese Franc','CDF',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CHE','WIR Euro','CHE',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CHF','Swiss Franc','CHF',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CHW','WIR Franc','CHW',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CLE','Chilean Escudo','CLE',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CLF','Chilean Unit of Account (UF)','CLF',4,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CLP','Chilean Peso','CLP',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CNH','Chinese Yuan (offshore)','CNH',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CNX','Chinese People’s Bank Dollar','CNX',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CNY','Chinese Yuan','CN¥',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('COP','Colombian Peso','COP',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('COU','Colombian Real Value Unit','COU',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CRC','Costa Rican Colón','CRC',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CSD','Serbian Dinar (2002–2006)','CSD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CSK','Czechoslovak Hard Koruna','CSK',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CUC','Cuban Convertible Peso','CUC',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CUP','Cuban Peso','CUP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CVE','Cape Verdean Escudo','CVE',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CYP','Cypriot Pound','CYP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('CZK','Czech Koruna','CZK',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('DDM','East German Mark','DDM',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('DEM','German Mark','DEM',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('DJF','Djiboutian Franc','DJF',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('DKK','Danish Krone','DKK',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('DOP','Dominican Peso','DOP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('DZD','Algerian Dinar','DZD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ECS','Ecuadorian Sucre','ECS',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ECV','Ecuadorian Unit of Constant Value','ECV',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('EEK','Estonian Kroon','EEK',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('EGP','Egyptian Pound','EGP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ERN','Eritrean Nakfa','ERN',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ESA','Spanish Peseta (A account)','ESA',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ESB','Spanish Peseta (convertible account)','ESB',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ESP','Spanish Peseta','ESP',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ETB','Ethiopian Birr','ETB',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('EUR','Euro','€',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('FIM','Finnish Markka','FIM',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('FJD','Fijian Dollar','FJD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('FKP','Falkland Islands Pound','FKP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('FRF','French Franc','FRF',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GBP','British Pound','£',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GEK','Georgian Kupon Larit','GEK',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GEL','Georgian Lari','GEL',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GHC','Ghanaian Cedi (1979–2007)','GHC',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GHS','Ghanaian Cedi','GHS',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GIP','Gibraltar Pound','GIP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GMD','Gambian Dalasi','GMD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GNF','Guinean Franc','GNF',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GNS','Guinean Syli','GNS',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GQE','Equatorial Guinean Ekwele','GQE',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GRD','Greek Drachma','GRD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GTQ','Guatemalan Quetzal','GTQ',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GWE','Portuguese Guinea Escudo','GWE',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GWP','Guinea-Bissau Peso','GWP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('GYD','Guyanaese Dollar','GYD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('HKD','Hong Kong Dollar','HK$',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('HNL','Honduran Lempira','HNL',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('HRD','Croatian Dinar','HRD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('HRK','Croatian Kuna','HRK',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('HTG','Haitian Gourde','HTG',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('HUF','Hungarian Forint','HUF',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('IDR','Indonesian Rupiah','IDR',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('IEP','Irish Pound','IEP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ILP','Israeli Pound','ILP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ILR','Israeli Shekel (1980–1985)','ILR',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ILS','Israeli New Shekel','₪',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('INR','Indian Rupee','₹',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('IQD','Iraqi Dinar','IQD',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('IRR','Iranian Rial','IRR',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ISJ','Icelandic Króna (1918–1981)','ISJ',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ISK','Icelandic Króna','ISK',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ITL','Italian Lira','ITL',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('JMD','Jamaican Dollar','JMD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('JOD','Jordanian Dinar','JOD',3,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('JPY','Japanese Yen','¥',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KES','Kenyan Shilling','KES',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KGS','Kyrgyz Som','KGS',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KHR','Cambodian Riel','KHR',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KMF','Comorian Franc','KMF',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KPW','North Korean Won','KPW',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KRH','South Korean Hwan (1953–1962)','KRH',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KRO','South Korean Won (1945–1953)','KRO',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KRW','South Korean Won','₩',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KWD','Kuwaiti Dinar','KWD',3,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KYD','Cayman Islands Dollar','KYD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('KZT','Kazakhstani Tenge','KZT',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LAK','Laotian Kip','LAK',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LBP','Lebanese Pound','LBP',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LKR','Sri Lankan Rupee','LKR',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LRD','Liberian Dollar','LRD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LSL','Lesotho Loti','LSL',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LTL','Lithuanian Litas','LTL',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LTT','Lithuanian Talonas','LTT',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LUC','Luxembourgian Convertible Franc','LUC',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LUF','Luxembourgian Franc','LUF',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LUL','Luxembourg Financial Franc','LUL',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LVL','Latvian Lats','LVL',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LVR','Latvian Ruble','LVR',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('LYD','Libyan Dinar','LYD',3,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MAD','Moroccan Dirham','MAD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MAF','Moroccan Franc','MAF',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MCF','Monegasque Franc','MCF',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MDC','Moldovan Cupon','MDC',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MDL','Moldovan Leu','MDL',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MGA','Malagasy Ariary','MGA',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MGF','Malagasy Franc','MGF',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MKD','Macedonian Denar','MKD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MKN','Macedonian Denar (1992–1993)','MKN',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MLF','Malian Franc','MLF',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MMK','Myanmar Kyat','MMK',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MNT','Mongolian Tugrik','MNT',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MOP','Macanese Pataca','MOP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MRO','Mauritanian Ouguiya (1973–2017)','MRO',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MRU','Mauritanian Ouguiya','MRU',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MTL','Maltese Lira','MTL',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MTP','Maltese Pound','MTP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MUR','Mauritian Rupee','MUR',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MVP','Maldivian Rupee (1947–1981)','MVP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MVR','Maldivian Rufiyaa','MVR',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MWK','Malawian Kwacha','MWK',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MXN','Mexican Peso','MX$',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MXP','Mexican Silver Peso (1861–1992)','MXP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MXV','Mexican Investment Unit','MXV',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MYR','Malaysian Ringgit','MYR',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MZE','Mozambican Escudo','MZE',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MZM','Mozambican Metical (1980–2006)','MZM',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('MZN','Mozambican Metical','MZN',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('NAD','Namibian Dollar','NAD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('NGN','Nigerian Naira','NGN',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('NIC','Nicaraguan Córdoba (1988–1991)','NIC',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('NIO','Nicaraguan Córdoba','NIO',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('NLG','Dutch Guilder','NLG',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('NOK','Norwegian Krone','NOK',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('NPR','Nepalese Rupee','NPR',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('NZD','New Zealand Dollar','NZ$',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('OMR','Omani Rial','OMR',3,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PAB','Panamanian Balboa','PAB',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PEI','Peruvian Inti','PEI',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PEN','Peruvian Sol','PEN',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PES','Peruvian Sol (1863–1965)','PES',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PGK','Papua New Guinean Kina','PGK',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PHP','Philippine Peso','₱',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PKR','Pakistani Rupee','PKR',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PLN','Polish Zloty','PLN',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PLZ','Polish Zloty (1950–1995)','PLZ',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PTE','Portuguese Escudo','PTE',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('PYG','Paraguayan Guarani','PYG',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('QAR','Qatari Riyal','QAR',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('RHD','Rhodesian Dollar','RHD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ROL','Romanian Leu (1952–2006)','ROL',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('RON','Romanian Leu','RON',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('RSD','Serbian Dinar','RSD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('RUB','Russian Ruble','RUB',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('RUR','Russian Ruble (1991–1998)','RUR',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('RWF','Rwandan Franc','RWF',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SAR','Saudi Riyal','SAR',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SBD','Solomon Islands Dollar','SBD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SCR','Seychellois Rupee','SCR',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SDD','Sudanese Dinar (1992–2007)','SDD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SDG','Sudanese Pound','SDG',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SDP','Sudanese Pound (1957–1998)','SDP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SEK','Swedish Krona','SEK',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SGD','Singapore Dollar','SGD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SHP','St. Helena Pound','SHP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SIT','Slovenian Tolar','SIT',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SKK','Slovak Koruna','SKK',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SLE','Sierra Leonean Leone','SLE',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SLL','Sierra Leonean Leone (1964—2022)','SLL',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SOS','Somali Shilling','SOS',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SRD','Surinamese Dollar','SRD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SRG','Surinamese Guilder','SRG',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SSP','South Sudanese Pound','SSP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('STD','São Tomé & Príncipe Dobra (1977–2017)','STD',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('STN','São Tomé & Príncipe Dobra','STN',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SUR','Soviet Rouble','SUR',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SVC','Salvadoran Colón','SVC',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SYP','Syrian Pound','SYP',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('SZL','Swazi Lilangeni','SZL',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('THB','Thai Baht','THB',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TJR','Tajikistani Ruble','TJR',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TJS','Tajikistani Somoni','TJS',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TMM','Turkmenistani Manat (1993–2009)','TMM',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TMT','Turkmenistani Manat','TMT',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TND','Tunisian Dinar','TND',3,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TOP','Tongan Paʻanga','TOP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TPE','Timorese Escudo','TPE',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TRL','Turkish Lira (1922–2005)','TRL',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TRY','Turkish Lira','TRY',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TTD','Trinidad & Tobago Dollar','TTD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TWD','New Taiwan Dollar','NT$',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('TZS','Tanzanian Shilling','TZS',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('UAH','Ukrainian Hryvnia','UAH',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('UAK','Ukrainian Karbovanets','UAK',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('UGS','Ugandan Shilling (1966–1987)','UGS',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('UGX','Ugandan Shilling','UGX',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('USD','US Dollar','$',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('USN','US Dollar (Next day)','USN',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('USS','US Dollar (Same day)','USS',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('UYI','Uruguayan Peso (Indexed Units)','UYI',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('UYP','Uruguayan Peso (1975–1993)','UYP',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('UYU','Uruguayan Peso','UYU',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('UYW','Uruguayan Nominal Wage Index Unit','UYW',4,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('UZS','Uzbekistani Som','UZS',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('VEB','Venezuelan Bolívar (1871–2008)','VEB',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('VED','Bolívar Soberano','VED',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('VEF','Venezuelan Bolívar (2008–2018)','VEF',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('VES','Venezuelan Bolívar','VES',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('VND','Vietnamese Dong','₫',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('VNN','Vietnamese Dong (1978–1985)','VNN',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('VUV','Vanuatu Vatu','VUV',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('WST','Samoan Tala','WST',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('XAF','Central African CFA Franc','FCFA',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('XCD','East Caribbean Dollar','EC$',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('XCG','Caribbean guilder','Cg.',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('XEU','European Currency Unit','XEU',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('XFO','French Gold Franc','XFO',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('XFU','French UIC-Franc','XFU',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('XOF','West African CFA Franc','F CFA',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('XPF','CFP Franc','CFPF',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('XRE','RINET Funds','XRE',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('YDD','Yemeni Dinar','YDD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('YER','Yemeni Rial','YER',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('YUD','Yugoslavian Hard Dinar (1966–1990)','YUD',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('YUM','Yugoslavian New Dinar (1994–2002)','YUM',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('YUN','Yugoslavian Convertible Dinar (1990–1992)','YUN',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('YUR','Yugoslavian Reformed Dinar (1992–1993)','YUR',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ZAL','South African Rand (financial)','ZAL',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ZAR','South African Rand','ZAR',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ZMK','Zambian Kwacha (1968–2012)','ZMK',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ZMW','Zambian Kwacha','ZMW',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ZRN','Zairean New Zaire (1993–1998)','ZRN',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ZRZ','Zairean Zaire (1971–1993)','ZRZ',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ZWD','Zimbabwean Dollar (1980–2008)','ZWD',0,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ZWG','Zimbabwean Gold','ZWG',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ZWL','Zimbabwean Dollar (2009–2024)','ZWL',2,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
('ZWR','Zimbabwean Dollar (2008)','ZWR',2,'2026-09-27 11:24:54','2026-09-27 11:24:54');
/*!40000 ALTER TABLE `currencies` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `document_sequences`
--

DROP TABLE IF EXISTS `document_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_sequences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `property_id` bigint(20) unsigned DEFAULT NULL,
  `property_scope` bigint(20) unsigned GENERATED ALWAYS AS (coalesce(`property_id`,0)) STORED,
  `document_type` varchar(50) NOT NULL,
  `prefix` varchar(20) NOT NULL,
  `format` varchar(100) NOT NULL,
  `next_number` bigint(20) unsigned NOT NULL DEFAULT 1,
  `reset` varchar(10) NOT NULL DEFAULT 'never',
  `period` smallint(5) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_sequences_tenant_id_property_scope_document_type_unique` (`tenant_id`,`property_scope`,`document_type`),
  CONSTRAINT `document_sequences_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_sequences`
--

LOCK TABLES `document_sequences` WRITE;
/*!40000 ALTER TABLE `document_sequences` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `document_sequences` VALUES
(1,1,NULL,0,'reservation','RSV','{PREFIX}-{YYYY}-{SEQ:5}',1,'yearly',NULL,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(2,1,NULL,0,'invoice','INV','{PREFIX}-{YYYY}-{SEQ:5}',1,'yearly',NULL,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(3,1,NULL,0,'purchase_order','PO','{PREFIX}-{YYYY}-{SEQ:5}',1,'yearly',NULL,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(4,1,NULL,0,'goods_receipt','GRN','{PREFIX}-{YYYY}-{SEQ:5}',1,'yearly',NULL,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(5,1,NULL,0,'journal','JV','{PREFIX}-{YYYY}-{SEQ:5}',1,'yearly',NULL,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(6,1,NULL,0,'payment','PAY','{PREFIX}-{YYYY}-{SEQ:5}',1,'yearly',NULL,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(7,2,NULL,0,'reservation','RSV','{PREFIX}-{YYYY}-{SEQ:5}',1,'yearly',NULL,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(8,2,NULL,0,'invoice','INV','{PREFIX}-{YYYY}-{SEQ:5}',1,'yearly',NULL,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(9,2,NULL,0,'purchase_order','PO','{PREFIX}-{YYYY}-{SEQ:5}',1,'yearly',NULL,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(10,2,NULL,0,'goods_receipt','GRN','{PREFIX}-{YYYY}-{SEQ:5}',1,'yearly',NULL,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(11,2,NULL,0,'journal','JV','{PREFIX}-{YYYY}-{SEQ:5}',1,'yearly',NULL,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(12,2,NULL,0,'payment','PAY','{PREFIX}-{YYYY}-{SEQ:5}',1,'yearly',NULL,'2026-09-27 11:24:59','2026-09-27 11:24:59');
/*!40000 ALTER TABLE `document_sequences` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `exchange_rates`
--

DROP TABLE IF EXISTS `exchange_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `exchange_rates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `base_currency` char(3) NOT NULL,
  `quote_currency` char(3) NOT NULL,
  `rate` decimal(18,8) NOT NULL,
  `effective_date` date NOT NULL,
  `source` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exchange_rates_pair_date_unique` (`tenant_id`,`base_currency`,`quote_currency`,`effective_date`),
  KEY `exchange_rates_base_currency_foreign` (`base_currency`),
  KEY `exchange_rates_quote_currency_foreign` (`quote_currency`),
  CONSTRAINT `exchange_rates_base_currency_foreign` FOREIGN KEY (`base_currency`) REFERENCES `currencies` (`code`),
  CONSTRAINT `exchange_rates_quote_currency_foreign` FOREIGN KEY (`quote_currency`) REFERENCES `currencies` (`code`),
  CONSTRAINT `exchange_rates_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exchange_rates`
--

LOCK TABLES `exchange_rates` WRITE;
/*!40000 ALTER TABLE `exchange_rates` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `exchange_rates` VALUES
(1,1,'USD','BDT',122.00000000,'2026-01-01','demo','2026-09-27 11:24:59','2026-09-27 11:24:59'),
(2,2,'USD','BDT',122.00000000,'2026-01-01','demo','2026-09-27 11:25:04','2026-09-27 11:25:04');
/*!40000 ALTER TABLE `exchange_rates` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` varchar(255) NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `login_histories`
--

DROP TABLE IF EXISTS `login_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `event` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `login_histories_user_id_foreign` (`user_id`),
  KEY `login_histories_tenant_id_user_id_created_at_index` (`tenant_id`,`user_id`,`created_at`),
  KEY `login_histories_tenant_id_event_created_at_index` (`tenant_id`,`event`,`created_at`),
  CONSTRAINT `login_histories_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `login_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_histories`
--

LOCK TABLES `login_histories` WRITE;
/*!40000 ALTER TABLE `login_histories` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `login_histories` VALUES
(1,1,1,'login','owner@sunrise.test','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:155.0) Gecko/20100101 Firefox/155.0','2026-09-27 15:52:36','2026-09-27 15:52:36');
/*!40000 ALTER TABLE `login_histories` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `media`
--

DROP TABLE IF EXISTS `media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `media` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  `uuid` char(36) DEFAULT NULL,
  `collection_name` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `disk` varchar(255) NOT NULL,
  `conversions_disk` varchar(255) DEFAULT NULL,
  `size` bigint(20) unsigned NOT NULL,
  `manipulations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`manipulations`)),
  `custom_properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`custom_properties`)),
  `generated_conversions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`generated_conversions`)),
  `responsive_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`responsive_images`)),
  `order_column` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `media_uuid_unique` (`uuid`),
  KEY `media_model_type_model_id_index` (`model_type`,`model_id`),
  KEY `media_tenant_id_model_type_model_id_index` (`tenant_id`,`model_type`,`model_id`),
  KEY `media_order_column_index` (`order_column`),
  CONSTRAINT `media_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `media`
--

LOCK TABLES `media` WRITE;
/*!40000 ALTER TABLE `media` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `media` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `migrations` VALUES
(1,'0001_01_01_000000_create_users_table',1),
(2,'0001_01_01_000001_create_cache_table',1),
(3,'0001_01_01_000002_create_jobs_table',1),
(4,'2026_09_27_000100_create_tenants_table',1),
(5,'2026_09_28_000100_add_tenancy_and_security_columns_to_users_table',1),
(6,'2026_09_28_000200_create_login_histories_table',1),
(7,'2026_09_28_000300_create_platform_admins_table',1),
(8,'2026_09_29_000100_create_tenant_modules_table',1),
(9,'2026_09_29_000200_create_permission_tables',1),
(10,'2026_09_30_000100_create_reference_data_tables',1),
(11,'2026_09_30_000200_create_settings_table',1),
(12,'2026_09_30_000300_create_document_sequences_table',1),
(13,'2026_09_30_000400_create_activity_log_table',1),
(14,'2026_09_30_000500_create_media_table',1),
(15,'2026_09_30_000600_create_exchange_rates_table',1),
(16,'2026_09_30_000700_create_notification_templates_table',1),
(17,'2026_09_30_000800_create_notifications_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`tenant_id`,`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_permission_id_foreign` (`permission_id`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `model_has_permissions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`tenant_id`,`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_role_id_foreign` (`role_id`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `model_has_roles_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `model_has_roles` VALUES
(1,'Modules\\IAM\\Models\\User',1,1),
(2,'Modules\\IAM\\Models\\User',2,1),
(3,'Modules\\IAM\\Models\\User',3,1),
(4,'Modules\\IAM\\Models\\User',4,1),
(5,'Modules\\IAM\\Models\\User',5,1),
(6,'Modules\\IAM\\Models\\User',6,1),
(7,'Modules\\IAM\\Models\\User',7,1),
(8,'Modules\\IAM\\Models\\User',8,1),
(9,'Modules\\IAM\\Models\\User',9,1),
(10,'Modules\\IAM\\Models\\User',10,1),
(11,'Modules\\IAM\\Models\\User',11,1),
(12,'Modules\\IAM\\Models\\User',12,1),
(13,'Modules\\IAM\\Models\\User',13,1),
(14,'Modules\\IAM\\Models\\User',14,1),
(15,'Modules\\IAM\\Models\\User',15,1),
(16,'Modules\\IAM\\Models\\User',16,1),
(17,'Modules\\IAM\\Models\\User',17,1),
(18,'Modules\\IAM\\Models\\User',18,1),
(19,'Modules\\IAM\\Models\\User',19,2),
(20,'Modules\\IAM\\Models\\User',20,2),
(21,'Modules\\IAM\\Models\\User',21,2),
(22,'Modules\\IAM\\Models\\User',22,2),
(23,'Modules\\IAM\\Models\\User',23,2),
(24,'Modules\\IAM\\Models\\User',24,2),
(25,'Modules\\IAM\\Models\\User',25,2),
(26,'Modules\\IAM\\Models\\User',26,2),
(27,'Modules\\IAM\\Models\\User',27,2),
(28,'Modules\\IAM\\Models\\User',28,2),
(29,'Modules\\IAM\\Models\\User',29,2),
(30,'Modules\\IAM\\Models\\User',30,2),
(31,'Modules\\IAM\\Models\\User',31,2),
(32,'Modules\\IAM\\Models\\User',32,2),
(33,'Modules\\IAM\\Models\\User',33,2),
(34,'Modules\\IAM\\Models\\User',34,2),
(35,'Modules\\IAM\\Models\\User',35,2),
(36,'Modules\\IAM\\Models\\User',36,2);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `notification_templates`
--

DROP TABLE IF EXISTS `notification_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `key` varchar(100) NOT NULL,
  `channel` varchar(20) NOT NULL,
  `locale` varchar(10) NOT NULL DEFAULT 'en',
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_templates_tenant_id_key_channel_locale_unique` (`tenant_id`,`key`,`channel`,`locale`),
  CONSTRAINT `notification_templates_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_templates`
--

LOCK TABLES `notification_templates` WRITE;
/*!40000 ALTER TABLE `notification_templates` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `notification_templates` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`),
  KEY `notifications_tenant_notifiable_read_index` (`tenant_id`,`notifiable_type`,`notifiable_id`,`read_at`),
  CONSTRAINT `notifications_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `notifications` VALUES
('024b26dd-0f99-46c6-beb8-3d337ade1491',1,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',12,'{\"title\":\"Welcome to Sunrise Resorts Ltd\",\"body\":\"Hi Bartender, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:24:57','2026-09-27 11:24:57'),
('02e81ed2-d010-4425-9d92-f81db8cf12c2',1,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',3,'{\"title\":\"Welcome to Sunrise Resorts Ltd\",\"body\":\"Hi Front Office Manager, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:24:55','2026-09-27 11:24:55'),
('0c771b4c-bce0-45be-b158-3beb01d0545f',2,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',36,'{\"title\":\"Welcome to Green Valley Resort\",\"body\":\"Hi Auditor, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:25:04','2026-09-27 11:25:04'),
('10ad1578-2991-4480-80f1-75998ac5f2ef',1,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',16,'{\"title\":\"Welcome to Sunrise Resorts Ltd\",\"body\":\"Hi HR Manager, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:24:58','2026-09-27 11:24:58'),
('1350f430-19cd-45af-93b4-cd4d45d94de3',1,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',18,'{\"title\":\"Welcome to Sunrise Resorts Ltd\",\"body\":\"Hi Auditor, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
('14da16e5-e3ab-4982-840e-518e5ea941f1',2,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',34,'{\"title\":\"Welcome to Green Valley Resort\",\"body\":\"Hi HR Manager, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:25:03','2026-09-27 11:25:03'),
('1756e303-bec5-40d7-8e22-62f98ff896de',2,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',20,'{\"title\":\"Welcome to Green Valley Resort\",\"body\":\"Hi General Manager, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:25:00','2026-09-27 11:25:00'),
('19dc3988-858b-4794-832e-c2eca03fe0ac',1,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',4,'{\"title\":\"Welcome to Sunrise Resorts Ltd\",\"body\":\"Hi Nusrat Jahan, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:24:55','2026-09-27 11:24:55'),
('1acd3844-7d54-499e-ba2a-712ac2f1f4d6',1,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',7,'{\"title\":\"Welcome to Sunrise Resorts Ltd\",\"body\":\"Hi Maintenance Technician, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:24:56','2026-09-27 11:24:56'),
('1bdae48a-7faa-4372-9699-c8f1d85cc5fd',2,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',21,'{\"title\":\"Welcome to Green Valley Resort\",\"body\":\"Hi Front Office Manager, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:25:00','2026-09-27 11:25:00'),
('1f079bf7-df94-4141-b9a6-2e35299a441c',1,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',11,'{\"title\":\"Welcome to Sunrise Resorts Ltd\",\"body\":\"Hi Chef \\/ Kitchen Staff, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:24:57','2026-09-27 11:24:57'),
('2584a8d9-09b4-4d23-acd5-499118a4ab90',2,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',24,'{\"title\":\"Welcome to Green Valley Resort\",\"body\":\"Hi Housekeeping Supervisor, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:25:01','2026-09-27 11:25:01'),
('26e31193-2022-4957-b792-653df86afaf8',1,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',14,'{\"title\":\"Welcome to Sunrise Resorts Ltd\",\"body\":\"Hi Procurement Officer, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:24:58','2026-09-27 11:24:58'),
('2a29d87c-ab47-468b-8445-8998dadbbf85',2,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',32,'{\"title\":\"Welcome to Green Valley Resort\",\"body\":\"Hi Procurement Officer, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:25:03','2026-09-27 11:25:03'),
('30572eaa-2b3e-424c-bf73-df97f6c9cb0c',2,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',25,'{\"title\":\"Welcome to Green Valley Resort\",\"body\":\"Hi Maintenance Technician, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:25:01','2026-09-27 11:25:01'),
('3a1b5a2e-f8dc-45a4-b64e-1170ef6b3e6a',2,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',28,'{\"title\":\"Welcome to Green Valley Resort\",\"body\":\"Hi Waiter \\/ Captain, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:25:02','2026-09-27 11:25:02'),
('406cb4cf-17cb-4260-b7f0-946184a44171',1,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',5,'{\"title\":\"Welcome to Sunrise Resorts Ltd\",\"body\":\"Hi Reservation Agent, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:24:56','2026-09-27 11:24:56'),
('40a01353-4fd1-47cf-a289-d24c0e170b3a',2,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',30,'{\"title\":\"Welcome to Green Valley Resort\",\"body\":\"Hi Bartender, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:25:02','2026-09-27 11:25:02'),
('60ba33b1-d7bd-40cd-a9ed-7d8a363da712',2,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',27,'{\"title\":\"Welcome to Green Valley Resort\",\"body\":\"Hi Outlet Cashier, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:25:01','2026-09-27 11:25:01'),
('63f2444a-a0ba-4d93-8c44-6d447ad86454',2,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',35,'{\"title\":\"Welcome to Green Valley Resort\",\"body\":\"Hi Payroll Officer, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:25:03','2026-09-27 11:25:03'),
('711181e4-197d-473f-a1b9-8aaddfaafc2d',1,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',1,'{\"title\":\"Welcome to Sunrise Resorts Ltd\",\"body\":\"Hi Rahim Uddin, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:24:55','2026-09-27 11:24:55'),
('7c79f669-8b6d-4279-ac7b-cb4409c6e881',1,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',9,'{\"title\":\"Welcome to Sunrise Resorts Ltd\",\"body\":\"Hi Outlet Cashier, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:24:57','2026-09-27 11:24:57'),
('81930f85-eb35-42b9-8d5a-43d8c386a636',1,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',8,'{\"title\":\"Welcome to Sunrise Resorts Ltd\",\"body\":\"Hi F&B Manager, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:24:56','2026-09-27 11:24:56'),
('819f1936-f136-4efb-844f-ae3bed502bf7',1,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',2,'{\"title\":\"Welcome to Sunrise Resorts Ltd\",\"body\":\"Hi General Manager, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:24:55','2026-09-27 11:24:55'),
('942bda57-c224-4b85-bafc-2a64c5d270eb',2,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',26,'{\"title\":\"Welcome to Green Valley Resort\",\"body\":\"Hi F&B Manager, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:25:01','2026-09-27 11:25:01'),
('9cb5b388-5ede-40c1-bac6-93cf4f5a62a7',1,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',17,'{\"title\":\"Welcome to Sunrise Resorts Ltd\",\"body\":\"Hi Payroll Officer, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
('a1bfd79a-6e4c-456f-afa0-9df3b51c8132',2,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',29,'{\"title\":\"Welcome to Green Valley Resort\",\"body\":\"Hi Chef \\/ Kitchen Staff, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:25:02','2026-09-27 11:25:02'),
('aa686641-8798-4a38-badf-6804fc6fddfd',1,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',13,'{\"title\":\"Welcome to Sunrise Resorts Ltd\",\"body\":\"Hi Store Keeper, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:24:58','2026-09-27 11:24:58'),
('be499b5c-fd96-4577-ac0b-3f62b039786e',2,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',23,'{\"title\":\"Welcome to Green Valley Resort\",\"body\":\"Hi Reservation Agent, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:25:00','2026-09-27 11:25:00'),
('c9457ee5-4de8-40a8-97df-63ea8813ddb5',2,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',33,'{\"title\":\"Welcome to Green Valley Resort\",\"body\":\"Hi Accountant, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:25:03','2026-09-27 11:25:03'),
('ce145a46-a959-4eb4-a2c6-80ca97a412cd',1,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',6,'{\"title\":\"Welcome to Sunrise Resorts Ltd\",\"body\":\"Hi Housekeeping Supervisor, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:24:56','2026-09-27 11:24:56'),
('d62d9648-d7a7-4d7b-8def-e21e6a96eb6e',2,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',19,'{\"title\":\"Welcome to Green Valley Resort\",\"body\":\"Hi Tanvir Ahmed, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
('ee1b75e2-4dd3-49e8-b96a-3b791db796d4',2,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',31,'{\"title\":\"Welcome to Green Valley Resort\",\"body\":\"Hi Store Keeper, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:25:02','2026-09-27 11:25:02'),
('ee22ebee-ef1f-4a23-a54b-edfe1db33c39',1,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',10,'{\"title\":\"Welcome to Sunrise Resorts Ltd\",\"body\":\"Hi Waiter \\/ Captain, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:24:57','2026-09-27 11:24:57'),
('f1532b76-74cc-4e86-af4e-ef1faef54a7a',1,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',15,'{\"title\":\"Welcome to Sunrise Resorts Ltd\",\"body\":\"Hi Farzana Akter, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:24:58','2026-09-27 11:24:58'),
('fd22918a-bcf5-4b32-94b4-cf4ec23bd475',2,'Modules\\Core\\Notifications\\WelcomeNotification','Modules\\IAM\\Models\\User',22,'{\"title\":\"Welcome to Green Valley Resort\",\"body\":\"Hi Front Desk Agent, your account is ready. Explore the menu on the left to get started.\",\"icon\":\"bi-stars\",\"url\":null}',NULL,'2026-09-27 11:25:00','2026-09-27 11:25:00');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `permissions` VALUES
(1,'core.audit.view','web','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(2,'core.sequence.update','web','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(3,'core.sequence.view','web','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(4,'core.setting.update','web','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(5,'core.setting.view','web','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(6,'iam.role.create','web','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(7,'iam.role.delete','web','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(8,'iam.role.update','web','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(9,'iam.role.view','web','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(10,'iam.user.invite','web','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(11,'iam.user.update','web','2026-09-27 11:24:54','2026-09-27 11:24:54'),
(12,'iam.user.view','web','2026-09-27 11:24:54','2026-09-27 11:24:54');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `platform_admins`
--

DROP TABLE IF EXISTS `platform_admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_admins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `platform_admins_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `platform_admins`
--

LOCK TABLES `platform_admins` WRITE;
/*!40000 ALTER TABLE `platform_admins` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `platform_admins` VALUES
(1,'Platform Admin','admin@resort365.test','$2y$12$WcB.Rbiib/sn9Vmq8oEIveEjBv/mWaZ1EY1GDEIrMTHLLtxNYLKg6',NULL,NULL,NULL,'2026-09-27 11:25:04','2026-09-27 11:25:04');
/*!40000 ALTER TABLE `platform_admins` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `role_has_permissions` VALUES
(1,1),
(2,1),
(3,1),
(4,1),
(5,1),
(6,1),
(7,1),
(8,1),
(9,1),
(10,1),
(11,1),
(12,1),
(1,2),
(2,2),
(3,2),
(4,2),
(5,2),
(9,2),
(10,2),
(11,2),
(12,2),
(1,18),
(3,18),
(5,18),
(9,18),
(12,18),
(1,19),
(2,19),
(3,19),
(4,19),
(5,19),
(6,19),
(7,19),
(8,19),
(9,19),
(10,19),
(11,19),
(12,19),
(1,20),
(2,20),
(3,20),
(4,20),
(5,20),
(9,20),
(10,20),
(11,20),
(12,20),
(1,36),
(3,36),
(5,36),
(9,36),
(12,36);
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_tenant_id_name_guard_name_unique` (`tenant_id`,`name`,`guard_name`),
  CONSTRAINT `roles_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `roles` VALUES
(1,1,'tenant-owner','web','Everything in the tenant, including subscription and billing.',1,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(2,1,'general-manager','web','All operational and financial modules for assigned properties; approvals.',1,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(3,1,'front-office-manager','web','Reservations, front office, guests, folios, rates (view), reports.',1,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(4,1,'front-desk-agent','web','Create and modify reservations, check-in/out, take payments.',1,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(5,1,'reservation-agent','web','Create and modify reservations, quotes, deposits.',1,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(6,1,'housekeeping-supervisor','web','Room status, housekeeping tasks, lost & found.',1,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(7,1,'maintenance-technician','web','Work orders assigned to them.',1,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(8,1,'fnb-manager','web','Everything in the Restaurant module for assigned outlets.',1,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(9,1,'outlet-cashier','web','POS sessions, settling bills, payments, charge to room.',1,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(10,1,'waiter','web','Tables, orders, kitchen tickets and bills.',1,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(11,1,'chef','web','Kitchen display and wastage.',1,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(12,1,'bartender','web','Bar display and bar orders.',1,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(13,1,'store-keeper','web','Inventory: receive, issue, transfer, stock count.',1,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(14,1,'procurement-officer','web','Requisitions, RFQs, purchase orders, vendors.',1,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(15,1,'accountant','web','Accounting, expenses, vendor bills, payments, bank reconciliation.',1,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(16,1,'hr-manager','web','Employees, attendance, leave, shifts.',1,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(17,1,'payroll-officer','web','Payroll runs, payslips, loans.',1,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(18,1,'auditor','web','Read-only access to everything, including the audit log.',1,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(19,2,'tenant-owner','web','Everything in the tenant, including subscription and billing.',1,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(20,2,'general-manager','web','All operational and financial modules for assigned properties; approvals.',1,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(21,2,'front-office-manager','web','Reservations, front office, guests, folios, rates (view), reports.',1,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(22,2,'front-desk-agent','web','Create and modify reservations, check-in/out, take payments.',1,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(23,2,'reservation-agent','web','Create and modify reservations, quotes, deposits.',1,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(24,2,'housekeeping-supervisor','web','Room status, housekeeping tasks, lost & found.',1,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(25,2,'maintenance-technician','web','Work orders assigned to them.',1,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(26,2,'fnb-manager','web','Everything in the Restaurant module for assigned outlets.',1,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(27,2,'outlet-cashier','web','POS sessions, settling bills, payments, charge to room.',1,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(28,2,'waiter','web','Tables, orders, kitchen tickets and bills.',1,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(29,2,'chef','web','Kitchen display and wastage.',1,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(30,2,'bartender','web','Bar display and bar orders.',1,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(31,2,'store-keeper','web','Inventory: receive, issue, transfer, stock count.',1,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(32,2,'procurement-officer','web','Requisitions, RFQs, purchase orders, vendors.',1,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(33,2,'accountant','web','Accounting, expenses, vendor bills, payments, bank reconciliation.',1,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(34,2,'hr-manager','web','Employees, attendance, leave, shifts.',1,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(35,2,'payroll-officer','web','Payroll runs, payslips, loans.',1,'2026-09-27 11:24:59','2026-09-27 11:24:59'),
(36,2,'auditor','web','Read-only access to everything, including the audit log.',1,'2026-09-27 11:24:59','2026-09-27 11:24:59');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `property_id` bigint(20) unsigned DEFAULT NULL,
  `property_scope` bigint(20) unsigned GENERATED ALWAYS AS (coalesce(`property_id`,0)) STORED,
  `key` varchar(100) NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`value`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_tenant_id_property_scope_key_unique` (`tenant_id`,`property_scope`,`key`),
  CONSTRAINT `settings_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `tenant_modules`
--

DROP TABLE IF EXISTS `tenant_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_modules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `module` varchar(50) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_modules_tenant_id_module_unique` (`tenant_id`,`module`),
  CONSTRAINT `tenant_modules_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_modules`
--

LOCK TABLES `tenant_modules` WRITE;
/*!40000 ALTER TABLE `tenant_modules` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `tenant_modules` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `tenants`
--

DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(30) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `suspended_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenants_slug_unique` (`slug`),
  KEY `tenants_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenants`
--

LOCK TABLES `tenants` WRITE;
/*!40000 ALTER TABLE `tenants` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `tenants` VALUES
(1,'sunrise','Sunrise Resorts Ltd','info@sunrise.test','active',NULL,NULL,'2026-09-27 11:24:54','2026-09-27 11:24:54'),
(2,'greenvalley','Green Valley Resort','info@greenvalley.test','active',NULL,NULL,'2026-09-27 11:24:54','2026-09-27 11:24:54');
/*!40000 ALTER TABLE `tenants` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `timezones`
--

DROP TABLE IF EXISTS `timezones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `timezones` (
  `name` varchar(64) NOT NULL,
  `country_code` char(2) DEFAULT NULL,
  `utc_offset` varchar(6) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`name`),
  KEY `timezones_country_code_index` (`country_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timezones`
--

LOCK TABLES `timezones` WRITE;
/*!40000 ALTER TABLE `timezones` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `timezones` VALUES
('Africa/Abidjan','CI','+00:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Accra','GH','+00:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Addis_Ababa','ET','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Algiers','DZ','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Asmara','ER','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Bamako','ML','+00:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Bangui','CF','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Banjul','GM','+00:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Bissau','GW','+00:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Blantyre','MW','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Brazzaville','CG','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Bujumbura','BI','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Cairo','EG','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Casablanca','MA','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Ceuta','ES','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Conakry','GN','+00:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Dakar','SN','+00:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Dar_es_Salaam','TZ','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Djibouti','DJ','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Douala','CM','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/El_Aaiun','EH','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Freetown','SL','+00:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Gaborone','BW','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Harare','ZW','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Johannesburg','ZA','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Juba','SS','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Kampala','UG','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Khartoum','SD','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Kigali','RW','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Kinshasa','CD','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Lagos','NG','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Libreville','GA','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Lome','TG','+00:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Luanda','AO','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Lubumbashi','CD','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Lusaka','ZM','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Malabo','GQ','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Maputo','MZ','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Maseru','LS','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Mbabane','SZ','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Mogadishu','SO','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Monrovia','LR','+00:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Nairobi','KE','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Ndjamena','TD','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Niamey','NE','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Nouakchott','MR','+00:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Ouagadougou','BF','+00:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Porto-Novo','BJ','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Sao_Tome','ST','+00:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Tripoli','LY','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Tunis','TN','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Africa/Windhoek','NA','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Adak','US','-09:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Anchorage','US','-08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Anguilla','AI','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Antigua','AG','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Araguaina','BR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Argentina/Buenos_Aires','AR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Argentina/Catamarca','AR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Argentina/Cordoba','AR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Argentina/Jujuy','AR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Argentina/La_Rioja','AR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Argentina/Mendoza','AR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Argentina/Rio_Gallegos','AR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Argentina/Salta','AR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Argentina/San_Juan','AR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Argentina/San_Luis','AR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Argentina/Tucuman','AR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Argentina/Ushuaia','AR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Aruba','AW','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Asuncion','PY','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Atikokan','CA','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Bahia','BR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Bahia_Banderas','MX','-06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Barbados','BB','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Belem','BR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Belize','BZ','-06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Blanc-Sablon','CA','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Boa_Vista','BR','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Bogota','CO','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Boise','US','-06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Cambridge_Bay','CA','-06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Campo_Grande','BR','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Cancun','MX','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Caracas','VE','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Cayenne','GF','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Cayman','KY','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Chicago','US','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Chihuahua','MX','-06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Ciudad_Juarez','MX','-06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Costa_Rica','CR','-06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Coyhaique','CL','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Creston','CA','-07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Cuiaba','BR','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Curacao','CW','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Danmarkshavn','GL','+00:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Dawson','CA','-07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Dawson_Creek','CA','-07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Denver','US','-06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Detroit','US','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Dominica','DM','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Edmonton','CA','-06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Eirunepe','BR','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/El_Salvador','SV','-06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Fort_Nelson','CA','-07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Fortaleza','BR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Glace_Bay','CA','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Goose_Bay','CA','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Grand_Turk','TC','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Grenada','GD','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Guadeloupe','GP','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Guatemala','GT','-06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Guayaquil','EC','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Guyana','GY','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Halifax','CA','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Havana','CU','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Hermosillo','MX','-07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Indiana/Indianapolis','US','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Indiana/Knox','US','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Indiana/Marengo','US','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Indiana/Petersburg','US','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Indiana/Tell_City','US','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Indiana/Vevay','US','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Indiana/Vincennes','US','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Indiana/Winamac','US','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Inuvik','CA','-06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Iqaluit','CA','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Jamaica','JM','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Juneau','US','-08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Kentucky/Louisville','US','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Kentucky/Monticello','US','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Kralendijk','BQ','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/La_Paz','BO','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Lima','PE','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Los_Angeles','US','-07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Lower_Princes','SX','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Maceio','BR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Managua','NI','-06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Manaus','BR','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Marigot','MF','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Martinique','MQ','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Matamoros','MX','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Mazatlan','MX','-07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Menominee','US','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Merida','MX','-06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Metlakatla','US','-08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Mexico_City','MX','-06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Miquelon','PM','-02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Moncton','CA','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Monterrey','MX','-06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Montevideo','UY','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Montserrat','MS','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Nassau','BS','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/New_York','US','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Nome','US','-08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Noronha','BR','-02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/North_Dakota/Beulah','US','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/North_Dakota/Center','US','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/North_Dakota/New_Salem','US','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Nuuk','GL','-01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Ojinaga','MX','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Panama','PA','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Paramaribo','SR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Phoenix','US','-07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Port_of_Spain','TT','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Port-au-Prince','HT','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Porto_Velho','BR','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Puerto_Rico','PR','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Punta_Arenas','CL','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Rankin_Inlet','CA','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Recife','BR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Regina','CA','-06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Resolute','CA','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Rio_Branco','BR','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Santarem','BR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Santiago','CL','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Santo_Domingo','DO','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Sao_Paulo','BR','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Scoresbysund','GL','-01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Sitka','US','-08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/St_Barthelemy','BL','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/St_Johns','CA','-02:30','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/St_Kitts','KN','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/St_Lucia','LC','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/St_Thomas','VI','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/St_Vincent','VC','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Swift_Current','CA','-06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Tegucigalpa','HN','-06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Thule','GL','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Tijuana','MX','-07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Toronto','CA','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Tortola','VG','-04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Vancouver','CA','-07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Whitehorse','CA','-07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Winnipeg','CA','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('America/Yakutat','US','-08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Antarctica/Casey','AQ','+08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Antarctica/Davis','AQ','+07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Antarctica/DumontDUrville','AQ','+10:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Antarctica/Macquarie','AU','+10:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Antarctica/Mawson','AQ','+05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Antarctica/McMurdo','AQ','+13:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Antarctica/Palmer','AQ','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Antarctica/Rothera','AQ','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Antarctica/Syowa','AQ','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Antarctica/Troll',NULL,'+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Antarctica/Vostok','AQ','+05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Arctic/Longyearbyen','SJ','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Aden','YE','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Almaty','KZ','+05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Amman','JO','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Anadyr','RU','+12:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Aqtau','KZ','+05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Aqtobe','KZ','+05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Ashgabat','TM','+05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Atyrau','KZ','+05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Baghdad','IQ','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Bahrain','BH','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Baku','AZ','+04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Bangkok','TH','+07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Barnaul','RU','+07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Beirut','LB','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Bishkek','KG','+06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Brunei','BN','+08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Chita','RU','+09:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Colombo','LK','+05:30','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Damascus','SY','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Dhaka','BD','+06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Dili','TL','+09:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Dubai','AE','+04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Dushanbe','TJ','+05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Famagusta','CY','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Gaza','PS','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Hebron','PS','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Ho_Chi_Minh','VN','+07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Hong_Kong','HK','+08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Hovd','MN','+07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Irkutsk','RU','+08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Jakarta','ID','+07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Jayapura','ID','+09:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Jerusalem','IL','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Kabul','AF','+04:30','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Kamchatka','RU','+12:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Karachi','PK','+05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Kathmandu','NP','+05:45','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Khandyga','RU','+09:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Kolkata','IN','+05:30','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Krasnoyarsk','RU','+07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Kuala_Lumpur','MY','+08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Kuching','MY','+08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Kuwait','KW','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Macau','MO','+08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Magadan','RU','+11:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Makassar','ID','+08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Manila','PH','+08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Muscat','OM','+04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Nicosia','CY','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Novokuznetsk','RU','+07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Novosibirsk','RU','+07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Omsk','RU','+06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Oral','KZ','+05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Phnom_Penh','KH','+07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Pontianak','ID','+07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Pyongyang','KP','+09:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Qatar','QA','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Qostanay','KZ','+05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Qyzylorda','KZ','+05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Riyadh','SA','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Sakhalin','RU','+11:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Samarkand','UZ','+05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Seoul','KR','+09:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Shanghai','CN','+08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Singapore','SG','+08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Srednekolymsk','RU','+11:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Taipei','TW','+08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Tashkent','UZ','+05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Tbilisi','GE','+04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Tehran','IR','+03:30','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Thimphu','BT','+06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Tokyo','JP','+09:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Tomsk','RU','+07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Ulaanbaatar','MN','+08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Urumqi','CN','+06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Ust-Nera','RU','+10:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Vientiane','LA','+07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Vladivostok','RU','+10:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Yakutsk','RU','+09:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Yangon','MM','+06:30','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Yekaterinburg','RU','+05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Asia/Yerevan','AM','+04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Atlantic/Azores','PT','+00:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Atlantic/Bermuda','BM','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Atlantic/Canary','ES','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Atlantic/Cape_Verde','CV','-01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Atlantic/Faroe','FO','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Atlantic/Madeira','PT','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Atlantic/Reykjavik','IS','+00:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Atlantic/South_Georgia','GS','-02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Atlantic/St_Helena','SH','+00:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Atlantic/Stanley','FK','-03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Australia/Adelaide','AU','+09:30','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Australia/Brisbane','AU','+10:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Australia/Broken_Hill','AU','+09:30','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Australia/Darwin','AU','+09:30','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Australia/Eucla','AU','+08:45','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Australia/Hobart','AU','+10:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Australia/Lindeman','AU','+10:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Australia/Lord_Howe','AU','+10:30','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Australia/Melbourne','AU','+10:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Australia/Perth','AU','+08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Australia/Sydney','AU','+10:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Amsterdam','NL','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Andorra','AD','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Astrakhan','RU','+04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Athens','GR','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Belgrade','RS','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Berlin','DE','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Bratislava','SK','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Brussels','BE','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Bucharest','RO','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Budapest','HU','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Busingen','DE','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Chisinau','MD','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Copenhagen','DK','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Dublin','IE','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Gibraltar','GI','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Guernsey','GG','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Helsinki','FI','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Isle_of_Man','IM','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Istanbul','TR','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Jersey','JE','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Kaliningrad','RU','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Kirov','RU','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Kyiv','UA','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Lisbon','PT','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Ljubljana','SI','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/London','GB','+01:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Luxembourg','LU','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Madrid','ES','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Malta','MT','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Mariehamn','AX','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Minsk','BY','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Monaco','MC','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Moscow','RU','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Oslo','NO','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Paris','FR','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Podgorica','ME','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Prague','CZ','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Riga','LV','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Rome','IT','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Samara','RU','+04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/San_Marino','SM','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Sarajevo','BA','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Saratov','RU','+04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Simferopol','UA','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Skopje','MK','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Sofia','BG','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Stockholm','SE','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Tallinn','EE','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Tirane','AL','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Ulyanovsk','RU','+04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Vaduz','LI','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Vatican','VA','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Vienna','AT','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Vilnius','LT','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Volgograd','RU','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Warsaw','PL','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Zagreb','HR','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Europe/Zurich','CH','+02:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Indian/Antananarivo','MG','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Indian/Chagos','IO','+06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Indian/Christmas','CX','+07:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Indian/Cocos','CC','+06:30','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Indian/Comoro','KM','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Indian/Kerguelen','TF','+05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Indian/Mahe','SC','+04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Indian/Maldives','MV','+05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Indian/Mauritius','MU','+04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Indian/Mayotte','YT','+03:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Indian/Reunion','RE','+04:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Apia','WS','+13:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Auckland','NZ','+13:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Bougainville','PG','+11:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Chatham','NZ','+13:45','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Chuuk','FM','+10:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Easter','CL','-05:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Efate','VU','+11:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Fakaofo','TK','+13:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Fiji','FJ','+12:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Funafuti','TV','+12:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Galapagos','EC','-06:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Gambier','PF','-09:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Guadalcanal','SB','+11:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Guam','GU','+10:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Honolulu','US','-10:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Kanton','KI','+13:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Kiritimati','KI','+14:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Kosrae','FM','+11:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Kwajalein','MH','+12:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Majuro','MH','+12:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Marquesas','PF','-09:30','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Midway','UM','-11:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Nauru','NR','+12:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Niue','NU','-11:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Norfolk','NF','+11:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Noumea','NC','+11:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Pago_Pago','AS','-11:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Palau','PW','+09:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Pitcairn','PN','-08:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Pohnpei','FM','+11:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Port_Moresby','PG','+10:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Rarotonga','CK','-10:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Saipan','MP','+10:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Tahiti','PF','-10:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Tarawa','KI','+12:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Tongatapu','TO','+13:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Wake','UM','+12:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('Pacific/Wallis','WF','+12:00','2026-09-27 11:24:54','2026-09-27 11:24:54'),
('UTC',NULL,'+00:00','2026-09-27 11:24:54','2026-09-27 11:24:54');
/*!40000 ALTER TABLE `timezones` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `locale` varchar(10) NOT NULL DEFAULT 'en',
  `theme` varchar(10) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `invited_at` timestamp NULL DEFAULT NULL,
  `invited_by` bigint(20) unsigned DEFAULT NULL,
  `deactivated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_tenant_id_email_unique` (`tenant_id`,`email`),
  KEY `users_invited_by_foreign` (`invited_by`),
  KEY `users_tenant_id_status_index` (`tenant_id`,`status`),
  CONSTRAINT `users_invited_by_foreign` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `users` VALUES
(1,1,'Rahim Uddin','owner@sunrise.test','2026-09-27 11:24:55','$2y$12$wk9Kh7fXKQYxbCrGPCAY7OP6gfVn3s1u6ojLsQmP/IAdmZJ5J1GnC','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:24:55','2026-09-27 15:52:36','2026-09-27 15:52:36','127.0.0.1',NULL,NULL,NULL),
(2,1,'General Manager','gm@sunrise.test','2026-09-27 11:24:55','$2y$12$KDqbeO0cMXwnaFPoSajKBuBWqYuNrueWsx7zpBVxyh3XnyNQxfAAq','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:24:55','2026-09-27 11:24:55',NULL,NULL,NULL,NULL,NULL),
(3,1,'Front Office Manager','fomanager@sunrise.test','2026-09-27 11:24:55','$2y$12$oKGMM3WffiuEKc5eDI4Up.7FSPP4rwieaoMjrhYW51J7/JGlCAFRm','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:24:55','2026-09-27 11:24:55',NULL,NULL,NULL,NULL,NULL),
(4,1,'Nusrat Jahan','frontdesk@sunrise.test','2026-09-27 11:24:55','$2y$12$2UmCMI6sCphToagUSuGgmemur5vpnmNSpYl7kGiK6p/PKCYWHBfDi','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:24:55','2026-09-27 11:24:55',NULL,NULL,NULL,NULL,NULL),
(5,1,'Reservation Agent','reservations@sunrise.test','2026-09-27 11:24:56','$2y$12$F1FIamESSZB8rvEoLD0LkOvkl.GOu9ox8Zw6whvROW.Zs9qLs1Vqa','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:24:56','2026-09-27 11:24:56',NULL,NULL,NULL,NULL,NULL),
(6,1,'Housekeeping Supervisor','housekeeping@sunrise.test','2026-09-27 11:24:56','$2y$12$K4zflx0ZZYogrPUWcK6sEuz7PrzOXshwDozum7UwaasZDMjyXWa1O','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:24:56','2026-09-27 11:24:56',NULL,NULL,NULL,NULL,NULL),
(7,1,'Maintenance Technician','maintenance@sunrise.test','2026-09-27 11:24:56','$2y$12$KIh8klbbHCoXmcHs6Y1g.uu93HEHhH5SMeKwxFR7bLGZXFsLoHm/2','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:24:56','2026-09-27 11:24:56',NULL,NULL,NULL,NULL,NULL),
(8,1,'F&B Manager','fnb@sunrise.test','2026-09-27 11:24:56','$2y$12$FcEl8PX/HVXUWvMvaeRQbOa51ZPhqf/3QpUX4QJpTII0DhDXbHaeq','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:24:56','2026-09-27 11:24:56',NULL,NULL,NULL,NULL,NULL),
(9,1,'Outlet Cashier','cashier@sunrise.test','2026-09-27 11:24:57','$2y$12$EZRalhVOnWGQ0RL0EL31vutzW.A6779pcZ9Gqo8tEDiX270sjFFne','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:24:57','2026-09-27 11:24:57',NULL,NULL,NULL,NULL,NULL),
(10,1,'Waiter / Captain','waiter@sunrise.test','2026-09-27 11:24:57','$2y$12$nueCzpcxaVvdgtUoh/g1yOzPVnt7DCOAcAOfA5Lc2VCslGlxobNuC','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:24:57','2026-09-27 11:24:57',NULL,NULL,NULL,NULL,NULL),
(11,1,'Chef / Kitchen Staff','chef@sunrise.test','2026-09-27 11:24:57','$2y$12$SPHZyuOzVkOwlIkuqf8wfeSoEYip/ahmsnEEJ5QveCaegOqkZilDS','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:24:57','2026-09-27 11:24:57',NULL,NULL,NULL,NULL,NULL),
(12,1,'Bartender','bartender@sunrise.test','2026-09-27 11:24:57','$2y$12$vhfU0UZk1PSMgq5vxeTIMuNNYEQr5R1n0.d2ikNe69Z0kxP.nB2se','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:24:57','2026-09-27 11:24:57',NULL,NULL,NULL,NULL,NULL),
(13,1,'Store Keeper','store@sunrise.test','2026-09-27 11:24:58','$2y$12$TTTYUH/fE//YzQ4MIJ5e4OVlY8asa8CDA0073RuUOQuziOgHkGuIy','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:24:58','2026-09-27 11:24:58',NULL,NULL,NULL,NULL,NULL),
(14,1,'Procurement Officer','procurement@sunrise.test','2026-09-27 11:24:58','$2y$12$TY4dznCjViTngY7A39OFdueBRoYj5pnmYx31tkQOP4E3UQ00ix2vi','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:24:58','2026-09-27 11:24:58',NULL,NULL,NULL,NULL,NULL),
(15,1,'Farzana Akter','accountant@sunrise.test','2026-09-27 11:24:58','$2y$12$.VpdiDpy1L69xyflViPNbuqKtQarKRWndSYio3J5BBjmp7lhhNi6W','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:24:58','2026-09-27 11:24:58',NULL,NULL,NULL,NULL,NULL),
(16,1,'HR Manager','hr@sunrise.test','2026-09-27 11:24:58','$2y$12$AeVATIOeebv9W0ODMhEiMuZhDtjoVim5rBVlqzqH1IECTqG6SIXbO','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:24:58','2026-09-27 11:24:58',NULL,NULL,NULL,NULL,NULL),
(17,1,'Payroll Officer','payroll@sunrise.test','2026-09-27 11:24:59','$2y$12$k4w9Ejbsg2IgT1df6qH4d.QNDV7gFUYk7QwbYFpZXYZgZukbfqopa','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:24:59','2026-09-27 11:24:59',NULL,NULL,NULL,NULL,NULL),
(18,1,'Auditor','auditor@sunrise.test','2026-09-27 11:24:59','$2y$12$ZVEjJp/eDgvU.mzk9pRpJes6yhg4uB1mPGrunAdrP2l58wiYQUCUq','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:24:59','2026-09-27 11:24:59',NULL,NULL,NULL,NULL,NULL),
(19,2,'Tanvir Ahmed','owner@greenvalley.test','2026-09-27 11:24:59','$2y$12$2izkC8/m4tzI73JJhhkh3.9y1JXqdrOyERFKOdYkIfsS7RHqOWYNC','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:24:59','2026-09-27 11:24:59',NULL,NULL,NULL,NULL,NULL),
(20,2,'General Manager','gm@greenvalley.test','2026-09-27 11:25:00','$2y$12$8lr7g3iPLF7GPbzz/kEcPOrkLZeC8JxnIPjUbeMLJ8jreCLH0s7a6','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:25:00','2026-09-27 11:25:00',NULL,NULL,NULL,NULL,NULL),
(21,2,'Front Office Manager','fomanager@greenvalley.test','2026-09-27 11:25:00','$2y$12$d6Y1T6tuzhGJe8PoMkD8.eujuVuvg3nvs47jVaCIx0XbPnp41REB2','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:25:00','2026-09-27 11:25:00',NULL,NULL,NULL,NULL,NULL),
(22,2,'Front Desk Agent','frontdesk@greenvalley.test','2026-09-27 11:25:00','$2y$12$EOdIAhTL5CvAqG9VxWT7hetOUuaS.nAD/8xW2nnr79VxEQT99djVC','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:25:00','2026-09-27 11:25:00',NULL,NULL,NULL,NULL,NULL),
(23,2,'Reservation Agent','reservations@greenvalley.test','2026-09-27 11:25:00','$2y$12$K1Yr3jYYgreEl1kF500cY.XNMn8ECy73ApeQEp4IBKTDyRvywwUWi','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:25:00','2026-09-27 11:25:00',NULL,NULL,NULL,NULL,NULL),
(24,2,'Housekeeping Supervisor','housekeeping@greenvalley.test','2026-09-27 11:25:01','$2y$12$zX2mUTRAku/BGmPrQ/jzA.GZ1JC40j0j6ph5yV6wUHAsae85V8zkO','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:25:01','2026-09-27 11:25:01',NULL,NULL,NULL,NULL,NULL),
(25,2,'Maintenance Technician','maintenance@greenvalley.test','2026-09-27 11:25:01','$2y$12$hDuwHAGgCY0rUK/Hw5ezj.dWJpLeOf4X4ldCx45Mg7Fz8az8iAs5S','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:25:01','2026-09-27 11:25:01',NULL,NULL,NULL,NULL,NULL),
(26,2,'F&B Manager','fnb@greenvalley.test','2026-09-27 11:25:01','$2y$12$bmy2Gdgwommeo02VfGsVXu8.jAU2fkbjMDLyaABtftZn4v7bDqw0O','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:25:01','2026-09-27 11:25:01',NULL,NULL,NULL,NULL,NULL),
(27,2,'Outlet Cashier','cashier@greenvalley.test','2026-09-27 11:25:01','$2y$12$mGes05oWfIudnD3/kiXjp.HIv2osckmYA55Ot0TQGb77Vh7SCwy0a','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:25:01','2026-09-27 11:25:01',NULL,NULL,NULL,NULL,NULL),
(28,2,'Waiter / Captain','waiter@greenvalley.test','2026-09-27 11:25:02','$2y$12$c6FwW3b5N73qFvS2OQ.5JeYgrr0JVg1zr3t5A4QZxj5.ihAF29wCe','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:25:02','2026-09-27 11:25:02',NULL,NULL,NULL,NULL,NULL),
(29,2,'Chef / Kitchen Staff','chef@greenvalley.test','2026-09-27 11:25:02','$2y$12$Ni4lUdMTVzicZwckSJqWYOo28W9lp7uD5BPdpupruYdBA1NjfqWom','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:25:02','2026-09-27 11:25:02',NULL,NULL,NULL,NULL,NULL),
(30,2,'Bartender','bartender@greenvalley.test','2026-09-27 11:25:02','$2y$12$5R73CDHMDNc8ZeUzkYxSce64gRpVLYnMLcOdQ/p7X0OHk3zWpdHbq','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:25:02','2026-09-27 11:25:02',NULL,NULL,NULL,NULL,NULL),
(31,2,'Store Keeper','store@greenvalley.test','2026-09-27 11:25:02','$2y$12$cGh235gkJlLCOxKyTva0c.AgNf/XRrNGVwoE5YO3W6UA1d/Ty88RS','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:25:02','2026-09-27 11:25:02',NULL,NULL,NULL,NULL,NULL),
(32,2,'Procurement Officer','procurement@greenvalley.test','2026-09-27 11:25:03','$2y$12$5A6VmyL08YhO5KVjoBGLB.Rn.cgg00zriNdnd0m7LfEkIhViIJ9Z6','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:25:03','2026-09-27 11:25:03',NULL,NULL,NULL,NULL,NULL),
(33,2,'Accountant','accountant@greenvalley.test','2026-09-27 11:25:03','$2y$12$ixw92ng3C/5PkpqYIPoSfe3n1TpsryIK7qCQ40Bt8cOOj2.ARpXVe','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:25:03','2026-09-27 11:25:03',NULL,NULL,NULL,NULL,NULL),
(34,2,'HR Manager','hr@greenvalley.test','2026-09-27 11:25:03','$2y$12$mO1GxtNHqzuRBMLg1eb5ou93Jnps6J2rgEIFdU.jvHy2XU1bSSIwq','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:25:03','2026-09-27 11:25:03',NULL,NULL,NULL,NULL,NULL),
(35,2,'Payroll Officer','payroll@greenvalley.test','2026-09-27 11:25:03','$2y$12$Npr5OSFnl3jNRrXJGVK4muBqYjXGPnEGtVHM5atHB4CXlqd94Mksi','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:25:03','2026-09-27 11:25:03',NULL,NULL,NULL,NULL,NULL),
(36,2,'Auditor','auditor@greenvalley.test','2026-09-27 11:25:04','$2y$12$aaHoYiwKg6hHQPMLEm/Dh.8QiM4yLWtRhg3Y1bZvwnRcpM507HSCO','active','en',NULL,NULL,NULL,NULL,NULL,'2026-09-27 11:25:04','2026-09-27 11:25:04',NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
commit;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-09-28  3:55:52
