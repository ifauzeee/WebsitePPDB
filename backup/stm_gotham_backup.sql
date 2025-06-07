-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: stm_gotham
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (2,'admin','$2y$10$1vEp4ReRx21MAtDzTVNCouIjzMWScfVpJSci188vw6d3C7taDtIT6','2025-05-11 18:24:03');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `answers`
--

DROP TABLE IF EXISTS `answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_session_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_answer` enum('A','B','C','D') NOT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `answered_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `exam_session_id` (`exam_session_id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `answers_ibfk_1` FOREIGN KEY (`exam_session_id`) REFERENCES `exam_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `answers`
--

LOCK TABLES `answers` WRITE;
/*!40000 ALTER TABLE `answers` DISABLE KEYS */;
/*!40000 ALTER TABLE `answers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `document_type` enum('Ijazah','Rapor','Pas Foto','Kartu Keluarga') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `document_name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `registration_id` (`registration_id`),
  CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exam_results`
--

DROP TABLE IF EXISTS `exam_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exam_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_session_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_answer` enum('A','B','C','D') NOT NULL,
  `is_correct` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `question_id` (`question_id`),
  KEY `exam_results_ibfk_1` (`exam_session_id`),
  CONSTRAINT `exam_results_ibfk_1` FOREIGN KEY (`exam_session_id`) REFERENCES `exam_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_results_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exam_results`
--

LOCK TABLES `exam_results` WRITE;
/*!40000 ALTER TABLE `exam_results` DISABLE KEYS */;
/*!40000 ALTER TABLE `exam_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exam_sessions`
--

DROP TABLE IF EXISTS `exam_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exam_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `major_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `status` enum('Scheduled','In Progress','Completed','Cancelled') DEFAULT 'Scheduled',
  PRIMARY KEY (`id`),
  KEY `registration_id` (`registration_id`),
  KEY `major_id` (`major_id`),
  CONSTRAINT `exam_sessions_ibfk_1` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_sessions_ibfk_2` FOREIGN KEY (`major_id`) REFERENCES `majors` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exam_sessions`
--

LOCK TABLES `exam_sessions` WRITE;
/*!40000 ALTER TABLE `exam_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `exam_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `majors`
--

DROP TABLE IF EXISTS `majors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `majors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `majors`
--

LOCK TABLES `majors` WRITE;
/*!40000 ALTER TABLE `majors` DISABLE KEYS */;
INSERT INTO `majors` VALUES (1,'Teknik Komputer dan Jaringan'),(2,'Teknik Otomotif'),(3,'Multimedia');
/*!40000 ALTER TABLE `majors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `sender` enum('Student','Admin') NOT NULL,
  `message` text NOT NULL,
  `sent_at` datetime DEFAULT current_timestamp(),
  `subject` varchar(255) NOT NULL DEFAULT 'No Subject',
  `status` enum('unread','read','replied') NOT NULL DEFAULT 'unread',
  `is_read` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `registration_id` (`registration_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `questions`
--

DROP TABLE IF EXISTS `questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `major_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) NOT NULL,
  `option_d` varchar(255) NOT NULL,
  `correct_answer` enum('A','B','C','D') NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `major_id` (`major_id`),
  CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`major_id`) REFERENCES `majors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `questions`
--

LOCK TABLES `questions` WRITE;
/*!40000 ALTER TABLE `questions` DISABLE KEYS */;
INSERT INTO `questions` VALUES (24,1,'Apa kepanjangan dari LAN dalam jaringan komputer?','Local Area Network','Large Area Network','Long Access Network','Limited Access Network','A','2025-05-12 19:14:28'),(25,1,'Protokol yang digunakan untuk mengakses halaman web adalah?','FTP','HTTP','SMTP','POP3','B','2025-05-12 19:14:28'),(26,1,'Alat yang digunakan untuk menghubungkan dua jaringan dengan segmen berbeda adalah?','Switch','Router','Hub','Access Point','B','2025-05-12 19:14:28'),(27,1,'Kabel yang digunakan untuk menghubungkan komputer ke switch dalam topologi star adalah?','Kabel Coaxial','Kabel Fiber Optic','Kabel UTP','Kabel STP','C','2025-05-12 19:14:28'),(28,1,'Berapa jumlah lapisan pada model OSI?','5','6','7','8','C','2025-05-12 19:14:28'),(29,1,'Apa fungsi utama dari IP Address?','Mengidentifikasi perangkat di jaringan','Menyimpan data','Mengenkripsi data','Mengatur bandwidth','A','2025-05-12 19:14:28'),(30,1,'Perintah di Linux untuk melihat daftar file dalam direktori adalah?','cd','ls','rm','mkdir','B','2025-05-12 19:14:28'),(31,1,'Topologi jaringan dimana semua perangkat terhubung ke satu kabel utama disebut?','Star','Ring','Bus','Mesh','C','2025-05-12 19:14:28'),(32,1,'Apa itu DNS dalam jaringan?','Domain Name System','Dynamic Network Service','Data Network System','Distributed Name Server','A','2025-05-12 19:14:28'),(33,1,'Port standar untuk protokol HTTP adalah?','21','80','443','25','B','2025-05-12 19:14:28'),(34,1,'Perangkat yang memperkuat sinyal jaringan agar jaraknya lebih jauh adalah?','Repeater','Switch','Gateway','Modem','A','2025-05-12 19:14:28'),(35,1,'Apa kepanjangan dari TCP dalam TCP/IP?','Transmission Control Protocol','Transfer Control Protocol','Terminal Control Protocol','Transport Communication Protocol','A','2025-05-12 19:14:28'),(36,1,'Kelas IP Address yang memiliki rentang 192.0.0.0 - 223.255.255.255 adalah?','Kelas A','Kelas B','Kelas C','Kelas D','C','2025-05-12 19:14:28'),(37,1,'Alat untuk menguji konektivitas jaringan adalah?','Ping','Telnet','Traceroute','Netstat','A','2025-05-12 19:14:28'),(38,1,'Apa itu subnet mask?','Alamat gateway','Pembagi jaringan menjadi subnet','Kode keamanan jaringan','Penentu kecepatan jaringan','B','2025-05-12 19:14:28'),(39,1,'Protokol untuk mengirim email adalah?','IMAP','POP3','SMTP','FTP','C','2025-05-12 19:14:28'),(40,1,'Perangkat lunak untuk mengelola jaringan berbasis Cisco adalah?','Wireshark','Packet Tracer','Putty','Nmap','B','2025-05-12 19:14:28'),(41,1,'Apa fungsi switch dalam jaringan?','Menghubungkan perangkat dalam LAN','Mengatur lalu lintas internet','Mengkonversi sinyal analog ke digital','Mengenkripsi data','A','2025-05-12 19:14:28'),(42,1,'Kabel UTP dengan standar T568-B memiliki urutan warna untuk pin 1 adalah?','Putih-Oranye','Putih-Hijau','Oranye','Hijau','A','2025-05-12 19:14:28'),(43,1,'Apa itu bandwidth dalam jaringan?','Kecepatan transfer data','Jumlah perangkat di jaringan','Kapasitas penyimpanan','Keamanan jaringan','A','2025-05-12 19:14:28'),(44,2,'Apa fungsi utama sistem pelumasan pada mesin kendaraan?','Mendinginkan mesin','Mengurangi gesekan antar komponen','Meningkatkan tenaga mesin','Membersihkan karbon','B','2025-05-12 19:18:16'),(45,2,'Komponen yang mengubah gerakan linier piston menjadi gerakan putar adalah?','Camshaft','Crankshaft','Timing Belt','Piston Ring','B','2025-05-12 19:18:16'),(46,2,'Apa kepanjangan dari ABS pada sistem pengereman?','Anti-lock Braking System','Automatic Brake System','Advanced Braking Solution','Adaptive Brake Support','A','2025-05-12 19:18:16'),(47,2,'Busi pada mesin bensin berfungsi untuk?','Menyimpan bahan bakar','Menyalakan campuran udara dan bahan bakar','Mengatur suhu mesin','Menyaring udara','B','2025-05-12 19:18:16'),(48,2,'Apa yang dimaksud dengan overheat pada mesin kendaraan?','Mesin terlalu dingin','Mesin terlalu panas','Mesin kehilangan oli','Mesin berhenti mendadak','B','2025-05-12 19:18:16'),(49,2,'Komponen yang mengatur aliran udara masuk ke mesin adalah?','Throttle Valve','Fuel Injector','Air Filter','Exhaust Valve','A','2025-05-12 19:18:16'),(50,2,'Jenis suspensi yang sering digunakan pada mobil penumpang adalah?','Leaf Spring','Coil Spring','Torsion Bar','Air Suspension','B','2025-05-12 19:18:16'),(51,2,'Apa fungsi kopling pada kendaraan bermotor?','Menghubungkan dan memutuskan tenaga mesin ke transmisi','Meningkatkan kecepatan kendaraan','Mengatur suhu mesin','Menyimpan energi','A','2025-05-12 19:18:16'),(52,2,'Oli mesin perlu diganti secara rutin untuk?','Meningkatkan tenaga mesin','Menjaga kebersihan dan pelumasan mesin','Mengurangi konsumsi bahan bakar','Memperpanjang umur ban','B','2025-05-12 19:18:16'),(53,2,'Apa itu timing belt pada mesin kendaraan?','Sabuk penggerak roda','Sabuk pengatur waktu buka-tutup katup','Sabuk penyeimbang mesin','Sabuk transmisi','B','2025-05-12 19:18:16'),(54,2,'Sistem yang mengubah energi mekanis menjadi energi listrik adalah?','Alternator','Starter Motor','Battery','Ignition Coil','A','2025-05-12 19:18:16'),(55,2,'Apa fungsi radiator pada kendaraan?','Mendinginkan oli mesin','Mendinginkan udara masuk','Mendinginkan air pendingin mesin','Menyimpan bahan bakar','C','2025-05-12 19:18:16'),(56,2,'Jenis bahan bakar yang digunakan pada mesin diesel adalah?','Bensin','Solar','Avtur','LPG','B','2025-05-12 19:18:16'),(57,2,'Apa yang dimaksud dengan tune-up kendaraan?','Penggantian mesin','Perawatan rutin untuk optimalisasi performa','Pemasangan aksesori','Perbaikan bodi','B','2025-05-12 19:18:16'),(58,2,'Komponen yang mengatur tekanan bahan bakar ke mesin adalah?','Fuel Pump','Fuel Pressure Regulator','Carburetor','Fuel Tank','B','2025-05-12 19:18:16'),(59,2,'Apa fungsi kampas rem pada sistem pengereman?','Menghasilkan panas','Menghentikan putaran roda','Meningkatkan kecepatan','Menjaga keseimbangan','B','2025-05-12 19:18:16'),(60,2,'Sistem penggerak roda depan disebut?','Rear-Wheel Drive','Front-Wheel Drive','All-Wheel Drive','Four-Wheel Drive','B','2025-05-12 19:18:16'),(61,2,'Apa itu catalytic converter pada kendaraan?','Alat untuk meningkatkan tenaga','Alat untuk mengurangi emisi gas buang','Alat untuk menyaring oli','Alat untuk mendinginkan mesin','B','2025-05-12 19:18:16'),(62,2,'Alat yang digunakan untuk mengukur tekanan ban adalah?','Manometer','Tachometer','Pressure Gauge','Voltmeter','C','2025-05-12 19:18:16'),(63,2,'Apa fungsi differential pada kendaraan?','Mengatur kecepatan mesin','Membagi tenaga ke roda saat berbelok','Menyimpan bahan bakar','Meningkatkan traksi ban','B','2025-05-12 19:18:16'),(64,3,'Aplikasi yang sering digunakan untuk mengedit foto adalah?','Adobe Premiere','Adobe Photoshop','Adobe After Effects','Audacity','B','2025-05-12 19:18:40'),(65,3,'Format file yang mendukung transparansi adalah?','JPEG','PNG','BMP','GIF','B','2025-05-12 19:18:40'),(66,3,'Apa fungsi layer pada perangkat lunak desain grafis?','Menyimpan file','Mengatur elemen desain secara terpisah','Mengubah warna gambar','Memperbesar gambar','B','2025-05-12 19:18:40'),(67,3,'Resolusi gambar diukur dalam?','Inch','Pixel','Centimeter','Byte','B','2025-05-12 19:18:40'),(68,3,'Warna yang dihasilkan dari kombinasi Red, Green, dan Blue disebut?','CMYK','RGB','Grayscale','Pantone','B','2025-05-12 19:18:40'),(69,3,'Aplikasi yang digunakan untuk mengedit video adalah?','CorelDRAW','Adobe Premiere Pro','Blender','GIMP','B','2025-05-12 19:18:40'),(70,3,'Apa itu frame rate dalam produksi video?','Jumlah frame per detik','Ukuran layar video','Kualitas warna video','Durasi video','A','2025-05-12 19:18:40'),(71,3,'Format audio yang tidak terkompresi adalah?','MP3','WAV','AAC','OGG','B','2025-05-12 19:18:40'),(72,3,'Alat yang digunakan untuk menggambar digital adalah?','Mouse','Graphic Tablet','Keyboard','Joystick','B','2025-05-12 19:18:40'),(73,3,'Apa fungsi keyframe dalam animasi?','Mengatur durasi video','Menentukan posisi awal dan akhir animasi','Mengubah warna objek','Menyimpan animasi','B','2025-05-12 19:18:40'),(74,3,'Perangkat lunak untuk membuat animasi 3D adalah?','Adobe Illustrator','Blender','Canva','Figma','B','2025-05-12 19:18:40'),(75,3,'Apa itu storyboard dalam produksi multimedia?','Skrip dialog','Rancangan visual alur cerita','Daftar efek suara','Jadwal produksi','B','2025-05-12 19:18:40'),(76,3,'Warna primer dalam model CMYK adalah?','Red, Green, Blue','Cyan, Magenta, Yellow','Black, White, Gray','Orange, Purple, Green','B','2025-05-12 19:18:40'),(77,3,'Apa fungsi cropping dalam pengeditan gambar?','Mengubah warna','Memotong bagian gambar','Menambahkan efek','Meningkatkan resolusi','B','2025-05-12 19:18:40'),(78,3,'Format video yang umum digunakan untuk streaming adalah?','AVI','MP4','WMV','MOV','B','2025-05-12 19:18:40'),(79,3,'Apa itu bitrate dalam audio atau video?','Kecepatan pemutaran','Kualitas data per detik','Ukuran file','Durasi file','B','2025-05-12 19:18:40'),(80,3,'Perangkat lunak untuk mengedit suara adalah?','Adobe Animate','Audacity','SketchUp','Lightroom','B','2025-05-12 19:18:40'),(81,3,'Apa yang dimaksud dengan rendering dalam multimedia?','Membuat desain awal','Mengubah proyek menjadi file final','Mengedit warna','Menyimpan file sementara','B','2025-05-12 19:18:40'),(82,3,'Jenis font yang memiliki ujung dekoratif adalah?','Sans Serif','Serif','Script','Monospace','B','2025-05-12 19:18:40'),(83,3,'Apa fungsi green screen dalam produksi video?','Meningkatkan kualitas warna','Memungkinkan penggantian latar belakang','Mengurangi noise','Mempercepat rendering','B','2025-05-12 19:18:40');
/*!40000 ALTER TABLE `questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `registrations`
--

DROP TABLE IF EXISTS `registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `nisn` char(10) NOT NULL,
  `status` enum('Menunggu Verifikasi','Terverifikasi','Diterima','Ditolak') DEFAULT 'Menunggu Verifikasi',
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `exam_permission` tinyint(1) DEFAULT 0,
  `major_id` int(11) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `program` varchar(100) DEFAULT NULL,
  `gender` enum('Laki-laki','Perempuan') DEFAULT NULL,
  `birth_place` varchar(100) DEFAULT NULL,
  `nik` char(16) DEFAULT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `previous_school` varchar(255) DEFAULT NULL,
  `graduation_year` year(4) DEFAULT NULL,
  `father_name` varchar(255) DEFAULT NULL,
  `father_occupation` varchar(100) DEFAULT NULL,
  `father_phone` varchar(15) DEFAULT NULL,
  `mother_name` varchar(255) DEFAULT NULL,
  `mother_occupation` varchar(100) DEFAULT NULL,
  `mother_phone` varchar(15) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `major_id` (`major_id`),
  CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `registrations_ibfk_2` FOREIGN KEY (`major_id`) REFERENCES `majors` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `registrations`
--

LOCK TABLES `registrations` WRITE;
/*!40000 ALTER TABLE `registrations` DISABLE KEYS */;
INSERT INTO `registrations` VALUES (10,12,'ifauze343@gmail.com','IBNU FAUZI','1234567890','Menunggu Verifikasi',NULL,'2025-05-12 22:39:07',0,1,'085156051343','2004-09-12','PURI CILEUNGSI','Teknik Komputer dan Jaringan','Laki-laki','BOGOR','1293812408124098','Islam','NEPAT',2020,'FAUZI ANWAR','KARYAWAN SWASTA','081290759945','INDAWATI','IBU RUMAH TANGGA','085156051343','CILEUNGSI','BOGOR','JAWA BARAT','16820',NULL);
/*!40000 ALTER TABLE `registrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `status_history`
--

DROP TABLE IF EXISTS `status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `status_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `status` enum('Menunggu Verifikasi','Terverifikasi','Diterima','Ditolak') NOT NULL,
  `changed_at` datetime DEFAULT current_timestamp(),
  `changed_by` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `registration_id` (`registration_id`),
  CONSTRAINT `status_history_ibfk_1` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `status_history`
--

LOCK TABLES `status_history` WRITE;
/*!40000 ALTER TABLE `status_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `status_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (12,'ifauze343@gmail.com','$2y$10$kRNEwzW0olEtsikyeV4G2.KOl/a0x6FzwhHpZjxDzUep26kWocNp.','2025-05-12 22:39:07');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-05-13 12:36:19
