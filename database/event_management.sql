-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 01, 2026 at 07:01 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `event_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'admin', 'admin123', '2026-03-15 17:03:51');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','success','danger') DEFAULT 'info',
  `event_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` varchar(50) DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `emails_sent` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `message`, `type`, `event_id`, `is_active`, `created_by`, `created_at`, `emails_sent`) VALUES
(1, 'street play', 'all street play come before 10..30am', 'info', 14, 1, 'admin', '2026-03-31 12:37:29', 0),
(2, 'all participants', 'come 2 floor', 'info', NULL, 1, 'admin', '2026-04-01 03:47:10', 0),
(3, 'coding challenge starting in 15 minutes', 'come to hall', 'info', 3, 1, 'admin', '2026-04-01 16:03:59', 0),
(4, 'coding challenge starting in 15 minutes', 'come to hall', 'info', 3, 1, 'admin', '2026-04-01 16:07:41', 0),
(6, 'poetry recitation', 'starting in 15 mins', 'info', 10, 1, 'admin', '2026-04-01 16:20:46', 1),
(7, 'all participants', 'come to diamond jublee', 'danger', NULL, 1, 'admin', '2026-04-01 16:22:03', 18);

-- --------------------------------------------------------

--
-- Table structure for table `checkins`
--

CREATE TABLE `checkins` (
  `id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `note` varchar(100) DEFAULT '',
  `checked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `checkins`
--

INSERT INTO `checkins` (`id`, `participant_id`, `note`, `checked_at`) VALUES
(1, 1, '', '2026-03-28 15:08:49'),
(2, 2, '', '2026-03-29 09:20:20'),
(4, 14, 'QR Scan', '2026-04-01 16:41:19');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `event_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `max_participants` int(11) DEFAULT 50,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `event_type` enum('solo','group') DEFAULT 'solo',
  `min_team_size` int(11) DEFAULT 1,
  `max_team_size` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `event_name`, `description`, `max_participants`, `created_at`, `event_type`, `min_team_size`, `max_team_size`) VALUES
(1, 'Solo Singing', 'Individual vocal performance competition', 40, '2026-03-15 17:03:51', 'solo', 1, 1),
(2, 'Group Dance', 'Team dance performance (4-8 members)', 20, '2026-03-15 17:03:51', 'group', 4, 8),
(3, 'Coding Challenge', 'Competitive programming contest', 50, '2026-03-15 17:03:51', 'solo', 1, 1),
(4, 'Photography Contest', 'Creative photography competition', 30, '2026-03-15 17:03:51', 'solo', 1, 1),
(5, 'Debate Competition', 'Public speaking and argumentation', 40, '2026-03-15 17:03:51', 'solo', 1, 1),
(6, 'Short Film', 'Mini film making and direction competition', 15, '2026-03-15 17:03:51', 'group', 3, 6),
(7, 'Solo Dance', 'Individual dance performance — any style', 40, '2026-03-21 13:21:41', 'solo', 1, 1),
(8, 'Stand-up Comedy', 'Individual comedy performance — original content only', 30, '2026-03-21 13:21:42', 'solo', 1, 1),
(9, 'Mono Acting', 'Solo theatrical performance — any language', 30, '2026-03-21 13:21:42', 'solo', 1, 1),
(10, 'Poetry Recitation', 'Individual poem recitation — original or classic', 35, '2026-03-21 13:21:42', 'solo', 1, 1),
(11, 'Face Painting', 'Creative face art — individual performance', 25, '2026-03-21 13:21:42', 'solo', 1, 1),
(12, 'Quiz Competition', 'Individual general knowledge and technical quiz', 60, '2026-03-21 13:21:42', 'solo', 1, 1),
(13, 'Group Singing', 'Choir or band performance — 3 to 8 members', 15, '2026-03-21 13:21:42', 'group', 3, 8),
(14, 'Street Play (Nukkad)', 'Outdoor theatrical performance — social message', 10, '2026-03-21 13:21:42', 'group', 5, 10),
(15, 'Band Performance', 'Live music band — original or cover songs', 10, '2026-03-21 13:21:42', 'group', 3, 8),
(16, 'Fashion Show', 'Themed costume and ramp walk — group coordination judged', 10, '2026-03-21 13:21:42', 'group', 5, 12),
(17, 'Skit', 'Short comedy or drama skit — 3 to 6 members', 15, '2026-03-21 13:21:42', 'group', 3, 6),
(18, 'Treasure Hunt', 'Campus-wide treasure hunt — team strategy and speed', 20, '2026-03-21 13:21:42', 'group', 3, 5);

-- --------------------------------------------------------

--
-- Table structure for table `group_members`
--

CREATE TABLE `group_members` (
  `id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `member_name` varchar(120) NOT NULL,
  `member_phone` varchar(20) DEFAULT '',
  `member_email` varchar(120) DEFAULT '',
  `member_dept` varchar(100) DEFAULT '',
  `member_sid` varchar(50) DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `judges`
--

CREATE TABLE `judges` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `assigned_event` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `judges`
--

INSERT INTO `judges` (`id`, `username`, `password`, `assigned_event`, `created_at`) VALUES
(1, 'judge1', 'judge123', 1, '2026-03-15 17:03:51'),
(2, 'judge2', 'judge123', 2, '2026-03-15 17:03:51'),
(3, 'judge3', 'judge123', 3, '2026-03-15 17:03:51');

-- --------------------------------------------------------

--
-- Table structure for table `participants`
--

CREATE TABLE `participants` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `email` varchar(100) NOT NULL,
  `college` varchar(150) NOT NULL,
  `department` varchar(100) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `registration_type` enum('solo','group') NOT NULL DEFAULT 'solo',
  `group_name` varchar(120) DEFAULT NULL,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `participants`
--

INSERT INTO `participants` (`id`, `name`, `phone`, `email`, `college`, `department`, `student_id`, `registration_type`, `group_name`, `registered_at`) VALUES
(1, 'mariganesh', '9384748627', 'mariganesh500@gmail.com', 'v.o.c college', 'B.Sc Computer Science', '017', 'solo', NULL, '2026-03-15 17:06:27'),
(2, 'chandru S', '9003899956', 'guruchandru725@gmail.com', 'popes college', 'B.Sc Computer Science', '114', 'solo', NULL, '2026-03-16 03:56:08'),
(3, 'ashok', '9087654321', 'ashok@gmail.com', 'kamaraj college', 'B.Sc Computer Science', '003', 'solo', NULL, '2026-03-16 04:12:20'),
(4, 'subbiah', '9876543210', 'subbiah@gmail.com', 'grace college', 'B.Sc geogly', '040', 'solo', NULL, '2026-03-16 04:26:57'),
(5, 'kumar', '9678054321', 'kumar@gmail.com', 'Biscop college', 'bca', '020', 'solo', NULL, '2026-03-18 05:35:08'),
(6, 'mariganesh', '+919384748620', 'marichandru@gmail.com', 'kamaraj college', 'B.Sc Computer Science', '88', 'solo', NULL, '2026-03-21 13:27:32'),
(7, 'kvoy', '+919384748786', 'kvoy@gmail.com', 'grace college', 'bca', '7tr6', 'solo', NULL, '2026-03-21 13:46:49'),
(8, 'ghobi', '+919384748098', 'hhbgfbc@gmail.com', 'grace college', 'B.Sc Computer Science', '88ij88', 'solo', NULL, '2026-03-21 14:07:18'),
(9, 'selva', '9876543222', 'fgd500@gmail.com', 'Biscop college', 'B.Sc Computer Science', '6576', 'solo', NULL, '2026-03-22 11:22:59'),
(11, 'velan', '8989898909', 'velan@gmail.com', 'v.o.c college', 'B.Sc geogly', '34356', 'solo', NULL, '2026-03-22 18:08:03'),
(12, 'nuhman', '+919384748634', 'nuhmanmohamed784@gmail.com', 'v.o.c college', 'B.Sc Computer Science', '882', 'solo', NULL, '2026-03-25 10:13:15'),
(13, 'M.AnnamalaiAshok', '8838057485', 'malaiashok2006@gmail.com', 'v.o.c college', 'B.Sc Computer Science', '3007', 'solo', NULL, '2026-03-25 10:33:53'),
(14, 'ng', '9999999999', 'guruchandru825@gmail.com', 'grace college', 'B.Sc Computer Science', '888', 'solo', NULL, '2026-03-28 12:20:24'),
(15, 'nnn', '9876767676', 'marichandru5555@gmaill.com', 'v.o.c college', 'B.Sc Computer Science', '876', 'solo', NULL, '2026-03-28 12:22:45'),
(20, 'selvin', '9876543212', 'strangerthing831@gmail.com', 'Biscop college', 'bca', '111', 'solo', NULL, '2026-03-29 17:34:43'),
(21, 'mariganesh', '98765656565', 'uyviut@gmail.com', 'popes college', 'B.Sc Computer Science', '880', 'solo', NULL, '2026-03-30 02:21:38'),
(22, 'mariganesh', '98765656565', 'masdfr@gmail.com', 'popes college', 'B.Sc Computer Science', '880', 'solo', NULL, '2026-03-30 02:22:06'),
(23, 'ashok', '7867854323', 'aalumni65@gmail.com', 'grace college', 'bca', '23447', 'solo', NULL, '2026-03-31 12:50:29');

-- --------------------------------------------------------

--
-- Table structure for table `participant_events`
--

CREATE TABLE `participant_events` (
  `id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `participant_events`
--

INSERT INTO `participant_events` (`id`, `participant_id`, `event_id`) VALUES
(1, 1, 1),
(2, 1, 3),
(3, 2, 1),
(4, 2, 3),
(5, 3, 2),
(6, 3, 5),
(7, 4, 2),
(8, 4, 6),
(9, 5, 4),
(10, 5, 5),
(11, 6, 15),
(12, 7, 3),
(13, 7, 9),
(14, 8, 15),
(15, 9, 18),
(17, 11, 3),
(18, 12, 4),
(19, 13, 4),
(20, 13, 7),
(21, 14, 6),
(22, 15, 11),
(27, 20, 2),
(28, 21, 17),
(29, 22, 17),
(30, 23, 10);

-- --------------------------------------------------------

--
-- Table structure for table `scores`
--

CREATE TABLE `scores` (
  `id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `judge_id` int(11) NOT NULL,
  `creativity` int(11) DEFAULT 0,
  `performance` int(11) DEFAULT 0,
  `presentation` int(11) DEFAULT 0,
  `total` int(11) GENERATED ALWAYS AS (`creativity` + `performance` + `presentation`) STORED,
  `scored_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `scores`
--

INSERT INTO `scores` (`id`, `participant_id`, `event_id`, `judge_id`, `creativity`, `performance`, `presentation`, `scored_at`) VALUES
(1, 1, 1, 1, 24, 29, 26, '2026-03-15 17:18:01'),
(2, 2, 1, 1, 30, 30, 30, '2026-03-17 08:49:59'),
(3, 3, 2, 2, 30, 30, 26, '2026-03-17 08:51:18'),
(4, 4, 2, 2, 22, 30, 30, '2026-03-17 08:51:36'),
(5, 2, 3, 3, 25, 30, 30, '2026-03-17 08:52:14'),
(6, 1, 3, 3, 30, 30, 27, '2026-03-17 08:52:45'),
(7, 2, 3, 1, 27, 5, 7, '2026-03-17 14:17:30'),
(8, 1, 3, 1, 22, 12, 30, '2026-03-18 05:38:34'),
(9, 3, 5, 1, 22, 11, 30, '2026-03-18 05:39:08'),
(10, 5, 5, 1, 30, 22, 22, '2026-03-18 05:39:50'),
(11, 3, 2, 1, 23, 12, 30, '2026-03-18 05:40:42'),
(12, 4, 2, 1, 12, 28, 22, '2026-03-18 05:41:00'),
(13, 5, 4, 1, 23, 30, 23, '2026-03-18 05:41:22'),
(14, 4, 6, 1, 30, 26, 22, '2026-03-18 05:41:41'),
(15, 2, 3, 2, 22, 22, 12, '2026-03-18 05:42:49'),
(16, 1, 3, 2, 23, 30, 11, '2026-03-18 05:43:02'),
(17, 3, 5, 2, 23, 30, 23, '2026-03-18 05:43:20'),
(18, 5, 5, 2, 23, 12, 23, '2026-03-18 05:43:36'),
(19, 5, 4, 2, 30, 27, 12, '2026-03-18 05:43:57'),
(20, 4, 6, 2, 23, 12, 30, '2026-03-18 05:44:18'),
(21, 2, 1, 2, 30, 12, 30, '2026-03-18 05:44:40'),
(22, 1, 1, 2, 30, 30, 20, '2026-03-18 05:44:55'),
(23, 3, 5, 3, 30, 30, 15, '2026-03-18 05:45:47'),
(24, 5, 5, 3, 23, 28, 22, '2026-03-18 05:46:03'),
(25, 3, 2, 3, 30, 20, 25, '2026-03-18 05:46:20'),
(26, 4, 2, 3, 30, 20, 22, '2026-03-18 05:46:35'),
(27, 5, 4, 3, 30, 22, 22, '2026-03-18 05:46:51'),
(28, 4, 6, 3, 26, 21, 30, '2026-03-18 05:47:11'),
(29, 2, 1, 3, 23, 30, 12, '2026-03-18 05:47:41'),
(30, 1, 1, 3, 30, 29, 12, '2026-03-18 05:47:59'),
(31, 8, 15, 2, 19, 23, 28, '2026-03-21 16:36:31'),
(32, 6, 15, 2, 23, 22, 30, '2026-03-21 16:36:56'),
(33, 7, 9, 1, 22, 22, 22, '2026-03-21 16:39:38'),
(34, 9, 18, 1, 23, 30, 27, '2026-03-22 11:23:38'),
(35, 9, 18, 2, 23, 30, 23, '2026-03-22 11:24:11'),
(36, 9, 18, 3, 23, 30, 23, '2026-03-22 11:24:50'),
(37, 7, 3, 1, 10, 30, 10, '2026-03-22 18:04:16'),
(38, 7, 3, 2, 23, 23, 5, '2026-03-22 18:04:46'),
(39, 8, 15, 3, 12, 22, 12, '2026-03-22 18:05:11'),
(40, 7, 3, 3, 11, 12, 22, '2026-03-22 18:06:34'),
(41, 11, 3, 1, 12, 11, 11, '2026-03-22 18:09:04'),
(42, 11, 3, 2, 21, 11, 11, '2026-03-22 18:09:30'),
(43, 11, 3, 3, 12, 11, 11, '2026-03-22 18:10:02'),
(44, 8, 15, 1, 11, 6, 19, '2026-03-25 10:36:52'),
(45, 6, 15, 1, 30, 30, 30, '2026-03-27 16:45:56'),
(46, 13, 4, 2, 23, 23, 30, '2026-03-27 16:50:12'),
(47, 12, 4, 2, 30, 30, 30, '2026-03-27 16:51:11'),
(48, 14, 6, 1, 25, 20, 15, '2026-04-01 16:38:59'),
(49, 14, 6, 2, 25, 30, 20, '2026-04-01 16:39:24'),
(50, 6, 15, 3, 30, 25, 25, '2026-04-01 16:40:06');

-- --------------------------------------------------------

--
-- Table structure for table `team_members`
--

CREATE TABLE `team_members` (
  `id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `member_name` varchar(100) NOT NULL,
  `member_student_id` varchar(50) DEFAULT NULL,
  `member_department` varchar(100) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `team_members`
--

INSERT INTO `team_members` (`id`, `participant_id`, `event_id`, `member_name`, `member_student_id`, `member_department`, `added_at`) VALUES
(1, 8, 15, 'ghf', '5646', 'cs', '2026-03-21 14:07:18'),
(2, 8, 15, 'jhg', '5649', 'cs', '2026-03-21 14:07:18'),
(3, 9, 18, 'ssalmon', '343', 'cs', '2026-03-22 11:22:59'),
(4, 9, 18, 'shan', '323', 'cs', '2026-03-22 11:22:59'),
(5, 14, 6, 'jj', '', 'bsc cs', '2026-03-28 12:20:24'),
(6, 14, 6, 'kkumar', '', 'bsc cs', '2026-03-28 12:20:24'),
(7, 20, 2, 'salmon', '112', 'bca', '2026-03-29 17:34:43'),
(8, 20, 2, 'selvam', '113', 'bca', '2026-03-29 17:34:43'),
(9, 20, 2, 'shan', '114', 'bca', '2026-03-29 17:34:43'),
(10, 21, 17, 'vt7i7c', '876', 'bca', '2026-03-30 02:21:38'),
(11, 21, 17, 'htdhcy', '987', 'bca', '2026-03-30 02:21:38'),
(12, 22, 17, 'vt7i7c', '876', 'bca', '2026-03-30 02:22:06'),
(13, 22, 17, 'htdhcy', '987', 'bca', '2026-03-30 02:22:06');

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
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `checkins`
--
ALTER TABLE `checkins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `participant_id` (`participant_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `group_members`
--
ALTER TABLE `group_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `participant_id` (`participant_id`);

--
-- Indexes for table `judges`
--
ALTER TABLE `judges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `assigned_event` (`assigned_event`);

--
-- Indexes for table `participants`
--
ALTER TABLE `participants`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `participant_events`
--
ALTER TABLE `participant_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `participant_id` (`participant_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `scores`
--
ALTER TABLE `scores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `participant_id` (`participant_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `judge_id` (`judge_id`);

--
-- Indexes for table `team_members`
--
ALTER TABLE `team_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `participant_id` (`participant_id`),
  ADD KEY `event_id` (`event_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `checkins`
--
ALTER TABLE `checkins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `group_members`
--
ALTER TABLE `group_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `judges`
--
ALTER TABLE `judges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `participants`
--
ALTER TABLE `participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `participant_events`
--
ALTER TABLE `participant_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `scores`
--
ALTER TABLE `scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `team_members`
--
ALTER TABLE `team_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `checkins`
--
ALTER TABLE `checkins`
  ADD CONSTRAINT `checkins_ibfk_1` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `group_members`
--
ALTER TABLE `group_members`
  ADD CONSTRAINT `group_members_ibfk_1` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `judges`
--
ALTER TABLE `judges`
  ADD CONSTRAINT `judges_ibfk_1` FOREIGN KEY (`assigned_event`) REFERENCES `events` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `participant_events`
--
ALTER TABLE `participant_events`
  ADD CONSTRAINT `participant_events_ibfk_1` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `participant_events_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scores`
--
ALTER TABLE `scores`
  ADD CONSTRAINT `scores_ibfk_1` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scores_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scores_ibfk_3` FOREIGN KEY (`judge_id`) REFERENCES `judges` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `team_members`
--
ALTER TABLE `team_members`
  ADD CONSTRAINT `team_members_ibfk_1` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_members_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
