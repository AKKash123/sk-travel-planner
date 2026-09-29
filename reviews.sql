-- Run once (phpMyAdmin > SQL tab, or mysql CLI)
CREATE TABLE IF NOT EXISTS itinerary_reviews (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    itinerary_id  INT NOT NULL,
    voter_hash    CHAR(64) NOT NULL,          -- sha256 of the browser's anonymous token
    rating        TINYINT UNSIGNED NOT NULL,  -- 1..5
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vote (itinerary_id, voter_hash),
    KEY idx_itinerary (itinerary_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
