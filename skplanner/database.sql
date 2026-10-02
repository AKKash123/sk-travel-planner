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
-- 5. ITINERARIES (16 Verified Packages)
-- ============================================

TRUNCATE TABLE `itinerary_reviews`;
TRUNCATE TABLE `itinerary_images`;
TRUNCATE TABLE `itineraries`;

INSERT INTO `itineraries` (`id`, `title`, `destination`, `description`, `price`, `duration_days`, `image`, `highlights`, `inclusions`, `exclusions`, `day_plan`, `status`) VALUES
(1, "Pine Forests of Darjeeling", "Darjeeling", "Immerse yourself in the fragrant cedar and pine forests of Darjeeling. Wander through misty forest trails, colonial pathways, peaceful tea garden vantage points, and enjoy authentic mountain hospitality in the queen of hills.", 14500, 4, "https://commons.wikimedia.org/wiki/Special:FilePath/Pine_trees_Darjeeling.jpg?width=1200", "[\"Guided walking tour through towering pine trails of Senchal Wildlife Sanctuary\",\"Sunrise view of Mt. Kanchenjunga over forested ridges from Tiger Hill\",\"Scenic stroll through the historic Mall Road & Chowrasta\",\"Visit Padmaja Naidu Himalayan Zoological Park & Himalayan Mountaineering Institute\",\"Peaceful morning tea tasting overlooking verdant pine slopes\"]", "[\"3 Nights accommodation in a boutique hill-view hotel\",\"Daily mountain breakfast & dinner\",\"Private cab for all local sightseeing & transfers from NJP\/Bagdogra\",\"Guided nature walk in Senchal Forest\",\"All entry fees and permits\"]", "[\"Train\/Airfare to and from NJP\/Bagdogra\",\"Personal expenses, tips, and laundry\",\"Any meals not mentioned in inclusions\"]", "[{\"day\":1,\"title\":\"Arrival & Darjeeling Pine Transfer\",\"desc\":\"Meet & greet at NJP Railway Station \/ Bagdogra Airport. Enjoy a scenic 3.5-hour hill drive winding past dense pine corridors. Evening stroll around Darjeeling Mall Road.\",\"meals\":\"Dinner\",\"hotel\":\"Pine View Heritage Hotel\"},{\"day\":2,\"title\":\"Tiger Hill Sunrise & Pine Trails\",\"desc\":\"Early morning excursion (4:00 AM) to Tiger Hill for the world-famous sunrise over Mt. Kanchenjunga. Visit Batasia Loop, Ghoom Monastery, and afternoon guided hike through the Senchal Pine Sanctuary.\",\"meals\":\"Breakfast, Dinner\",\"hotel\":\"Pine View Heritage Hotel\"},{\"day\":3,\"title\":\"Darjeeling Mountain Heritage & Tea\",\"desc\":\"Visit Himalayan Mountaineering Institute, P.N. Himalayan Zoo, Tibetan Refugee Self Help Centre, Japanese Peace Pagoda, and Happy Valley Tea Estate.\",\"meals\":\"Breakfast, Dinner\",\"hotel\":\"Pine View Heritage Hotel\"},{\"day\":4,\"title\":\"Departure via Mirik Pines\",\"desc\":\"Check-out and transfer back to NJP\/Bagdogra with a picturesque detour through the cedar forest road of Mirik.\",\"meals\":\"Breakfast\",\"hotel\":\"N\/A\"}]", 1);

INSERT INTO `itineraries` (`id`, `title`, `destination`, `description`, `price`, `duration_days`, `image`, `highlights`, `inclusions`, `exclusions`, `day_plan`, `status`) VALUES
(2, "Yumthang Valley of Flowers", "North Sikkim", "Explore Yumthang Valley at 11,800 ft, renowned as the Valley of Flowers in North Sikkim. Carpeted with vibrant rhododendrons, alpine meadows, natural sulfur hot springs, and enclosed by snow-capped Himalayan giants.", 18500, 5, "https://commons.wikimedia.org/wiki/Special:FilePath/Yumthang_Valley_at_North_Sikkim,_India_01.jpg?width=1200", "[\"Traverse the Shingba Rhododendron Sanctuary with over 24 floral species\",\"Natural therapeutic hot sulfur water spring bath in Yumthang\",\"Breathtaking vistas of snow-crowned peaks and crystalline Teesta riverbeds\",\"Experience authentic Sikkimese culture in the peaceful village of Lachung\",\"Scenic waterfalls including Seven Sister and Naga Falls along the journey\"]", "[\"4 Nights stay (2N Gangtok, 2N Lachung) in cozy mountain lodges\",\"All meals during the North Sikkim tour (Breakfast, Lunch, Dinner)\",\"Dedicated SUV (Bolero\/Innova\/Xylo) with experienced mountain driver\",\"North Sikkim Restricted Area Protected Permits & documentation\",\"Fuel, toll, driver allowances, and parking\"]", "[\"Zero Point \/ Katao excursion (payable directly on spot)\",\"Personal warm clothing rentals (boots, overcoats)\",\"Personal expenses and travel insurance\"]", "[{\"day\":1,\"title\":\"Arrival in Gangtok\",\"desc\":\"Pick-up from NJP\/Bagdogra and transfer to Gangtok (approx. 4.5 hrs). Evening leisure walk on MG Marg.\",\"meals\":\"Dinner\",\"hotel\":\"Gangtok Mountain View Hotel\"},{\"day\":2,\"title\":\"Gangtok to Lachung Expedition\",\"desc\":\"Drive to Lachung (8,600 ft) via Chungthang. Stop at Seven Sister Waterfalls, Singhik Viewpoint, and Naga Falls. Check-in at Lachung homestay.\",\"meals\":\"Breakfast, Lunch, Dinner\",\"hotel\":\"Lachung Pine Lodge\"},{\"day\":3,\"title\":\"Yumthang Valley of Flowers Exploration\",\"desc\":\"Early morning drive to Yumthang Valley (11,800 ft). Marvel at alpine meadows, hot springs, and riverbeds. Optional excursion to Zero Point (15,300 ft). Return to Lachung.\",\"meals\":\"Breakfast, Lunch, Dinner\",\"hotel\":\"Lachung Pine Lodge\"},{\"day\":4,\"title\":\"Lachung to Gangtok via Phodong\",\"desc\":\"Drive back to Gangtok with stops at scenic river confluences. Evening free for souvenir shopping on MG Marg.\",\"meals\":\"Breakfast, Dinner\",\"hotel\":\"Gangtok Mountain View Hotel\"},{\"day\":5,\"title\":\"Departure\",\"desc\":\"Morning breakfast and transfer to Siliguri \/ NJP \/ Bagdogra for onward journey.\",\"meals\":\"Breakfast\",\"hotel\":\"N\/A\"}]", 1);

INSERT INTO `itineraries` (`id`, `title`, `destination`, `description`, `price`, `duration_days`, `image`, `highlights`, `inclusions`, `exclusions`, `day_plan`, `status`) VALUES
(3, "Toy Train at Ghum Station", "Ghum", "Experience the nostalgic steam-powered heritage joyride of the Darjeeling Himalayan Railway (UNESCO World Heritage). Climb to Ghum, India\'s highest railway station at 7,407 ft, and visit the DHR Rail Museum.", 11000, 3, "https://commons.wikimedia.org/wiki/Special:FilePath/A_train_of_Darjeeling_Himalayan_Railway_at_Ghoom_Station.jpg?width=1200", "[\"Reserved Joy Ride tickets on the authentic steam\/diesel Darjeeling Toy Train\",\"360-degree panoramic loop at the famous Batasia Loop & War Memorial\",\"Visit Ghum Railway Station and the historic DHR Railway Museum\",\"Explore the ancient Yiga Choeling (Old Ghum) Monastery\",\"Rock Garden and Ganga Maya Park mountain drive\"]", "[\"2 Nights hotel stay in Darjeeling \/ Ghum\",\"Confirmed Toy Train Joyride tickets\",\"Daily Breakfast and Dinner\",\"Private transfers from and to NJP\/Bagdogra\",\"All local sightseeing and driver costs\"]", "[\"Camera fees at monuments\",\"Lunches and extra snacks\",\"Expenses due to unforeseen weather delays\"]", "[{\"day\":1,\"title\":\"Arrival & Ghum Heritage Welcome\",\"desc\":\"Pickup from NJP\/IXB and drive through the tea-lined Hill Cart Road following the narrow-gauge rail tracks. Check in and relax.\",\"meals\":\"Dinner\",\"hotel\":\"Ghum Heritage Resort\"},{\"day\":2,\"title\":\"DHR Toy Train Joy Ride & Monasteries\",\"desc\":\"Board the iconic DHR Toy Train from Darjeeling to Ghum. Stop for 10 minutes at Batasia Loop for 360-degree views, explore Ghum Rail Museum, then visit Old Ghum Monastery.\",\"meals\":\"Breakfast, Dinner\",\"hotel\":\"Ghum Heritage Resort\"},{\"day\":3,\"title\":\"Departure\",\"desc\":\"Morning breakfast, quick photo-stop at Batasia Loop viewpoint, and drop-off at NJP Station \/ Bagdogra Airport.\",\"meals\":\"Breakfast\",\"hotel\":\"N\/A\"}]", 1);

INSERT INTO `itineraries` (`id`, `title`, `destination`, `description`, `price`, `duration_days`, `image`, `highlights`, `inclusions`, `exclusions`, `day_plan`, `status`) VALUES
(4, "Zero Point, North Sikkim", "North Sikkim", "Ascend to Yumesamdong (Zero Point) at a staggering 15,300 ft, where civilization ends and the rugged snowy wonderland near the Indo-Tibetan border begins. An unforgettable high-altitude adventure.", 21000, 5, "https://commons.wikimedia.org/wiki/Special:FilePath/Zero_Point,_Sikkim.jpg?width=1200", "[\"High-altitude snow exploration at Yumesamdong \/ Zero Point (15,300 ft)\",\"Panoramic vista of the snow-clad Donkia La Himalayan range\",\"Visit Yumthang Valley and natural hot springs\",\"Pristine mountain river gorges along the Lachen and Lachung valleys\",\"Traditional hot mountain momos and tea amidst freezing glaciers\"]", "[\"4 Nights stay (2N Gangtok, 2N Lachung)\",\"All meals during North Sikkim excursion (Breakfast, Lunch, Dinner)\",\"Dedicated high-clearance 4WD vehicle for high-altitude terrain\",\"Special army and administrative permits for Zero Point\",\"Professional local mountain driver\"]", "[\"Any medical evacuation or oxygen cylinder costs\",\"Personal cold-weather gear rentals\",\"Air \/ Train tickets\"]", "[{\"day\":1,\"title\":\"Arrival in Gangtok\",\"desc\":\"Transfer from NJP\/Bagdogra to Gangtok hotel. Acclimatization and permit processing day.\",\"meals\":\"Dinner\",\"hotel\":\"Gangtok Alpine Hotel\"},{\"day\":2,\"title\":\"Gangtok to Lachung\",\"desc\":\"Scenic drive into North Sikkim through cascading waterfalls and deep valleys. Overnight at Lachung.\",\"meals\":\"Breakfast, Lunch, Dinner\",\"hotel\":\"Lachung Alpine Inn\"},{\"day\":3,\"title\":\"Zero Point (15,300 ft) & Yumthang Expedition\",\"desc\":\"Early 5:30 AM departure to Yumthang Valley and up to Zero Point (Yumesamdong). Experience pristine snow, towering peaks, and return to Lachung.\",\"meals\":\"Breakfast, Lunch, Dinner\",\"hotel\":\"Lachung Alpine Inn\"},{\"day\":4,\"title\":\"Lachung to Gangtok\",\"desc\":\"Return journey to Gangtok. Relax and celebrate the high-altitude expedition on MG Marg.\",\"meals\":\"Breakfast, Dinner\",\"hotel\":\"Gangtok Alpine Hotel\"},{\"day\":5,\"title\":\"Gangtok Departure\",\"desc\":\"Transfer back to NJP Railway Station \/ Bagdogra Airport.\",\"meals\":\"Breakfast\",\"hotel\":\"N\/A\"}]", 1);

INSERT INTO `itineraries` (`id`, `title`, `destination`, `description`, `price`, `duration_days`, `image`, `highlights`, `inclusions`, `exclusions`, `day_plan`, `status`) VALUES
(5, "Kurseong Tea Estates", "Kurseong", "Step into the land of the world\'s most prized Darjeeling muscatel teas. Stay amidst centuries-old tea gardens in Kurseong, tour Makaibari and Castleton estates, and master the art of tea tasting.", 13500, 3, "https://commons.wikimedia.org/wiki/Special:FilePath/Tea_estate_in_kurseong.jpg?width=1200", "[\"Private guided tea factory walk and plucking experience at Makaibari\/Castleton\",\"Tea masterclass and tasting session with freshly brewed First Flush teas\",\"Panoramic sunset over tea slopes from Eagle\'s Crag viewpoint\",\"Heritage walk along the colonial bungalow trails of Kurseong\",\"Scenic toy train track photography spots\"]", "[\"2 Nights stay in a luxury tea retreat \/ colonial planter bungalow\",\"Daily Breakfast and multi-course Farm-to-Table Dinners\",\"Exclusive Tea Estate and Factory guided tour\",\"Dedicated private cab for all sightseeing & transfers\",\"Complimentary artisanal tea tasting pack\"]", "[\"Personal purchases from estate boutique\",\"Gratuities and tips\",\"Travel insurance\"]", "[{\"day\":1,\"title\":\"Arrival in Kurseong Tea Country\",\"desc\":\"Pick-up from NJP\/Bagdogra and drive up to Kurseong (1.5 hrs). Check into heritage tea estate bungalow. Sunset tea on the garden lawn.\",\"meals\":\"Dinner\",\"hotel\":\"Makaibari Planter Retreat\"},{\"day\":2,\"title\":\"Tea Plucking, Factory Tour & Eagle\'s Crag\",\"desc\":\"Morning tea plucking with local estate workers. Guided tour of the orthodox tea factory, tea tasting session, followed by sunset at Eagle\'s Crag.\",\"meals\":\"Breakfast, Dinner\",\"hotel\":\"Makaibari Planter Retreat\"},{\"day\":3,\"title\":\"Kurseong to Departure\",\"desc\":\"Leisurely breakfast overlooking the misty tea slopes. Check-out and transfer to Bagdogra Airport or NJP Station.\",\"meals\":\"Breakfast\",\"hotel\":\"N\/A\"}]", 1);

INSERT INTO `itineraries` (`id`, `title`, `destination`, `description`, `price`, `duration_days`, `image`, `highlights`, `inclusions`, `exclusions`, `day_plan`, `status`) VALUES
(6, "Dow Hill Forest", "Kurseong", "Uncover the mystical pine and conifer wilderness of Dow Hill, Kurseong. Featuring moss-draped forest trails, colonial-era architecture, serene deer park, and rich Himalayan birdlife.", 12500, 3, "https://commons.wikimedia.org/wiki/Special:FilePath/Dow_hill_Kurseong.jpg?width=1200", "[\"Trek through the atmospheric dense pine and conifer woods of Dow Hill\",\"Visit the historic Victoria Boys School & Dow Hill Girls School (est. 1879)\",\"Explore the Dow Hill Deer Park and Forest Museum\",\"Chimney heritage viewpoint overlooking Teesta and Balason rivers\",\"Peaceful mountain photography and birdwatching opportunities\"]", "[\"2 Nights stay at a forest-view resort in Kurseong\",\"Daily breakfast and dinner\",\"Private cab for transfers and sightseeing\",\"Guided morning forest nature trail\",\"All local sightseeing permits\"]", "[\"Video camera permits\",\"Personal expenses & lunch\",\"Train\/Flight fares\"]", "[{\"day\":1,\"title\":\"Arrival at Dow Hill\",\"desc\":\"Transfer from NJP\/Bagdogra to Kurseong. Check-in to forest resort. Evening leisurely stroll to nearby viewpoints.\",\"meals\":\"Dinner\",\"hotel\":\"Dow Hill Forest Eco Resort\"},{\"day\":2,\"title\":\"Forest Trails & Colonial Heritage\",\"desc\":\"Morning guided nature trek into Dow Hill pine trails. Visit the Forest Museum, Deer Park, and the iconic colonial architecture of Victoria School. Sunset at Chimney.\",\"meals\":\"Breakfast, Dinner\",\"hotel\":\"Dow Hill Forest Eco Resort\"},{\"day\":3,\"title\":\"Departure\",\"desc\":\"Breakfast amidst pine breeze, check-out, and drive back to Bagdogra \/ NJP.\",\"meals\":\"Breakfast\",\"hotel\":\"N\/A\"}]", 1);

INSERT INTO `itineraries` (`id`, `title`, `destination`, `description`, `price`, `duration_days`, `image`, `highlights`, `inclusions`, `exclusions`, `day_plan`, `status`) VALUES
(7, "Kanchenjunga Panorama", "Darjeeling", "Revel in the majestic grandeur of Mt. Kanchenjunga (8,586 m), the world\'s third highest peak. Experience 360-degree Himalayan vistas, Tiger Hill sunrise, cable car rides, and charming alpine cafes.", 16500, 4, "https://commons.wikimedia.org/wiki/Special:FilePath/Darjeeling-panoramic.jpg?width=1200", "[\"Unobstructed sunrise view of the Kanchenjunga range from Tiger Hill (8,482 ft)\",\"Darjeeling Ropeway (Cable Car) ride over lush tea gardens\",\"Batasia Loop spiral railway engineering marvel with mountain backdrop\",\"Himalayan Mountaineering Institute museum & Everest summit gear\",\"Evening mountain coffee and bakery crawl at Chowrasta\"]", "[\"3 Nights stay in premium Kanchenjunga-facing hotel room\",\"Daily Breakfast & Dinner\",\"Dedicated private cab for all excursions\",\"Ropeway and Tiger Hill permits\",\"24\/7 dedicated local tour coordinator\"]", "[\"Meals outside breakfast and dinner\",\"Personal expenses and shopping\",\"Excess baggage charges\"]", "[{\"day\":1,\"title\":\"Arrival & Queen of Hills Panorama\",\"desc\":\"Pick-up from NJP\/Bagdogra. Scenic hill climb to Darjeeling. Check into mountain-facing room with direct Kanchenjunga views. Evening at Mall Road.\",\"meals\":\"Dinner\",\"hotel\":\"Kanchenjunga View Grand\"},{\"day\":2,\"title\":\"Tiger Hill Sunrise & Classic Sights\",\"desc\":\"Early morning sunrise at Tiger Hill. Visit Batasia Loop, Ghoom Monastery, Japanese Peace Pagoda, and Ava Art Gallery.\",\"meals\":\"Breakfast, Dinner\",\"hotel\":\"Kanchenjunga View Grand\"},{\"day\":3,\"title\":\"Ropeway Cable Car & Tea Valleys\",\"desc\":\"Take the Darjeeling Ropeway cable car across the Rangeet valley. Visit HMI, Zoo, and Lebong Race Course viewpoint.\",\"meals\":\"Breakfast, Dinner\",\"hotel\":\"Kanchenjunga View Grand\"},{\"day\":4,\"title\":\"Farewell Darjeeling\",\"desc\":\"Breakfast with final panoramic views of the Himalayas. Check-out and transfer to NJP\/Bagdogra.\",\"meals\":\"Breakfast\",\"hotel\":\"N\/A\"}]", 1);

INSERT INTO `itineraries` (`id`, `title`, `destination`, `description`, `price`, `duration_days`, `image`, `highlights`, `inclusions`, `exclusions`, `day_plan`, `status`) VALUES
(8, "Pine Road to Mirik", "Mirik", "Drive along the breathtaking serpentine pine corridors connecting Siliguri, Sukhiapokhri, and Mirik along the Indo-Nepal border. A scenic road trip through cardamom forests and misty ridges.", 10500, 3, "https://commons.wikimedia.org/wiki/Special:FilePath/A_Route_through_Pine_Forest.jpg?width=1200", "[\"Scenic drive along the tree-lined pine forest road of Sukhiapokhri & Mirik\",\"Stop at the Indo-Nepal border market in Pashupati Nagar\",\"Visit orange orchards and cardamom valley plantations\",\"Relaxing lakeside walk and boating at Sumendu Lake\",\"Spectacular sunrise and sunset views from Tingling Viewpoint\"]", "[\"2 Nights hotel stay in Mirik\",\"Daily Breakfast & Dinner\",\"Private vehicle for the complete road trip from NJP\/Bagdogra\",\"All tolls, parking, and driver allowances\"]", "[\"Cross-border purchases at Nepal market\",\"Boating fees at Mirik Lake\",\"Personal travel insurance\"]", "[{\"day\":1,\"title\":\"Siliguri to Mirik Pine Corridor\",\"desc\":\"Pick-up from NJP\/Bagdogra. Drive through the dense pine highways of Mirik. Check-in and evening walk by the lake.\",\"meals\":\"Dinner\",\"hotel\":\"Mirik Pine Retreat\"},{\"day\":2,\"title\":\"Pine Forest Road & Indo-Nepal Border\",\"desc\":\"Drive along the pine ridge road to Pashupati Nagar (Indo-Nepal border). Visit Tingling View Point, orange gardens, and Swiss Cottage grounds.\",\"meals\":\"Breakfast, Dinner\",\"hotel\":\"Mirik Pine Retreat\"},{\"day\":3,\"title\":\"Departure\",\"desc\":\"Breakfast with lake views, souvenir shopping for authentic hill honey and tea, transfer to NJP\/Bagdogra.\",\"meals\":\"Breakfast\",\"hotel\":\"N\/A\"}]", 1);

INSERT INTO `itineraries` (`id`, `title`, `destination`, `description`, `price`, `duration_days`, `image`, `highlights`, `inclusions`, `exclusions`, `day_plan`, `status`) VALUES
(9, "Sumendu Lake", "Mirik", "A soothing weekend getaway centered around the calm, picturesque Sumendu Lake in Mirik. Walk across the arched 80-foot rainbow footbridge, ride paddle boats, and ride horses along pine-lined shores.", 9500, 2, "https://commons.wikimedia.org/wiki/Special:FilePath/Sumendu_Lake,_Mirik.jpg?width=1200", "[\"Serene paddle boating on the calm waters of Sumendu Lake\",\"Walk across the iconic arched Indreni Bridge (Rainbow Footbridge)\",\"Lakeside horse riding and cycling along pine trails\",\"Visit Bokar Buddhist Monastery offering panoramic valley views\",\"Sample fresh hot momos, thukpa, and local organic teas\"]", "[\"1 Night stay in a lakefront resort\",\"Breakfast and Dinner included\",\"Private vehicle for round-trip transfers from NJP\/Bagdogra\",\"Guided walking tour around the 3.5 km lake perimeter\"]", "[\"Boating and horse-riding activity charges\",\"Lunch and personal expenses\"]", "[{\"day\":1,\"title\":\"Arrival & Lakeside Sunset\",\"desc\":\"Pickup from NJP\/Bagdogra, drive to Mirik (2 hrs). Check into lakeside resort. Enjoy evening boating on Sumendu Lake and sunset over the water.\",\"meals\":\"Dinner\",\"hotel\":\"Sumendu Lakefront Resort\"},{\"day\":2,\"title\":\"Monastery, Lake Trail & Departure\",\"desc\":\"Morning walk across the Rainbow Bridge and visit Bokar Monastery. Check-out and return transfer to NJP\/Bagdogra.\",\"meals\":\"Breakfast\",\"hotel\":\"N\/A\"}]", 1);

INSERT INTO `itineraries` (`id`, `title`, `destination`, `description`, `price`, `duration_days`, `image`, `highlights`, `inclusions`, `exclusions`, `day_plan`, `status`) VALUES
(10, "Tea Gardens of the Dooars", "Dooars", "Journey into the lush green plains and foothills of Dooars. Where emerald tea gardens meet untamed tropical rainforests, wildlife sanctuaries, and clear babbling rivers like Murti and Jaldhaka.", 17500, 4, "https://commons.wikimedia.org/wiki/Special:FilePath/Tea_garden_in_dooars.jpg?width=1200", "[\"Open 4x4 Jeep Safari in Gorumara \/ Jaldapara National Park\",\"Guided tea estate heritage walks across Chalsa and Malbazar tea gardens\",\"Riverside relaxation at Murti river banks and Rocky Island\",\"Visit Samsing, Suntalekhola, and Jhalong Indo-Bhutan border\",\"Evening tribal folk dance performance with bonfire\"]", "[\"3 Nights accommodation in a nature resort in Lataguri \/ Murti\",\"Daily Breakfast, Lunch, and Dinner (All Meals)\",\"1 Jungle Jeep Safari with forest guide and gypsy permit\",\"Private AC vehicle for all transfers and sightseeing\",\"Tribal cultural evening with bonfire\"]", "[\"Forest elephant safari tickets (subject to availability)\",\"Camera and video gear fees\",\"Train\/Air tickets\"]", "[{\"day\":1,\"title\":\"Arrival in Dooars (Lataguri\/Murti)\",\"desc\":\"Pick-up from NJP\/Hasimara\/Bagdogra. Drive through tea gardens into Lataguri. Evening bonfire by the river.\",\"meals\":\"Lunch, Dinner\",\"hotel\":\"Dooars Jungle & Tea Resort\"},{\"day\":2,\"title\":\"Jungle Safari & Tea Valleys\",\"desc\":\"Early morning jungle jeep safari in Gorumara to spot rhinos, bison, and peacocks. Afternoon guided tour of heritage Dooars tea gardens.\",\"meals\":\"Breakfast, Lunch, Dinner\",\"hotel\":\"Dooars Jungle & Tea Resort\"},{\"day\":3,\"title\":\"Samsing, Suntalekhola & Jhalong\",\"desc\":\"Day excursion to the scenic hill-streams of Samsing, hanging bridge of Suntalekhola, and Bindu dam on Indo-Bhutan border.\",\"meals\":\"Breakfast, Lunch, Dinner\",\"hotel\":\"Dooars Jungle & Tea Resort\"},{\"day\":4,\"title\":\"Departure\",\"desc\":\"Breakfast by the forest edge, check-out, and drop at NJP or Bagdogra.\",\"meals\":\"Breakfast\",\"hotel\":\"N\/A\"}]", 1);

INSERT INTO `itineraries` (`id`, `title`, `destination`, `description`, `price`, `duration_days`, `image`, `highlights`, `inclusions`, `exclusions`, `day_plan`, `status`) VALUES
(11, "Sela Pass", "Arunachal Pradesh", "Embark on an epic Himalayan expedition to Sela Pass at 13,700 ft in Arunachal Pradesh. Witness the sacred high-altitude Sela Lake, snow-draped landscapes, Jaswant Garh War Memorial, and Tawang Monastery.", 26000, 6, "https://commons.wikimedia.org/wiki/Special:FilePath/Sela_Pass,_Arunachal_Pradesh.jpg?width=1200", "[\"Cross the snow-covered Sela Pass (13,700 ft) and crystal-clear Sela Lake\",\"Visit the sacred 400-year-old Tawang Monastery (second largest in the world)\",\"Pay homage at Jaswant Garh War Memorial & Nuranang (Jung) Waterfalls\",\"Scenic journey through Dirang Valley, apple orchards, and kiwi farms\",\"High mountain pass photography in the mystical Land of the Dawn-lit Mountains\"]", "[\"5 Nights stay (Dirang, Tawang, Bomdila)\",\"Daily Breakfast and Dinner\",\"Dedicated SUV (Scorpio\/Innova) with expert mountain driver\",\"Arunachal Pradesh Inner Line Permit (ILP) processing\",\"All toll, parking, and driver allowances\"]", "[\"Bumla Pass & Madhuri Lake special army permits (arranged locally)\",\"Warm clothing rentals and personal expenses\",\"Airfare to Guwahati\/Tezpur\"]", "[{\"day\":1,\"title\":\"Guwahati to Bhalukpong\/Dirang\",\"desc\":\"Pick-up from Guwahati. Drive through Assam plains entering Arunachal Pradesh. Overnight in Dirang.\",\"meals\":\"Dinner\",\"hotel\":\"Dirang Valley Lodge\"},{\"day\":2,\"title\":\"Dirang to Tawang via Sela Pass\",\"desc\":\"Drive across Sela Pass (13,700 ft), stop at frozen Sela Lake and Jaswant Garh Memorial. Arrive in Tawang.\",\"meals\":\"Breakfast, Dinner\",\"hotel\":\"Tawang Himalayan Hotel\"},{\"day\":3,\"title\":\"Tawang Monastery & Local Wonders\",\"desc\":\"Full day in Tawang: visit Tawang Monastery, Urgelling Monastery, War Memorial light & sound show.\",\"meals\":\"Breakfast, Dinner\",\"hotel\":\"Tawang Himalayan Hotel\"},{\"day\":4,\"title\":\"Bum La Pass & Sangetsar Lake\",\"desc\":\"Day trip to Indo-China border at Bum La Pass and scenic Madhuri Lake. Return to Tawang.\",\"meals\":\"Breakfast, Dinner\",\"hotel\":\"Tawang Himalayan Hotel\"},{\"day\":5,\"title\":\"Tawang to Bomdila via Nuranang Falls\",\"desc\":\"Descend via spectacular 100-meter Nuranang Falls to Bomdila. Visit Bomdila Monastery & viewpoint.\",\"meals\":\"Breakfast, Dinner\",\"hotel\":\"Bomdila View Hotel\"},{\"day\":6,\"title\":\"Bomdila to Guwahati Departure\",\"desc\":\"Early breakfast and drive back to Guwahati Airport \/ Railway Station.\",\"meals\":\"Breakfast\",\"hotel\":\"N\/A\"}]", 1);

INSERT INTO `itineraries` (`id`, `title`, `destination`, `description`, `price`, `duration_days`, `image`, `highlights`, `inclusions`, `exclusions`, `day_plan`, `status`) VALUES
(12, "One-Horned Rhino, Kaziranga", "Assam", "Witness the legendary Great Indian One-Horned Rhinoceros in its prime natural sanctuary at Kaziranga National Park (UNESCO World Heritage Site). Includes open jeep and elephant safaris across diverse ranges.", 22000, 4, "https://commons.wikimedia.org/wiki/Special:FilePath/One-Horned_Rhino_at_the_Kaziranga_National_Park,_Assam.jpg?width=1200", "[\"Multiple Jeep Safaris across Central (Kohora) and Western (Bagori) Ranges\",\"Close-range sightings of One-Horned Rhinos, wild water buffaloes, and elephants\",\"Visit Kaziranga National Orchid and Biodiversity Park\",\"Tea garden stroll and traditional Assamese cultural dance performance\",\"Dolphin boat safari on the mighty Brahmaputra river\"]", "[\"3 Nights accommodation in an eco-jungle resort in Kaziranga\",\"All meals included (Breakfast, Lunch, Dinner)\",\"2 Private Jeep Safaris with forest entry permits, toll, and armed guard\",\"Dedicated AC vehicle for Guwahati-Kaziranga transfers and local travel\",\"Cultural show entry tickets\"]", "[\"Elephant ride tickets (subject to forest dept lottery)\",\"Professional camera and lens fees\",\"Air \/ Train tickets to Guwahati\"]", "[{\"day\":1,\"title\":\"Guwahati to Kaziranga\",\"desc\":\"Pick-up from Guwahati Airport\/Station. Scenic drive (4 hrs) to Kaziranga. Evening Assamese folk dance show.\",\"meals\":\"Dinner\",\"hotel\":\"Kaziranga Eco Jungle Resort\"},{\"day\":2,\"title\":\"Central & Western Range Safaris\",\"desc\":\"Early morning Western Range (Bagori) Jeep Safari for heavy rhino sightings. Afternoon Central Range (Kohora) safari.\",\"meals\":\"Breakfast, Lunch, Dinner\",\"hotel\":\"Kaziranga Eco Jungle Resort\"},{\"day\":3,\"title\":\"Orchid Park, Tea Tour & River Cruise\",\"desc\":\"Visit the National Orchid Park showcasing 500+ species, visit surrounding tea estates, and sunset Brahmaputra cruise.\",\"meals\":\"Breakfast, Lunch, Dinner\",\"hotel\":\"Kaziranga Eco Jungle Resort\"},{\"day\":4,\"title\":\"Kaziranga to Guwahati Departure\",\"desc\":\"Morning breakfast, check-out, and transfer back to Guwahati Airport for departure.\",\"meals\":\"Breakfast\",\"hotel\":\"N\/A\"}]", 1);

INSERT INTO `itineraries` (`id`, `title`, `destination`, `description`, `price`, `duration_days`, `image`, `highlights`, `inclusions`, `exclusions`, `day_plan`, `status`) VALUES
(13, "Tea Gardens of Assam", "Assam", "Experience the colonial charm of Assam\'s lush, sprawling tea estates in the Brahmaputra valley. Stay in a century-old heritage British planter bungalow, learn tea plucking, and savor authentic single-origin Assam brews.", 19000, 4, "https://commons.wikimedia.org/wiki/Special:FilePath/Tea_Garden_at_Indo-Bhutan_Border_at_Darranga,_Assam.jpg?width=1200", "[\"Stay in an authentic colonial Tea Planter Heritage Bungalow\",\"Guided tea estate walk and hands-on tea leaf plucking session\",\"Factory visit to witness the journey from leaf to cup (CTC & Orthodox)\",\"High tea on the manicured lawns overlooking endless tea bushes\",\"Sunset boat ride along the Brahmaputra River\"]", "[\"3 Nights luxury stay in a Heritage Tea Bungalow\",\"Daily Breakfast and gourmet 3-course Dinners\",\"Private tea tasting and estate factory guided tour\",\"Dedicated private AC vehicle with chauffeur for all days\",\"Complimentary tin of premium Single-Estate Assam Golden Tips Tea\"]", "[\"Extra drinks and alcohol\",\"Airfare \/ Rail fare\",\"Gratuities for estate staff\"]", "[{\"day\":1,\"title\":\"Arrival in Assam Tea Country\",\"desc\":\"Pick-up from Guwahati \/ Jorhat. Drive through green tea gardens to the heritage planter bungalow. Welcoming high tea.\",\"meals\":\"Dinner\",\"hotel\":\"Heritage Assam Tea Bungalow\"},{\"day\":2,\"title\":\"Estate Walk, Tea Plucking & Factory\",\"desc\":\"Morning walk through tea lanes with pluckers. Afternoon visit to the tea processing factory with tea tasting session.\",\"meals\":\"Breakfast, Dinner\",\"hotel\":\"Heritage Assam Tea Bungalow\"},{\"day\":3,\"title\":\"Brahmaputra Sunset & Local Culture\",\"desc\":\"Explore nearby silk weaving village and take an evening boat cruise on the Brahmaputra River.\",\"meals\":\"Breakfast, Dinner\",\"hotel\":\"Heritage Assam Tea Bungalow\"},{\"day\":4,\"title\":\"Departure\",\"desc\":\"Breakfast on the veranda, check-out, and transfer to airport\/station.\",\"meals\":\"Breakfast\",\"hotel\":\"N\/A\"}]", 1);

INSERT INTO `itineraries` (`id`, `title`, `destination`, `description`, `price`, `duration_days`, `image`, `highlights`, `inclusions`, `exclusions`, `day_plan`, `status`) VALUES
(14, "Palolem Beach", "Goa", "Relax on the golden crescent sands of Palolem Beach in South Goa. Enclosed by palm groves and calm turquoise waters, ideal for dolphin cruises, kayaking, beachside yoga, and candlelit seafood dinners.", 18000, 4, "https://commons.wikimedia.org/wiki/Special:FilePath/Palolem_Beach.jpg?width=1200", "[\"Stay in cozy beach cottages steps away from the Arabian Sea\",\"Early morning dolphin-spotting boat ride to Butterfly Beach & Honeymoon Beach\",\"Sea kayaking along the calm sheltered bay of Palolem\",\"Visit scenic neighboring Agonda and Cola Beach with its freshwater lagoon\",\"Beachfront candlelight dinner with fresh coastal Goan cuisine\"]", "[\"3 Nights accommodation in a beachside boutique resort\/cottage\",\"Daily Tropical Breakfast\",\"Private AC cab for airport\/railway transfers (Dabolim\/Mopa\/Madgaon)\",\"1 Dolphin watching boat trip for all guests\",\"All vehicle taxes and parking\"]", "[\"Water sports and kayak rental fees\",\"Alcoholic beverages\",\"Flight\/train tickets\"]", "[{\"day\":1,\"title\":\"Arrival in South Goa\",\"desc\":\"Pick-up from Goa Airport \/ Madgaon Station and transfer to Palolem. Check-in to beach cottage. Relax with sunset beach walk.\",\"meals\":\"Dinner\",\"hotel\":\"Palolem Beachside Boutique Cottages\"},{\"day\":2,\"title\":\"Dolphin Cruise & Secret Beaches\",\"desc\":\"Morning boat trip to spot dolphins, visit Butterfly Beach and Monkey Island. Afternoon swimming in the calm bay.\",\"meals\":\"Breakfast\",\"hotel\":\"Palolem Beachside Boutique Cottages\"},{\"day\":3,\"title\":\"Agonda, Cabo de Rama & Cola Lagoon\",\"desc\":\"Day tour to scenic Cabo de Rama fort overlooking cliffs, Cola Beach freshwater lagoon, and pristine Agonda beach.\",\"meals\":\"Breakfast\",\"hotel\":\"Palolem Beachside Boutique Cottages\"},{\"day\":4,\"title\":\"Departure\",\"desc\":\"Morning beach breakfast, check-out, and transfer to Goa Airport \/ Madgaon Station.\",\"meals\":\"Breakfast\",\"hotel\":\"N\/A\"}]", 1);

INSERT INTO `itineraries` (`id`, `title`, `destination`, `description`, `price`, `duration_days`, `image`, `highlights`, `inclusions`, `exclusions`, `day_plan`, `status`) VALUES
(15, "Basilica of Bom Jesus", "Goa", "Immerse yourself in centuries of rich history and baroque architecture in Old Goa. Visit the UNESCO World Heritage Basilica of Bom Jesus, Se Cathedral, Church of St. Francis of Assisi, and colourful Fontainhas.", 15000, 3, "https://commons.wikimedia.org/wiki/Special:FilePath/Basilica_of_Bom_Jesus.jpg?width=1200", "[\"Guided tour of the Basilica of Bom Jesus (holding relics of St. Francis Xavier)\",\"Explore the grand Se Cathedral, Church of St. Cajetan, and St. Augustine Tower\",\"Heritage walking tour through the vibrant Portuguese Latin Quarter of Fontainhas\",\"Visit ancestral spice plantations with traditional Goan buffet lunch\",\"Sunset Mandovi River Cruise with live Goan folk music\"]", "[\"2 Nights stay in a boutique heritage hotel in Panaji \/ Old Goa\",\"Daily Breakfast & 1 authentic Spice Plantation Buffet Lunch\",\"Guided walking tour of Fontainhas Latin Quarter\",\"Private AC vehicle for all transfers and heritage sightseeing\",\"Sunset Mandovi River cruise tickets\"]", "[\"Camera fees inside museums\",\"Personal shopping and tips\",\"Train\/Airfare\"]", "[{\"day\":1,\"title\":\"Arrival & Latin Quarter Charm\",\"desc\":\"Pick-up from Goa Airport\/Station. Check into heritage hotel. Guided sunset walking tour through the colorful streets of Fontainhas.\",\"meals\":\"Dinner\",\"hotel\":\"Panjim Heritage Inn\"},{\"day\":2,\"title\":\"Old Goa UNESCO Heritage & Spice Farm\",\"desc\":\"Visit the Basilica of Bom Jesus, Se Cathedral, and St. Francis of Assisi. Afternoon tour of Sahakari Spice Plantation with buffet lunch. Evening Mandovi cruise.\",\"meals\":\"Breakfast, Lunch\",\"hotel\":\"Panjim Heritage Inn\"},{\"day\":3,\"title\":\"Departure\",\"desc\":\"Morning breakfast, shopping for Goan feni, cashew nuts, and bebinca, transfer to airport\/station.\",\"meals\":\"Breakfast\",\"hotel\":\"N\/A\"}]", 1);

INSERT INTO `itineraries` (`id`, `title`, `destination`, `description`, `price`, `duration_days`, `image`, `highlights`, `inclusions`, `exclusions`, `day_plan`, `status`) VALUES
(16, "Dudhsagar Falls", "Goa", "Experience the breathtaking 4-tiered Dudhsagar Waterfalls (Sea of Milk) plunging 310 meters down the Western Ghats in Bhagwan Mahavir Wildlife Sanctuary. Includes thrilling 4x4 jungle jeep safari and natural pool swim.", 14000, 3, "https://commons.wikimedia.org/wiki/Special:FilePath/Dudhsagar_Falls.jpg?width=1200", "[\"4x4 Off-road Jungle Jeep Safari through rivers and dense Western Ghats forests\",\"Swim in the refreshing natural plunge pool beneath the thunderous Dudhsagar Falls\",\"Watch trains cross the iconic arched railway bridge right in front of the waterfall\",\"Guided tour of an aromatic organic spice plantation with traditional buffet\",\"Spot monkeys, exotic birds, and tropical flora in the wildlife sanctuary\"]", "[\"2 Nights stay in a nature resort \/ South Goa hotel\",\"Daily Breakfast & 1 Authentic Spice Farm Lunch\",\"4x4 Dudhsagar Jeep Safari tickets, forest department entry, and life jacket\",\"Dedicated private AC transfers throughout the tour\"]", "[\"Swimming costume rentals\",\"Personal tips and camera fees\",\"Rail \/ Air fare\"]", "[{\"day\":1,\"title\":\"Arrival in Goa\",\"desc\":\"Pickup from Airport \/ Madgaon Station. Check into resort and relax by the pool. Evening briefing for next day\'s safari.\",\"meals\":\"Dinner\",\"hotel\":\"Goa Jungle & River Resort\"},{\"day\":2,\"title\":\"Dudhsagar Off-Road Safari & Spice Farm\",\"desc\":\"Early morning departure to Kulem. Board 4x4 safari jeep through the sanctuary to Dudhsagar Falls. Swim in the mountain pool. Afternoon spice plantation tour with lunch.\",\"meals\":\"Breakfast, Lunch\",\"hotel\":\"Goa Jungle & River Resort\"},{\"day\":3,\"title\":\"Departure\",\"desc\":\"Morning breakfast, check-out, and transfer to Goa Airport \/ Madgaon Station.\",\"meals\":\"Breakfast\",\"hotel\":\"N\/A\"}]", 1);

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

-- ============================================
-- 10. REVIEW IMAGES TABLE
-- ============================================

CREATE TABLE IF NOT EXISTS review_images (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    review_id  INT NOT NULL,
    image      VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_review_id (review_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- 11. TRAVEL GALLERY TABLE
-- ============================================

CREATE TABLE IF NOT EXISTS gallery_images (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    destination VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    image       VARCHAR(255) NOT NULL,
    sort_order  INT DEFAULT 0,
    status      TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO gallery_images
    (id, title, destination, description, image, sort_order, status, created_at)
VALUES
    (1, 'Misty Sunrise at Tiger Hill', 'Darjeeling', 'Spectacular morning golden rays hitting the snow-capped peak of Mount Kanchenjunga.', 'https://images.unsplash.com/photo-1544735716-392fe2489ffa?auto=format&fit=crop&w=1200&q=80', 1, 1, NOW() - INTERVAL 10 DAY),
    (2, 'Valley of Flowers & Mountain Streams', 'Sikkim', 'Unbelievable colors and pristine alpine meadows in North Sikkim Yumthang Valley.', 'https://images.unsplash.com/photo-1626621341517-bbf3d9990a23?auto=format&fit=crop&w=1200&q=80', 2, 1, NOW() - INTERVAL 8 DAY),
    (3, 'Emerald Tea Gardens of Kurseong', 'Darjeeling', 'Walking through endless rolling slopes of fragrant tea plantations in the misty hills.', 'https://images.unsplash.com/photo-1576487247238-d98f9c065f49?auto=format&fit=crop&w=1200&q=80', 3, 1, NOW() - INTERVAL 6 DAY),
    (4, 'Serene Alleppey Houseboat Sunset', 'Kerala', 'Gentle cruise along palm-fringed backwaters as the sun dips below shimmering canals.', 'https://images.unsplash.com/photo-1602216056096-3b40cc0c9944?auto=format&fit=crop&w=1200&q=80', 4, 1, NOW() - INTERVAL 4 DAY),
    (5, 'Pristine Waves & Golden Sands', 'Goa', 'Peaceful evening walks and turquoise shores along the southern coastline.', 'https://images.unsplash.com/photo-1512343879784-a960bf40e7f2?auto=format&fit=crop&w=1200&q=80', 5, 1, NOW() - INTERVAL 2 DAY),
    (6, 'Kelingking Secret Beach & Cliffs', 'Bali', 'Dramatic T-Rex shaped coastal headland and crystal clear sapphire waters of Nusa Penida.', 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=1200&q=80', 6, 1, NOW() - INTERVAL 1 DAY)
ON DUPLICATE KEY UPDATE id=id;