-- MySQL dump 10.13  Distrib 8.0.45, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: ezicart_db
-- ------------------------------------------------------
-- Server version	8.0.45

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `announcements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_by` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('info','warning','urgent','maintenance') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `target_role` enum('all','buyer','seller','courier','sorting_center') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `announcements_created_by_foreign` (`created_by`),
  CONSTRAINT `announcements_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `area_municipalities`
--

DROP TABLE IF EXISTS `area_municipalities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `area_municipalities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `area_id` bigint unsigned NOT NULL,
  `province` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `municipality` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `province_normalized` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `municipality_normalized` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `area_municipalities_address_unique` (`province_normalized`,`municipality_normalized`),
  KEY `area_municipalities_area_id_foreign` (`area_id`),
  CONSTRAINT `area_municipalities_area_id_foreign` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `area_municipalities`
--

LOCK TABLES `area_municipalities` WRITE;
/*!40000 ALTER TABLE `area_municipalities` DISABLE KEYS */;
/*!40000 ALTER TABLE `area_municipalities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `area_user`
--

DROP TABLE IF EXISTS `area_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `area_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `area_id` bigint unsigned NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `area_user_user_id_area_id_unique` (`user_id`,`area_id`),
  KEY `area_user_area_id_foreign` (`area_id`),
  KEY `area_user_user_id_is_active_is_primary_index` (`user_id`,`is_active`,`is_primary`),
  CONSTRAINT `area_user_area_id_foreign` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `area_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `area_user`
--

LOCK TABLES `area_user` WRITE;
/*!40000 ALTER TABLE `area_user` DISABLE KEYS */;
/*!40000 ALTER TABLE `area_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `areas`
--

DROP TABLE IF EXISTS `areas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `areas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sorting_center_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `areas_code_unique` (`code`),
  KEY `areas_sorting_center_id_is_active_index` (`sorting_center_id`,`is_active`),
  CONSTRAINT `areas_sorting_center_id_foreign` FOREIGN KEY (`sorting_center_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `areas`
--

LOCK TABLES `areas` WRITE;
/*!40000 ALTER TABLE `areas` DISABLE KEYS */;
/*!40000 ALTER TABLE `areas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('laravel-cache-04e7bfa4de47d02062798c6423d27134','i:1;',1791878067),('laravel-cache-04e7bfa4de47d02062798c6423d27134:timer','i:1791878067;',1791878067),('laravel-cache-0c07d4562d542884ab13b95518afa28f','i:2;',1792028986),('laravel-cache-0c07d4562d542884ab13b95518afa28f:timer','i:1792028986;',1792028986),('laravel-cache-0f5a55d3671b1335cbe394ef8d43eaf7','i:1;',1792026992),('laravel-cache-0f5a55d3671b1335cbe394ef8d43eaf7:timer','i:1792026992;',1792026992),('laravel-cache-11aa42e09814ad3ae4340800f3529cfb','i:1;',1792036219),('laravel-cache-11aa42e09814ad3ae4340800f3529cfb:timer','i:1792036219;',1792036219),('laravel-cache-151789139f7e1ab5aa92e2ff34f74b6a','i:1;',1792028396),('laravel-cache-151789139f7e1ab5aa92e2ff34f74b6a:timer','i:1792028396;',1792028396),('laravel-cache-1a835f5aaa3549108219bfa0d6648a2179e06865','i:2;',1792025268),('laravel-cache-1a835f5aaa3549108219bfa0d6648a2179e06865:timer','i:1792025268;',1792025268),('laravel-cache-1b6453892473a467d07372d45eb05abc2031647a','i:2;',1792339990),('laravel-cache-1b6453892473a467d07372d45eb05abc2031647a:timer','i:1792339990;',1792339990),('laravel-cache-1e2438c657e19c0faee3ad1864b636ff','i:1;',1792019290),('laravel-cache-1e2438c657e19c0faee3ad1864b636ff:timer','i:1792019290;',1792019290),('laravel-cache-24d13dd092cce194c81ddb9538e2913a','i:1;',1791880696),('laravel-cache-24d13dd092cce194c81ddb9538e2913a:timer','i:1791880696;',1791880696),('laravel-cache-26291e45f9c7f554ce9c2df0b3ae4ce2','i:1;',1792005912),('laravel-cache-26291e45f9c7f554ce9c2df0b3ae4ce2:timer','i:1792005912;',1792005912),('laravel-cache-29d75d7c73de4253ade94e9454901014','i:1;',1792025312),('laravel-cache-29d75d7c73de4253ade94e9454901014:timer','i:1792025312;',1792025312),('laravel-cache-2a0f043de9368b98e76e18ac08b6ba53','i:1;',1792339087),('laravel-cache-2a0f043de9368b98e76e18ac08b6ba53:timer','i:1792339087;',1792339087),('laravel-cache-34cd65814a0a750ff225012ff8d05bc1','i:2;',1792018270),('laravel-cache-34cd65814a0a750ff225012ff8d05bc1:timer','i:1792018270;',1792018270),('laravel-cache-356a192b7913b04c54574d18c28d46e6395428ab','i:1;',1792026227),('laravel-cache-356a192b7913b04c54574d18c28d46e6395428ab:timer','i:1792026227;',1792026227),('laravel-cache-41816eeeed65df27bb07c3d33ec4c875','i:1;',1791873588),('laravel-cache-41816eeeed65df27bb07c3d33ec4c875:timer','i:1791873588;',1791873588),('laravel-cache-46fd077bd559ae6ed752408e51550c89','i:1;',1792339888),('laravel-cache-46fd077bd559ae6ed752408e51550c89:timer','i:1792339888;',1792339888),('laravel-cache-4b844c6e9c7fc9ddd8407d370c503dd2','i:2;',1791825812),('laravel-cache-4b844c6e9c7fc9ddd8407d370c503dd2:timer','i:1791825812;',1791825812),('laravel-cache-4d1b1e891c3b4f02c0bdf0a3cc2a3c85','i:1;',1792032661),('laravel-cache-4d1b1e891c3b4f02c0bdf0a3cc2a3c85:timer','i:1792032661;',1792032661),('laravel-cache-4ed3c271e9e82bf348134fb46c1e27ca','i:1;',1792017433),('laravel-cache-4ed3c271e9e82bf348134fb46c1e27ca:timer','i:1792017433;',1792017433),('laravel-cache-59e1cdb41eb9ee4f9dab4c941353d872','i:1;',1792036478),('laravel-cache-59e1cdb41eb9ee4f9dab4c941353d872:timer','i:1792036478;',1792036478),('laravel-cache-5bd49996608317ec4f545564749c5df6','i:2;',1792034020),('laravel-cache-5bd49996608317ec4f545564749c5df6:timer','i:1792034020;',1792034020),('laravel-cache-5c785c036466adea360111aa28563bfd556b5fba','i:3;',1791880815),('laravel-cache-5c785c036466adea360111aa28563bfd556b5fba:timer','i:1791880815;',1791880815),('laravel-cache-697cd90837f0071a2586b3b064920984','i:1;',1792335646),('laravel-cache-697cd90837f0071a2586b3b064920984:timer','i:1792335646;',1792335646),('laravel-cache-69b0d654e2b28204d7393d29c583ee3c','i:1;',1792036378),('laravel-cache-69b0d654e2b28204d7393d29c583ee3c:timer','i:1792036378;',1792036378),('laravel-cache-6ab6f68bc3a295b7cdf7a06f3eb0b591','i:1;',1792025267),('laravel-cache-6ab6f68bc3a295b7cdf7a06f3eb0b591:timer','i:1792025267;',1792025267),('laravel-cache-6f83715e590dbc1a8f89f3de65f1c071','i:1;',1792032661),('laravel-cache-6f83715e590dbc1a8f89f3de65f1c071:timer','i:1792032661;',1792032661),('laravel-cache-6fb1cc9c566f3582fab110f043e6e6a2','i:1;',1792339912),('laravel-cache-6fb1cc9c566f3582fab110f043e6e6a2:timer','i:1792339912;',1792339912),('laravel-cache-70550da1d7db19775da8136556f9f8b0','i:1;',1792026587),('laravel-cache-70550da1d7db19775da8136556f9f8b0:timer','i:1792026587;',1792026587),('laravel-cache-75c76c7fcc7d15d2985a1a2fbcf4d25e','i:1;',1792337918),('laravel-cache-75c76c7fcc7d15d2985a1a2fbcf4d25e:timer','i:1792337918;',1792337918),('laravel-cache-77de68daecd823babbb58edb1c8e14d7106e83bb','i:1;',1792340534),('laravel-cache-77de68daecd823babbb58edb1c8e14d7106e83bb:timer','i:1792340534;',1792340534),('laravel-cache-7b52009b64fd0a2a49e6d8a939753077792b0554','i:4;',1792028999),('laravel-cache-7b52009b64fd0a2a49e6d8a939753077792b0554:timer','i:1792028999;',1792028999),('laravel-cache-7f22a42ff7eed9f7097d4a38e2ad8af7','i:1;',1792019246),('laravel-cache-7f22a42ff7eed9f7097d4a38e2ad8af7:timer','i:1792019246;',1792019246),('laravel-cache-8d1bc4a05e1e7101c36d0e7994cc98e1','i:1;',1791880608),('laravel-cache-8d1bc4a05e1e7101c36d0e7994cc98e1:timer','i:1791880608;',1791880608),('laravel-cache-902ba3cda1883801594b6e1b452790cc53948fda','i:2;',1791991361),('laravel-cache-902ba3cda1883801594b6e1b452790cc53948fda:timer','i:1791991361;',1791991361),('laravel-cache-91c9a377c3fc97ebb4a175b6aa305ea3','i:1;',1792348892),('laravel-cache-91c9a377c3fc97ebb4a175b6aa305ea3:timer','i:1792348892;',1792348892),('laravel-cache-92e3c1c8d2a5559c917b34cff3762f57','i:1;',1792337623),('laravel-cache-92e3c1c8d2a5559c917b34cff3762f57:timer','i:1792337623;',1792337623),('laravel-cache-92fa1ff23c9914051d19944989fdcc08','i:1;',1791880619),('laravel-cache-92fa1ff23c9914051d19944989fdcc08:timer','i:1791880619;',1791880619),('laravel-cache-9cdc6f6b2b8f479da3060ddee25befc3','i:1;',1792034959),('laravel-cache-9cdc6f6b2b8f479da3060ddee25befc3:timer','i:1792034959;',1792034959),('laravel-cache-a27a04b72a1328585dcbb02e70e200b7','i:1;',1792348892),('laravel-cache-a27a04b72a1328585dcbb02e70e200b7:timer','i:1792348892;',1792348892),('laravel-cache-a7eaac6b76b405280df5b18aee0453d4','i:1;',1792017119),('laravel-cache-a7eaac6b76b405280df5b18aee0453d4:timer','i:1792017119;',1792017119),('laravel-cache-b02719713560fa0c85f50275a7cca1d8','i:1;',1791880721),('laravel-cache-b02719713560fa0c85f50275a7cca1d8:timer','i:1791880721;',1791880721),('laravel-cache-b0bb48820eaa2de3899c3d2adad60cff','i:1;',1791873570),('laravel-cache-b0bb48820eaa2de3899c3d2adad60cff:timer','i:1791873570;',1791873570),('laravel-cache-b205bfd773d9a80082dccd890b1d57d5','i:1;',1792339059),('laravel-cache-b205bfd773d9a80082dccd890b1d57d5:timer','i:1792339059;',1792339059),('laravel-cache-ba2001847ddfe746aa7c1a24b32d5ef2','i:1;',1792025248),('laravel-cache-ba2001847ddfe746aa7c1a24b32d5ef2:timer','i:1792025248;',1792025248),('laravel-cache-c4132d5196e06ea726112adc20285d0d','i:1;',1792028986),('laravel-cache-c4132d5196e06ea726112adc20285d0d:timer','i:1792028986;',1792028986),('laravel-cache-c99079febe0c683010c5eba5d2226472','i:1;',1792036478),('laravel-cache-c99079febe0c683010c5eba5d2226472:timer','i:1792036478;',1792036478),('laravel-cache-cb4f0f5e0edce94963eca826791beb65','i:1;',1791873671),('laravel-cache-cb4f0f5e0edce94963eca826791beb65:timer','i:1791873671;',1791873671),('laravel-cache-cd73765622d2eb2e2c409e247afcdf59','i:2;',1792036398),('laravel-cache-cd73765622d2eb2e2c409e247afcdf59:timer','i:1792036398;',1792036398),('laravel-cache-da4b9237bacccdf19c0760cab7aec4a8359010b0','i:4;',1792347769),('laravel-cache-da4b9237bacccdf19c0760cab7aec4a8359010b0:timer','i:1792347769;',1792347769),('laravel-cache-e037862223e31ab06cfd848cdb2cccbe20fa2f1b','i:1;',1792347752),('laravel-cache-e037862223e31ab06cfd848cdb2cccbe20fa2f1b:timer','i:1792347752;',1792347752),('laravel-cache-ed143e9d7ff5f61bb68dc5ad173dfdfe','i:2;',1792034947),('laravel-cache-ed143e9d7ff5f61bb68dc5ad173dfdfe:timer','i:1792034947;',1792034947),('laravel-cache-ed95989c2b6deb52441f2afec58b4375','i:1;',1792026681),('laravel-cache-ed95989c2b6deb52441f2afec58b4375:timer','i:1792026681;',1792026681),('laravel-cache-ef1a619fb7c883e991792af0bed9f3c86a65b644','i:1;',1792029292),('laravel-cache-ef1a619fb7c883e991792af0bed9f3c86a65b644:timer','i:1792029292;',1792029292),('laravel-cache-f5031d63a0c1deee78c79804ce485199','i:1;',1791795349),('laravel-cache-f5031d63a0c1deee78c79804ce485199:timer','i:1791795349;',1791795349),('laravel-cache-f6552d92da71e881d3f163e80c5ddde0','i:1;',1792026301),('laravel-cache-f6552d92da71e881d3f163e80c5ddde0:timer','i:1792026301;',1792026301),('laravel-cache-f7924518ace1155a3d88651ba357112a','i:1;',1792029034),('laravel-cache-f7924518ace1155a3d88651ba357112a:timer','i:1792029034;',1792029034),('laravel-cache-f9ba21cac0bd29867815830b9ccb938d','i:1;',1791880807),('laravel-cache-f9ba21cac0bd29867815830b9ccb938d:timer','i:1791880807;',1791880807),('laravel-cache-fa0357a7a6f977eadd795fbeab6c71ca','i:1;',1792034947),('laravel-cache-fa0357a7a6f977eadd795fbeab6c71ca:timer','i:1792034947;',1792034947),('laravel-cache-faadeb104d45c5a7a0b9295a316c2464','i:1;',1792340507),('laravel-cache-faadeb104d45c5a7a0b9295a316c2464:timer','i:1792340507;',1792340507),('laravel-cache-fce992d3da6d758ee1ad203bcada608c','i:1;',1792339959),('laravel-cache-fce992d3da6d758ee1ad203bcada608c:timer','i:1792339959;',1792339959),('laravel-cache-logistics.dashboard.stats.5.2026-10-12','a:10:{s:7:\"inbound\";i:0;s:9:\"at_center\";i:0;s:6:\"sorted\";i:0;s:8:\"assigned\";i:0;s:3:\"out\";i:0;s:15:\"delivered_today\";i:0;s:6:\"failed\";i:0;s:8:\"returned\";i:0;s:13:\"active_riders\";i:0;s:16:\"available_riders\";i:0;}',1791825554),('laravel-cache-logistics.dashboard.stats.5.2026-10-13','a:10:{s:7:\"inbound\";i:0;s:9:\"at_center\";i:0;s:6:\"sorted\";i:0;s:8:\"assigned\";i:0;s:3:\"out\";i:0;s:15:\"delivered_today\";i:0;s:6:\"failed\";i:0;s:8:\"returned\";i:0;s:13:\"active_riders\";i:0;s:16:\"available_riders\";i:0;}',1791878027),('laravel-cache-psgc.v2.132f2325ee740825db4248883887e263d3f372e8','a:15:{i:0;a:2:{s:4:\"code\";s:10:\"0403402001\";s:4:\"name\";s:5:\"Bitin\";}i:1;a:2:{s:4:\"code\";s:10:\"0403402002\";s:4:\"name\";s:4:\"Calo\";}i:2;a:2:{s:4:\"code\";s:10:\"0403402003\";s:4:\"name\";s:4:\"Dila\";}i:3;a:2:{s:4:\"code\";s:10:\"0403402004\";s:4:\"name\";s:6:\"Maitim\";}i:4;a:2:{s:4:\"code\";s:10:\"0403402005\";s:4:\"name\";s:6:\"Masaya\";}i:5;a:2:{s:4:\"code\";s:10:\"0403402006\";s:4:\"name\";s:13:\"Paciano Rizal\";}i:6;a:2:{s:4:\"code\";s:10:\"0403402009\";s:4:\"name\";s:6:\"Puypuy\";}i:7;a:2:{s:4:\"code\";s:10:\"0403402010\";s:4:\"name\";s:11:\"San Antonio\";}i:8;a:2:{s:4:\"code\";s:10:\"0403402011\";s:4:\"name\";s:10:\"San Isidro\";}i:9;a:2:{s:4:\"code\";s:10:\"0403402012\";s:4:\"name\";s:10:\"Santa Cruz\";}i:10;a:2:{s:4:\"code\";s:10:\"0403402013\";s:4:\"name\";s:13:\"Santo Domingo\";}i:11;a:2:{s:4:\"code\";s:10:\"0403402014\";s:4:\"name\";s:8:\"Tagumpay\";}i:12;a:2:{s:4:\"code\";s:10:\"0403402015\";s:4:\"name\";s:6:\"Tranca\";}i:13;a:2:{s:4:\"code\";s:10:\"0403402016\";s:4:\"name\";s:12:\"San Agustin \";}i:14;a:2:{s:4:\"code\";s:10:\"0403402017\";s:4:\"name\";s:12:\"San Nicolas \";}}',1792113717),('laravel-cache-psgc.v2.1fadad5feacaa37d8a4cad744198f9a2fa358ee2','a:25:{i:0;a:2:{s:4:\"code\";s:10:\"1606817001\";s:4:\"name\";s:7:\"Batunan\";}i:1;a:2:{s:4:\"code\";s:10:\"1606817002\";s:4:\"name\";s:9:\"Carpenito\";}i:2;a:2:{s:4:\"code\";s:10:\"1606817003\";s:4:\"name\";s:9:\"Kahayagan\";}i:3;a:2:{s:4:\"code\";s:10:\"1606817004\";s:4:\"name\";s:4:\"Lago\";}i:4;a:2:{s:4:\"code\";s:10:\"1606817006\";s:4:\"name\";s:10:\"Maglambing\";}i:5;a:2:{s:4:\"code\";s:10:\"1606817007\";s:4:\"name\";s:8:\"Maglatab\";}i:6;a:2:{s:4:\"code\";s:10:\"1606817008\";s:4:\"name\";s:9:\"Magsaysay\";}i:7;a:2:{s:4:\"code\";s:10:\"1606817009\";s:4:\"name\";s:6:\"Malixi\";}i:8;a:2:{s:4:\"code\";s:10:\"1606817010\";s:4:\"name\";s:8:\"Manambia\";}i:9;a:2:{s:4:\"code\";s:10:\"1606817011\";s:4:\"name\";s:9:\"OsmeÃ±a\";}i:10;a:2:{s:4:\"code\";s:10:\"1606817012\";s:4:\"name\";s:9:\"Poblacion\";}i:11;a:2:{s:4:\"code\";s:10:\"1606817013\";s:4:\"name\";s:6:\"Quezon\";}i:12;a:2:{s:4:\"code\";s:10:\"1606817014\";s:4:\"name\";s:11:\"San Vicente\";}i:13;a:2:{s:4:\"code\";s:10:\"1606817015\";s:4:\"name\";s:10:\"Santa Cruz\";}i:14;a:2:{s:4:\"code\";s:10:\"1606817016\";s:4:\"name\";s:8:\"Santa Fe\";}i:15;a:2:{s:4:\"code\";s:10:\"1606817017\";s:4:\"name\";s:11:\"Santa Juana\";}i:16;a:2:{s:4:\"code\";s:10:\"1606817018\";s:4:\"name\";s:11:\"Santa Maria\";}i:17;a:2:{s:4:\"code\";s:10:\"1606817019\";s:4:\"name\";s:5:\"Sayon\";}i:18;a:2:{s:4:\"code\";s:10:\"1606817020\";s:4:\"name\";s:7:\"Soriano\";}i:19;a:2:{s:4:\"code\";s:10:\"1606817021\";s:4:\"name\";s:8:\"Tagongon\";}i:20;a:2:{s:4:\"code\";s:10:\"1606817022\";s:4:\"name\";s:8:\"Trinidad\";}i:21;a:2:{s:4:\"code\";s:10:\"1606817023\";s:4:\"name\";s:6:\"Ugoban\";}i:22;a:2:{s:4:\"code\";s:10:\"1606817024\";s:4:\"name\";s:10:\"Villaverde\";}i:23;a:2:{s:4:\"code\";s:10:\"1606817025\";s:4:\"name\";s:14:\"DoÃ±a Carmen\";}i:24;a:2:{s:4:\"code\";s:10:\"1606817026\";s:4:\"name\";s:10:\"Hinagdanan\";}}',1792111849),('laravel-cache-psgc.v2.23aef418e728c1d1eae0fa9c2659430c225abecd','a:5:{i:0;a:2:{s:4:\"code\";s:10:\"1600200000\";s:4:\"name\";s:16:\"Agusan del Norte\";}i:1;a:2:{s:4:\"code\";s:10:\"1600300000\";s:4:\"name\";s:14:\"Agusan del Sur\";}i:2;a:2:{s:4:\"code\";s:10:\"1606700000\";s:4:\"name\";s:17:\"Surigao del Norte\";}i:3;a:2:{s:4:\"code\";s:10:\"1606800000\";s:4:\"name\";s:15:\"Surigao del Sur\";}i:4;a:2:{s:4:\"code\";s:10:\"1608500000\";s:4:\"name\";s:15:\"Dinagat Islands\";}}',1792111844),('laravel-cache-psgc.v2.2ffe25e5354d59274a022eb7bf41f65f2082af97','a:26:{i:0;a:2:{s:4:\"code\";s:10:\"0403426001\";s:4:\"name\";s:6:\"Alipit\";}i:1;a:2:{s:4:\"code\";s:10:\"0403426002\";s:4:\"name\";s:10:\"Bagumbayan\";}i:2;a:2:{s:4:\"code\";s:10:\"0403426003\";s:4:\"name\";s:7:\"Bubukal\";}i:3;a:2:{s:4:\"code\";s:10:\"0403426004\";s:4:\"name\";s:6:\"Calios\";}i:4;a:2:{s:4:\"code\";s:10:\"0403426005\";s:4:\"name\";s:5:\"Duhat\";}i:5;a:2:{s:4:\"code\";s:10:\"0403426006\";s:4:\"name\";s:5:\"Gatid\";}i:6;a:2:{s:4:\"code\";s:10:\"0403426007\";s:4:\"name\";s:6:\"Jasaan\";}i:7;a:2:{s:4:\"code\";s:10:\"0403426008\";s:4:\"name\";s:6:\"Labuin\";}i:8;a:2:{s:4:\"code\";s:10:\"0403426009\";s:4:\"name\";s:7:\"Malinao\";}i:9;a:2:{s:4:\"code\";s:10:\"0403426010\";s:4:\"name\";s:6:\"Oogong\";}i:10;a:2:{s:4:\"code\";s:10:\"0403426011\";s:4:\"name\";s:10:\"Pagsawitan\";}i:11;a:2:{s:4:\"code\";s:10:\"0403426012\";s:4:\"name\";s:7:\"Palasan\";}i:12;a:2:{s:4:\"code\";s:10:\"0403426013\";s:4:\"name\";s:8:\"Patimbao\";}i:13;a:2:{s:4:\"code\";s:10:\"0403426014\";s:4:\"name\";s:11:\"Barangay I \";}i:14;a:2:{s:4:\"code\";s:10:\"0403426015\";s:4:\"name\";s:12:\"Barangay II \";}i:15;a:2:{s:4:\"code\";s:10:\"0403426016\";s:4:\"name\";s:13:\"Barangay III \";}i:16;a:2:{s:4:\"code\";s:10:\"0403426017\";s:4:\"name\";s:12:\"Barangay IV \";}i:17;a:2:{s:4:\"code\";s:10:\"0403426018\";s:4:\"name\";s:11:\"Barangay V \";}i:18;a:2:{s:4:\"code\";s:10:\"0403426019\";s:4:\"name\";s:8:\"San Jose\";}i:19;a:2:{s:4:\"code\";s:10:\"0403426020\";s:4:\"name\";s:8:\"San Juan\";}i:20;a:2:{s:4:\"code\";s:10:\"0403426021\";s:4:\"name\";s:15:\"San Pablo Norte\";}i:21;a:2:{s:4:\"code\";s:10:\"0403426022\";s:4:\"name\";s:13:\"San Pablo Sur\";}i:22;a:2:{s:4:\"code\";s:10:\"0403426023\";s:4:\"name\";s:14:\"Santisima Cruz\";}i:23;a:2:{s:4:\"code\";s:10:\"0403426024\";s:4:\"name\";s:19:\"Santo Angel Central\";}i:24;a:2:{s:4:\"code\";s:10:\"0403426025\";s:4:\"name\";s:17:\"Santo Angel Norte\";}i:25;a:2:{s:4:\"code\";s:10:\"0403426026\";s:4:\"name\";s:15:\"Santo Angel Sur\";}}',1792114487),('laravel-cache-psgc.v2.3724cbf137cd9037d21e78fa61b3c513b3571206','a:0:{}',1792113617),('laravel-cache-psgc.v2.45f9ccc95ae57fae05336fe6c70581c499ae015c','a:15:{i:0;a:2:{s:4:\"code\";s:10:\"0403414001\";s:4:\"name\";s:7:\"Amuyong\";}i:1;a:2:{s:4:\"code\";s:10:\"0403414002\";s:4:\"name\";s:7:\"Lambac \";}i:2;a:2:{s:4:\"code\";s:10:\"0403414003\";s:4:\"name\";s:6:\"Lucong\";}i:3;a:2:{s:4:\"code\";s:10:\"0403414004\";s:4:\"name\";s:10:\"Matalatala\";}i:4;a:2:{s:4:\"code\";s:10:\"0403414005\";s:4:\"name\";s:7:\"Nanguma\";}i:5;a:2:{s:4:\"code\";s:10:\"0403414006\";s:4:\"name\";s:6:\"Numero\";}i:6;a:2:{s:4:\"code\";s:10:\"0403414007\";s:4:\"name\";s:8:\"Paagahan\";}i:7;a:2:{s:4:\"code\";s:10:\"0403414008\";s:4:\"name\";s:10:\"Bayanihan \";}i:8;a:2:{s:4:\"code\";s:10:\"0403414009\";s:4:\"name\";s:15:\"Libis ng Nayon \";}i:9;a:2:{s:4:\"code\";s:10:\"0403414010\";s:4:\"name\";s:9:\"Maligaya \";}i:10;a:2:{s:4:\"code\";s:10:\"0403414011\";s:4:\"name\";s:8:\"Masikap \";}i:11;a:2:{s:4:\"code\";s:10:\"0403414012\";s:4:\"name\";s:8:\"Pag-Asa \";}i:12;a:2:{s:4:\"code\";s:10:\"0403414013\";s:4:\"name\";s:10:\"Sinagtala \";}i:13;a:2:{s:4:\"code\";s:10:\"0403414014\";s:4:\"name\";s:11:\"San Antonio\";}i:14;a:2:{s:4:\"code\";s:10:\"0403414015\";s:4:\"name\";s:10:\"San Miguel\";}}',1792114484),('laravel-cache-psgc.v2.491fb8c757d00b83be97854588cca8e651cc6719','a:40:{i:0;a:2:{s:4:\"code\";s:10:\"0403416001\";s:4:\"name\";s:6:\"Amonoy\";}i:1;a:2:{s:4:\"code\";s:10:\"0403416002\";s:4:\"name\";s:5:\"Bakia\";}i:2;a:2:{s:4:\"code\";s:10:\"0403416003\";s:4:\"name\";s:5:\"Bukal\";}i:3;a:2:{s:4:\"code\";s:10:\"0403416004\";s:4:\"name\";s:7:\"Balanac\";}i:4;a:2:{s:4:\"code\";s:10:\"0403416005\";s:4:\"name\";s:8:\"Balayong\";}i:5;a:2:{s:4:\"code\";s:10:\"0403416007\";s:4:\"name\";s:7:\"Banilad\";}i:6;a:2:{s:4:\"code\";s:10:\"0403416008\";s:4:\"name\";s:5:\"Banti\";}i:7;a:2:{s:4:\"code\";s:10:\"0403416010\";s:4:\"name\";s:6:\"Bitaoy\";}i:8;a:2:{s:4:\"code\";s:10:\"0403416011\";s:4:\"name\";s:7:\"Botocan\";}i:9;a:2:{s:4:\"code\";s:10:\"0403416012\";s:4:\"name\";s:6:\"Burgos\";}i:10;a:2:{s:4:\"code\";s:10:\"0403416013\";s:4:\"name\";s:5:\"Burol\";}i:11;a:2:{s:4:\"code\";s:10:\"0403416014\";s:4:\"name\";s:7:\"Coralao\";}i:12;a:2:{s:4:\"code\";s:10:\"0403416015\";s:4:\"name\";s:7:\"Gagalot\";}i:13;a:2:{s:4:\"code\";s:10:\"0403416016\";s:4:\"name\";s:13:\"Ibabang Banga\";}i:14;a:2:{s:4:\"code\";s:10:\"0403416017\";s:4:\"name\";s:16:\"Ibabang Bayucain\";}i:15;a:2:{s:4:\"code\";s:10:\"0403416018\";s:4:\"name\";s:13:\"Ilayang Banga\";}i:16;a:2:{s:4:\"code\";s:10:\"0403416019\";s:4:\"name\";s:16:\"Ilayang Bayucain\";}i:17;a:2:{s:4:\"code\";s:10:\"0403416020\";s:4:\"name\";s:7:\"Isabang\";}i:18;a:2:{s:4:\"code\";s:10:\"0403416021\";s:4:\"name\";s:7:\"Malinao\";}i:19;a:2:{s:4:\"code\";s:10:\"0403416022\";s:4:\"name\";s:6:\"May-It\";}i:20;a:2:{s:4:\"code\";s:10:\"0403416023\";s:4:\"name\";s:15:\"Munting Kawayan\";}i:21;a:2:{s:4:\"code\";s:10:\"0403416024\";s:4:\"name\";s:4:\"Oobi\";}i:22;a:2:{s:4:\"code\";s:10:\"0403416025\";s:4:\"name\";s:4:\"Olla\";}i:23;a:2:{s:4:\"code\";s:10:\"0403416026\";s:4:\"name\";s:8:\"Origuel \";}i:24;a:2:{s:4:\"code\";s:10:\"0403416027\";s:4:\"name\";s:9:\"Panalaban\";}i:25;a:2:{s:4:\"code\";s:10:\"0403416028\";s:4:\"name\";s:7:\"Panglan\";}i:26;a:2:{s:4:\"code\";s:10:\"0403416029\";s:4:\"name\";s:6:\"Pangil\";}i:27;a:2:{s:4:\"code\";s:10:\"0403416030\";s:4:\"name\";s:4:\"Piit\";}i:28;a:2:{s:4:\"code\";s:10:\"0403416031\";s:4:\"name\";s:4:\"Pook\";}i:29;a:2:{s:4:\"code\";s:10:\"0403416032\";s:4:\"name\";s:5:\"Rizal\";}i:30;a:2:{s:4:\"code\";s:10:\"0403416033\";s:4:\"name\";s:14:\"San Francisco \";}i:31;a:2:{s:4:\"code\";s:10:\"0403416034\";s:4:\"name\";s:10:\"San Isidro\";}i:32;a:2:{s:4:\"code\";s:10:\"0403416035\";s:4:\"name\";s:11:\"San Miguel \";}i:33;a:2:{s:4:\"code\";s:10:\"0403416036\";s:4:\"name\";s:9:\"San Roque\";}i:34;a:2:{s:4:\"code\";s:10:\"0403416037\";s:4:\"name\";s:15:\"Santa Catalina \";}i:35;a:2:{s:4:\"code\";s:10:\"0403416038\";s:4:\"name\";s:4:\"Suba\";}i:36;a:2:{s:4:\"code\";s:10:\"0403416039\";s:4:\"name\";s:7:\"Tanawan\";}i:37;a:2:{s:4:\"code\";s:10:\"0403416040\";s:4:\"name\";s:6:\"Taytay\";}i:38;a:2:{s:4:\"code\";s:10:\"0403416041\";s:4:\"name\";s:8:\"Talortor\";}i:39;a:2:{s:4:\"code\";s:10:\"0403416042\";s:4:\"name\";s:13:\"Villa Nogales\";}}',1792421941),('laravel-cache-psgc.v2.4cecaf076cb5f451735aa570c2419cd50273d5c1','a:5:{i:0;a:2:{s:4:\"code\";s:10:\"1001801000\";s:4:\"name\";s:8:\"Catarman\";}i:1;a:2:{s:4:\"code\";s:10:\"1001802000\";s:4:\"name\";s:11:\"Guinsiliban\";}i:2;a:2:{s:4:\"code\";s:10:\"1001803000\";s:4:\"name\";s:7:\"Mahinog\";}i:3;a:2:{s:4:\"code\";s:10:\"1001804000\";s:4:\"name\";s:9:\"Mambajao \";}i:4;a:2:{s:4:\"code\";s:10:\"1001805000\";s:4:\"name\";s:5:\"Sagay\";}}',1792112740),('laravel-cache-psgc.v2.64ad27971609bdb5a56b7a78fb7954051bfc6ce7','a:5:{i:0;a:2:{s:4:\"code\";s:10:\"0401000000\";s:4:\"name\";s:8:\"Batangas\";}i:1;a:2:{s:4:\"code\";s:10:\"0402100000\";s:4:\"name\";s:6:\"Cavite\";}i:2;a:2:{s:4:\"code\";s:10:\"0403400000\";s:4:\"name\";s:6:\"Laguna\";}i:3;a:2:{s:4:\"code\";s:10:\"0405600000\";s:4:\"name\";s:6:\"Quezon\";}i:4;a:2:{s:4:\"code\";s:10:\"0405800000\";s:4:\"name\";s:5:\"Rizal\";}}',1792421932),('laravel-cache-psgc.v2.698763389f031ee250e4540066d9619bf8ee898e','a:5:{i:0;a:2:{s:4:\"code\";s:10:\"0200900000\";s:4:\"name\";s:7:\"Batanes\";}i:1;a:2:{s:4:\"code\";s:10:\"0201500000\";s:4:\"name\";s:7:\"Cagayan\";}i:2;a:2:{s:4:\"code\";s:10:\"0203100000\";s:4:\"name\";s:7:\"Isabela\";}i:3;a:2:{s:4:\"code\";s:10:\"0205000000\";s:4:\"name\";s:13:\"Nueva Vizcaya\";}i:4;a:2:{s:4:\"code\";s:10:\"0205700000\";s:4:\"name\";s:7:\"Quirino\";}}',1792421927),('laravel-cache-psgc.v2.6eaa64505a72d2fc1e567954022bce05985c8fc2','a:9:{i:0;a:2:{s:4:\"code\";s:10:\"0304925001\";s:4:\"name\";s:4:\"Alua\";}i:1;a:2:{s:4:\"code\";s:10:\"0304925002\";s:4:\"name\";s:6:\"Calaba\";}i:2;a:2:{s:4:\"code\";s:10:\"0304925004\";s:4:\"name\";s:7:\"Malapit\";}i:3;a:2:{s:4:\"code\";s:10:\"0304925005\";s:4:\"name\";s:6:\"Mangga\";}i:4;a:2:{s:4:\"code\";s:10:\"0304925006\";s:4:\"name\";s:9:\"Poblacion\";}i:5;a:2:{s:4:\"code\";s:10:\"0304925008\";s:4:\"name\";s:4:\"Pulo\";}i:6;a:2:{s:4:\"code\";s:10:\"0304925010\";s:4:\"name\";s:9:\"San Roque\";}i:7;a:2:{s:4:\"code\";s:10:\"0304925011\";s:4:\"name\";s:11:\"Sto. Cristo\";}i:8;a:2:{s:4:\"code\";s:10:\"0304925012\";s:4:\"name\";s:5:\"Tabon\";}}',1792421918),('laravel-cache-psgc.v2.a421755d48ed54a742d5f63396e10247f26d6306','a:6:{i:0;a:2:{s:4:\"code\";s:10:\"0802600000\";s:4:\"name\";s:13:\"Eastern Samar\";}i:1;a:2:{s:4:\"code\";s:10:\"0803700000\";s:4:\"name\";s:5:\"Leyte\";}i:2;a:2:{s:4:\"code\";s:10:\"0804800000\";s:4:\"name\";s:14:\"Northern Samar\";}i:3;a:2:{s:4:\"code\";s:10:\"0806000000\";s:4:\"name\";s:5:\"Samar\";}i:4;a:2:{s:4:\"code\";s:10:\"0806400000\";s:4:\"name\";s:14:\"Southern Leyte\";}i:5;a:2:{s:4:\"code\";s:10:\"0807800000\";s:4:\"name\";s:7:\"Biliran\";}}',1791912886),('laravel-cache-psgc.v2.ad8b5358a864ffedf03e1757d35b0f92a56a02d1','a:7:{i:0;a:2:{s:4:\"code\";s:10:\"0300800000\";s:4:\"name\";s:6:\"Bataan\";}i:1;a:2:{s:4:\"code\";s:10:\"0301400000\";s:4:\"name\";s:7:\"Bulacan\";}i:2;a:2:{s:4:\"code\";s:10:\"0304900000\";s:4:\"name\";s:11:\"Nueva Ecija\";}i:3;a:2:{s:4:\"code\";s:10:\"0305400000\";s:4:\"name\";s:8:\"Pampanga\";}i:4;a:2:{s:4:\"code\";s:10:\"0306900000\";s:4:\"name\";s:6:\"Tarlac\";}i:5;a:2:{s:4:\"code\";s:10:\"0307100000\";s:4:\"name\";s:8:\"Zambales\";}i:6;a:2:{s:4:\"code\";s:10:\"0307700000\";s:4:\"name\";s:6:\"Aurora\";}}',1792421895),('laravel-cache-psgc.v2.af59dd0d6f5a85916d873fcad0b9ff3c8c85e127','a:30:{i:0;a:2:{s:4:\"code\";s:10:\"0403401000\";s:4:\"name\";s:8:\"Alaminos\";}i:1;a:2:{s:4:\"code\";s:10:\"0403402000\";s:4:\"name\";s:3:\"Bay\";}i:2;a:2:{s:4:\"code\";s:10:\"0403403000\";s:4:\"name\";s:16:\"City of BiÃ±an\";}i:3;a:2:{s:4:\"code\";s:10:\"0403404000\";s:4:\"name\";s:15:\"City of Cabuyao\";}i:4;a:2:{s:4:\"code\";s:10:\"0403405000\";s:4:\"name\";s:15:\"City of Calamba\";}i:5;a:2:{s:4:\"code\";s:10:\"0403406000\";s:4:\"name\";s:7:\"Calauan\";}i:6;a:2:{s:4:\"code\";s:10:\"0403407000\";s:4:\"name\";s:7:\"Cavinti\";}i:7;a:2:{s:4:\"code\";s:10:\"0403408000\";s:4:\"name\";s:4:\"Famy\";}i:8;a:2:{s:4:\"code\";s:10:\"0403409000\";s:4:\"name\";s:8:\"Kalayaan\";}i:9;a:2:{s:4:\"code\";s:10:\"0403410000\";s:4:\"name\";s:5:\"Liliw\";}i:10;a:2:{s:4:\"code\";s:10:\"0403411000\";s:4:\"name\";s:12:\"Los BaÃ±os\";}i:11;a:2:{s:4:\"code\";s:10:\"0403412000\";s:4:\"name\";s:8:\"Luisiana\";}i:12;a:2:{s:4:\"code\";s:10:\"0403413000\";s:4:\"name\";s:6:\"Lumban\";}i:13;a:2:{s:4:\"code\";s:10:\"0403414000\";s:4:\"name\";s:7:\"Mabitac\";}i:14;a:2:{s:4:\"code\";s:10:\"0403415000\";s:4:\"name\";s:9:\"Magdalena\";}i:15;a:2:{s:4:\"code\";s:10:\"0403416000\";s:4:\"name\";s:8:\"Majayjay\";}i:16;a:2:{s:4:\"code\";s:10:\"0403417000\";s:4:\"name\";s:9:\"Nagcarlan\";}i:17;a:2:{s:4:\"code\";s:10:\"0403418000\";s:4:\"name\";s:5:\"Paete\";}i:18;a:2:{s:4:\"code\";s:10:\"0403419000\";s:4:\"name\";s:9:\"Pagsanjan\";}i:19;a:2:{s:4:\"code\";s:10:\"0403420000\";s:4:\"name\";s:5:\"Pakil\";}i:20;a:2:{s:4:\"code\";s:10:\"0403421000\";s:4:\"name\";s:6:\"Pangil\";}i:21;a:2:{s:4:\"code\";s:10:\"0403422000\";s:4:\"name\";s:4:\"Pila\";}i:22;a:2:{s:4:\"code\";s:10:\"0403423000\";s:4:\"name\";s:5:\"Rizal\";}i:23;a:2:{s:4:\"code\";s:10:\"0403424000\";s:4:\"name\";s:17:\"City of San Pablo\";}i:24;a:2:{s:4:\"code\";s:10:\"0403425000\";s:4:\"name\";s:17:\"City of San Pedro\";}i:25;a:2:{s:4:\"code\";s:10:\"0403426000\";s:4:\"name\";s:11:\"Santa Cruz \";}i:26;a:2:{s:4:\"code\";s:10:\"0403427000\";s:4:\"name\";s:11:\"Santa Maria\";}i:27;a:2:{s:4:\"code\";s:10:\"0403428000\";s:4:\"name\";s:18:\"City of Santa Rosa\";}i:28;a:2:{s:4:\"code\";s:10:\"0403429000\";s:4:\"name\";s:8:\"Siniloan\";}i:29;a:2:{s:4:\"code\";s:10:\"0403430000\";s:4:\"name\";s:8:\"Victoria\";}}',1792421937),('laravel-cache-psgc.v2.b26046de46a34497463206467db95afde66ca62b','a:5:{i:0;a:2:{s:4:\"code\";s:10:\"1001300000\";s:4:\"name\";s:8:\"Bukidnon\";}i:1;a:2:{s:4:\"code\";s:10:\"1001800000\";s:4:\"name\";s:8:\"Camiguin\";}i:2;a:2:{s:4:\"code\";s:10:\"1003500000\";s:4:\"name\";s:15:\"Lanao del Norte\";}i:3;a:2:{s:4:\"code\";s:10:\"1004200000\";s:4:\"name\";s:18:\"Misamis Occidental\";}i:4;a:2:{s:4:\"code\";s:10:\"1004300000\";s:4:\"name\";s:16:\"Misamis Oriental\";}}',1792112735),('laravel-cache-psgc.v2.b2d3a71e73b35da44a9ded0e4e550d4e7d554e33','a:24:{i:0;a:2:{s:4:\"code\";s:10:\"0403415001\";s:4:\"name\";s:6:\"Alipit\";}i:1;a:2:{s:4:\"code\";s:10:\"0403415002\";s:4:\"name\";s:16:\"Malaking Ambling\";}i:2;a:2:{s:4:\"code\";s:10:\"0403415003\";s:4:\"name\";s:15:\"Munting Ambling\";}i:3;a:2:{s:4:\"code\";s:10:\"0403415004\";s:4:\"name\";s:6:\"Baanan\";}i:4;a:2:{s:4:\"code\";s:10:\"0403415005\";s:4:\"name\";s:7:\"Balanac\";}i:5;a:2:{s:4:\"code\";s:10:\"0403415006\";s:4:\"name\";s:5:\"Bucal\";}i:6;a:2:{s:4:\"code\";s:10:\"0403415007\";s:4:\"name\";s:10:\"Buenavista\";}i:7;a:2:{s:4:\"code\";s:10:\"0403415008\";s:4:\"name\";s:7:\"Bungkol\";}i:8;a:2:{s:4:\"code\";s:10:\"0403415009\";s:4:\"name\";s:3:\"Buo\";}i:9;a:2:{s:4:\"code\";s:10:\"0403415010\";s:4:\"name\";s:9:\"Burlungan\";}i:10;a:2:{s:4:\"code\";s:10:\"0403415011\";s:4:\"name\";s:7:\"Cigaras\";}i:11;a:2:{s:4:\"code\";s:10:\"0403415012\";s:4:\"name\";s:15:\"Ibabang Atingay\";}i:12;a:2:{s:4:\"code\";s:10:\"0403415013\";s:4:\"name\";s:15:\"Ibabang Butnong\";}i:13;a:2:{s:4:\"code\";s:10:\"0403415014\";s:4:\"name\";s:15:\"Ilayang Atingay\";}i:14;a:2:{s:4:\"code\";s:10:\"0403415015\";s:4:\"name\";s:15:\"Ilayang Butnong\";}i:15;a:2:{s:4:\"code\";s:10:\"0403415016\";s:4:\"name\";s:4:\"Ilog\";}i:16;a:2:{s:4:\"code\";s:10:\"0403415017\";s:4:\"name\";s:7:\"Malinao\";}i:17;a:2:{s:4:\"code\";s:10:\"0403415018\";s:4:\"name\";s:9:\"Maravilla\";}i:18;a:2:{s:4:\"code\";s:10:\"0403415019\";s:4:\"name\";s:9:\"Poblacion\";}i:19;a:2:{s:4:\"code\";s:10:\"0403415020\";s:4:\"name\";s:6:\"Sabang\";}i:20;a:2:{s:4:\"code\";s:10:\"0403415021\";s:4:\"name\";s:7:\"Salasad\";}i:21;a:2:{s:4:\"code\";s:10:\"0403415022\";s:4:\"name\";s:7:\"Tanawan\";}i:22;a:2:{s:4:\"code\";s:10:\"0403415023\";s:4:\"name\";s:7:\"Tipunan\";}i:23;a:2:{s:4:\"code\";s:10:\"0403415024\";s:4:\"name\";s:10:\"Halayhayin\";}}',1792434111),('laravel-cache-psgc.v2.b43231fa7e7398041f918d04aeeb6618c7097554','a:32:{i:0;a:2:{s:4:\"code\";s:10:\"0304901000\";s:4:\"name\";s:6:\"Aliaga\";}i:1;a:2:{s:4:\"code\";s:10:\"0304902000\";s:4:\"name\";s:8:\"Bongabon\";}i:2;a:2:{s:4:\"code\";s:10:\"0304903000\";s:4:\"name\";s:18:\"City of Cabanatuan\";}i:3;a:2:{s:4:\"code\";s:10:\"0304904000\";s:4:\"name\";s:6:\"Cabiao\";}i:4;a:2:{s:4:\"code\";s:10:\"0304905000\";s:4:\"name\";s:10:\"Carranglan\";}i:5;a:2:{s:4:\"code\";s:10:\"0304906000\";s:4:\"name\";s:6:\"Cuyapo\";}i:6;a:2:{s:4:\"code\";s:10:\"0304907000\";s:4:\"name\";s:8:\"Gabaldon\";}i:7;a:2:{s:4:\"code\";s:10:\"0304908000\";s:4:\"name\";s:13:\"City of Gapan\";}i:8;a:2:{s:4:\"code\";s:10:\"0304909000\";s:4:\"name\";s:25:\"General Mamerto Natividad\";}i:9;a:2:{s:4:\"code\";s:10:\"0304910000\";s:4:\"name\";s:13:\"General Tinio\";}i:10;a:2:{s:4:\"code\";s:10:\"0304911000\";s:4:\"name\";s:6:\"Guimba\";}i:11;a:2:{s:4:\"code\";s:10:\"0304912000\";s:4:\"name\";s:4:\"Jaen\";}i:12;a:2:{s:4:\"code\";s:10:\"0304913000\";s:4:\"name\";s:4:\"Laur\";}i:13;a:2:{s:4:\"code\";s:10:\"0304914000\";s:4:\"name\";s:5:\"Licab\";}i:14;a:2:{s:4:\"code\";s:10:\"0304915000\";s:4:\"name\";s:7:\"Llanera\";}i:15;a:2:{s:4:\"code\";s:10:\"0304916000\";s:4:\"name\";s:5:\"Lupao\";}i:16;a:2:{s:4:\"code\";s:10:\"0304917000\";s:4:\"name\";s:24:\"Science City of MuÃ±oz\";}i:17;a:2:{s:4:\"code\";s:10:\"0304918000\";s:4:\"name\";s:9:\"Nampicuan\";}i:18;a:2:{s:4:\"code\";s:10:\"0304919000\";s:4:\"name\";s:16:\"City of Palayan \";}i:19;a:2:{s:4:\"code\";s:10:\"0304920000\";s:4:\"name\";s:11:\"Pantabangan\";}i:20;a:2:{s:4:\"code\";s:10:\"0304921000\";s:4:\"name\";s:12:\"PeÃ±aranda\";}i:21;a:2:{s:4:\"code\";s:10:\"0304922000\";s:4:\"name\";s:6:\"Quezon\";}i:22;a:2:{s:4:\"code\";s:10:\"0304923000\";s:4:\"name\";s:5:\"Rizal\";}i:23;a:2:{s:4:\"code\";s:10:\"0304924000\";s:4:\"name\";s:11:\"San Antonio\";}i:24;a:2:{s:4:\"code\";s:10:\"0304925000\";s:4:\"name\";s:10:\"San Isidro\";}i:25;a:2:{s:4:\"code\";s:10:\"0304926000\";s:4:\"name\";s:13:\"San Jose City\";}i:26;a:2:{s:4:\"code\";s:10:\"0304927000\";s:4:\"name\";s:12:\"San Leonardo\";}i:27;a:2:{s:4:\"code\";s:10:\"0304928000\";s:4:\"name\";s:10:\"Santa Rosa\";}i:28;a:2:{s:4:\"code\";s:10:\"0304929000\";s:4:\"name\";s:13:\"Santo Domingo\";}i:29;a:2:{s:4:\"code\";s:10:\"0304930000\";s:4:\"name\";s:8:\"Talavera\";}i:30;a:2:{s:4:\"code\";s:10:\"0304931000\";s:4:\"name\";s:8:\"Talugtug\";}i:31;a:2:{s:4:\"code\";s:10:\"0304932000\";s:4:\"name\";s:8:\"Zaragoza\";}}',1792421912),('laravel-cache-psgc.v2.bb386e9784d004b67c83d3a0c12af6bad2806e5a','a:7:{i:0;a:2:{s:4:\"code\";s:10:\"1001802001\";s:4:\"name\";s:5:\"Butay\";}i:1;a:2:{s:4:\"code\";s:10:\"1001802002\";s:4:\"name\";s:6:\"Cabuan\";}i:2;a:2:{s:4:\"code\";s:10:\"1001802003\";s:4:\"name\";s:7:\"Cantaan\";}i:3;a:2:{s:4:\"code\";s:10:\"1001802004\";s:4:\"name\";s:5:\"Liong\";}i:4;a:2:{s:4:\"code\";s:10:\"1001802005\";s:4:\"name\";s:4:\"Maac\";}i:5;a:2:{s:4:\"code\";s:10:\"1001802006\";s:4:\"name\";s:15:\"North Poblacion\";}i:6;a:2:{s:4:\"code\";s:10:\"1001802007\";s:4:\"name\";s:15:\"South Poblacion\";}}',1792112744),('laravel-cache-psgc.v2.c26dff68466aefbbc7d08c445490e15b4581cedf','a:19:{i:0;a:2:{s:4:\"code\";s:10:\"1606801000\";s:4:\"name\";s:6:\"Barobo\";}i:1;a:2:{s:4:\"code\";s:10:\"1606802000\";s:4:\"name\";s:7:\"Bayabas\";}i:2;a:2:{s:4:\"code\";s:10:\"1606803000\";s:4:\"name\";s:14:\"City of Bislig\";}i:3;a:2:{s:4:\"code\";s:10:\"1606804000\";s:4:\"name\";s:7:\"Cagwait\";}i:4;a:2:{s:4:\"code\";s:10:\"1606805000\";s:4:\"name\";s:8:\"Cantilan\";}i:5;a:2:{s:4:\"code\";s:10:\"1606806000\";s:4:\"name\";s:6:\"Carmen\";}i:6;a:2:{s:4:\"code\";s:10:\"1606807000\";s:4:\"name\";s:9:\"Carrascal\";}i:7;a:2:{s:4:\"code\";s:10:\"1606808000\";s:4:\"name\";s:6:\"Cortes\";}i:8;a:2:{s:4:\"code\";s:10:\"1606809000\";s:4:\"name\";s:8:\"Hinatuan\";}i:9;a:2:{s:4:\"code\";s:10:\"1606810000\";s:4:\"name\";s:6:\"Lanuza\";}i:10;a:2:{s:4:\"code\";s:10:\"1606811000\";s:4:\"name\";s:6:\"Lianga\";}i:11;a:2:{s:4:\"code\";s:10:\"1606812000\";s:4:\"name\";s:6:\"Lingig\";}i:12;a:2:{s:4:\"code\";s:10:\"1606813000\";s:4:\"name\";s:6:\"Madrid\";}i:13;a:2:{s:4:\"code\";s:10:\"1606814000\";s:4:\"name\";s:9:\"Marihatag\";}i:14;a:2:{s:4:\"code\";s:10:\"1606815000\";s:4:\"name\";s:11:\"San Agustin\";}i:15;a:2:{s:4:\"code\";s:10:\"1606816000\";s:4:\"name\";s:10:\"San Miguel\";}i:16;a:2:{s:4:\"code\";s:10:\"1606817000\";s:4:\"name\";s:7:\"Tagbina\";}i:17;a:2:{s:4:\"code\";s:10:\"1606818000\";s:4:\"name\";s:4:\"Tago\";}i:18;a:2:{s:4:\"code\";s:10:\"1606819000\";s:4:\"name\";s:15:\"City of Tandag \";}}',1792111847),('laravel-cache-psgc.v2.c5fc907830090fdd881226ee67635772d5851955','a:13:{i:0;a:2:{s:4:\"code\";s:10:\"1001803001\";s:4:\"name\";s:6:\"Benoni\";}i:1;a:2:{s:4:\"code\";s:10:\"1001803002\";s:4:\"name\";s:8:\"Binatubo\";}i:2;a:2:{s:4:\"code\";s:10:\"1001803004\";s:4:\"name\";s:9:\"Catohugan\";}i:3;a:2:{s:4:\"code\";s:10:\"1001803005\";s:4:\"name\";s:8:\"Hubangon\";}i:4;a:2:{s:4:\"code\";s:10:\"1001803006\";s:4:\"name\";s:6:\"Owakan\";}i:5;a:2:{s:4:\"code\";s:10:\"1001803007\";s:4:\"name\";s:9:\"Poblacion\";}i:6;a:2:{s:4:\"code\";s:10:\"1001803008\";s:4:\"name\";s:6:\"Puntod\";}i:7;a:2:{s:4:\"code\";s:10:\"1001803009\";s:4:\"name\";s:10:\"San Isidro\";}i:8;a:2:{s:4:\"code\";s:10:\"1001803010\";s:4:\"name\";s:8:\"San Jose\";}i:9;a:2:{s:4:\"code\";s:10:\"1001803011\";s:4:\"name\";s:10:\"San Miguel\";}i:10;a:2:{s:4:\"code\";s:10:\"1001803012\";s:4:\"name\";s:9:\"San Roque\";}i:11;a:2:{s:4:\"code\";s:10:\"1001803013\";s:4:\"name\";s:5:\"Tubod\";}i:12;a:2:{s:4:\"code\";s:10:\"1001803014\";s:4:\"name\";s:17:\"Tupsan PequeÃ±o\";}}',1792113189),('laravel-cache-psgc.v2.e226808ec4968b1bee18356eab0d21e90b63414d','a:17:{i:0;a:2:{s:4:\"code\";s:10:\"0100000000\";s:4:\"name\";s:24:\"Region I (Ilocos Region)\";}i:1;a:2:{s:4:\"code\";s:10:\"0200000000\";s:4:\"name\";s:26:\"Region II (Cagayan Valley)\";}i:2;a:2:{s:4:\"code\";s:10:\"0300000000\";s:4:\"name\";s:26:\"Region III (Central Luzon)\";}i:3;a:2:{s:4:\"code\";s:10:\"0400000000\";s:4:\"name\";s:24:\"Region IV-A (CALABARZON)\";}i:4;a:2:{s:4:\"code\";s:10:\"1700000000\";s:4:\"name\";s:15:\"MIMAROPA Region\";}i:5;a:2:{s:4:\"code\";s:10:\"0500000000\";s:4:\"name\";s:23:\"Region V (Bicol Region)\";}i:6;a:2:{s:4:\"code\";s:10:\"0600000000\";s:4:\"name\";s:27:\"Region VI (Western Visayas)\";}i:7;a:2:{s:4:\"code\";s:10:\"0700000000\";s:4:\"name\";s:28:\"Region VII (Central Visayas)\";}i:8;a:2:{s:4:\"code\";s:10:\"0800000000\";s:4:\"name\";s:29:\"Region VIII (Eastern Visayas)\";}i:9;a:2:{s:4:\"code\";s:10:\"0900000000\";s:4:\"name\";s:31:\"Region IX (Zamboanga Peninsula)\";}i:10;a:2:{s:4:\"code\";s:10:\"1000000000\";s:4:\"name\";s:28:\"Region X (Northern Mindanao)\";}i:11;a:2:{s:4:\"code\";s:10:\"1100000000\";s:4:\"name\";s:24:\"Region XI (Davao Region)\";}i:12;a:2:{s:4:\"code\";s:10:\"1200000000\";s:4:\"name\";s:25:\"Region XII (SOCCSKSARGEN)\";}i:13;a:2:{s:4:\"code\";s:10:\"1300000000\";s:4:\"name\";s:29:\"National Capital Region (NCR)\";}i:14;a:2:{s:4:\"code\";s:10:\"1400000000\";s:4:\"name\";s:38:\"Cordillera Administrative Region (CAR)\";}i:15;a:2:{s:4:\"code\";s:10:\"1600000000\";s:4:\"name\";s:20:\"Region XIII (Caraga)\";}i:16;a:2:{s:4:\"code\";s:10:\"1900000000\";s:4:\"name\";s:55:\"Bangsamoro Autonomous Region In Muslim Mindanao (BARMM)\";}}',1792421769);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_assignments`
--

DROP TABLE IF EXISTS `delivery_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `active_order_id` bigint unsigned DEFAULT NULL,
  `rider_id` bigint unsigned NOT NULL,
  `assigned_by` bigint unsigned DEFAULT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `assigned_at` timestamp NOT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_assignments_active_order_id_unique` (`active_order_id`),
  KEY `delivery_assignments_assigned_by_foreign` (`assigned_by`),
  KEY `delivery_assignments_order_id_status_index` (`order_id`,`status`),
  KEY `delivery_assignments_rider_id_status_index` (`rider_id`,`status`),
  CONSTRAINT `delivery_assignments_active_order_id_foreign` FOREIGN KEY (`active_order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `delivery_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `delivery_assignments_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `delivery_assignments_rider_id_foreign` FOREIGN KEY (`rider_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_assignments`
--

LOCK TABLES `delivery_assignments` WRITE;
/*!40000 ALTER TABLE `delivery_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `delivery_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_attempts`
--

DROP TABLE IF EXISTS `delivery_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `rider_id` bigint unsigned NOT NULL,
  `attempt_no` smallint unsigned NOT NULL,
  `outcome` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `proof_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `attempted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_attempts_order_id_attempt_no_unique` (`order_id`,`attempt_no`),
  KEY `delivery_attempts_rider_id_attempted_at_index` (`rider_id`,`attempted_at`),
  CONSTRAINT `delivery_attempts_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `delivery_attempts_rider_id_foreign` FOREIGN KEY (`rider_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_attempts`
--

LOCK TABLES `delivery_attempts` WRITE;
/*!40000 ALTER TABLE `delivery_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `delivery_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `disputes`
--

DROP TABLE IF EXISTS `disputes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `disputes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `buyer_id` bigint unsigned NOT NULL,
  `seller_id` bigint unsigned NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `evidence_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('PENDING','UNDER_REVIEW','REFUND_APPROVED','REPLACEMENT_APPROVED','REJECTED','RESOLVED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `disputes_order_id_unique` (`order_id`),
  KEY `disputes_buyer_id_foreign` (`buyer_id`),
  KEY `disputes_seller_id_foreign` (`seller_id`),
  KEY `disputes_resolved_by_foreign` (`resolved_by`),
  CONSTRAINT `disputes_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `disputes_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `disputes_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `disputes_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disputes`
--

LOCK TABLES `disputes` WRITE;
/*!40000 ALTER TABLE `disputes` DISABLE KEYS */;
/*!40000 ALTER TABLE `disputes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_verification_codes`
--

DROP TABLE IF EXISTS `email_verification_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_verification_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `code_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purpose` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'registration',
  `expires_at` timestamp NOT NULL,
  `attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `last_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_verification_codes_user_id_unique` (`user_id`),
  KEY `email_verification_codes_expires_at_index` (`expires_at`),
  KEY `email_verification_codes_purpose_index` (`purpose`),
  CONSTRAINT `email_verification_codes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_verification_codes`
--

LOCK TABLES `email_verification_codes` WRITE;
/*!40000 ALTER TABLE `email_verification_codes` DISABLE KEYS */;
INSERT INTO `email_verification_codes` VALUES (2,7,'$2y$12$juW/fLMzN1qlGv8OTwB.8uABn1JxaOEV.ayQybEO98NzpzQExqPpm','login','2026-10-14 11:29:13',0,'2026-10-14 11:24:13','2026-10-14 10:16:58','2026-10-14 11:24:13'),(3,9,'$2y$12$uPPoy59NTHDN1PebKEnGkuwv/1VVMQthDx5nFJ9a0xCjYcXVvT/mO','registration','2026-10-14 15:11:27',0,'2026-10-14 15:06:27','2026-10-14 14:50:42','2026-10-14 15:06:27');
/*!40000 ALTER TABLE `email_verification_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logistics_exceptions`
--

DROP TABLE IF EXISTS `logistics_exceptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logistics_exceptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `opened_by` bigint unsigned NOT NULL,
  `previous_rider_id` bigint unsigned DEFAULT NULL,
  `new_rider_id` bigint unsigned DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPEN',
  `resolution` text COLLATE utf8mb4_unicode_ci,
  `resolved_by` bigint unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `logistics_exceptions_order_id_foreign` (`order_id`),
  KEY `logistics_exceptions_opened_by_foreign` (`opened_by`),
  KEY `logistics_exceptions_previous_rider_id_foreign` (`previous_rider_id`),
  KEY `logistics_exceptions_new_rider_id_foreign` (`new_rider_id`),
  KEY `logistics_exceptions_resolved_by_foreign` (`resolved_by`),
  CONSTRAINT `logistics_exceptions_new_rider_id_foreign` FOREIGN KEY (`new_rider_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `logistics_exceptions_opened_by_foreign` FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `logistics_exceptions_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `logistics_exceptions_previous_rider_id_foreign` FOREIGN KEY (`previous_rider_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `logistics_exceptions_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logistics_exceptions`
--

LOCK TABLES `logistics_exceptions` WRITE;
/*!40000 ALTER TABLE `logistics_exceptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `logistics_exceptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (16,'0001_01_01_000000_create_users_table',1),(17,'0001_01_01_000001_create_cache_table',1),(18,'0001_01_01_000002_create_jobs_table',1),(19,'2026_09_07_134132_create_products_table',1),(20,'2026_09_08_101938_create_orders_table',1),(21,'2026_09_08_101940_create_order_items_table',1),(22,'2026_09_08_123057_add_courier_id_to_orders_table',1),(23,'2026_09_08_150926_add_sorting_and_delivery_fields_to_orders_and_users',1),(24,'2026_09_08_152020_update_role_column_in_users_table',1),(25,'2026_09_08_154200_add_document_paths_to_users_table',1),(26,'2026_09_08_161746_create_product_variations_table',1),(27,'2026_09_08_162646_create_vouchers_table',1),(28,'2026_09_08_163503_create_reviews_table',1),(29,'2026_09_08_165659_create_disputes_table',1),(30,'2026_09_08_171040_create_announcements_and_moderation_table',1),(31,'2026_09_24_120000_allow_suspended_user_status',2),(32,'2026_09_26_172340_create_parcel_tracking_events_table',2),(33,'2026_09_26_172342_add_logistics_timestamps_to_orders_table',2),(34,'2026_10_04_161549_add_order_lifecycle_statuses_and_indexes',3),(35,'2026_10_04_161550_add_delivery_attempts_table',3),(36,'2026_10_04_163756_add_pickup_handoff_timestamps_to_orders_table',3),(37,'2026_10_04_172834_create_areas_and_rider_area_assignments',4),(38,'2026_10_04_175236_add_seller_pickup_schedule_to_orders_table',4),(39,'2026_10_04_175822_create_notifications_table',4),(40,'2026_10_04_180209_create_delivery_assignments_table',4),(41,'2026_10_04_184352_add_compliance_review_to_products_table',4),(42,'2026_10_04_185827_create_order_conversations_and_messages_tables',4),(43,'2026_10_04_191655_add_active_order_key_to_delivery_assignments_table',4),(44,'2026_10_04_192656_preserve_order_and_user_history_foreign_keys',4),(45,'2026_10_04_203728_add_return_handoff_timestamp_to_orders_table',4),(46,'2026_10_05_010253_add_hub_release_to_orders_table',4),(47,'2026_10_05_013448_add_unique_order_id_to_disputes_table',4),(48,'2026_10_05_014713_add_pickup_courier_workload_index_to_orders_table',4),(49,'2026_10_05_020018_add_delivery_recovered_at_to_orders_table',4),(50,'2026_10_05_052739_add_suspension_previous_status_to_users_table',5),(51,'2026_10_05_053429_add_suspension_source_to_users_table',6),(52,'2026_10_05_060618_backfill_legacy_courier_assignment_history',7),(53,'2026_10_05_121047_add_resolution_audit_columns_to_disputes_table',8),(54,'2026_10_05_121331_add_parcel_code_to_orders_table',8),(55,'2026_10_05_121422_create_logistics_exceptions_and_scan_events_tables',8),(56,'2026_10_05_122651_create_storage_locations_and_parcel_placements_tables',9),(57,'2026_10_05_125827_add_delivery_code_verification_fields_to_orders_table',10),(58,'2026_10_05_131216_add_sorting_center_id_to_areas_table',11),(59,'2026_10_05_132343_add_badge_code_to_users_table',12),(60,'2026_10_05_151622_create_parcel_label_reprints_table',13),(61,'2026_10_12_000000_add_middle_name_and_suffix_to_users_table',14),(62,'2026_10_11_170000_add_registration_verification_fields',15),(63,'2026_10_12_000001_add_house_number_to_addresses',16),(64,'2026_10_15_000000_add_purpose_to_email_verification_codes',17);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES ('0527eb79-ce23-42dc-87f4-ed5e4fe45647','App\\Notifications\\OrderStatusNotification','App\\Models\\User',2,'{\"order_id\":2,\"order_number\":\"EZC-PRUPEFGTKN\",\"status\":\"PREPARING\",\"event_type\":\"preparing\",\"message\":\"Seller updated order fulfillment status.\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/buyer\\/orders\\/2\"}','2026-10-12 23:56:37','2026-10-07 17:33:07','2026-10-12 23:56:37'),('166cb2b8-2d2a-4ad5-a270-ffd42a30e583','App\\Notifications\\AccountStatusNotification','App\\Models\\User',12,'{\"status\":\"approved\",\"message\":\"Your account application has been approved. You can now sign in.\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/login\"}',NULL,'2026-10-14 17:15:32','2026-10-14 17:15:32'),('17a188f4-69b6-4771-a657-9d31645f2dc2','App\\Notifications\\ProductComplianceNotification','App\\Models\\User',3,'{\"product_id\":10,\"product_name\":\"white trouser\",\"status\":\"approved\",\"message\":\"white trouser was approved for sale.\",\"note\":\"\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/seller\\/products\"}','2026-10-07 17:27:56','2026-10-07 14:51:23','2026-10-07 17:27:56'),('19e71aef-fd54-460a-b5da-1ce87495df71','App\\Notifications\\OrderStatusNotification','App\\Models\\User',3,'{\"order_id\":2,\"order_number\":\"EZC-PRUPEFGTKN\",\"status\":\"READY_FOR_PICKUP\",\"event_type\":\"ready_for_pickup\",\"message\":\"Seller updated order fulfillment status.\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/seller\\/orders\\/2\"}',NULL,'2026-10-07 17:33:10','2026-10-07 17:33:10'),('1e3eab31-f2f4-4149-874e-c627f4950a14','App\\Notifications\\ProductComplianceNotification','App\\Models\\User',3,'{\"product_id\":4,\"product_name\":\"sneaker 1\",\"status\":\"approved\",\"message\":\"sneaker 1 was approved for sale.\",\"note\":\"\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/seller\\/products\"}','2026-10-07 17:27:44','2026-10-07 14:51:44','2026-10-07 17:27:44'),('2ce8930d-0ad6-4988-85cf-337478ebcaed','App\\Notifications\\OrderStatusNotification','App\\Models\\User',2,'{\"order_id\":1,\"order_number\":\"EZC-UGWZQYDHET\",\"status\":\"PREPARING\",\"event_type\":\"preparing\",\"message\":\"Seller updated order fulfillment status.\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/buyer\\/orders\\/1\"}','2026-10-12 23:56:42','2026-10-07 07:44:31','2026-10-12 23:56:42'),('3ea9ce84-130b-443a-b86b-2e7f6cfcdb5c','App\\Notifications\\ProductComplianceNotification','App\\Models\\User',3,'{\"product_id\":3,\"product_name\":\"Test\",\"status\":\"approved\",\"message\":\"Test was approved for sale.\",\"note\":\"\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/seller\\/products\"}','2026-10-07 07:44:09','2026-10-06 00:47:30','2026-10-07 07:44:09'),('621ff546-c3cf-4f10-99aa-e3cca79aec82','App\\Notifications\\OrderStatusNotification','App\\Models\\User',3,'{\"order_id\":2,\"order_number\":\"EZC-PRUPEFGTKN\",\"status\":\"CONFIRMED\",\"event_type\":\"confirmed\",\"message\":\"Seller updated order fulfillment status.\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/seller\\/orders\\/2\"}',NULL,'2026-10-07 17:33:03','2026-10-07 17:33:03'),('636470d8-0148-49c1-8bd8-61dd87d3c0e8','App\\Notifications\\ProductComplianceNotification','App\\Models\\User',3,'{\"product_id\":7,\"product_name\":\"shoes 2\",\"status\":\"approved\",\"message\":\"shoes 2 was approved for sale.\",\"note\":\"\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/seller\\/products\"}','2026-10-07 17:27:49','2026-10-07 14:51:37','2026-10-07 17:27:49'),('651f682e-3a32-42ae-a71a-c01506fb2739','App\\Notifications\\OrderStatusNotification','App\\Models\\User',2,'{\"order_id\":2,\"order_number\":\"EZC-PRUPEFGTKN\",\"status\":\"CONFIRMED\",\"event_type\":\"confirmed\",\"message\":\"Seller updated order fulfillment status.\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/buyer\\/orders\\/2\"}','2026-10-12 23:56:38','2026-10-07 17:33:03','2026-10-12 23:56:38'),('7220639f-bf5c-4766-a202-174132f8246f','App\\Notifications\\OrderWorkflowNotification','App\\Models\\User',3,'{\"order_id\":2,\"order_number\":\"EZC-PRUPEFGTKN\",\"event_type\":\"order_placed\",\"message\":\"A buyer placed an order. Review it and confirm whether you can fulfill it.\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/seller\\/orders\\/2\"}',NULL,'2026-10-07 17:32:38','2026-10-07 17:32:38'),('726a9712-0a6d-41d6-b89b-83e9fe93dd75','App\\Notifications\\ProductComplianceNotification','App\\Models\\User',3,'{\"product_id\":6,\"product_name\":\"luxury watch 1\",\"status\":\"approved\",\"message\":\"luxury watch 1 was approved for sale.\",\"note\":\"\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/seller\\/products\"}','2026-10-07 17:27:48','2026-10-07 14:51:39','2026-10-07 17:27:48'),('76bd68f5-4a9f-423b-b232-09c5a0b5d0a3','App\\Notifications\\OrderStatusNotification','App\\Models\\User',2,'{\"order_id\":2,\"order_number\":\"EZC-PRUPEFGTKN\",\"status\":\"READY_FOR_PICKUP\",\"event_type\":\"ready_for_pickup\",\"message\":\"Seller updated order fulfillment status.\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/buyer\\/orders\\/2\"}','2026-10-12 23:56:36','2026-10-07 17:33:10','2026-10-12 23:56:36'),('787aadf7-9c06-4ee2-bdde-b2e8d475b335','App\\Notifications\\OrderStatusNotification','App\\Models\\User',2,'{\"order_id\":1,\"order_number\":\"EZC-UGWZQYDHET\",\"status\":\"READY_FOR_PICKUP\",\"event_type\":\"ready_for_pickup\",\"message\":\"Seller updated order fulfillment status.\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/buyer\\/orders\\/1\"}','2026-10-12 23:56:40','2026-10-07 07:44:34','2026-10-12 23:56:40'),('848248b1-d91e-49a9-990d-5bbc7f48a670','App\\Notifications\\OrderWorkflowNotification','App\\Models\\User',3,'{\"order_id\":1,\"order_number\":\"EZC-UGWZQYDHET\",\"event_type\":\"order_placed\",\"message\":\"A buyer placed an order. Review it and confirm whether you can fulfill it.\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/seller\\/orders\\/1\"}','2026-10-07 07:44:19','2026-10-06 00:31:54','2026-10-07 07:44:19'),('8d6137c2-0590-4f7f-8a7a-fe155411c24d','App\\Notifications\\OrderWorkflowNotification','App\\Models\\User',5,'{\"order_id\":1,\"order_number\":\"EZC-UGWZQYDHET\",\"event_type\":\"pickup_requested\",\"message\":\"A seller requested pickup for an order.\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/logistics\\/tracking?search=EZC-UGWZQYDHET\"}','2026-10-12 23:54:53','2026-10-07 07:45:32','2026-10-12 23:54:53'),('a42ce606-89d3-47bb-a0fd-b688ece22e99','App\\Notifications\\OrderStatusNotification','App\\Models\\User',3,'{\"order_id\":1,\"order_number\":\"EZC-UGWZQYDHET\",\"status\":\"PREPARING\",\"event_type\":\"preparing\",\"message\":\"Seller updated order fulfillment status.\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/seller\\/orders\\/1\"}','2026-10-07 17:28:01','2026-10-07 07:44:31','2026-10-07 17:28:01'),('a4ee12d8-9001-406c-b41d-1b9cdc0f7565','App\\Notifications\\OrderStatusNotification','App\\Models\\User',2,'{\"order_id\":1,\"order_number\":\"EZC-UGWZQYDHET\",\"status\":\"CONFIRMED\",\"event_type\":\"confirmed\",\"message\":\"Seller updated order fulfillment status.\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/buyer\\/orders\\/1\"}','2026-10-12 23:56:43','2026-10-06 00:34:28','2026-10-12 23:56:43'),('a912ab1c-dbfa-4b99-879c-ff14ddcd09af','App\\Notifications\\ProductComplianceNotification','App\\Models\\User',3,'{\"product_id\":1,\"product_name\":\"Sweater Clothe No.1\",\"status\":\"approved\",\"message\":\"Sweater Clothe No.1 was approved for sale.\",\"note\":\"\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/seller\\/products\"}','2026-10-06 00:15:00','2026-10-06 00:13:33','2026-10-06 00:15:00'),('ac810859-42a5-4b88-bb5e-d1b56b8d9c74','App\\Notifications\\OrderWorkflowNotification','App\\Models\\User',5,'{\"order_id\":2,\"order_number\":\"EZC-PRUPEFGTKN\",\"event_type\":\"pickup_requested\",\"message\":\"A seller requested pickup for an order.\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/logistics\\/tracking?search=EZC-PRUPEFGTKN\"}','2026-10-12 23:54:51','2026-10-07 18:06:30','2026-10-12 23:54:51'),('c22f539c-71b4-41e1-8989-2f58bf1b13de','App\\Notifications\\OrderStatusNotification','App\\Models\\User',3,'{\"order_id\":1,\"order_number\":\"EZC-UGWZQYDHET\",\"status\":\"CONFIRMED\",\"event_type\":\"confirmed\",\"message\":\"Seller updated order fulfillment status.\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/seller\\/orders\\/1\"}','2026-10-07 07:44:18','2026-10-06 00:34:28','2026-10-07 07:44:18'),('c46078a7-62dd-4a6e-9a02-7bb6c55764d4','App\\Notifications\\AccountStatusNotification','App\\Models\\User',6,'{\"status\":\"approved\",\"message\":\"Your account application has been approved. You can now sign in.\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/login\"}',NULL,'2026-10-06 00:40:46','2026-10-06 00:40:46'),('d31d726a-f33d-44ba-b667-ce7f83ac84a8','App\\Notifications\\ProductComplianceNotification','App\\Models\\User',3,'{\"product_id\":9,\"product_name\":\"baggy cargo pants\",\"status\":\"approved\",\"message\":\"baggy cargo pants was approved for sale.\",\"note\":\"\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/seller\\/products\"}','2026-10-07 17:27:53','2026-10-07 14:51:32','2026-10-07 17:27:53'),('e3da3a9c-c9ff-45d7-a62e-cba16791f94f','App\\Notifications\\ProductComplianceNotification','App\\Models\\User',3,'{\"product_id\":5,\"product_name\":\"leather hand bag\",\"status\":\"approved\",\"message\":\"leather hand bag was approved for sale.\",\"note\":\"\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/seller\\/products\"}','2026-10-07 17:27:46','2026-10-07 14:51:42','2026-10-07 17:27:46'),('ed632403-fce4-49b9-8691-6a8b90e538a8','App\\Notifications\\OrderStatusNotification','App\\Models\\User',3,'{\"order_id\":2,\"order_number\":\"EZC-PRUPEFGTKN\",\"status\":\"PREPARING\",\"event_type\":\"preparing\",\"message\":\"Seller updated order fulfillment status.\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/seller\\/orders\\/2\"}',NULL,'2026-10-07 17:33:07','2026-10-07 17:33:07'),('f0baa568-bf2a-4d3c-9377-f17965050322','App\\Notifications\\OrderStatusNotification','App\\Models\\User',3,'{\"order_id\":1,\"order_number\":\"EZC-UGWZQYDHET\",\"status\":\"READY_FOR_PICKUP\",\"event_type\":\"ready_for_pickup\",\"message\":\"Seller updated order fulfillment status.\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/seller\\/orders\\/1\"}','2026-10-07 17:27:58','2026-10-07 07:44:34','2026-10-07 17:27:58'),('f599ec65-be7c-46e6-9e08-479a68dfd658','App\\Notifications\\ProductComplianceNotification','App\\Models\\User',3,'{\"product_id\":8,\"product_name\":\"leather hand bag 2\",\"status\":\"approved\",\"message\":\"leather hand bag 2 was approved for sale.\",\"note\":\"\",\"url\":\"https:\\/\\/shop.ezicart.trade\\/seller\\/products\"}','2026-10-07 17:27:50','2026-10-07 14:51:35','2026-10-07 17:27:50');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_conversations`
--

DROP TABLE IF EXISTS `order_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_conversations_order_id_unique` (`order_id`),
  CONSTRAINT `order_conversations_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_conversations`
--

LOCK TABLES `order_conversations` WRITE;
/*!40000 ALTER TABLE `order_conversations` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_conversations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `variation_id` bigint unsigned DEFAULT NULL,
  `variation_info` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `quantity` int NOT NULL,
  `item_total` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_product_id_foreign` (`product_id`),
  KEY `order_items_variation_id_foreign` (`variation_id`),
  KEY `order_items_order_id_foreign` (`order_id`),
  CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_items_variation_id_foreign` FOREIGN KEY (`variation_id`) REFERENCES `product_variations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,1,NULL,NULL,'Sweater Clothe No.1',250.00,1,250.00,'2026-10-06 00:31:54','2026-10-06 00:31:54'),(2,2,6,NULL,NULL,'luxury watch 1',1000.00,1,1000.00,'2026-10-07 17:32:38','2026-10-07 17:32:38');
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_messages`
--

DROP TABLE IF EXISTS `order_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `sender_id` bigint unsigned NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_messages_conversation_id_created_at_index` (`conversation_id`,`created_at`),
  KEY `order_messages_sender_id_read_at_index` (`sender_id`,`read_at`),
  CONSTRAINT `order_messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `order_conversations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `order_messages_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_messages`
--

LOCK TABLES `order_messages` WRITE;
/*!40000 ALTER TABLE `order_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parcel_code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parcel_code_version` int unsigned NOT NULL DEFAULT '1',
  `buyer_id` bigint unsigned NOT NULL,
  `seller_id` bigint unsigned NOT NULL,
  `courier_id` bigint unsigned DEFAULT NULL,
  `pickup_courier_id` bigint unsigned DEFAULT NULL,
  `delivery_courier_id` bigint unsigned DEFAULT NULL,
  `sorting_center_id` bigint unsigned DEFAULT NULL,
  `recipient_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_contact` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `province` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `municipality` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `barangay` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `street_address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `delivery_area` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_area_id` bigint unsigned DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `voucher_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `shipping_fee` decimal(10,2) NOT NULL DEFAULT '50.00',
  `commission_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PLACED',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `picked_up_at` timestamp NULL DEFAULT NULL,
  `pickup_claimed_at` timestamp NULL DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `sorted_at` timestamp NULL DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `hub_released_at` timestamp NULL DEFAULT NULL,
  `delivery_recovered_at` timestamp NULL DEFAULT NULL,
  `out_for_delivery_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `delivery_failure_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_notes` text COLLATE utf8mb4_unicode_ci,
  `delivery_code_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_code_encrypted` text COLLATE utf8mb4_unicode_ci,
  `delivery_code_expires_at` timestamp NULL DEFAULT NULL,
  `delivery_code_attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `delivery_code_used_at` timestamp NULL DEFAULT NULL,
  `cod_collected_amount` decimal(10,2) DEFAULT NULL,
  `inventory_restored_at` timestamp NULL DEFAULT NULL,
  `pickup_arrived_at` timestamp NULL DEFAULT NULL,
  `seller_handover_at` timestamp NULL DEFAULT NULL,
  `pickup_requested_at` timestamp NULL DEFAULT NULL,
  `pickup_scheduled_for` timestamp NULL DEFAULT NULL,
  `pickup_window` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_notes` text COLLATE utf8mb4_unicode_ci,
  `return_handed_to_seller_at` timestamp NULL DEFAULT NULL,
  `region` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `house_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_order_number_unique` (`order_number`),
  UNIQUE KEY `orders_parcel_code_unique` (`parcel_code`),
  KEY `orders_status_delivered_at_index` (`status`,`delivered_at`),
  KEY `orders_delivery_courier_id_status_index` (`delivery_courier_id`,`status`),
  KEY `orders_status_updated_at_index` (`status`,`updated_at`),
  KEY `orders_received_at_index` (`received_at`),
  KEY `orders_sorted_at_index` (`sorted_at`),
  KEY `orders_assigned_at_index` (`assigned_at`),
  KEY `orders_failed_at_index` (`failed_at`),
  KEY `orders_destination_area_id_foreign` (`destination_area_id`),
  KEY `orders_buyer_id_foreign` (`buyer_id`),
  KEY `orders_seller_id_foreign` (`seller_id`),
  KEY `orders_courier_id_foreign` (`courier_id`),
  KEY `orders_sorting_center_id_foreign` (`sorting_center_id`),
  KEY `orders_pickup_courier_id_status_pickup_claimed_at_index` (`pickup_courier_id`,`status`,`pickup_claimed_at`),
  KEY `orders_delivery_code_expires_at_delivery_code_used_at_index` (`delivery_code_expires_at`,`delivery_code_used_at`),
  CONSTRAINT `orders_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `orders_courier_id_foreign` FOREIGN KEY (`courier_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `orders_delivery_courier_id_foreign` FOREIGN KEY (`delivery_courier_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `orders_destination_area_id_foreign` FOREIGN KEY (`destination_area_id`) REFERENCES `areas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `orders_pickup_courier_id_foreign` FOREIGN KEY (`pickup_courier_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `orders_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `orders_sorting_center_id_foreign` FOREIGN KEY (`sorting_center_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,'EZC-UGWZQYDHET','00ANBU0EXMAYNTFYKSRJIPQD',1,2,3,NULL,NULL,NULL,NULL,'John Buyer','09123456781','Laguna','Majayjay','San Roque','45 Market Rd.',NULL,NULL,250.00,NULL,0.00,50.00,25.00,300.00,'COD','READY_FOR_PICKUP','Blah blah','2026-10-06 00:31:54','2026-10-07 07:45:32',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,'2026-10-07 07:45:32','2026-10-07 15:45:00','Morning (8 AM–12 PM)',NULL,NULL,NULL,NULL),(2,'EZC-PRUPEFGTKN','D4WSQ9RL15KX3XNXYUYSCXCT',1,2,3,NULL,NULL,NULL,NULL,'John Buyer','09123456781','Laguna','Majayjay','Poblacion','purok 1 sampaguita',NULL,NULL,1000.00,NULL,0.00,50.00,100.00,1050.00,'COD','READY_FOR_PICKUP',NULL,'2026-10-07 17:32:38','2026-10-07 18:06:30',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,'2026-10-07 18:06:30','2026-10-08 02:06:00','Morning (8 AM–12 PM)',NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parcel_label_reprints`
--

DROP TABLE IF EXISTS `parcel_label_reprints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parcel_label_reprints` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `actor_id` bigint unsigned NOT NULL,
  `previous_parcel_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `replacement_parcel_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `previous_version` int unsigned NOT NULL,
  `replacement_version` int unsigned NOT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parcel_label_reprints_replacement_parcel_code_unique` (`replacement_parcel_code`),
  KEY `parcel_label_reprints_actor_id_foreign` (`actor_id`),
  KEY `parcel_label_reprints_order_id_created_at_index` (`order_id`,`created_at`),
  CONSTRAINT `parcel_label_reprints_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `parcel_label_reprints_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parcel_label_reprints`
--

LOCK TABLES `parcel_label_reprints` WRITE;
/*!40000 ALTER TABLE `parcel_label_reprints` DISABLE KEYS */;
/*!40000 ALTER TABLE `parcel_label_reprints` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parcel_placements`
--

DROP TABLE IF EXISTS `parcel_placements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parcel_placements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `active_order_id` bigint unsigned DEFAULT NULL,
  `location_id` bigint unsigned NOT NULL,
  `placed_by` bigint unsigned NOT NULL,
  `placed_at` timestamp NOT NULL,
  `removed_by` bigint unsigned DEFAULT NULL,
  `removed_at` timestamp NULL DEFAULT NULL,
  `removal_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parcel_placements_active_order_id_unique` (`active_order_id`),
  KEY `parcel_placements_order_id_foreign` (`order_id`),
  KEY `parcel_placements_placed_by_foreign` (`placed_by`),
  KEY `parcel_placements_removed_by_foreign` (`removed_by`),
  KEY `parcel_placements_location_id_removed_at_index` (`location_id`,`removed_at`),
  CONSTRAINT `parcel_placements_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `storage_locations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `parcel_placements_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `parcel_placements_placed_by_foreign` FOREIGN KEY (`placed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `parcel_placements_removed_by_foreign` FOREIGN KEY (`removed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parcel_placements`
--

LOCK TABLES `parcel_placements` WRITE;
/*!40000 ALTER TABLE `parcel_placements` DISABLE KEYS */;
/*!40000 ALTER TABLE `parcel_placements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parcel_tracking_events`
--

DROP TABLE IF EXISTS `parcel_tracking_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parcel_tracking_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `event_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parcel_tracking_events_order_id_created_at_index` (`order_id`,`created_at`),
  KEY `parcel_tracking_events_event_type_created_at_index` (`event_type`,`created_at`),
  KEY `parcel_tracking_events_actor_id_foreign` (`actor_id`),
  CONSTRAINT `parcel_tracking_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `parcel_tracking_events_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parcel_tracking_events`
--

LOCK TABLES `parcel_tracking_events` WRITE;
/*!40000 ALTER TABLE `parcel_tracking_events` DISABLE KEYS */;
INSERT INTO `parcel_tracking_events` VALUES (1,1,2,'order_placed','PLACED','Majayjay','Order placed by buyer.','2026-10-06 00:31:54','2026-10-06 00:31:54'),(2,1,3,'confirmed','CONFIRMED','Majayjay','Seller updated order fulfillment status.','2026-10-06 00:34:28','2026-10-06 00:34:28'),(3,1,3,'preparing','PREPARING','Majayjay','Seller updated order fulfillment status.','2026-10-07 07:44:31','2026-10-07 07:44:31'),(4,1,3,'ready_for_pickup','READY_FOR_PICKUP','Majayjay','Seller updated order fulfillment status.','2026-10-07 07:44:34','2026-10-07 07:44:34'),(5,1,3,'pickup_requested','READY_FOR_PICKUP','78 Merchant Ave., Majayjay, Laguna','Seller requested pickup for Oct 7, 2026 11:45 PM (Morning (8 AM–12 PM)).','2026-10-07 07:45:32','2026-10-07 07:45:32'),(6,2,2,'order_placed','PLACED','Majayjay','Order placed by buyer.','2026-10-07 17:32:38','2026-10-07 17:32:38'),(7,2,3,'confirmed','CONFIRMED','Majayjay','Seller updated order fulfillment status.','2026-10-07 17:33:03','2026-10-07 17:33:03'),(8,2,3,'preparing','PREPARING','Majayjay','Seller updated order fulfillment status.','2026-10-07 17:33:07','2026-10-07 17:33:07'),(9,2,3,'ready_for_pickup','READY_FOR_PICKUP','Majayjay','Seller updated order fulfillment status.','2026-10-07 17:33:10','2026-10-07 17:33:10'),(10,2,3,'pickup_requested','READY_FOR_PICKUP','purok 1 sampaguita, Majayjay, Laguna','Seller requested pickup for Oct 8, 2026 10:06 AM (Morning (8 AM–12 PM)).','2026-10-07 18:06:30','2026-10-07 18:06:30');
/*!40000 ALTER TABLE `parcel_tracking_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_compliance_events`
--

DROP TABLE IF EXISTS `product_compliance_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_compliance_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `actor_id` bigint unsigned NOT NULL,
  `action` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `previous_status` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_compliance_events_actor_id_foreign` (`actor_id`),
  KEY `product_compliance_events_product_id_created_at_index` (`product_id`,`created_at`),
  KEY `product_compliance_events_action_created_at_index` (`action`,`created_at`),
  CONSTRAINT `product_compliance_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `product_compliance_events_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_compliance_events`
--

LOCK TABLES `product_compliance_events` WRITE;
/*!40000 ALTER TABLE `product_compliance_events` DISABLE KEYS */;
INSERT INTO `product_compliance_events` VALUES (1,1,3,'submitted',NULL,'pending_review','New product submitted for compliance review.','2026-10-06 00:02:30','2026-10-06 00:02:30'),(2,1,1,'approved','pending_review','approved',NULL,'2026-10-06 00:13:32','2026-10-06 00:13:32'),(3,2,3,'submitted',NULL,'pending_review','New product submitted for compliance review.','2026-10-06 00:42:10','2026-10-06 00:42:10'),(4,3,3,'submitted',NULL,'pending_review','New product submitted for compliance review.','2026-10-06 00:42:14','2026-10-06 00:42:14'),(5,3,1,'approved','pending_review','approved',NULL,'2026-10-06 00:47:30','2026-10-06 00:47:30'),(6,4,3,'submitted',NULL,'pending_review','New product submitted for compliance review.','2026-10-07 14:44:41','2026-10-07 14:44:41'),(7,5,3,'submitted',NULL,'pending_review','New product submitted for compliance review.','2026-10-07 14:45:51','2026-10-07 14:45:51'),(8,6,3,'submitted',NULL,'pending_review','New product submitted for compliance review.','2026-10-07 14:46:49','2026-10-07 14:46:49'),(9,7,3,'submitted',NULL,'pending_review','New product submitted for compliance review.','2026-10-07 14:47:37','2026-10-07 14:47:37'),(10,8,3,'submitted',NULL,'pending_review','New product submitted for compliance review.','2026-10-07 14:48:25','2026-10-07 14:48:25'),(11,9,3,'submitted',NULL,'pending_review','New product submitted for compliance review.','2026-10-07 14:49:27','2026-10-07 14:49:27'),(12,10,3,'submitted',NULL,'pending_review','New product submitted for compliance review.','2026-10-07 14:50:40','2026-10-07 14:50:40'),(13,10,1,'approved','pending_review','approved',NULL,'2026-10-07 14:51:22','2026-10-07 14:51:22'),(14,9,1,'approved','pending_review','approved',NULL,'2026-10-07 14:51:32','2026-10-07 14:51:32'),(15,8,1,'approved','pending_review','approved',NULL,'2026-10-07 14:51:35','2026-10-07 14:51:35'),(16,7,1,'approved','pending_review','approved',NULL,'2026-10-07 14:51:37','2026-10-07 14:51:37'),(17,6,1,'approved','pending_review','approved',NULL,'2026-10-07 14:51:39','2026-10-07 14:51:39'),(18,5,1,'approved','pending_review','approved',NULL,'2026-10-07 14:51:42','2026-10-07 14:51:42'),(19,4,1,'approved','pending_review','approved',NULL,'2026-10-07 14:51:44','2026-10-07 14:51:44'),(20,11,3,'submitted',NULL,'pending_review','New product submitted for compliance review.','2026-10-13 00:36:55','2026-10-13 00:36:55');
/*!40000 ALTER TABLE `product_compliance_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_variations`
--

DROP TABLE IF EXISTS `product_variations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_variations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price_adjustment` decimal(10,2) NOT NULL DEFAULT '0.00',
  `stock` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_variations_product_id_foreign` (`product_id`),
  CONSTRAINT `product_variations_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_variations`
--

LOCK TABLES `product_variations` WRITE;
/*!40000 ALTER TABLE `product_variations` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_variations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `compliance_status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_review',
  `compliance_note` text COLLATE utf8mb4_unicode_ci,
  `compliance_reviewed_by` bigint unsigned DEFAULT NULL,
  `compliance_reviewed_at` timestamp NULL DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `products_user_id_foreign` (`user_id`),
  KEY `products_compliance_reviewed_by_foreign` (`compliance_reviewed_by`),
  KEY `products_compliance_status_created_at_index` (`compliance_status`,`created_at`),
  CONSTRAINT `products_compliance_reviewed_by_foreign` FOREIGN KEY (`compliance_reviewed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `products_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,3,'Sweater Clothe No.1','Premium Charcoal Ribbed Knit Sweater | Minimalist Crewneck Pullover\r\nStay warm and effortlessly stylish with this cozy chunky knit sweater. Featuring a classic crew neckline and a thick ribbed texture, it’s the perfect blend of comfort and casual fashion. The subtle brown leather patch at the hem gives it a premium, minimalist touch. It\'s a fantastic staple piece to feature in your EZiCart catalog for the cozy season!\r\n✨ Key Features:\r\n * Material: Soft, thick ribbed knit fabric for maximum warmth.\r\n * Style: Minimalist crewneck pullover.\r\n * Color: Versatile dark charcoal grey.\r\n * Details: Accented with a small brown leather logo tag at the bottom hem.\r\n * Fit: Relaxed, comfortable fit perfect for layering.\r\n💡 Styling Tip: Pair it with your favorite denim or chinos for an easy, elevated everyday look.','Fashion','approved',NULL,1,'2026-10-06 00:13:32',250.00,9,'products/HKYciI0Kxeg1Hzcza9npiDdb7YVYYjeAHh4LCnIQ.jpg',0,'2026-10-06 00:02:30','2026-10-06 00:31:54'),(2,3,'Test','Test','Fashion','pending_review',NULL,NULL,NULL,999.00,10,'products/NKgLUFUiJgPf4Ib9CvSEU2jupLUtXaBG7yt8wuAJ.jpg',1,'2026-10-06 00:42:10','2026-10-07 14:33:32'),(3,3,'Test','Test','Fashion','approved',NULL,1,'2026-10-06 00:47:30',999.00,10,'products/B2LkHRi0FtENKchFsvgvAVqd037JKh1dU4Fp1nQG.jpg',1,'2026-10-06 00:42:14','2026-10-07 14:33:19'),(4,3,'sneaker 1','Elevate your everyday look with these premium minimalist white sneakers featuring elegant metallic gold accents and soft suede overlays. Crafted with a padded interior lining and a durable, non-slip rubber sole, they combine all-day walking comfort with effortless luxury styling.\r\n\r\n* **Luxe Minimalist Aesthetic:** Smooth white leather upper elevated by metallic gold heel tabs and refined suede trim.\r\n\r\n\r\n* **All-Day Cushioning:** Soft padded collar and shock-absorbing footbed for maximum comfort.\r\n* **Non-Slip Rubber Outsole:** Flat vulcanized sole provides superior grip and durability.\r\n\r\n\r\n* **Versatile Wardrobe Staple:** Pairs effortlessly with jeans, skirts, shorts, or smart-casual attire.','Fashion','approved',NULL,1,'2026-10-07 14:51:44',2500.00,99,'products/WvAok3DuHRYRkHRbJvkHUHE9nGQ84qthvxmDpAAF.jpg',0,'2026-10-07 14:44:41','2026-10-07 14:51:44'),(5,3,'leather hand bag','Elevate your everyday aesthetic with this soft, slouchy leather hobo shoulder bag. Crafted from rich pebbled-grain textured leather in a warm cognac tan shade, this spacious bag features a clean center-seam accent and sleek gold-tone hardware. Designed with a comfortable wide shoulder strap and a roomy interior, it offers the perfect blend of minimalist quiet luxury and practical daily function.\r\n\r\n* 👜 **Timeless Hobo Design:** Relaxed, soft slouchy silhouette crafted with a rich pebbled leather texture.\r\n\r\n\r\n* ✨ **Gold-Tone Accents:** Premium metallic ring hardware attaching a durable, comfortable shoulder strap.\r\n\r\n\r\n* 📦 **Spacious Interior:** Easily holds your daily essentials—iPad/tablet, cosmetics, wallet, phone, and fold-up umbrella.\r\n* 👗 **Effortless Styling:** Perfect for work, coffee runs, shopping, or weekend getaways with a chic modern look.','Fashion','approved',NULL,1,'2026-10-07 14:51:42',500.00,10,'products/bYAAz0Q3KYH9lGlK5mUKcPGggmlyjL0PKuaVvOpf.jpg',0,'2026-10-07 14:45:51','2026-10-07 14:51:42'),(6,3,'luxury watch 1','Elevate your prestige with this iconic luxury stainless steel sports watch featuring a signature sunburst blue horizontally embossed dial and an integrated bracelet. Crafted with a polished rounded-octagonal bezel, luminous hands, and a functional date window at 3 o\'clock, it delivers timeless luxury and precision engineering for both business and leisure.\r\n\r\n* ⌚ **Iconic Cushion-Octagonal Bezel:** Premium brushed and polished stainless steel case with seamless integrated link bracelet.\r\n\r\n\r\n* 🌊 **Sunburst Blue Embossed Dial:** Distinctive horizontal grooved pattern with luminous hands and markers for low-light visibility.\r\n\r\n\r\n* 📅 **Functional Date Display:** Easy-to-read date window positioned at 3 o\'clock.\r\n\r\n\r\n* 💧 **Water-Resistant Build:** Engineered for everyday wear, rain resistance, and splash protection with a secure crown design.\r\n\r\n\r\n* 👔 **Versatile Executive Style:** The ultimate luxury piece to elevate suits, formal attire, or high-end casual wear.','Fashion','approved',NULL,1,'2026-10-07 14:51:39',1000.00,99,'products/LaQ3RVUrhevQgoKCHOuR9wdPtq6KulLMmBR3YpTk.jpg',1,'2026-10-07 14:46:49','2026-10-14 19:54:05'),(7,3,'shoes 2','Engineered for speed and comfort, these premium blue performance running shoes feature a lightweight, breathable mesh upper and responsive cushioning. Designed with secure lace-up support and a durable rubber outsole built for optimal track and road traction, they provide maximum energy return and stability for every stride.\r\n\r\n* 🏃 **Responsive Cushioning:** Delivers a springy, high-energy return for long-distance runs and daily track workouts.\r\n\r\n\r\n* 🌬️ **Breathable Mesh Upper:** Lightweight ventilation keeps your feet cool, dry, and comfortable during intense cardio.\r\n\r\n\r\n* 👟 **Secure Locked-In Fit:** Integrated lace system wraps the midfoot for optimal stability and snug support.\r\n\r\n\r\n* 🏁 **High-Traction Outsole:** Durable rubber tread engineered for flexible transitions and superior grip on pavement and running tracks.','Fashion','approved',NULL,1,'2026-10-07 14:51:37',500.00,99,'products/4ZmMFlbf5bFvbfY8GIHnbLwMWBt2CcR3JaVHah0E.jpg',0,'2026-10-07 14:47:37','2026-10-07 14:51:37'),(8,3,'leather hand bag 2','Elevate your daily wardrobe with this elegant structured flap crossbody bag in a classic saddle tan shade. Crafted from durable, scratch-resistant textured leather, it features a sophisticated gold-tone push-lock closure and an adjustable shoulder strap for versatile, hands-free wear.\r\n\r\n* 💼 **Classic Flap Silhouette:** Clean envelope flap design paired with a stylish metallic gold clasp closure.\r\n\r\n\r\n* 🧵 **Durable Textured Leather:** Premium scratch-resistant finish built for long-lasting daily use.\r\n\r\n\r\n* 🎒 **Adjustable Crossbody Strap:** Customizable shoulder strap ensures comfortable, lightweight carrying.\r\n\r\n\r\n* 📦 **Organized Interior Space:** Perfect size to neatly fit your smartphone, compact wallet, keys, and lipsticks.\r\n* 👗 **Versatile Parisian Style:** Easily transitions from casual daytime coffee runs to elegant evening dinners.','Fashion','approved',NULL,1,'2026-10-07 14:51:35',500.00,10,'products/yBvgWo27fbJERUbvNxsWdwC0jxw0lgeudkxf0gd9.jpg',0,'2026-10-07 14:48:25','2026-10-07 14:51:35'),(9,3,'baggy cargo pants','Upgrade your streetwear wardrobe with these vintage military utility cargo pants in a washed army green tone. Crafted from heavy-duty cotton fabric with a relaxed baggy fit and wide-leg cut, they feature multiple functional cargo pockets with button flap closures for authentic tactical style and maximum daily utility.\r\n\r\n* 👖 **Relaxed Baggy Fit:** Loose, wide-leg silhouette provides maximum comfort, movement, and effortless streetwear aesthetic.\r\n\r\n\r\n* 📦 **Multi-Pocket Utility:** Equipped with multiple spacious cargo flap pockets to securely hold your phone, wallet, and essentials.\r\n\r\n\r\n* 🧵 **Heavy-Duty Fabric:** Constructed from durable, breathable woven material built to withstand daily wear.\r\n\r\n\r\n* 🎨 **Versatile Tactical Look:** Easily pairs with oversized t-shirts, hoodies, crop tops, and chunky sneakers.','Fashion','approved',NULL,1,'2026-10-07 14:51:32',299.00,99,'products/OiAG1ZoxQNRYNfse6Ra3OfajJsOZmjl5fLhB1NMK.jpg',0,'2026-10-07 14:49:27','2026-10-07 14:51:32'),(10,3,'white trouser','Elevate your everyday wardrobe with these chic high-waisted wide-leg linen trousers in an airy beige oatmeal shade. Designed with crisp front pleats, belt loops, and a flowing wide-leg silhouette, they offer a sophisticated blend of Old Money luxury aesthetic and lightweight, breathable comfort.\r\n\r\n* 👖 **Flattering High-Waist Fit:** Elongates your legs with tailored front pleats and classic belt loops.\r\n\r\n\r\n* 🌾 **Breathable Linen Fabric:** Lightweight, airy woven material designed to keep you cool and comfortable all day.\r\n\r\n\r\n* ✨ **Effortless Wide-Leg Flow:** Relaxed, wide-leg drape creates a sophisticated and fluid silhouette.\r\n\r\n\r\n* 💼 **Versatile Styling:** Perfect for office workwear, cafe dates, beach getaways, or smart-casual street style.','Fashion','approved',NULL,1,'2026-10-07 14:51:22',399.00,98,'products/D7SV2ey4k5GVYgJYQHIki1s1w2LFKG2YbXH0g4Ho.jpg',1,'2026-10-07 14:50:40','2026-10-14 19:30:27'),(11,3,'test','test','Electronics','pending_review',NULL,NULL,NULL,1000.00,99,'products/MX0NVxcLHxyejGKnPCWlPdWVAxoFa3Fe9eCAs3W4.png',0,'2026-10-13 00:36:55','2026-10-13 00:36:55');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `buyer_id` bigint unsigned NOT NULL,
  `rating` tinyint unsigned NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reviews_order_id_product_id_unique` (`order_id`,`product_id`),
  KEY `reviews_product_id_foreign` (`product_id`),
  KEY `reviews_buyer_id_foreign` (`buyer_id`),
  CONSTRAINT `reviews_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `reviews_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `reviews_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scan_events`
--

DROP TABLE IF EXISTS `scan_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `scan_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned DEFAULT NULL,
  `location_id` bigint unsigned DEFAULT NULL,
  `actor_id` bigint unsigned NOT NULL,
  `station` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `result` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `failure_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `method` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `scan_events_order_id_foreign` (`order_id`),
  KEY `scan_events_actor_id_foreign` (`actor_id`),
  KEY `scan_events_station_created_at_index` (`station`,`created_at`),
  KEY `scan_events_location_id_foreign` (`location_id`),
  CONSTRAINT `scan_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `scan_events_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `scan_events_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scan_events`
--

LOCK TABLES `scan_events` WRITE;
/*!40000 ALTER TABLE `scan_events` DISABLE KEYS */;
INSERT INTO `scan_events` VALUES (1,2,NULL,5,'intake','rejected','Unknown parcel or parcel is not eligible for intake.','camera','160.20.40.246','2026-10-07 17:45:45'),(2,2,NULL,5,'intake','rejected','Unknown parcel or parcel is not eligible for intake.','camera','160.20.40.246','2026-10-07 17:45:51'),(3,2,NULL,5,'intake','rejected','Unknown parcel or parcel is not eligible for intake.','camera','160.20.40.246','2026-10-07 17:46:00'),(4,2,NULL,5,'intake','rejected','Unknown parcel or parcel is not eligible for intake.','camera','160.20.40.246','2026-10-07 17:46:16'),(5,2,NULL,5,'intake','rejected','Unknown parcel or parcel is not eligible for intake.','camera','160.20.40.246','2026-10-07 17:50:45'),(6,2,NULL,5,'intake','rejected','Unknown parcel or parcel is not eligible for intake.','camera','160.20.40.246','2026-10-07 17:51:13'),(7,2,NULL,5,'intake','rejected','Unknown parcel or parcel is not eligible for intake.','camera','160.20.40.246','2026-10-07 17:51:40'),(8,2,NULL,5,'intake','rejected','Unknown parcel or parcel is not eligible for intake.','camera','160.20.40.246','2026-10-07 17:51:48'),(9,2,NULL,5,'intake','rejected','Unknown parcel or parcel is not eligible for intake.','camera','160.20.40.246','2026-10-07 17:52:28'),(10,2,NULL,5,'intake','rejected','Unknown parcel or parcel is not eligible for intake.','camera','160.20.40.246','2026-10-07 17:52:34'),(11,2,NULL,5,'intake','rejected','Unknown parcel or parcel is not eligible for intake.','camera','160.20.40.246','2026-10-07 17:52:37'),(12,2,NULL,5,'intake','rejected','Unknown parcel or parcel is not eligible for intake.','camera','160.20.40.246','2026-10-07 17:52:44'),(13,2,NULL,5,'intake','rejected','Unknown parcel or parcel is not eligible for intake.','camera','160.20.40.246','2026-10-07 17:52:47'),(14,2,NULL,5,'intake','rejected','Unknown parcel or parcel is not eligible for intake.','camera','160.20.40.246','2026-10-07 17:52:48'),(15,2,NULL,5,'intake','rejected','Unknown parcel or parcel is not eligible for intake.','camera','160.20.40.246','2026-10-07 17:52:49'),(16,2,NULL,5,'intake','rejected','Unknown parcel or parcel is not eligible for intake.','camera','160.20.40.246','2026-10-07 17:52:50'),(17,2,NULL,5,'intake','rejected','Unknown parcel or parcel is not eligible for intake.','camera','160.20.40.246','2026-10-07 17:52:51'),(18,2,NULL,5,'intake','rejected','Unknown parcel or parcel is not eligible for intake.','camera','160.20.40.246','2026-10-07 17:52:52'),(19,2,NULL,5,'intake','rejected','Unknown parcel or parcel is not eligible for intake.','camera','111.90.220.180','2026-10-07 18:05:32');
/*!40000 ALTER TABLE `scan_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
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
INSERT INTO `sessions` VALUES ('I7PDFeoebuKvVxp5rW995FkfnIh5GluEzx9Ek5vw',2,'112.201.194.12','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJEbXJMMTQxT1FwdVQzNVJFdnNiRHl1b2RUODJ1aml1VzZMSWtwRnFEIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cHM6XC9cL3Nob3AuZXppY2FydC50cmFkZVwvP21heF9wcmljZT0wLjM0Jm1pbl9wcmljZT0mc2VhcmNoPSZzb3J0PW5ld2VzdCIsInJvdXRlIjoiaG9tZSJ9LCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6MiwicGFzc3dvcmRfaGFzaF93ZWIiOiIxN2Q2MjAzNWYzOWViYjNhYzAzZTZkYzE3YTZhMmJhMGNmYzc5N2NjMDlkOTU5YjkxYzE1OTc4YjE5Nzg3M2I1In0=',1792348871);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `storage_locations`
--

DROP TABLE IF EXISTS `storage_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `storage_locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `hub_id` bigint unsigned NOT NULL,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `area_id` bigint unsigned DEFAULT NULL,
  `capacity` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `storage_locations_hub_id_code_unique` (`hub_id`,`code`),
  KEY `storage_locations_area_id_foreign` (`area_id`),
  KEY `storage_locations_hub_id_type_is_active_index` (`hub_id`,`type`,`is_active`),
  CONSTRAINT `storage_locations_area_id_foreign` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `storage_locations_hub_id_foreign` FOREIGN KEY (`hub_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `storage_locations`
--

LOCK TABLES `storage_locations` WRITE;
/*!40000 ALTER TABLE `storage_locations` DISABLE KEYS */;
/*!40000 ALTER TABLE `storage_locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'buyer',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `badge_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suspension_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suspended_at` timestamp NULL DEFAULT NULL,
  `suspension_previous_status` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suspension_source` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `suffix` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `middle_initial` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sex` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_no` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `birthday` date DEFAULT NULL,
  `age` int DEFAULT NULL,
  `province` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `municipality` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barangay` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `street_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_upload_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `line_of_business` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_area` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_permit_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vehicle_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plate_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permit_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `or_cr_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `or_cr_upload_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `canonical_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `canonical_mobile` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `canonical_plate` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shop_name` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `canonical_company_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seller_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tin` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `region` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sec_registration_no` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_position` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_areas` json DEFAULT NULL,
  `sec_certificate_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bir_certificate_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `representative_id_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_number` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_expires_at` date DEFAULT NULL,
  `logistics_owner_id` bigint unsigned DEFAULT NULL,
  `terms_accepted_at` timestamp NULL DEFAULT NULL,
  `terms_version` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `privacy_accepted_at` timestamp NULL DEFAULT NULL,
  `privacy_version` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `house_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_badge_code_unique` (`badge_code`),
  UNIQUE KEY `users_canonical_email_unique` (`canonical_email`),
  UNIQUE KEY `users_canonical_mobile_unique` (`canonical_mobile`),
  UNIQUE KEY `users_canonical_plate_unique` (`canonical_plate`),
  UNIQUE KEY `users_shop_name_unique` (`shop_name`),
  UNIQUE KEY `users_canonical_company_name_unique` (`canonical_company_name`),
  UNIQUE KEY `users_tin_unique` (`tin`),
  UNIQUE KEY `users_sec_registration_no_unique` (`sec_registration_no`),
  UNIQUE KEY `users_license_number_unique` (`license_number`),
  KEY `users_logistics_owner_id_foreign` (`logistics_owner_id`),
  KEY `users_approved_by_foreign` (`approved_by`),
  CONSTRAINT `users_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_logistics_owner_id_foreign` FOREIGN KEY (`logistics_owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin@ezicart.com',NULL,'$2y$12$EseSNsRp1vM2DgV1an9SIe/RnJDB4JktInHfWRMp9WqbomvSxpZPu','admin','approved',NULL,NULL,NULL,NULL,NULL,'System',NULL,'Admin',NULL,'A','Other','09123456780','1990-01-01',34,'Laguna','Majayjay','Poblacion','123 Admin Way',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 07:04:24','2026-09-22 07:04:24',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'buyer@ezicart.com','2026-10-18 18:21:38','$argon2id$v=19$m=65536,t=4,p=1$MmNVdkJVRVBsTlB5eDZlZA$FIHyN3anb1arsV2Ig4ZXEM/WmCmKQSQCcvAdyMdx88w','buyer','approved',NULL,NULL,NULL,NULL,NULL,'Juan123',NULL,'Buyer',NULL,'B','Male','090556253600','1998-05-12',26,'Laguna','Magdalena','Buo','purok 1 sampaguita',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'GmO1mRjukSup3ta0qFJW8tcQypywRByQ5IuV3DScIPyMmTfoTfzWQp2SWmYk','2026-09-22 07:04:24','2026-10-18 18:21:38',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Region IV-A (CALABARZON)',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'69'),(3,'seller@ezicart.com','2026-10-18 08:18:06','$2y$12$jq2ogjwq3vOsxjH.z9R5EeAFmi9qtBuaPsNcSu02Om2d7TuvdYs2i','seller','approved',NULL,NULL,NULL,NULL,NULL,'Sarah',NULL,'Seller',NULL,'S','Female','09123456782','1995-11-20',29,'Laguna','Majayjay','Poblacion','purok 1 sampaguita',NULL,'Sarah Essentials Store','Beauty & Personal Care',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 07:04:24','2026-10-18 08:18:06',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(4,'courier@ezicart.com','2026-10-18 08:11:39','$2y$12$3TSFzjjDXPZoXJIkwbd3zOXZIN/zSBsJwp6X0hDMFifaoQD0f2zce','courier','approved','ORCw2dfRAkFOv4NdMGc3TPhuw3vYWj15mqVSIFRGJYgqhdYb',NULL,NULL,NULL,NULL,'Carlos',NULL,'Courier',NULL,'C','Male','09123456783','1996-03-15',28,'Laguna','Majayjay','Poblacion','purok 1 sampaguita',NULL,NULL,NULL,NULL,NULL,'Motorcycle','MC-4589',NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 07:04:25','2026-10-18 08:11:39',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(5,'sorting@ezicart.com',NULL,'$2y$12$YswEdDTzqbm2lOROiX6lpe6quV2Pg7/5VhorBz1ERc6wGrRA07qYK','sorting_center','approved',NULL,NULL,NULL,NULL,NULL,'Laguna Central',NULL,'Hub',NULL,'L','Male','09123456785','1990-01-01',36,'Laguna','Santa Cruz','Poblacion','Purok 1 sampaguita',NULL,'EZiCart Central Sorting Hub',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 07:04:25','2026-10-07 17:38:37',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(6,'pending.seller@ezicart.com',NULL,'$2y$12$diuDeOKBNGwdkqJ0AudStu14b.Gh.1S44.zKoYK2PN1oTz4wAoXQa','seller','approved',NULL,NULL,NULL,NULL,NULL,'Pending',NULL,'Merchant',NULL,'P','Male','09123456784','2000-01-10',24,'Laguna','Majayjay','Poblacion','99 Waitlist St.',NULL,'Tech Gear Central','Electronics',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-22 07:04:25','2026-10-06 00:40:46',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(7,'denzellecontado8@gmail.com',NULL,'$2y$12$L9LxbeGXXYMZtVsSJeK5MuIJUnuPVhL5RwIt4N.UFU/Is7G7S3L1i','buyer','approved',NULL,NULL,NULL,NULL,NULL,'Denzelle','Agojo','Contado','I',NULL,NULL,'09127409775','2006-08-15',20,'Laguna','Majayjay','San Isidro','Purok 1 sampaguita',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-11 11:04:38','2026-10-11 11:41:36','denzellecontado8@gmail.com','09127409775',NULL,NULL,NULL,NULL,NULL,'4005','Region IV-A (CALABARZON)',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-11 11:04:38','2026-10','2026-10-11 11:04:38','2026-10',NULL,NULL,NULL,'154509'),(8,'denzelle.a.contado.l@gmail.com','2026-10-14 14:44:15','$2y$12$PAsbe1bWwxlu1zULegVFgeUmrie6sRA8tN6jBi7t4VAqcqFwdnXQK','buyer','approved',NULL,NULL,NULL,NULL,NULL,'Denze','a','contado','jr',NULL,NULL,'09663900451','2006-08-15',20,'Laguna','Majayjay','San Isidro','Purok 1 Sampaguita Brgy San Isidro',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-14 14:35:14','2026-10-14 14:44:15','denzelle.a.contado.l@gmail.com','09663900451',NULL,NULL,NULL,NULL,NULL,'4005','Region IV-A (CALABARZON)',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-14 14:35:14','2026-10','2026-10-14 14:35:14','2026-10',NULL,NULL,NULL,'15b'),(9,'r7924454@gmail.com',NULL,'$2y$12$ufqJBfPUeHKBRkgVuhgtr.0ma2z4j5ZhWcmbey6whifATwN7b2UuC','seller','unverified',NULL,NULL,NULL,NULL,NULL,'rando',NULL,'rando',NULL,NULL,NULL,'09663900452',NULL,NULL,'Laguna','Majayjay','San Isidro','Purok 1 Sampaguita Brgy San Isidro',NULL,'randostore','Home & Living',NULL,NULL,NULL,NULL,'documents/ids/GKf8hgqkt07rwrcUi5bbVC8eZhnbAockIjX00kSo.png',NULL,NULL,NULL,NULL,NULL,'2026-10-14 14:50:42','2026-10-14 14:50:42','r7924454@gmail.com','09663900452',NULL,'randostore',NULL,'Individual','123-456-789-000','4005','Region IV-A (CALABARZON)',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-14 14:50:42','2026-10','2026-10-14 14:50:42','2026-10',NULL,NULL,NULL,'220b'),(10,'hip00n.15@gmail.com',NULL,'$2y$12$HSSltbPYOt5tr6GRlrj2iO00F6bACxvNbGTbs5udsUNlU5UK0iYt2','buyer','unverified',NULL,NULL,NULL,NULL,NULL,'dustine',NULL,'contado',NULL,NULL,NULL,'09127409774','2006-08-15',20,'Laguna','Majayjay','San Isidro','Purok 1 Sampaguita',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-14 16:46:29','2026-10-14 16:46:29','hip00n.15@gmail.com','09127409774',NULL,NULL,NULL,NULL,NULL,'4005','Region IV-A (CALABARZON)',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-14 16:46:29','2026-10','2026-10-14 16:46:29','2026-10',NULL,NULL,NULL,'221b'),(11,'sellerss@ezicart.com',NULL,'$2y$12$LRajhwBxadwkbJgddNOXd.i0KouGES/oxfPSgKdTMlAdoRGN/F9aG','seller','unverified',NULL,NULL,NULL,NULL,NULL,'Jaime',NULL,'Flores',NULL,NULL,NULL,'09055625360',NULL,NULL,'Surigao del Sur','Tagbina','Santa Maria','gedli lang ville',NULL,'tindahannnn','Beauty',NULL,NULL,NULL,NULL,'documents/ids/5XJLBG0Qw8YNdq8bRNsYkfMEB8ryzveSNINZAXB3.jpg',NULL,NULL,NULL,NULL,NULL,'2026-10-14 16:56:46','2026-10-14 16:56:46','sellerss@ezicart.com','09055625360',NULL,'tindahannnn',NULL,'Individual','696-696-696-696','4069','Region XIII (Caraga)',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-14 16:56:46','2026-10','2026-10-14 16:56:46','2026-10',NULL,NULL,NULL,'69'),(12,'ggbynavi454@gmail.com',NULL,'$2y$12$KDI7SK.JDHeP5pedUd2ZrOec/7WRJ8Uylm50c6.mBkdWSfh1sWDzW','seller','approved',NULL,NULL,NULL,NULL,NULL,'jaimee',NULL,'floress',NULL,NULL,NULL,'09284746299',NULL,NULL,'Camiguin','Guinsiliban','Cabuan','sesame st',NULL,'Sarah Essentials Store','Beauty',NULL,NULL,NULL,NULL,'documents/ids/yibjmxWfTGMALMibHl2MFFukKhjcRE9hhjrUu0Ro.jpg',NULL,NULL,NULL,NULL,NULL,'2026-10-14 17:08:47','2026-10-14 17:15:32','ggbynavi454@gmail.com','09284746299',NULL,'Sarah Essentials Store',NULL,'Individual','696-696-696-691','4069','Region X (Northern Mindanao)',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-14 17:08:47','2026-10','2026-10-14 17:08:47','2026-10','2026-10-14 17:15:32',1,NULL,'69'),(13,'operations@bilishatid.example.com',NULL,'$2y$12$zqvDDnX3mdIWrJYTshfSBu0mym8.lGNlPO7q4upiM3oR/EjAfTus.','sorting_center','unverified',NULL,NULL,NULL,NULL,NULL,'Carlos Antonio',NULL,'Mendoza',NULL,NULL,NULL,'09285551234',NULL,NULL,'Laguna','Bay','Calo','blagadum',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'documents/ids/7c38EL74AlSO9AHBcWLSwTw34cMBS5p4IL7FmjlN.jpg','documents/permits/Xqo4F7ZuIN9w0KP1PzcjjGAQhzgpMKoW9UqlxkG1.jpg',NULL,NULL,NULL,NULL,'2026-10-14 17:23:33','2026-10-14 17:23:33','operations@bilishatid.example.com','09285551234',NULL,NULL,'bilis hatid express inc.',NULL,'234-567-890-000','4008','Region IV-A (CALABARZON)','CS202012345','Operations Manager','[\"National Capital Region (NCR)\"]','documents/sec/OTT1jTo93QfxizCFxjlZXFmkSfkE8WvLHLWUYgHR.jpg','documents/bir/JOkJxItejtBhI1CUCJTvBJPXiS4AiT3F9bQybh3l.jpg',NULL,NULL,NULL,NULL,'2026-10-14 17:23:33','2026-10','2026-10-14 17:23:33','2026-10',NULL,NULL,NULL,'911'),(14,'ggbynavi4542@gmail.com',NULL,'$2y$12$hxiWR5MGKiByKUBGSNrkQ.tcc8YmQid7zTC4WkvDQL.5T/nI5HSpm','sorting_center','unverified',NULL,NULL,NULL,NULL,NULL,'ca',NULL,'Maria Cristina Angelica Bernadette Josefina aaaaaa',NULL,NULL,NULL,'09055625368',NULL,NULL,'Laguna','Santa Cruz ','Calios','360',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'documents/ids/3JiyaJFmPpbiRlLt0KjMNRCzRxTt2zT8SnizsXxQ.jpg','documents/permits/oyIdOuRTJdOIjxRiDjNIjrILz69dm6n5ahOAQNCq.jpg',NULL,NULL,NULL,NULL,'2026-10-14 17:38:57','2026-10-14 17:38:57','ggbynavi4542@gmail.com','09055625368',NULL,NULL,'tindahannnn',NULL,'000-000-000-124','4009','Region IV-A (CALABARZON)','CS202012348','Own','[\"National Capital Region (NCR)\", \"Region IV-A (CALABARZON)\"]','documents/sec/vMVJi6OrEz4FSbHaP5VE1V1Si4X9E2JIOWg5Rcq0.jpg','documents/bir/sx0hngAOQwEfF2fT9egJiOzEP9EynTzFrsYVlCse.jpg',NULL,NULL,NULL,NULL,'2026-10-14 17:38:57','2026-10','2026-10-14 17:38:57','2026-10',NULL,NULL,NULL,'619'),(15,'casuarinaequisetifolia20@gmail.com','2026-10-18 07:57:08','$2y$12$0S9Bl3e1gIE5nfQdIlkVJOms6c4MNE4f/ea1io5BrknLhh0C..6wO','buyer','approved',NULL,NULL,NULL,NULL,NULL,'Den',NULL,'Contado',NULL,NULL,NULL,'09127409778','2007-03-14',19,'Laguna','Majayjay','San Isidro','Purok 1 sampaguita',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-18 06:59:46','2026-10-18 07:57:08','casuarinaequisetifolia20@gmail.com','09127409778',NULL,NULL,NULL,NULL,NULL,'4005','Region IV-A (CALABARZON)',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-18 06:59:46','2026-10','2026-10-18 06:59:46','2026-10',NULL,NULL,NULL,'220b');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vouchers`
--

DROP TABLE IF EXISTS `vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vouchers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `seller_id` bigint unsigned NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('fixed','percent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed',
  `value` decimal(10,2) NOT NULL,
  `min_spend` decimal(10,2) NOT NULL DEFAULT '0.00',
  `usage_limit` int DEFAULT NULL,
  `used_count` int NOT NULL DEFAULT '0',
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vouchers_code_unique` (`code`),
  KEY `vouchers_seller_id_foreign` (`seller_id`),
  CONSTRAINT `vouchers_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vouchers`
--

LOCK TABLES `vouchers` WRITE;
/*!40000 ALTER TABLE `vouchers` DISABLE KEYS */;
/*!40000 ALTER TABLE `vouchers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'ezicart_db'
--

--
-- Dumping routines for database 'ezicart_db'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-20  1:32:39
