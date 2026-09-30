-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 31, 2026 at 05:21 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `creative_touch_interiors`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','super_admin') DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`, `name`, `email`, `role`, `created_at`, `updated_at`) VALUES
(5, 'harsh', '$2y$10$qWrm84lsqwZ3a1ZUoWbseOEziRUInTql6purqQtzNBNBsQG6ijqu6', 'Harsh Admin', 'harsh@gmail.com', 'super_admin', '2026-08-14 03:48:40', '2026-08-26 07:20:12'),
(6, 'het', '$2y$10$eT9bnh80ajuIohUWZm.C2OeuuM1MMuiDf/sK7vi6y6o5rbefjOfKi', 'het rana', 'boyhetrana@gmail.com', 'admin', '2026-08-26 07:16:54', '2026-08-26 07:20:58');

-- --------------------------------------------------------

--
-- Table structure for table `consultations`
--

CREATE TABLE `consultations` (
  `id` int(11) NOT NULL,
  `client_name` varchar(100) NOT NULL,
  `client_email` varchar(100) NOT NULL,
  `client_phone` varchar(20) DEFAULT NULL,
  `subject` varchar(255) DEFAULT 'General Consultation',
  `consultation_date` date NOT NULL,
  `consultation_time` time NOT NULL,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `consultations`
--

INSERT INTO `consultations` (`id`, `client_name`, `client_email`, `client_phone`, `subject`, `consultation_date`, `consultation_time`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(12, 'Annie', 'annie240407@gmail.com', '9825140298', 'Consultation: Full home (Villa)', '2026-09-05', '10:00:00', 'confirmed', 'City: surat | Property: villa | Type: full_home | Area: 10000 sq ft | Budget: 50+ Lakhs | Style: luxury\n\ni need good and best work this reson i am come to you', '2026-08-26 07:12:39', '2026-08-26 07:15:34');

-- --------------------------------------------------------

--
-- Table structure for table `contact_inquiries`
--

CREATE TABLE `contact_inquiries` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('new','read','replied','closed') DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_inquiries`
--

INSERT INTO `contact_inquiries` (`id`, `name`, `email`, `phone`, `subject`, `message`, `status`, `created_at`, `updated_at`) VALUES
(1, 'het', 'het@gmail.com', '00000 00000', 'house', 'do fast work', 'closed', '2026-08-26 06:06:55', '2026-08-26 07:05:11'),
(3, 'Annie', 'annie240407@gmail.com', '9825140298', 'my quote update', 'how many time need for check my quote', 'replied', '2026-08-26 07:13:26', '2026-08-26 07:15:19');

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` int(11) NOT NULL,
  `question` varchar(255) NOT NULL,
  `answer` text NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `order_index` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gallery_images`
--

CREATE TABLE `gallery_images` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `featured` tinyint(1) DEFAULT 0,
  `order_index` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gallery_images`
--

INSERT INTO `gallery_images` (`id`, `title`, `image_path`, `category`, `project_id`, `featured`, `order_index`, `created_at`, `updated_at`) VALUES
(1, 'Elegant Family Living Room', 'uploads/1786885208_gallery_01.jpg', 'living_room', NULL, 1, 0, '2026-08-16 13:00:08', '2026-08-16 13:00:08'),
(2, 'Warm Contemporary Living Space', 'uploads/1786886853_gallery_02.jpg', 'living_room', NULL, 0, 0, '2026-08-16 13:27:33', '2026-08-16 13:27:33'),
(3, 'Classic Comfort Living Room', 'uploads/1786887080_gallery_03.jpg', 'living_room', NULL, 1, 0, '2026-08-16 13:31:20', '2026-08-16 13:31:20'),
(4, 'Luxury Victorian Living Room', 'uploads/1786887116_gallery_04.jpg', 'living_room', NULL, 0, 0, '2026-08-16 13:31:56', '2026-08-16 13:31:56'),
(5, 'Designer Open Living Area', 'uploads/1786887131_gallery_05.jpg', 'living_room', NULL, 0, 0, '2026-08-16 13:32:11', '2026-08-16 13:32:11'),
(6, 'Coastal Master Bedroom', 'uploads/1786887149_gallery_06.jpg', 'bedroom', NULL, 1, 0, '2026-08-16 13:32:29', '2026-08-16 13:32:29'),
(7, 'Warm Southwestern Bedroom', 'uploads/1786887168_gallery_07.jpg', 'bedroom', NULL, 0, 0, '2026-08-16 13:32:48', '2026-08-16 13:32:48'),
(8, 'Contemporary Master Suite', 'uploads/1786887188_gallery_08.jpg', 'bedroom', NULL, 1, 0, '2026-08-16 13:33:08', '2026-08-16 13:33:08'),
(9, 'Bright Modern Bedroom', 'uploads/1786887225_gallery_09.jpg', 'bedroom', NULL, 0, 0, '2026-08-16 13:33:45', '2026-08-16 13:33:45'),
(10, 'Nature Inspired Bedroom', 'uploads/1786887248_gallery_10.jpg', 'bedroom', NULL, 0, 0, '2026-08-16 13:34:08', '2026-08-16 13:34:08'),
(11, 'Rustic Wood Kitchen', 'uploads/1786887286_gallery_11.jpg', 'kitchen', NULL, 1, 0, '2026-08-16 13:34:46', '2026-08-16 13:34:46'),
(12, 'Contemporary White Kitchen', 'uploads/1786887299_gallery_12.jpg', 'kitchen', NULL, 0, 0, '2026-08-16 13:34:59', '2026-08-16 13:34:59'),
(13, 'Modern Black Kitchen', 'uploads/1786887318_gallery_13.jpg', 'kitchen', NULL, 1, 0, '2026-08-16 13:35:18', '2026-08-16 13:35:18'),
(14, 'Coastal Open Kitchen', 'uploads/1786887332_gallery_14.jpg', 'kitchen', NULL, 0, 0, '2026-08-16 13:35:32', '2026-08-16 13:35:32'),
(15, 'Minimalist Family Kitchen', 'uploads/1786887347_gallery_15.jpg', 'kitchen', NULL, 0, 0, '2026-08-16 13:35:47', '2026-08-16 13:35:47'),
(16, 'Classic White Bathroom', 'uploads/1786887368_gallery_16.jpg', 'bathroom', NULL, 1, 0, '2026-08-16 13:36:08', '2026-08-16 13:36:08'),
(17, 'Modern Luxury Bathroom', 'uploads/1786887381_gallery_17.jpg', 'bathroom', NULL, 0, 0, '2026-08-16 13:36:21', '2026-08-16 13:36:21'),
(18, 'Bright Contemporary Bathroom', 'uploads/1786887396_gallery_18.jpg', 'bathroom', NULL, 0, 0, '2026-08-16 13:36:36', '2026-08-16 13:36:36'),
(19, 'Premium Modern Bathroom', 'uploads/1786887412_gallery_19.jpg', 'bathroom', NULL, 1, 0, '2026-08-16 13:36:52', '2026-08-16 13:36:52'),
(20, 'Elegant Double Vanity Bathroom', 'uploads/1786887426_gallery_20.jpg', 'bathroom', NULL, 0, 0, '2026-08-16 13:37:06', '2026-08-16 13:37:06'),
(21, 'Modern Open Office', 'uploads/1786887440_gallery_21.jpg', 'office', NULL, 1, 0, '2026-08-16 13:37:20', '2026-08-16 13:37:20'),
(22, 'Executive Industrial Office', 'uploads/1786887453_gallery_22.jpg', 'office', NULL, 0, 0, '2026-08-16 13:37:33', '2026-08-16 13:37:33'),
(23, 'Creative Industrial Workspace', 'uploads/1786887464_gallery_23.jpg', 'office', NULL, 0, 0, '2026-08-16 13:37:44', '2026-08-16 13:37:44'),
(24, 'Warm Professional Workspace', 'uploads/1786887479_gallery_24.jpg', 'office', NULL, 0, 0, '2026-08-16 13:37:59', '2026-08-16 13:37:59'),
(25, 'Contemporary Private Office', 'uploads/1786887492_gallery_25.jpg', 'office', NULL, 1, 0, '2026-08-16 13:38:12', '2026-08-16 13:38:12');

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `property_type` varchar(100) DEFAULT NULL,
  `project_type` varchar(100) DEFAULT NULL,
  `area` varchar(50) DEFAULT NULL,
  `budget` varchar(50) DEFAULT NULL,
  `design_preference` varchar(100) DEFAULT NULL,
  `expected_start_date` date DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('new','contacted','qualified','converted','lost') DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`id`, `name`, `email`, `phone`, `city`, `property_type`, `project_type`, `area`, `budget`, `design_preference`, `expected_start_date`, `message`, `status`, `created_at`, `updated_at`) VALUES
(23, 'Annie', 'annie240407@gmail.com', '9825140298', 'surat', 'villa', 'full_home', '10000', '50+', 'luxury', '2026-09-05', 'i need good and best work this reson i am come to you', 'contacted', '2026-08-26 07:12:39', '2026-08-26 07:15:13');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `category` enum('residential','commercial','office','retail') NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `area` varchar(50) DEFAULT NULL,
  `design_style` varchar(100) DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `budget` decimal(15,2) DEFAULT NULL,
  `status` enum('completed','in_progress','planned') DEFAULT 'planned',
  `featured` tinyint(1) DEFAULT 0,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`images`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `title`, `slug`, `description`, `image`, `category`, `location`, `area`, `design_style`, `completion_date`, `budget`, `status`, `featured`, `images`, `created_at`, `updated_at`) VALUES
(10, 'Serene Oak Residence', 'serene-oak-residence', 'Warm wood finishes, neutral tones, custom storage and clean architectural lines create a comfortable modern family residence.', 'uploads/projects/1786869078_project_01.jpg', 'residential', 'Surat, Gujarat', '1,850 sq.ft', 'Modern', '2025-03-15', 1850000.00, 'completed', 1, NULL, '2026-08-16 08:31:18', '2026-08-16 08:31:18'),
(12, 'Urban Vista Showroom', 'urban-vista-showroom', 'A contemporary retail environment with open display zones, feature lighting, premium finishes and a clear customer circulation path.', 'uploads/projects/1786869187_project_02.jpg', 'retail', 'Ahmedabad, Gujarat', '2,400 sq.ft', 'Contemporary', '2025-06-28', 2480000.00, 'completed', 1, NULL, '2026-08-16 08:33:07', '2026-08-16 08:33:07'),
(13, 'Nexus Business Hub', 'nexus-business-hub', 'A professional office focused on efficient workstations, collaborative areas, acoustic comfort and a clean modern aesthetic.', 'uploads/projects/1786869288_project_03.jpg', 'office', 'Mumbai, Maharashtra', '3,200 sq.ft', 'Minimalist', '2025-11-10', 3250000.00, 'completed', 1, NULL, '2026-08-16 08:34:48', '2026-08-16 08:34:48'),
(15, 'Grand Avenue Lounge', 'grand-avenue-lounge', 'A commercial interior combining textured surfaces, statement lighting, contemporary furniture and functional customer areas.', 'uploads/projects/1786870369_project_04.jpg', 'commercial', 'Pune, Maharashtra', '2,750 sq.ft', 'Industrial', '2026-02-20', 2740000.00, 'completed', 1, NULL, '2026-08-16 08:52:49', '2026-08-16 14:54:03'),
(16, 'Palm Grove Villa', 'palm-grove-villa', 'A spacious family villa using soft textures, practical zoning, layered lighting and contemporary furnishings.', 'uploads/projects/1786870500_project_05.jpg', 'residential', 'Vadodara, Gujarat', '2,650 sq.ft', 'Contemporary', '2025-04-12', 2620000.00, 'completed', 1, NULL, '2026-08-16 08:55:00', '2026-08-16 14:54:15'),
(18, 'Metroline Fashion Store', 'metroline-fashion-store', 'A modern fashion retail space with organized display walls, accent lighting, fitting areas and a premium customer experience.', 'uploads/projects/1786879506_project_06.jpg', 'retail', 'Mumbai, Maharashtra', '1,900 sq.ft', 'Modern', '2025-08-05', 2160000.00, 'completed', 0, NULL, '2026-08-16 11:25:06', '2026-08-16 11:25:06'),
(20, 'Vertex Corporate Suite', 'vertex-corporate-suite', 'A bright corporate workspace with simple furniture, natural finishes, flexible meeting areas and a calm professional atmosphere.', 'uploads/projects/1786884033_project_07.jpg', 'office', 'Surat, Gujarat', '2,850 sq.ft', 'Scandinavian', '2025-09-18', 2575000.00, 'completed', 0, NULL, '2026-08-16 12:40:33', '2026-08-16 12:40:33'),
(21, 'Heritage Comfort Home', 'heritage-comfort-home', 'A refined home interior blending traditional detailing with practical storage, comfortable furniture and warm ambient lighting.', 'uploads/projects/1786884090_project_08.jpg', 'residential', 'Rajkot, Gujarat', '2,100 sq.ft', 'Traditional', '2026-06-30', 2090000.00, 'completed', 0, NULL, '2026-08-16 12:41:30', '2026-08-16 12:41:30'),
(22, 'Cedar Court Residence', 'cedar-court-residence', 'A compact residence designed around clean forms, uncluttered spaces, functional storage and soft neutral finishes.', 'uploads/projects/1786884144_project_09.jpg', 'residential', 'Pune, Maharashtra', '1,550 sq.ft', 'Minimalist', '2026-05-22', 1540000.00, 'completed', 0, NULL, '2026-08-16 12:42:24', '2026-08-16 12:42:24'),
(23, 'Luxe Avenue Boutique', 'luxe-avenue-boutique', 'A boutique interior featuring premium display fixtures, elegant lighting, textured walls and an inviting customer layout.', 'uploads/projects/1786884201_project_10.jpg', 'retail', 'Ahmedabad, Gujarat', '2,150 sq.ft', 'Contemporary', '2025-10-14', 2390000.00, 'completed', 0, NULL, '2026-08-16 12:43:21', '2026-08-16 12:43:21'),
(24, 'Skyline Business Centre', 'skyline-business-centre', 'A modern business centre planned with executive cabins, open work areas, meeting rooms and a welcoming reception zone.', 'uploads/projects/1786884258_project_11.jpg', 'office', 'Pune, Maharashtra', '3,600 sq.ft', 'Modern', '2026-06-08', 3680000.00, 'in_progress', 0, NULL, '2026-08-16 12:44:18', '2026-08-16 12:44:18'),
(25, 'Amber House Residence', 'amber-house-residence', 'A modern family home with warm materials, functional cabinetry, balanced lighting and comfortable living spaces.', 'uploads/projects/1786884314_project_12.jpg', 'residential', 'Surat, Gujarat', '1,980 sq.ft', 'Modern', '2026-07-16', 1980000.00, 'in_progress', 0, NULL, '2026-08-16 12:45:14', '2026-08-16 12:45:14'),
(26, 'Urban Craft Store', 'urban-craft-store', 'An industrial-inspired retail store using textured finishes, open shelving, focused lighting and practical product displays.', 'uploads/projects/1786884374_project_13.jpg', 'retail', 'Vadodara, Gujarat', '1,700 sq.ft', 'Industrial', '2025-12-03', 1795000.00, 'completed', 0, NULL, '2026-08-16 12:46:14', '2026-08-16 12:46:14'),
(27, 'Primework Office', 'primework-office', 'A contemporary office layout with collaborative work zones, private cabins, a compact pantry and efficient storage.', 'uploads/projects/1786884447_project_14.jpg', 'office', 'Rajkot, Gujarat', '2,450 sq.ft', 'Contemporary', '2026-03-25', 2280000.00, 'planned', 0, NULL, '2026-08-16 12:47:27', '2026-08-18 06:19:39'),
(28, 'Riverside Family Home', 'riverside-family-home', 'A light Scandinavian-inspired home using natural textures, simple furniture, soft tones and functional family spaces.', 'uploads/projects/1786884502_project_15.jpg', 'residential', 'Nashik, Maharashtra', '2,300 sq.ft', 'Scandinavian', '2026-02-11', 2150000.00, 'completed', 0, NULL, '2026-08-16 12:48:22', '2026-08-16 12:48:22'),
(29, 'Central Square Café', 'central-square-café', 'A welcoming commercial café interior with comfortable seating, feature lighting, practical service areas and contemporary finishes.', 'uploads/projects/1786884560_project_16.jpg', 'commercial', 'Ahmedabad, Gujarat', '2,050 sq.ft', 'Contemporary', '2025-08-19', 1925000.00, 'completed', 0, NULL, '2026-08-16 12:49:20', '2026-08-16 12:49:20'),
(30, 'Oakline Residence', 'oakline-residence', 'A planned modern residence with open living zones, custom joinery, layered lighting and a practical family-oriented layout.', 'uploads/projects/1786884614_project_17.jpg', 'residential', 'Mumbai, Maharashtra', '2,750 sq.ft', 'Modern', '2026-08-07', 2960000.00, 'planned', 0, NULL, '2026-08-16 12:50:14', '2026-08-16 12:50:14'),
(31, 'Studio 24 Retail', 'studio-24-retail', 'A minimalist retail concept emphasizing clean display lines, efficient circulation, subtle lighting and a spacious visual presentation.', 'uploads/projects/1786884677_project_18.jpg', 'retail', 'Surat, Gujarat', '1,480 sq.ft', 'Minimalist', '2026-04-24', 1475000.00, 'in_progress', 0, NULL, '2026-08-16 12:51:17', '2026-08-16 12:51:17'),
(32, 'Summit Workspace', 'summit-workspace', 'A large office concept combining exposed textures, flexible work areas, meeting spaces and contemporary industrial detailing.', 'uploads/projects/1786884729_project_19.jpg', 'office', 'Mumbai, Maharashtra', '4,100 sq.ft', 'Industrial', '2026-05-09', 4120000.00, 'completed', 0, NULL, '2026-08-16 12:52:09', '2026-08-26 07:24:12'),
(33, 'Greenfield Residence', 'greenfield-residence', 'A contemporary residence featuring balanced proportions, custom storage, warm lighting and comfortable everyday living spaces.', 'uploads/projects/1786884780_project_20.jpg', 'residential', 'Vadodara, Gujarat', '2,500 sq.ft', 'Contemporary', '2026-07-31', 2430000.00, 'completed', 0, NULL, '2026-08-16 12:53:00', '2026-08-26 07:23:18');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `price_range` varchar(100) DEFAULT NULL,
  `featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `title`, `slug`, `description`, `category`, `icon`, `price_range`, `featured`, `created_at`, `updated_at`) VALUES
(8, 'Complete Home Interior Design', 'complete-home-interior-design', 'End-to-end residential planning covering layout development, furniture selection, lighting concepts, material coordination and final styling.\r\n', 'Residential', '🏠', '₹8L – ₹25L', 1, '2026-08-16 13:49:05', '2026-08-16 13:49:05'),
(9, 'Living Room Styling', 'living-room-styling', 'Living spaces planned with seating arrangements, media units, accent walls, decorative lighting and coordinated finishes.\r\n', 'Residential', '🛋️', '₹1.2L – ₹4.5L', 0, '2026-08-16 13:49:28', '2026-08-16 13:49:28'),
(10, 'Master Bedroom Planning', 'master-bedroom-planning', 'Master bedroom solutions including wardrobe planning, bedside storage, lighting layers and a comfortable furniture arrangement.\r\n', 'Residential', '🛏️', '₹1.4L – ₹5L', 0, '2026-08-16 13:49:59', '2026-08-16 13:49:59'),
(11, 'Modular Kitchen Installation', 'modular-kitchen-installation', 'Kitchen solutions with customized cabinets, countertop planning, hardware selection, appliance zones and practical storage.\r\n', 'Residential', '🍳', '₹2L – ₹7L', 0, '2026-08-16 13:50:32', '2026-08-16 13:50:32'),
(12, 'Bathroom Makeover', 'bathroom-makeover', 'Bathroom renovation concepts covering sanitaryware placement, vanity storage, wall finishes, lighting and functional zoning.\r\n', 'Residential', '🛁', '₹90K – ₹3.5L', 0, '2026-08-16 13:51:04', '2026-08-16 13:51:04'),
(13, 'Luxury Villa Interiors', 'luxury-villa-interiors', 'High-end villa interiors with coordinated room concepts, bespoke furniture, premium materials and detailed finishing.\r\n', 'Residential', '🏡', '₹18L – ₹45L', 0, '2026-08-16 13:51:36', '2026-08-16 13:51:36'),
(14, 'Apartment Space Optimization', 'apartment-space-optimization', 'Smart apartment planning that maximizes usable space through efficient zoning, built-in storage and multifunctional furniture.\r\n', 'Residential', '📐', '₹4L – ₹14L', 0, '2026-08-16 13:51:56', '2026-08-16 13:51:56'),
(15, 'Kids Room Design', 'kids-room-design', 'Playful and practical children\'s rooms designed with study areas, storage, safe furniture layouts and adaptable themes.\r\n', 'Residential', '🧸', '₹1L – ₹3.8L', 0, '2026-08-16 13:52:16', '2026-08-16 13:52:16'),
(16, 'Executive Office Design', 'executive-office-design', 'Executive workplace interiors featuring private cabins, meeting areas, premium finishes, storage and professional lighting.\r\n', 'Office', '💼', '₹6L – ₹24L', 1, '2026-08-16 13:52:40', '2026-08-16 13:52:40'),
(17, 'Open Workspace Planning', 'open-workspace-planning', 'Flexible open-office layouts with workstation clusters, circulation planning, collaboration zones and organized storage.\r\n', 'Office', '🖥️', '₹7L – ₹28L', 0, '2026-08-16 13:53:08', '2026-08-16 13:53:08'),
(18, 'Conference Room Interiors', 'conference-room-interiors', 'Focused meeting-room design with presentation walls, conference furniture, acoustic considerations and integrated lighting.\r\n', 'Office', '📊', '₹2L – ₹8L', 0, '2026-08-16 13:53:32', '2026-08-16 13:53:32'),
(19, 'Reception & Waiting Area', 'reception-&-waiting-area', 'Professional reception environments with branded feature walls, visitor seating, reception desks and welcoming lighting.\r\n', 'Office', '🪑', '₹1.8L – ₹6.5L', 0, '2026-08-16 13:54:00', '2026-08-16 13:54:00'),
(20, 'Retail Store Fit-Out', 'retail-store-fit-out', 'Complete retail fit-out including display fixtures, customer circulation, checkout planning, lighting and storage zones.\r\n', 'Retail', '🛍️', '₹6L – ₹22L', 1, '2026-08-16 13:54:26', '2026-08-16 13:54:26'),
(21, 'Fashion Boutique Design', 'fashion-boutique-design', 'Boutique interiors with curated garment displays, fitting rooms, mirrors, feature lighting and premium customer areas.\r\n', 'Retail', '👗', '₹4.5L – ₹16L', 0, '2026-08-16 13:54:51', '2026-08-16 13:54:51'),
(22, 'Electronics Showroom Planning', 'electronics-showroom-planning', 'Showroom layouts designed for product visibility, demonstration zones, secure display units and clear customer movement.\r\n', 'Retail', '📱', '₹7L – ₹26L', 0, '2026-08-16 13:55:18', '2026-08-16 13:55:18'),
(23, 'Jewellery Store Interiors', 'jewellery-store-interiors', 'Premium jewellery retail environments with elegant display counters, focused lighting, secure storage and refined finishes.\r\n', 'Retail', '💎', '₹10L – ₹35L', 0, '2026-08-16 13:55:48', '2026-08-16 13:55:48'),
(24, 'Café Interior Design', 'café-interior-design', 'Café environments planned around seating comfort, service flow, counter placement, lighting and durable decorative finishes.\r\n', 'Commercial', '☕', '₹5L – ₹18L', 0, '2026-08-16 13:56:14', '2026-08-16 13:56:14'),
(25, 'Restaurant Interior Planning', 'restaurant-interior-planning', 'Restaurant design balancing guest seating, service circulation, ambience, lighting and efficient operational zones.\r\n', 'Commercial', '🍽️', '₹8L – ₹30L', 1, '2026-08-16 13:56:41', '2026-08-16 13:56:41'),
(26, 'Salon & Beauty Studio Design', 'salon-&-beauty-studio-design', 'Beauty studio interiors with reception, styling stations, treatment zones, mirrors, storage and customer-friendly circulation.\r\n', 'Commercial', '💇', '₹4L – ₹15L', 0, '2026-08-16 13:57:01', '2026-08-16 13:57:01'),
(27, '3D Interior Visualization', '3d-interior-visualization', 'Detailed 3D visualization packages that present proposed layouts, furniture, materials, lighting and design concepts before execution.\r\n', 'Design Service', '🎨', '₹20K – ₹90K', 1, '2026-08-16 13:57:23', '2026-08-16 13:57:23');

-- --------------------------------------------------------

--
-- Table structure for table `team_members`
--

CREATE TABLE `team_members` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `bio` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `order_index` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL,
  `client_name` varchar(100) NOT NULL,
  `client_photo` varchar(255) DEFAULT NULL,
  `project_title` varchar(255) DEFAULT NULL,
  `rating` int(11) DEFAULT 5,
  `testimonial` text NOT NULL,
  `featured` tinyint(1) DEFAULT 0,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL,
  `terms_agreed` tinyint(1) NOT NULL DEFAULT 0,
  `terms_agreed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `created_at`, `profile_picture`) VALUES
(1, 'het', 'het@gmail.com', '$2y$10$GEECYRjjveBcIpgnt05i7e/EEfukl6IGLFNMqnIDNws3fzYipiiuC', '00000 00000', '2026-08-14 04:38:44', 'profile_6a81ca5b05f86_1.png'),
(9, 'Annie', 'annie240407@gmail.com', '$2y$10$88ZazMkp8M43aaxNq68ZXebMuL7KszWjDYmOLYLp/acXP.nwd0xEa', '9825140298', '2026-08-26 07:11:21', NULL),
(12, 'pavan ajuiya', 'pavanajugiya@gmail.com', '$2y$10$8Wol3g2bXCuVzSE0uZmMC.xLsHZGCBbNvqp/aC4CR68SEa9CNwnK2', '7845256512', '2026-08-28 07:54:33', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(128) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `website_content`
--

CREATE TABLE `website_content` (
  `id` int(11) NOT NULL,
  `section_key` varchar(100) NOT NULL,
  `content` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `website_content`
--

INSERT INTO `website_content` (`id`, `section_key`, `content`, `updated_at`) VALUES
(1, 'hero_title', 'Transform Your Space Into Something Extraordinary', '2026-08-14 03:24:04'),
(2, 'hero_subtitle', 'Award-winning interior design services for homes and businesses', '2026-08-14 03:24:04'),
(3, 'about_story', 'Founded in 2009, Creative Touch Interiors began with a simple vision: to transform ordinary spaces into extraordinary experiences.', '2026-08-14 03:24:04'),
(4, 'contact_address', '123 Design Street, Andheri West, Mumbai, Maharashtra 400058', '2026-08-14 03:24:04'),
(5, 'contact_phone', '+91 98765 43210', '2026-08-14 03:24:04'),
(6, 'contact_email', 'info@creativetouch.com', '2026-08-14 03:24:04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `consultations`
--
ALTER TABLE `consultations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contact_inquiries`
--
ALTER TABLE `contact_inquiries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gallery_images`
--
ALTER TABLE `gallery_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `team_members`
--
ALTER TABLE `team_members`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `website_content`
--
ALTER TABLE `website_content`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `section_key` (`section_key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `contact_inquiries`
--
ALTER TABLE `contact_inquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gallery_images`
--
ALTER TABLE `gallery_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `team_members`
--
ALTER TABLE `team_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `website_content`
--
ALTER TABLE `website_content`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `gallery_images`
--
ALTER TABLE `gallery_images`
  ADD CONSTRAINT `gallery_images_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
