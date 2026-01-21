-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 02, 2026 at 05:16 PM
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
-- Database: `eatease`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `hotel_id` int(10) UNSIGNED NOT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `number_of_guests` int(3) UNSIGNED NOT NULL,
  `special_requests` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `user_id`, `hotel_id`, `booking_date`, `booking_time`, `number_of_guests`, `special_requests`, `status`, `created_at`, `updated_at`) VALUES
(34, 2, 14, '2025-12-25', '21:23:00', 2, '', 'confirmed', '2025-12-24 21:21:07', '2025-12-24 21:21:40'),
(35, 2, 14, '2025-12-26', '11:37:00', 1, '', 'confirmed', '2025-12-24 21:37:42', '2025-12-24 21:38:09'),
(36, 2, 14, '2025-12-27', '21:03:00', 3, 'ghjn', 'pending', '2025-12-24 22:03:28', '2025-12-24 22:05:39'),
(37, 2, 15, '2025-12-26', '19:22:00', 2, '', 'confirmed', '2025-12-25 17:22:05', '2025-12-25 17:22:38'),
(38, 2, 16, '2026-01-01', '21:30:00', 2, '', 'confirmed', '2025-12-25 21:29:50', '2025-12-25 21:30:22'),
(40, 2, 15, '2025-12-31', '04:18:00', 10, '', 'confirmed', '2025-12-26 16:18:44', '2025-12-26 16:19:15'),
(41, 2, 15, '2026-01-02', '22:40:00', 5, '', 'confirmed', '2025-12-26 16:24:21', '2025-12-26 16:25:04'),
(42, 2, 15, '2026-01-01', '19:44:00', 2, '', 'confirmed', '2025-12-26 16:44:13', '2025-12-26 16:45:57'),
(43, 2, 16, '2026-01-03', '21:46:00', 2, '', 'confirmed', '2025-12-26 16:46:20', '2025-12-26 16:46:45'),
(44, 2, 16, '2026-01-01', '22:00:00', 5, '', 'pending', '2025-12-31 13:48:08', '2025-12-31 13:49:35'),
(45, 2, 15, '2026-01-01', '21:00:00', 1, 'ok', 'confirmed', '2025-12-31 13:52:55', '2025-12-31 13:53:31'),
(46, 2, 14, '2026-01-02', '14:32:00', 3, '', 'confirmed', '2025-12-31 14:30:04', '2025-12-31 14:30:40'),
(47, 2, 15, '2026-01-03', '21:33:00', 4, 'yubinj hjij', 'confirmed', '2025-12-31 14:33:59', '2025-12-31 14:34:58'),
(48, 2, 15, '2026-01-02', '20:17:00', 11, '', 'confirmed', '2025-12-31 18:17:34', '2025-12-31 18:18:14');

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `hotel_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hotels`
--

CREATE TABLE `hotels` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `cuisine_type` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `open_time` time DEFAULT NULL,
  `close_time` time DEFAULT NULL,
  `price_range` varchar(10) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `avg_rating` decimal(3,2) DEFAULT 0.00,
  `total_ratings` int(10) UNSIGNED DEFAULT 0,
  `owner_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hotels`
--

INSERT INTO `hotels` (`id`, `name`, `description`, `cuisine_type`, `location`, `phone`, `open_time`, `close_time`, `price_range`, `image_url`, `avg_rating`, `total_ratings`, `owner_id`, `created_at`, `updated_at`, `deleted_at`) VALUES
(14, 'The Secret Kitchen', 'The secret kitchen - A vegetarian Saga is a restaurant that invites you to engage in the experience of Royal Indian Cuisine with a Secret twist by Chef Aanal Kotak.', 'All Types', 'Bus Stop, 9 10, 150 Feet Ring Rd, nr. Shital Park, Sheetal Park, Shastri Nagar, Dharam Nagar Society, Rajkot, Gujarat 360007', '7600917009', '12:30:00', '01:00:00', '₹₹₹', 'uploads/restaurants/restaurants_1766576062_b500c9ca.webp', 5.00, 1, 3, '2025-12-24 17:04:22', '2025-12-25 11:50:21', NULL),
(15, 'I❤️HEDKEY', 'Hedkey is a popular fast-food/cafe chain in Rajkot, Gujarat, known for its fusion of Chinese, Italian, Mexican, and Indian street food, offering dishes like Dragon Potato, Manchurian, pizzas, biryani, burgers, and shakes in a trendy, cozy setting with affordable options and takeaway/delivery services.', 'All Types', 'HEDKEY, New Eara, 150 Feet Ring Rd, opposite Reliance Mall, AP Park, Chandr, Rajkot, Gujarat 360005', '8799461028', '10:30:00', '23:30:00', '₹₹', 'uploads/restaurants/restaurants_1766663384_02f9f884.webp', 5.00, 1, 7, '2025-12-25 17:19:44', '2025-12-25 21:14:11', NULL),
(16, 'The Grand Thakar', 'The Grand Thakar in Limbdi Surendranagar,Surendra Nagar ...\r\nThe Grand Thakar in Rajkot, India, is a well-regarded 3-star hotel known for its excellent hospitality, comfortable rooms, and especially its fantastic traditional Gujarati Thali and other cuisines, offering great value with features like free Wi-Fi, helpful staff, and convenient transport services, making it a popular choice for both tourists and event hosting.', 'All Types', '8R22+RVG, Jawahar Rd, opp. Jubilee Road, Lohana Para, Rajkot, Gujarat 360001', '9687189099', '10:30:00', '01:00:00', '₹₹₹₹', 'uploads/restaurants/restaurants_1766678132_d6c83c39.webp', 5.00, 1, 8, '2025-12-25 21:25:32', '2026-01-02 18:24:17', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `hotel_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(8,2) NOT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `hotel_id`, `name`, `description`, `price`, `image_path`, `created_at`) VALUES
(6, 14, 'Mexican Sizzler', 'A Mexican sizzler is a complete, fusion-style meal served on a preheated cast iron or metal plate that creates a dramatic \"sizzling\" sound and a smoky aroma when presented.', 399.00, 'uploads/menu/menu_1766576256_05df9e7e.webp', '2025-12-24 17:07:36'),
(7, 14, 'Italian Platter', 'The top items appear to be mini pizzas or a similar cheesy, breaded appetizer with olives and jalapenos.', 199.00, 'uploads/menu/menu_1766580019_ffd343af.webp', '2025-12-24 18:10:19'),
(8, 14, 'Pink Penne Pasta', 'The vodka helps emulsify the sauce and enhance the flavors, with most of the alcohol evaporating during cooking.', 149.00, 'uploads/menu/menu_1766580125_d1c10469.webp', '2025-12-24 18:12:05'),
(9, 14, 'Dragon Drink', 'cocktail served in a decorative ceramic tiki mug with a light blue glaze, which is a common style of barware used for elaborate, rum-based drinks.', 99.00, 'uploads/menu/menu_1766580256_90332b86.webp', '2025-12-24 18:14:16'),
(10, 15, 'Mexican Sizzler', 'A Mexican sizzler is a complete, fusion-style meal served on a preheated cast iron or metal plate that creates a dramatic \"sizzling\" sound and a smoky aroma when presented.', 199.00, 'uploads/menu/menu_1766663412_caf1ba8c.webp', '2025-12-25 17:20:12'),
(11, 15, 'Dragon Drink', 'cocktail served in a decorative ceramic tiki mug with a light blue glaze, which is a common style of barware used for elaborate, rum-based drinks.', 99.00, 'uploads/menu/menu_1766677498_28d6b9d3.webp', '2025-12-25 21:14:58'),
(12, 15, 'Pink Penne Pasta', 'The vodka helps emulsify the sauce and enhance the flavors, with most of the alcohol evaporating during cooking.', 199.00, 'uploads/menu/menu_1766677654_e07fe41e.webp', '2025-12-25 21:17:34'),
(13, 15, 'Italian Platter', 'The top items appear to be mini pizzas or a similar cheesy, breaded appetizer with olives and jalapenos.', 399.00, 'uploads/menu/menu_1766677704_2bd626f8.webp', '2025-12-25 21:18:24'),
(14, 16, 'Mexican Sizzler', 'A Mexican sizzler is a complete, fusion-style meal served on a preheated cast iron or metal plate that creates a dramatic \"sizzling\" sound and a smoky aroma when presented.', 499.00, 'uploads/menu/menu_1766678232_277a5ccc.webp', '2025-12-25 21:27:12'),
(15, 16, 'Dragon Drink', 'cocktail served in a decorative ceramic tiki mug with a light blue glaze, which is a common style of barware used for elaborate, rum-based drinks.', 199.00, 'uploads/menu/menu_1766678259_7fc7ea6c.webp', '2025-12-25 21:27:39'),
(16, 16, 'Pink Penne Pasta', 'The vodka helps emulsify the sauce and enhance the flavors, with most of the alcohol evaporating during cooking.', 249.00, 'uploads/menu/menu_1766678292_ef121ad8.webp', '2025-12-25 21:28:12'),
(17, 16, 'Italian Platter', 'The top items appear to be mini pizzas or a similar cheesy, breaded appetizer with olives and jalapenos.', 399.00, 'uploads/menu/menu_1766678328_4d716d5d.webp', '2025-12-25 21:28:48');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `provider` varchar(30) NOT NULL,
  `provider_order_id` varchar(100) DEFAULT NULL,
  `provider_payment_id` varchar(100) DEFAULT NULL,
  `amount` int(11) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `status` varchar(20) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `booking_id`, `provider`, `provider_order_id`, `provider_payment_id`, `amount`, `currency`, `status`, `created_at`, `updated_at`) VALUES
(1, 24, 'razorpay', 'order_Rv0GmkJuza4vai', 'pay_Rv0HKTqytLCTUY', 10000, 'INR', 'captured', '2025-12-23 14:57:53', '2025-12-23 14:58:42'),
(2, 25, 'razorpay', 'order_Rv0UGH8gnhylkB', 'pay_Rv0UPuEyTehOVI', 10000, 'INR', 'captured', '2025-12-23 15:10:39', '2025-12-23 15:11:06'),
(3, 26, 'razorpay', 'order_Rv0ksh5xyP0BHk', 'pay_Rv0kzJDLZgkjiQ', 10000, 'INR', 'captured', '2025-12-23 15:26:23', '2025-12-23 15:26:46'),
(4, 27, 'razorpay', 'order_Rv0vhUDMAzDWIg', 'pay_Rv0vq2k8RFtAY5', 10000, 'INR', 'captured', '2025-12-23 15:36:37', '2025-12-23 15:37:02'),
(5, 23, 'razorpay', 'order_Rv12mSkhzJCmqU', NULL, 10000, 'INR', 'pending', '2025-12-23 15:43:20', '2025-12-23 15:43:21'),
(6, 28, 'razorpay', 'order_Rv13TvndExbU2k', 'pay_Rv13boxUNTefmf', 10000, 'INR', 'captured', '2025-12-23 15:44:00', '2025-12-23 15:44:24'),
(7, 29, 'razorpay', 'order_Rv1C4oYTmHhPzC', 'pay_Rv1CBv6TssH8b0', 10000, 'INR', 'captured', '2025-12-23 15:52:07', '2025-12-23 15:52:30'),
(8, 30, 'razorpay', 'order_Rv1D4ZSAVjftxk', NULL, 10000, 'INR', 'pending', '2025-12-23 15:53:04', '2025-12-23 15:53:06'),
(9, 30, 'razorpay', 'order_Rv1DdH0EewF6KO', NULL, 10000, 'INR', 'pending', '2025-12-23 15:53:36', '2025-12-23 15:53:37'),
(10, 30, 'razorpay', 'order_Rv1E0KWnS9FmUS', NULL, 10000, 'INR', 'pending', '2025-12-23 15:53:57', '2025-12-23 15:53:59'),
(11, 31, 'razorpay', 'order_Rv1qNeG8BJhnqR', NULL, 10000, 'INR', 'pending', '2025-12-23 16:30:17', '2025-12-23 16:30:18'),
(12, 32, 'razorpay', 'order_Rv1rY1h9AyGFPe', 'pay_Rv1rdqubPzjplz', 10000, 'INR', 'captured', '2025-12-23 16:31:24', '2025-12-23 16:31:45'),
(13, 33, 'razorpay', NULL, NULL, 10000, 'INR', 'pending', '2025-12-24 08:18:31', NULL),
(14, 33, 'razorpay', 'order_RvI0knbDws7aLZ', 'pay_RvI0tEnUVdjHQa', 10000, 'INR', 'captured', '2025-12-24 08:19:11', '2025-12-24 08:19:37'),
(15, 34, 'razorpay', 'order_RvVKs89cfQghWn', 'pay_RvVL0UBEwyW9ov', 10000, 'INR', 'captured', '2025-12-24 21:21:14', '2025-12-24 21:21:40'),
(16, 35, 'razorpay', 'order_RvVcL0JTBYXDiE', 'pay_RvVcQKjEVVPaAt', 10000, 'INR', 'captured', '2025-12-24 21:37:49', '2025-12-24 21:38:09'),
(17, 36, 'razorpay', 'order_RvW4Xlsbl3njXY', 'pay_RvW4x9yfeL4jtH', 10000, 'INR', 'captured', '2025-12-24 22:04:30', '2025-12-24 22:05:15'),
(18, 37, 'razorpay', 'order_RvpnUGVQ7DXLm7', 'pay_RvpndOR8Sy707b', 10000, 'INR', 'captured', '2025-12-25 17:22:12', '2025-12-25 17:22:38'),
(19, 38, 'razorpay', 'order_Rvu18QfoIQfzL4', 'pay_Rvu1J9wiqNsEMT', 10000, 'INR', 'captured', '2025-12-25 21:29:55', '2025-12-25 21:30:22'),
(20, 39, 'razorpay', 'order_RvuB8utb2pO7KL', 'pay_RvuBZrCch3pRcU', 10000, 'INR', 'captured', '2025-12-25 21:39:21', '2025-12-25 21:40:11'),
(21, 40, 'razorpay', 'order_RwDFfFarNNkj89', 'pay_RwDFmbzon24yDD', 10000, 'INR', 'captured', '2025-12-26 16:18:50', '2025-12-26 16:19:15'),
(22, 41, 'razorpay', 'order_RwDLjNw4V3uDDC', 'pay_RwDLutYlXAJpQu', 10000, 'INR', 'captured', '2025-12-26 16:24:36', '2025-12-26 16:25:04'),
(23, 42, 'razorpay', NULL, NULL, 10000, 'INR', 'pending', '2025-12-26 16:44:17', NULL),
(24, 42, 'razorpay', 'order_RwDhh9v0CVHLyB', 'pay_RwDhysCLoYB4QW', 10000, 'INR', 'captured', '2025-12-26 16:45:23', '2025-12-26 16:45:57'),
(25, 43, 'razorpay', 'order_RwDiktv1E065Ag', 'pay_RwDipLkHRpiHTT', 10000, 'INR', 'captured', '2025-12-26 16:46:24', '2025-12-26 16:46:45'),
(26, 44, 'razorpay', 'order_Ry9MKxvnwvUzXe', 'pay_Ry9Mj7rrw9bWOd', 10000, 'INR', 'captured', '2025-12-31 13:48:24', '2025-12-31 13:49:09'),
(27, 45, 'razorpay', 'order_Ry9R8tkBnlTGx3', 'pay_Ry9RPRoO2REMoo', 10000, 'INR', 'captured', '2025-12-31 13:52:59', '2025-12-31 13:53:31'),
(28, 46, 'razorpay', 'order_RyA4O2DuNd0ORf', 'pay_RyA4T826q1KxnT', 10000, 'INR', 'captured', '2025-12-31 14:30:08', '2025-12-31 14:30:31'),
(29, 47, 'razorpay', 'order_RyA8flF26gBAY2', 'pay_RyA8rE62QwCpx9', 10000, 'INR', 'captured', '2025-12-31 14:34:13', '2025-12-31 14:34:40'),
(30, 48, 'razorpay', 'order_RyDwhoXsvs7d5G', 'pay_RyDwoyFM2C3pwa', 10000, 'INR', 'captured', '2025-12-31 18:17:39', '2025-12-31 18:18:02');

-- --------------------------------------------------------

--
-- Table structure for table `payment_events`
--

CREATE TABLE `payment_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `payment_id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `event` varchar(50) NOT NULL,
  `provider_payment_id` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_events`
--

INSERT INTO `payment_events` (`id`, `payment_id`, `booking_id`, `event`, `provider_payment_id`, `created_at`) VALUES
(1, 3, 26, 'captured', 'pay_Rv0kzJDLZgkjiQ', '2025-12-23 15:26:46'),
(2, 4, 27, 'captured', 'pay_Rv0vq2k8RFtAY5', '2025-12-23 15:37:02'),
(3, 7, 29, 'captured', 'pay_Rv1CBv6TssH8b0', '2025-12-23 15:52:30'),
(4, 12, 32, 'captured', 'pay_Rv1rdqubPzjplz', '2025-12-23 16:31:45'),
(5, 14, 33, 'captured', 'pay_RvI0tEnUVdjHQa', '2025-12-24 08:19:37'),
(6, 15, 34, 'captured', 'pay_RvVL0UBEwyW9ov', '2025-12-24 21:21:40'),
(7, 16, 35, 'captured', 'pay_RvVcQKjEVVPaAt', '2025-12-24 21:38:09'),
(8, 17, 36, 'captured', 'pay_RvW4x9yfeL4jtH', '2025-12-24 22:05:15'),
(9, 18, 37, 'captured', 'pay_RvpndOR8Sy707b', '2025-12-25 17:22:38'),
(10, 19, 38, 'captured', 'pay_Rvu1J9wiqNsEMT', '2025-12-25 21:30:22'),
(11, 20, 39, 'captured', 'pay_RvuBZrCch3pRcU', '2025-12-25 21:40:11'),
(12, 21, 40, 'captured', 'pay_RwDFmbzon24yDD', '2025-12-26 16:19:15'),
(13, 22, 41, 'captured', 'pay_RwDLutYlXAJpQu', '2025-12-26 16:25:04'),
(14, 24, 42, 'captured', 'pay_RwDhysCLoYB4QW', '2025-12-26 16:45:57'),
(15, 25, 43, 'captured', 'pay_RwDipLkHRpiHTT', '2025-12-26 16:46:45'),
(16, 26, 44, 'captured', 'pay_Ry9Mj7rrw9bWOd', '2025-12-31 13:49:09'),
(17, 27, 45, 'captured', 'pay_Ry9RPRoO2REMoo', '2025-12-31 13:53:31'),
(18, 28, 46, 'captured', 'pay_RyA4T826q1KxnT', '2025-12-31 14:30:31'),
(19, 29, 47, 'captured', 'pay_RyA8rE62QwCpx9', '2025-12-31 14:34:40'),
(20, 30, 48, 'captured', 'pay_RyDwoyFM2C3pwa', '2025-12-31 18:18:02');

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

CREATE TABLE `ratings` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `hotel_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(1) UNSIGNED NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `hidden_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ratings`
--

INSERT INTO `ratings` (`id`, `user_id`, `hotel_id`, `rating`, `review`, `created_at`, `hidden_at`, `updated_at`) VALUES
(4, 2, 14, 5, 'yes', '2025-12-24 21:36:55', NULL, NULL),
(5, 2, 15, 5, 'ok', '2025-12-25 17:22:55', NULL, NULL),
(6, 2, 16, 5, 'ijk,', '2025-12-31 14:33:18', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(190) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `city` varchar(120) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password_plain` varchar(255) NOT NULL,
  `otp` int(6) DEFAULT NULL,
  `otp_exp` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `status` varchar(10) DEFAULT NULL,
  `role` enum('user','restaurant_owner','admin') NOT NULL DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `first_name`, `last_name`, `city`, `phone`, `password_plain`, `otp`, `otp_exp`, `created_at`, `updated_at`, `deleted_at`, `status`, `role`) VALUES
(1, 'eatease0@gmail.com', 'Admin', 'User', 'Rajkot', '6351010234', 'Eatease@123321', NULL, NULL, '2025-01-01 00:00:00', NULL, NULL, 'verified', 'admin'),
(2, 'chintankatariya5261@gmail.com', 'CHINTAN', 'KATARIYA', 'Rajkot', '1212121212', 'Chintan@1212', NULL, NULL, '2025-12-21 13:36:10', '2025-12-25 21:31:24', NULL, 'verified', 'user'),
(3, 'a4233887@gmail.com', 'chintan', 'katariya', 'rajkot', '1111111111', 'Abc@1212', NULL, NULL, '2025-12-21 13:52:17', '2025-12-21 13:52:33', NULL, 'verified', 'restaurant_owner'),
(7, 'chintankatariya707@gmail.com', 'CHINTAN', 'KATARIYA', 'Rajkot', '6351010234', 'Chintan@123', NULL, NULL, '2025-12-25 12:07:22', '2025-12-25 12:07:40', NULL, 'verified', 'restaurant_owner'),
(8, 'bhattkashyap09@gmail.com', 'Kashyap', 'Bhatt', 'rajkot', '3142423431', 'Bhatt@123', NULL, NULL, '2025-12-25 21:21:15', '2025-12-25 21:21:33', NULL, 'verified', 'restaurant_owner'),
(10, 'c.k.patel5261@gmail.com', 'CK', 'Patel', 'rajkot', '6351010234', 'Chintan@1212', NULL, NULL, '2025-12-25 21:36:07', '2026-01-02 18:24:32', NULL, 'verified', 'user'),
(11, 'jash49491@gmail.com', 'jash', 'jash', 'rajkot', '5786546543', 'Jash@4949', NULL, NULL, '2025-12-31 14:37:47', '2026-01-02 18:24:22', NULL, 'verified', 'restaurant_owner');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `hotel_id` (`hotel_id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`user_id`,`hotel_id`),
  ADD KEY `hotel_id` (`hotel_id`);

--
-- Indexes for table `hotels`
--
ALTER TABLE `hotels`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_owner_id` (`owner_id`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hotel_id` (`hotel_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_provider_order_id` (`provider_order_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `payment_events`
--
ALTER TABLE `payment_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_id` (`payment_id`),
  ADD KEY `idx_booking_id` (`booking_id`);

--
-- Indexes for table `ratings`
--
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_hotel_rating` (`user_id`,`hotel_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `hotel_id` (`hotel_id`),
  ADD KEY `idx_hidden_at` (`hidden_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_users_email` (`email`),
  ADD KEY `idx_users_deleted_at` (`deleted_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `hotels`
--
ALTER TABLE `hotels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `payment_events`
--
ALTER TABLE `payment_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hotels`
--
ALTER TABLE `hotels`
  ADD CONSTRAINT `fk_hotel_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD CONSTRAINT `menu_items_ibfk_1` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ratings`
--
ALTER TABLE `ratings`
  ADD CONSTRAINT `ratings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ratings_ibfk_2` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
