-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3308
-- Generation Time: Sep 18, 2026 at 10:08 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `Gary`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`) VALUES
(1, 'admin', '$2y$10$ImNlAHZjtv6HaoRq03oIUeqfg0xTmXs.Ukt5f7QNR8FLLvZWIYe9u');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `accuracy` float DEFAULT NULL,
  `time_in` datetime NOT NULL,
  `time_out` datetime DEFAULT NULL,
  `out_latitude` decimal(10,8) DEFAULT NULL,
  `out_longitude` decimal(11,8) DEFAULT NULL,
  `out_accuracy` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `user_id`, `event_id`, `attendance_date`, `latitude`, `longitude`, `accuracy`, `time_in`, `time_out`, `out_latitude`, `out_longitude`, `out_accuracy`) VALUES
(4, 2, 9, '2026-09-14', 18.06089973, 121.59757232, 69.6667, '2026-09-14 11:39:35', NULL, NULL, NULL, NULL),
(5, 4, 9, '2026-09-14', 18.06089973, 121.59757232, 69.6667, '2026-09-14 12:13:14', NULL, NULL, NULL, NULL),
(6, 4, 9, '2026-09-18', 18.07739892, 121.60576347, 35, '2026-09-18 14:52:23', '2026-09-18 14:58:00', 18.07742553, 121.60571853, 39),
(7, 5, 9, '2026-09-18', 18.07735624, 121.60566519, 41, '2026-09-18 15:06:39', '2026-09-18 15:06:44', 18.07738576, 121.60571836, 41),
(8, 3, 9, '2026-09-18', 18.07743840, 121.60573562, 39, '2026-09-18 15:58:04', '2026-09-18 15:58:06', 18.07743840, 121.60573568, 39);

-- --------------------------------------------------------

--
-- Table structure for table `certificates_sent`
--

CREATE TABLE `certificates_sent` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'sent',
  `sent_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificates_sent`
--

INSERT INTO `certificates_sent` (`id`, `user_id`, `event_id`, `email`, `status`, `sent_at`) VALUES
(1, 2, 9, 'salassss@gmail.com', 'sent', '2026-09-14 12:26:21'),
(3, 4, 9, 'guerrerorussel7@gmail.com', 'sent', '2026-09-18 15:15:39'),
(8, 5, 9, 'garyjoseph.gjb@gmail.com', 'sent', '2026-09-18 15:15:44');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `event_name` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `allowed_departments` text DEFAULT NULL,
  `allowed_year_levels` text DEFAULT NULL,
  `venue_lat` decimal(10,8) DEFAULT NULL,
  `venue_lng` decimal(11,8) DEFAULT NULL,
  `radius_meters` int(11) DEFAULT 100,
  `signatory_name` varchar(100) DEFAULT NULL,
  `signatory_signature` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `event_name`, `start_date`, `end_date`, `allowed_departments`, `allowed_year_levels`, `venue_lat`, `venue_lng`, `radius_meters`, `signatory_name`, `signatory_signature`) VALUES
(9, 'Intrams', '2026-09-16', '2026-09-20', '[]', '[]', 18.07739825, 121.60576315, 1000, 'Russel Guerrero', 'uploads/signatures/sig_event9_1789715460.png');

-- --------------------------------------------------------

--
-- Table structure for table `qr_sent`
--

CREATE TABLE `qr_sent` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'sent',
  `sent_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qr_sent`
--

INSERT INTO `qr_sent` (`id`, `user_id`, `email`, `status`, `sent_at`) VALUES
(1, 4, 'guerrerorussel7@gmail.com', 'sent', '2026-09-18 15:37:51'),
(2, 2, 'salassss@gmail.com', 'sent', '2026-09-18 15:37:41'),
(3, 3, 'juan@example.com', 'sent', '2026-09-18 15:37:46'),
(5, 5, 'garyjoseph.gjb@gmail.com', 'sent', '2026-09-18 15:37:56');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` varchar(50) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `year_level` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `role`, `department`, `year_level`, `email`) VALUES
(2, 'Joenel', 'Faculty', NULL, NULL, 'salassss@gmail.com'),
(3, 'Juan Dela Cruz', 'Student', NULL, NULL, 'juan@example.com'),
(4, 'Maria Santos', 'Faculty', NULL, NULL, 'guerrerorussel7@gmail.com'),
(5, 'Gary', 'Guest', 'CICS', 'Faculty', 'garyjoseph.gjb@gmail.com');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_daily_attendance` (`user_id`,`event_id`,`attendance_date`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `certificates_sent`
--
ALTER TABLE `certificates_sent`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cert` (`user_id`,`event_id`),
  ADD KEY `cert_event_fk` (`event_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `qr_sent`
--
ALTER TABLE `qr_sent`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_qr_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `certificates_sent`
--
ALTER TABLE `certificates_sent`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `qr_sent`
--
ALTER TABLE `qr_sent`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `certificates_sent`
--
ALTER TABLE `certificates_sent`
  ADD CONSTRAINT `cert_event_fk` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cert_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `qr_sent`
--
ALTER TABLE `qr_sent`
  ADD CONSTRAINT `qr_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
