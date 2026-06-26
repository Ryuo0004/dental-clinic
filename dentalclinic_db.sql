-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 26, 2026 at 12:51 PM
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
-- Database: `dentalclinic_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_admin`
--

CREATE TABLE `tbl_admin` (
  `Admin_Id` int(11) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Name` varchar(255) DEFAULT NULL,
  `Password_hash` varchar(255) NOT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_admin`
--

INSERT INTO `tbl_admin` (`Admin_Id`, `Email`, `Name`, `Password_hash`, `Created_At`) VALUES
(1, 'Admin@gmail.com', 'Clinic Admin', '$2y$10$pKPdcFrUkyDNt8wroWFYCujUzyPMHg63GJ.M4zTKoVTKIQCsRtHni', '2025-09-27 14:17:53');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_appointments`
--

CREATE TABLE `tbl_appointments` (
  `Appointment_Id` int(11) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Patient_Id` int(11) DEFAULT NULL,
  `Dentist_Id` int(11) NOT NULL,
  `Procedure` varchar(255) NOT NULL,
  `Date` date NOT NULL,
  `Time` time NOT NULL,
  `Room` varchar(64) DEFAULT NULL,
  `Admin_Notes` text DEFAULT NULL,
  `Status` enum('Pending','Booked','Confirmed','Ongoing','Finished','Cancelled') NOT NULL DEFAULT 'Pending',
  `has_multiple_treatments` tinyint(1) NOT NULL DEFAULT 0,
  `Reschedule_Count` int(11) NOT NULL DEFAULT 0,
  `Updated_At` timestamp NULL DEFAULT NULL,
  `Duration` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_appointments`
--

INSERT INTO `tbl_appointments` (`Appointment_Id`, `Email`, `Patient_Id`, `Dentist_Id`, `Procedure`, `Date`, `Time`, `Room`, `Admin_Notes`, `Status`, `has_multiple_treatments`, `Reschedule_Count`, `Updated_At`, `Duration`) VALUES
(1, 'walkin+ryuoo+09221212212@clinic.local', 2, 1, 'Teeth Whitening', '2025-10-15', '07:00:00', '', 'bye', 'Finished', 0, 0, '2025-10-14 19:11:48', NULL),
(2, 'nightmarefox50@gmail.com', 1, 4, 'Tooth Restoration', '2025-10-15', '07:00:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(3, 'nightmarefox50@gmail.com', 1, 1, 'Oral Prophylaxis (Cleaning)', '2025-10-15', '10:15:00', NULL, NULL, 'Finished', 0, 0, '2025-10-15 02:43:32', NULL),
(4, 'walkin+summertime+09888323232@clinic.local', 3, 3, 'Oral Prophylaxis (Cleaning)', '2025-10-15', '07:25:00', '', 'Walk-in | Name: summer time | Contact: 09888323232 | Address: cattipunan street', 'Cancelled', 0, 0, '2025-10-15 04:10:11', NULL),
(5, 'walkin+summertime+09888323232@clinic.local', 3, 2, 'Brace Adjustment', '2025-10-15', '13:10:00', '', 'Walk-in | Name: summer time | Contact: 09888323232 | Address: cattipunan street', 'Finished', 0, 0, '2025-10-15 04:10:28', NULL),
(6, 'walkin1760536750_72298e05@clinic.local', 4, 1, 'Check-Up', '2025-10-16', '08:00:00', '', '', 'Cancelled', 0, 0, '2025-10-16 01:34:14', NULL),
(7, 'walkin1760579417_904e552c@clinic.local', 5, 4, 'Check-Up', '2025-10-16', '10:00:00', '', '', 'Finished', 0, 0, '2025-10-16 01:50:48', NULL),
(8, 'nightmarefox50@gmail.com', 1, 3, 'Oral Prophylaxis (Cleaning), Tooth Restoration, Tooth Extraction, Oral Surgery, Dentures, Panoramic X-Ray (X-Ray), Full Braces, Upper Braces, Lower Braces, Brace Adjustment, Teeth Whitening, Check-Up', '2025-10-17', '07:40:00', NULL, NULL, 'Finished', 0, 0, '2025-10-16 04:33:58', NULL),
(9, 'gwc.manuel@gmail.com', 6, 4, '', '2025-10-16', '15:05:00', NULL, NULL, 'Cancelled', 0, 0, '2025-10-16 07:06:55', NULL),
(10, 'gwc.manuel@gmail.com', 6, 4, '', '2025-10-20', '07:00:00', NULL, NULL, 'Cancelled', 0, 0, '2025-10-16 07:07:05', NULL),
(11, 'gwc.manuel@gmail.com', 6, 3, 'Tooth Extraction, Upper Braces, Lower Braces, Check-Up', '2025-10-20', '07:00:00', NULL, NULL, 'Cancelled', 0, 0, '2025-10-16 07:08:30', NULL),
(12, 'gwc.manuel@gmail.com', 6, 1, 'Tooth Restoration, Tooth Extraction', '2025-10-21', '16:00:00', NULL, NULL, 'Finished', 0, 0, '2025-10-17 15:30:57', NULL),
(13, 'walkin1760599037_a10ed8c1@clinic.local', 7, 1, 'Oral Prophylaxis (Cleaning)', '2025-10-29', '10:15:00', '', '', 'Finished', 0, 0, '2025-10-17 15:31:03', NULL),
(14, 'nightmarefox50@gmail.com', 1, 3, '', '2025-10-21', '07:55:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(15, 'nightmarefox50@gmail.com', 1, 2, 'Tooth Restoration', '2025-10-23', '07:40:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(16, 'nightmarefox50@gmail.com', 1, 2, 'Tooth Restoration', '2025-10-29', '07:40:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(17, 'nightmarefox50@gmail.com', 1, 1, 'Panoramic X-Ray (X-Ray)', '2025-10-28', '07:20:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(18, 'nightmarefox50@gmail.com', 1, 2, 'Oral Surgery', '2025-10-21', '07:45:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(19, 'nightmarefox50@gmail.com', 1, 2, 'Check-Up', '2025-10-21', '18:55:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(20, 'nightmarefox50@gmail.com', 1, 1, 'Tooth Restoration', '2025-10-19', '07:00:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(21, 'nightmarefox50@gmail.com', 1, 1, 'Brace Adjustment', '2025-10-28', '07:20:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(22, 'nightmarefox50@gmail.com', 1, 1, 'Tooth Restoration', '2025-10-28', '07:45:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(23, 'nightmarefox50@gmail.com', 1, 3, 'Panoramic X-Ray (X-Ray)', '2025-10-21', '07:40:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(24, 'nightmarefox50@gmail.com', 1, 2, 'Tooth Restoration', '2025-10-21', '07:40:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(25, 'nightmarefox50@gmail.com', 1, 3, 'Panoramic X-Ray (X-Ray)', '2025-10-27', '07:45:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(26, 'nightmarefox50@gmail.com', 1, 4, 'Teeth Whitening', '2025-10-20', '07:45:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(27, 'nightmarefox50@gmail.com', 1, 3, 'Tooth Restoration', '2025-10-20', '07:40:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(28, 'nightmarefox50@gmail.com', 1, 3, 'Tooth Restoration', '2025-10-26', '07:40:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(29, 'nightmarefox50@gmail.com', 1, 3, 'Brace Adjustment', '2025-10-27', '07:40:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(30, 'nightmarefox50@gmail.com', 1, 4, 'Oral Prophylaxis (Cleaning)', '2025-10-27', '07:20:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(31, 'nightmarefox50@gmail.com', 1, 4, 'Tooth Restoration', '2025-10-20', '07:40:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(32, 'nightmarefox50@gmail.com', 1, 3, 'Panoramic X-Ray (X-Ray)', '2025-10-27', '07:45:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(33, 'fme090909@gmail.com', 8, 1, 'Brace Adjustment', '2025-10-19', '08:40:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(34, 'fme090909@gmail.com', 8, 3, 'Tooth Restoration', '2025-10-19', '07:40:00', NULL, NULL, 'Cancelled', 0, 0, '2025-10-18 11:46:59', NULL),
(35, 'fme090909@gmail.com', 8, 3, 'Brace Adjustment', '2025-10-19', '09:40:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(36, 'fme090909@gmail.com', 8, 2, 'Tooth Extraction', '2025-10-19', '10:30:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(37, 'fme090909@gmail.com', 8, 4, 'Brace Adjustment', '2025-10-27', '07:25:00', NULL, NULL, 'Finished', 0, 0, '2025-10-18 12:01:14', NULL),
(38, 'nightmarefox50@gmail.com', 1, 4, 'Oral Prophylaxis (Cleaning), Panoramic X-Ray (X-Ray), Brace Adjustment', '2025-10-28', '07:20:00', NULL, NULL, 'Finished', 0, 0, '2025-10-18 12:49:59', NULL),
(39, 'fme090909@gmail.com', 8, 2, 'Oral Prophylaxis (Cleaning), Brace Adjustment', '2025-10-27', '07:40:00', NULL, NULL, 'Cancelled', 0, 0, '2025-10-18 13:06:52', NULL),
(40, 'nightmarefox50@gmail.com', 1, 1, 'Brace Adjustment', '2025-10-19', '16:35:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(41, 'nightmarefox50@gmail.com', 1, 3, 'Panoramic X-Ray (X-Ray)', '2025-10-19', '17:00:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(42, 'nightmarefox50@gmail.com', 1, 1, 'Tooth Restoration', '2025-10-20', '09:45:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(43, 'nightmarefox50@gmail.com', 1, 2, 'Tooth Restoration', '2025-10-19', '17:25:00', NULL, NULL, 'Finished', 0, 0, '2025-10-19 14:39:41', NULL),
(44, 'nightmarefox50@gmail.com', 1, 3, 'Oral Prophylaxis (Cleaning)', '2025-10-20', '18:45:00', NULL, NULL, 'Finished', 0, 0, '2025-10-20 10:57:43', NULL),
(45, 'nightmarefox50@gmail.com', 1, 1, 'Tooth Restoration', '2025-10-21', '18:50:00', NULL, NULL, 'Finished', 0, 0, '2025-10-21 12:34:22', NULL),
(46, 'sber33690@gmail.com', 9, 1, 'Full Braces', '2025-10-21', '18:55:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(47, 'sber33690@gmail.com', 9, 4, 'Panoramic X-Ray (X-Ray)', '2025-10-21', '18:50:00', NULL, NULL, 'Cancelled', 0, 0, NULL, NULL),
(48, 'sber33690@gmail.com', 9, 4, 'Oral Prophylaxis (Cleaning)', '2025-10-21', '07:00:00', NULL, NULL, 'Finished', 0, 0, '2025-10-21 12:34:18', NULL),
(49, 'ryuonoou@gmail.com', 10, 3, 'Tooth Restoration', '2025-10-22', '10:05:00', NULL, NULL, 'Cancelled', 0, 0, '2025-10-22 14:02:15', NULL),
(50, 'ryuonoou@gmail.com', 10, 4, 'Panoramic X-Ray (X-Ray)', '2025-10-22', '07:45:00', NULL, NULL, 'Cancelled', 0, 0, '2025-10-22 14:02:05', NULL),
(51, 'walkin1761051737_f51bfd58@clinic.local', 11, 4, 'Brace Adjustment', '2025-10-21', '07:25:00', '', '', 'Finished', 0, 0, '2025-10-27 10:49:53', NULL),
(52, 'walkin1761052052_f4d0b135@clinic.local', 12, 4, 'Check-Up', '2025-10-22', '07:10:00', '', '', 'Cancelled', 0, 0, '2025-10-22 14:02:04', NULL),
(53, 'walkin1761052083_84f26957@clinic.local', 13, 4, 'Brace Adjustment', '2025-10-22', '07:20:00', '', '', 'Cancelled', 0, 0, '2025-10-22 14:02:05', NULL),
(54, 'nightmarefox50@gmail.com', 1, 3, 'Panoramic X-Ray (X-Ray)', '2025-10-23', '15:30:00', NULL, NULL, 'Cancelled', 0, 0, NULL, 15),
(55, 'nightmarefox50@gmail.com', 1, 3, 'Oral Prophylaxis (Cleaning), Tooth Restoration, Tooth Extraction, Oral Surgery, Dentures, Panoramic X-Ray (X-Ray), Full Braces, Upper Braces, Lower Braces, Brace Adjustment, Teeth Whitening, Check-Up', '2025-10-24', '07:00:00', NULL, NULL, 'Cancelled', 0, 0, '2025-10-22 14:20:46', 625),
(56, 'ryuonoou@gmail.com', 10, 2, 'Brace Adjustment', '2025-10-23', '07:05:00', NULL, NULL, 'Finished', 0, 0, '2025-10-27 10:50:23', 30),
(57, 'nightmarefox50@gmail.com', 1, 1, 'Oral Prophylaxis (Cleaning), Tooth Restoration, Tooth Extraction, Oral Surgery, Dentures, Panoramic X-Ray (X-Ray), Full Braces, Upper Braces, Lower Braces, Brace Adjustment, Teeth Whitening, Check-Up', '2025-10-25', '08:00:00', NULL, NULL, 'Cancelled', 0, 0, '2025-10-22 15:00:05', 625),
(58, 'nightmarefox50@gmail.com', 1, 2, 'Tooth Extraction', '2025-10-28', '07:05:00', NULL, NULL, 'Cancelled', 0, 0, '2025-10-27 15:21:22', 10),
(59, 'nightmarefox50@gmail.com', 1, 1, 'Tooth Extraction', '2025-10-28', '07:10:00', NULL, NULL, 'Cancelled', 0, 0, '2025-10-27 15:30:28', 10),
(60, 'nightmarefox50@gmail.com', 1, 3, 'Tooth Extraction', '2025-10-28', '07:15:00', NULL, NULL, 'Cancelled', 0, 0, '2025-10-27 15:31:51', 10),
(61, 'nightmarefox50@gmail.com', 1, 3, 'Oral Prophylaxis (Cleaning)', '2025-10-30', '07:50:00', NULL, NULL, 'Finished', 0, 0, '2025-10-27 15:33:57', 45),
(62, 'nightmarefox50@gmail.com', 1, 4, 'Oral Prophylaxis (Cleaning)', '2025-11-06', '07:05:00', NULL, NULL, 'Finished', 0, 0, '2025-11-05 13:59:16', 45),
(63, 'nightmarefox50@gmail.com', 1, 4, 'Upper Braces', '2025-11-10', '07:00:00', NULL, NULL, 'Cancelled', 0, 0, NULL, 90),
(64, 'nightmarefox50@gmail.com', 1, 4, 'Upper Braces', '2025-11-12', '07:00:00', NULL, NULL, 'Cancelled', 0, 0, NULL, 90),
(65, 'nightmarefox50@gmail.com', 1, 1, 'Panoramic X-Ray (X-Ray)', '2025-11-07', '17:15:00', NULL, NULL, 'Cancelled', 0, 0, '2025-11-07 00:31:13', 15),
(66, 'nightmarefox50@gmail.com', 1, 4, 'Oral Surgery', '2025-11-07', '08:55:00', NULL, NULL, 'Finished', 0, 0, '2025-11-07 00:52:03', 60),
(67, 'nightmarefox50@gmail.com', 1, 4, 'Oral Prophylaxis (Cleaning)', '2025-11-18', '07:05:00', NULL, NULL, 'Cancelled', 0, 0, '2025-11-17 12:10:18', 45),
(68, 'fme090909@gmail.com', 8, 1, 'Oral Prophylaxis (Cleaning)', '2025-11-18', '07:00:00', NULL, NULL, 'Cancelled', 0, 0, NULL, 45),
(69, 'fme090909@gmail.com', 8, 1, 'Oral Prophylaxis (Cleaning)', '2025-11-18', '07:00:00', NULL, NULL, 'Cancelled', 0, 0, NULL, 45),
(70, 'fme090909@gmail.com', 8, 4, 'Oral Prophylaxis (Cleaning)', '2025-11-18', '07:50:00', NULL, NULL, 'Cancelled', 0, 0, NULL, 45),
(71, 'fme090909@gmail.com', 8, 4, 'Oral Prophylaxis (Cleaning)', '2025-11-18', '07:00:00', NULL, NULL, 'Cancelled', 0, 0, '2025-11-17 12:25:13', 45),
(72, 'nightmarefox50@gmail.com', 1, 1, 'Oral Prophylaxis (Cleaning)', '2025-11-18', '07:05:00', NULL, NULL, 'Cancelled', 0, 0, NULL, 45),
(73, 'nightmarefox50@gmail.com', 1, 1, 'Oral Prophylaxis (Cleaning)', '2025-11-18', '07:00:00', NULL, NULL, 'Cancelled', 0, 0, NULL, 45),
(74, 'nightmarefox50@gmail.com', 1, 1, 'Oral Prophylaxis (Cleaning)', '2025-11-19', '07:00:00', NULL, NULL, 'Cancelled', 0, 0, '2025-11-17 12:26:01', 45),
(75, 'nightmarefox50@gmail.com', 1, 2, 'Oral Prophylaxis (Cleaning)', '2025-11-18', '07:00:00', NULL, NULL, 'Cancelled', 0, 0, NULL, 45),
(76, 'nightmarefox50@gmail.com', 1, 2, 'Oral Prophylaxis (Cleaning)', '2025-11-18', '07:00:00', NULL, NULL, 'Cancelled', 0, 0, NULL, 45),
(77, 'nightmarefox50@gmail.com', 1, 2, 'Oral Prophylaxis (Cleaning)', '2025-11-18', '07:00:00', NULL, NULL, 'Cancelled', 0, 0, NULL, 45),
(78, 'nightmarefox50@gmail.com', 1, 2, 'Oral Prophylaxis (Cleaning), Tooth Restoration, Tooth Extraction, Oral Surgery, Dentures, Panoramic X-Ray (X-Ray), Full Braces, Upper Braces, Lower Braces, Brace Adjustment, Teeth Whitening, Check-Up', '2025-11-18', '07:00:00', NULL, NULL, 'Cancelled', 0, 0, NULL, 625),
(79, 'walkin1763383770_7189e278@clinic.local', 14, 2, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', '2025-11-23', '07:00:00', '', '', 'Cancelled', 0, 0, '2025-11-22 10:46:08', 585),
(80, 'walkin1763807414_2ccff332@clinic.local', 15, 4, 'Brace Adjustment, Tooth Extraction', '2025-11-23', '07:00:00', '', '', 'Cancelled', 0, 0, '2025-11-22 10:46:58', 60),
(81, 'walkin1763383770_7189e278@clinic.local', 14, 4, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', '2025-11-24', '07:00:00', '', '', 'Cancelled', 0, 0, '2025-11-23 13:34:56', 585),
(82, 'nightmarefox50@gmail.com', 1, 4, 'Oral Prophylaxis (Cleaning), Tooth Restoration, Tooth Extraction, Oral Surgery, Dentures, Panoramic X-Ray (X-Ray), Full Braces, Upper Braces, Lower Braces, Brace Adjustment, Teeth Whitening, Check-Up', '2025-11-25', '07:00:00', NULL, NULL, 'Cancelled', 0, 0, NULL, 625),
(83, 'nightmarefox50@gmail.com', 1, 2, 'Full Braces', '2025-11-26', '08:00:00', NULL, NULL, 'Cancelled', 0, 0, '2025-11-24 06:53:41', 120),
(84, 'walkin1763383770_7189e278@clinic.local', 14, 1, 'Full Braces', '2025-11-26', '08:00:00', '', '', 'Cancelled', 0, 0, '2025-11-24 06:53:13', 90),
(85, 'walkin1763383770_7189e278@clinic.local', 14, 2, 'Full Braces', '2025-11-26', '08:00:00', '', '', 'Cancelled', 0, 0, '2025-11-24 06:56:17', 90),
(86, 'nightmarefox50@gmail.com', 1, 2, 'Full Braces', '2025-11-27', '08:00:00', NULL, NULL, 'Cancelled', 0, 0, '2025-11-24 08:45:44', 120),
(87, 'walkin1763383770_7189e278@clinic.local', 14, 2, 'Full Braces', '2025-11-27', '10:00:00', '', '', 'Cancelled', 0, 0, '2025-11-24 08:45:43', 90),
(88, 'walkin1763974193_d3a67736@clinic.local', 16, 4, 'Teeth Whitening', '2025-11-24', '16:50:00', '', '', 'Finished', 0, 0, '2025-11-24 11:59:34', 90),
(89, 'walkin1763383770_7189e278@clinic.local', 14, 1, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', '2025-11-25', '07:00:00', '', '', 'Finished', 0, 0, '2025-11-24 11:59:37', 585),
(90, 'walkin1763974306_b2298ccd@clinic.local', 17, 4, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', '2025-11-25', '07:00:00', '', '', 'Finished', 0, 0, '2025-11-24 11:59:38', 585),
(91, 'walkin1763383770_7189e278@clinic.local', 14, 1, 'Upper Braces', '2025-11-24', '17:05:00', '', '', 'Finished', 0, 0, '2025-11-24 11:59:36', 60),
(92, 'walkin1763985601_cb0ae58f@clinic.local', 18, 4, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', '2025-11-28', '07:00:00', '', '', 'Cancelled', 0, 0, '2025-11-24 14:23:59', 585),
(93, 'walkin1763985601_cb0ae58f@clinic.local', 18, 1, 'Brace Adjustment', '2025-11-28', '07:00:00', '', '', 'Cancelled', 0, 0, '2025-11-24 12:04:29', 30),
(94, 'walkin1763985884_eb855303@clinic.local', 19, 4, 'Brace Adjustment', '2025-11-29', '07:00:00', '', '', 'Cancelled', 0, 0, '2025-11-24 12:04:52', 30),
(95, 'walkin1763985910_ab024527@clinic.local', 20, 4, 'Brace Adjustment', '2025-11-26', '07:30:00', '', '', 'Cancelled', 0, 0, '2025-11-24 12:05:15', 30),
(96, 'walkin1763985952_bb85586a@clinic.local', 21, 4, 'Brace Adjustment', '2025-11-26', '11:45:00', '', '', 'Cancelled', 0, 0, '2025-11-24 14:23:57', 30),
(97, 'walkin1763986109_e52accff@clinic.local', 22, 1, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', '2025-11-26', '07:00:00', '', '', 'Cancelled', 0, 0, '2025-11-24 14:23:56', 585),
(98, 'walkin1763985601_cb0ae58f@clinic.local', 18, 4, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces, \'.htmlspecialchars($opt).\'', '2025-11-29', '07:00:00', '', '', 'Finished', 0, 0, '2025-11-24 15:15:41', 645),
(99, 'walkin1763994319_8ec0c08c@clinic.local', 23, 4, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', '2025-11-26', '07:05:00', '', '', 'Finished', 0, 0, '2025-11-24 15:15:39', 585),
(100, 'walkin1763994369_6fdab847@clinic.local', 24, 4, 'Brace Adjustment', '2025-11-25', '07:00:00', '', '', 'Cancelled', 0, 0, '2025-11-24 14:26:16', 30),
(101, 'walkin1763994406_cd4a8fd6@clinic.local', 25, 1, 'Tooth Extraction, Tooth Restoration', '2025-11-26', '07:35:00', '', '', 'Finished', 0, 0, '2025-11-24 15:15:40', 60),
(102, 'walkin1764023215_c23ade19@clinic.local', 26, 4, 'Brace Adjustment', '2025-11-25', '07:00:00', '', '', 'Finished', 0, 0, '2025-11-26 03:31:07', 30),
(103, 'walkin1764226685_f2d85c9e@clinic.local', 27, 4, 'Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', '2025-11-27', '15:00:00', '', '', 'Cancelled', 0, 0, '2025-11-27 06:58:21', 210),
(104, 'lawrencevalderama.bscs.pass@gmail.com', 28, 1, 'Full Braces, Lower Braces, Check-Up', '2025-11-27', '18:50:00', NULL, NULL, 'Cancelled', 0, 0, '2025-11-27 11:52:06', 225),
(105, 'lawrencevalderama.bscs.pass@gmail.com', 28, 1, 'Lower Braces', '2025-11-28', '13:25:00', NULL, NULL, 'Cancelled', 0, 0, NULL, 90),
(106, 'lawrencevalderama.bscs.pass@gmail.com', 28, 1, 'Brace Adjustment', '2025-12-06', '08:25:00', NULL, NULL, 'Cancelled', 0, 0, '2025-12-04 10:49:40', 30),
(107, 'lawrencevalderama.bscs.pass@gmail.com', 28, 2, 'Brace Adjustment', '2025-12-11', '09:25:00', NULL, NULL, 'Cancelled', 0, 0, '2025-12-04 10:54:41', 30),
(108, 'baromargkrulak19@gmail.com', 29, 2, 'Upper Braces', '2025-12-06', '09:05:00', NULL, NULL, 'Confirmed', 0, 0, '2025-12-04 11:17:29', 90);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_appointment_cancellations`
--

CREATE TABLE `tbl_appointment_cancellations` (
  `Id` int(11) NOT NULL,
  `Appointment_Id` int(11) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Reason` varchar(255) NOT NULL,
  `Notes` text DEFAULT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_appointment_cancellations`
--

INSERT INTO `tbl_appointment_cancellations` (`Id`, `Appointment_Id`, `Email`, `Reason`, `Notes`, `Created_At`) VALUES
(1, 2, 'nightmarefox50@gmail.com', 'Feeling unwell', '', '2025-10-15 02:10:34'),
(2, 11, 'gwc.manuel@gmail.com', 'Feeling unwell', 'nagtatampo', '2025-10-16 07:09:29'),
(3, 14, 'nightmarefox50@gmail.com', 'Feeling unwell', '', '2025-10-17 15:04:52'),
(4, 15, 'nightmarefox50@gmail.com', 'Feeling unwell', 'testing', '2025-10-17 15:18:40'),
(5, 16, 'nightmarefox50@gmail.com', 'Feeling unwell', 'testing', '2025-10-17 15:21:07'),
(6, 17, 'nightmarefox50@gmail.com', 'Feeling unwell', 'testing2', '2025-10-17 15:38:06'),
(7, 18, 'nightmarefox50@gmail.com', 'Feeling unwell', 'test3', '2025-10-17 15:38:45'),
(8, 19, 'nightmarefox50@gmail.com', 'Feeling unwell', 'test2', '2025-10-17 15:42:54'),
(9, 20, 'nightmarefox50@gmail.com', 'Schedule conflict', '', '2025-10-17 15:49:08'),
(10, 21, 'nightmarefox50@gmail.com', 'Feeling unwell', 'test4', '2025-10-17 15:52:31'),
(11, 22, 'nightmarefox50@gmail.com', 'Transportation issue', 'test5', '2025-10-17 15:58:02'),
(12, 23, 'nightmarefox50@gmail.com', 'Schedule conflict', 'hi cutie', '2025-10-17 16:12:45'),
(13, 24, 'nightmarefox50@gmail.com', 'Schedule conflict', '', '2025-10-17 16:16:51'),
(14, 25, 'nightmarefox50@gmail.com', 'Schedule conflict', 'test6', '2025-10-17 16:25:31'),
(15, 26, 'nightmarefox50@gmail.com', 'Schedule conflict', '', '2025-10-17 16:29:04'),
(16, 27, 'nightmarefox50@gmail.com', 'Schedule conflict', '', '2025-10-17 16:32:20'),
(17, 28, 'nightmarefox50@gmail.com', 'Feeling unwell', 'test2', '2025-10-17 16:36:33'),
(18, 29, 'nightmarefox50@gmail.com', 'Feeling unwell', '', '2025-10-17 16:37:23'),
(19, 30, 'nightmarefox50@gmail.com', 'Schedule conflict', '', '2025-10-17 16:47:26'),
(20, 31, 'nightmarefox50@gmail.com', 'Schedule conflict', '', '2025-10-17 17:54:46'),
(21, 32, 'nightmarefox50@gmail.com', 'Feeling unwell', '', '2025-10-17 17:57:08'),
(22, 33, 'fme090909@gmail.com', 'Schedule conflict', '', '2025-10-18 11:42:25'),
(23, 35, 'fme090909@gmail.com', 'Feeling unwell', '', '2025-10-18 11:46:52'),
(24, 34, 'fme090909@gmail.com', 'Schedule conflict', '', '2025-10-18 11:47:37'),
(25, 36, 'fme090909@gmail.com', 'Feeling unwell', '', '2025-10-18 12:00:14'),
(26, 40, 'nightmarefox50@gmail.com', 'Schedule conflict', '', '2025-10-19 08:34:48'),
(27, 41, 'nightmarefox50@gmail.com', 'Feeling unwell', '', '2025-10-19 08:35:10'),
(28, 42, 'nightmarefox50@gmail.com', 'Feeling unwell', '', '2025-10-19 10:32:26'),
(29, 46, 'sber33690@gmail.com', 'Schedule conflict', '', '2025-10-20 13:31:41'),
(30, 47, 'sber33690@gmail.com', 'Schedule conflict', '', '2025-10-20 13:40:54'),
(31, 54, 'nightmarefox50@gmail.com', 'Schedule conflict', '', '2025-10-22 14:21:16'),
(32, 57, 'nightmarefox50@gmail.com', 'Transportation issue', '', '2025-10-22 15:28:55'),
(33, 55, 'nightmarefox50@gmail.com', 'Feeling unwell', '', '2025-10-23 14:24:49'),
(34, 60, 'nightmarefox50@gmail.com', 'Feeling unwell', '', '2025-10-27 15:56:22'),
(35, 63, 'nightmarefox50@gmail.com', 'Feeling unwell', '', '2025-11-07 00:28:49'),
(36, 64, 'nightmarefox50@gmail.com', 'Feeling unwell', '', '2025-11-07 00:29:22'),
(37, 65, 'nightmarefox50@gmail.com', 'Feeling unwell', '', '2025-11-07 00:32:03'),
(38, 68, 'fme090909@gmail.com', 'Feeling unwell', '', '2025-11-17 12:03:39'),
(39, 69, 'fme090909@gmail.com', 'Schedule conflict', '', '2025-11-17 12:14:27'),
(40, 70, 'fme090909@gmail.com', 'Feeling unwell', '', '2025-11-17 12:14:33'),
(41, 72, 'nightmarefox50@gmail.com', 'Feeling unwell', '', '2025-11-17 12:23:53'),
(42, 73, 'nightmarefox50@gmail.com', 'Transportation issue', '', '2025-11-17 12:24:49'),
(43, 74, 'nightmarefox50@gmail.com', 'Feeling unwell', '', '2025-11-17 12:33:06'),
(44, 75, 'nightmarefox50@gmail.com', 'Schedule conflict', '', '2025-11-17 12:34:13'),
(45, 76, 'nightmarefox50@gmail.com', 'Feeling unwell', '', '2025-11-17 12:39:31'),
(46, 77, 'nightmarefox50@gmail.com', 'Schedule conflict', '', '2025-11-17 12:44:56'),
(47, 78, 'nightmarefox50@gmail.com', 'Feeling unwell', '', '2025-11-17 14:07:09'),
(48, 82, 'nightmarefox50@gmail.com', 'Schedule conflict', '', '2025-11-23 13:38:49'),
(49, 105, 'lawrencevalderama.bscs.pass@gmail.com', 'Feeling unwell', '', '2025-12-04 10:47:02'),
(50, 104, 'lawrencevalderama.bscs.pass@gmail.com', 'Feeling unwell', '', '2025-12-04 10:47:15');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_appointment_reschedules`
--

CREATE TABLE `tbl_appointment_reschedules` (
  `Id` int(11) NOT NULL,
  `Appointment_Id` int(11) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Old_Date` date NOT NULL,
  `Old_Time` time NOT NULL,
  `New_Date` date NOT NULL,
  `New_Time` time NOT NULL,
  `Reason` varchar(255) DEFAULT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_appointment_reschedules`
--

INSERT INTO `tbl_appointment_reschedules` (`Id`, `Appointment_Id`, `Email`, `Old_Date`, `Old_Time`, `New_Date`, `New_Time`, `Reason`, `Created_At`) VALUES
(1, 12, 'gwc.manuel@gmail.com', '2025-10-21', '07:00:00', '2025-10-21', '16:00:00', 'trip lang', '2025-10-16 07:13:17'),
(2, 36, 'fme090909@gmail.com', '2025-10-19', '07:25:00', '2025-10-19', '10:30:00', 'trip lang', '2025-10-18 11:56:13');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_appointment_treatments`
--

CREATE TABLE `tbl_appointment_treatments` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `treatment_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duration` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_appointment_treatments`
--

INSERT INTO `tbl_appointment_treatments` (`id`, `appointment_id`, `treatment_name`, `price`, `duration`, `notes`, `created_at`) VALUES
(1, 4, 'Oral Prophylaxis (Cleaning)', 0.00, 0, '', '2025-10-15 04:09:18'),
(2, 5, 'Brace Adjustment', 0.00, 0, '', '2025-10-15 04:09:40'),
(3, 6, 'Check-Up', 0.00, 0, '', '2025-10-15 13:59:10'),
(4, 7, 'Check-Up', 0.00, 0, '', '2025-10-16 01:50:17'),
(5, 13, 'Oral Prophylaxis (Cleaning)', 0.00, 0, '', '2025-10-16 07:17:17'),
(6, 51, 'Brace Adjustment', 0.00, 0, '', '2025-10-21 13:02:17'),
(7, 52, 'Check-Up', 0.00, 0, '', '2025-10-21 13:07:32'),
(8, 53, 'Brace Adjustment', 0.00, 0, '', '2025-10-21 13:08:03'),
(9, 79, 'Brace Adjustment', 0.00, 0, '', '2025-11-22 10:39:28'),
(10, 79, 'Check-Up', 0.00, 0, '', '2025-11-22 10:39:28'),
(11, 79, 'Dentures', 0.00, 0, '', '2025-11-22 10:39:28'),
(12, 79, 'Full Braces', 0.00, 0, '', '2025-11-22 10:39:28'),
(13, 79, 'Lower Braces', 0.00, 0, '', '2025-11-22 10:39:28'),
(14, 79, 'Oral Prophylaxis (Cleaning)', 0.00, 0, '', '2025-11-22 10:39:28'),
(15, 79, 'Oral Surgery', 0.00, 0, '', '2025-11-22 10:39:28'),
(16, 79, 'Panoramic X-Ray (X-Ray)', 0.00, 0, '', '2025-11-22 10:39:28'),
(17, 79, 'Teeth Whitening', 0.00, 0, '', '2025-11-22 10:39:28'),
(18, 79, 'Tooth Extraction', 0.00, 0, '', '2025-11-22 10:39:28'),
(19, 79, 'Tooth Restoration', 0.00, 0, '', '2025-11-22 10:39:28'),
(20, 79, 'Upper Braces', 0.00, 0, '', '2025-11-22 10:39:28'),
(21, 80, 'Brace Adjustment', 0.00, 0, '', '2025-11-22 10:46:54'),
(22, 80, 'Tooth Extraction', 0.00, 0, '', '2025-11-22 10:46:54'),
(23, 81, 'Brace Adjustment', 0.00, 0, '', '2025-11-23 13:34:42'),
(24, 81, 'Check-Up', 0.00, 0, '', '2025-11-23 13:34:42'),
(25, 81, 'Dentures', 0.00, 0, '', '2025-11-23 13:34:42'),
(26, 81, 'Full Braces', 0.00, 0, '', '2025-11-23 13:34:42'),
(27, 81, 'Lower Braces', 0.00, 0, '', '2025-11-23 13:34:42'),
(28, 81, 'Oral Prophylaxis (Cleaning)', 0.00, 0, '', '2025-11-23 13:34:42'),
(29, 81, 'Oral Surgery', 0.00, 0, '', '2025-11-23 13:34:42'),
(30, 81, 'Panoramic X-Ray (X-Ray)', 0.00, 0, '', '2025-11-23 13:34:42'),
(31, 81, 'Teeth Whitening', 0.00, 0, '', '2025-11-23 13:34:42'),
(32, 81, 'Tooth Extraction', 0.00, 0, '', '2025-11-23 13:34:42'),
(33, 81, 'Tooth Restoration', 0.00, 0, '', '2025-11-23 13:34:42'),
(34, 81, 'Upper Braces', 0.00, 0, '', '2025-11-23 13:34:42'),
(35, 84, 'Full Braces', 0.00, 0, '', '2025-11-24 06:51:50'),
(36, 85, 'Full Braces', 0.00, 0, '', '2025-11-24 06:54:20'),
(37, 87, 'Full Braces', 0.00, 0, '', '2025-11-24 06:59:17'),
(38, 88, 'Teeth Whitening', 0.00, 0, '', '2025-11-24 08:49:54'),
(39, 89, 'Brace Adjustment', 0.00, 0, '', '2025-11-24 08:51:08'),
(40, 89, 'Check-Up', 0.00, 0, '', '2025-11-24 08:51:08'),
(41, 89, 'Dentures', 0.00, 0, '', '2025-11-24 08:51:08'),
(42, 89, 'Full Braces', 0.00, 0, '', '2025-11-24 08:51:08'),
(43, 89, 'Lower Braces', 0.00, 0, '', '2025-11-24 08:51:08'),
(44, 89, 'Oral Prophylaxis (Cleaning)', 0.00, 0, '', '2025-11-24 08:51:08'),
(45, 89, 'Oral Surgery', 0.00, 0, '', '2025-11-24 08:51:08'),
(46, 89, 'Panoramic X-Ray (X-Ray)', 0.00, 0, '', '2025-11-24 08:51:08'),
(47, 89, 'Teeth Whitening', 0.00, 0, '', '2025-11-24 08:51:08'),
(48, 89, 'Tooth Extraction', 0.00, 0, '', '2025-11-24 08:51:08'),
(49, 89, 'Tooth Restoration', 0.00, 0, '', '2025-11-24 08:51:08'),
(50, 89, 'Upper Braces', 0.00, 0, '', '2025-11-24 08:51:08'),
(51, 90, 'Brace Adjustment', 0.00, 0, '', '2025-11-24 08:51:46'),
(52, 90, 'Check-Up', 0.00, 0, '', '2025-11-24 08:51:46'),
(53, 90, 'Dentures', 0.00, 0, '', '2025-11-24 08:51:46'),
(54, 90, 'Full Braces', 0.00, 0, '', '2025-11-24 08:51:46'),
(55, 90, 'Lower Braces', 0.00, 0, '', '2025-11-24 08:51:46'),
(56, 90, 'Oral Prophylaxis (Cleaning)', 0.00, 0, '', '2025-11-24 08:51:46'),
(57, 90, 'Oral Surgery', 0.00, 0, '', '2025-11-24 08:51:46'),
(58, 90, 'Panoramic X-Ray (X-Ray)', 0.00, 0, '', '2025-11-24 08:51:46'),
(59, 90, 'Teeth Whitening', 0.00, 0, '', '2025-11-24 08:51:46'),
(60, 90, 'Tooth Extraction', 0.00, 0, '', '2025-11-24 08:51:46'),
(61, 90, 'Tooth Restoration', 0.00, 0, '', '2025-11-24 08:51:46'),
(62, 90, 'Upper Braces', 0.00, 0, '', '2025-11-24 08:51:46'),
(63, 91, 'Upper Braces', 0.00, 0, '', '2025-11-24 09:04:29'),
(64, 92, 'Brace Adjustment', 0.00, 0, '', '2025-11-24 12:00:01'),
(65, 92, 'Check-Up', 0.00, 0, '', '2025-11-24 12:00:01'),
(66, 92, 'Dentures', 0.00, 0, '', '2025-11-24 12:00:01'),
(67, 92, 'Full Braces', 0.00, 0, '', '2025-11-24 12:00:01'),
(68, 92, 'Lower Braces', 0.00, 0, '', '2025-11-24 12:00:01'),
(69, 92, 'Oral Prophylaxis (Cleaning)', 0.00, 0, '', '2025-11-24 12:00:01'),
(70, 92, 'Oral Surgery', 0.00, 0, '', '2025-11-24 12:00:01'),
(71, 92, 'Panoramic X-Ray (X-Ray)', 0.00, 0, '', '2025-11-24 12:00:01'),
(72, 92, 'Teeth Whitening', 0.00, 0, '', '2025-11-24 12:00:01'),
(73, 92, 'Tooth Extraction', 0.00, 0, '', '2025-11-24 12:00:01'),
(74, 92, 'Tooth Restoration', 0.00, 0, '', '2025-11-24 12:00:01'),
(75, 92, 'Upper Braces', 0.00, 0, '', '2025-11-24 12:00:01'),
(76, 93, 'Brace Adjustment', 0.00, 0, '', '2025-11-24 12:00:27'),
(77, 94, 'Brace Adjustment', 0.00, 0, '', '2025-11-24 12:04:44'),
(78, 95, 'Brace Adjustment', 0.00, 0, '', '2025-11-24 12:05:10'),
(79, 96, 'Brace Adjustment', 0.00, 0, '', '2025-11-24 12:05:52'),
(80, 97, 'Brace Adjustment', 0.00, 0, '', '2025-11-24 12:08:29'),
(81, 97, 'Check-Up', 0.00, 0, '', '2025-11-24 12:08:29'),
(82, 97, 'Dentures', 0.00, 0, '', '2025-11-24 12:08:29'),
(83, 97, 'Full Braces', 0.00, 0, '', '2025-11-24 12:08:29'),
(84, 97, 'Lower Braces', 0.00, 0, '', '2025-11-24 12:08:29'),
(85, 97, 'Oral Prophylaxis (Cleaning)', 0.00, 0, '', '2025-11-24 12:08:29'),
(86, 97, 'Oral Surgery', 0.00, 0, '', '2025-11-24 12:08:29'),
(87, 97, 'Panoramic X-Ray (X-Ray)', 0.00, 0, '', '2025-11-24 12:08:29'),
(88, 97, 'Teeth Whitening', 0.00, 0, '', '2025-11-24 12:08:29'),
(89, 97, 'Tooth Extraction', 0.00, 0, '', '2025-11-24 12:08:29'),
(90, 97, 'Tooth Restoration', 0.00, 0, '', '2025-11-24 12:08:29'),
(91, 97, 'Upper Braces', 0.00, 0, '', '2025-11-24 12:08:29'),
(92, 98, 'Brace Adjustment', 0.00, 0, '', '2025-11-24 14:20:30'),
(93, 98, 'Check-Up', 0.00, 0, '', '2025-11-24 14:20:30'),
(94, 98, 'Dentures', 0.00, 0, '', '2025-11-24 14:20:30'),
(95, 98, 'Full Braces', 0.00, 0, '', '2025-11-24 14:20:30'),
(96, 98, 'Lower Braces', 0.00, 0, '', '2025-11-24 14:20:30'),
(97, 98, 'Oral Prophylaxis (Cleaning)', 0.00, 0, '', '2025-11-24 14:20:30'),
(98, 98, 'Oral Surgery', 0.00, 0, '', '2025-11-24 14:20:30'),
(99, 98, 'Panoramic X-Ray (X-Ray)', 0.00, 0, '', '2025-11-24 14:20:30'),
(100, 98, 'Teeth Whitening', 0.00, 0, '', '2025-11-24 14:20:30'),
(101, 98, 'Tooth Extraction', 0.00, 0, '', '2025-11-24 14:20:30'),
(102, 98, 'Tooth Restoration', 0.00, 0, '', '2025-11-24 14:20:30'),
(103, 98, 'Upper Braces', 0.00, 0, '', '2025-11-24 14:20:30'),
(104, 98, '\'.htmlspecialchars($opt).\'', 0.00, 0, '', '2025-11-24 14:20:30'),
(105, 99, 'Brace Adjustment', 0.00, 0, '', '2025-11-24 14:25:19'),
(106, 99, 'Check-Up', 0.00, 0, '', '2025-11-24 14:25:19'),
(107, 99, 'Dentures', 0.00, 0, '', '2025-11-24 14:25:19'),
(108, 99, 'Full Braces', 0.00, 0, '', '2025-11-24 14:25:19'),
(109, 99, 'Lower Braces', 0.00, 0, '', '2025-11-24 14:25:19'),
(110, 99, 'Oral Prophylaxis (Cleaning)', 0.00, 0, '', '2025-11-24 14:25:19'),
(111, 99, 'Oral Surgery', 0.00, 0, '', '2025-11-24 14:25:19'),
(112, 99, 'Panoramic X-Ray (X-Ray)', 0.00, 0, '', '2025-11-24 14:25:19'),
(113, 99, 'Teeth Whitening', 0.00, 0, '', '2025-11-24 14:25:19'),
(114, 99, 'Tooth Extraction', 0.00, 0, '', '2025-11-24 14:25:19'),
(115, 99, 'Tooth Restoration', 0.00, 0, '', '2025-11-24 14:25:19'),
(116, 99, 'Upper Braces', 0.00, 0, '', '2025-11-24 14:25:19'),
(117, 100, 'Brace Adjustment', 0.00, 0, '', '2025-11-24 14:26:09'),
(118, 101, 'Tooth Extraction', 0.00, 0, '', '2025-11-24 14:26:46'),
(119, 101, 'Tooth Restoration', 0.00, 0, '', '2025-11-24 14:26:46'),
(120, 102, 'Brace Adjustment', 0.00, 0, '', '2025-11-24 22:26:55'),
(121, 103, 'Teeth Whitening', 0.00, 0, '', '2025-11-27 06:58:05'),
(122, 103, 'Tooth Extraction', 0.00, 0, '', '2025-11-27 06:58:05'),
(123, 103, 'Tooth Restoration', 0.00, 0, '', '2025-11-27 06:58:05'),
(124, 103, 'Upper Braces', 0.00, 0, '', '2025-11-27 06:58:05');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_clinic_settings`
--

CREATE TABLE `tbl_clinic_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_clinic_settings`
--

INSERT INTO `tbl_clinic_settings` (`id`, `setting_key`, `setting_value`) VALUES
(1, 'clinic_logo_url', 'uploads/clinic_logo_20251014_170349.png'),
(2, 'clinic_name', 'Miles Dental Clinic'),
(3, 'contact_email', 'milesdentalclinic@gmail.com'),
(4, 'phone_number', '(555) 123-4567'),
(5, 'address', 'rizal street mabini pangasinan'),
(6, 'clinic_hours', 'Monday - Sunday: 7:00 AM - 7:00 PM'),
(7, 'timezone', 'Asia/Manila'),
(8, 'max_appointments_per_day', '20'),
(9, 'appointment_duration', '60'),
(10, 'session_timeout_minutes', '0'),
(11, 'after_hours_phone', '(555) 987-6543'),
(12, 'emergency_instructions', 'For severe pain, bleeding, or broken teeth, call immediately. Do not wait for regular office hours.');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_dentist`
--

CREATE TABLE `tbl_dentist` (
  `Dentist_id` int(11) NOT NULL,
  `Name` varchar(255) NOT NULL,
  `Specialization` varchar(255) DEFAULT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `Password` varchar(100) NOT NULL,
  `Phone` varchar(11) NOT NULL,
  `Clinic_schedule` varchar(100) NOT NULL,
  `Photo_url` varchar(200) DEFAULT NULL,
  `Status` varchar(50) NOT NULL DEFAULT '(Active,Enactive)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_dentist`
--

INSERT INTO `tbl_dentist` (`Dentist_id`, `Name`, `Specialization`, `Email`, `Password`, `Phone`, `Clinic_schedule`, `Photo_url`, `Status`, `is_active`) VALUES
(1, 'Carrel Miles Cabrales', 'General Dentistry', 'miles@gmail.com', '$2y$10$23T0MK1aL8Ild44E3A8ml.uNhWzRZTWbAZKL4m3k7.H2JFk3IhZfy', '09898233221', '', NULL, '(Active,Enactive)', 1),
(2, 'Catherine Aben Reyes', 'General Dentistry', 'Catherine@gmail.com', '$2y$10$GQxTTcoafRrsQi2ZmAyfZOl6DOyFYHvMMlvKhENI1uOrMJGJOZdna', '', '', NULL, '(Active,Enactive)', 1),
(3, 'Kyle Eve Evangelista', 'General Dentistry', 'Kyle@gmail.com', '$2y$10$pxYRb/k9ejJx5MtSSD19Se.xoNCkjkX1kuRNASTLyZ7A.holZUBEC', '09212112123', '', NULL, '(Active,Enactive)', 1),
(4, 'Bernadine Bungaoen', 'General Dentistry', 'Bernadine@gmail.com', '$2y$10$qW7PY1ylocepIg/F4SdwaePSUh1j57t0qMaiEQZDTtokgbxvK3Pdq', '', '', NULL, '(Active,Enactive)', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_dentist_leave`
--

CREATE TABLE `tbl_dentist_leave` (
  `Id` int(11) NOT NULL,
  `Dentist_Id` int(11) NOT NULL,
  `Leave_Date` date NOT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_inventory`
--

CREATE TABLE `tbl_inventory` (
  `Item_Id` int(11) NOT NULL,
  `Item_Name` varchar(255) NOT NULL,
  `SKU` varchar(100) DEFAULT NULL,
  `Unit` varchar(32) NOT NULL DEFAULT 'unit',
  `Current_Stock` int(11) NOT NULL DEFAULT 0,
  `Reorder_Level` int(11) NOT NULL DEFAULT 0,
  `Updated_At` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp(),
  `Quantity` int(11) NOT NULL DEFAULT 0,
  `Threshold` int(11) DEFAULT 5,
  `Category` varchar(100) DEFAULT NULL,
  `Used_For` varchar(255) DEFAULT NULL,
  `Supplier` varchar(255) DEFAULT NULL,
  `Expiration_Date` date DEFAULT NULL,
  `Unit_Cost` decimal(10,2) DEFAULT NULL,
  `Last_update` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_inventory`
--

INSERT INTO `tbl_inventory` (`Item_Id`, `Item_Name`, `SKU`, `Unit`, `Current_Stock`, `Reorder_Level`, `Updated_At`, `Created_At`, `Quantity`, `Threshold`, `Category`, `Used_For`, `Supplier`, `Expiration_Date`, `Unit_Cost`, `Last_update`, `Description`) VALUES
(1, 'Cotton Rolls', 'DNT-001', 'pcs', 437, 100, '2025-11-24 16:26:53', '2025-10-15 02:29:24', 437, 5, NULL, 'Check-Up, Oral Prophylaxis (Cleaning), Teeth Whitening, Tooth Extraction, Tooth Restoration', '', '2027-10-29', NULL, '2025-11-24 16:26:53', ''),
(2, 'Mouth Mirror', 'DNT-002', 'pcs', 99, 5, '2025-11-24 16:29:09', '2025-10-15 02:29:24', 99, 5, NULL, 'Check-Up, Oral Prophylaxis (Cleaning)', '', '2027-10-27', NULL, '2025-11-24 16:29:09', ''),
(3, 'Explorer', 'DNT-003', 'pcs', 19, 5, '2025-10-27 12:33:29', '2025-10-15 02:29:24', 10, 5, NULL, 'Check-Up', NULL, NULL, NULL, '2025-10-27 12:33:29', NULL),
(4, 'Scaler Tip', 'DNT-004', 'pcs', 90, 5, '2025-11-24 16:29:30', '2025-10-15 02:29:24', 90, 5, NULL, 'Oral Prophylaxis (Cleaning)', '', '2027-10-20', NULL, '2025-11-24 16:29:30', ''),
(5, 'Saliva Ejector', 'DNT-005', 'pcs', 94, 20, '2025-11-05 13:59:16', '2025-10-15 02:29:24', 0, 5, NULL, 'Oral Prophylaxis (Cleaning)', NULL, NULL, NULL, '2025-11-05 13:59:16', NULL),
(6, 'Gauze Pad', 'DNT-006', 'pcs', 295, 50, '2025-11-24 16:28:17', '2025-10-15 02:29:24', 295, 5, NULL, 'Oral Surgery', '', '2027-10-20', NULL, '2025-11-24 16:28:17', ''),
(7, 'Cheek Retractor', 'DNT-007', 'pcs', 19, 5, '2025-11-24 11:59:35', '2025-10-15 02:29:24', 0, 5, NULL, 'Teeth Whitening', NULL, NULL, NULL, '2025-11-24 11:59:35', NULL),
(8, 'Composite Resin', 'RST-001', 'tube', 48, 10, '2025-10-27 08:38:24', '2025-10-15 02:29:24', 0, 5, NULL, 'Tooth Restoration', NULL, NULL, NULL, '2025-10-27 08:38:24', NULL),
(9, 'Etching Gel', 'RST-002', 'syringe', 28, 5, '2025-10-27 08:38:24', '2025-10-15 02:29:24', 0, 5, NULL, 'Tooth Restoration', NULL, NULL, NULL, '2025-10-27 08:38:24', NULL),
(10, 'Bonding Agent', 'RST-003', 'bottle', 23, 5, '2025-11-24 16:26:42', '2025-10-15 02:29:24', 23, 5, NULL, 'Tooth Restoration', '', '2027-10-20', NULL, '2025-11-24 16:26:42', ''),
(11, 'Anesthetic Cartridge', 'SUR-001', 'pcs', 120, 5, '2025-11-24 16:44:43', '2025-10-15 02:29:24', 120, 5, '', 'Oral Surgery', 'Ando', '2027-12-21', 20.00, '2025-11-24 16:44:43', 'edi'),
(12, 'Surgical Blade', 'SUR-002', 'pcs', 99, 20, '2025-11-07 00:52:03', '2025-10-15 02:29:24', 0, 5, NULL, 'Oral Surgery', NULL, NULL, NULL, '2025-11-07 00:52:03', NULL),
(13, 'Suture Thread', 'SUR-003', 'pcs', 49, 10, '2025-11-07 00:52:03', '2025-10-15 02:29:24', 0, 5, NULL, 'Oral Surgery', NULL, NULL, NULL, '2025-11-07 00:52:03', NULL),
(14, 'Impression Tray', 'DEN-001', 'pcs', 15, 5, '2025-10-27 08:38:24', '2025-10-15 02:29:24', 0, 5, NULL, 'Dentures', NULL, NULL, NULL, '2025-10-27 08:38:24', NULL),
(15, 'Impression Material', 'DEN-002', 'pack', 30, 10, '2025-10-27 08:38:24', '2025-10-15 02:29:24', 0, 5, NULL, 'Dentures', NULL, NULL, NULL, '2025-10-27 08:38:24', NULL),
(16, 'Wax Block', 'DEN-003', 'pcs', 25, 5, '2025-10-27 08:38:24', '2025-10-15 02:29:24', 0, 5, NULL, 'Dentures', NULL, NULL, NULL, '2025-10-27 08:38:24', NULL),
(17, 'X-Ray Film', 'XRY-001', 'sheet', 100, 20, '2025-10-27 08:38:24', '2025-10-15 02:29:24', 0, 5, NULL, 'Panoramic X-Ray (X-Ray)', NULL, NULL, NULL, '2025-10-27 08:38:24', NULL),
(18, 'Lead Apron', 'XRY-002', 'pcs', 10, 2, '2025-10-27 08:38:24', '2025-10-15 02:29:24', 0, 5, NULL, 'Panoramic X-Ray (X-Ray)', NULL, NULL, NULL, '2025-10-27 08:38:24', NULL),
(19, 'Bracket Set', 'ORT-001', 'set', 30, 10, '2025-10-27 08:38:24', '2025-10-15 02:29:24', 0, 5, NULL, 'Full Braces', NULL, NULL, NULL, '2025-10-27 08:38:24', NULL),
(20, 'Bracket Set (Upper)', 'ORT-002', 'set', 19, 5, '2025-11-24 11:59:36', '2025-10-15 02:29:24', 0, 5, NULL, 'Upper Braces', NULL, NULL, NULL, '2025-11-24 11:59:36', NULL),
(21, 'Bracket Set (Lower)', 'ORT-003', 'set', 20, 5, '2025-10-27 08:38:24', '2025-10-15 02:29:24', 0, 5, NULL, 'Lower Braces', NULL, NULL, NULL, '2025-10-27 08:38:24', NULL),
(22, 'Archwire', 'ORT-004', 'pcs', 89, 5, '2025-11-26 03:31:07', '2025-10-15 02:29:24', 90, 5, NULL, 'Brace Adjustment, Full Braces', '', '2027-10-20', NULL, '2025-11-26 03:31:07', ''),
(23, 'Archwire (Upper)', 'ORT-005', 'pcs', 59, 10, '2025-11-24 16:26:28', '2025-10-15 02:29:24', 59, 5, NULL, 'Upper Braces', '', '2027-10-29', NULL, '2025-11-24 16:26:28', ''),
(24, 'Archwire (Lower)', 'ORT-006', 'pcs', 60, 10, '2025-10-27 08:38:24', '2025-10-15 02:29:24', 0, 5, NULL, 'Lower Braces', NULL, NULL, NULL, '2025-10-27 08:38:24', NULL),
(25, 'Ligature Elastic', 'ORT-007', 'pcs', 940, 200, '2025-11-26 03:31:07', '2025-10-15 02:29:24', 0, 5, NULL, 'Brace Adjustment, Full Braces, Lower Braces, Upper Braces', NULL, NULL, NULL, '2025-11-26 03:31:07', NULL),
(26, 'Whitening Gel', 'WHI-001', 'syringe', 29, 10, '2025-11-24 11:59:35', '2025-10-15 02:29:24', 0, 5, NULL, 'Teeth Whitening', NULL, NULL, NULL, '2025-11-24 11:59:35', NULL),
(35, 'Lidocaine 2% carpule', NULL, 'pcs', 50, 10, '2025-10-27 08:38:24', '2025-10-15 11:56:17', 0, 5, 'Anesthetics', 'Tooth Extraction', NULL, NULL, NULL, '2025-10-27 08:38:24', NULL),
(36, 'Disposable syringe', NULL, 'pcs', 120, 20, '2025-11-24 16:27:44', '2025-10-15 11:56:17', 120, 5, 'Consumables', 'Consumables', '', '2027-10-20', NULL, '2025-11-24 16:27:44', ''),
(37, 'Dental needle', NULL, 'pcs', 120, 20, '2025-11-24 16:27:19', '2025-10-15 11:56:17', 120, 5, 'Consumables', 'Consumables', '', '2027-10-20', NULL, '2025-11-24 16:27:19', ''),
(38, 'Gauze pack', NULL, 'packs', 100, 50, '2025-11-24 16:28:04', '2025-10-15 11:56:17', 100, 5, 'Consumables', 'Consumables', '', '2027-10-20', NULL, '2025-11-24 16:28:04', ''),
(39, 'Saline (ml)', NULL, 'ml', 221, 200, '2025-11-24 16:29:21', '2025-10-15 11:56:17', 221, 5, 'Solutions', 'Solutions', '', '2027-10-20', NULL, '2025-11-24 16:29:21', ''),
(40, 'Hemostat/gel foam', NULL, 'pcs', 110, 10, '2025-11-24 16:28:38', '2025-10-15 11:56:17', 110, 5, 'Hemostatic', 'Hemostatic', '', '2027-10-20', NULL, '2025-11-24 16:28:38', ''),
(41, 'Exo forceps set (kit use)', NULL, 'sets', 10, 2, '2025-11-24 16:27:52', '2025-10-15 11:56:17', 10, 5, 'Instruments', 'Instruments', '', '2027-10-20', NULL, '2025-11-24 16:27:52', ''),
(42, 'Gauze Pads', NULL, 'pcs', 90, 0, '2025-11-24 16:28:25', '2025-11-05 11:46:48', 90, 20, 'Consumables', 'Tooth Extraction', 'Default Supplier', '2027-10-20', 5.00, '2025-11-24 16:28:25', 'Sterile gauze pads'),
(43, 'Lidocaine (Anesthetic)', NULL, 'vials', 95, 0, '2025-11-24 16:29:00', '2025-11-05 11:46:48', 95, 10, 'Medicine', 'Tooth Extraction', 'Default Supplier', '2027-10-20', 150.00, '2025-11-24 16:29:00', 'Local anesthetic'),
(44, 'Syringe', NULL, 'pcs', 95, 0, '2025-11-24 16:29:39', '2025-11-05 11:46:48', 95, 20, 'Consumables', 'Tooth Extraction', 'Default Supplier', '2027-10-20', 20.00, '2025-11-24 16:29:39', 'Disposable syringe'),
(45, 'Gloves', NULL, 'pairs', 77, 10, '2025-11-24 16:27:32', '2025-11-05 11:46:48', 77, 30, 'Consumables', 'Tooth Extraction', 'Default Supplier', '2027-10-20', 8.00, '2025-11-24 16:27:32', 'Disposable gloves (pair)');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_inventory_added`
--

CREATE TABLE `tbl_inventory_added` (
  `Id` int(11) NOT NULL,
  `Inventory_Id` int(11) DEFAULT NULL,
  `Item_Name` varchar(255) NOT NULL,
  `Quantity` int(11) NOT NULL,
  `Added_By` varchar(255) DEFAULT NULL,
  `Added_By_Email` varchar(255) DEFAULT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_inventory_audit`
--

CREATE TABLE `tbl_inventory_audit` (
  `Id` int(11) NOT NULL,
  `Inventory_Id` int(11) DEFAULT NULL,
  `Action` varchar(32) NOT NULL,
  `Changed_By` varchar(255) DEFAULT NULL,
  `Changed_By_Email` varchar(255) DEFAULT NULL,
  `Details` text DEFAULT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_inventory_audit`
--

INSERT INTO `tbl_inventory_audit` (`Id`, `Inventory_Id`, `Action`, `Changed_By`, `Changed_By_Email`, `Details`, `Created_At`) VALUES
(1, 2, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Cotton Rolls', '2025-10-15 01:26:58'),
(2, 1, 'delete', 'Clinic Admin', 'Admin@gmail.com', 'Item deleted', '2025-10-15 01:27:08'),
(3, 1, 'delete', 'Clinic Admin', 'Admin@gmail.com', 'Item deleted', '2025-10-15 01:28:44'),
(4, 2, 'delete', 'Clinic Admin', 'Admin@gmail.com', 'Item deleted', '2025-10-15 01:28:46'),
(5, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-15 02:35:40'),
(6, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-15 02:35:55'),
(7, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-15 02:38:19'),
(8, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-15 02:38:36'),
(9, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-16 07:25:03'),
(10, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-16 07:25:20'),
(11, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-16 07:25:40'),
(12, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-16 07:26:29'),
(13, 11, 'update', 'system', '', 'Updated: Anesthetic Cartridge', '2025-10-17 14:55:46'),
(14, 11, 'update', 'system', '', 'Updated: Anesthetic Cartridge', '2025-10-17 16:11:27'),
(15, 11, 'update', 'system', '', 'Updated: Anesthetic Cartridge', '2025-10-19 14:49:46'),
(16, 11, 'update', 'system', '', 'Updated: Anesthetic Cartridge', '2025-10-19 14:49:55'),
(17, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-20 10:02:32'),
(18, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-20 10:06:32'),
(19, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-20 10:09:42'),
(20, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-20 10:24:20'),
(21, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-27 09:13:32'),
(22, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-27 11:54:47'),
(23, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-27 11:55:30'),
(24, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-27 11:55:58'),
(25, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-27 11:57:45'),
(26, 36, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Disposable syringe', '2025-10-27 12:01:47'),
(27, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-27 12:02:28'),
(28, 23, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Archwire (Upper)', '2025-10-27 12:10:31'),
(29, 23, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Archwire (Upper)', '2025-10-27 12:10:42'),
(30, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-27 12:33:39'),
(31, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-27 12:48:19'),
(32, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-27 12:49:04'),
(33, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-27 12:49:23'),
(34, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-27 12:56:46'),
(35, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-27 12:57:45'),
(36, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-27 12:58:07'),
(37, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-27 12:58:08'),
(38, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-27 12:58:09'),
(39, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-27 13:02:16'),
(40, 40, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Hemostat/gel foam', '2025-10-27 14:54:47'),
(41, 40, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Hemostat/gel foam', '2025-10-27 15:00:19'),
(42, 37, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Dental needle', '2025-10-27 15:36:46'),
(43, 37, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Dental needle', '2025-10-27 15:37:04'),
(44, 36, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Disposable syringe', '2025-10-27 15:37:14'),
(45, 36, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Disposable syringe', '2025-10-27 15:37:25'),
(46, 39, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Saline (ml)', '2025-10-27 15:37:35'),
(47, 39, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Saline (ml)', '2025-10-27 15:37:57'),
(48, 39, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Saline (ml)', '2025-10-27 15:38:28'),
(49, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-27 15:47:39'),
(50, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-10-27 15:47:58'),
(51, 1, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Cotton Rolls', '2025-10-27 15:48:58'),
(52, 1, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Cotton Rolls', '2025-10-27 15:49:18'),
(53, 11, 'update', 'Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-03 13:21:05'),
(54, 38, 'update', 'Admin', 'Admin@gmail.com', 'Updated: Gauze pack', '2025-11-03 13:21:31'),
(55, 38, 'update', 'Admin', 'Admin@gmail.com', 'Updated: Gauze pack', '2025-11-03 13:21:45'),
(56, 11, 'update', 'Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-03 13:37:23'),
(57, 11, 'update', 'Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-03 13:37:36'),
(58, 41, 'update', 'Admin', 'Admin@gmail.com', 'Updated: Exo forceps set (kit use)', '2025-11-03 13:37:53'),
(59, 41, 'update', 'Admin', 'Admin@gmail.com', 'Updated: Exo forceps set (kit use)', '2025-11-03 13:38:07'),
(60, 11, 'update', 'Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-03 13:50:56'),
(61, 11, 'update', 'system', '', 'Updated: Anesthetic Cartridge', '2025-11-03 14:11:42'),
(62, 11, 'update', 'system', '', 'Updated: Anesthetic Cartridge', '2025-11-03 14:16:44'),
(63, 11, 'update', 'system', '', 'Updated: Anesthetic Cartridge', '2025-11-03 14:16:49'),
(64, 11, 'update', 'system', '', 'Updated: Anesthetic Cartridge', '2025-11-03 14:17:40'),
(65, 11, 'update', 'system', '', 'Updated: Anesthetic Cartridge', '2025-11-03 14:27:19'),
(66, 11, 'update', 'system', '', 'Updated: Anesthetic Cartridge', '2025-11-03 14:27:53'),
(67, 11, 'update', 'system', '', 'Updated: Anesthetic Cartridge', '2025-11-03 14:28:07'),
(68, 11, 'update', 'Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-04 11:43:21'),
(69, 11, 'update', 'Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-04 11:47:30'),
(70, 22, 'update', 'Admin', 'Admin@gmail.com', 'Updated: Archwire', '2025-11-04 11:47:46'),
(71, 22, 'update', 'Admin', 'Admin@gmail.com', 'Updated: Archwire', '2025-11-04 11:48:02'),
(72, 2, 'update', 'Admin', 'Admin@gmail.com', 'Updated: Mouth Mirror', '2025-11-04 11:49:01'),
(73, 2, 'update', 'Admin', 'Admin@gmail.com', 'Updated: Mouth Mirror', '2025-11-04 11:49:11'),
(74, 10, 'update', 'Admin', 'Admin@gmail.com', 'Updated: Bonding Agent', '2025-11-04 11:51:02'),
(75, 10, 'update', 'Admin', 'Admin@gmail.com', 'Updated: Bonding Agent', '2025-11-04 11:51:27'),
(76, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-05 11:17:53'),
(77, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-05 11:18:18'),
(78, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-05 11:38:18'),
(79, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-05 11:38:54'),
(80, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-05 11:39:32'),
(81, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-05 11:39:44'),
(82, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-05 11:39:53'),
(83, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-05 11:41:20'),
(84, 38, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Gauze pack', '2025-11-05 11:47:21'),
(85, 38, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Gauze pack', '2025-11-05 11:47:30'),
(86, 38, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Gauze pack', '2025-11-05 11:47:45'),
(87, 38, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Gauze pack', '2025-11-05 11:48:01'),
(88, 6, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Gauze Pad', '2025-11-05 11:48:24'),
(89, 6, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Gauze Pad', '2025-11-05 11:48:32'),
(90, 42, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Gauze Pads', '2025-11-05 12:04:47'),
(91, 45, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Gloves', '2025-11-05 12:05:00'),
(92, 43, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Lidocaine (Anesthetic)', '2025-11-05 12:05:08'),
(93, 44, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Syringe', '2025-11-05 12:05:15'),
(94, 43, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Lidocaine (Anesthetic)', '2025-11-05 12:24:23'),
(95, 43, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Lidocaine (Anesthetic)', '2025-11-05 12:24:31'),
(96, 41, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Exo forceps set (kit use)', '2025-11-05 12:24:57'),
(97, 45, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Gloves', '2025-11-05 12:45:29'),
(98, 41, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Exo forceps set (kit use)', '2025-11-05 12:45:51'),
(99, 45, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Gloves', '2025-11-05 12:45:58'),
(100, 45, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Gloves', '2025-11-05 12:46:06'),
(101, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-05 12:49:01'),
(102, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-05 12:55:13'),
(103, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-07 00:52:17'),
(104, 4, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Scaler Tip', '2025-11-11 13:49:02'),
(105, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-16 15:15:19'),
(106, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-16 15:15:37'),
(107, 1, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Cotton Rolls', '2025-11-16 15:17:08'),
(108, 1, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Cotton Rolls', '2025-11-16 15:17:33'),
(109, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-17 14:06:10'),
(110, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-17 14:06:29'),
(111, 22, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Archwire', '2025-11-19 14:38:28'),
(112, 22, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Archwire', '2025-11-19 14:38:45'),
(113, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 01:48:31'),
(114, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 01:49:15'),
(115, 22, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Archwire', '2025-11-24 07:12:04'),
(116, 22, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Archwire', '2025-11-24 08:45:57'),
(117, 22, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Archwire', '2025-11-24 08:46:09'),
(118, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 10:55:07'),
(119, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 11:55:08'),
(120, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 12:21:07'),
(121, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 12:25:19'),
(122, 45, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Gloves', '2025-11-24 12:27:28'),
(123, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 12:27:38'),
(124, 45, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Gloves', '2025-11-24 12:27:54'),
(125, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 12:28:08'),
(126, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 12:34:13'),
(127, 11, 'deduct', 'Clinic Admin', 'Admin@gmail.com', 'Manual deduction -100 (from 190 to 90)', '2025-11-24 12:34:13'),
(128, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 14:33:47'),
(129, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 14:36:46'),
(130, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 14:45:00'),
(131, 11, 'quantity_change', 'Clinic Admin', 'Admin@gmail.com', 'qty_delta:+10; new_qty:100', '2025-11-24 14:45:00'),
(132, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 15:06:10'),
(133, 11, 'quantity_change', 'Clinic Admin', 'Admin@gmail.com', 'qty_delta:+1; new_qty:101', '2025-11-24 15:06:10'),
(134, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 15:23:58'),
(135, 11, 'quantity_change', 'Clinic Admin', 'Admin@gmail.com', 'qty_delta:-101; new_qty:0', '2025-11-24 15:23:58'),
(136, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 15:24:20'),
(137, 11, 'quantity_change', 'Clinic Admin', 'Admin@gmail.com', 'qty_delta:+100; new_qty:100', '2025-11-24 15:24:20'),
(138, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 15:31:53'),
(139, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 15:32:07'),
(140, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 15:54:41'),
(141, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 16:01:33'),
(142, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 16:05:43'),
(143, 11, 'quantity_change', 'Clinic Admin', 'Admin@gmail.com', 'qty_delta:+10; new_qty:110', '2025-11-24 16:05:43'),
(144, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 16:05:55'),
(145, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge', '2025-11-24 16:07:07'),
(146, 11, 'quantity_change', 'Clinic Admin', 'Admin@gmail.com', 'qty_delta:+1; new_qty:111', '2025-11-24 16:07:07'),
(147, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge; exp_at:2027-11-04', '2025-11-24 16:11:13'),
(148, 11, 'quantity_change', 'Clinic Admin', 'Admin@gmail.com', 'qty_delta:+1; new_qty:112; exp_at:2027-11-04', '2025-11-24 16:11:13'),
(149, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge; exp_at:2027-11-05', '2025-11-24 16:13:06'),
(150, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge; exp_at:2027-11-06', '2025-11-24 16:17:22'),
(151, 11, 'quantity_change', 'Clinic Admin', 'Admin@gmail.com', 'qty_delta:+1; new_qty:113; exp_at:2027-11-06', '2025-11-24 16:17:22'),
(152, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge; exp_at:2027-12-06', '2025-11-24 16:18:06'),
(153, 11, 'quantity_change', 'Clinic Admin', 'Admin@gmail.com', 'qty_delta:+2; new_qty:115; exp_at:2027-12-06', '2025-11-24 16:18:06'),
(154, 22, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Archwire; exp_at:2027-10-20', '2025-11-24 16:26:16'),
(155, 23, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Archwire (Upper); exp_at:2027-10-29', '2025-11-24 16:26:27'),
(156, 10, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Bonding Agent; exp_at:2027-10-20', '2025-11-24 16:26:41'),
(157, 1, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Cotton Rolls; exp_at:2027-10-29', '2025-11-24 16:26:51'),
(158, 37, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Dental needle; exp_at:2027-10-20', '2025-11-24 16:27:17'),
(159, 45, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Gloves; exp_at:2027-10-20', '2025-11-24 16:27:31'),
(160, 36, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Disposable syringe; exp_at:2027-10-20', '2025-11-24 16:27:42'),
(161, 41, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Exo forceps set (kit use); exp_at:2027-10-20', '2025-11-24 16:27:51'),
(162, 38, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Gauze pack; exp_at:2027-10-20', '2025-11-24 16:28:02'),
(163, 6, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Gauze Pad; exp_at:2027-10-20', '2025-11-24 16:28:15'),
(164, 42, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Gauze Pads; exp_at:2027-10-20', '2025-11-24 16:28:24'),
(165, 40, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Hemostat/gel foam; exp_at:2027-10-20', '2025-11-24 16:28:37'),
(166, 43, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Lidocaine (Anesthetic); exp_at:2027-10-20', '2025-11-24 16:28:59'),
(167, 2, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Mouth Mirror; exp_at:2027-10-27', '2025-11-24 16:29:07'),
(168, 39, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Saline (ml); exp_at:2027-10-20', '2025-11-24 16:29:20'),
(169, 4, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Scaler Tip; exp_at:2027-10-20', '2025-11-24 16:29:29'),
(170, 44, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Syringe; exp_at:2027-10-20', '2025-11-24 16:29:38'),
(171, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge; exp_at:2027-12-20', '2025-11-24 16:41:42'),
(172, 11, 'quantity_change', 'Clinic Admin', 'Admin@gmail.com', 'qty_delta:+5; new_qty:120; exp_at:2027-12-20', '2025-11-24 16:41:42'),
(173, 11, 'update', 'Clinic Admin', 'Admin@gmail.com', 'Updated: Anesthetic Cartridge; exp_at:2027-12-21', '2025-11-24 16:44:42');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_inventory_deduction`
--

CREATE TABLE `tbl_inventory_deduction` (
  `Deduction_Id` int(11) NOT NULL,
  `Appointment_Id` int(11) NOT NULL,
  `Patient_Id` int(11) DEFAULT NULL,
  `Patient_Email` varchar(255) DEFAULT NULL,
  `Dentist_Id` int(11) DEFAULT NULL,
  `Deducted_By` varchar(64) NOT NULL DEFAULT 'system',
  `Notes` varchar(500) DEFAULT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_inventory_deduction_items`
--

CREATE TABLE `tbl_inventory_deduction_items` (
  `Deduction_Item_Id` int(11) NOT NULL,
  `Deduction_Id` int(11) NOT NULL,
  `Item_Id` int(11) DEFAULT NULL,
  `Item_Name` varchar(255) NOT NULL,
  `Quantity_Deducted` int(11) NOT NULL,
  `Unit` varchar(32) NOT NULL DEFAULT 'unit',
  `Status` varchar(32) NOT NULL DEFAULT 'deducted',
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_inventory_log`
--

CREATE TABLE `tbl_inventory_log` (
  `Log_Id` int(11) NOT NULL,
  `Appointment_Id` int(11) DEFAULT NULL,
  `Treatment_Name` varchar(255) NOT NULL,
  `Patient_Name` varchar(255) DEFAULT NULL,
  `Dentist_Name` varchar(255) DEFAULT NULL,
  `Item_Name` varchar(255) NOT NULL,
  `Quantity_Deducted` int(11) NOT NULL DEFAULT 0,
  `Remaining_Quantity` int(11) NOT NULL DEFAULT 0,
  `Log_Date` timestamp NOT NULL DEFAULT current_timestamp(),
  `Status` enum('success','failed','insufficient') NOT NULL DEFAULT 'success',
  `Notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_inventory_log`
--

INSERT INTO `tbl_inventory_log` (`Log_Id`, `Appointment_Id`, `Treatment_Name`, `Patient_Name`, `Dentist_Name`, `Item_Name`, `Quantity_Deducted`, `Remaining_Quantity`, `Log_Date`, `Status`, `Notes`) VALUES
(1, 3, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Cotton Rolls', 5, 495, '2025-10-15 02:43:32', 'success', 'Automatic deduction for treatment completion'),
(2, 3, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Mouth Mirror', 1, 19, '2025-10-15 02:43:32', 'success', 'Automatic deduction for treatment completion'),
(3, 3, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Saliva Ejector', 1, 99, '2025-10-15 02:43:32', 'success', 'Automatic deduction for treatment completion'),
(4, 3, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Scaler Tip', 1, 14, '2025-10-15 02:43:32', 'success', 'Automatic deduction for treatment completion'),
(5, 5, 'Brace Adjustment', NULL, NULL, 'Archwire', 1, 99, '2025-10-15 04:10:28', 'success', 'Automatic deduction for treatment completion'),
(6, 5, 'Brace Adjustment', NULL, NULL, 'Ligature Elastic', 10, 990, '2025-10-15 04:10:28', 'success', 'Automatic deduction for treatment completion'),
(7, 7, 'Check-Up', NULL, NULL, 'Cotton Rolls', 2, 493, '2025-10-16 01:50:49', 'success', 'Automatic deduction for treatment completion'),
(8, 7, 'Check-Up', NULL, NULL, 'Explorer', 1, 19, '2025-10-16 01:50:49', 'success', 'Automatic deduction for treatment completion'),
(9, 7, 'Check-Up', NULL, NULL, 'Mouth Mirror', 1, 18, '2025-10-16 01:50:49', 'success', 'Automatic deduction for treatment completion'),
(10, 13, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Cotton Rolls', 5, 488, '2025-10-17 15:31:03', 'success', 'Automatic deduction for treatment completion'),
(11, 13, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Mouth Mirror', 1, 17, '2025-10-17 15:31:03', 'success', 'Automatic deduction for treatment completion'),
(12, 13, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Saliva Ejector', 1, 98, '2025-10-17 15:31:03', 'success', 'Automatic deduction for treatment completion'),
(13, 13, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Scaler Tip', 1, 13, '2025-10-17 15:31:03', 'success', 'Automatic deduction for treatment completion'),
(14, 37, 'Brace Adjustment', NULL, NULL, 'Archwire', 1, 98, '2025-10-18 12:01:15', 'success', 'Automatic deduction for treatment completion'),
(15, 37, 'Brace Adjustment', NULL, NULL, 'Ligature Elastic', 10, 980, '2025-10-18 12:01:15', 'success', 'Automatic deduction for treatment completion'),
(16, 43, 'Tooth Restoration', NULL, NULL, 'Bonding Agent', 1, 24, '2025-10-19 14:39:41', 'success', 'Automatic deduction for treatment completion'),
(17, 43, 'Tooth Restoration', NULL, NULL, 'Composite Resin', 1, 49, '2025-10-19 14:39:41', 'success', 'Automatic deduction for treatment completion'),
(18, 43, 'Tooth Restoration', NULL, NULL, 'Cotton Rolls', 4, 484, '2025-10-19 14:39:41', 'success', 'Automatic deduction for treatment completion'),
(19, 43, 'Tooth Restoration', NULL, NULL, 'Etching Gel', 1, 29, '2025-10-19 14:39:41', 'success', 'Automatic deduction for treatment completion'),
(20, 44, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Cotton Rolls', 5, 479, '2025-10-20 10:57:43', 'success', 'Automatic deduction for treatment completion'),
(21, 44, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Mouth Mirror', 1, 16, '2025-10-20 10:57:43', 'success', 'Automatic deduction for treatment completion'),
(22, 44, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Saliva Ejector', 1, 97, '2025-10-20 10:57:43', 'success', 'Automatic deduction for treatment completion'),
(23, 44, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Scaler Tip', 1, 12, '2025-10-20 10:57:43', 'success', 'Automatic deduction for treatment completion'),
(24, 48, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Cotton Rolls', 5, 474, '2025-10-21 12:34:18', 'success', 'Automatic deduction for treatment completion'),
(25, 48, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Mouth Mirror', 1, 15, '2025-10-21 12:34:18', 'success', 'Automatic deduction for treatment completion'),
(26, 48, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Saliva Ejector', 1, 96, '2025-10-21 12:34:18', 'success', 'Automatic deduction for treatment completion'),
(27, 48, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Scaler Tip', 1, 11, '2025-10-21 12:34:18', 'success', 'Automatic deduction for treatment completion'),
(28, 45, 'Tooth Restoration', NULL, NULL, 'Bonding Agent', 1, 23, '2025-10-21 12:34:22', 'success', 'Automatic deduction for treatment completion'),
(29, 45, 'Tooth Restoration', NULL, NULL, 'Composite Resin', 1, 48, '2025-10-21 12:34:22', 'success', 'Automatic deduction for treatment completion'),
(30, 45, 'Tooth Restoration', NULL, NULL, 'Cotton Rolls', 4, 470, '2025-10-21 12:34:22', 'success', 'Automatic deduction for treatment completion'),
(31, 45, 'Tooth Restoration', NULL, NULL, 'Etching Gel', 1, 28, '2025-10-21 12:34:22', 'success', 'Automatic deduction for treatment completion'),
(32, 51, 'Brace Adjustment', NULL, NULL, 'Archwire', 1, 97, '2025-10-27 10:49:53', 'success', 'Automatic deduction for treatment completion'),
(33, 51, 'Brace Adjustment', NULL, NULL, 'Ligature Elastic', 10, 970, '2025-10-27 10:49:53', 'success', 'Automatic deduction for treatment completion'),
(34, 56, 'Brace Adjustment', NULL, NULL, 'Archwire', 1, 96, '2025-10-27 10:50:23', 'success', 'Automatic deduction for treatment completion'),
(35, 56, 'Brace Adjustment', NULL, NULL, 'Ligature Elastic', 10, 960, '2025-10-27 10:50:23', 'success', 'Automatic deduction for treatment completion'),
(36, 61, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Cotton Rolls', 5, 465, '2025-10-27 15:33:57', 'success', 'Automatic deduction for treatment completion'),
(37, 61, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Mouth Mirror', 1, 14, '2025-10-27 15:33:57', 'success', 'Automatic deduction for treatment completion'),
(38, 61, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Saliva Ejector', 1, 95, '2025-10-27 15:33:57', 'success', 'Automatic deduction for treatment completion'),
(39, 61, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Scaler Tip', 1, 10, '2025-10-27 15:33:57', 'success', 'Automatic deduction for treatment completion'),
(40, 62, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Cotton Rolls', 5, 460, '2025-11-05 13:59:16', 'success', 'Automatic deduction for treatment completion'),
(41, 62, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Mouth Mirror', 1, 99, '2025-11-05 13:59:16', 'success', 'Automatic deduction for treatment completion'),
(42, 62, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Saliva Ejector', 1, 94, '2025-11-05 13:59:16', 'success', 'Automatic deduction for treatment completion'),
(43, 62, 'Oral Prophylaxis (Cleaning)', NULL, NULL, 'Scaler Tip', 1, 9, '2025-11-05 13:59:16', 'success', 'Automatic deduction for treatment completion'),
(44, 66, 'Oral Surgery', NULL, NULL, 'Anesthetic Cartridge', 1, 9, '2025-11-07 00:52:03', 'success', 'Automatic deduction for treatment completion'),
(45, 66, 'Oral Surgery', NULL, NULL, 'Gauze Pad', 5, 295, '2025-11-07 00:52:03', 'success', 'Automatic deduction for treatment completion'),
(46, 66, 'Oral Surgery', NULL, NULL, 'Surgical Blade', 1, 99, '2025-11-07 00:52:03', 'success', 'Automatic deduction for treatment completion'),
(47, 66, 'Oral Surgery', NULL, NULL, 'Suture Thread', 1, 49, '2025-11-07 00:52:03', 'success', 'Automatic deduction for treatment completion'),
(48, 88, 'Teeth Whitening', NULL, NULL, 'Cheek Retractor', 1, 19, '2025-11-24 11:59:35', 'success', 'Automatic deduction for treatment completion'),
(49, 88, 'Teeth Whitening', NULL, NULL, 'Cotton Rolls', 3, 457, '2025-11-24 11:59:35', 'success', 'Automatic deduction for treatment completion'),
(50, 88, 'Teeth Whitening', NULL, NULL, 'Whitening Gel', 1, 29, '2025-11-24 11:59:35', 'success', 'Automatic deduction for treatment completion'),
(51, 91, 'Upper Braces', NULL, NULL, 'Archwire (Upper)', 1, 59, '2025-11-24 11:59:36', 'success', 'Automatic deduction for treatment completion'),
(52, 91, 'Upper Braces', NULL, NULL, 'Bracket Set (Upper)', 1, 19, '2025-11-24 11:59:36', 'success', 'Automatic deduction for treatment completion'),
(53, 91, 'Upper Braces', NULL, NULL, 'Ligature Elastic', 10, 950, '2025-11-24 11:59:36', 'success', 'Automatic deduction for treatment completion'),
(54, 89, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', NULL, NULL, 'Cotton Rolls', 4, 453, '2025-11-24 11:59:37', 'success', 'Automatic deduction for treatment completion'),
(55, 89, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', NULL, NULL, 'Gauze Pads', 2, 98, '2025-11-24 11:59:37', 'success', 'Automatic deduction for treatment completion'),
(56, 89, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', NULL, NULL, 'Gloves', 1, 9, '2025-11-24 11:59:37', 'success', 'Automatic deduction for treatment completion'),
(57, 89, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', NULL, NULL, 'Lidocaine (Anesthetic)', 1, 99, '2025-11-24 11:59:37', 'success', 'Automatic deduction for treatment completion'),
(58, 89, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', NULL, NULL, 'Syringe', 1, 99, '2025-11-24 11:59:37', 'success', 'Automatic deduction for treatment completion'),
(59, 90, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', NULL, NULL, 'Cotton Rolls', 4, 449, '2025-11-24 11:59:38', 'success', 'Automatic deduction for treatment completion'),
(60, 90, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', NULL, NULL, 'Gauze Pads', 2, 96, '2025-11-24 11:59:38', 'success', 'Automatic deduction for treatment completion'),
(61, 90, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', NULL, NULL, 'Gloves', 1, 8, '2025-11-24 11:59:38', 'success', 'Automatic deduction for treatment completion'),
(62, 90, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', NULL, NULL, 'Lidocaine (Anesthetic)', 1, 98, '2025-11-24 11:59:38', 'success', 'Automatic deduction for treatment completion'),
(63, 90, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', NULL, NULL, 'Syringe', 1, 98, '2025-11-24 11:59:38', 'success', 'Automatic deduction for treatment completion'),
(64, 99, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', NULL, NULL, 'Cotton Rolls', 4, 445, '2025-11-24 15:15:39', 'success', 'Automatic deduction for treatment completion'),
(65, 99, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', NULL, NULL, 'Gauze Pads', 2, 94, '2025-11-24 15:15:39', 'success', 'Automatic deduction for treatment completion'),
(66, 99, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', NULL, NULL, 'Gloves', 1, 79, '2025-11-24 15:15:39', 'success', 'Automatic deduction for treatment completion'),
(67, 99, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', NULL, NULL, 'Lidocaine (Anesthetic)', 1, 97, '2025-11-24 15:15:39', 'success', 'Automatic deduction for treatment completion'),
(68, 99, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', NULL, NULL, 'Syringe', 1, 97, '2025-11-24 15:15:39', 'success', 'Automatic deduction for treatment completion'),
(69, 101, 'Tooth Extraction, Tooth Restoration', NULL, NULL, 'Cotton Rolls', 4, 441, '2025-11-24 15:15:40', 'success', 'Automatic deduction for treatment completion'),
(70, 101, 'Tooth Extraction, Tooth Restoration', NULL, NULL, 'Gauze Pads', 2, 92, '2025-11-24 15:15:40', 'success', 'Automatic deduction for treatment completion'),
(71, 101, 'Tooth Extraction, Tooth Restoration', NULL, NULL, 'Gloves', 1, 78, '2025-11-24 15:15:40', 'success', 'Automatic deduction for treatment completion'),
(72, 101, 'Tooth Extraction, Tooth Restoration', NULL, NULL, 'Lidocaine (Anesthetic)', 1, 96, '2025-11-24 15:15:40', 'success', 'Automatic deduction for treatment completion'),
(73, 101, 'Tooth Extraction, Tooth Restoration', NULL, NULL, 'Syringe', 1, 96, '2025-11-24 15:15:40', 'success', 'Automatic deduction for treatment completion'),
(74, 98, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces, \'.htmlspecialchars($opt).\'', NULL, NULL, 'Cotton Rolls', 4, 437, '2025-11-24 15:15:41', 'success', 'Automatic deduction for treatment completion'),
(75, 98, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces, \'.htmlspecialchars($opt).\'', NULL, NULL, 'Gauze Pads', 2, 90, '2025-11-24 15:15:41', 'success', 'Automatic deduction for treatment completion'),
(76, 98, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces, \'.htmlspecialchars($opt).\'', NULL, NULL, 'Gloves', 1, 77, '2025-11-24 15:15:41', 'success', 'Automatic deduction for treatment completion'),
(77, 98, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces, \'.htmlspecialchars($opt).\'', NULL, NULL, 'Lidocaine (Anesthetic)', 1, 95, '2025-11-24 15:15:41', 'success', 'Automatic deduction for treatment completion'),
(78, 98, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces, \'.htmlspecialchars($opt).\'', NULL, NULL, 'Syringe', 1, 95, '2025-11-24 15:15:41', 'success', 'Automatic deduction for treatment completion'),
(79, 102, 'Brace Adjustment', NULL, NULL, 'Archwire', 1, 89, '2025-11-26 03:31:07', 'success', 'Automatic deduction for treatment completion'),
(80, 102, 'Brace Adjustment', NULL, NULL, 'Ligature Elastic', 10, 940, '2025-11-26 03:31:07', 'success', 'Automatic deduction for treatment completion');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_inventory_restock_log`
--

CREATE TABLE `tbl_inventory_restock_log` (
  `Restock_Id` int(11) NOT NULL,
  `Item_Id` int(11) NOT NULL,
  `Item_Name` varchar(255) NOT NULL,
  `Quantity_Added` decimal(12,3) NOT NULL DEFAULT 0.000,
  `Previous_Stock` decimal(12,3) NOT NULL DEFAULT 0.000,
  `New_Stock` decimal(12,3) NOT NULL DEFAULT 0.000,
  `Restocked_By` varchar(100) NOT NULL DEFAULT 'Admin',
  `Notes` varchar(255) DEFAULT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_messages`
--

CREATE TABLE `tbl_messages` (
  `Id` int(11) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Sender` enum('Patient','Admin','Dentist') NOT NULL,
  `Message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_notifications`
--

CREATE TABLE `tbl_notifications` (
  `Id` int(11) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Admin_Id` int(11) DEFAULT NULL,
  `Message` text NOT NULL,
  `Type` varchar(64) NOT NULL,
  `Is_Read` tinyint(1) NOT NULL DEFAULT 0,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_notifications`
--

INSERT INTO `tbl_notifications` (`Id`, `Email`, `Admin_Id`, `Message`, `Type`, `Is_Read`, `Created_At`) VALUES
(1, 'walkin+ryuoo+09221212212@clinic.local', NULL, 'Your appointment for Teeth Whitening on Oct 15, 2025 at 7:00 AM has been CONFIRMED by admin.', 'appointment_confirmed', 0, '2025-10-14 16:44:00'),
(2, 'walkin+ryuoo+09221212212@clinic.local', NULL, 'Your appointment for Teeth Whitening on Oct 15, 2025 at 7:00 AM has been CONFIRMED by admin.', 'appointment_confirmed', 0, '2025-10-14 17:37:18'),
(3, 'walkin+ryuoo+09221212212@clinic.local', NULL, 'Your appointment for Teeth Whitening on Oct 15, 2025 at 7:00 AM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 0, '2025-10-14 19:11:48'),
(6, 'walkin+summertime+09888323232@clinic.local', NULL, 'Your appointment for Oral Prophylaxis (Cleaning) on Oct 15, 2025 at 7:25 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-10-15 04:10:11'),
(7, 'walkin+summertime+09888323232@clinic.local', NULL, 'Your appointment for Brace Adjustment on Oct 15, 2025 at 1:10 PM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 0, '2025-10-15 04:10:28'),
(8, 'walkin1760536750_72298e05@clinic.local', NULL, 'Your appointment for Check-Up on Oct 16, 2025 at 8:00 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-10-16 01:34:14'),
(9, 'walkin1760579417_904e552c@clinic.local', NULL, 'Your appointment for Check-Up on Oct 16, 2025 at 10:00 AM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 0, '2025-10-16 01:50:49'),
(13, 'gwc.manuel@gmail.com', NULL, 'Your appointment for  on Oct 16, 2025 at 3:05 PM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-10-16 07:06:55'),
(14, 'gwc.manuel@gmail.com', NULL, 'Your appointment for  on Oct 20, 2025 at 7:00 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-10-16 07:07:05'),
(15, 'gwc.manuel@gmail.com', NULL, 'Your appointment for Tooth Extraction, Upper Braces, Lower Braces, Check-Up on Oct 20, 2025 at 7:00 AM has been CONFIRMED by admin.', 'appointment_confirmed', 0, '2025-10-16 07:08:30'),
(16, 'gwc.manuel@gmail.com', NULL, 'Your appointment for Tooth Restoration, Tooth Extraction on Oct 21, 2025 at 4:00 PM has been CONFIRMED by admin.', 'appointment_confirmed', 0, '2025-10-16 07:18:04'),
(17, 'gwc.manuel@gmail.com', NULL, 'Your appointment for Tooth Restoration, Tooth Extraction on Oct 21, 2025 at 4:00 PM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 0, '2025-10-17 15:30:57'),
(18, 'walkin1760599037_a10ed8c1@clinic.local', NULL, 'Your appointment for Oral Prophylaxis (Cleaning) on Oct 29, 2025 at 10:15 AM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 0, '2025-10-17 15:31:03'),
(19, 'miles@gmail.com', NULL, 'A patient cancelled an appointment on Oct 28, 2025 7:20 AM. Reason: Feeling unwell — testing2', 'patient_cancel', 0, '2025-10-17 15:38:06'),
(20, 'Admin@gmail.com', NULL, 'A patient cancelled an appointment on Oct 28, 2025 7:20 AM. Reason: Feeling unwell — testing2', 'patient_cancel_admin', 1, '2025-10-17 15:38:06'),
(21, 'Catherine@gmail.com', NULL, 'A patient cancelled an appointment on Oct 21, 2025 7:45 AM. Reason: Feeling unwell — test3', 'patient_cancel', 0, '2025-10-17 15:38:45'),
(22, 'Admin@gmail.com', NULL, 'A patient cancelled an appointment on Oct 21, 2025 7:45 AM. Reason: Feeling unwell — test3', 'patient_cancel_admin', 1, '2025-10-17 15:38:45'),
(23, 'Catherine@gmail.com', NULL, 'A patient cancelled an appointment on Oct 21, 2025 6:55 PM. Reason: Feeling unwell — test2', 'patient_cancel', 0, '2025-10-17 15:42:54'),
(24, 'Admin@gmail.com', NULL, 'A patient cancelled an appointment on Oct 21, 2025 6:55 PM. Reason: Feeling unwell — test2', 'patient_cancel_admin', 1, '2025-10-17 15:42:54'),
(25, 'miles@gmail.com', NULL, 'A patient cancelled an appointment on Oct 19, 2025 7:00 AM. Reason: Schedule conflict', 'patient_cancel', 0, '2025-10-17 15:49:08'),
(26, 'Admin@gmail.com', NULL, 'A patient cancelled an appointment on Oct 19, 2025 7:00 AM. Reason: Schedule conflict', 'patient_cancel_admin', 1, '2025-10-17 15:49:08'),
(27, 'Admin@gmail.com', NULL, 'New pending appointment: Brace Adjustment on Oct 28, 2025 7:20 AM (patient: Fernando Estrada).', 'patient_booking_pending', 1, '2025-10-17 15:52:19'),
(28, 'miles@gmail.com', NULL, 'New pending appointment: Brace Adjustment on Oct 28, 2025 7:20 AM (patient: Fernando Estrada).', 'patient_booking_pending', 0, '2025-10-17 15:52:19'),
(29, 'miles@gmail.com', NULL, 'A patient cancelled an appointment on Oct 28, 2025 7:20 AM. Reason: Feeling unwell — test4', 'patient_cancel', 0, '2025-10-17 15:52:31'),
(30, 'Admin@gmail.com', NULL, 'A patient cancelled an appointment on Oct 28, 2025 7:20 AM. Reason: Feeling unwell — test4', 'patient_cancel_admin', 1, '2025-10-17 15:52:31'),
(31, 'Admin@gmail.com', NULL, 'New pending appointment: Tooth Restoration on Oct 28, 2025 7:45 AM (patient: Fernando Estrada).', 'patient_booking_pending', 1, '2025-10-17 15:57:34'),
(32, 'miles@gmail.com', NULL, 'New pending appointment: Tooth Restoration on Oct 28, 2025 7:45 AM (patient: Fernando Estrada).', 'patient_booking_pending', 0, '2025-10-17 15:57:34'),
(33, 'miles@gmail.com', NULL, 'A patient cancelled an appointment on Oct 28, 2025 7:45 AM. Reason: Transportation issue — test5', 'patient_cancel', 0, '2025-10-17 15:58:02'),
(34, 'Admin@gmail.com', NULL, 'A patient cancelled an appointment on Oct 28, 2025 7:45 AM. Reason: Transportation issue — test5', 'patient_cancel_admin', 1, '2025-10-17 15:58:02'),
(35, 'Admin@gmail.com', NULL, 'New pending appointment: Panoramic X-Ray (X-Ray) on Oct 21, 2025 7:40 AM (patient: Fernando Estrada).', 'patient_booking_pending', 1, '2025-10-17 16:12:30'),
(36, 'Kyle@gmail.com', NULL, 'New pending appointment: Panoramic X-Ray (X-Ray) on Oct 21, 2025 7:40 AM (patient: Fernando Estrada).', 'patient_booking_pending', 0, '2025-10-17 16:12:30'),
(37, 'Kyle@gmail.com', NULL, 'Fernando Estrada cancelled Panoramic X-Ray (X-Ray) on Oct 21, 2025 7:40 AM. Reason: Schedule conflict — hi cutie', 'patient_cancel', 0, '2025-10-17 16:12:45'),
(38, 'Admin@gmail.com', NULL, 'Fernando Estrada cancelled Panoramic X-Ray (X-Ray) on Oct 21, 2025 7:40 AM. Reason: Schedule conflict — hi cutie', 'patient_cancel_admin', 1, '2025-10-17 16:12:45'),
(39, 'Admin@gmail.com', NULL, 'New pending appointment: Tooth Restoration on Oct 21, 2025 7:40 AM (patient: Fernando Estrada).', 'patient_booking_pending', 1, '2025-10-17 16:16:45'),
(40, 'Catherine@gmail.com', NULL, 'New pending appointment: Tooth Restoration on Oct 21, 2025 7:40 AM (patient: Fernando Estrada).', 'patient_booking_pending', 0, '2025-10-17 16:16:45'),
(41, 'Catherine@gmail.com', NULL, 'Fernando Estrada cancelled Tooth Restoration on Oct 21, 2025 7:40 AM. Reason: Schedule conflict', 'patient_cancel', 0, '2025-10-17 16:16:51'),
(42, 'Admin@gmail.com', NULL, 'Fernando Estrada cancelled Tooth Restoration on Oct 21, 2025 7:40 AM. Reason: Schedule conflict', 'patient_cancel_admin', 1, '2025-10-17 16:16:51'),
(43, 'Admin@gmail.com', 1, 'New pending appointment: Panoramic X-Ray (X-Ray) on Oct 27, 2025 7:45 AM (patient: Fernando Estrada).', 'patient_booking_pending', 1, '2025-10-17 16:25:15'),
(44, 'Kyle@gmail.com', NULL, 'New pending appointment: Panoramic X-Ray (X-Ray) on Oct 27, 2025 7:45 AM (patient: Fernando Estrada).', 'patient_booking_pending', 0, '2025-10-17 16:25:15'),
(45, 'Kyle@gmail.com', NULL, 'Fernando Estrada cancelled Panoramic X-Ray (X-Ray) on Oct 27, 2025 7:45 AM. Reason: Schedule conflict — test6', 'patient_cancel', 0, '2025-10-17 16:25:31'),
(46, 'Admin@gmail.com', NULL, 'Fernando Estrada cancelled Panoramic X-Ray (X-Ray) on Oct 27, 2025 7:45 AM. Reason: Schedule conflict — test6', 'patient_cancel_admin', 1, '2025-10-17 16:25:31'),
(47, 'Bernadine@gmail.com', NULL, 'Fernando Estrada cancelled Teeth Whitening on Oct 20, 2025 7:45 AM. Reason: Schedule conflict', 'patient_cancel', 0, '2025-10-17 16:29:04'),
(48, 'Admin@gmail.com', NULL, 'Fernando Estrada cancelled Teeth Whitening on Oct 20, 2025 7:45 AM. Reason: Schedule conflict', 'patient_cancel_admin', 1, '2025-10-17 16:29:04'),
(49, 'Kyle@gmail.com', NULL, 'Fernando Estrada cancelled Tooth Restoration on Oct 20, 2025 7:40 AM. Reason: Schedule conflict', 'patient_cancel', 0, '2025-10-17 16:32:20'),
(50, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Tooth Restoration on Oct 20, 2025 7:40 AM. Reason: Schedule conflict', 'patient_cancel_admin', 1, '2025-10-17 16:32:20'),
(51, 'Kyle@gmail.com', NULL, 'Fernando Estrada cancelled Tooth Restoration on Oct 26, 2025 7:40 AM. Reason: Feeling unwell — test2', 'patient_cancel', 0, '2025-10-17 16:36:33'),
(52, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Tooth Restoration on Oct 26, 2025 7:40 AM. Reason: Feeling unwell — test2', 'patient_cancel_admin', 1, '2025-10-17 16:36:33'),
(53, 'Kyle@gmail.com', NULL, 'Fernando Estrada cancelled Brace Adjustment on Oct 27, 2025 7:40 AM. Reason: Feeling unwell', 'patient_cancel', 0, '2025-10-17 16:37:23'),
(54, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Brace Adjustment on Oct 27, 2025 7:40 AM. Reason: Feeling unwell', 'patient_cancel_admin', 1, '2025-10-17 16:37:23'),
(55, 'Bernadine@gmail.com', NULL, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning) on Oct 27, 2025 7:20 AM. Reason: Schedule conflict', 'patient_cancel', 0, '2025-10-17 16:47:26'),
(56, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning) on Oct 27, 2025 7:20 AM. Reason: Schedule conflict', 'patient_cancel_admin', 1, '2025-10-17 16:47:26'),
(57, 'Bernadine@gmail.com', NULL, 'Fernando Estrada cancelled Tooth Restoration on Oct 20, 2025 7:40 AM. Reason: Schedule conflict', 'patient_cancel', 0, '2025-10-17 17:54:46'),
(58, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Tooth Restoration on Oct 20, 2025 7:40 AM. Reason: Schedule conflict', 'patient_cancel_admin', 1, '2025-10-17 17:54:46'),
(59, 'Kyle@gmail.com', NULL, 'Fernando Estrada cancelled Panoramic X-Ray (X-Ray) on Oct 27, 2025 7:45 AM. Reason: Feeling unwell', 'patient_cancel', 0, '2025-10-17 17:57:08'),
(60, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Panoramic X-Ray (X-Ray) on Oct 27, 2025 7:45 AM. Reason: Feeling unwell', 'patient_cancel_admin', 1, '2025-10-17 17:57:08'),
(61, 'miles@gmail.com', NULL, 'Garp Monkey cancelled Brace Adjustment on Oct 19, 2025 8:40 AM. Reason: Schedule conflict', 'patient_cancel', 0, '2025-10-18 11:42:25'),
(62, 'Admin@gmail.com', 1, 'Garp Monkey cancelled Brace Adjustment on Oct 19, 2025 8:40 AM. Reason: Schedule conflict', 'patient_cancel_admin', 1, '2025-10-18 11:42:25'),
(63, 'Kyle@gmail.com', NULL, 'Garp Monkey cancelled Brace Adjustment on Oct 19, 2025 9:40 AM. Reason: Feeling unwell', 'patient_cancel', 0, '2025-10-18 11:46:52'),
(64, 'Admin@gmail.com', 1, 'Garp Monkey cancelled Brace Adjustment on Oct 19, 2025 9:40 AM. Reason: Feeling unwell', 'patient_cancel_admin', 1, '2025-10-18 11:46:52'),
(65, 'fme090909@gmail.com', NULL, 'Your appointment for Tooth Restoration on Oct 19, 2025 at 7:40 AM has been CONFIRMED by admin.', 'appointment_confirmed', 0, '2025-10-18 11:46:59'),
(66, 'Kyle@gmail.com', NULL, 'Garp Monkey cancelled Tooth Restoration on Oct 19, 2025 7:40 AM. Reason: Schedule conflict', 'patient_cancel', 0, '2025-10-18 11:47:37'),
(67, 'Admin@gmail.com', 1, 'Garp Monkey cancelled Tooth Restoration on Oct 19, 2025 7:40 AM. Reason: Schedule conflict', 'patient_cancel_admin', 1, '2025-10-18 11:47:37'),
(68, 'Catherine@gmail.com', NULL, 'Garp Monkey cancelled Tooth Extraction on Oct 19, 2025 10:30 AM. Reason: Feeling unwell', 'patient_cancel', 0, '2025-10-18 12:00:14'),
(69, 'Admin@gmail.com', 1, 'Garp Monkey cancelled Tooth Extraction on Oct 19, 2025 10:30 AM. Reason: Feeling unwell', 'patient_cancel_admin', 1, '2025-10-18 12:00:14'),
(70, 'fme090909@gmail.com', NULL, 'Your appointment for Brace Adjustment on Oct 27, 2025 at 7:25 AM has been CONFIRMED by admin.', 'appointment_confirmed', 0, '2025-10-18 12:00:52'),
(71, 'fme090909@gmail.com', NULL, 'Your appointment for Brace Adjustment on Oct 27, 2025 at 7:25 AM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 0, '2025-10-18 12:01:15'),
(72, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Oral Prophylaxis (Cleaning), Panoramic X-Ray (X-Ray), Brace Adjustment on Oct 28, 2025 at 7:20 AM has been CONFIRMED by admin.', 'appointment_confirmed', 1, '2025-10-18 12:49:42'),
(73, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Oral Prophylaxis (Cleaning), Panoramic X-Ray (X-Ray), Brace Adjustment on Oct 28, 2025 at 7:20 AM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 1, '2025-10-18 12:49:59'),
(74, 'fme090909@gmail.com', NULL, 'Your appointment for Oral Prophylaxis (Cleaning), Brace Adjustment on Oct 27, 2025 at 7:40 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-10-18 13:06:52'),
(75, 'miles@gmail.com', NULL, 'Fernando Estrada cancelled Brace Adjustment on Oct 19, 2025 4:35 PM. Reason: Schedule conflict', 'patient_cancel', 0, '2025-10-19 08:34:48'),
(76, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Brace Adjustment on Oct 19, 2025 4:35 PM. Reason: Schedule conflict', 'patient_cancel_admin', 1, '2025-10-19 08:34:48'),
(77, 'Kyle@gmail.com', NULL, 'Fernando Estrada cancelled Panoramic X-Ray (X-Ray) on Oct 19, 2025 5:00 PM. Reason: Feeling unwell', 'patient_cancel', 0, '2025-10-19 08:35:10'),
(78, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Panoramic X-Ray (X-Ray) on Oct 19, 2025 5:00 PM. Reason: Feeling unwell', 'patient_cancel_admin', 1, '2025-10-19 08:35:10'),
(79, 'miles@gmail.com', NULL, 'Fernando Estrada cancelled Tooth Restoration on Oct 20, 2025 9:45 AM. Reason: Feeling unwell', 'patient_cancel', 0, '2025-10-19 10:32:26'),
(80, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Tooth Restoration on Oct 20, 2025 9:45 AM. Reason: Feeling unwell', 'patient_cancel_admin', 1, '2025-10-19 10:32:26'),
(81, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Tooth Restoration on Oct 19, 2025 at 5:25 PM has been CONFIRMED by admin.', 'appointment_confirmed', 1, '2025-10-19 14:39:15'),
(82, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Tooth Restoration on Oct 19, 2025 at 5:25 PM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 1, '2025-10-19 14:39:41'),
(83, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Oral Prophylaxis (Cleaning) on Oct 20, 2025 at 6:45 PM has been CONFIRMED by admin.', 'appointment_confirmed', 1, '2025-10-20 10:55:21'),
(84, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Oral Prophylaxis (Cleaning) on Oct 20, 2025 at 6:45 PM has been CONFIRMED by admin.', 'appointment_confirmed', 1, '2025-10-20 10:55:26'),
(85, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Oral Prophylaxis (Cleaning) on Oct 20, 2025 at 6:45 PM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 1, '2025-10-20 10:57:43'),
(86, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Tooth Restoration on Oct 21, 2025 at 6:50 PM has been CONFIRMED by admin.', 'appointment_confirmed', 1, '2025-10-20 13:30:58'),
(87, 'miles@gmail.com', NULL, 'HONEY ABEN cancelled Full Braces on Oct 21, 2025 6:55 PM. Reason: Schedule conflict', 'patient_cancel', 0, '2025-10-20 13:31:41'),
(88, 'Admin@gmail.com', 1, 'HONEY ABEN cancelled Full Braces on Oct 21, 2025 6:55 PM. Reason: Schedule conflict', 'patient_cancel_admin', 1, '2025-10-20 13:31:41'),
(89, 'Bernadine@gmail.com', NULL, 'HONEY ABEN cancelled Panoramic X-Ray (X-Ray) on Oct 21, 2025 6:50 PM. Reason: Schedule conflict', 'patient_cancel', 0, '2025-10-20 13:40:54'),
(90, 'Admin@gmail.com', 1, 'HONEY ABEN cancelled Panoramic X-Ray (X-Ray) on Oct 21, 2025 6:50 PM. Reason: Schedule conflict', 'patient_cancel_admin', 1, '2025-10-20 13:40:54'),
(91, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Tooth Restoration on Oct 21, 2025 at 6:50 PM has been CONFIRMED by admin.', 'appointment_confirmed', 1, '2025-10-20 13:51:59'),
(92, 'sber33690@gmail.com', NULL, 'Your appointment for Oral Prophylaxis (Cleaning) on Oct 21, 2025 at 7:00 AM has been CONFIRMED by admin.', 'appointment_confirmed', 1, '2025-10-20 13:52:05'),
(93, 'sber33690@gmail.com', NULL, 'Your appointment for Oral Prophylaxis (Cleaning) on Oct 21, 2025 at 7:00 AM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 0, '2025-10-21 12:34:18'),
(94, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Tooth Restoration on Oct 21, 2025 at 6:50 PM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 1, '2025-10-21 12:34:22'),
(95, 'walkin1761051737_f51bfd58@clinic.local', NULL, 'Your appointment for Brace Adjustment has been RESCHEDULED to Oct 21, 2025 at 7:25 AM by admin.', 'appointment_rescheduled', 0, '2025-10-21 13:02:38'),
(96, 'walkin1761052052_f4d0b135@clinic.local', NULL, 'Your appointment for Check-Up on Oct 22, 2025 at 7:10 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-10-22 14:02:04'),
(97, 'walkin1761052083_84f26957@clinic.local', NULL, 'Your appointment for Brace Adjustment on Oct 22, 2025 at 7:20 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-10-22 14:02:05'),
(98, 'ryuonoou@gmail.com', NULL, 'Your appointment for Panoramic X-Ray (X-Ray) on Oct 22, 2025 at 7:45 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-10-22 14:02:05'),
(99, 'ryuonoou@gmail.com', NULL, 'Your appointment for Tooth Restoration on Oct 22, 2025 at 10:05 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-10-22 14:02:15'),
(100, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Oral Prophylaxis (Cleaning), Tooth Restoration, Tooth Extraction, Oral Surgery, Dentures, Panoramic X-Ray (X-Ray), Full Braces, Upper Braces, Lower Braces, Brace Adjustment, Teeth Whitening, Check-Up on Oct 24, 2025 at 7:00 AM has been CONFIRMED by admin.', 'appointment_confirmed', 1, '2025-10-22 14:20:46'),
(101, 'Kyle@gmail.com', NULL, 'Fernando Estrada cancelled Panoramic X-Ray (X-Ray) on Oct 23, 2025 3:30 PM. Reason: Schedule conflict', 'patient_cancel', 0, '2025-10-22 14:21:16'),
(103, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Panoramic X-Ray (X-Ray) on Oct 23, 2025 at 3:30 PM has been CANCELLED by admin.', 'appointment_cancelled', 1, '2025-10-22 14:31:59'),
(104, 'ryuonoou@gmail.com', NULL, 'Your appointment for Brace Adjustment on Oct 23, 2025 at 7:05 AM has been CONFIRMED by admin.', 'appointment_confirmed', 0, '2025-10-22 14:32:28'),
(105, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Oral Prophylaxis (Cleaning), Tooth Restoration, Tooth Extraction, Oral Surgery, Dentures, Panoramic X-Ray (X-Ray), Full Braces, Upper Braces, Lower Braces, Brace Adjustment, Teeth Whitening, Check-Up on Oct 25, 2025 at 8:00 AM has been CONFIRMED by admin.', 'appointment_confirmed', 1, '2025-10-22 15:00:05'),
(106, 'miles@gmail.com', NULL, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning), Tooth Restoration, Tooth Extraction, Oral Surgery, Dentures, Panoramic X-Ray (X-Ray), Full Braces, Upper Braces, Lower Braces, Brace Adjustment, Teeth Whitening, Check-Up on Oct 25, 2025 8:00 AM. Reason: Transportation issue', 'patient_cancel', 0, '2025-10-22 15:28:55'),
(108, 'Kyle@gmail.com', NULL, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning), Tooth Restoration, Tooth Extraction, Oral Surgery, Dentures, Panoramic X-Ray (X-Ray), Full Braces, Upper Braces, Lower Braces, Brace Adjustment, Teeth Whitening, Check-Up on Oct 24, 2025 7:00 AM. Reason: Feeling unwell', 'patient_cancel', 0, '2025-10-23 14:24:49'),
(109, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning), Tooth Restoration, Tooth Extraction, Oral Surgery, Dentures, Panoramic X-Ray (X-Ray), Full Braces, Upper Braces, Lower Braces, Brace Adjustment, Teeth Whitening, Check-Up on Oct 24, 2025 7:00 AM. Reason: Feeling unwell', 'patient_cancel_admin', 1, '2025-10-23 14:24:49'),
(110, 'walkin1761051737_f51bfd58@clinic.local', NULL, 'Your appointment for Brace Adjustment on Oct 21, 2025 at 7:25 AM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 0, '2025-10-27 10:49:53'),
(111, 'ryuonoou@gmail.com', NULL, 'Your appointment for Brace Adjustment on Oct 23, 2025 at 7:05 AM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 0, '2025-10-27 10:50:23'),
(112, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Tooth Extraction on Oct 28, 2025 at 7:05 AM has been CONFIRMED by admin.', 'appointment_confirmed', 1, '2025-10-27 15:00:47'),
(113, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Tooth Extraction on Oct 28, 2025 at 7:05 AM has been CANCELLED by admin.', 'appointment_cancelled', 1, '2025-10-27 15:21:22'),
(114, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Tooth Extraction on Oct 28, 2025 at 7:10 AM has been CONFIRMED by admin.', 'appointment_confirmed', 1, '2025-10-27 15:21:50'),
(115, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Tooth Extraction on Oct 28, 2025 at 7:10 AM has been CANCELLED by admin.', 'appointment_cancelled', 1, '2025-10-27 15:30:28'),
(116, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Tooth Extraction on Oct 28, 2025 at 7:15 AM has been CONFIRMED by admin.', 'appointment_confirmed', 1, '2025-10-27 15:31:51'),
(117, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Oral Prophylaxis (Cleaning) on Oct 30, 2025 at 7:50 AM has been CONFIRMED by admin.', 'appointment_confirmed', 1, '2025-10-27 15:33:48'),
(118, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Oral Prophylaxis (Cleaning) on Oct 30, 2025 at 7:50 AM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 1, '2025-10-27 15:33:57'),
(119, 'Kyle@gmail.com', NULL, 'Fernando Estrada cancelled Tooth Extraction on Oct 28, 2025 7:15 AM. Reason: Feeling unwell', 'patient_cancel', 0, '2025-10-27 15:56:22'),
(120, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Tooth Extraction on Oct 28, 2025 7:15 AM. Reason: Feeling unwell', 'patient_cancel_admin', 1, '2025-10-27 15:56:22'),
(121, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Oral Prophylaxis (Cleaning) on Nov 6, 2025 at 7:05 AM has been CONFIRMED by admin.', 'appointment_confirmed', 0, '2025-11-05 13:59:07'),
(122, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Oral Prophylaxis (Cleaning) on Nov 6, 2025 at 7:05 AM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 0, '2025-11-05 13:59:16'),
(123, 'Bernadine@gmail.com', NULL, 'Fernando Estrada cancelled Upper Braces on Nov 10, 2025 7:00 AM. Reason: Feeling unwell', 'patient_cancel', 0, '2025-11-07 00:28:49'),
(124, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Upper Braces on Nov 10, 2025 7:00 AM. Reason: Feeling unwell', 'patient_cancel_admin', 1, '2025-11-07 00:28:49'),
(125, 'Bernadine@gmail.com', NULL, 'Fernando Estrada cancelled Upper Braces on Nov 12, 2025 7:00 AM. Reason: Feeling unwell', 'patient_cancel', 0, '2025-11-07 00:29:22'),
(126, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Upper Braces on Nov 12, 2025 7:00 AM. Reason: Feeling unwell', 'patient_cancel_admin', 1, '2025-11-07 00:29:22'),
(127, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Panoramic X-Ray (X-Ray) on Nov 7, 2025 at 5:15 PM has been CONFIRMED by admin.', 'appointment_confirmed', 0, '2025-11-07 00:31:13'),
(128, 'miles@gmail.com', NULL, 'Fernando Estrada cancelled Panoramic X-Ray (X-Ray) on Nov 7, 2025 5:15 PM. Reason: Feeling unwell', 'patient_cancel', 0, '2025-11-07 00:32:03'),
(129, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Panoramic X-Ray (X-Ray) on Nov 7, 2025 5:15 PM. Reason: Feeling unwell', 'patient_cancel_admin', 1, '2025-11-07 00:32:03'),
(130, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Oral Surgery on Nov 7, 2025 at 8:55 AM has been CONFIRMED by admin.', 'appointment_confirmed', 0, '2025-11-07 00:51:39'),
(131, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Oral Surgery on Nov 7, 2025 at 8:55 AM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 0, '2025-11-07 00:52:03'),
(132, 'miles@gmail.com', NULL, 'Garp Monkey cancelled Oral Prophylaxis (Cleaning) on Nov 18, 2025 7:00 AM. Reason: Feeling unwell', 'patient_cancel', 0, '2025-11-17 12:03:39'),
(133, 'Admin@gmail.com', 1, 'Garp Monkey cancelled Oral Prophylaxis (Cleaning) on Nov 18, 2025 7:00 AM. Reason: Feeling unwell', 'patient_cancel_admin', 1, '2025-11-17 12:03:39'),
(134, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Oral Prophylaxis (Cleaning) on Nov 18, 2025 at 7:05 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-11-17 12:10:18'),
(135, 'miles@gmail.com', NULL, 'Garp Monkey cancelled Oral Prophylaxis (Cleaning) on Nov 18, 2025 7:00 AM. Reason: Schedule conflict', 'patient_cancel', 0, '2025-11-17 12:14:27'),
(136, 'Admin@gmail.com', 1, 'Garp Monkey cancelled Oral Prophylaxis (Cleaning) on Nov 18, 2025 7:00 AM. Reason: Schedule conflict', 'patient_cancel_admin', 1, '2025-11-17 12:14:27'),
(137, 'Bernadine@gmail.com', NULL, 'Garp Monkey cancelled Oral Prophylaxis (Cleaning) on Nov 18, 2025 7:50 AM. Reason: Feeling unwell', 'patient_cancel', 0, '2025-11-17 12:14:33'),
(138, 'Admin@gmail.com', 1, 'Garp Monkey cancelled Oral Prophylaxis (Cleaning) on Nov 18, 2025 7:50 AM. Reason: Feeling unwell', 'patient_cancel_admin', 1, '2025-11-17 12:14:33'),
(139, 'miles@gmail.com', NULL, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning) on Nov 18, 2025 7:05 AM. Reason: Feeling unwell', 'patient_cancel', 0, '2025-11-17 12:23:53'),
(140, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning) on Nov 18, 2025 7:05 AM. Reason: Feeling unwell', 'patient_cancel_admin', 1, '2025-11-17 12:23:53'),
(141, 'miles@gmail.com', NULL, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning) on Nov 18, 2025 7:00 AM. Reason: Transportation issue', 'patient_cancel', 0, '2025-11-17 12:24:49'),
(142, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning) on Nov 18, 2025 7:00 AM. Reason: Transportation issue', 'patient_cancel_admin', 1, '2025-11-17 12:24:49'),
(143, 'fme090909@gmail.com', NULL, 'Your appointment for Oral Prophylaxis (Cleaning) on Nov 18, 2025 at 7:00 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-11-17 12:25:13'),
(144, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Oral Prophylaxis (Cleaning) on Nov 19, 2025 at 7:00 AM has been CONFIRMED by admin.', 'appointment_confirmed', 0, '2025-11-17 12:26:01'),
(145, 'miles@gmail.com', NULL, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning) on Nov 19, 2025 7:00 AM. Reason: Feeling unwell', 'patient_cancel', 0, '2025-11-17 12:33:06'),
(146, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning) on Nov 19, 2025 7:00 AM. Reason: Feeling unwell', 'patient_cancel_admin', 1, '2025-11-17 12:33:06'),
(147, 'Catherine@gmail.com', NULL, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning) on Nov 18, 2025 7:00 AM. Reason: Schedule conflict', 'patient_cancel', 0, '2025-11-17 12:34:13'),
(148, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning) on Nov 18, 2025 7:00 AM. Reason: Schedule conflict', 'patient_cancel_admin', 1, '2025-11-17 12:34:13'),
(149, 'Catherine@gmail.com', NULL, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning) on Nov 18, 2025 7:00 AM. Reason: Feeling unwell', 'patient_cancel', 0, '2025-11-17 12:39:31'),
(150, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning) on Nov 18, 2025 7:00 AM. Reason: Feeling unwell', 'patient_cancel_admin', 1, '2025-11-17 12:39:31'),
(151, 'Catherine@gmail.com', NULL, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning) on Nov 18, 2025 7:00 AM. Reason: Schedule conflict', 'patient_cancel', 0, '2025-11-17 12:44:56'),
(152, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning) on Nov 18, 2025 7:00 AM. Reason: Schedule conflict', 'patient_cancel_admin', 1, '2025-11-17 12:44:56'),
(153, 'Catherine@gmail.com', NULL, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning), Tooth Restoration, Tooth Extraction, Oral Surgery, Dentures, Panoramic X-Ray (X-Ray), Full Braces, Upper Braces, Lower Braces, Brace Adjustment, Teeth Whitening, Check-Up on Nov 18, 2025 7:00 AM. Reason: Feeling unwell', 'patient_cancel', 0, '2025-11-17 14:07:09'),
(154, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning), Tooth Restoration, Tooth Extraction, Oral Surgery, Dentures, Panoramic X-Ray (X-Ray), Full Braces, Upper Braces, Lower Braces, Brace Adjustment, Teeth Whitening, Check-Up on Nov 18, 2025 7:00 AM. Reason: Feeling unwell', 'patient_cancel_admin', 1, '2025-11-17 14:07:09'),
(155, 'walkin1763383770_7189e278@clinic.local', NULL, 'Your appointment for Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces on Nov 23, 2025 at 7:00 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-11-22 10:46:08'),
(156, 'walkin1763807414_2ccff332@clinic.local', NULL, 'Your appointment for Brace Adjustment, Tooth Extraction on Nov 23, 2025 at 7:00 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-11-22 10:46:58'),
(157, 'walkin1763383770_7189e278@clinic.local', NULL, 'Your appointment for Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces on Nov 24, 2025 at 7:00 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-11-23 13:34:56'),
(158, 'Bernadine@gmail.com', NULL, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning), Tooth Restoration, Tooth Extraction, Oral Surgery, Dentures, Panoramic X-Ray (X-Ray), Full Braces, Upper Braces, Lower Braces, Brace Adjustment, Teeth Whitening, Check-Up on Nov 25, 2025 7:00 AM. Reason: Schedule conflict', 'patient_cancel', 0, '2025-11-23 13:38:49'),
(159, 'Admin@gmail.com', 1, 'Fernando Estrada cancelled Oral Prophylaxis (Cleaning), Tooth Restoration, Tooth Extraction, Oral Surgery, Dentures, Panoramic X-Ray (X-Ray), Full Braces, Upper Braces, Lower Braces, Brace Adjustment, Teeth Whitening, Check-Up on Nov 25, 2025 7:00 AM. Reason: Schedule conflict', 'patient_cancel_admin', 0, '2025-11-23 13:38:49'),
(160, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Full Braces on Nov 26, 2025 at 8:00 AM has been CONFIRMED by admin.', 'appointment_confirmed', 0, '2025-11-24 06:53:00'),
(161, 'walkin1763383770_7189e278@clinic.local', NULL, 'Your appointment for Full Braces on Nov 26, 2025 at 8:00 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-11-24 06:53:13'),
(162, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Full Braces on Nov 26, 2025 at 8:00 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-11-24 06:53:41'),
(163, 'walkin1763383770_7189e278@clinic.local', NULL, 'Your appointment for Full Braces on Nov 26, 2025 at 8:00 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-11-24 06:56:17'),
(164, 'walkin1763383770_7189e278@clinic.local', NULL, 'Your appointment for Full Braces on Nov 27, 2025 at 10:00 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-11-24 08:45:43'),
(165, 'nightmarefox50@gmail.com', NULL, 'Your appointment for Full Braces on Nov 27, 2025 at 8:00 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-11-24 08:45:44'),
(166, 'walkin1763974193_d3a67736@clinic.local', NULL, 'Your appointment for Teeth Whitening on Nov 24, 2025 at 4:50 PM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 0, '2025-11-24 11:59:35'),
(167, 'walkin1763383770_7189e278@clinic.local', NULL, 'Your appointment for Upper Braces on Nov 24, 2025 at 5:05 PM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 0, '2025-11-24 11:59:36'),
(168, 'walkin1763383770_7189e278@clinic.local', NULL, 'Your appointment for Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces on Nov 25, 2025 at 7:00 AM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 0, '2025-11-24 11:59:37'),
(169, 'walkin1763974306_b2298ccd@clinic.local', NULL, 'Your appointment for Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces on Nov 25, 2025 at 7:00 AM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 0, '2025-11-24 11:59:38'),
(170, 'walkin1763985601_cb0ae58f@clinic.local', NULL, 'Your appointment for Brace Adjustment on Nov 28, 2025 at 7:00 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-11-24 12:04:29'),
(171, 'walkin1763985884_eb855303@clinic.local', NULL, 'Your appointment for Brace Adjustment on Nov 29, 2025 at 7:00 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-11-24 12:04:52'),
(172, 'walkin1763985910_ab024527@clinic.local', NULL, 'Your appointment for Brace Adjustment on Nov 26, 2025 at 7:30 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-11-24 12:05:15'),
(173, 'walkin1763986109_e52accff@clinic.local', NULL, 'Your appointment for Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces on Nov 26, 2025 at 7:00 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-11-24 14:23:56'),
(174, 'walkin1763985952_bb85586a@clinic.local', NULL, 'Your appointment for Brace Adjustment on Nov 26, 2025 at 11:45 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-11-24 14:23:57'),
(175, 'walkin1763985601_cb0ae58f@clinic.local', NULL, 'Your appointment for Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces on Nov 28, 2025 at 7:00 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-11-24 14:23:59'),
(176, 'walkin1763994369_6fdab847@clinic.local', NULL, 'Your appointment for Brace Adjustment on Nov 25, 2025 at 7:00 AM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-11-24 14:26:16'),
(177, 'walkin1763994319_8ec0c08c@clinic.local', NULL, 'Your appointment for Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces on Nov 26, 2025 at 7:05 AM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 0, '2025-11-24 15:15:39'),
(178, 'walkin1763994406_cd4a8fd6@clinic.local', NULL, 'Your appointment for Tooth Extraction, Tooth Restoration on Nov 26, 2025 at 7:35 AM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 0, '2025-11-24 15:15:40'),
(179, 'walkin1763985601_cb0ae58f@clinic.local', NULL, 'Your appointment for Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces, \'.htmlspecialchars($opt).\' on Nov 29, 2025 at 7:00 AM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 0, '2025-11-24 15:15:41'),
(180, 'walkin1764023215_c23ade19@clinic.local', NULL, 'Your appointment for Brace Adjustment on Nov 25, 2025 at 7:00 AM has been COMPLETED. Thank you for choosing our clinic!', 'appointment_completed', 0, '2025-11-26 03:31:07'),
(181, 'walkin1764226685_f2d85c9e@clinic.local', NULL, 'Your appointment for Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces on Nov 27, 2025 at 3:00 PM has been CANCELLED by admin.', 'appointment_cancelled', 0, '2025-11-27 06:58:21'),
(182, 'lawrencevalderama.bscs.pass@gmail.com', NULL, 'Your appointment for Full Braces, Lower Braces, Check-Up on Nov 27, 2025 at 6:50 PM has been CONFIRMED by admin.', 'appointment_confirmed', 0, '2025-11-27 11:52:06'),
(183, 'miles@gmail.com', NULL, 'Lawrence Valderama cancelled Lower Braces on Nov 28, 2025 1:25 PM. Reason: Feeling unwell', 'patient_cancel', 0, '2025-12-04 10:47:02'),
(184, 'Admin@gmail.com', 1, 'Lawrence Valderama cancelled Lower Braces on Nov 28, 2025 1:25 PM. Reason: Feeling unwell', 'patient_cancel_admin', 0, '2025-12-04 10:47:02'),
(185, 'miles@gmail.com', NULL, 'Lawrence Valderama cancelled Full Braces, Lower Braces, Check-Up on Nov 27, 2025 6:50 PM. Reason: Feeling unwell', 'patient_cancel', 0, '2025-12-04 10:47:15'),
(186, 'Admin@gmail.com', 1, 'Lawrence Valderama cancelled Full Braces, Lower Braces, Check-Up on Nov 27, 2025 6:50 PM. Reason: Feeling unwell', 'patient_cancel_admin', 0, '2025-12-04 10:47:15'),
(187, 'lawrencevalderama.bscs.pass@gmail.com', NULL, 'Your appointment for Brace Adjustment on Dec 6, 2025 at 8:25 AM has been CONFIRMED by admin.', 'appointment_confirmed', 0, '2025-12-04 10:49:40'),
(188, 'lawrencevalderama.bscs.pass@gmail.com', NULL, 'Your appointment for Brace Adjustment on Dec 6, 2025 at 8:25 AM has been CONFIRMED by admin.', 'appointment_confirmed', 0, '2025-12-04 10:50:01'),
(189, 'lawrencevalderama.bscs.pass@gmail.com', NULL, 'Your appointment for Brace Adjustment on Dec 6, 2025 at 8:25 AM has been CONFIRMED by admin.', 'appointment_confirmed', 0, '2025-12-04 10:50:36'),
(190, 'lawrencevalderama.bscs.pass@gmail.com', NULL, 'Your appointment for Brace Adjustment on Dec 11, 2025 at 9:25 AM has been CONFIRMED by admin.', 'appointment_confirmed', 0, '2025-12-04 10:54:41'),
(191, 'baromargkrulak19@gmail.com', NULL, 'Your appointment for Upper Braces on Dec 6, 2025 at 9:05 AM has been CONFIRMED by admin.', 'appointment_confirmed', 0, '2025-12-04 11:17:29');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_patient`
--

CREATE TABLE `tbl_patient` (
  `Patient_Id` int(11) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Password` varchar(255) DEFAULT NULL,
  `First_name` varchar(100) NOT NULL,
  `Middle_name` varchar(100) DEFAULT NULL,
  `Last_name` varchar(100) NOT NULL,
  `Suffix` varchar(20) DEFAULT NULL,
  `Gender` enum('Male','Female','Other') DEFAULT NULL,
  `Address` varchar(500) DEFAULT NULL,
  `Status` varchar(50) NOT NULL DEFAULT 'Active',
  `Email_Verified` tinyint(1) NOT NULL DEFAULT 0,
  `Verification_Code` varchar(16) DEFAULT NULL,
  `Verification_Expires` datetime DEFAULT NULL,
  `otp_code` varchar(16) DEFAULT NULL,
  `otp_expiration` datetime DEFAULT NULL,
  `Photo_url` varchar(200) DEFAULT NULL,
  `Phone_num` varchar(20) NOT NULL,
  `bday` date DEFAULT NULL,
  `Patient_Type` enum('Online','Walk-in') DEFAULT 'Online'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_patient`
--

INSERT INTO `tbl_patient` (`Patient_Id`, `Email`, `Password`, `First_name`, `Middle_name`, `Last_name`, `Suffix`, `Gender`, `Address`, `Status`, `Email_Verified`, `Verification_Code`, `Verification_Expires`, `otp_code`, `otp_expiration`, `Photo_url`, `Phone_num`, `bday`, `Patient_Type`) VALUES
(1, 'nightmarefox50@gmail.com', '$2y$10$zh2GU57XaVdl.kXR48L7PetyIF2IH.EqtJGC2KCkP8vSaW739pQbW', 'Fernando', 'Majistrado', 'Estrada', '', 'Male', 'eastblue', 'Active', 1, NULL, NULL, NULL, NULL, 'uploads/patient_photos/nightmarefox50_gmail_com_1760862510.png', '09616397862', '2004-01-04', 'Online'),
(2, 'walkin+ryuoo+09221212212@clinic.local', '$2y$10$8mudT39E.GxmjZQGHD/HdeTBq9H.PcQoC67c7OkU.n.a21qcA3Rm.', 'ryu', 'Dawn', 'oo', NULL, NULL, NULL, 'Active', 1, NULL, NULL, NULL, NULL, NULL, '', NULL, 'Walk-in'),
(3, 'walkin+summertime+09888323232@clinic.local', '$2y$10$S511ic6wp6HK5nLTyATn8u2VEAjZs26.Ri8rd6cpERCohejQax/em', 'summer', '', 'time', NULL, NULL, NULL, 'Active', 1, NULL, NULL, NULL, NULL, NULL, '', NULL, 'Walk-in'),
(4, 'walkin1760536750_72298e05@clinic.local', '$2y$10$2WCg883Pxa14aEBt1iu1WeTZs5xmcCXvWDjICbw9X9g6mXDlX7QEm', 'levi', '', 'Ackerman', NULL, NULL, NULL, 'Active', 1, NULL, NULL, NULL, NULL, NULL, '', NULL, 'Walk-in'),
(5, 'walkin1760579417_904e552c@clinic.local', '$2y$10$7XuA5V4/wy8eEqqBPB2VRuniQ.KYPP8iyLbYLnLgpcjMLpGM44Lbm', 'Richard', '', 'Basila', NULL, NULL, NULL, 'Active', 1, NULL, NULL, NULL, NULL, NULL, '', NULL, 'Walk-in'),
(6, 'gwc.manuel@gmail.com', '$2y$10$.O0RUF3UW3dTGcBx/UWageBuETRkW0t54HPvFBTYHms9/azVziQr.', 'JUAN', 'Sanchez', 'pizarro', NULL, NULL, NULL, 'Active', 1, NULL, NULL, NULL, NULL, NULL, '', NULL, 'Online'),
(7, 'walkin1760599037_a10ed8c1@clinic.local', '$2y$10$loDlARwH7F/1mA.tu8H5C.zxzPShmq0rg070QFcG2Ztrw/sJaNCze', 'zyzy', '', 'Balagot', NULL, NULL, NULL, 'Active', 1, NULL, NULL, NULL, NULL, NULL, '', NULL, 'Walk-in'),
(8, 'fme090909@gmail.com', '$2y$10$vy2V/XSHMhYC9nDsv3rIG.ufSt46xxQASrWKEpFqVSpAzmsm11ivG', 'Garp', 'D', 'Monkey', NULL, NULL, NULL, 'Active', 1, NULL, NULL, NULL, NULL, NULL, '', NULL, 'Online'),
(9, 'sber33690@gmail.com', '$2y$10$ncvvceIDMZQJjHdnUnsrrusba7OTvWVbS.FXkKguRIGTF5wm2QQv.', 'HONEY', ' A.', 'ABEN', '', NULL, '', 'Active', 1, NULL, NULL, NULL, NULL, NULL, '09874737343', '2020-10-20', 'Online'),
(10, 'ryuonoou@gmail.com', '$2y$10$knyCkOXblXB7VVW9IEvOAuKkTguBAiu5BtiueYBYxZH6.vdFHsPdG', 'JUAN', 'Pizarro', 'Sanchez', NULL, 'Male', 'Ambabaay Bani, Pangasinan', 'Active', 1, NULL, NULL, '166189', '2026-06-26 18:25:47', NULL, '09811877761', '2020-01-10', 'Online'),
(11, 'walkin1761051737_f51bfd58@clinic.local', '$2y$10$SbECuBSx80iJudJ7VcvkYODGplEES88TcPcESr4tdqhtRLXvLKChm', 'max', '', 'level', NULL, NULL, NULL, 'Active', 1, NULL, NULL, NULL, NULL, NULL, '', NULL, 'Walk-in'),
(12, 'walkin1761052052_f4d0b135@clinic.local', '$2y$10$TlVcjIxp0aIeYjZgLxYlH.94B24U3FNqwezvjBH/ygQMMrBr/lbWu', 'ry', '', 'u', NULL, NULL, NULL, 'Active', 1, NULL, NULL, NULL, NULL, NULL, '', NULL, 'Walk-in'),
(13, 'walkin1761052083_84f26957@clinic.local', '$2y$10$ZTxu66nupXdNGTg5Ry4GrOcImcEyavzmWmGUOZJsmKJ5VzAphh5x6', 'wes', '', 'phi', NULL, NULL, NULL, 'Active', 1, NULL, NULL, NULL, NULL, NULL, '', NULL, 'Walk-in'),
(14, 'walkin1763383770_7189e278@clinic.local', '$2y$10$FOKEUBqP63givAefYUPm/OTkYjZvyyVfhcXI7NCaER5bWtwMl1cNu', 'lance', '', 'metro', NULL, NULL, 'Poblacion Mabini Pangasinan', 'Active', 1, NULL, NULL, NULL, NULL, NULL, '09121221211', NULL, 'Walk-in'),
(15, 'walkin1763807414_2ccff332@clinic.local', '$2y$10$XvMMJAClVY3..Bb1Pc.1PO.H/x4Wu2s/gYwo9B/t3TDRM6EyF.7lC', 'Luffy', 'Dawn', 'Monkey', NULL, NULL, 'eastblue', 'Active', 1, NULL, NULL, NULL, NULL, NULL, '09221212212', NULL, 'Walk-in'),
(16, 'walkin1763974193_d3a67736@clinic.local', '$2y$10$EC5hQ/nJdeVLPwits8hbjusxq/q.ICo.2VjK6WENAaqHAnZtOJTiW', 'levi', '', 'Ackerman', NULL, NULL, NULL, 'Active', 1, NULL, NULL, NULL, NULL, NULL, '09123453434', NULL, 'Walk-in'),
(17, 'walkin1763974306_b2298ccd@clinic.local', '$2y$10$Ix2DuD9OmCoYhkXguVs8AeueAAJGVjyEREKno.oY2ux.baPDH4hhy', 'Kairos', '', 'Molina', NULL, NULL, 'Poblacion Mabini Pangasinan', 'Active', 1, NULL, NULL, NULL, NULL, NULL, '09544443222', NULL, 'Walk-in'),
(18, 'walkin1763985601_cb0ae58f@clinic.local', '$2y$10$rHvaQur9Y5c5BHoG.N7YFuNODEb7wgJ.WHjv0Uk2kelHwLlY9MQ32', 'mia', 'A.', 'low', NULL, NULL, 'eastblue', 'Active', 1, NULL, NULL, NULL, NULL, NULL, '09121212124', NULL, 'Walk-in'),
(19, 'walkin1763985884_eb855303@clinic.local', '$2y$10$VKjXPQjBuSZ.CdexS7VXTusH906SBb8yjd9BMBlj586DNLOhULYoS', 'kara', '', 'low', NULL, NULL, 'eastblue', 'Active', 1, NULL, NULL, NULL, NULL, NULL, '09121212124', NULL, 'Walk-in'),
(20, 'walkin1763985910_ab024527@clinic.local', '$2y$10$9c9TMol3MhMRBTStJE/g4uOBy6EpJ40T96V067VQdzHs7G9PgrQui', 'kara', '', 'low', NULL, NULL, 'eastblue', 'Active', 1, NULL, NULL, NULL, NULL, NULL, '09121212124', NULL, 'Walk-in'),
(21, 'walkin1763985952_bb85586a@clinic.local', '$2y$10$eTIql5omjkRQyDv3hksuveUXWZ4Atzi0L1c09JBD1geZ3dHevWPN6', 'kara', '', 'low', NULL, NULL, 'eastblue', 'Active', 1, NULL, NULL, NULL, NULL, NULL, '09121212124', NULL, 'Walk-in'),
(22, 'walkin1763986109_e52accff@clinic.local', '$2y$10$6vmAHU.45MmNvgYyENOri.A13mlSh420qOjxyhSs1nBjHOrI4vkkC', 'cross', '', 'low', NULL, NULL, 'eastblue', 'Active', 1, NULL, NULL, NULL, NULL, NULL, '09121212124', NULL, 'Walk-in'),
(23, 'walkin1763994319_8ec0c08c@clinic.local', '$2y$10$KIpYRlhO06yfXZklZFKStu9dupyPmZneTkAtRUjS7K9s7oRVyFB9u', 'mika', '', 'low', NULL, NULL, 'eastblue', 'Active', 1, NULL, NULL, NULL, NULL, NULL, '09121212124', NULL, 'Walk-in'),
(24, 'walkin1763994369_6fdab847@clinic.local', '$2y$10$5C6DVP0mIInDbmb2kxNytOnhVqGeGzeG.qQOn9csyhypPX1./juDi', 'tres', '', 'martines', NULL, NULL, 'eastblue', 'Active', 1, NULL, NULL, NULL, NULL, NULL, '09121212124', NULL, 'Walk-in'),
(25, 'walkin1763994406_cd4a8fd6@clinic.local', '$2y$10$feG/4wyDRIens/8uqXBlseV4exMNGnoMSQwLIyYVorK/xYAT2HnAa', 'tres', '', 'martines', NULL, NULL, 'eastblue', 'Active', 1, NULL, NULL, NULL, NULL, NULL, '09121212124', NULL, 'Walk-in'),
(26, 'walkin1764023215_c23ade19@clinic.local', '$2y$10$O1s3dy8HHaAp/kvdnlkbT.vFq8nP5C.6t0smpYpbz8wyYS2eSG/JW', 'dragon', 'Dawn', 'Monkey', NULL, NULL, 'west', 'Active', 1, NULL, NULL, NULL, NULL, NULL, '09887886655', NULL, 'Walk-in'),
(27, 'walkin1764226685_f2d85c9e@clinic.local', '$2y$10$t7RokJD.zXIYlWH.f6Ghnu4TLhozluK3MQHin9WlQXDkmxNhMYLxm', 'haruto', '', 'amakawa', NULL, NULL, 'strhal', 'Active', 1, NULL, NULL, NULL, NULL, NULL, '09887886655', NULL, 'Walk-in'),
(28, 'lawrencevalderama.bscs.pass@gmail.com', '$2y$10$GgrEbzElJ46SS9mkRNKxs.MPLysIWGF8fVjCxOHsR/V8GRsZ75HWG', 'Lawrence', 'B.', 'Valderama', NULL, 'Male', 'Poblacion mabini pang', 'Active', 1, NULL, NULL, NULL, NULL, NULL, '09682058133', '2004-12-23', 'Online'),
(29, 'baromargkrulak19@gmail.com', '$2y$10$XhlrloCR0st6r5B1WdyFmeWrX7gBnarUW8EtTe6FD1Xk7bU/nxs1q', 'Rg ', NULL, 'Baroma', NULL, 'Male', 'Poblacion mabini pang', 'Active', 1, NULL, NULL, NULL, NULL, NULL, '09674906716', '2004-03-19', 'Online'),
(30, 'faaaannnyy@gmail.com', '$2y$10$teRzcwqaYduCML3x1f/PJ.SoAjXOc3iIfSOTEP3QDgI4GE5wwIaVK', 'lance', 'Dawn', 'metro', NULL, 'Male', 'Poblacion Mabini Pangasinan', 'Active', 1, NULL, NULL, NULL, NULL, NULL, '09121221211', '2000-12-02', 'Online');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_treatments`
--

CREATE TABLE `tbl_treatments` (
  `Treatment_Id` int(11) NOT NULL,
  `Name` varchar(255) NOT NULL,
  `Description` text DEFAULT NULL,
  `Active` tinyint(1) NOT NULL DEFAULT 1,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp(),
  `Updated_At` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `duration` int(11) NOT NULL DEFAULT 30 COMMENT 'Duration in minutes',
  `duration_text` varchar(100) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_treatments`
--

INSERT INTO `tbl_treatments` (`Treatment_Id`, `Name`, `Description`, `Active`, `Created_At`, `Updated_At`, `duration`, `duration_text`) VALUES
(1, 'Oral Prophylaxis (Cleaning)', 'Professional teeth cleaning and scaling', 1, '2025-10-14 15:53:22', '2025-10-22 13:51:06', 45, '45 minutes'),
(2, 'Tooth Restoration', 'Restoration/filling to repair tooth structure', 1, '2025-10-14 15:53:22', NULL, 30, '30 minutes\r\n'),
(3, 'Oral Surgery', 'Minor oral surgical procedure', 1, '2025-10-14 15:53:22', '2025-10-22 13:48:36', 60, '60 minutes'),
(4, 'Dentures', 'Denture measurement/adjustment session', 1, '2025-10-14 15:53:22', '2025-10-22 13:48:36', 60, '60 minutes (per fitting session)'),
(5, 'Panoramic X-Ray (X-Ray)', 'Panoramic radiograph', 1, '2025-10-14 15:53:22', '2025-10-22 13:48:36', 15, '15–20 minutes'),
(6, 'Full Braces', 'Comprehensive orthodontic braces placement/adjustment (upper and lower)', 1, '2025-10-14 15:53:22', '2025-10-22 13:51:06', 120, '120 minutes (installation)'),
(7, 'Upper Braces', 'Orthodontic braces placement/adjustment for upper teeth', 1, '2025-10-14 15:53:22', '2025-10-22 13:51:06', 90, '90 minutes'),
(8, 'Lower Braces', 'Orthodontic braces placement/adjustment for lower teeth', 1, '2025-10-14 15:53:22', '2025-10-22 13:51:06', 90, '90 minutes'),
(9, 'Brace Adjustment', 'Regular adjustment of installed orthodontic braces', 1, '2025-10-14 15:53:22', '2025-10-22 13:48:36', 30, '30 minutes'),
(10, 'Teeth Whitening', 'Professional whitening treatment', 1, '2025-10-14 15:53:22', '2025-10-22 13:51:06', 60, '60 minutes'),
(11, 'Check-Up', 'Routine dental examination and consultation', 1, '2025-10-14 15:53:22', '2025-10-22 13:48:36', 15, '15 minutes'),
(13, 'Tooth Extraction', 'Tooth extraction procedure', 1, '2025-11-05 11:16:00', NULL, 10, ''),
(14, 'Tooth Extraction', 'Tooth extraction procedure', 1, '2025-11-05 11:46:48', NULL, 10, '');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_treatment_history`
--

CREATE TABLE `tbl_treatment_history` (
  `History_Id` int(11) NOT NULL,
  `Appointment_Id` int(11) DEFAULT NULL,
  `Patient_Id` int(11) DEFAULT NULL,
  `Patient_Email` varchar(255) NOT NULL,
  `Dentist_Id` int(11) NOT NULL,
  `Procedure_Name` varchar(255) NOT NULL,
  `Treatment_Date` date NOT NULL,
  `Treatment_Time` time NOT NULL,
  `Room` varchar(64) DEFAULT NULL,
  `Admin_Notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_treatment_history`
--

INSERT INTO `tbl_treatment_history` (`History_Id`, `Appointment_Id`, `Patient_Id`, `Patient_Email`, `Dentist_Id`, `Procedure_Name`, `Treatment_Date`, `Treatment_Time`, `Room`, `Admin_Notes`) VALUES
(1, 1, 2, 'ryo@gmail.com', 1, 'Teeth Whitening', '2025-10-15', '07:00:00', '', 'bye'),
(2, 3, 1, 'nightmarefox50@gmail.com', 1, 'Oral Prophylaxis (Cleaning)', '2025-10-15', '10:15:00', NULL, NULL),
(3, 3, 1, 'nightmarefox50@gmail.com', 1, 'Oral Prophylaxis (Cleaning)', '2025-10-15', '10:15:00', NULL, NULL),
(4, 4, 3, 'walkin+summertime+09888323232@clinic.local', 3, 'Oral Prophylaxis (Cleaning)', '2025-10-15', '07:25:00', '', ''),
(5, 5, 3, 'summs@gmail.com', 2, 'Brace Adjustment', '2025-10-15', '13:10:00', '', 'cattipunan street'),
(6, 6, 4, 'walkin1760536750_72298e05@clinic.local', 1, 'Check-Up', '2025-10-16', '08:00:00', '', ''),
(7, 7, 5, 'walkin1760579417_904e552c@clinic.local', 4, 'Check-Up', '2025-10-16', '10:00:00', '', ''),
(8, 8, 1, 'nightmarefox50@gmail.com', 3, 'Oral Prophylaxis (Cleaning), Tooth Restoration, Tooth Extraction, Oral Surgery, Dentures, Panoramic X-Ray (X-Ray), Full Braces, Upper Braces, Lower Braces, Brace Adjustment, Teeth Whitening, Check-Up', '2025-10-17', '07:40:00', NULL, NULL),
(9, 8, 1, 'nightmarefox50@gmail.com', 3, 'Oral Prophylaxis (Cleaning), Tooth Restoration, Tooth Extraction, Oral Surgery, Dentures, Panoramic X-Ray (X-Ray), Full Braces, Upper Braces, Lower Braces, Brace Adjustment, Teeth Whitening, Check-Up', '2025-10-17', '07:40:00', NULL, NULL),
(10, 8, 1, 'nightmarefox50@gmail.com', 3, 'Oral Prophylaxis (Cleaning), Tooth Restoration, Tooth Extraction, Oral Surgery, Dentures, Panoramic X-Ray (X-Ray), Full Braces, Upper Braces, Lower Braces, Brace Adjustment, Teeth Whitening, Check-Up', '2025-10-17', '07:40:00', NULL, NULL),
(11, 9, 6, 'gwc.manuel@gmail.com', 4, '', '2025-10-16', '15:05:00', NULL, NULL),
(12, 10, 6, 'gwc.manuel@gmail.com', 4, '', '2025-10-20', '07:00:00', NULL, NULL),
(13, 11, 6, 'gwc.manuel@gmail.com', 3, 'Tooth Extraction, Upper Braces, Lower Braces, Check-Up', '2025-10-20', '07:00:00', NULL, NULL),
(14, 12, 6, 'gwc.manuel@gmail.com', 1, 'Tooth Restoration, Tooth Extraction', '2025-10-21', '16:00:00', NULL, NULL),
(15, 34, 8, 'fme090909@gmail.com', 3, 'Tooth Restoration', '2025-10-19', '07:40:00', NULL, NULL),
(16, 37, 8, 'fme090909@gmail.com', 4, 'Brace Adjustment', '2025-10-27', '07:25:00', NULL, NULL),
(17, 38, 1, 'nightmarefox50@gmail.com', 4, 'Oral Prophylaxis (Cleaning), Panoramic X-Ray (X-Ray), Brace Adjustment', '2025-10-28', '07:20:00', NULL, NULL),
(18, 39, 8, 'fme090909@gmail.com', 2, 'Oral Prophylaxis (Cleaning), Brace Adjustment', '2025-10-27', '07:40:00', NULL, NULL),
(19, 43, 1, 'nightmarefox50@gmail.com', 2, 'Tooth Restoration', '2025-10-19', '17:25:00', NULL, NULL),
(20, 44, 1, 'nightmarefox50@gmail.com', 3, 'Oral Prophylaxis (Cleaning)', '2025-10-20', '18:45:00', NULL, NULL),
(21, 45, 1, 'nightmarefox50@gmail.com', 1, 'Tooth Restoration', '2025-10-21', '18:50:00', NULL, NULL),
(22, 48, 9, 'sber33690@gmail.com', 4, 'Oral Prophylaxis (Cleaning)', '2025-10-21', '07:00:00', NULL, NULL),
(23, 52, 12, 'walkin1761052052_f4d0b135@clinic.local', 4, 'Check-Up', '2025-10-22', '07:10:00', '', ''),
(24, 53, 13, 'walkin1761052083_84f26957@clinic.local', 4, 'Brace Adjustment', '2025-10-22', '07:20:00', '', ''),
(25, 50, 10, 'ryuonoou@gmail.com', 4, 'Panoramic X-Ray (X-Ray)', '2025-10-22', '07:45:00', NULL, NULL),
(26, 49, 10, 'ryuonoou@gmail.com', 3, 'Tooth Restoration', '2025-10-22', '10:05:00', NULL, NULL),
(27, 55, 1, 'nightmarefox50@gmail.com', 3, 'Oral Prophylaxis (Cleaning), Tooth Restoration, Tooth Extraction, Oral Surgery, Dentures, Panoramic X-Ray (X-Ray), Full Braces, Upper Braces, Lower Braces, Brace Adjustment, Teeth Whitening, Check-Up', '2025-10-24', '07:00:00', NULL, NULL),
(28, 54, 1, 'nightmarefox50@gmail.com', 3, 'Panoramic X-Ray (X-Ray)', '2025-10-23', '15:30:00', NULL, NULL),
(29, 56, 10, 'ryuonoou@gmail.com', 2, 'Brace Adjustment', '2025-10-23', '07:05:00', NULL, NULL),
(30, 57, 1, 'nightmarefox50@gmail.com', 1, 'Oral Prophylaxis (Cleaning), Tooth Restoration, Tooth Extraction, Oral Surgery, Dentures, Panoramic X-Ray (X-Ray), Full Braces, Upper Braces, Lower Braces, Brace Adjustment, Teeth Whitening, Check-Up', '2025-10-25', '08:00:00', NULL, NULL),
(31, 58, 1, 'nightmarefox50@gmail.com', 2, 'Tooth Extraction', '2025-10-28', '07:05:00', NULL, NULL),
(32, 59, 1, 'nightmarefox50@gmail.com', 1, 'Tooth Extraction', '2025-10-28', '07:10:00', NULL, NULL),
(33, 60, 1, 'nightmarefox50@gmail.com', 3, 'Tooth Extraction', '2025-10-28', '07:15:00', NULL, NULL),
(34, 61, 1, 'nightmarefox50@gmail.com', 3, 'Oral Prophylaxis (Cleaning)', '2025-10-30', '07:50:00', NULL, NULL),
(35, 62, 1, 'nightmarefox50@gmail.com', 4, 'Oral Prophylaxis (Cleaning)', '2025-11-06', '07:05:00', NULL, NULL),
(36, 65, 1, 'nightmarefox50@gmail.com', 1, 'Panoramic X-Ray (X-Ray)', '2025-11-07', '17:15:00', NULL, NULL),
(37, 66, 1, 'nightmarefox50@gmail.com', 4, 'Oral Surgery', '2025-11-07', '08:55:00', NULL, NULL),
(38, 67, 1, 'nightmarefox50@gmail.com', 4, 'Oral Prophylaxis (Cleaning)', '2025-11-18', '07:05:00', NULL, NULL),
(39, 71, 8, 'fme090909@gmail.com', 4, 'Oral Prophylaxis (Cleaning)', '2025-11-18', '07:00:00', NULL, NULL),
(40, 74, 1, 'nightmarefox50@gmail.com', 1, 'Oral Prophylaxis (Cleaning)', '2025-11-19', '07:00:00', NULL, NULL),
(41, 79, 14, 'walkin1763383770_7189e278@clinic.local', 2, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', '2025-11-23', '07:00:00', '', ''),
(42, 80, 15, 'walkin1763807414_2ccff332@clinic.local', 4, 'Brace Adjustment, Tooth Extraction', '2025-11-23', '07:00:00', '', ''),
(43, 81, 14, 'walkin1763383770_7189e278@clinic.local', 4, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', '2025-11-24', '07:00:00', '', ''),
(44, 83, 1, 'nightmarefox50@gmail.com', 2, 'Full Braces', '2025-11-26', '08:00:00', NULL, NULL),
(45, 84, 14, 'walkin1763383770_7189e278@clinic.local', 1, 'Full Braces', '2025-11-26', '08:00:00', '', ''),
(46, 85, 14, 'walkin1763383770_7189e278@clinic.local', 2, 'Full Braces', '2025-11-26', '08:00:00', '', ''),
(47, 87, 14, 'walkin1763383770_7189e278@clinic.local', 2, 'Full Braces', '2025-11-27', '10:00:00', '', ''),
(48, 86, 1, 'nightmarefox50@gmail.com', 2, 'Full Braces', '2025-11-27', '08:00:00', NULL, NULL),
(49, 93, 18, 'walkin1763985601_cb0ae58f@clinic.local', 1, 'Brace Adjustment', '2025-11-28', '07:00:00', '', ''),
(50, 94, 19, 'walkin1763985884_eb855303@clinic.local', 4, 'Brace Adjustment', '2025-11-29', '07:00:00', '', ''),
(51, 95, 20, 'walkin1763985910_ab024527@clinic.local', 4, 'Brace Adjustment', '2025-11-26', '07:30:00', '', ''),
(52, 97, 22, 'walkin1763986109_e52accff@clinic.local', 1, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', '2025-11-26', '07:00:00', '', ''),
(53, 96, 21, 'walkin1763985952_bb85586a@clinic.local', 4, 'Brace Adjustment', '2025-11-26', '11:45:00', '', ''),
(54, 92, 18, 'walkin1763985601_cb0ae58f@clinic.local', 4, 'Brace Adjustment, Check-Up, Dentures, Full Braces, Lower Braces, Oral Prophylaxis (Cleaning), Oral Surgery, Panoramic X-Ray (X-Ray), Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', '2025-11-28', '07:00:00', '', ''),
(55, 100, 24, 'walkin1763994369_6fdab847@clinic.local', 4, 'Brace Adjustment', '2025-11-25', '07:00:00', '', ''),
(56, 103, 27, 'walkin1764226685_f2d85c9e@clinic.local', 4, 'Teeth Whitening, Tooth Extraction, Tooth Restoration, Upper Braces', '2025-11-27', '15:00:00', '', ''),
(57, 104, 28, 'lawrencevalderama.bscs.pass@gmail.com', 1, 'Full Braces, Lower Braces, Check-Up', '2025-11-27', '18:50:00', NULL, NULL),
(58, 106, 28, 'lawrencevalderama.bscs.pass@gmail.com', 1, 'Brace Adjustment', '2025-12-06', '08:25:00', NULL, NULL),
(59, 107, 28, 'lawrencevalderama.bscs.pass@gmail.com', 2, 'Brace Adjustment', '2025-12-11', '09:25:00', NULL, NULL),
(60, 108, 29, 'baromargkrulak19@gmail.com', 2, 'Upper Braces', '2025-12-06', '09:05:00', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_treatment_inventory`
--

CREATE TABLE `tbl_treatment_inventory` (
  `Id` int(11) NOT NULL,
  `Treatment_Name` varchar(255) NOT NULL,
  `Item_Id` int(11) DEFAULT NULL,
  `Item_Name` varchar(255) NOT NULL,
  `Quantity_Required` int(11) NOT NULL,
  `Unit` varchar(32) NOT NULL DEFAULT 'unit',
  `Last_Updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_treatment_inventory`
--

INSERT INTO `tbl_treatment_inventory` (`Id`, `Treatment_Name`, `Item_Id`, `Item_Name`, `Quantity_Required`, `Unit`, `Last_Updated`, `Created_At`) VALUES
(1, 'Oral Prophylaxis (Cleaning)', NULL, 'Cotton Rolls', 5, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(2, 'Oral Prophylaxis (Cleaning)', NULL, 'Mouth Mirror', 1, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(3, 'Oral Prophylaxis (Cleaning)', NULL, 'Scaler Tip', 1, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(4, 'Oral Prophylaxis (Cleaning)', NULL, 'Saliva Ejector', 1, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(5, 'Tooth Restoration', NULL, 'Composite Resin', 1, 'tube', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(6, 'Tooth Restoration', NULL, 'Etching Gel', 1, 'syringe', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(7, 'Tooth Restoration', NULL, 'Bonding Agent', 1, 'bottle', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(8, 'Tooth Restoration', NULL, 'Cotton Rolls', 4, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(9, 'Oral Surgery', NULL, 'Anesthetic Cartridge', 1, 'pcs', '2025-11-05 11:39:22', '2025-10-15 02:27:24'),
(10, 'Oral Surgery', NULL, 'Surgical Blade', 1, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(11, 'Oral Surgery', NULL, 'Gauze Pad', 5, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(12, 'Oral Surgery', NULL, 'Suture Thread', 1, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(13, 'Dentures', NULL, 'Impression Tray', 1, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(14, 'Dentures', NULL, 'Impression Material', 1, 'pack', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(15, 'Dentures', NULL, 'Wax Block', 1, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(16, 'Panoramic X-Ray (X-Ray)', NULL, 'X-Ray Film', 1, 'sheet', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(17, 'Panoramic X-Ray (X-Ray)', NULL, 'Lead Apron', 1, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(18, 'Full Braces', NULL, 'Bracket Set', 1, 'set', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(19, 'Full Braces', NULL, 'Archwire', 1, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(20, 'Full Braces', NULL, 'Ligature Elastic', 10, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(21, 'Upper Braces', NULL, 'Bracket Set (Upper)', 1, 'set', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(22, 'Upper Braces', NULL, 'Archwire (Upper)', 1, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(23, 'Upper Braces', NULL, 'Ligature Elastic', 10, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(24, 'Lower Braces', NULL, 'Bracket Set (Lower)', 1, 'set', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(25, 'Lower Braces', NULL, 'Archwire (Lower)', 1, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(26, 'Lower Braces', NULL, 'Ligature Elastic', 10, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(27, 'Brace Adjustment', NULL, 'Ligature Elastic', 10, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(28, 'Brace Adjustment', NULL, 'Archwire', 1, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(29, 'Teeth Whitening', NULL, 'Whitening Gel', 1, 'syringe', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(30, 'Teeth Whitening', NULL, 'Cotton Rolls', 3, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(31, 'Teeth Whitening', NULL, 'Cheek Retractor', 1, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(32, 'Check-Up', NULL, 'Mouth Mirror', 1, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(33, 'Check-Up', NULL, 'Explorer', 1, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(34, 'Check-Up', NULL, 'Cotton Rolls', 2, 'pcs', '2025-10-15 02:27:24', '2025-10-15 02:27:24'),
(69, 'Tooth Extraction', NULL, 'Gauze Pads', 2, 'unit', '2025-11-05 11:46:48', '2025-11-05 11:46:48'),
(70, 'Tooth Extraction', NULL, 'Lidocaine (Anesthetic)', 1, 'unit', '2025-11-05 11:46:48', '2025-11-05 11:46:48'),
(71, 'Tooth Extraction', NULL, 'Syringe', 1, 'unit', '2025-11-05 11:46:48', '2025-11-05 11:46:48'),
(72, 'Tooth Extraction', NULL, 'Cotton Rolls', 4, 'unit', '2025-11-05 11:46:48', '2025-11-05 11:46:48'),
(73, 'Tooth Extraction', NULL, 'Gloves', 1, 'unit', '2025-11-05 11:46:48', '2025-11-05 11:46:48');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_admin`
--
ALTER TABLE `tbl_admin`
  ADD PRIMARY KEY (`Admin_Id`),
  ADD UNIQUE KEY `Email` (`Email`),
  ADD KEY `idx_admin_email` (`Email`);

--
-- Indexes for table `tbl_appointments`
--
ALTER TABLE `tbl_appointments`
  ADD PRIMARY KEY (`Appointment_Id`),
  ADD KEY `idx_email` (`Email`),
  ADD KEY `idx_dentist_date` (`Dentist_Id`,`Date`),
  ADD KEY `idx_status` (`Status`),
  ADD KEY `idx_date_time` (`Date`,`Time`);

--
-- Indexes for table `tbl_appointment_cancellations`
--
ALTER TABLE `tbl_appointment_cancellations`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `idx_appt` (`Appointment_Id`),
  ADD KEY `idx_email` (`Email`),
  ADD KEY `idx_created` (`Created_At`);

--
-- Indexes for table `tbl_appointment_reschedules`
--
ALTER TABLE `tbl_appointment_reschedules`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `idx_appt` (`Appointment_Id`),
  ADD KEY `idx_email` (`Email`),
  ADD KEY `idx_old` (`Old_Date`,`Old_Time`),
  ADD KEY `idx_new` (`New_Date`,`New_Time`);

--
-- Indexes for table `tbl_appointment_treatments`
--
ALTER TABLE `tbl_appointment_treatments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `tbl_clinic_settings`
--
ALTER TABLE `tbl_clinic_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `tbl_dentist`
--
ALTER TABLE `tbl_dentist`
  ADD PRIMARY KEY (`Dentist_id`),
  ADD KEY `idx_name` (`Name`);

--
-- Indexes for table `tbl_dentist_leave`
--
ALTER TABLE `tbl_dentist_leave`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `unique_leave` (`Dentist_Id`,`Leave_Date`);

--
-- Indexes for table `tbl_inventory`
--
ALTER TABLE `tbl_inventory`
  ADD PRIMARY KEY (`Item_Id`),
  ADD UNIQUE KEY `uq_inventory_item_name` (`Item_Name`),
  ADD KEY `idx_inventory_sku` (`SKU`);

--
-- Indexes for table `tbl_inventory_added`
--
ALTER TABLE `tbl_inventory_added`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `Inventory_Id` (`Inventory_Id`),
  ADD KEY `Item_Name` (`Item_Name`);

--
-- Indexes for table `tbl_inventory_audit`
--
ALTER TABLE `tbl_inventory_audit`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `Inventory_Id` (`Inventory_Id`),
  ADD KEY `Action` (`Action`),
  ADD KEY `Changed_By_Email` (`Changed_By_Email`);

--
-- Indexes for table `tbl_inventory_deduction`
--
ALTER TABLE `tbl_inventory_deduction`
  ADD PRIMARY KEY (`Deduction_Id`),
  ADD KEY `idx_ded_appt` (`Appointment_Id`),
  ADD KEY `idx_ded_dentist` (`Dentist_Id`),
  ADD KEY `idx_ded_patient` (`Patient_Email`);

--
-- Indexes for table `tbl_inventory_deduction_items`
--
ALTER TABLE `tbl_inventory_deduction_items`
  ADD PRIMARY KEY (`Deduction_Item_Id`),
  ADD KEY `idx_dedi_ded` (`Deduction_Id`),
  ADD KEY `idx_dedi_item` (`Item_Name`),
  ADD KEY `fk_dedi_item` (`Item_Id`);

--
-- Indexes for table `tbl_inventory_log`
--
ALTER TABLE `tbl_inventory_log`
  ADD PRIMARY KEY (`Log_Id`),
  ADD KEY `idx_appointment` (`Appointment_Id`),
  ADD KEY `idx_treatment` (`Treatment_Name`),
  ADD KEY `idx_date` (`Log_Date`);

--
-- Indexes for table `tbl_inventory_restock_log`
--
ALTER TABLE `tbl_inventory_restock_log`
  ADD PRIMARY KEY (`Restock_Id`),
  ADD KEY `fk_restock_inventory` (`Item_Id`);

--
-- Indexes for table `tbl_messages`
--
ALTER TABLE `tbl_messages`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `idx_email` (`Email`),
  ADD KEY `idx_read` (`is_read`),
  ADD KEY `idx_sent` (`sent_at`);

--
-- Indexes for table `tbl_notifications`
--
ALTER TABLE `tbl_notifications`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `idx_email` (`Email`),
  ADD KEY `idx_type` (`Type`),
  ADD KEY `idx_is_read` (`Is_Read`),
  ADD KEY `idx_created` (`Created_At`),
  ADD KEY `idx_admin_id` (`Admin_Id`);

--
-- Indexes for table `tbl_patient`
--
ALTER TABLE `tbl_patient`
  ADD PRIMARY KEY (`Patient_Id`),
  ADD UNIQUE KEY `Email` (`Email`),
  ADD KEY `idx_email` (`Email`);

--
-- Indexes for table `tbl_treatments`
--
ALTER TABLE `tbl_treatments`
  ADD PRIMARY KEY (`Treatment_Id`);

--
-- Indexes for table `tbl_treatment_history`
--
ALTER TABLE `tbl_treatment_history`
  ADD PRIMARY KEY (`History_Id`),
  ADD KEY `idx_patient_email_date` (`Patient_Email`,`Treatment_Date`,`Treatment_Time`),
  ADD KEY `idx_dentist_date` (`Dentist_Id`,`Treatment_Date`,`Treatment_Time`);

--
-- Indexes for table `tbl_treatment_inventory`
--
ALTER TABLE `tbl_treatment_inventory`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `uq_treatment_item` (`Treatment_Name`,`Item_Name`),
  ADD KEY `fk_treat_item_inventory` (`Item_Id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_admin`
--
ALTER TABLE `tbl_admin`
  MODIFY `Admin_Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_appointments`
--
ALTER TABLE `tbl_appointments`
  MODIFY `Appointment_Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- AUTO_INCREMENT for table `tbl_appointment_cancellations`
--
ALTER TABLE `tbl_appointment_cancellations`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `tbl_appointment_reschedules`
--
ALTER TABLE `tbl_appointment_reschedules`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_appointment_treatments`
--
ALTER TABLE `tbl_appointment_treatments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=125;

--
-- AUTO_INCREMENT for table `tbl_clinic_settings`
--
ALTER TABLE `tbl_clinic_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT for table `tbl_dentist`
--
ALTER TABLE `tbl_dentist`
  MODIFY `Dentist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_dentist_leave`
--
ALTER TABLE `tbl_dentist_leave`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_inventory`
--
ALTER TABLE `tbl_inventory`
  MODIFY `Item_Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `tbl_inventory_added`
--
ALTER TABLE `tbl_inventory_added`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_inventory_audit`
--
ALTER TABLE `tbl_inventory_audit`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=174;

--
-- AUTO_INCREMENT for table `tbl_inventory_deduction`
--
ALTER TABLE `tbl_inventory_deduction`
  MODIFY `Deduction_Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_inventory_deduction_items`
--
ALTER TABLE `tbl_inventory_deduction_items`
  MODIFY `Deduction_Item_Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_inventory_log`
--
ALTER TABLE `tbl_inventory_log`
  MODIFY `Log_Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `tbl_inventory_restock_log`
--
ALTER TABLE `tbl_inventory_restock_log`
  MODIFY `Restock_Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_messages`
--
ALTER TABLE `tbl_messages`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_notifications`
--
ALTER TABLE `tbl_notifications`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=192;

--
-- AUTO_INCREMENT for table `tbl_patient`
--
ALTER TABLE `tbl_patient`
  MODIFY `Patient_Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `tbl_treatments`
--
ALTER TABLE `tbl_treatments`
  MODIFY `Treatment_Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `tbl_treatment_history`
--
ALTER TABLE `tbl_treatment_history`
  MODIFY `History_Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `tbl_treatment_inventory`
--
ALTER TABLE `tbl_treatment_inventory`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_appointment_treatments`
--
ALTER TABLE `tbl_appointment_treatments`
  ADD CONSTRAINT `fk_appointment_treatment` FOREIGN KEY (`appointment_id`) REFERENCES `tbl_appointments` (`Appointment_Id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_dentist_leave`
--
ALTER TABLE `tbl_dentist_leave`
  ADD CONSTRAINT `fk_dentist_leave` FOREIGN KEY (`Dentist_Id`) REFERENCES `tbl_dentist` (`Dentist_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_inventory_deduction_items`
--
ALTER TABLE `tbl_inventory_deduction_items`
  ADD CONSTRAINT `fk_dedi_header` FOREIGN KEY (`Deduction_Id`) REFERENCES `tbl_inventory_deduction` (`Deduction_Id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dedi_item` FOREIGN KEY (`Item_Id`) REFERENCES `tbl_inventory` (`Item_Id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tbl_inventory_restock_log`
--
ALTER TABLE `tbl_inventory_restock_log`
  ADD CONSTRAINT `fk_restock_inventory` FOREIGN KEY (`Item_Id`) REFERENCES `tbl_inventory` (`Item_Id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_treatment_inventory`
--
ALTER TABLE `tbl_treatment_inventory`
  ADD CONSTRAINT `fk_treat_item_inventory` FOREIGN KEY (`Item_Id`) REFERENCES `tbl_inventory` (`Item_Id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
