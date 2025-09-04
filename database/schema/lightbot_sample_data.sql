/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.4.7-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: lightbot
-- ------------------------------------------------------
-- Server version	11.4.7-MariaDB-0ubuntu0.25.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Dumping data for table `activity_log`
--

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
INSERT INTO `activity_log` (`id`, `timestamp`, `user_id`, `action`, `component`, `ip_address`, `processing_time_ms`, `error_message`) VALUES (37,'2025-08-30 08:58:23','fp_5c7056058f8fbbb213e07d202117d184a01b825bd00ea942dcc6bce2108b515b_mex4eqxa','first_message','rate_limiter',NULL,NULL,NULL),
(38,'2025-08-30 08:58:49','fp_5c7056058f8fbbb213e07d202117d184a01b825bd00ea942dcc6bce2108b515b_mex4eqxa','message_sent','rate_limiter',NULL,NULL,NULL),
(39,'2025-08-30 10:45:39','fp_213f0fd308e2f09e80bad00cd14320c78bb9ef929bd7ba2b7aef9f4f959e664f_mey0syxw','first_message','rate_limiter',NULL,NULL,NULL),
(40,'2025-08-30 10:49:57','fp_0ae0368f39b350df397454e99d35a512d767639179598b3676d2aac1d37b1930_mey539zd','first_message','rate_limiter',NULL,NULL,NULL),
(41,'2025-08-30 10:50:58','fp_0ae0368f39b350df397454e99d35a512d767639179598b3676d2aac1d37b1930_mey539zd','message_sent','rate_limiter',NULL,NULL,NULL),
(42,'2025-08-30 10:51:45','fp_0ae0368f39b350df397454e99d35a512d767639179598b3676d2aac1d37b1930_mey539zd','message_sent','rate_limiter',NULL,NULL,NULL),
(43,'2025-08-30 10:52:31','fp_d4b3bea8fdca7dc4f02357927228356bf45d3dff0359573723f98df00be169bb_mey56kc6','first_message','rate_limiter',NULL,NULL,NULL),
(44,'2025-08-30 10:56:33','fp_434c6509681a923c2fb4cb5112694c60e06ee6e7a7fc78e8c4d4d8a8b4e89983_mey5ai3p','first_message','rate_limiter',NULL,NULL,NULL),
(45,'2025-08-30 11:09:30','fp_5c7056058f8fbbb213e07d202117d184a01b825bd00ea942dcc6bce2108b515b_mex4eqxa','message_sent','rate_limiter',NULL,NULL,NULL),
(46,'2025-08-30 11:10:03','fp_5c7056058f8fbbb213e07d202117d184a01b825bd00ea942dcc6bce2108b515b_mex4eqxa','message_sent','rate_limiter',NULL,NULL,NULL),
(47,'2025-08-30 11:10:58','fp_5c7056058f8fbbb213e07d202117d184a01b825bd00ea942dcc6bce2108b515b_mex4eqxa','message_sent','rate_limiter',NULL,NULL,NULL),
(48,'2025-08-30 11:18:41','fp_5c7056058f8fbbb213e07d202117d184a01b825bd00ea942dcc6bce2108b515b_mex4eqxa','message_sent','rate_limiter',NULL,NULL,NULL),
(49,'2025-08-30 16:51:53','fp_dc9171a611669d7e03337cad982eee4e557033503c5851a67a2776a0c8120318_meyhyqsm','first_message','rate_limiter',NULL,NULL,NULL),
(50,'2025-08-30 17:02:35','fp_434c6509681a923c2fb4cb5112694c60e06ee6e7a7fc78e8c4d4d8a8b4e89983_mey5ai3p','message_sent','rate_limiter',NULL,NULL,NULL),
(51,'2025-08-30 17:03:08','fp_434c6509681a923c2fb4cb5112694c60e06ee6e7a7fc78e8c4d4d8a8b4e89983_mey5ai3p','message_sent','rate_limiter',NULL,NULL,NULL),
(52,'2025-08-30 17:03:28','fp_434c6509681a923c2fb4cb5112694c60e06ee6e7a7fc78e8c4d4d8a8b4e89983_mey5ai3p','message_sent','rate_limiter',NULL,NULL,NULL),
(53,'2025-08-30 17:03:50','fp_434c6509681a923c2fb4cb5112694c60e06ee6e7a7fc78e8c4d4d8a8b4e89983_mey5ai3p','message_sent','rate_limiter',NULL,NULL,NULL),
(54,'2025-08-30 17:04:01','fp_434c6509681a923c2fb4cb5112694c60e06ee6e7a7fc78e8c4d4d8a8b4e89983_mey5ai3p','message_sent','rate_limiter',NULL,NULL,NULL),
(55,'2025-08-30 17:04:50','fp_434c6509681a923c2fb4cb5112694c60e06ee6e7a7fc78e8c4d4d8a8b4e89983_mey5ai3p','message_sent','rate_limiter',NULL,NULL,NULL),
(56,'2025-08-31 16:58:11','fp_3f827a5196d0141c963465c5a0d02226e42ffe1d1a925d3c582c8a965ef9197e_mezxn1ay','first_message','rate_limiter',NULL,NULL,NULL),
(57,'2025-09-03 06:59:24','fp_202cffdce84e9ac2abdc5e461b14514da7826e6814e55a52ce358c7a4cc69a05_mf3mhmaj','first_message','rate_limiter',NULL,NULL,NULL),
(58,'2025-09-03 07:08:05','fp_202cffdce84e9ac2abdc5e461b14514da7826e6814e55a52ce358c7a4cc69a05_mf3mhmaj','message_sent','rate_limiter',NULL,NULL,NULL),
(59,'2025-09-03 07:33:43','fp_8f2a7e95421af1a25092fc043cecf2f92497dacd59d975219fe60c53bc64286b_mf3mzucd','first_message','rate_limiter',NULL,NULL,NULL),
(60,'2025-09-03 07:35:23','fp_34e8790c3f813bf77786a2968b9bd9b03a05e9c4cb07f3f51b7e3103e22e18c9_mf3nwgnm','first_message','rate_limiter',NULL,NULL,NULL),
(61,'2025-09-03 07:49:59','fp_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa_mf3ngj0w','first_message','rate_limiter',NULL,NULL,NULL),
(62,'2025-09-03 08:00:07','fp_8f2a7e95421af1a25092fc043cecf2f92497dacd59d975219fe60c53bc64286b_mf3mzucd','message_sent','rate_limiter',NULL,NULL,NULL),
(63,'2025-09-03 08:02:50','fp_8f2a7e95421af1a25092fc043cecf2f92497dacd59d975219fe60c53bc64286b_mf3mzucd','message_sent','rate_limiter',NULL,NULL,NULL),
(64,'2025-09-03 08:11:51','fp_8f2a7e95421af1a25092fc043cecf2f92497dacd59d975219fe60c53bc64286b_mf3mzucd','message_sent','rate_limiter',NULL,NULL,NULL),
(65,'2025-09-03 08:18:56','fp_8f2a7e95421af1a25092fc043cecf2f92497dacd59d975219fe60c53bc64286b_mf3mzucd','message_sent','rate_limiter',NULL,NULL,NULL),
(66,'2025-09-03 08:20:49','fp_8f2a7e95421af1a25092fc043cecf2f92497dacd59d975219fe60c53bc64286b_mf3mzucd','message_sent','rate_limiter',NULL,NULL,NULL),
(67,'2025-09-03 08:28:52','fp_202cffdce84e9ac2abdc5e461b14514da7826e6814e55a52ce358c7a4cc69a05_mf3mhmaj','message_sent','rate_limiter',NULL,NULL,NULL),
(68,'2025-09-03 17:25:57','fp_8f2a7e95421af1a25092fc043cecf2f92497dacd59d975219fe60c53bc64286b_mf3mzucd','message_sent','rate_limiter',NULL,NULL,NULL),
(69,'2025-09-03 17:26:24','fp_8f2a7e95421af1a25092fc043cecf2f92497dacd59d975219fe60c53bc64286b_mf3mzucd','message_sent','rate_limiter',NULL,NULL,NULL),
(70,'2025-09-03 19:53:27','fp_8f2a7e95421af1a25092fc043cecf2f92497dacd59d975219fe60c53bc64286b_mf3mzucd','message_sent','rate_limiter',NULL,NULL,NULL),
(71,'2025-09-03 19:54:00','fp_8f2a7e95421af1a25092fc043cecf2f92497dacd59d975219fe60c53bc64286b_mf3mzucd','message_sent','rate_limiter',NULL,NULL,NULL),
(72,'2025-09-03 20:33:46','fp_7dfa8b656566008293164adf39df1426062667d57e5661d77b09268777733e4a_mf4fnajc','first_message','rate_limiter',NULL,NULL,NULL),
(73,'2025-09-03 20:34:30','fp_7dfa8b656566008293164adf39df1426062667d57e5661d77b09268777733e4a_mf4fnajc','message_sent','rate_limiter',NULL,NULL,NULL),
(74,'2025-09-03 20:36:02','fp_7dfa8b656566008293164adf39df1426062667d57e5661d77b09268777733e4a_mf4fnajc','message_sent','rate_limiter',NULL,NULL,NULL),
(75,'2025-09-03 20:38:18','fp_7dfa8b656566008293164adf39df1426062667d57e5661d77b09268777733e4a_mf4fnajc','message_sent','rate_limiter',NULL,NULL,NULL),
(76,'2025-09-03 20:40:05','fp_7dfa8b656566008293164adf39df1426062667d57e5661d77b09268777733e4a_mf4fnajc','message_sent','rate_limiter',NULL,NULL,NULL),
(77,'2025-09-03 20:42:51','fp_7dfa8b656566008293164adf39df1426062667d57e5661d77b09268777733e4a_mf4fnajc','message_sent','rate_limiter',NULL,NULL,NULL),
(78,'2025-09-03 20:44:16','fp_7dfa8b656566008293164adf39df1426062667d57e5661d77b09268777733e4a_mf4fnajc','message_sent','rate_limiter',NULL,NULL,NULL),
(79,'2025-09-04 08:48:11','fp_6955b27e796c080eebc42f6379950fe3b434bff4ccf9160ce34c554613dfc4fa_mf55xw2y','first_message','rate_limiter',NULL,NULL,NULL),
(80,'2025-09-04 15:46:40','fp_a49b7a021ff8f49deca037c62d0ec51d24018d0e5f1336b1bfa9520b3d1569b1_mf5k8jgs','first_message','rate_limiter',NULL,NULL,NULL),
(81,'2025-09-04 15:47:01','fp_a49b7a021ff8f49deca037c62d0ec51d24018d0e5f1336b1bfa9520b3d1569b1_mf5k8jgs','message_sent','rate_limiter',NULL,NULL,NULL),
(82,'2025-09-04 15:47:52','fp_a49b7a021ff8f49deca037c62d0ec51d24018d0e5f1336b1bfa9520b3d1569b1_mf5k8jgs','message_sent','rate_limiter',NULL,NULL,NULL),
(83,'2025-09-04 16:25:21','fp_6955b27e796c080eebc42f6379950fe3b434bff4ccf9160ce34c554613dfc4fa_mf55xw2y','message_sent','rate_limiter',NULL,NULL,NULL),
(84,'2025-09-04 16:31:55','fp_6955b27e796c080eebc42f6379950fe3b434bff4ccf9160ce34c554613dfc4fa_mf55xw2y','message_sent','rate_limiter',NULL,NULL,NULL);
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `user_limits`
--

LOCK TABLES `user_limits` WRITE;
/*!40000 ALTER TABLE `user_limits` DISABLE KEYS */;
INSERT INTO `user_limits` (`id`, `user_id`, `user_id_hash`, `count`, `max_count`, `first_message`, `last_message`, `created`, `is_blocked`, `total_attempts`, `grace_period_start`, `metadata`) VALUES (55,'fp_5c7056058f8fbbb213e07d202117d184a01b825bd00ea942dcc6bce2108b515b_mex4eqxa','fp_5c7056058f8fbbb21...',1,999999,'2025-08-30 08:58:23','2025-09-03 07:03:20','2025-08-30 08:58:23',0,6,NULL,NULL),
(57,'fp_213f0fd308e2f09e80bad00cd14320c78bb9ef929bd7ba2b7aef9f4f959e664f_mey0syxw','fp_213f0fd308e2f09e8...',0,999999,'2025-08-30 10:45:39','2025-09-03 07:03:20','2025-08-30 10:45:39',0,1,NULL,NULL),
(58,'fp_0ae0368f39b350df397454e99d35a512d767639179598b3676d2aac1d37b1930_mey539zd','fp_0ae0368f39b350df3...',0,999999,'2025-08-30 10:49:57','2025-09-03 07:03:20','2025-08-30 10:49:57',0,3,NULL,NULL),
(61,'fp_d4b3bea8fdca7dc4f02357927228356bf45d3dff0359573723f98df00be169bb_mey56kc6','fp_d4b3bea8fdca7dc4f...',0,999999,'2025-08-30 10:52:31','2025-09-03 07:03:20','2025-08-30 10:52:31',0,1,NULL,NULL),
(62,'fp_434c6509681a923c2fb4cb5112694c60e06ee6e7a7fc78e8c4d4d8a8b4e89983_mey5ai3p','fp_434c6509681a923c2...',6,999999,'2025-08-30 10:56:33','2025-09-03 07:03:20','2025-08-30 10:56:33',0,7,NULL,NULL),
(67,'fp_dc9171a611669d7e03337cad982eee4e557033503c5851a67a2776a0c8120318_meyhyqsm','fp_dc9171a611669d7e0...',1,999999,'2025-08-30 16:51:53','2025-09-03 07:03:20','2025-08-30 16:51:53',0,1,NULL,NULL),
(74,'fp_3f827a5196d0141c963465c5a0d02226e42ffe1d1a925d3c582c8a965ef9197e_mezxn1ay','fp_3f827a5196d0141c9...',1,999999,'2025-08-31 16:58:11','2025-09-03 07:03:20','2025-08-31 16:58:11',0,1,NULL,NULL),
(75,'fp_202cffdce84e9ac2abdc5e461b14514da7826e6814e55a52ce358c7a4cc69a05_mf3mhmaj','fp_202cffdce84e9ac2a...',3,999999,'2025-09-03 06:59:24','2025-09-03 08:28:52','2025-09-03 06:59:24',0,3,NULL,NULL),
(77,'fp_8f2a7e95421af1a25092fc043cecf2f92497dacd59d975219fe60c53bc64286b_mf3mzucd','fp_8f2a7e95421af1a25...',10,999999,'2025-09-03 07:33:43','2025-09-03 19:54:00','2025-09-03 07:33:43',0,10,NULL,NULL),
(78,'fp_34e8790c3f813bf77786a2968b9bd9b03a05e9c4cb07f3f51b7e3103e22e18c9_mf3nwgnm','fp_34e8790c3f813bf77...',1,999999,'2025-09-03 07:35:23','2025-09-03 07:35:23','2025-09-03 07:35:23',0,1,NULL,NULL),
(79,'fp_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa_mf3ngj0w','fp_aaaaaaaaaaaaaaaaa...',1,999999,'2025-09-03 07:49:59','2025-09-03 07:49:59','2025-09-03 07:49:59',0,1,NULL,NULL),
(90,'fp_7dfa8b656566008293164adf39df1426062667d57e5661d77b09268777733e4a_mf4fnajc','fp_7dfa8b65656600829...',7,999999,'2025-09-03 20:33:46','2025-09-03 20:44:16','2025-09-03 20:33:46',0,7,NULL,NULL),
(97,'fp_6955b27e796c080eebc42f6379950fe3b434bff4ccf9160ce34c554613dfc4fa_mf55xw2y','fp_6955b27e796c080ee...',3,999999,'2025-09-04 08:48:11','2025-09-04 16:31:55','2025-09-04 08:48:11',0,3,NULL,NULL),
(98,'fp_a49b7a021ff8f49deca037c62d0ec51d24018d0e5f1336b1bfa9520b3d1569b1_mf5k8jgs','fp_a49b7a021ff8f49de...',3,999999,'2025-09-04 15:46:40','2025-09-04 15:47:52','2025-09-04 15:46:40',0,3,NULL,NULL);
/*!40000 ALTER TABLE `user_limits` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2025-09-04 22:06:52
