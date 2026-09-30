-- ============================================
-- SK Travel Planner - Complete Database Schema
-- With Multiple Images per Itinerary
-- ============================================

CREATE DATABASE IF NOT EXISTS sk_travel_planner
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sk_travel_planner;


-- ============================================
-- 1. ADMIN USERS TABLE
-- ============================================

CREATE TABLE IF NOT EXISTS admin_users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- ============================================
-- 2. ITINERARIES TABLE
-- ============================================

CREATE TABLE IF NOT EXISTS itineraries (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(255) NOT NULL,
    destination   VARCHAR(255) NOT NULL,
    description   TEXT,
    price         DECIMAL(12,2) DEFAULT 0.00,
    duration_days INT DEFAULT 1,
    image         VARCHAR(255) DEFAULT NULL,
    highlights    TEXT,
    inclusions    TEXT,
    exclusions    TEXT,
    day_plan      TEXT,
    status        TINYINT(1) DEFAULT 1,

    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB;


-- ============================================
-- 3. MULTIPLE IMAGES TABLE
-- ============================================

CREATE TABLE IF NOT EXISTS itinerary_images (
    id            INT AUTO_INCREMENT PRIMARY KEY,

    itinerary_id INT NOT NULL,

    image         VARCHAR(255) NOT NULL,

    sort_order    INT DEFAULT 0,

    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_itinerary_id (itinerary_id),

    CONSTRAINT fk_itinerary_images_itinerary
        FOREIGN KEY (itinerary_id)
        REFERENCES itineraries(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB;


-- ============================================
-- 4. DEFAULT ADMIN USER
-- ============================================
-- Username: admin
-- Password: admin123
--
-- The INSERT IGNORE prevents an error if
-- the admin already exists on the live server.
-- ============================================

INSERT IGNORE INTO admin_users
(
    username,
    password
)
VALUES
(
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEaGVRoUbLZQK5aBjK9jiBZKJg6m'
);


-- ============================================
-- 5. SAMPLE ITINERARIES
-- ============================================

INSERT INTO itineraries
(
    title,
    destination,
    description,
    price,
    duration_days,
    highlights,
    inclusions,
    exclusions,
    day_plan,
    status
)
SELECT
    'Bali Paradise Escape',
    'Bali, Indonesia',
    'Experience the magic of Bali with pristine beaches, ancient temples, and lush rice terraces. This carefully curated itinerary takes you through the best of Bali, from the cultural hub of Ubud to the stunning coastline of Seminyak.',
    45000,
    7,

    '[
        "Visit Tegallalang Rice Terraces",
        "Explore Uluwatu Temple at sunset",
        "Snorkel at Nusa Penida",
        "Traditional Balinese spa experience",
        "Mount Batur sunrise trek"
    ]',

    '[
        "Airport transfers",
        "4-star accommodation",
        "Daily breakfast & 2 lunches",
        "English-speaking guide",
        "All entrance fees",
        "AC vehicle throughout"
    ]',

    '[
        "International flights",
        "Travel insurance",
        "Personal expenses",
        "Optional activities"
    ]',

    '[
        {
            "day":1,
            "title":"Arrival & Seminyak",
            "desc":"Airport pickup, check-in, beach walk at Seminyak Beach, sunset cocktails",
            "meals":"Dinner",
            "hotel":"4-star Seminyak Resort"
        },
        {
            "day":2,
            "title":"Ubud Cultural Tour",
            "desc":"Tegallalang Rice Terraces, Monkey Forest, Ubud Art Market, traditional dance show",
            "meals":"Breakfast, Lunch",
            "hotel":"4-star Seminyak Resort"
        },
        {
            "day":3,
            "title":"Nusa Penida Day Trip",
            "desc":"Speedboat to Nusa Penida, Kelingking Beach, Angel Billabong, Broken Beach, snorkeling with manta rays",
            "meals":"Breakfast, Lunch",
            "hotel":"4-star Seminyak Resort"
        },
        {
            "day":4,
            "title":"Waterfalls & Coffee",
            "desc":"Tegenungan Waterfall, Bali Pulina coffee plantation, Tirta Empul water temple",
            "meals":"Breakfast",
            "hotel":"4-star Ubud Villa"
        },
        {
            "day":5,
            "title":"Mount Batur Sunrise",
            "desc":"2 AM departure for sunrise trek, breakfast at summit, hot springs at Toya Bungkah",
            "meals":"Breakfast, Lunch",
            "hotel":"4-star Ubud Villa"
        },
        {
            "day":6,
            "title":"Uluwatu & Beach Club",
            "desc":"Padang Padang Beach, Suluban Beach, Uluwatu Temple kecak dance, beach club sunset",
            "meals":"Breakfast, Dinner",
            "hotel":"4-star Seminyak Resort"
        },
        {
            "day":7,
            "title":"Departure",
            "desc":"Morning spa session, last-minute shopping, airport transfer",
            "meals":"Breakfast",
            "hotel":"N/A"
        }
    ]',

    1

WHERE NOT EXISTS
(
    SELECT 1
    FROM itineraries
    WHERE title = 'Bali Paradise Escape'
);


-- ============================================
-- TOKYO
-- ============================================

INSERT INTO itineraries
(
    title,
    destination,
    description,
    price,
    duration_days,
    highlights,
    inclusions,
    exclusions,
    day_plan,
    status
)
SELECT
    'Tokyo Adventure Tour',
    'Tokyo, Japan',
    'Dive into the electrifying city of Tokyo where ancient traditions meet cutting-edge technology. From serene shrines to neon-lit streets, this tour covers every facet of Japan vibrant capital.',
    62000,
    5,

    '[
        "Walk through Shibuya Crossing",
        "Visit Meiji Shrine",
        "Explore Akihabara district",
        "Day trip to Mount Fuji",
        "Authentic sushi-making class"
    ]',

    '[
        "Airport transfers",
        "3-star hotel accommodation",
        "Daily breakfast",
        "JR Pass (5 days)",
        "Guided tours as per itinerary",
        "Entrance fees"
    ]',

    '[
        "International flights",
        "Travel insurance",
        "Meals not mentioned",
        "Personal shopping"
    ]',

    '[
        {
            "day":1,
            "title":"Welcome to Tokyo",
            "desc":"Narita pickup, check-in Shinjuku, evening walk through Golden Gai",
            "meals":"Dinner",
            "hotel":"Shinjuku Business Hotel"
        },
        {
            "day":2,
            "title":"Classic Tokyo",
            "desc":"Meiji Shrine, Harajuku Takeshita Street, Shibuya Crossing, Yoyogi Park, evening at Roppongi Hills",
            "meals":"Breakfast",
            "hotel":"Shinjuku Business Hotel"
        },
        {
            "day":3,
            "title":"Culture & Tradition",
            "desc":"Senso-ji Temple Asakusa, Nakamise shopping street, Ueno Park museums, Akihabara evening",
            "meals":"Breakfast, Lunch",
            "hotel":"Shinjuku Business Hotel"
        },
        {
            "day":4,
            "title":"Mount Fuji Day Trip",
            "desc":"Hakone ropeway, Lake Ashi cruise, Owakudani volcanic valley, Fuji Five Lakes",
            "meals":"Breakfast, Lunch",
            "hotel":"Shinjuku Business Hotel"
        },
        {
            "day":5,
            "title":"Tsukiji & Departure",
            "desc":"Tsukiji outer market breakfast, sushi-making class, Ginza walk, airport transfer",
            "meals":"Breakfast, Lunch",
            "hotel":"N/A"
        }
    ]',

    1

WHERE NOT EXISTS
(
    SELECT 1
    FROM itineraries
    WHERE title = 'Tokyo Adventure Tour'
);


-- ============================================
-- KERALA
-- ============================================

INSERT INTO itineraries
(
    title,
    destination,
    description,
    price,
    duration_days,
    highlights,
    inclusions,
    exclusions,
    day_plan,
    status
)
SELECT
    'Kerala Backwater Cruise',
    'Kerala, India',
    'Discover God Own Country through tranquil backwaters, spice plantations, and pristine hill stations. This tour showcases the best of Kerala natural beauty and rich cultural heritage.',
    28000,
    6,

    '[
        "Houseboat stay in Alleppey",
        "Munnar tea plantation visit",
        "Kathakali dance performance",
        "Periyar wildlife sanctuary",
        "Kovalam beach relaxation"
    ]',

    '[
        "Airport transfers",
        "3-star accommodation",
        "Daily breakfast & 3 dinners",
        "Houseboat (1 night)",
        "AC vehicle",
        "Guide for sightseeing"
    ]',

    '[
        "Flights",
        "Travel insurance",
        "Personal expenses",
        "Meals not mentioned"
    ]',

    '[
        {
            "day":1,
            "title":"Arrival Cochin",
            "desc":"Airport pickup, Fort Kochi walk, Chinese fishing nets, evening Kathakali show",
            "meals":"Dinner",
            "hotel":"Fort Kochi Heritage Hotel"
        },
        {
            "day":2,
            "title":"Munnar Hills",
            "desc":"Drive to Munnar, tea museum, Eravikulam National Park, Attukad waterfalls",
            "meals":"Breakfast, Dinner",
            "hotel":"Munnar Hill Resort"
        },
        {
            "day":3,
            "title":"Munnar Exploration",
            "desc":"Tea plantation walk, Mattupetty Dam, Echo Point, Kundala Lake boating",
            "meals":"Breakfast, Dinner",
            "hotel":"Munnar Hill Resort"
        },
        {
            "day":4,
            "title":"Thekkady Wildlife",
            "desc":"Drive to Thekkady, Periyar boat safari, spice plantation tour, elephant ride",
            "meals":"Breakfast, Dinner",
            "hotel":"Thekkady Jungle Lodge"
        },
        {
            "day":5,
            "title":"Alleppey Houseboat",
            "desc":"Drive to Alleppey, board traditional houseboat, cruise backwaters, village visits",
            "meals":"Breakfast, Lunch, Dinner",
            "hotel":"Deluxe Houseboat"
        },
        {
            "day":6,
            "title":"Kovalam & Departure",
            "desc":"Drive to Kovalam, beach time, Trivandrum Padmanabhaswamy Temple, airport drop",
            "meals":"Breakfast",
            "hotel":"N/A"
        }
    ]',

    1

WHERE NOT EXISTS
(
    SELECT 1
    FROM itineraries
    WHERE title = 'Kerala Backwater Cruise'
);


-- ============================================
-- 6. SAMPLE MULTIPLE IMAGES
-- ============================================
-- These are linked to itinerary IDs 1, 2 and 3.
--
-- If the sample itineraries already existed,
-- these records will only be added if they
-- don't already exist.
-- ============================================


-- --------------------------------------------
-- BALI IMAGES
-- --------------------------------------------

INSERT INTO itinerary_images
(
    itinerary_id,
    image,
    sort_order
)
SELECT 1, 'uploads/itineraries/bali-1.jpg', 1
WHERE EXISTS (SELECT 1 FROM itineraries WHERE id = 1)
AND NOT EXISTS (
    SELECT 1
    FROM itinerary_images
    WHERE itinerary_id = 1
    AND image = 'uploads/itineraries/bali-1.jpg'
);

INSERT INTO itinerary_images
(
    itinerary_id,
    image,
    sort_order
)
SELECT 1, 'uploads/itineraries/bali-2.jpg', 2
WHERE EXISTS (SELECT 1 FROM itineraries WHERE id = 1)
AND NOT EXISTS (
    SELECT 1
    FROM itinerary_images
    WHERE itinerary_id = 1
    AND image = 'uploads/itineraries/bali-2.jpg'
);

INSERT INTO itinerary_images
(
    itinerary_id,
    image,
    sort_order
)
SELECT 1, 'uploads/itineraries/bali-3.jpg', 3
WHERE EXISTS (SELECT 1 FROM itineraries WHERE id = 1)
AND NOT EXISTS (
    SELECT 1
    FROM itinerary_images
    WHERE itinerary_id = 1
    AND image = 'uploads/itineraries/bali-3.jpg'
);

INSERT INTO itinerary_images
(
    itinerary_id,
    image,
    sort_order
)
SELECT 1, 'uploads/itineraries/bali-4.jpg', 4
WHERE EXISTS (SELECT 1 FROM itineraries WHERE id = 1)
AND NOT EXISTS (
    SELECT 1
    FROM itinerary_images
    WHERE itinerary_id = 1
    AND image = 'uploads/itineraries/bali-4.jpg'
);


-- --------------------------------------------
-- TOKYO IMAGES
-- --------------------------------------------

INSERT INTO itinerary_images
(
    itinerary_id,
    image,
    sort_order
)
SELECT 2, 'uploads/itineraries/tokyo-1.jpg', 1
WHERE EXISTS (SELECT 1 FROM itineraries WHERE id = 2)
AND NOT EXISTS (
    SELECT 1
    FROM itinerary_images
    WHERE itinerary_id = 2
    AND image = 'uploads/itineraries/tokyo-1.jpg'
);

INSERT INTO itinerary_images
(
    itinerary_id,
    image,
    sort_order
)
SELECT 2, 'uploads/itineraries/tokyo-2.jpg', 2
WHERE EXISTS (SELECT 1 FROM itineraries WHERE id = 2)
AND NOT EXISTS (
    SELECT 1
    FROM itinerary_images
    WHERE itinerary_id = 2
    AND image = 'uploads/itineraries/tokyo-2.jpg'
);

INSERT INTO itinerary_images
(
    itinerary_id,
    image,
    sort_order
)
SELECT 2, 'uploads/itineraries/tokyo-3.jpg', 3
WHERE EXISTS (SELECT 1 FROM itineraries WHERE id = 2)
AND NOT EXISTS (
    SELECT 1
    FROM itinerary_images
    WHERE itinerary_id = 2
    AND image = 'uploads/itineraries/tokyo-3.jpg'
);

INSERT INTO itinerary_images
(
    itinerary_id,
    image,
    sort_order
)
SELECT 2, 'uploads/itineraries/tokyo-4.jpg', 4
WHERE EXISTS (SELECT 1 FROM itineraries WHERE id = 2)
AND NOT EXISTS (
    SELECT 1
    FROM itinerary_images
    WHERE itinerary_id = 2
    AND image = 'uploads/itineraries/tokyo-4.jpg'
);


-- --------------------------------------------
-- KERALA IMAGES
-- --------------------------------------------

INSERT INTO itinerary_images
(
    itinerary_id,
    image,
    sort_order
)
SELECT 3, 'uploads/itineraries/kerala-1.jpg', 1
WHERE EXISTS (SELECT 1 FROM itineraries WHERE id = 3)
AND NOT EXISTS (
    SELECT 1
    FROM itinerary_images
    WHERE itinerary_id = 3
    AND image = 'uploads/itineraries/kerala-1.jpg'
);

INSERT INTO itinerary_images
(
    itinerary_id,
    image,
    sort_order
)
SELECT 3, 'uploads/itineraries/kerala-2.jpg', 2
WHERE EXISTS (SELECT 1 FROM itineraries WHERE id = 3)
AND NOT EXISTS (
    SELECT 1
    FROM itinerary_images
    WHERE itinerary_id = 3
    AND image = 'uploads/itineraries/kerala-2.jpg'
);

INSERT INTO itinerary_images
(
    itinerary_id,
    image,
    sort_order
)
SELECT 3, 'uploads/itineraries/kerala-3.jpg', 3
WHERE EXISTS (SELECT 1 FROM itineraries WHERE id = 3)
AND NOT EXISTS (
    SELECT 1
    FROM itinerary_images
    WHERE itinerary_id = 3
    AND image = 'uploads/itineraries/kerala-3.jpg'
);

INSERT INTO itinerary_images
(
    itinerary_id,
    image,
    sort_order
)
SELECT 3, 'uploads/itineraries/kerala-4.jpg', 4
WHERE EXISTS (SELECT 1 FROM itineraries WHERE id = 3)
AND NOT EXISTS (
    SELECT 1
    FROM itinerary_images
    WHERE itinerary_id = 3
    AND image = 'uploads/itineraries/kerala-4.jpg'
);


-- ============================================
-- 7. VERIFY DATABASE
-- ============================================

SELECT
    id,
    title,
    destination,
    price,
    duration_days,
    status
FROM itineraries
ORDER BY id;


-- ============================================
-- 8. VERIFY MULTIPLE IMAGES
-- ============================================

SELECT
    ii.id,
    ii.itinerary_id,
    i.title AS itinerary_title,
    ii.image,
    ii.sort_order
FROM itinerary_images ii
INNER JOIN itineraries i
    ON i.id = ii.itinerary_id
ORDER BY
    ii.itinerary_id,
    ii.sort_order;


-- ============================================
-- 9. USER REVIEWS TABLE & SAMPLES
-- ============================================

CREATE TABLE IF NOT EXISTS user_reviews (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_name     VARCHAR(100) NOT NULL,
    user_email    VARCHAR(150) DEFAULT NULL,
    user_location VARCHAR(100) DEFAULT NULL,
    rating        TINYINT UNSIGNED NOT NULL DEFAULT 5,
    review_title  VARCHAR(255) DEFAULT NULL,
    review_text   TEXT NOT NULL,
    status        ENUM('pending', 'approved', 'declined') NOT NULL DEFAULT 'pending',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_rating (rating),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO user_reviews 
    (id, user_name, user_email, user_location, rating, review_title, review_text, status, created_at)
VALUES
    (1, 'Rahul Sengupta', 'rahul.s@example.com', 'Kolkata, WB (Kashmir Tour)', 5, 'Seamless organization & magical trip!', 'Our 7-day family tour to Kashmir was organized to perfection by SK Travel Planners. From airport pickup in Srinagar to the serene houseboat stay and Gulmarg gondola tickets, everything was completely stress-free. Exceptional service and very polite chauffeur throughout!', 'approved', NOW() - INTERVAL 12 DAY),
    (2, 'Priya & Vikram Malhotra', 'vikram.m@example.com', 'New Delhi (Bali Paradise)', 5, 'Unforgettable honeymoon in Bali!', 'SK Travel Planners crafted our dream honeymoon itinerary in Bali. The private pool villa in Seminyak, the sunrise trek at Mount Batur, and the Nusa Penida island speedboat tour were breathtaking. 24/7 WhatsApp assistance gave us huge peace of mind!', 'approved', NOW() - INTERVAL 6 DAY),
    (3, 'Ananya Sharma', 'ananya.sh@example.com', 'Bengaluru (Darjeeling & Sikkim)', 5, 'Spectacular mountain views & top hospitality', 'Booked the Darjeeling and Gangtok adventure for our college reunion group. The hotel selections had mesmerizing views of Mount Kanchenjunga, and our local guide was incredibly knowledgeable. Best travel planner we have worked with!', 'approved', NOW() - INTERVAL 2 DAY),
    (4, 'Devendra Joshi', 'd.joshi@example.com', 'Ahmedabad (Kerala Cruise)', 4, 'Wonderful houseboat experience in Alleppey', 'Very good planning and authentic South Indian cuisine on the houseboat. The driver was punctual and courteous. A memorable holiday with family. Would love to book again next winter!', 'pending', NOW() - INTERVAL 1 HOUR)
ON DUPLICATE KEY UPDATE id=id;