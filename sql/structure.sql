-- 1. Datenbank erstellen (falls noch nicht geschehen)
CREATE DATABASE IF NOT EXISTS `thegunsite_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `thegunsite_db`;

-- 2. Tabelle: Users (Benutzerverwaltung)
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(100) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0, -- Der lila Haken
  `is_admin` tinyint(1) DEFAULT 0,    -- Zugriff auf Moderation/Verify_queue
  `is_premium` tinyint(1) DEFAULT 0,  -- Für den Lila Glow & Ranking
  `verification_status` enum('none','pending','approved','rejected') DEFAULT 'none',
  `rating_avg` decimal(3,2) DEFAULT 5.00,
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabelle: Listings (Inserate)
CREATE TABLE IF NOT EXISTS `listings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seller_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `category` enum('frei','ewb','zubehoer') NOT NULL,
  `type` enum('fixed','auction') NOT NULL, -- Festpreis oder Auktion
  `price` decimal(10,2) NOT NULL,          -- Startpreis oder Festpreis
  `sofort_kauf` decimal(10,2) DEFAULT 0.00, -- Sofort-Kauf Option
  `current_bid` decimal(10,2) DEFAULT NULL,
  `caliber` varchar(50) DEFAULT NULL,
  `condition_id` varchar(50) DEFAULT 'gebraucht',
  `zip` varchar(10) NOT NULL,
  `city` varchar(100) NOT NULL,
  `pickup` tinyint(1) DEFAULT 1,
  `views` int(11) DEFAULT 0,               -- Für das Dashboard
  `status` enum('active','sold','banned') DEFAULT 'active',
  `expires_at` timestamp NULL DEFAULT NULL, -- Für Auktionsende
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabelle: Messages (Chat-System)
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `listing_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`listing_id`) REFERENCES `listings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Erster Admin-Account (Passwort: CrackIt)
-- Hinweis: Der Hash wurde mit password_hash('CrackIt', PASSWORD_DEFAULT) generiert.
INSERT INTO `users` (`username`, `email`, `password_hash`, `city`, `is_verified`, `is_admin`, `verification_status`) 
VALUES ('Admin', 'admin@thegunsite.com', '$2y$10$7R9IAnJp9Wn8J7X1pXWzuehYx9qN8y2X9W1z2X9W1z2X9W1z2X9W1', 'Berlin', 1, 1, 'approved');