-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 14, 2026 at 09:22 AM
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
-- Database: `urbanstay_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `pg_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent') DEFAULT 'present'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `tenant_id`, `pg_id`, `date`, `status`) VALUES
(1, 3, 3, '2026-04-12', 'present');

-- --------------------------------------------------------

--
-- Table structure for table `beds`
--

CREATE TABLE `beds` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `bed_label` varchar(10) NOT NULL,
  `status` enum('available','occupied') DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `beds`
--

INSERT INTO `beds` (`id`, `room_id`, `bed_label`, `status`) VALUES
(7, 4, 'A', 'available'),
(8, 4, 'B', 'occupied'),
(9, 5, 'A', 'occupied'),
(10, 5, 'B', 'available'),
(11, 5, 'C', 'available'),
(12, 6, 'A', 'available'),
(13, 6, 'B', 'available'),
(14, 6, 'C', 'available');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `bed_id` int(11) NOT NULL,
  `pg_id` int(11) NOT NULL,
  `personal_info_json` text DEFAULT NULL,
  `status` enum('processing','confirmed','rejected','completed') DEFAULT 'processing',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `tenant_id`, `bed_id`, `pg_id`, `personal_info_json`, `status`, `requested_at`, `approved_at`) VALUES
(1, 3, 7, 3, '{\"full_name\":\"manu\",\"phone\":\"1234\",\"id_type\":\"Aadhar\",\"id_number\":\"454645\",\"address\":\"mysore\",\"profile_photo\":\"assets\\/images\\/uploads\\/1776142315_Screenshot4.png\",\"emergency_name\":\"yash\",\"emergency_phone\":\"12345\"}', 'completed', '2026-04-12 12:51:21', '2026-04-12 12:56:38'),
(2, 9, 8, 3, '{\"full_name\":\"manu\",\"phone\":\"1234\",\"id_type\":\"Aadhar\",\"id_number\":\"454645\",\"address\":\"mysore\",\"profile_photo\":\"assets\\/images\\/uploads\\/1775998827_Screenshot (1).png\",\"emergency_name\":\"yash\",\"emergency_phone\":\"12345\"}', 'confirmed', '2026-04-12 13:00:27', '2026-04-12 13:02:02'),
(3, 10, 10, 3, '{\"full_name\":\"ravi\",\"phone\":\"1234\",\"id_type\":\"Aadhar\",\"id_number\":\"454645\",\"address\":\"mysore\",\"profile_photo\":\"assets\\/images\\/uploads\\/1776000493_Screenshot2.png\",\"emergency_name\":\"yash\",\"emergency_phone\":\"12345\"}', 'rejected', '2026-04-12 13:28:13', NULL),
(4, 10, 10, 3, '{\"full_name\":\"ravi\",\"phone\":\"1234\",\"id_type\":\"Aadhar\",\"id_number\":\"454645\",\"address\":\"mysore\",\"profile_photo\":\"assets\\/images\\/uploads\\/1776003107_Screenshot1.png\",\"emergency_name\":\"yash\",\"emergency_phone\":\"12345\"}', 'rejected', '2026-04-12 14:11:47', NULL),
(5, 10, 9, 3, '{\"full_name\":\"ravi\",\"phone\":\"1234\",\"id_type\":\"Aadhar\",\"id_number\":\"454645\",\"address\":\"mysore\",\"profile_photo\":\"assets\\/images\\/uploads\\/1776003689_2950.png\",\"emergency_name\":\"yash\",\"emergency_phone\":\"12345\"}', 'confirmed', '2026-04-12 14:21:29', '2026-04-12 14:50:39'),
(6, 11, 10, 3, '{\"full_name\":\"gowthu\",\"phone\":\"1234\",\"id_type\":\"Aadhar\",\"id_number\":\"454645\",\"address\":\"mysore\",\"profile_photo\":\"assets\\/images\\/uploads\\/1776004235_4207.png\",\"emergency_name\":\"yash\",\"emergency_phone\":\"12345\"}', 'rejected', '2026-04-12 14:30:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `pg_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `status` enum('open','resolved') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `pg_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `tenant_id`, `pg_id`, `rating`, `review`, `created_at`) VALUES
(1, 3, 3, 5, 'vwry good\r\n', '2026-04-14 04:48:55');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `is_read`, `created_at`) VALUES
(1, 3, 'Your booking has been confirmed!', 1, '2026-04-12 12:56:29'),
(2, 3, 'Your booking has been confirmed!', 1, '2026-04-12 12:56:38'),
(3, 9, 'Your booking has been confirmed!', 1, '2026-04-12 13:02:02'),
(4, 9, 'Your payment has been verified', 1, '2026-04-12 13:18:46'),
(5, 4, 'New booking request received from ravi', 0, '2026-04-12 13:28:13'),
(6, 10, 'Your booking request has been submitted. Please wait for manager approval.', 1, '2026-04-12 13:28:13'),
(7, 4, 'New booking request received from ravi', 0, '2026-04-12 14:11:47'),
(8, 10, 'Your booking request has been submitted. Please wait for manager approval.', 1, '2026-04-12 14:11:47'),
(9, 4, 'New booking request from ravi', 0, '2026-04-12 14:21:30'),
(10, 10, 'Your booking request has been submitted. Please visit the PG for confirmation.', 1, '2026-04-12 14:21:30'),
(11, 4, 'New booking request from gowthu', 0, '2026-04-12 14:30:35'),
(12, 11, 'Your booking request has been submitted. Please visit the PG for confirmation.', 1, '2026-04-12 14:30:35'),
(13, 10, 'Your booking has been confirmed!', 1, '2026-04-12 14:50:39'),
(14, 4, 'A parent has requested to unlink from their child. Please review.', 0, '2026-04-14 06:14:25'),
(15, 4, 'A parent has requested to unlink from their child. Please review.', 0, '2026-04-14 06:19:53'),
(16, 4, 'A parent has requested to unlink from child: manu. Please review the request.', 0, '2026-04-14 06:55:32'),
(17, 12, 'Your request to unlink from child has been approved. You can no longer view this child\'s details.', 1, '2026-04-14 06:58:50'),
(18, 4, 'A parent has requested to unlink from child: manu. Please review the request.', 0, '2026-04-14 07:00:13'),
(19, 12, 'Your request to unlink from child has been rejected. Please contact the PG manager for more information.', 1, '2026-04-14 07:03:05'),
(20, 4, 'Vacate request received from yashu', 0, '2026-04-14 07:03:37'),
(21, 3, 'Your vacate request has been approved', 1, '2026-04-14 07:03:58');

-- --------------------------------------------------------

--
-- Table structure for table `parent_tenant`
--

CREATE TABLE `parent_tenant` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parent_tenant`
--

INSERT INTO `parent_tenant` (`id`, `parent_id`, `tenant_id`) VALUES
(7, 12, 9);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `pg_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_type` enum('upi','cash') NOT NULL,
  `utr_number` varchar(100) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `status` enum('pending','verified','rejected') DEFAULT 'pending',
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `tenant_id`, `pg_id`, `amount`, `payment_type`, `utr_number`, `payment_date`, `status`, `recorded_by`, `created_at`) VALUES
(1, 9, 3, 1000.00, 'upi', '653535636753678', '2026-04-12', 'verified', NULL, '2026-04-12 13:17:30');

-- --------------------------------------------------------

--
-- Table structure for table `pgs`
--

CREATE TABLE `pgs` (
  `id` int(11) NOT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `address` text NOT NULL,
  `type` enum('Male','Female','Both') DEFAULT 'Both',
  `price_per_month` decimal(10,2) DEFAULT 0.00,
  `amenities` text DEFAULT NULL,
  `upi_id` varchar(100) DEFAULT NULL,
  `qr_code_image` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pgs`
--

INSERT INTO `pgs` (`id`, `manager_id`, `name`, `address`, `type`, `price_per_month`, `amenities`, `upi_id`, `qr_code_image`, `status`, `created_at`) VALUES
(2, 2, 'sri pg', 'mysore', 'Both', 0.00, NULL, NULL, NULL, 'approved', '2026-04-05 12:29:10'),
(3, 4, 'hari pg', 'mandya', 'Both', 0.00, NULL, NULL, NULL, 'approved', '2026-04-08 14:37:30');

-- --------------------------------------------------------

--
-- Table structure for table `pg_images`
--

CREATE TABLE `pg_images` (
  `id` int(11) NOT NULL,
  `pg_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pg_images`
--

INSERT INTO `pg_images` (`id`, `pg_id`, `image_path`) VALUES
(1, 3, 'assets/images/uploads/1775998965_Screenshot2.png'),
(2, 2, 'assets/images/uploads/1776150995_Screenshot6.png');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `pg_id` int(11) NOT NULL,
  `room_number` varchar(50) NOT NULL,
  `sharing_type` varchar(50) DEFAULT NULL,
  `total_beds` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `pg_id`, `room_number`, `sharing_type`, `total_beds`) VALUES
(4, 3, '102', NULL, 2),
(5, 3, '103', NULL, 3),
(6, 3, '104', NULL, 3);

-- --------------------------------------------------------

--
-- Table structure for table `tenant_details`
--

CREATE TABLE `tenant_details` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `rent_amount` decimal(10,2) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `move_in_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tenant_details`
--

INSERT INTO `tenant_details` (`id`, `booking_id`, `rent_amount`, `due_date`, `move_in_date`) VALUES
(1, 2, 1000.00, '2026-04-23', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `unlink_requests`
--

CREATE TABLE `unlink_requests` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `unlink_requests`
--

INSERT INTO `unlink_requests` (`id`, `parent_id`, `tenant_id`, `status`, `requested_at`, `processed_at`) VALUES
(1, 12, 9, 'approved', '2026-04-14 06:55:31', '2026-04-14 06:58:50'),
(2, 12, 9, 'rejected', '2026-04-14 07:00:12', '2026-04-14 07:03:05');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager','tenant','parent') NOT NULL,
  `security_question` varchar(255) DEFAULT NULL,
  `security_answer` varchar(255) DEFAULT NULL,
  `status` enum('active','pending','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `role`, `security_question`, `security_answer`, `status`, `created_at`) VALUES
(2, 'yash', 'manager@urbanstay.com', '1234', '$2y$10$Dq89K7j2UKwMtwQCUeZ0OuGrvor3efh.eckkxC73oy.l5rGixDhZa', 'manager', 'What is your favorite book?', 'got', 'active', '2026-04-05 12:29:10'),
(3, 'yashu', 'yash1234@gmail.com', '1234', '$2y$10$Rq/yjNCzAFr1vWrY3uZf2et7pT/W1xdaLgJoxVSzU5JRPn0t/g9I.', 'tenant', 'What is your favorite book?', 'got', 'active', '2026-04-05 12:33:11'),
(4, 'yash', 'yash12@gmail.com', '1234', '$2y$10$UWxPllTFZqzT8qFM75gLlOceIVXN8EuwGwfc33bFuwbtOVMegJ2NK', 'manager', 'What is your favorite book?', 'got', 'active', '2026-04-08 14:37:30'),
(8, 'Super Admin', 'admin@urbanstay.com', '9999999999', '$2y$10$HjJDKfAi6hJUbDUcJ2HsZe/pax7wAchNvRJ.dp86nLRD2.gx4Vs5y', 'admin', 'What is your mother\'s maiden name?', 'admin123', 'active', '2026-04-08 15:22:22'),
(9, 'manu', 'manu123@gmail.com', '1234', '$2y$10$unCCMQRKqWH8qNCdZXWJsuMPLtVGAraCvM7FppciNTvVh2e3pbh/K', 'tenant', 'What is your favorite book?', 'got', 'active', '2026-04-12 12:59:22'),
(10, 'ravi', 'ravi123@gmail.com', '1234', '$2y$10$Lk4oHaU1/8CJky0vMx2klO1G0tPbMgdr3zEDnnLz1uBL.jxllItci', 'tenant', 'What is your favorite book?', 'got', 'active', '2026-04-12 13:25:15'),
(11, 'gowthu', 'gowthu123@gmail.com', '1234', '$2y$10$ryn5oYz1NYz5L0z4WkDAju833PeX2h3CZ9xiV7bQNlp.a2fJ.vYge', 'tenant', 'What is your favorite book?', 'got', 'active', '2026-04-12 14:29:37'),
(12, 'yashwanth', 'yash123@gmail.com', '1234', '$2y$10$akiKaoaAMZWPz6YU4/UklOilfXp.Sr6TPZFnNdh.14ivnIz2QvLx2', 'parent', 'What is your favorite book?', 'got', 'active', '2026-04-14 04:55:37');

-- --------------------------------------------------------

--
-- Table structure for table `vacate_requests`
--

CREATE TABLE `vacate_requests` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vacate_requests`
--

INSERT INTO `vacate_requests` (`id`, `tenant_id`, `booking_id`, `status`, `requested_at`, `approved_at`) VALUES
(1, 3, 1, 'approved', '2026-04-14 07:03:37', '2026-04-14 07:03:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`tenant_id`,`date`),
  ADD KEY `pg_id` (`pg_id`);

--
-- Indexes for table `beds`
--
ALTER TABLE `beds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `bed_id` (`bed_id`),
  ADD KEY `pg_id` (`pg_id`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `pg_id` (`pg_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_feedback` (`tenant_id`,`pg_id`),
  ADD KEY `pg_id` (`pg_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `parent_tenant`
--
ALTER TABLE `parent_tenant`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_parent_tenant` (`parent_id`,`tenant_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `pg_id` (`pg_id`);

--
-- Indexes for table `pgs`
--
ALTER TABLE `pgs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `pg_images`
--
ALTER TABLE `pg_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pg_id` (`pg_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pg_id` (`pg_id`);

--
-- Indexes for table `tenant_details`
--
ALTER TABLE `tenant_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `unlink_requests`
--
ALTER TABLE `unlink_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vacate_requests`
--
ALTER TABLE `vacate_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `beds`
--
ALTER TABLE `beds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `parent_tenant`
--
ALTER TABLE `parent_tenant`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pgs`
--
ALTER TABLE `pgs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pg_images`
--
ALTER TABLE `pg_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tenant_details`
--
ALTER TABLE `tenant_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `unlink_requests`
--
ALTER TABLE `unlink_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `vacate_requests`
--
ALTER TABLE `vacate_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`pg_id`) REFERENCES `pgs` (`id`);

--
-- Constraints for table `beds`
--
ALTER TABLE `beds`
  ADD CONSTRAINT `beds_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`id`),
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`pg_id`) REFERENCES `pgs` (`id`);

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`pg_id`) REFERENCES `pgs` (`id`);

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`pg_id`) REFERENCES `pgs` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `parent_tenant`
--
ALTER TABLE `parent_tenant`
  ADD CONSTRAINT `parent_tenant_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `parent_tenant_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`pg_id`) REFERENCES `pgs` (`id`);

--
-- Constraints for table `pgs`
--
ALTER TABLE `pgs`
  ADD CONSTRAINT `pgs_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pg_images`
--
ALTER TABLE `pg_images`
  ADD CONSTRAINT `pg_images_ibfk_1` FOREIGN KEY (`pg_id`) REFERENCES `pgs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`pg_id`) REFERENCES `pgs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tenant_details`
--
ALTER TABLE `tenant_details`
  ADD CONSTRAINT `tenant_details_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`);

--
-- Constraints for table `unlink_requests`
--
ALTER TABLE `unlink_requests`
  ADD CONSTRAINT `unlink_requests_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `unlink_requests_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `vacate_requests`
--
ALTER TABLE `vacate_requests`
  ADD CONSTRAINT `vacate_requests_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `vacate_requests_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
