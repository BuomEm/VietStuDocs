-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Dec 21, 2025 at 04:35 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `docshare`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_approvals`
--

CREATE TABLE `admin_approvals` (
  `id` int NOT NULL,
  `document_id` int NOT NULL,
  `reviewed_by` int NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_points` int DEFAULT NULL,
  `rejection_reason` text,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_approvals`
--

INSERT INTO `admin_approvals` (`id`, `document_id`, `reviewed_by`, `status`, `admin_points`, `rejection_reason`, `reviewed_at`, `created_at`) VALUES
(2, 25, 1, 'approved', 2, NULL, '2025-12-17 04:29:37', '2025-12-17 04:29:37'),
(3, 27, 1, 'approved', 1, NULL, '2025-12-17 14:37:50', '2025-12-17 14:37:50'),
(4, 26, 1, 'approved', 50, NULL, '2025-12-17 14:37:53', '2025-12-17 14:37:53'),
(5, 28, 1, 'approved', 50, NULL, '2025-12-18 07:14:39', '2025-12-18 07:14:39'),
(6, 29, 1, 'approved', 9, NULL, '2025-12-18 07:15:50', '2025-12-18 07:15:50'),
(8, 31, 1, 'approved', 10, NULL, '2025-12-18 13:46:07', '2025-12-18 13:46:07');

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` int NOT NULL,
  `admin_id` int NOT NULL,
  `notification_type` enum('new_document','document_sold','system_alert') DEFAULT 'new_document',
  `document_id` int DEFAULT NULL,
  `message` text,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_notifications`
--

INSERT INTO `admin_notifications` (`id`, `admin_id`, `notification_type`, `document_id`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 'new_document', NULL, 'New document submitted for review: Thuyết trình Hoá.docx', 1, '2025-12-16 13:08:01'),
(2, 1, 'new_document', 22, 'New document submitted for review: vocabulary and grammar- ONLINE.docx', 1, '2025-12-16 14:58:01'),
(3, 1, 'new_document', 23, 'New document submitted for review: Thuyết trình Hoá.docx', 1, '2025-12-16 14:58:01'),
(4, 1, 'new_document', 24, 'New document submitted for review: tiêu chípdf.pdf', 1, '2025-12-17 04:26:10'),
(5, 1, 'new_document', 25, 'New document submitted for review: Ielts.docx', 1, '2025-12-17 04:29:18'),
(6, 1, 'new_document', 26, 'New document submitted for review: vocabulary and grammar- ONLINE.docx', 1, '2025-12-17 04:41:59'),
(7, 1, 'new_document', 27, 'New document submitted for review: TIN 12.docx', 1, '2025-12-17 14:37:26'),
(8, 1, 'document_sold', 24, 'Document sold for 10 points', 1, '2025-12-17 14:42:30'),
(9, 1, 'document_sold', 25, 'Document sold for 2 points', 1, '2025-12-17 15:28:11'),
(10, 1, 'document_sold', 27, 'Document sold for 1 points', 1, '2025-12-17 15:29:22'),
(11, 1, 'document_sold', 22, 'Tài liệu đã được bán với giá 2 điểm', 1, '2025-12-17 15:41:12'),
(12, 1, 'document_sold', 23, 'Tài liệu đã được bán với giá 2 điểm', 1, '2025-12-17 15:42:08'),
(13, 1, 'new_document', 28, 'New document submitted for review: EBOOK PHONG TOẢ VẬT LÝ 11 - TẬP 2  .pdf', 1, '2025-12-18 07:09:03'),
(14, 1, 'new_document', 29, 'New document submitted for review: ĐỀ NGÀY 25.docx', 1, '2025-12-18 07:15:31'),
(15, 1, 'document_sold', 29, 'Tài liệu đã được bán với giá 9 điểm', 1, '2025-12-18 07:16:31'),
(17, 1, 'new_document', NULL, 'New document submitted for review: congthucluonggiac2.pdf', 1, '2025-12-18 13:38:36'),
(18, 1, 'new_document', 31, 'New document submitted for review: DE CHINH THUC HUONG DAN VA GIAI THICH.docx', 1, '2025-12-18 13:45:02'),
(19, 1, 'system_alert', NULL, 'Test: System notification - This is a test alert message', 1, '2025-12-18 13:49:02'),
(20, 1, 'document_sold', NULL, 'Test: Document \"Research Paper.docx\" was purchased for 150 points', 1, '2025-12-18 13:49:06'),
(21, 1, 'new_document', NULL, 'Test: New document \"Sample Document.pdf\" uploaded by user John Doe', 1, '2025-12-18 13:49:07'),
(22, 1, 'document_sold', NULL, 'Test: Document \"Research Paper.docx\" was purchased for 150 points', 1, '2025-12-20 12:19:22'),
(23, 1, 'document_sold', NULL, 'Test: Document \"Research Paper.docx\" was purchased for 150 points', 1, '2025-12-20 12:19:33');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('field','subject','level','curriculum','doc_type') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `parent_id` int DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `type`, `description`, `parent_id`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'Toán học', 'field', NULL, NULL, 1, 1, '2025-12-18 13:28:39'),
(2, 'Vật lý', 'field', NULL, NULL, 2, 1, '2025-12-18 13:28:39'),
(3, 'Hóa học', 'field', NULL, NULL, 3, 1, '2025-12-18 13:28:39'),
(4, 'Sinh học', 'field', NULL, NULL, 4, 1, '2025-12-18 13:28:39'),
(5, 'Tin học', 'field', NULL, NULL, 5, 1, '2025-12-18 13:28:39'),
(6, 'Công nghệ thông tin', 'field', NULL, NULL, 6, 1, '2025-12-18 13:28:39'),
(7, 'Khoa học máy tính', 'field', NULL, NULL, 7, 1, '2025-12-18 13:28:39'),
(8, 'Kỹ thuật', 'field', NULL, NULL, 8, 1, '2025-12-18 13:28:39'),
(9, 'Kinh tế', 'field', NULL, NULL, 9, 1, '2025-12-18 13:28:39'),
(10, 'Quản trị kinh doanh', 'field', NULL, NULL, 10, 1, '2025-12-18 13:28:39'),
(11, 'Kế toán – Tài chính', 'field', NULL, NULL, 11, 1, '2025-12-18 13:28:39'),
(12, 'Luật', 'field', NULL, NULL, 12, 1, '2025-12-18 13:28:39'),
(13, 'Khoa học chính trị', 'field', NULL, NULL, 13, 1, '2025-12-18 13:28:39'),
(14, 'Tâm lý học', 'field', NULL, NULL, 14, 1, '2025-12-18 13:28:39'),
(15, 'Xã hội học', 'field', NULL, NULL, 15, 1, '2025-12-18 13:28:39'),
(16, 'Giáo dục', 'field', NULL, NULL, 16, 1, '2025-12-18 13:28:39'),
(17, 'Lịch sử', 'field', NULL, NULL, 17, 1, '2025-12-18 13:28:39'),
(18, 'Địa lý', 'field', NULL, NULL, 18, 1, '2025-12-18 13:28:39'),
(19, 'Ngữ văn', 'field', NULL, NULL, 19, 1, '2025-12-18 13:28:39'),
(20, 'Ngoại ngữ', 'field', NULL, NULL, 20, 1, '2025-12-18 13:28:39'),
(21, 'Y học', 'field', NULL, NULL, 21, 1, '2025-12-18 13:28:39'),
(22, 'Dược học', 'field', NULL, NULL, 22, 1, '2025-12-18 13:28:39'),
(23, 'Điều dưỡng', 'field', NULL, NULL, 23, 1, '2025-12-18 13:28:39'),
(24, 'Môi trường', 'field', NULL, NULL, 24, 1, '2025-12-18 13:28:39'),
(25, 'Kiến trúc', 'field', NULL, NULL, 25, 1, '2025-12-18 13:28:39'),
(26, 'Mỹ thuật – Thiết kế', 'field', NULL, NULL, 26, 1, '2025-12-18 13:28:39'),
(27, 'Âm nhạc', 'field', NULL, NULL, 27, 1, '2025-12-18 13:28:39'),
(28, 'Số học', 'subject', NULL, NULL, 1, 1, '2025-12-18 13:28:39'),
(29, 'Đại số', 'subject', NULL, NULL, 2, 1, '2025-12-18 13:28:39'),
(30, 'Hình học', 'subject', NULL, NULL, 3, 1, '2025-12-18 13:28:39'),
(31, 'Lượng giác', 'subject', NULL, NULL, 4, 1, '2025-12-18 13:28:39'),
(32, 'Tiền giải tích', 'subject', NULL, NULL, 5, 1, '2025-12-18 13:28:39'),
(33, 'Giải tích', 'subject', NULL, NULL, 6, 1, '2025-12-18 13:28:39'),
(34, 'Đại số tuyến tính', 'subject', NULL, NULL, 7, 1, '2025-12-18 13:28:39'),
(35, 'Xác suất', 'subject', NULL, NULL, 8, 1, '2025-12-18 13:28:39'),
(36, 'Thống kê', 'subject', NULL, NULL, 9, 1, '2025-12-18 13:28:39'),
(37, 'Toán rời rạc', 'subject', NULL, NULL, 10, 1, '2025-12-18 13:28:39'),
(38, 'Phương trình vi phân', 'subject', NULL, NULL, 11, 1, '2025-12-18 13:28:39'),
(39, 'Nhập môn lập trình', 'subject', NULL, NULL, 20, 1, '2025-12-18 13:28:39'),
(40, 'Cấu trúc dữ liệu', 'subject', NULL, NULL, 21, 1, '2025-12-18 13:28:39'),
(41, 'Giải thuật', 'subject', NULL, NULL, 22, 1, '2025-12-18 13:28:39'),
(42, 'Cơ sở dữ liệu', 'subject', NULL, NULL, 23, 1, '2025-12-18 13:28:39'),
(43, 'Hệ điều hành', 'subject', NULL, NULL, 24, 1, '2025-12-18 13:28:39'),
(44, 'Mạng máy tính', 'subject', NULL, NULL, 25, 1, '2025-12-18 13:28:39'),
(45, 'Công nghệ phần mềm', 'subject', NULL, NULL, 26, 1, '2025-12-18 13:28:39'),
(46, 'Trí tuệ nhân tạo', 'subject', NULL, NULL, 27, 1, '2025-12-18 13:28:39'),
(47, 'Học máy', 'subject', NULL, NULL, 28, 1, '2025-12-18 13:28:39'),
(48, 'An toàn thông tin', 'subject', NULL, NULL, 29, 1, '2025-12-18 13:28:39'),
(49, 'Kinh tế vi mô', 'subject', NULL, NULL, 30, 1, '2025-12-18 13:28:39'),
(50, 'Kinh tế vĩ mô', 'subject', NULL, NULL, 31, 1, '2025-12-18 13:28:39'),
(51, 'Kinh tế lượng', 'subject', NULL, NULL, 32, 1, '2025-12-18 13:28:39'),
(52, 'Kinh tế phát triển', 'subject', NULL, NULL, 33, 1, '2025-12-18 13:28:39'),
(53, 'Kinh tế quốc tế', 'subject', NULL, NULL, 34, 1, '2025-12-18 13:28:39'),
(54, 'Marketing', 'subject', NULL, NULL, 35, 1, '2025-12-18 13:28:39'),
(55, 'Quản trị nhân sự', 'subject', NULL, NULL, 36, 1, '2025-12-18 13:28:39'),
(56, 'Quản trị chiến lược', 'subject', NULL, NULL, 37, 1, '2025-12-18 13:28:39'),
(57, 'Tài chính doanh nghiệp', 'subject', NULL, NULL, 38, 1, '2025-12-18 13:28:39'),
(58, 'Kế toán quản trị', 'subject', NULL, NULL, 39, 1, '2025-12-18 13:28:39'),
(59, 'Vật lý đại cương', 'subject', NULL, NULL, 40, 1, '2025-12-18 13:28:39'),
(60, 'Cơ học', 'subject', NULL, NULL, 41, 1, '2025-12-18 13:28:39'),
(61, 'Điện từ học', 'subject', NULL, NULL, 42, 1, '2025-12-18 13:28:39'),
(62, 'Hóa đại cương', 'subject', NULL, NULL, 43, 1, '2025-12-18 13:28:39'),
(63, 'Hóa hữu cơ', 'subject', NULL, NULL, 44, 1, '2025-12-18 13:28:39'),
(64, 'Hóa vô cơ', 'subject', NULL, NULL, 45, 1, '2025-12-18 13:28:39'),
(65, 'Sinh học phân tử', 'subject', NULL, NULL, 46, 1, '2025-12-18 13:28:39'),
(66, 'Di truyền học', 'subject', NULL, NULL, 47, 1, '2025-12-18 13:28:39'),
(67, 'Tiếng Anh', 'subject', NULL, NULL, 48, 1, '2025-12-18 13:28:39'),
(68, 'Tiếng Trung', 'subject', NULL, NULL, 49, 1, '2025-12-18 13:28:39'),
(69, 'Tiếng Nhật', 'subject', NULL, NULL, 50, 1, '2025-12-18 13:28:39'),
(70, 'Tiểu học', 'level', NULL, NULL, 1, 1, '2025-12-18 13:28:40'),
(71, 'THCS', 'level', NULL, NULL, 2, 1, '2025-12-18 13:28:40'),
(72, 'THPT', 'level', NULL, NULL, 3, 1, '2025-12-18 13:28:40'),
(73, 'Đại học', 'level', NULL, NULL, 4, 1, '2025-12-18 13:28:40'),
(74, 'Sau đại học', 'level', NULL, NULL, 5, 1, '2025-12-18 13:28:40'),
(75, 'Nghiên cứu sinh', 'level', NULL, NULL, 6, 1, '2025-12-18 13:28:40'),
(76, 'Chứng chỉ nghề / Chuyên môn', 'level', NULL, NULL, 7, 1, '2025-12-18 13:28:40'),
(77, 'Chương trình Bộ GD&ĐT Việt Nam', 'curriculum', NULL, NULL, 1, 1, '2025-12-18 13:28:40'),
(78, 'Chương trình Mỹ (Common Core)', 'curriculum', NULL, NULL, 2, 1, '2025-12-18 13:28:40'),
(79, 'Chương trình Anh (National Curriculum)', 'curriculum', NULL, NULL, 3, 1, '2025-12-18 13:28:40'),
(80, 'IB (Tú tài quốc tế)', 'curriculum', NULL, NULL, 4, 1, '2025-12-18 13:28:40'),
(81, 'Cambridge', 'curriculum', NULL, NULL, 5, 1, '2025-12-18 13:28:40'),
(82, 'AP (Advanced Placement)', 'curriculum', NULL, NULL, 6, 1, '2025-12-18 13:28:40'),
(83, 'Chương trình đại học (chung)', 'curriculum', NULL, NULL, 7, 1, '2025-12-18 13:28:40'),
(84, 'Tự học', 'curriculum', NULL, NULL, 8, 1, '2025-12-18 13:28:40'),
(85, 'Không xác định', 'curriculum', NULL, NULL, 9, 1, '2025-12-18 13:28:40'),
(86, 'Bài giảng', 'doc_type', NULL, NULL, 1, 1, '2025-12-18 13:28:40'),
(87, 'Ghi chú học tập', 'doc_type', NULL, NULL, 2, 1, '2025-12-18 13:28:40'),
(88, 'Sách giáo khoa', 'doc_type', NULL, NULL, 3, 1, '2025-12-18 13:28:40'),
(89, 'Tài liệu tổng hợp', 'doc_type', NULL, NULL, 4, 1, '2025-12-18 13:28:40'),
(90, 'Bài tập', 'doc_type', NULL, NULL, 5, 1, '2025-12-18 13:28:40'),
(91, 'Bài tập có lời giải', 'doc_type', NULL, NULL, 6, 1, '2025-12-18 13:28:40'),
(92, 'Đề kiểm tra', 'doc_type', NULL, NULL, 7, 1, '2025-12-18 13:28:40'),
(93, 'Đề thi giữa kỳ', 'doc_type', NULL, NULL, 8, 1, '2025-12-18 13:28:40'),
(94, 'Đề thi cuối kỳ', 'doc_type', NULL, NULL, 9, 1, '2025-12-18 13:28:40'),
(95, 'Đề thi thử', 'doc_type', NULL, NULL, 10, 1, '2025-12-18 13:28:40'),
(96, 'Báo cáo dự án', 'doc_type', NULL, NULL, 11, 1, '2025-12-18 13:28:40'),
(97, 'Khóa luận / Luận văn', 'doc_type', NULL, NULL, 12, 1, '2025-12-18 13:28:40'),
(98, 'Slide thuyết trình', 'doc_type', NULL, NULL, 13, 1, '2025-12-18 13:28:40'),
(99, 'Báo cáo thí nghiệm', 'doc_type', NULL, NULL, 14, 1, '2025-12-18 13:28:40'),
(100, 'Tình huống nghiên cứu', 'doc_type', NULL, NULL, 15, 1, '2025-12-18 13:28:40'),
(101, 'Bài báo khoa học', 'doc_type', NULL, NULL, 16, 1, '2025-12-18 13:28:40'),
(102, 'Giáo án', 'doc_type', NULL, NULL, 17, 1, '2025-12-18 13:28:40'),
(103, 'Tài liệu giảng dạy', 'doc_type', NULL, NULL, 18, 1, '2025-12-18 13:28:40');

-- --------------------------------------------------------

--
-- Table structure for table `docs_points`
--

CREATE TABLE `docs_points` (
  `id` int NOT NULL,
  `document_id` int NOT NULL,
  `admin_points` int NOT NULL DEFAULT '0',
  `assigned_by` int DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `docs_points`
--

INSERT INTO `docs_points` (`id`, `document_id`, `admin_points`, `assigned_by`, `assigned_at`, `notes`) VALUES
(1, 23, 2, 1, '2025-12-16 14:58:38', ''),
(2, 22, 2, 1, '2025-12-16 14:58:59', 'haaa'),
(3, 24, 10, 1, '2025-12-17 04:26:33', '111'),
(4, 25, 2, 1, '2025-12-17 04:29:37', 'aâ'),
(5, 27, 1, 1, '2025-12-17 14:37:50', ''),
(6, 26, 50, 1, '2025-12-17 14:37:53', ''),
(7, 28, 50, 1, '2025-12-18 07:14:39', ''),
(8, 29, 9, 1, '2025-12-18 07:15:50', ''),
(9, 31, 10, 1, '2025-12-18 13:46:07', '');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_public` tinyint(1) DEFAULT '1',
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `views` int DEFAULT '0',
  `downloads` int DEFAULT '0',
  `admin_points` int DEFAULT '0',
  `user_price` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `user_id`, `original_name`, `file_name`, `description`, `created_at`, `is_public`, `status`, `views`, `downloads`, `admin_points`, `user_price`) VALUES
(22, 1, 'vocabulary and grammar- ONLINE.docx', '6941737965a78_1765897081.docx', '', '2025-12-16 14:58:01', 1, 'approved', 0, 0, 2, 0),
(23, 1, 'Thuyết trình Hoá.docx', '69417379d670e_1765897081.docx', '', '2025-12-16 14:58:01', 1, 'approved', 2, 0, 2, 0),
(24, 2, 'tiêu chípdf.pdf', '694230e208263_1765945570.pdf', '', '2025-12-17 04:26:10', 1, 'approved', 1, 0, 10, 0),
(25, 2, 'Ielts.docx', '6942319e92905_1765945758.docx', '', '2025-12-17 04:29:18', 1, 'approved', 1, 0, 2, 0),
(26, 2, 'vocabulary and grammar- ONLINE.docx', '694234974202b_1765946519.docx', '', '2025-12-17 04:41:59', 1, 'approved', 2, 0, 50, 0),
(27, 1, 'TIN 12.docx', '6942c0265cf65_1765982246.docx', 'aaaaaaaaa', '2025-12-17 14:37:26', 1, 'approved', 1, 0, 1, 0),
(28, 1, 'EBOOK PHONG TOẢ VẬT LÝ 11 - TẬP 2  .pdf', '6943a88ee6faf_1766041742.pdf', '', '2025-12-18 07:09:03', 1, 'approved', 1, 1, 50, 0),
(29, 2, 'ĐỀ NGÀY 25.docx', '6943aa136acb2_1766042131.docx', '', '2025-12-18 07:15:31', 1, 'approved', 2, 0, 9, 0),
(31, 1, 'DE CHINH THUC HUONG DAN VA GIAI THICH.docx', '6944055e6254f_1766065502.docx', 'Tin Học', '2025-12-18 13:45:02', 1, 'approved', 2, 6, 10, 0);

-- --------------------------------------------------------

--
-- Table structure for table `document_categories`
--

CREATE TABLE `document_categories` (
  `id` int NOT NULL,
  `document_id` int NOT NULL,
  `category_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `document_categories`
--

INSERT INTO `document_categories` (`id`, `document_id`, `category_id`, `created_at`) VALUES
(1, 31, 5, '2025-12-18 13:45:02'),
(2, 31, 72, '2025-12-18 13:45:02'),
(3, 31, 77, '2025-12-18 13:45:02'),
(4, 31, 90, '2025-12-18 13:45:02');

-- --------------------------------------------------------

--
-- Table structure for table `document_interactions`
--

CREATE TABLE `document_interactions` (
  `id` int NOT NULL,
  `document_id` int NOT NULL,
  `user_id` int NOT NULL,
  `type` enum('like','dislike','save') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `document_interactions`
--

INSERT INTO `document_interactions` (`id`, `document_id`, `user_id`, `type`, `created_at`) VALUES
(10, 23, 2, 'like', '2025-12-17 15:43:43'),
(11, 29, 1, 'save', '2025-12-18 07:17:02'),
(17, 31, 1, 'like', '2025-12-21 10:44:32');

-- --------------------------------------------------------

--
-- Table structure for table `document_reports`
--

CREATE TABLE `document_reports` (
  `id` int NOT NULL,
  `document_id` int NOT NULL,
  `user_id` int NOT NULL,
  `reason` text,
  `status` enum('pending','reviewed','dismissed') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_sales`
--

CREATE TABLE `document_sales` (
  `id` int NOT NULL,
  `document_id` int NOT NULL,
  `buyer_user_id` int NOT NULL,
  `seller_user_id` int NOT NULL,
  `points_paid` int NOT NULL,
  `transaction_id` int DEFAULT NULL,
  `purchased_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `document_sales`
--

INSERT INTO `document_sales` (`id`, `document_id`, `buyer_user_id`, `seller_user_id`, `points_paid`, `transaction_id`, `purchased_at`) VALUES
(1, 24, 1, 2, 10, 5, '2025-12-17 14:42:30'),
(2, 25, 1, 2, 2, 7, '2025-12-17 15:28:11'),
(3, 27, 2, 1, 1, 9, '2025-12-17 15:29:22'),
(6, 22, 2, 1, 2, 12, '2025-12-17 15:41:12'),
(7, 23, 2, 1, 2, 13, '2025-12-17 15:42:08'),
(8, 29, 1, 2, 9, 16, '2025-12-18 07:16:31');

-- --------------------------------------------------------

--
-- Table structure for table `document_shares`
--

CREATE TABLE `document_shares` (
  `id` int NOT NULL,
  `document_id` int NOT NULL,
  `shared_with_id` int NOT NULL,
  `shared_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_verification`
--

CREATE TABLE `document_verification` (
  `id` int NOT NULL,
  `document_id` int NOT NULL,
  `is_verified` tinyint(1) DEFAULT '0',
  `verified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `point_transactions`
--

CREATE TABLE `point_transactions` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `transaction_type` enum('earn','spend') NOT NULL,
  `points` int NOT NULL,
  `related_document_id` int DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'completed',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `point_transactions`
--

INSERT INTO `point_transactions` (`id`, `user_id`, `transaction_type`, `points`, `related_document_id`, `reason`, `status`, `created_at`) VALUES
(1, 2, 'spend', 100, NULL, '.', 'completed', '2025-12-17 14:02:06'),
(2, 1, 'earn', 1, 27, 'Tài liệu của bạn đã được duyệt ', 'completed', '2025-12-17 14:37:50'),
(3, 2, 'earn', 50, 26, 'Tài liệu của bạn đã được duyệt ', 'completed', '2025-12-17 14:37:53'),
(4, 1, 'spend', 10, 24, 'Purchased document: ', 'completed', '2025-12-17 14:42:30'),
(5, 1, 'spend', 10, 24, 'Document Purchase', 'completed', '2025-12-17 14:42:30'),
(6, 1, 'spend', 2, 25, 'Purchased document: ', 'completed', '2025-12-17 15:28:11'),
(7, 1, 'spend', 2, 25, 'Document Purchase', 'completed', '2025-12-17 15:28:11'),
(8, 2, 'spend', 1, 27, 'Purchased document: ', 'completed', '2025-12-17 15:29:22'),
(9, 2, 'spend', 1, 27, 'Document Purchase', 'completed', '2025-12-17 15:29:22'),
(10, 2, 'spend', 2, 22, 'Mua tài liệu: vocabulary and grammar- ONLINE.docx', 'completed', '2025-12-17 15:37:12'),
(11, 2, 'spend', 2, 22, 'Mua tài liệu: vocabulary and grammar- ONLINE.docx', 'completed', '2025-12-17 15:38:54'),
(12, 2, 'spend', 2, 22, 'Mua tài liệu: vocabulary and grammar- ONLINE.docx', 'completed', '2025-12-17 15:41:12'),
(13, 2, 'spend', 2, 23, 'Mua tài liệu: Thuyết trình Hoá.docx', 'completed', '2025-12-17 15:42:08'),
(14, 1, 'earn', 50, 28, 'Tài liệu của bạn đã được duyệt ', 'completed', '2025-12-18 07:14:39'),
(15, 2, 'earn', 9, 29, 'Tài liệu của bạn đã được duyệt ', 'completed', '2025-12-18 07:15:50'),
(16, 1, 'spend', 9, 29, 'Mua tài liệu: ĐỀ NGÀY 25.docx', 'completed', '2025-12-18 07:16:31'),
(17, 1, 'earn', 10, 31, 'Tài liệu của bạn đã được duyệt ', 'completed', '2025-12-18 13:46:07');

-- --------------------------------------------------------

--
-- Table structure for table `premium`
--

CREATE TABLE `premium` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `plan_type` enum('monthly','free_trial') DEFAULT 'monthly',
  `start_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `end_date` datetime NOT NULL,
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `premium`
--

INSERT INTO `premium` (`id`, `user_id`, `plan_type`, `start_date`, `end_date`, `is_active`) VALUES
(2, 1, 'free_trial', '2025-12-15 07:54:30', '2025-12-02 07:54:30', 1),
(3, 1, 'monthly', '2025-12-15 08:13:33', '2026-01-14 08:13:33', 1),
(4, 1, 'monthly', '2025-12-15 08:13:42', '2026-01-14 08:13:42', 1),
(5, 1, 'monthly', '2025-12-15 08:13:47', '2026-01-14 08:13:47', 1);

-- --------------------------------------------------------

--
-- Table structure for table `search_history`
--

CREATE TABLE `search_history` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `keyword` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `filters` text COLLATE utf8mb4_unicode_ci,
  `results_count` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `search_history`
--

INSERT INTO `search_history` (`id`, `user_id`, `keyword`, `filters`, `results_count`, `created_at`) VALUES
(1, 1, 'đề ngày', '[]', 1, '2025-12-18 14:48:30'),
(2, 1, 'đề ngày', '[]', 1, '2025-12-18 14:48:54'),
(3, 1, 'đề ngày', '[]', 1, '2025-12-18 14:48:56'),
(4, 1, 'đề ngày', '[]', 1, '2025-12-18 14:48:57'),
(5, 2, 'tin học', '[]', 2, '2025-12-18 14:50:52'),
(6, 2, 'thuyet trinh', '[]', 1, '2025-12-18 14:52:51'),
(7, 2, 'thuyet', '[]', 1, '2025-12-18 14:53:09'),
(8, 2, 'th', '[]', 0, '2025-12-18 14:53:13'),
(9, 2, 'đề ngày', '[]', 1, '2025-12-18 14:53:16'),
(10, 1, 'tài liệu', '[]', 0, '2025-12-18 14:59:47'),
(11, 1, 'tiếng anh', '[]', 0, '2025-12-18 14:59:49'),
(12, 1, 'hóa học', '[]', 2, '2025-12-18 14:59:51'),
(13, 1, 'đề ngày', '[]', 1, '2025-12-19 12:51:33'),
(14, 1, 'hướng dẫn', '[]', 1, '2025-12-19 12:57:57'),
(15, 1, 'ielts', '[]', 1, '2025-12-19 12:58:09'),
(16, 2, 'đề ngày', '[]', 1, '2025-12-20 12:44:42'),
(17, 2, 'đề ngày', '[]', 1, '2025-12-20 12:44:43'),
(18, 2, 'đề ngày', '[]', 1, '2025-12-20 12:44:44');

-- --------------------------------------------------------

--
-- Table structure for table `search_suggestions`
--

CREATE TABLE `search_suggestions` (
  `id` int NOT NULL,
  `keyword` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `search_count` int DEFAULT '1',
  `results_count_avg` int DEFAULT '0',
  `last_searched` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `search_suggestions`
--

INSERT INTO `search_suggestions` (`id`, `keyword`, `search_count`, `results_count_avg`, `last_searched`, `created_at`) VALUES
(1, 'đề ngày', 9, 1, '2025-12-20 12:44:44', '2025-12-18 14:48:30'),
(5, 'tin học', 1, 2, '2025-12-18 14:50:52', '2025-12-18 14:50:52'),
(6, 'thuyet trinh', 1, 1, '2025-12-18 14:52:51', '2025-12-18 14:52:51'),
(7, 'thuyet', 1, 1, '2025-12-18 14:53:09', '2025-12-18 14:53:09'),
(8, 'th', 1, 0, '2025-12-18 14:53:13', '2025-12-18 14:53:13'),
(10, 'toán học', 50, 15, '2025-12-18 14:59:23', '2025-12-18 14:59:23'),
(11, 'lập trình', 45, 20, '2025-12-18 14:59:23', '2025-12-18 14:59:23'),
(12, 'tiếng anh', 41, 13, '2025-12-18 14:59:49', '2025-12-18 14:59:23'),
(13, 'vật lý', 35, 10, '2025-12-18 14:59:23', '2025-12-18 14:59:23'),
(14, 'hóa học', 31, 7, '2025-12-18 14:59:51', '2025-12-18 14:59:23'),
(15, 'sinh học', 28, 18, '2025-12-18 14:59:23', '2025-12-18 14:59:23'),
(16, 'văn học', 25, 14, '2025-12-18 14:59:23', '2025-12-18 14:59:23'),
(17, 'lịch sử', 22, 16, '2025-12-18 14:59:23', '2025-12-18 14:59:23'),
(18, 'địa lý', 20, 11, '2025-12-18 14:59:23', '2025-12-18 14:59:23'),
(19, 'kinh tế', 18, 13, '2025-12-18 14:59:23', '2025-12-18 14:59:23'),
(20, 'tài liệu', 1, 0, '2025-12-18 14:59:47', '2025-12-18 14:59:47'),
(24, 'hướng dẫn', 1, 1, '2025-12-19 12:57:57', '2025-12-19 12:57:57'),
(25, 'ielts', 1, 1, '2025-12-19 12:58:09', '2025-12-19 12:58:09');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_type` enum('monthly','document_upload') DEFAULT 'monthly',
  `status` enum('pending','success','failed') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `amount`, `transaction_type`, `status`, `created_at`) VALUES
(1, 1, 29.00, 'monthly', 'success', '2025-12-15 08:13:33'),
(2, 1, 29.00, 'monthly', 'success', '2025-12-15 08:13:42'),
(3, 1, 29.00, 'monthly', 'success', '2025-12-15 08:13:47');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `verified_documents_count` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `role`, `password`, `created_at`, `verified_documents_count`) VALUES
(1, 'buomem', 'bluevn26275260@gmail.com', 'admin', '$2y$10$UvW2RNSLEXYOvjVVE6kdbegozMMTzlYMCDyQoI3dMOg04PL8y/snq', '2025-12-15 05:34:10', 0),
(2, 'admm', 'admm@gmail.com', 'user', '$2y$10$Q5kGr2PqTqIpp3lvs6vDHOUvY7iYA9R/4A2cnCtO5DLlNAIufJNnW', '2025-12-15 06:59:11', 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_points`
--

CREATE TABLE `user_points` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `current_points` int DEFAULT '0',
  `total_earned` int DEFAULT '0',
  `total_spent` int DEFAULT '0',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_points`
--

INSERT INTO `user_points` (`id`, `user_id`, `current_points`, `total_earned`, `total_spent`, `last_updated`) VALUES
(1, 2, 50, 159, 109, '2025-12-18 07:15:50'),
(2, 1, 140, 161, 21, '2025-12-18 13:46:07');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_approvals`
--
ALTER TABLE `admin_approvals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `document_id` (`document_id`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `idx_unread` (`admin_id`,`is_read`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_parent` (`parent_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `docs_points`
--
ALTER TABLE `docs_points`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `document_id` (`document_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_views` (`views`),
  ADD KEY `idx_downloads` (`downloads`);
ALTER TABLE `documents` ADD FULLTEXT KEY `ft_search` (`original_name`,`description`);

--
-- Indexes for table `document_categories`
--
ALTER TABLE `document_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_doc_cat` (`document_id`,`category_id`),
  ADD KEY `idx_document` (`document_id`),
  ADD KEY `idx_category` (`category_id`);

--
-- Indexes for table `document_interactions`
--
ALTER TABLE `document_interactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_interaction` (`document_id`,`user_id`,`type`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `document_reports`
--
ALTER TABLE `document_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `document_sales`
--
ALTER TABLE `document_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `idx_document` (`document_id`),
  ADD KEY `idx_buyer` (`buyer_user_id`),
  ADD KEY `idx_seller` (`seller_user_id`);

--
-- Indexes for table `document_shares`
--
ALTER TABLE `document_shares`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_share` (`document_id`,`shared_with_id`),
  ADD KEY `shared_with_id` (`shared_with_id`);

--
-- Indexes for table `document_verification`
--
ALTER TABLE `document_verification`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`);

--
-- Indexes for table `point_transactions`
--
ALTER TABLE `point_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `related_document_id` (`related_document_id`),
  ADD KEY `idx_user_date` (`user_id`,`created_at`),
  ADD KEY `idx_type` (`transaction_type`);

--
-- Indexes for table `premium`
--
ALTER TABLE `premium`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `search_history`
--
ALTER TABLE `search_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_keyword` (`keyword`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `search_suggestions`
--
ALTER TABLE `search_suggestions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `keyword` (`keyword`),
  ADD KEY `idx_keyword` (`keyword`),
  ADD KEY `idx_count` (`search_count`),
  ADD KEY `idx_last_searched` (`last_searched`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_points`
--
ALTER TABLE `user_points`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_approvals`
--
ALTER TABLE `admin_approvals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT for table `docs_points`
--
ALTER TABLE `docs_points`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `document_categories`
--
ALTER TABLE `document_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `document_interactions`
--
ALTER TABLE `document_interactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `document_reports`
--
ALTER TABLE `document_reports`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_sales`
--
ALTER TABLE `document_sales`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `document_shares`
--
ALTER TABLE `document_shares`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_verification`
--
ALTER TABLE `document_verification`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `point_transactions`
--
ALTER TABLE `point_transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `premium`
--
ALTER TABLE `premium`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `search_history`
--
ALTER TABLE `search_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `search_suggestions`
--
ALTER TABLE `search_suggestions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_points`
--
ALTER TABLE `user_points`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_approvals`
--
ALTER TABLE `admin_approvals`
  ADD CONSTRAINT `admin_approvals_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_approvals_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD CONSTRAINT `admin_notifications_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_notifications_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `docs_points`
--
ALTER TABLE `docs_points`
  ADD CONSTRAINT `docs_points_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `docs_points_ibfk_2` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_categories`
--
ALTER TABLE `document_categories`
  ADD CONSTRAINT `document_categories_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_interactions`
--
ALTER TABLE `document_interactions`
  ADD CONSTRAINT `document_interactions_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_interactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_reports`
--
ALTER TABLE `document_reports`
  ADD CONSTRAINT `document_reports_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_reports_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_sales`
--
ALTER TABLE `document_sales`
  ADD CONSTRAINT `document_sales_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_sales_ibfk_2` FOREIGN KEY (`buyer_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_sales_ibfk_3` FOREIGN KEY (`seller_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_sales_ibfk_4` FOREIGN KEY (`transaction_id`) REFERENCES `point_transactions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `document_shares`
--
ALTER TABLE `document_shares`
  ADD CONSTRAINT `document_shares_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_shares_ibfk_2` FOREIGN KEY (`shared_with_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_verification`
--
ALTER TABLE `document_verification`
  ADD CONSTRAINT `document_verification_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `point_transactions`
--
ALTER TABLE `point_transactions`
  ADD CONSTRAINT `point_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `point_transactions_ibfk_2` FOREIGN KEY (`related_document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `premium`
--
ALTER TABLE `premium`
  ADD CONSTRAINT `premium_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_points`
--
ALTER TABLE `user_points`
  ADD CONSTRAINT `user_points_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
