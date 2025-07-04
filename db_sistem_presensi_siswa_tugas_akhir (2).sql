-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 04, 2025 at 03:55 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_sistem_presensi_siswa_tugas_akhir`
--

-- --------------------------------------------------------

--
-- Table structure for table `guru`
--

CREATE TABLE `guru` (
  `id` int NOT NULL,
  `pengguna_id` int DEFAULT NULL,
  `nip` varchar(50) DEFAULT NULL,
  `jenis_kelamin` enum('L','P') DEFAULT NULL,
  `telepon` varchar(50) DEFAULT NULL,
  `alamat` varchar(255) DEFAULT NULL,
  `status` enum('Aktif','Nonaktif') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'Aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `guru`
--

INSERT INTO `guru` (`id`, `pengguna_id`, `nip`, `jenis_kelamin`, `telepon`, `alamat`, `status`) VALUES
(2, 2, '1234', 'L', '0812345', 'KP', 'Aktif');

-- --------------------------------------------------------

--
-- Table structure for table `hari_libur`
--

CREATE TABLE `hari_libur` (
  `id` int NOT NULL,
  `tanggal_mulai` date DEFAULT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `keterangan` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `hari_libur`
--

INSERT INTO `hari_libur` (`id`, `tanggal_mulai`, `tanggal_selesai`, `keterangan`) VALUES
(1, '2025-06-01', '2025-06-01', 'Hari Lahir Pancasila'),
(2, '2025-06-09', '2025-06-09', 'Hari Raya Idul Adha');

-- --------------------------------------------------------

--
-- Table structure for table `history_rfid`
--

CREATE TABLE `history_rfid` (
  `id` int NOT NULL,
  `no_rfid` varchar(50) DEFAULT NULL,
  `waktu` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `history_rfid`
--

INSERT INTO `history_rfid` (`id`, `no_rfid`, `waktu`) VALUES
(1, '9F46311F', '2025-06-20 21:33:49'),
(2, '9F46311F', '2025-06-21 19:31:09'),
(3, '9F46311F', '2025-06-21 19:31:14'),
(4, '9F46311F', '2025-06-21 19:31:19'),
(5, '9F46311F', '2025-06-21 19:31:31'),
(6, '9F46311F', '2025-06-21 19:31:36'),
(7, '9F46311F', '2025-06-21 19:31:43'),
(8, '9F46311F', '2025-06-21 19:31:49'),
(9, '9F46311F', '2025-06-21 19:31:59'),
(10, '9F46311F', '2025-06-21 19:35:55'),
(11, '9F46311F', '2025-06-21 19:36:31'),
(12, '9F46311F', '2025-06-21 19:37:28'),
(13, '9F46311F', '2025-06-21 19:37:57'),
(14, '9F46311F', '2025-06-21 19:40:34'),
(15, 'C3BADF26', '2025-06-21 19:40:49'),
(16, '9F46311F', '2025-06-21 19:53:49'),
(17, 'C3BADF26', '2025-06-21 19:53:54'),
(18, '9F46311F', '2025-06-21 19:58:22'),
(19, '9F46311F', '2025-06-21 20:00:38'),
(20, '9F46311F', '2025-06-21 20:01:17'),
(21, '9F46311F', '2025-06-21 20:02:23'),
(22, '9F46311F', '2025-06-21 20:13:26'),
(23, '9F46311F', '2025-06-21 20:28:24'),
(24, '9F46311F', '2025-06-21 20:28:46'),
(25, '9F46311F', '2025-06-21 20:37:04'),
(26, '9F46311F', '2025-06-21 20:37:44'),
(27, '6D942C21', '2025-06-21 20:58:59'),
(28, '7D3A921', '2025-06-21 20:59:11'),
(29, '9F3F6C1F', '2025-06-21 20:59:18'),
(30, '6DA7A921', '2025-06-21 20:59:24'),
(31, '9F17111F', '2025-06-21 20:59:29'),
(32, '6D5F6B21', '2025-06-21 20:59:36'),
(33, 'CFF3CB1C', '2025-06-21 20:59:41'),
(34, 'CFDB101C', '2025-06-21 20:59:47'),
(35, 'C3BADF26', '2025-06-21 21:03:47'),
(36, 'C3BADF26', '2025-06-21 21:04:37'),
(37, 'C3BADF26', '2025-06-21 21:05:31'),
(38, 'C3BADF26', '2025-06-21 21:06:39'),
(39, '9F46311F', '2025-06-21 21:06:46'),
(40, 'C3BADF26', '2025-06-21 21:07:05'),
(41, 'C3BADF26', '2025-06-21 21:07:36'),
(42, '9F46311F', '2025-06-21 21:08:01'),
(43, 'C3BADF26', '2025-06-21 21:08:48'),
(44, '9F46311F', '2025-06-21 21:52:00'),
(45, 'C3BADF26', '2025-06-28 16:11:29'),
(46, 'C3BADF26', '2025-06-28 16:13:01'),
(47, 'C3BADF26', '2025-06-28 16:13:26'),
(48, 'C3BADF26', '2025-06-28 16:20:48'),
(49, 'C3BADF26', '2025-06-28 16:21:13'),
(50, 'C3BADF26', '2025-06-27 16:22:17'),
(51, 'C3BADF26', '2025-06-27 16:22:55'),
(52, 'C3BADF26', '2025-06-27 16:26:11'),
(53, 'C3BADF26', '2025-06-27 16:26:34'),
(54, 'C3BADF26', '2025-06-26 16:27:36'),
(55, 'C3BADF26', '2025-06-26 16:27:52'),
(56, '9F46311F', '2025-06-26 16:28:42'),
(57, '9F46311F', '2025-06-26 16:29:12'),
(58, 'C3BADF26', '2025-06-25 16:29:37'),
(59, 'C3BADF26', '2025-06-25 16:30:01'),
(60, 'C3BADF26', '2025-06-25 16:30:16'),
(61, 'C3BADF26', '2025-06-24 16:30:29'),
(62, 'C3BADF26', '2025-06-24 16:30:44'),
(63, '9F46311F', '2025-06-24 16:39:20'),
(64, 'CFDB101C', '2025-06-24 16:39:26'),
(65, 'CFDB101C', '2025-06-24 16:43:06'),
(66, 'CFDB101C', '2025-06-24 16:43:19'),
(67, 'CFDB101C', '2025-06-24 16:47:27'),
(68, 'CFDB101C', '2025-06-24 16:47:44'),
(69, 'CFDB101C', '2025-06-23 16:48:06'),
(70, 'CFDB101C', '2025-06-23 16:48:47'),
(71, 'CFDB101C', '2025-06-23 16:49:31'),
(72, 'CFDB101C', '2025-06-23 16:49:59'),
(73, 'CFDB101C', '2025-06-23 16:50:39'),
(74, 'CFDB101C', '2025-06-23 17:04:56'),
(75, 'CFDB101C', '2025-06-23 17:06:09'),
(76, 'CFDB101C', '2025-06-22 17:06:19'),
(77, 'CFDB101C', '2025-06-21 17:06:33'),
(78, 'CFDB101C', '2025-06-21 17:07:00'),
(79, 'CFDB101C', '2025-06-21 17:07:27'),
(80, 'CFDB101C', '2025-06-25 17:08:03'),
(81, 'CFDB101C', '2025-06-25 17:09:18'),
(82, 'CFDB101C', '2025-06-25 17:09:37'),
(83, 'CFDB101C', '2025-06-25 17:11:07'),
(84, 'CFDB101C', '2025-06-24 17:12:33'),
(85, 'CFDB101C', '2025-06-26 17:16:29'),
(86, 'CFDB101C', '2025-06-28 17:45:56'),
(87, 'CFDB101C', '2025-06-28 17:46:11'),
(88, '9F46311F', '2025-06-29 17:08:56'),
(89, '9F46311F', '2025-06-29 17:13:21'),
(90, '9F46311F', '2025-06-29 17:13:46'),
(91, '9F46311F', '2025-06-29 17:14:59'),
(92, 'CFDB101C', '2025-06-29 17:17:04'),
(93, 'CFDB101C', '2025-06-29 17:17:37'),
(94, 'C3BADF26', '2025-06-29 17:19:03'),
(95, 'C3BADF26', '2025-06-29 17:19:32'),
(96, 'C3BADF26', '2025-06-29 17:20:07'),
(97, '9F46311F', '2025-06-29 17:20:49'),
(98, 'C3BADF26', '2025-06-29 17:20:55'),
(99, 'C3BADF26', '2025-06-29 17:21:56'),
(100, 'C3BADF26', '2025-06-29 17:22:25'),
(101, 'CFDB101C', '2025-06-29 17:22:55'),
(102, '9F46311F', '2025-06-29 17:24:18'),
(103, '9F46311F', '2025-06-29 17:24:24'),
(104, 'C3BADF26', '2025-06-29 17:24:35'),
(105, 'CFDB101C', '2025-06-29 17:24:40'),
(106, 'CFDB101C', '2025-06-29 17:25:07'),
(107, '9F46311F', '2025-06-29 17:25:36'),
(108, '9F46311F', '2025-06-29 17:28:00'),
(109, '9F46311F', '2025-06-29 17:29:31'),
(110, '9F46311F', '2025-06-29 17:30:02'),
(111, '9F46311F', '2025-06-29 17:30:35'),
(112, '9F46311F', '2025-06-29 17:38:30'),
(113, '9F46311F', '2025-06-29 17:41:47'),
(114, '9F46311F', '2025-06-29 17:43:18'),
(115, '9F46311F', '2025-06-29 17:44:10'),
(116, 'C3BADF26', '2025-06-30 09:43:33'),
(117, '9F46311F', '2025-06-30 09:50:17'),
(118, '9F46311F', '2025-06-30 09:52:47'),
(119, 'C3BADF26', '2025-06-30 09:53:33'),
(120, '9F46311F', '2025-06-30 09:55:06'),
(121, '9F46311F', '2025-06-30 09:56:16'),
(122, '9F46311F', '2025-06-30 09:58:26'),
(123, 'C3BADF26', '2025-06-30 09:58:43'),
(124, 'C3BADF26', '2025-06-30 09:59:11'),
(125, '9F46311F', '2025-07-01 07:42:00'),
(126, '9F46311F', '2025-07-01 07:48:12'),
(127, '9F46311F', '2025-07-01 07:48:43'),
(128, '9F46311F', '2025-07-01 07:49:14'),
(129, '9F46311F', '2025-07-01 07:49:54'),
(130, '9F46311F', '2025-07-01 07:50:23'),
(131, '9F46311F', '2025-07-01 07:59:05'),
(132, '9F46311F', '2025-07-01 08:01:58'),
(133, '9F46311F', '2025-07-01 08:02:54'),
(134, '9F46311F', '2025-07-01 08:05:08'),
(135, '9F46311F', '2025-07-01 08:09:22'),
(136, '9F46311F', '2025-07-01 08:11:15'),
(137, '9F46311F', '2025-07-01 08:28:03'),
(138, '9F46311F', '2025-07-01 08:51:04'),
(139, '9F46311F', '2025-07-01 09:07:52'),
(140, '9F46311F', '2025-07-01 09:24:42'),
(141, '9F46311F', '2025-07-01 09:41:30'),
(142, '9F46311F', '2025-07-01 12:59:04'),
(143, '9F46311F', '2025-07-01 13:15:52'),
(144, '8FAE221F', '2025-07-01 16:10:03'),
(145, 'CFF3CB1C', '2025-07-01 16:10:25'),
(146, 'CFDB101C', '2025-07-01 16:12:41'),
(147, '9F46311F', '2025-07-01 16:18:29'),
(148, '9F46311F', '2025-07-01 16:20:06'),
(149, 'CFDB101C', '2025-07-01 16:24:35'),
(150, '6D5F6B21', '2025-07-01 16:29:39'),
(151, '6DA7A921', '2025-07-01 16:32:06'),
(152, '9F3F6C1F', '2025-07-01 16:34:00'),
(153, '8FAE221F', '2025-07-01 16:39:33'),
(154, '6D942C21', '2025-07-01 16:42:41'),
(155, '7D3A921', '2025-07-01 16:44:51'),
(156, '8FAE221F', '2025-07-01 16:45:04'),
(157, '8FAE221F', '2025-07-01 16:45:08'),
(158, 'CFF3CB1C', '2025-07-01 16:45:58'),
(159, '7D3A921', '2025-07-01 16:47:42'),
(160, '7D3A921', '2025-07-01 16:50:56'),
(161, 'C3BADF26', '2025-07-01 16:51:04'),
(162, '9F46311F', '2025-07-01 18:29:20'),
(163, 'CFDB101C', '2025-07-01 18:35:32'),
(164, '6D5F6B21', '2025-07-01 18:37:29'),
(165, '6D5F6B21', '2025-07-01 18:40:00'),
(166, '6DA7A921', '2025-07-01 18:41:22'),
(167, '6DA7A921', '2025-07-01 18:45:20'),
(168, '9F3F6C1F', '2025-07-01 18:46:44'),
(169, '9F3F6C1F', '2025-07-01 18:49:24'),
(170, '8FAE221F', '2025-07-01 18:54:00'),
(171, '8FAE221F', '2025-07-01 18:58:49'),
(172, '6D942C21', '2025-07-01 19:00:19'),
(173, '6D942C21', '2025-07-01 19:01:40'),
(174, 'CFF3CB1C', '2025-07-01 19:02:50'),
(175, 'CFF3CB1C', '2025-07-01 19:05:19'),
(176, '7D3A921', '2025-07-01 19:08:08'),
(177, '7D3A921', '2025-07-01 19:09:17'),
(178, 'C3BADF26', '2025-07-01 19:11:43'),
(179, 'CFF3CB1C', '2025-07-01 19:18:26'),
(180, '7D3A921', '2025-07-01 19:19:44'),
(181, 'C3BADF26', '2025-07-01 19:20:50'),
(182, '9F17111F', '2025-07-03 14:31:55'),
(183, '6DA7A921', '2025-07-03 14:32:01'),
(184, '6DA7A921', '2025-07-03 14:33:17'),
(185, '6D942C21', '2025-07-03 14:35:11'),
(186, '9F46311F', '2025-07-03 14:38:09'),
(187, '9F46311F', '2025-07-03 14:39:38'),
(188, '9F46311F', '2025-07-03 14:40:14'),
(189, '9F46311F', '2025-07-03 14:54:57'),
(190, 'CFDB101C', '2025-07-03 14:55:23'),
(191, 'CFDB101C', '2025-07-03 14:56:35'),
(192, 'CFDB101C', '2025-07-03 14:58:44'),
(193, 'CFDB101C', '2025-07-03 15:00:24'),
(194, 'CFF3CB1C', '2025-07-03 15:05:00'),
(195, 'CFF3CB1C', '2025-07-03 15:06:13'),
(196, 'CFF3CB1C', '2025-07-03 15:07:41'),
(197, 'CFF3CB1C', '2025-07-03 15:24:56'),
(198, 'CFF3CB1C', '2025-07-03 15:25:08'),
(199, 'CFF3CB1C', '2025-07-03 15:35:48'),
(200, 'CFF3CB1C', '2025-07-03 15:43:41'),
(201, 'CFF3CB1C', '2025-07-03 15:43:47'),
(202, 'C3BADF26', '2025-07-03 15:49:26');

-- --------------------------------------------------------

--
-- Table structure for table `kelas`
--

CREATE TABLE `kelas` (
  `id` int NOT NULL,
  `nama_kelas` varchar(50) DEFAULT NULL,
  `guru_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `kelas`
--

INSERT INTO `kelas` (`id`, `nama_kelas`, `guru_id`) VALUES
(1, '1', 2),
(2, '2', 2),
(3, '3', 2),
(4, '4', 2),
(5, '5', 2),
(6, '6', 2);

-- --------------------------------------------------------

--
-- Table structure for table `pengaturan`
--

CREATE TABLE `pengaturan` (
  `id` int NOT NULL,
  `jam_masuk` time DEFAULT NULL,
  `hari_operasional` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pengaturan`
--

INSERT INTO `pengaturan` (`id`, `jam_masuk`, `hari_operasional`) VALUES
(1, '07:00:00', '1,2,3,4,5,6');

-- --------------------------------------------------------

--
-- Table structure for table `pengguna`
--

CREATE TABLE `pengguna` (
  `id` int NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `level` enum('Admin','Guru') DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL,
  `status` enum('Aktif','Nonaktif') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'Aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pengguna`
--

INSERT INTO `pengguna` (`id`, `email`, `password`, `nama`, `level`, `foto_profil`, `status`) VALUES
(1, 'admin@gmail.com', '$2y$10$KI2ecejjMlu3ycGZv3IQVOHFqwSv4TtAYoKefn0WfM/D/BcE4gqUC', 'Admin', 'Admin', 'Admin_20250620212009.jpeg', 'Aktif'),
(2, 'joko@gmail.com', '$2y$10$mbpDxgEtPjaTxAvk/e4TTOxkPPihOLBcogi78HUyntf/0MK1yF0uK', 'Joko', 'Guru', 'Joko_20250621192914.png', 'Aktif');

-- --------------------------------------------------------

--
-- Table structure for table `presensi`
--

CREATE TABLE `presensi` (
  `id` int NOT NULL,
  `siswa_id` int DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `waktu_masuk` time DEFAULT NULL,
  `foto_masuk` varchar(255) DEFAULT NULL,
  `waktu_keluar` time DEFAULT NULL,
  `foto_keluar` varchar(255) DEFAULT NULL,
  `status` enum('Masuk','Hadir','Izin','Sakit','Alpa') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `presensi`
--

INSERT INTO `presensi` (`id`, `siswa_id`, `tanggal`, `waktu_masuk`, `foto_masuk`, `waktu_keluar`, `foto_keluar`, `status`) VALUES
(23, 9, '2025-07-03', '14:39:42', '1_masuk_20250703_143942.jpg', '14:40:15', '1_keluar_20250703_144015.jpg', 'Hadir'),
(25, 16, '2025-07-03', '15:07:44', '8_masuk_20250703_150744.jpg', NULL, NULL, 'Masuk');

-- --------------------------------------------------------

--
-- Table structure for table `siswa`
--

CREATE TABLE `siswa` (
  `id` int NOT NULL,
  `nis` varchar(50) DEFAULT NULL,
  `no_rfid` varchar(50) DEFAULT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `jenis_kelamin` enum('L','P') DEFAULT NULL,
  `alamat` varchar(255) DEFAULT NULL,
  `kelas_id` int DEFAULT NULL,
  `token` varchar(100) DEFAULT NULL,
  `id_chat` varchar(50) DEFAULT NULL,
  `foto_siswa` varchar(255) DEFAULT NULL,
  `dataset_wajah` varchar(255) DEFAULT NULL,
  `status` enum('Aktif','Nonaktif') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'Aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `siswa`
--

INSERT INTO `siswa` (`id`, `nis`, `no_rfid`, `nama`, `jenis_kelamin`, `alamat`, `kelas_id`, `token`, `id_chat`, `foto_siswa`, `dataset_wajah`, `status`) VALUES
(9, '1', '9F46311F', 'Agustina', 'P', 'YK', 5, '7604901750:AAFsDHBCLB2dAUVTOc6WaeXVZQR5zNyfrE8', '1400316789', '1_Agustina_9F46311F.webp', '1_Agustina_9F46311F', 'Aktif'),
(10, '2', 'CFDB101C', 'Aldian', 'L', 'YK', 5, '7604901750:AAFsDHBCLB2dAUVTOc6WaeXVZQR5zNyfrE8', '1400316789', '2_Aldian_CFDB101C.jpg', '2_Aldian_CFDB101C', 'Aktif'),
(11, '3', '6D5F6B21', 'Asyfa', 'P', 'YK', 5, '7604901750:AAFsDHBCLB2dAUVTOc6WaeXVZQR5zNyfrE8', '1400316789', '3_Asyfa_6D5F6B21.png', '3_Asyfa_6D5F6B21', 'Aktif'),
(12, '4', '6DA7A921', 'Aulia', 'P', 'YK', 5, '7604901750:AAFsDHBCLB2dAUVTOc6WaeXVZQR5zNyfrE8', '1400316789', '4_Aulia_6DA7A921.jpg', '4_Aulia_6DA7A921', 'Aktif'),
(13, '5', '9F3F6C1F', 'Sohibal', 'L', 'YK', 5, '7604901750:AAFsDHBCLB2dAUVTOc6WaeXVZQR5zNyfrE8', '1400316789', '5_Sohibal_9F3F6C1F.png', '5_Sohibal_9F3F6C1F', 'Aktif'),
(14, '6', '8FAE221F', 'Naufal', 'L', 'YK', 5, '7604901750:AAFsDHBCLB2dAUVTOc6WaeXVZQR5zNyfrE8', '1400316789', '6_Naufal_8FAE221F.jpeg', '6_Naufal_8FAE221F', 'Aktif'),
(15, '7', '6D942C21', 'Fatur', 'L', 'YK', 5, '7604901750:AAFsDHBCLB2dAUVTOc6WaeXVZQR5zNyfrE8', '1400316789', '7_Fatur_6D942C21.jpeg', '7_Fatur_6D942C21', 'Aktif'),
(16, '8', 'CFF3CB1C', 'David Ardianto', 'L', 'YK', 5, '7604901750:AAFsDHBCLB2dAUVTOc6WaeXVZQR5zNyfrE8', '1400316789', '8_David Ardianto_CFF3CB1C.jpg', '8_David Ardianto_CFF3CB1C', 'Aktif'),
(17, '9', '7D3A921', 'Supriyati', 'P', 'YK', 5, '7604901750:AAFsDHBCLB2dAUVTOc6WaeXVZQR5zNyfrE8', '1400316789', '9_Supriyati_7D3A921.jpeg', '9_Supriyati_7D3A921', 'Aktif'),
(18, '10', 'C3BADF26', 'Tukimin', 'L', 'YK', 5, '7604901750:AAFsDHBCLB2dAUVTOc6WaeXVZQR5zNyfrE8', '1400316789', '10_Tukimin_C3BADF26.webp', '10_Tukimin_C3BADF26', 'Aktif');

-- --------------------------------------------------------

--
-- Table structure for table `tmp_rfid_presensi`
--

CREATE TABLE `tmp_rfid_presensi` (
  `id` int NOT NULL,
  `no_rfid` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tmp_rfid_presensi`
--

INSERT INTO `tmp_rfid_presensi` (`id`, `no_rfid`) VALUES
(1, 'C3BADF26');

-- --------------------------------------------------------

--
-- Table structure for table `tmp_rfid_tambah`
--

CREATE TABLE `tmp_rfid_tambah` (
  `id` int NOT NULL,
  `no_rfid` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `guru`
--
ALTER TABLE `guru`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nip` (`nip`),
  ADD KEY `fk_guru_pengguna` (`pengguna_id`);

--
-- Indexes for table `hari_libur`
--
ALTER TABLE `hari_libur`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `history_rfid`
--
ALTER TABLE `history_rfid`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kelas`
--
ALTER TABLE `kelas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_kelas` (`nama_kelas`),
  ADD KEY `fk_kelas_guru` (`guru_id`);

--
-- Indexes for table `pengaturan`
--
ALTER TABLE `pengaturan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pengguna`
--
ALTER TABLE `pengguna`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `presensi`
--
ALTER TABLE `presensi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_presensi_siswa` (`siswa_id`);

--
-- Indexes for table `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nis` (`nis`),
  ADD UNIQUE KEY `no_rfid` (`no_rfid`),
  ADD KEY `fk_siswa_kelas` (`kelas_id`);

--
-- Indexes for table `tmp_rfid_presensi`
--
ALTER TABLE `tmp_rfid_presensi`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tmp_rfid_tambah`
--
ALTER TABLE `tmp_rfid_tambah`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `guru`
--
ALTER TABLE `guru`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `hari_libur`
--
ALTER TABLE `hari_libur`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `history_rfid`
--
ALTER TABLE `history_rfid`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=203;

--
-- AUTO_INCREMENT for table `kelas`
--
ALTER TABLE `kelas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `pengaturan`
--
ALTER TABLE `pengaturan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pengguna`
--
ALTER TABLE `pengguna`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `presensi`
--
ALTER TABLE `presensi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `tmp_rfid_presensi`
--
ALTER TABLE `tmp_rfid_presensi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tmp_rfid_tambah`
--
ALTER TABLE `tmp_rfid_tambah`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `guru`
--
ALTER TABLE `guru`
  ADD CONSTRAINT `fk_guru_pengguna` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `kelas`
--
ALTER TABLE `kelas`
  ADD CONSTRAINT `fk_kelas_guru` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `presensi`
--
ALTER TABLE `presensi`
  ADD CONSTRAINT `fk_presensi_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `siswa`
--
ALTER TABLE `siswa`
  ADD CONSTRAINT `fk_siswa_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
