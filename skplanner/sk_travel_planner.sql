-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 29, 2026 at 02:18 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.1.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sk_travel_planner`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`, `created_at`) VALUES
(3, 'admin', '$2y$12$4nsBcwhVN1K1ob5BY23bgeAypjLL.MAlEYesOVIgo3bD1eMu7E32O', '2026-09-22 18:26:04');

-- --------------------------------------------------------

--
-- Table structure for table `itineraries`
--

CREATE TABLE `itineraries` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `destination` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(12,2) DEFAULT 0.00,
  `duration_days` int(11) DEFAULT 1,
  `image` varchar(255) DEFAULT NULL,
  `highlights` text DEFAULT NULL,
  `inclusions` text DEFAULT NULL,
  `exclusions` text DEFAULT NULL,
  `day_plan` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `itineraries`
--

INSERT INTO `itineraries` (`id`, `title`, `destination`, `description`, `price`, `duration_days`, `image`, `highlights`, `inclusions`, `exclusions`, `day_plan`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Bali Paradise Escape', 'Bali, Indonesia', 'Experience the magic of Bali with pristine beaches, ancient temples, and lush rice terraces. This carefully curated itinerary takes you through the best of Bali, from the cultural hub of Ubud to the stunning coastline of Seminyak.', 45000.00, 7, NULL, '[\r\n        \"Visit Tegallalang Rice Terraces\",\r\n        \"Explore Uluwatu Temple at sunset\",\r\n        \"Snorkel at Nusa Penida\",\r\n        \"Traditional Balinese spa experience\",\r\n        \"Mount Batur sunrise trek\"\r\n    ]', '[\r\n        \"Airport transfers\",\r\n        \"4-star accommodation\",\r\n        \"Daily breakfast & 2 lunches\",\r\n        \"English-speaking guide\",\r\n        \"All entrance fees\",\r\n        \"AC vehicle throughout\"\r\n    ]', '[\r\n        \"International flights\",\r\n        \"Travel insurance\",\r\n        \"Personal expenses\",\r\n        \"Optional activities\"\r\n    ]', '[\r\n        {\r\n            \"day\":1,\r\n            \"title\":\"Arrival & Seminyak\",\r\n            \"desc\":\"Airport pickup, check-in, beach walk at Seminyak Beach, sunset cocktails\",\r\n            \"meals\":\"Dinner\",\r\n            \"hotel\":\"4-star Seminyak Resort\"\r\n        },\r\n        {\r\n            \"day\":2,\r\n            \"title\":\"Ubud Cultural Tour\",\r\n            \"desc\":\"Tegallalang Rice Terraces, Monkey Forest, Ubud Art Market, traditional dance show\",\r\n            \"meals\":\"Breakfast, Lunch\",\r\n            \"hotel\":\"4-star Seminyak Resort\"\r\n        },\r\n        {\r\n            \"day\":3,\r\n            \"title\":\"Nusa Penida Day Trip\",\r\n            \"desc\":\"Speedboat to Nusa Penida, Kelingking Beach, Angel Billabong, Broken Beach, snorkeling with manta rays\",\r\n            \"meals\":\"Breakfast, Lunch\",\r\n            \"hotel\":\"4-star Seminyak Resort\"\r\n        },\r\n        {\r\n            \"day\":4,\r\n            \"title\":\"Waterfalls & Coffee\",\r\n            \"desc\":\"Tegenungan Waterfall, Bali Pulina coffee plantation, Tirta Empul water temple\",\r\n            \"meals\":\"Breakfast\",\r\n            \"hotel\":\"4-star Ubud Villa\"\r\n        },\r\n        {\r\n            \"day\":5,\r\n            \"title\":\"Mount Batur Sunrise\",\r\n            \"desc\":\"2 AM departure for sunrise trek, breakfast at summit, hot springs at Toya Bungkah\",\r\n            \"meals\":\"Breakfast, Lunch\",\r\n            \"hotel\":\"4-star Ubud Villa\"\r\n        },\r\n        {\r\n            \"day\":6,\r\n            \"title\":\"Uluwatu & Beach Club\",\r\n            \"desc\":\"Padang Padang Beach, Suluban Beach, Uluwatu Temple kecak dance, beach club sunset\",\r\n            \"meals\":\"Breakfast, Dinner\",\r\n            \"hotel\":\"4-star Seminyak Resort\"\r\n        },\r\n        {\r\n            \"day\":7,\r\n            \"title\":\"Departure\",\r\n            \"desc\":\"Morning spa session, last-minute shopping, airport transfer\",\r\n            \"meals\":\"Breakfast\",\r\n            \"hotel\":\"N/A\"\r\n        }\r\n    ]', 1, '2026-09-22 17:08:12', '2026-09-23 17:29:33'),
(2, 'Tokyo Adventure Tour', 'Tokyo, Japan', 'Dive into the electrifying city of Tokyo where ancient traditions meet cutting-edge technology. From serene shrines to neon-lit streets, this tour covers every facet of Japan vibrant capital.', 62000.00, 5, NULL, '[\r\n        \"Walk through Shibuya Crossing\",\r\n        \"Visit Meiji Shrine\",\r\n        \"Explore Akihabara district\",\r\n        \"Day trip to Mount Fuji\",\r\n        \"Authentic sushi-making class\"\r\n    ]', '[\r\n        \"Airport transfers\",\r\n        \"3-star hotel accommodation\",\r\n        \"Daily breakfast\",\r\n        \"JR Pass (5 days)\",\r\n        \"Guided tours as per itinerary\",\r\n        \"Entrance fees\"\r\n    ]', '[\r\n        \"International flights\",\r\n        \"Travel insurance\",\r\n        \"Meals not mentioned\",\r\n        \"Personal shopping\"\r\n    ]', '[\r\n        {\r\n            \"day\":1,\r\n            \"title\":\"Welcome to Tokyo\",\r\n            \"desc\":\"Narita pickup, check-in Shinjuku, evening walk through Golden Gai\",\r\n            \"meals\":\"Dinner\",\r\n            \"hotel\":\"Shinjuku Business Hotel\"\r\n        },\r\n        {\r\n            \"day\":2,\r\n            \"title\":\"Classic Tokyo\",\r\n            \"desc\":\"Meiji Shrine, Harajuku Takeshita Street, Shibuya Crossing, Yoyogi Park, evening at Roppongi Hills\",\r\n            \"meals\":\"Breakfast\",\r\n            \"hotel\":\"Shinjuku Business Hotel\"\r\n        },\r\n        {\r\n            \"day\":3,\r\n            \"title\":\"Culture & Tradition\",\r\n            \"desc\":\"Senso-ji Temple Asakusa, Nakamise shopping street, Ueno Park museums, Akihabara evening\",\r\n            \"meals\":\"Breakfast, Lunch\",\r\n            \"hotel\":\"Shinjuku Business Hotel\"\r\n        },\r\n        {\r\n            \"day\":4,\r\n            \"title\":\"Mount Fuji Day Trip\",\r\n            \"desc\":\"Hakone ropeway, Lake Ashi cruise, Owakudani volcanic valley, Fuji Five Lakes\",\r\n            \"meals\":\"Breakfast, Lunch\",\r\n            \"hotel\":\"Shinjuku Business Hotel\"\r\n        },\r\n        {\r\n            \"day\":5,\r\n            \"title\":\"Tsukiji & Departure\",\r\n            \"desc\":\"Tsukiji outer market breakfast, sushi-making class, Ginza walk, airport transfer\",\r\n            \"meals\":\"Breakfast, Lunch\",\r\n            \"hotel\":\"N/A\"\r\n        }\r\n    ]', 1, '2026-09-22 17:08:12', '2026-09-22 17:08:12'),
(3, 'Kerala Backwater Cruise', 'Kerala, India', 'Discover God Own Country through tranquil backwaters, spice plantations, and pristine hill stations. This tour showcases the best of Kerala natural beauty and rich cultural heritage.', 28000.00, 6, NULL, '[\r\n        \"Houseboat stay in Alleppey\",\r\n        \"Munnar tea plantation visit\",\r\n        \"Kathakali dance performance\",\r\n        \"Periyar wildlife sanctuary\",\r\n        \"Kovalam beach relaxation\"\r\n    ]', '[\r\n        \"Airport transfers\",\r\n        \"3-star accommodation\",\r\n        \"Daily breakfast & 3 dinners\",\r\n        \"Houseboat (1 night)\",\r\n        \"AC vehicle\",\r\n        \"Guide for sightseeing\"\r\n    ]', '[\r\n        \"Flights\",\r\n        \"Travel insurance\",\r\n        \"Personal expenses\",\r\n        \"Meals not mentioned\"\r\n    ]', '[\r\n        {\r\n            \"day\":1,\r\n            \"title\":\"Arrival Cochin\",\r\n            \"desc\":\"Airport pickup, Fort Kochi walk, Chinese fishing nets, evening Kathakali show\",\r\n            \"meals\":\"Dinner\",\r\n            \"hotel\":\"Fort Kochi Heritage Hotel\"\r\n        },\r\n        {\r\n            \"day\":2,\r\n            \"title\":\"Munnar Hills\",\r\n            \"desc\":\"Drive to Munnar, tea museum, Eravikulam National Park, Attukad waterfalls\",\r\n            \"meals\":\"Breakfast, Dinner\",\r\n            \"hotel\":\"Munnar Hill Resort\"\r\n        },\r\n        {\r\n            \"day\":3,\r\n            \"title\":\"Munnar Exploration\",\r\n            \"desc\":\"Tea plantation walk, Mattupetty Dam, Echo Point, Kundala Lake boating\",\r\n            \"meals\":\"Breakfast, Dinner\",\r\n            \"hotel\":\"Munnar Hill Resort\"\r\n        },\r\n        {\r\n            \"day\":4,\r\n            \"title\":\"Thekkady Wildlife\",\r\n            \"desc\":\"Drive to Thekkady, Periyar boat safari, spice plantation tour, elephant ride\",\r\n            \"meals\":\"Breakfast, Dinner\",\r\n            \"hotel\":\"Thekkady Jungle Lodge\"\r\n        },\r\n        {\r\n            \"day\":5,\r\n            \"title\":\"Alleppey Houseboat\",\r\n            \"desc\":\"Drive to Alleppey, board traditional houseboat, cruise backwaters, village visits\",\r\n            \"meals\":\"Breakfast, Lunch, Dinner\",\r\n            \"hotel\":\"Deluxe Houseboat\"\r\n        },\r\n        {\r\n            \"day\":6,\r\n            \"title\":\"Kovalam & Departure\",\r\n            \"desc\":\"Drive to Kovalam, beach time, Trivandrum Padmanabhaswamy Temple, airport drop\",\r\n            \"meals\":\"Breakfast\",\r\n            \"hotel\":\"N/A\"\r\n        }\r\n    ]', 1, '2026-09-22 17:08:12', '2026-09-22 17:08:12');

-- --------------------------------------------------------

--
-- Table structure for table `itinerary_images`
--

CREATE TABLE `itinerary_images` (
  `id` int(11) NOT NULL,
  `itinerary_id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `itinerary_images`
--

INSERT INTO `itinerary_images` (`id`, `itinerary_id`, `image`, `sort_order`, `created_at`) VALUES
(1, 1, 'uploads/itineraries/bali-1.jpg', 1, '2026-09-22 17:08:12'),
(2, 1, 'uploads/itineraries/bali-2.jpg', 2, '2026-09-22 17:08:12'),
(3, 1, 'uploads/itineraries/bali-3.jpg', 3, '2026-09-22 17:08:12'),
(4, 1, 'uploads/itineraries/bali-4.jpg', 4, '2026-09-22 17:08:12'),
(5, 2, 'uploads/itineraries/tokyo-1.jpg', 1, '2026-09-22 17:08:12'),
(6, 2, 'uploads/itineraries/tokyo-2.jpg', 2, '2026-09-22 17:08:12'),
(7, 2, 'uploads/itineraries/tokyo-3.jpg', 3, '2026-09-22 17:08:12'),
(8, 2, 'uploads/itineraries/tokyo-4.jpg', 4, '2026-09-22 17:08:12'),
(9, 3, 'uploads/itineraries/kerala-1.jpg', 1, '2026-09-22 17:08:12'),
(10, 3, 'uploads/itineraries/kerala-2.jpg', 2, '2026-09-22 17:08:12'),
(11, 3, 'uploads/itineraries/kerala-3.jpg', 3, '2026-09-22 17:08:12'),
(12, 3, 'uploads/itineraries/kerala-4.jpg', 4, '2026-09-22 17:08:12');

-- --------------------------------------------------------

--
-- Table structure for table `itinerary_reviews`
--

CREATE TABLE `itinerary_reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `itinerary_id` int(11) NOT NULL,
  `voter_hash` char(64) NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `itinerary_reviews`
--

INSERT INTO `itinerary_reviews` (`id`, `itinerary_id`, `voter_hash`, `rating`, `created_at`, `updated_at`) VALUES
(1, 1, '8528cbb54c867ff6b1c3f396b286c7835ea42cd50460f46e3d983dddaf33f506', 5, '2026-09-29 11:59:34', '2026-09-29 11:59:44'),
(2, 2, '8528cbb54c867ff6b1c3f396b286c7835ea42cd50460f46e3d983dddaf33f506', 3, '2026-09-29 12:00:26', '2026-09-29 12:00:26'),
(3, 3, '8528cbb54c867ff6b1c3f396b286c7835ea42cd50460f46e3d983dddaf33f506', 4, '2026-09-29 12:14:45', '2026-09-29 12:14:45');

--
-- Table structure for table `user_reviews`
--

CREATE TABLE `user_reviews` (
  `id` int(11) NOT NULL,
  `user_name` varchar(100) NOT NULL,
  `user_email` varchar(150) DEFAULT NULL,
  `user_location` varchar(100) DEFAULT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `review_title` varchar(255) DEFAULT NULL,
  `review_text` text NOT NULL,
  `status` enum('pending','approved','declined') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `review_images`
--

CREATE TABLE `review_images` (
  `id` int(11) NOT NULL,
  `review_id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_reviews`
--

INSERT INTO `user_reviews` (`id`, `user_name`, `user_email`, `user_location`, `rating`, `review_title`, `review_text`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Rahul Sengupta', 'rahul.s@example.com', 'Kolkata, WB (Kashmir Tour)', 5, 'Seamless organization & magical trip!', 'Our 7-day family tour to Kashmir was organized to perfection by SK Travel Planners. From airport pickup in Srinagar to the serene houseboat stay and Gulmarg gondola tickets, everything was completely stress-free. Exceptional service and very polite chauffeur throughout!', 'approved', '2026-09-18 10:00:00', '2026-09-18 10:00:00'),
(2, 'Priya & Vikram Malhotra', 'vikram.m@example.com', 'New Delhi (Bali Paradise)', 5, 'Unforgettable honeymoon in Bali!', 'SK Travel Planners crafted our dream honeymoon itinerary in Bali. The private pool villa in Seminyak, the sunrise trek at Mount Batur, and the Nusa Penida island speedboat tour were breathtaking. 24/7 WhatsApp assistance gave us huge peace of mind!', 'approved', '2026-09-24 14:30:00', '2026-09-24 14:30:00'),
(3, 'Ananya Sharma', 'ananya.sh@example.com', 'Bengaluru (Darjeeling & Sikkim)', 5, 'Spectacular mountain views & top hospitality', 'Booked the Darjeeling and Gangtok adventure for our college reunion group. The hotel selections had mesmerizing views of Mount Kanchenjunga, and our local guide was incredibly knowledgeable. Best travel planner we have worked with!', 'approved', '2026-09-28 16:15:00', '2026-09-28 16:15:00'),
(4, 'Devendra Joshi', 'd.joshi@example.com', 'Ahmedabad (Kerala Cruise)', 4, 'Wonderful houseboat experience in Alleppey', 'Very good planning and authentic South Indian cuisine on the houseboat. The driver was punctual and courteous. A memorable holiday with family. Would love to book again next winter!', 'pending', '2026-09-30 20:00:00', '2026-09-30 20:00:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `itineraries`
--
ALTER TABLE `itineraries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `itinerary_images`
--
ALTER TABLE `itinerary_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_itinerary_id` (`itinerary_id`);

--
-- Indexes for table `itinerary_reviews`
--
ALTER TABLE `itinerary_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vote` (`itinerary_id`,`voter_hash`),
  ADD KEY `idx_itinerary` (`itinerary_id`);

--
-- Indexes for table `user_reviews`
--
ALTER TABLE `user_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_rating` (`rating`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `review_images`
--
ALTER TABLE `review_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_review_id` (`review_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `itineraries`
--
ALTER TABLE `itineraries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `itinerary_images`
--
ALTER TABLE `itinerary_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `itinerary_reviews`
--
ALTER TABLE `itinerary_reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_reviews`
--
ALTER TABLE `user_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `review_images`
--
ALTER TABLE `review_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `itinerary_images`
--
ALTER TABLE `itinerary_images`
  ADD CONSTRAINT `fk_itinerary_images_itinerary` FOREIGN KEY (`itinerary_id`) REFERENCES `itineraries` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
