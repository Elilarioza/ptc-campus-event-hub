-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 08, 2026 at 03:06 PM
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
-- Database: `ptc_campus_event_hub`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `posted_by` int(11) DEFAULT NULL,
  `status` enum('published','draft') DEFAULT 'published',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `posted_by`, `status`, `created_at`) VALUES
(1, 'MAGANDA AKO', 'WAIT LANGGGGGGGGG', 1, 'draft', '2026-11-06 13:25:43');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `registration_id` int(11) NOT NULL,
  `attendance_status` enum('present','absent') DEFAULT 'absent',
  `check_in_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `category_name`, `description`, `created_at`) VALUES
(1, 'Academic Events', 'Academic programs and learning activities.', '2026-11-03 11:18:07'),
(2, 'Sports Events', 'Sports competitions and campus tournaments.', '2026-11-03 11:18:07'),
(3, 'Cultural Events', 'Cultural performances and celebrations.', '2026-11-03 11:18:07'),
(4, 'Social Events', 'Student gatherings and community activities.', '2026-11-03 11:18:07'),
(5, 'Seminars and Workshops', 'Knowledge-sharing sessions and workshops.', '2026-11-03 11:18:07'),
(6, 'School Celebrations', 'Special occasions and school festivities.', '2026-11-03 11:18:07');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `venue_id` int(11) DEFAULT NULL,
  `event_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `event_image` varchar(255) DEFAULT NULL,
  `registration_deadline` date DEFAULT NULL,
  `maximum_participants` int(11) DEFAULT 100,
  `status` enum('upcoming','ongoing','completed','cancelled') DEFAULT 'upcoming',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `category_id`, `venue_id`, `event_name`, `description`, `event_date`, `start_time`, `end_time`, `event_image`, `registration_deadline`, `maximum_participants`, `status`, `created_by`, `created_at`) VALUES
(1, 6, 1, 'PTC Foundation Day 2026', 'Annual celebration of Pateros Technological College.', '2026-11-09', '12:00:00', '17:00:00', NULL, '2026-11-07', 100, 'upcoming', 1, '2026-11-03 11:54:53'),
(2, 2, 2, 'PTC Basketball Tournament', 'Sample campus showcase for student athletes. This demo event is for planning and can be edited by the administrator at any time.', '2027-01-18', '08:00:00', '12:00:00', 'assets/images/sports-basketball.jpg', '2027-01-08', 80, 'upcoming', 1, '2026-11-03 12:47:20'),
(3, 2, 2, 'Volleyball Championship', 'Sample indoor volleyball competition for students and campus teams. This demo event is editable by the administrator and not officially confirmed.', '2027-02-06', '09:00:00', '13:00:00', 'assets/images/sports-volleyball.jpg', '2027-01-27', 60, 'upcoming', 1, '2026-11-03 12:47:20'),
(4, 2, 2, 'Badminton Competition', 'Sample badminton match for students seeking active participation and campus sports engagement. This is a demo event for presentation purposes.', '2027-03-11', '14:00:00', '17:30:00', 'assets/images/sports-basketball.jpg', '2027-03-01', 40, 'upcoming', 1, '2026-11-03 12:47:20'),
(5, 2, 3, 'PTC Fun Run', 'Sample fun run event for wellness and school pride. This demo event can be modified or removed by the administrator as needed.', '2027-04-02', '06:30:00', '09:00:00', 'assets/images/sports-basketball.jpg', '2027-03-23', 120, 'upcoming', 1, '2026-11-03 12:47:20'),
(6, 2, 2, 'Table Tennis Tournament', 'Sample table tennis tournament designed to highlight student talent, sportsmanship, and healthy competition. Demo content only.', '2027-05-16', '13:00:00', '17:00:00', 'assets/images/sports-volleyball.jpg', '2027-05-06', 32, 'upcoming', 1, '2026-11-03 12:47:20'),
(7, 1, 4, 'Programming Challenge 2027', 'Sample programming contest for students to showcase creativity, coding skills, and problem-solving. Demo event only.', '2027-01-27', '09:00:00', '12:00:00', 'assets/images/academic-programming.jpg', '2027-01-17', 50, 'upcoming', 1, '2026-11-03 12:47:20'),
(8, 5, 5, 'IT and Technology Seminar', 'Sample seminar on current IT trends, tools, and digital innovation. This is a demo activity and may be adjusted by the administrator.', '2027-02-19', '10:00:00', '12:00:00', 'assets/images/academic-seminar.jpg', '2027-02-09', 70, 'upcoming', 1, '2026-11-03 12:47:20'),
(9, 5, 4, 'Web Development Workshop', 'Sample workshop for front-end and back-end development skills. Demo event for presentation and editable by the administrator.', '2027-03-09', '13:00:00', '16:00:00', 'assets/images/academic-programming.jpg', '2027-02-27', 45, 'upcoming', 1, '2026-11-03 12:47:20'),
(10, 1, 1, 'Quiz Bee Competition', 'Sample academic competition encouraging recall, teamwork, and general knowledge. This event is for demonstration and may be edited later.', '2027-04-15', '09:00:00', '11:30:00', 'assets/images/academic-seminar.jpg', '2027-04-05', 55, 'upcoming', 1, '2026-11-03 12:47:20'),
(11, 1, 1, 'Research and Innovation Exhibition', 'Sample student research and innovation showcase. Demo content only, meant for event planning and presentation needs.', '2027-05-24', '10:00:00', '15:00:00', 'assets/images/academic-programming.jpg', '2027-05-14', 100, 'upcoming', 1, '2026-11-03 12:47:20'),
(12, 3, 1, 'Cultural Dance Festival', 'Sample cultural celebration featuring dance performances, artistry, and student pride. Demo event only.', '2027-02-14', '18:00:00', '21:00:00', 'assets/images/cultural-dance.jpg', '2027-02-04', 200, 'upcoming', 1, '2026-11-03 12:47:20'),
(13, 3, 1, 'PTC Talent Showcase', 'Sample talent showcase featuring music, dance, acting, and student creativity. Demo content only, editable by the administrator.', '2027-03-18', '18:30:00', '21:30:00', 'assets/images/cultural-music.jpg', '2027-03-08', 150, 'upcoming', 1, '2026-11-03 12:47:20'),
(14, 3, 3, 'Music and Arts Festival', 'Sample festival featuring creative performances and student expression. This demo event can be updated anytime by the admin.', '2027-04-29', '15:00:00', '19:00:00', 'assets/images/cultural-music.jpg', '2027-04-19', 180, 'upcoming', 1, '2026-11-03 12:47:20'),
(15, 4, 3, 'Student Organization Fair', 'Sample campus fair for student organizations, clubs, and community engagement. Demo event only and not an official commitment.', '2027-05-07', '08:00:00', '12:00:00', 'assets/images/social-workshop.jpg', '2027-04-27', 220, 'upcoming', 1, '2026-11-03 12:47:20'),
(16, 6, 3, 'PTC Foundation Day Celebration', 'Sample foundation day event for school pride, activities, and community celebration. Demo event content for presentation purposes.', '2027-06-20', '08:00:00', '17:00:00', 'assets/images/school-celebration.jpg', '2027-06-10', 250, 'upcoming', 1, '2026-11-03 12:47:20'),
(17, 5, 5, 'Student Leadership Workshop', 'Sample workshop intended to build confidence, communication skills, and leadership among student leaders. Demo event only.', '2027-01-30', '13:00:00', '15:30:00', 'assets/images/social-workshop.jpg', '2027-01-20', 50, 'upcoming', 1, '2026-11-03 12:47:20'),
(18, 5, 5, 'Career Development Seminar', 'Sample seminar focused on career readiness, employability, and workplace preparation for students. Demo content only.', '2027-02-27', '09:00:00', '11:00:00', 'assets/images/academic-seminar.jpg', '2027-02-17', 65, 'upcoming', 1, '2026-11-03 12:47:20'),
(19, 5, 5, 'Resume Writing Workshop', 'Sample workshop to help students improve resumes, portfolios, and confidence in application preparation. Demo event content.', '2027-03-25', '14:00:00', '16:00:00', 'assets/images/social-workshop.jpg', '2027-03-15', 40, 'upcoming', 1, '2026-11-03 12:47:20'),
(20, 5, 5, 'Entrepreneurship Seminar', 'Sample seminar about entrepreneurial thinking, innovation, and student business ideas. Demo event only and editable by admin.', '2027-04-12', '10:00:00', '12:00:00', 'assets/images/academic-seminar.jpg', '2027-04-02', 60, 'upcoming', 1, '2026-11-03 12:47:20'),
(21, 5, 1, 'Mental Wellness Awareness Seminar', 'Sample awareness seminar focused on mental wellness, resilience, and support resources for students. Demo content only.', '2027-06-08', '09:30:00', '11:30:00', 'assets/images/social-workshop.jpg', '2027-05-29', 80, 'upcoming', 1, '2026-11-03 12:47:20');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 'Registration Submitted!', 'Your registration for PTC Basketball Tournament is pending approval. Please wait for the administrator to review your request.', 1, '2026-11-06 13:13:40'),
(2, 2, 'Registration Submitted!', 'Your registration for Programming Challenge 2027 is pending approval. Please wait for the administrator to review your request.', 1, '2026-11-06 13:14:43'),
(3, 2, 'Registration Approved!', 'Your registration for Programming Challenge 2027 has been approved by the administrator. You are now officially registered for this event.', 1, '2026-11-06 13:24:39'),
(4, 2, 'Registration Rejected!', 'Your registration for PTC Basketball Tournament has been rejected by the administrator. Please check other available events and try again.', 1, '2026-11-06 13:24:40'),
(5, 2, 'Registration Submitted!', 'Your registration for IT and Technology Seminar is pending approval. Please wait for the administrator to review your request.', 0, '2026-11-08 13:37:42'),
(6, 2, 'Registration Cancelled!', 'Your pending registration for IT and Technology Seminar has been cancelled.', 0, '2026-11-08 13:39:02');

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registrations`
--

INSERT INTO `registrations` (`id`, `user_id`, `event_id`, `registration_date`, `status`) VALUES
(1, 2, 1, '2026-11-03 12:00:23', 'approved'),
(2, 2, 2, '2026-11-06 13:13:40', 'rejected'),
(3, 2, 7, '2026-11-06 13:14:43', 'approved'),
(4, 2, 8, '2026-11-08 13:37:42', 'cancelled');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `student_number` varchar(50) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','admin') DEFAULT 'student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `student_number`, `email`, `profile_image`, `password`, `role`, `created_at`) VALUES
(1, 'ELIZABETH', 'LARIOZA', '23BSIT-0561', 'elarioza@paterostechnologicalcollege.edu.ph', NULL, '$2y$10$vPhzyV4RCO7Ye0Qd/9LbduMkAwhFYbOwZ8Q.9s0gOunYmFiyZZ.0S', 'admin', '2026-11-03 11:42:59'),
(2, 'ELIZABETH', 'ZUKI', '23BSIT-0001', 'test@gmail.com', 'assets/uploads/profiles/student_2_1d89ccca9800dfc3.jpg', '$2y$10$8/12eXnri3Y/e1ClyaMxu.t6TVIUsw7NxVLYTAbkrvO6SIdBr1upC', 'student', '2026-11-03 11:52:40');

-- --------------------------------------------------------

--
-- Table structure for table `venues`
--

CREATE TABLE `venues` (
  `id` int(11) NOT NULL,
  `venue_name` varchar(150) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `capacity` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venues`
--

INSERT INTO `venues` (`id`, `venue_name`, `location`, `capacity`, `created_at`) VALUES
(1, 'School Auditorium', 'Pateros Technological College', 300, '2026-11-03 11:18:07'),
(2, 'School Gymnasium', 'Pateros Technological College', 500, '2026-11-03 11:18:07'),
(3, 'School Grounds', 'Pateros Technological College', 1000, '2026-11-03 11:18:07'),
(4, 'Computer Laboratory', 'Pateros Technological College', 40, '2026-11-03 11:18:07'),
(5, 'Conference Room', 'Pateros Technological College', 50, '2026-11-03 11:18:07');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `posted_by` (`posted_by`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registration_id` (`registration_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `venue_id` (`venue_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user` (`user_id`),
  ADD KEY `idx_notifications_read` (`is_read`),
  ADD KEY `idx_notifications_user_created` (`user_id`,`created_at`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_registration` (`user_id`,`event_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `student_number` (`student_number`);

--
-- Indexes for table `venues`
--
ALTER TABLE `venues`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `venues`
--
ALTER TABLE `venues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `events_ibfk_2` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `events_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registrations_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
