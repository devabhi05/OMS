-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 11, 2025 at 12:45 PM
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
-- Database: `office_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `work_date` date NOT NULL,
  `attendance_status` enum('P','A','Off','Leave','Half') DEFAULT NULL,
  `time_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL,
  `break_duration` time NOT NULL DEFAULT '00:30:00',
  `total_minutes` int(11) NOT NULL DEFAULT 0,
  `overtime_minutes` int(11) NOT NULL DEFAULT 0,
  `remarks` varchar(255) DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `user_id`, `work_date`, `attendance_status`, `time_in`, `time_out`, `break_duration`, `total_minutes`, `overtime_minutes`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 3, '2025-12-05', 'P', '2025-12-05 14:43:56', '2025-12-05 16:59:23', '00:30:00', 105, 0, '', '2025-12-05 14:43:56', '2025-12-05 17:18:01'),
(2, 2, '2025-12-05', 'P', '2025-12-05 15:31:43', '2025-12-05 15:33:05', '00:30:00', 0, 0, '', '2025-12-05 15:31:43', '2025-12-05 15:33:05'),
(3, 3, '2025-12-08', 'P', '2025-12-08 09:22:01', '2025-12-08 16:41:35', '00:30:00', 409, 0, '', '2025-12-08 09:22:01', '2025-12-08 16:41:35'),
(4, 2, '2025-12-08', 'P', '2025-12-08 09:22:19', '2025-12-08 16:42:20', '00:30:00', 410, 0, '', '2025-12-08 09:22:19', '2025-12-08 16:42:20'),
(5, 4, '2025-12-08', 'P', '2025-12-08 11:11:00', '2025-12-08 11:11:20', '00:30:00', 0, 0, '', '2025-12-08 11:11:00', '2025-12-08 11:11:20'),
(6, 3, '2025-12-09', 'P', '2025-12-09 09:23:36', '2025-12-09 17:23:26', '00:30:00', 449, 0, '', '2025-12-09 09:23:36', '2025-12-09 17:23:26'),
(7, 2, '2025-12-09', 'P', '2025-12-09 09:46:02', '2025-12-09 17:18:16', '00:30:00', 422, 0, '', '2025-12-09 09:46:02', '2025-12-09 17:18:16'),
(8, 5, '2025-12-09', 'P', '2025-12-09 14:56:08', '2025-12-09 16:18:27', '00:30:00', 52, 0, '', '2025-12-09 14:56:08', '2025-12-09 16:18:27'),
(9, 3, '2025-12-10', 'P', '2025-12-10 09:20:19', '2025-12-10 15:25:50', '00:30:00', 335, 0, '', '2025-12-10 09:20:19', '2025-12-10 15:25:50'),
(10, 2, '2025-12-10', 'P', '2025-12-10 09:21:01', '2025-12-10 14:33:00', '00:30:00', 281, 0, '', '2025-12-10 09:21:01', '2025-12-10 14:33:00'),
(11, 5, '2025-12-10', 'P', '2025-12-10 11:36:43', '2025-12-10 14:12:42', '00:30:00', 125, 0, '', '2025-12-10 11:36:43', '2025-12-10 14:12:42'),
(12, 4, '2025-12-10', 'P', '2025-12-10 11:40:55', '2025-12-10 13:03:40', '00:30:00', 52, 0, '', '2025-12-10 11:40:55', '2025-12-10 13:03:40'),
(13, 6, '2025-12-10', 'P', '2025-12-10 14:50:00', '2025-12-10 14:50:52', '00:30:00', 0, 0, '', '2025-12-10 14:50:00', '2025-12-10 14:50:52'),
(14, 3, '2025-12-11', 'P', '2025-12-11 09:25:12', '2025-12-11 13:50:11', '00:30:00', 234, 0, '', '2025-12-11 09:25:12', '2025-12-11 13:50:11'),
(15, 2, '2025-12-11', 'P', '2025-12-11 09:26:28', '2025-12-11 09:26:29', '00:30:00', 0, 0, '', '2025-12-11 09:26:28', '2025-12-11 09:26:29'),
(16, 4, '2025-12-11', 'P', '2025-12-11 09:26:43', '2025-12-11 15:39:06', '00:30:00', 342, 0, '', '2025-12-11 09:26:43', '2025-12-11 15:39:06'),
(17, 5, '2025-12-11', 'P', '2025-12-11 09:27:08', '2025-12-11 09:27:35', '00:30:00', 0, 0, '', '2025-12-11 09:27:08', '2025-12-11 09:27:35');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `description`, `created_by`, `created_at`) VALUES
(10, 'Timesheet', 'Office Work', 1, '2025-12-04 16:38:17'),
(11, 'Attendance', 'Attendance table', 1, '2025-12-05 15:29:01'),
(12, 'Management', 'employee management system', 1, '2025-12-08 10:41:15'),
(13, 'Lab', 'A lab Website', 1, '2025-12-08 16:05:05'),
(14, 'Sigma Project', 'Stock', 1, '2025-12-10 11:59:04');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `status` enum('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
  `start_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `project_id`, `title`, `description`, `created_by`, `status`, `start_date`, `due_date`, `created_at`) VALUES
(21, 10, 'Dashboard', 'Create dashboard', 1, 'completed', NULL, NULL, '2025-12-04 16:38:40'),
(22, 10, 'Table', 'data tabel', 1, 'completed', NULL, NULL, '2025-12-04 16:39:09'),
(23, 10, 'csv', 'Excel import', 1, 'completed', NULL, NULL, '2025-12-04 16:52:21'),
(24, 10, 'dtata', 'figer', 1, 'completed', NULL, NULL, '2025-12-04 16:57:58'),
(25, 11, 'Manage', 'Employee attendance', 1, 'completed', NULL, NULL, '2025-12-05 15:29:41'),
(26, 12, 'Work Log', 'Work log hours history', 1, 'completed', NULL, NULL, '2025-12-08 10:42:10'),
(27, 12, 'Database system', 'sql Table', 1, 'completed', NULL, NULL, '2025-12-08 15:44:22'),
(31, 11, 'System', 'data', 1, 'completed', NULL, NULL, '2025-12-08 15:50:26'),
(34, 12, 'Prepare Timesheet', 'Timesheet for Employee log', 1, 'completed', NULL, NULL, '2025-12-08 16:04:00'),
(35, 13, 'Lab Dashboard', 'lab website', 1, 'completed', NULL, NULL, '2025-12-08 16:05:43'),
(36, 11, 'ABC', 'asdfa', 1, 'completed', NULL, NULL, '2025-12-08 16:40:05'),
(37, 12, 'mas', 'sdfgdsf', 1, 'completed', NULL, NULL, '2025-12-08 16:40:13'),
(38, 13, 'Chemistry', 'sjhfjh', 1, 'completed', NULL, NULL, '2025-12-08 16:40:40'),
(39, 14, 'Profile Interface', 'Create a profile UI', 1, 'completed', NULL, NULL, '2025-12-10 11:59:42'),
(40, 11, 'View Button', 'Create view Button', 1, 'completed', NULL, NULL, '2025-12-10 14:24:30'),
(41, 11, 'User Details', 'Create a User Details page.', 1, 'pending', NULL, NULL, '2025-12-11 11:43:22');

-- --------------------------------------------------------

--
-- Table structure for table `timesheets`
--

CREATE TABLE `timesheets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `work_date` date NOT NULL,
  `hours` decimal(5,2) NOT NULL,
  `status` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `manager_approved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `timesheets`
--

INSERT INTO `timesheets` (`id`, `user_id`, `task_id`, `work_date`, `hours`, `status`, `notes`, `manager_approved`, `created_at`, `updated_at`) VALUES
(16, 3, 21, '2025-12-04', 4.00, 'Completed', NULL, 1, '2025-12-04 16:41:05', '2025-12-04 16:42:17'),
(17, 3, 22, '2025-12-04', 6.00, 'Completed', NULL, 1, '2025-12-04 16:41:21', '2025-12-04 16:42:14'),
(18, 3, 23, '2025-12-03', 5.00, 'Completed', NULL, 1, '2025-12-04 16:52:54', '2025-12-04 16:53:37'),
(19, 3, 24, '2025-12-04', 4.00, 'Completed', NULL, 1, '2025-12-04 16:59:04', '2025-12-04 16:59:41'),
(20, 3, 25, '2025-12-05', 8.00, 'Completed', NULL, 1, '2025-12-05 15:31:24', '2025-12-05 15:33:02'),
(21, 4, 26, '2025-12-08', 8.00, 'Completed', NULL, 1, '2025-12-08 11:11:14', '2025-12-08 11:14:50'),
(22, 3, 26, '2025-12-08', 8.00, 'Completed', NULL, 1, '2025-12-08 11:15:19', '2025-12-08 11:15:35'),
(23, 3, 31, '2025-12-08', 5.00, 'Completed', NULL, 1, '2025-12-08 15:53:35', '2025-12-08 15:59:34'),
(24, 3, 27, '2025-12-08', 3.00, 'Completed', NULL, 1, '2025-12-08 15:53:54', '2025-12-08 15:59:28'),
(25, 3, 34, '2025-12-09', 4.00, 'Completed', NULL, 1, '2025-12-08 16:07:05', '2025-12-08 16:08:22'),
(26, 3, 35, '2025-12-09', 4.00, 'Completed', NULL, 1, '2025-12-08 16:07:18', '2025-12-08 16:08:19'),
(27, 3, 36, '2025-12-08', 2.00, 'Completed', NULL, 1, '2025-12-08 16:41:04', '2025-12-08 16:41:55'),
(28, 3, 38, '2025-12-08', 5.00, 'Completed', NULL, 1, '2025-12-08 16:41:10', '2025-12-08 16:41:53'),
(29, 3, 37, '2025-12-08', 1.00, 'Completed', NULL, 1, '2025-12-08 16:41:31', '2025-12-08 16:41:51'),
(30, 3, 39, '2025-12-10', 2.00, 'Completed', 'Task Compleated', 1, '2025-12-10 13:01:16', '2025-12-10 13:56:09'),
(31, 5, 39, '2025-12-10', 2.00, 'Completed', 'Done', 1, '2025-12-10 13:02:36', '2025-12-10 13:56:07'),
(32, 4, 39, '2025-12-10', 2.00, 'Completed', 'submited', 1, '2025-12-10 13:03:36', '2025-12-10 13:56:05'),
(33, 3, 40, '2025-12-10', 4.00, 'Completed', 'View button created.', 1, '2025-12-10 14:25:43', '2025-12-10 14:29:17'),
(34, 3, 41, '2025-12-11', 2.00, 'Completed', 'preparing user details', 0, '2025-12-11 13:48:25', '2025-12-11 13:48:25');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `social_facebook` varchar(191) DEFAULT NULL,
  `social_linkedin` varchar(191) DEFAULT NULL,
  `social_instagram` varchar(191) DEFAULT NULL,
  `social_twitter` varchar(191) DEFAULT NULL,
  `social_other` varchar(191) DEFAULT NULL,
  `profile_image` varchar(191) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager','employee') NOT NULL DEFAULT 'employee',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `first_name`, `last_name`, `username`, `email`, `mobile`, `phone`, `address`, `social_facebook`, `social_linkedin`, `social_instagram`, `social_twitter`, `social_other`, `profile_image`, `password`, `role`, `created_at`) VALUES
(1, 'Admin User', 'Admin', 'User', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Yh$A&fe@&;3Sf9q5', 'admin', '2025-12-01 16:58:40'),
(2, 'Manager User', 'Manager', 'User', 'manager', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '4a&Nw*Z3QWtz94bw', 'manager', '2025-12-01 16:58:40'),
(3, 'Employee User', 'Employee', 'User', 'employee', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ')2tp;5ct)mjwwgX?', 'employee', '2025-12-01 16:58:40'),
(4, 'Sarvjeet Gautam', 'Sarvjeet', 'Gautam', 'sarvjeet', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'LId*SnK;I9GX%?K?', 'employee', '2025-12-03 16:29:12'),
(5, 'Ravi Kumar', 'Ravi', 'Kumar', 'ravik', 'ravi45@gmail.com', '918855669955', '918855669933', '273015, Jatepur North  Gorakhpur, UP - India', 'facebook.com', 'linked.in', 'insta.com', 'x.com', 'Github.io', 'uploads/profile_images/1765268820_a3e79cf8b5f9fb46.jpg', '&GQfn)LQrD@Jf!3d', 'employee', '2025-12-09 13:57:00'),
(6, 'Eren Yeager', 'Eren', 'Yeager', 'eren', 'yegereren@gmail.com', '918855224466', '918844224466', 'Lulusisa Anime world pin-929264 Earth', 'eren/fb.com', 'eren.linked.in', 'eren.insta.com', 'x.eren.x.com', 'github.erenyeger.io', 'uploads/profile_images/1765358264_046930e6a76fae6a.jpg', 'KV@g&wwqbB2x!(K?', 'employee', '2025-12-10 14:47:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_date` (`user_id`,`work_date`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `timesheets`
--
ALTER TABLE `timesheets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `task_id` (`task_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `timesheets`
--
ALTER TABLE `timesheets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);

--
-- Constraints for table `timesheets`
--
ALTER TABLE `timesheets`
  ADD CONSTRAINT `timesheets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `timesheets_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
