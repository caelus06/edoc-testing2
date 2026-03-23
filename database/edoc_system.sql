-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 23, 2026 at 11:39 PM
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
-- Database: `edoc_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'FK to users.id — NULL for unauthenticated actions (signup)',
  `action` varchar(10) NOT NULL COMMENT 'INSERT, UPDATE, DELETE, LOGIN, LOGOUT',
  `table_name` varchar(50) NOT NULL COMMENT 'The database table affected',
  `record_id` int(11) DEFAULT NULL COMMENT 'PK of the affected row — NULL for login/logout',
  `details` text DEFAULT NULL COMMENT 'Human-readable context about the action',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Client IP address (supports IPv6)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 21, 'LOGOUT', '0', 21, 'User logged out', '::1', '2026-03-23 21:32:57'),
(2, 14, 'LOGIN', '0', 14, 'User logged in', '::1', '2026-03-23 21:33:04'),
(3, 14, 'LOGOUT', '0', 14, 'User logged out', '::1', '2026-03-23 21:33:11'),
(4, 14, 'INSERT', '0', 20, 'Registrar created request EDOC-2026-3984', '::1', '2026-03-23 21:34:29'),
(5, 21, 'LOGIN', '0', 21, 'User logged in', '::1', '2026-03-23 22:01:31'),
(6, 14, 'INSERT', '0', 20, 'Uploaded file: Valid_id', '::1', '2026-03-23 22:04:10'),
(7, 21, 'INSERT', '0', 21, 'New request: AUTHENTICATION (ABROAD)', '::1', '2026-03-23 22:07:55'),
(8, 14, 'INSERT', '0', 21, 'Uploaded file: Valid_id', '::1', '2026-03-23 22:08:21'),
(9, 14, 'INSERT', '0', 22, 'Registrar created request EDOC-2026-2541', '::1', '2026-03-23 22:09:30'),
(10, 21, 'INSERT', '0', 23, 'New request: TRANSCRIPT OF RECORDS (NOT-GRADUATE)', '::1', '2026-03-23 22:10:31'),
(11, 14, 'INSERT', '0', 24, 'Registrar created request EDOC-2026-3006', '::1', '2026-03-23 22:14:36'),
(12, 21, 'UPDATE', '0', 21, 'Profile updated', '::1', '2026-03-23 22:22:56'),
(13, 21, 'UPDATE', '0', 21, 'Profile updated', '::1', '2026-03-23 22:23:01');

-- --------------------------------------------------------

--
-- Table structure for table `document_process`
--

CREATE TABLE `document_process` (
  `id` int(11) NOT NULL,
  `document_type` varchar(80) NOT NULL,
  `working_days` varchar(30) DEFAULT NULL,
  `process_html` mediumtext NOT NULL,
  `description` text DEFAULT NULL,
  `last_updated` datetime DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_process`
--

INSERT INTO `document_process` (`id`, `document_type`, `working_days`, `process_html`, `description`, `last_updated`, `updated_by`) VALUES
(6, 'TRANSCRIPT OF RECORDS', '7-10 Working Days', '<b>Application Process</b><br><br>\n<b>Purpose:</b><br>\n1. As Grad<br>\n2. Registrar - present valid ID for verification<br>\n3. Registrar - schedule of claiming<br>\n<br>\n<i>Note: For first copy, no payment needed. Second request with corresponding payment.</i><br>\n1 page - Php 350<br>\n2 pages - Php 650<br>\n3 pages - Php 950<br><br>\n<b>For Transfer</b><br>\n1. Registrar<br>\n2. Registrar - Verify Documents/ Records<br>\n3. Guidance - Exit Interview Form<br>\n4. Dean - For Interview & Signature<br>\n5. Cashier - Payment ₱1,500.00<br>\n6. Registrar - Clearance Form (Fill out)<br><br>\n<b>Signatures:</b> Library, SAO, Dean, Cashier<br>\n1. Registrar - Schedule / Release of Certificate of Grades & Transfer Credentials<br>\n2. For Board<br>\n3. Registrar - approval form for Board Exam<br>\n4. Dean - interview and signature<br>\n5. Academic Affairs - final approval by President<br>\n6. Registrar - schedule of claiming', NULL, '2026-03-18 02:06:23', NULL),
(7, 'DIPLOMA', '7-10 Working Days', '<b>Application Process</b><br><br>\r\n1. Registrar - for verification of records<br>\r\n2. Payment Php 1,500 for second copies<br>\r\n3. Schedule of Claiming', NULL, '2026-03-18 02:06:23', NULL),
(8, 'CERTIFICATES', 'Within the Day (Without Cleara', '<b>Application Process</b><br><br>\r\nA. For Cert of Grades / GWA / Units Earned / Graduation (graduate)<br>\r\n1. Registrar - verification of records<br>\r\n2. Cashier - payment (Php 200.00)<br>\r\n3. Registrar - claiming of certificate<br><br>\r\nB. For Cert of Grades / GWA / Units Earned (currently enrolled)<br>\r\n1. Registrar - verification of records<br>\r\n2. Cashier - payment (Php 200.00)<br>\r\n3. Registrar - claiming of certificate<br><br>\r\nC. For Cert of Grades / GWA / Units Earned (not enrolled)<br>\r\n1. Registrar - Verify Documents/ Records<br>\r\n2. Guidance - Exit Interview Form<br>\r\n3. Dean - For Interview & Signature<br>\r\n4. Cashier - Payment ₱1,500.00 and Php 200 for Certificate<br>\r\n5. Registrar - Clearance Form (Fill out)<br><br>\r\n<b>Signatures:</b> Library, SAO, Dean, Cashier<br>\r\n1. Registrar - Schedule / Release of Certificate of Grades & Transfer Credentials', NULL, '2026-03-18 02:06:23', NULL),
(9, 'AUTHENTICATION', 'Within the Day (Authorized Sig', '<b>Application Process</b><br><br>\r\n<b>Local</b><br>\r\n1. Registrar - present document to be authenticated for verification<br>\r\n2. Cashier - payment Php 125 (3 sets of photocopy per document)<br>\r\n3. Releasing of authenticated document<br><br>\r\n<b>For Abroad</b><br>\r\n1. Registrar - present document to be authenticated for verification<br>\r\nA. Graduate (Nursing): Transcript of Records, Diploma, Summary of RLE (Payment Php 350)<br>\r\nB. Graduate (Other Courses): Transcript of Records, Diploma (Payment Php 250)<br>\r\n2. Cashier - payment Php 125 (3 sets of photocopy per document)<br>\r\n3. Releasing of authenticated document<br><br>\r\n<i>Note: For countries Doha and UAE additional Php 200 payment for certificate</i>', NULL, '2026-03-18 02:06:23', NULL),
(10, 'ADMISSION / ENROLLMENT', 'Within the Day', '<b>Application Process</b><br><br>\r\n<b>Enrollment Procedure Old and New Students</b><br>\r\n1. Proceed to enrollment area and submit the following<br>\r\nFor New students & transferees - Proceed to the enrollment area for evaluation and submission of school credentials and enlistment.<br>\r\nFor old students - proceed to V202 for enlistment<br><br>\r\n2. Proceed to cashier\'s office - Pay the admission fee at the cashier\'s office<br><br>\r\n3. Evaluation - Go to your respective Deans or assigned Faculty for evaluation of Grades<br>\r\nV203 SBA, V205 SOH/ESL, V206 SOHS Encoding, V207 SOHS Printing, V208 SIHM & SOC, V209 STTE & SOE<br><br>\r\n4. Encoding of enrolled subjects and printing of white forms<br>\r\n5. Email verification - proceed to V305 for email verification, Adobe registration, ID picture taking, and ID releasing', NULL, '2026-03-18 02:06:23', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reference_no` varchar(20) NOT NULL,
  `document_type` varchar(80) NOT NULL,
  `title_type` varchar(50) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `copies` int(11) NOT NULL DEFAULT 1,
  `status` enum('PENDING','APPROVED','PROCESSING','READY FOR PICKUP','COMPLETED','RETURNED','CANCELLED','RELEASED','VERIFIED') NOT NULL DEFAULT 'PENDING',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requests`
--

INSERT INTO `requests` (`id`, `user_id`, `reference_no`, `document_type`, `title_type`, `purpose`, `copies`, `status`, `created_at`, `updated_at`) VALUES
(19, 21, 'EDOC-2026-7422', 'ADMISSION / ENROLLMENT', 'Transferees', 'transfer', 1, 'PENDING', '2026-03-23 19:42:55', '2026-03-23 19:42:55'),
(21, 21, 'EDOC-2026-6686', 'AUTHENTICATION', 'Abroad', 'uu', 1, 'PENDING', '2026-03-23 22:07:55', '2026-03-23 22:07:55'),
(22, 21, 'EDOC-2026-2541', 'CERTIFICATES', 'Baccalaureate', 'ten yung max', 1, 'PENDING', '2026-03-23 22:09:30', '2026-03-23 22:09:30'),
(24, 21, 'EDOC-2026-3006', 'TRANSCRIPT OF RECORDS', 'Not-Graduate', 'transfer', 1, 'PENDING', '2026-03-23 22:14:36', '2026-03-23 22:14:36');

-- --------------------------------------------------------

--
-- Table structure for table `request_files`
--

CREATE TABLE `request_files` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `requirement_name` varchar(100) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_at` datetime DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `requirement_key` varchar(50) NOT NULL,
  `review_status` enum('PENDING','VERIFIED','RESUBMIT') NOT NULL DEFAULT 'PENDING',
  `resubmit_reason` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `request_files`
--

INSERT INTO `request_files` (`id`, `request_id`, `requirement_name`, `file_path`, `uploaded_at`, `verified_at`, `verified_by`, `requirement_key`, `review_status`, `resubmit_reason`) VALUES
(23, 19, 'Honorable Dismissal', 'uploads/requirements/2ec5ed012c1a9014252e0023_Image_20260222_0001.pdf', '2026-03-23 20:06:36', '2026-03-24 04:06:50', 14, 'honorable_dismissal', 'VERIFIED', NULL),
(25, 21, 'Valid ID', 'uploads/request_files/21/valid_id_e7075cbd3a01dea5.jpg', '2026-03-23 22:08:21', NULL, NULL, 'valid_id', 'PENDING', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `request_logs`
--

CREATE TABLE `request_logs` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `request_logs`
--

INSERT INTO `request_logs` (`id`, `request_id`, `message`, `created_at`) VALUES
(72, 19, 'PENDING - ADMISSION / ENROLLMENT (TRANSFEREES)', '2026-03-23 19:42:55'),
(73, 19, 'Requirement uploaded: Honorable Dismissal', '2026-03-23 19:44:33'),
(74, 19, 'Requirement uploaded: Honorable Dismissal', '2026-03-23 20:06:36'),
(75, 19, 'Registrar Update (Karl Lavarias): Honorable_dismissal has been verified', '2026-03-23 20:06:50'),
(78, 21, 'PENDING - AUTHENTICATION (ABROAD)', '2026-03-23 22:07:55'),
(79, 21, 'Registrar Update: Valid_id uploaded', '2026-03-23 22:08:21'),
(80, 22, 'REGISTRAR CREATED REQUEST (EDOC-2026-2541)', '2026-03-23 22:09:30'),
(82, 24, 'REGISTRAR CREATED REQUEST (EDOC-2026-3006)', '2026-03-23 22:14:36');

-- --------------------------------------------------------

--
-- Table structure for table `requirements_master`
--

CREATE TABLE `requirements_master` (
  `id` int(11) NOT NULL,
  `document_type` varchar(80) NOT NULL,
  `title_type` varchar(50) NOT NULL,
  `req_name` varchar(100) NOT NULL,
  `requirement_key` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requirements_master`
--

INSERT INTO `requirements_master` (`id`, `document_type`, `title_type`, `req_name`, `requirement_key`) VALUES
(15, 'TRANSCRIPT OF RECORDS', 'Graduate', 'Valid ID', 'valid_id'),
(16, 'TRANSCRIPT OF RECORDS', 'Not-Graduate', 'Valid ID', 'valid_id'),
(17, 'TRANSCRIPT OF RECORDS', 'Not-Graduate', 'Form 137 (regular)', 'form_137_(regular)'),
(18, 'TRANSCRIPT OF RECORDS', 'Not-Graduate', 'Transcript (transferee)', 'transcript_(transferee)'),
(19, 'DIPLOMA', 'First Request', 'Valid ID', 'valid_id'),
(20, 'DIPLOMA', 'Second Request', 'Valid ID', 'valid_id'),
(21, 'DIPLOMA', 'Second Request', 'Affidavit of Loss', 'affidavit_of_loss'),
(22, 'DIPLOMA', 'Second Request', 'Affidavit of Mutilation (if damage)', 'affidavit_of_mutilation_(if_damage)'),
(23, 'CERTIFICATES', 'Baccalaureate', 'Valid ID', 'valid_id'),
(24, 'CERTIFICATES', 'Post-Grad', 'Valid ID', 'valid_id'),
(25, 'CERTIFICATES', 'Post-Grad', 'Transfer Credential and Transcript of Records from previous school', 'transfer_credential_and_transcript_of_records_from'),
(26, 'AUTHENTICATION', 'Local', 'Valid ID', 'valid_id'),
(27, 'AUTHENTICATION', 'Local', 'Photocopy document to be authenticated (Transcript / Diploma / Certificate / Summary of RLE)', 'photocopy_document_to_be_authenticated_(transcript'),
(28, 'AUTHENTICATION', 'Abroad', 'Valid ID', 'valid_id'),
(29, 'AUTHENTICATION', 'Abroad', 'Photocopy document to be authenticated (Transcript / Diploma / Certificate / Summary of RLE)', 'photocopy_document_to_be_authenticated_(transcript'),
(30, 'ADMISSION / ENROLLMENT', 'New Student', 'F-13B (Report Card)', 'f_13b_(report_card)'),
(31, 'ADMISSION / ENROLLMENT', 'New Student', 'Certificate of Good Moral Character', 'certificate_of_good_moral_character'),
(32, 'ADMISSION / ENROLLMENT', 'New Student', 'Certificate of Graduation with Honors (For Honor Students)', 'certificate_of_graduation_with_honors_(for_honor_s'),
(33, 'ADMISSION / ENROLLMENT', 'New Student', 'Birth Certificate issued by PSA on security paper', 'birth_certificate_issued_by_psa_on_security_paper'),
(34, 'ADMISSION / ENROLLMENT', 'Transferees', 'Honorable Dismissal', 'honorable_dismissal'),
(35, 'ADMISSION / ENROLLMENT', 'Transferees', 'True Copy of Grades (previous school)', 'true_copy_of_grades_(previous_school)'),
(36, 'ADMISSION / ENROLLMENT', 'Transferees', 'Certificate of Good Moral Character', 'certificate_of_good_moral_character'),
(37, 'ADMISSION / ENROLLMENT', 'Transferees', 'Other documents as may be required by the university', 'other_documents_as_may_be_required_by_the_universi');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('USER','REGISTRAR','MIS') DEFAULT 'USER',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `gender` varchar(10) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `course` varchar(80) DEFAULT NULL,
  `major` varchar(80) DEFAULT NULL,
  `year_graduated` varchar(10) DEFAULT NULL,
  `verification_status` enum('PENDING','VERIFIED','REJECTED') DEFAULT 'PENDING',
  `id_front_path` varchar(255) DEFAULT NULL,
  `id_back_path` varchar(255) DEFAULT NULL,
  `face_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `middle_name`, `last_name`, `suffix`, `student_id`, `full_name`, `email`, `password`, `role`, `created_at`, `gender`, `address`, `contact_number`, `course`, `major`, `year_graduated`, `verification_status`, `id_front_path`, `id_back_path`, `face_path`) VALUES
(0, 'FIRST', 'MIDDLE', 'LAST', 'T', '00-0000-0000', '', '0@gmail.com', '$2y$10$wy7q5qiz0na/gxbv/AJA7.9F7wT9WPlotO9BKIFtIQnxJDauzZ3Ai', 'MIS', '2026-03-15 19:15:20', 'Male', 'ASD', '0921', 'BS COMPUTER SCIENCE', 'DATA SCIENCE', '2023', 'VERIFIED', '', '', ''),
(9, 'Juan', 'Carlos', 'Dela Cruz', 'Jr.', '2022-1001', '', '1@gmail.com', '$2y$10$mUPT0rGvj.Fa8esckeLgyuoe9TiIFk2J.2f3epg6CTzvVCmR5d6Hm', 'REGISTRAR', '2026-03-17 20:01:41', 'Male', 'Quezon City', '9123456781', 'BSCS DS', 'Data Science', '2026', 'VERIFIED', NULL, NULL, NULL),
(10, 'Maria', 'Clara', 'Santos', 'N/A', '2021-2045', '', '2@gmail.com', '$2y$10$Uur1/WLOEZBrshG83WZPxuUibHmpBUfCXHScqb36etTlE8AUzcqGS', 'REGISTRAR', '2026-03-17 20:01:41', 'Female', 'Manila', '9123456782', 'BSN', 'N/A', '2025', 'VERIFIED', NULL, NULL, NULL),
(11, 'Roberto', 'Paolo', 'Reyes', 'III', '2020-3089', '', '3@gmail.com', '$2y$10$DnsunBBxXoWj26kLGXmPdu4P76m2hB0ypl.ZeEGHBun2o625jaH9.', 'REGISTRAR', '2026-03-17 20:01:41', 'Male', 'Pasig City', '9123456783', 'BSCE', 'N/A', '2024', 'VERIFIED', NULL, NULL, NULL),
(12, 'Elena', 'Jane', 'Bautista', 'N/A', '2023-4012', '', '4@gmail.com', '$2y$10$0xny55Qk2nEDYHfTmJkXEeQ545nUahMoAs15DvL.iHsFQFp8O7YeC', 'REGISTRAR', '2026-03-17 20:01:41', 'Female', 'Taguig', '9123456784', 'BSED MATH', 'Mathematics', '2027', 'VERIFIED', NULL, NULL, NULL),
(13, 'Antonio', 'Luis', 'Gomez', 'N/A', '2019-5067', '', '5@gmail.com', '$2y$10$JSf6geGncQonjJILpEKFN.gimrK6w13wXc1BDP4WmWmnHU2BY4dvO', 'REGISTRAR', '2026-03-17 20:01:41', 'Male', 'Makati', '9123456785', 'BS PSYCH', 'N/A', '2023', 'VERIFIED', NULL, NULL, NULL),
(14, 'Karl', 'Darmen', 'Lavarias', 'N/A', '2019-5068', '', 'registrar@gmail.com', '$2y$10$/WE4ssZushfaEHDUjZcJE.IidpXZglGK.zE64cKxv0Es6Hr9ke0aa', 'REGISTRAR', '2026-03-17 20:01:41', 'Male', 'Urdaneta City', '909654234', 'BS Computer Science', 'Internet Engineering', '2026', 'VERIFIED', NULL, NULL, NULL),
(15, 'Carmen', 'Sofia', 'Andres', 'N/A', '2012-2132', '', 'mis@gmail.com', '$2y$10$zXOvWg24EH.MvYoH24BLCONmg9dSupjh7bsK/5uPAk0SnnttfFkp2', 'MIS', '2026-03-17 20:01:41', 'Female', 'Dagupan City', '9232323234', 'BSN', 'N/A', '2003', 'VERIFIED', NULL, NULL, NULL),
(16, 'Carlo', 'Flores', 'Bautista', 'III', '23-1005-2005', '', 'carlo.bautista@gmail.com', '$2y$10$/gt1/zllDg8GAsWeqBcGqedoJ82fLNF9Yakh4ZSo9DvQuAv3pARJq', 'USER', '2026-03-17 20:01:41', 'Male', '9 Marcos Highway, Baguio City', '0916 789 0123', 'BS Civil Engineering', 'N/A', 'N/A', 'PENDING', NULL, NULL, NULL),
(17, 'Maria', 'Lopez', 'Reyes', '', '23-1002-2002', '', 'maria.reyes@gmail.com', '$2y$10$ek/eUBYo1nxRCPC.mxZTqeM.2ed2/9n2p/0OKkFBzPc.crtcz0nse', 'USER', '2026-03-17 20:01:41', 'Female', '45 Mabini St., Barangay San Roque, Baguio City', '0913 456 7890', 'BS Education Major in English', 'N/A', 'N/A', 'PENDING', NULL, NULL, NULL),
(18, 'John', 'Cruz', 'Santos', '', '23-3001-4001', '', 'john.santos@gmail.com', '$2y$10$ggCFiVdtHwKVDHsTCBnXH.QUfFuKrkmtBYY3kyPuYolRlMGYrX4AK', 'USER', '2026-03-17 20:01:41', 'Male', '12 Rizal St., Dagupan City, Pangasinan', '0912 210 1111', 'BS Business Administration Major in Marketing Management', 'N/A', 'N/A', 'REJECTED', NULL, NULL, NULL),
(19, 'Mary', 'Lopez', 'Garcia', '', '23-3002-4002', '', 'mary.garcia@gmail.com', '$2y$10$2x5fajdWqXcRcwvRlbwRfeNbrEx0MzXOzqCAuybejwtQCPfiMj.JG', 'USER', '2026-03-17 20:01:42', 'Female', '45 Perez Blvd., Dagupan City, Pangasinan', '0913 320 2222', 'BS Nursing', 'N/A', 'N/A', 'VERIFIED', NULL, NULL, NULL),
(20, 'Mark', 'Ramos', 'Reyes', 'Jr.', '23-3003-4003', '', 'mark.reyes@gmail.com', '$2y$10$0J.CeMat5OUBKn5AuX6F2O/7Ex3HuqCShvgC1udUMorqO1ep5scIK', 'USER', '2026-03-17 20:01:42', 'Male', '78 Bonuan, Dagupan City, Pangasinan', '0914 430 3333', 'BS Civil Engineering', 'N/A', 'N/A', 'VERIFIED', NULL, NULL, NULL),
(21, 'Anna', 'Santos', 'Cruz', 'N/A', '23-3004-4004', '', 'anna.cruz@gmail.com', '$2y$10$fF4UeJtjSWE2xu.kleZ50eD43TcRGYaPUQZh65ifcyZtrCdl1VI1y', 'USER', '2026-03-17 20:01:42', 'Female', '9 Lingayen, Pangasinan', '0915 540 4444', 'BS Education Major in English', 'N/A', '2025', 'VERIFIED', NULL, NULL, NULL),
(22, 'Paul', 'Flores', 'Mendoza', '', '23-3005-4005', '', 'paul.mendoza@gmail.com', '$2y$10$jj2b1.DLGds7A2a4RnB4XOQiyAiNYJjOt86eIb/jjY7asBhFPa0pe', 'USER', '2026-03-17 20:01:42', 'Male', '33 San Carlos City, Pangasinan', '0916 650 5555', 'BS Business Administration Major in Marketing Management', 'N/A', 'N/A', 'VERIFIED', NULL, NULL, NULL),
(23, 'Jane', 'Santos', 'Diaz', '', '23-3006-4006', '', 'jane.diaz@gmail.com', '$2y$10$hFzGTC1qYvg2iqr1CVqXPO1Ar36frvOJpwSnMGqEJcIco6X56AZxC', 'USER', '2026-03-17 20:01:42', 'Female', '67 Urdaneta City, Pangasinan', '0917 760 6666', 'BS Psychology', 'N/A', 'N/A', 'VERIFIED', NULL, NULL, NULL),
(24, 'Kevin', '', 'Torres', '', '23-3007-4007', '', 'kevin.torres@gmail.com', '$2y$10$6ZGntBnnCALqXpybkGQOm.ZU2NTm5LfEguVzyG/imCQe7At1q1Due', 'USER', '2026-03-17 20:01:42', 'Male', '21 Alaminos City, Pangasinan', '0918 870 7777', 'BS Computer Engineering', 'N/A', 'N/A', 'VERIFIED', NULL, NULL, NULL),
(25, 'Grace', 'Biala', 'Rivera', '', '23-3008-4008', '', 'grace.rivera@gmail.com', '$2y$10$4zEgvORO.ydHMv04MmJpWO4G3OobgdUwZhDcDZBUdQSRQLQ5RMPs.', 'USER', '2026-03-17 20:01:42', 'Female', '14 Binmaley, Pangasinan', '0919 980 8888', 'BS Hospitality Management', 'N/A', 'N/A', 'VERIFIED', NULL, NULL, NULL),
(26, 'Jejomar', 'Batongbakal', 'Lim', 'III', '23-3009-4009', '', 'jejomar.lim@gmail.com', '$2y$10$G8qPlN53ZTMX9Sg438dsCu5mf4NF84gKIATsSB7adUcreCUTUAAr2', 'USER', '2026-03-17 20:01:42', 'Male', '56 Calasiao, Pangasinan', '0920 111 9999', 'BS Mechanical Engineering', 'N/A', 'N/A', 'VERIFIED', NULL, NULL, NULL),
(27, 'Diwata', 'Tan', 'Dalisay', '', '23-3010-4010', '', 'diwata.dalisay@gmail.com', '$2y$10$JZmhKkWRw1Ime5aZ75VukOeMUevb0YHcPYC/YmrsDERgtLSiIvrha', 'USER', '2026-03-17 20:01:42', 'Female', '8 Mangaldan, Pangasinan', '0921 222 0000', 'AB Communication', 'N/A', 'N/A', 'VERIFIED', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_notif_seen`
--

CREATE TABLE `user_notif_seen` (
  `user_id` int(11) NOT NULL,
  `last_seen_at` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_notif_seen`
--

INSERT INTO `user_notif_seen` (`user_id`, `last_seen_at`, `updated_at`) VALUES
(21, '2026-03-24 06:18:24', '2026-03-24 06:18:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_action` (`action`),
  ADD KEY `idx_audit_table` (`table_name`),
  ADD KEY `idx_audit_date` (`created_at`);

--
-- Indexes for table `document_process`
--
ALTER TABLE `document_process`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `document_type` (`document_type`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_no` (`reference_no`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `request_files`
--
ALTER TABLE `request_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `request_logs`
--
ALTER TABLE `request_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `requirements_master`
--
ALTER TABLE `requirements_master`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Indexes for table `user_notif_seen`
--
ALTER TABLE `user_notif_seen`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `document_process`
--
ALTER TABLE `document_process`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `request_files`
--
ALTER TABLE `request_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `request_logs`
--
ALTER TABLE `request_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `requirements_master`
--
ALTER TABLE `requirements_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `request_files`
--
ALTER TABLE `request_files`
  ADD CONSTRAINT `request_files_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `request_logs`
--
ALTER TABLE `request_logs`
  ADD CONSTRAINT `request_logs_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
