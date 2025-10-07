# Base de données NZELA - Script d'initialisation pour Render

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Base de données: `nzela_db`
--

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_province` (`province`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `types_signalements`
--

CREATE TABLE `types_signalements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `description` text,
  `icone` varchar(50) DEFAULT NULL,
  `couleur` varchar(7) DEFAULT '#007bff',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Données pour la table `types_signalements`
--

INSERT INTO `types_signalements` (`nom`, `description`, `icone`, `couleur`) VALUES
('Infrastructure Routière', 'Problèmes de routes, nids-de-poule, signalisation', 'road', '#e74c3c'),
('Éclairage Public', 'Éclairage défaillant, lampadaires cassés', 'lightbulb', '#f39c12'),
('Gestion des Déchets', 'Collecte des ordures, décharges sauvages', 'trash', '#27ae60'),
('Eau et Assainissement', 'Problèmes d\'eau, canalisations, égouts', 'tint', '#3498db'),
('Sécurité', 'Problèmes de sécurité publique', 'shield', '#9b59b6'),
('Espaces Verts', 'Parcs, jardins, espaces publics', 'tree', '#2ecc71'),
('Transport Public', 'Bus, taxis, circulation', 'bus', '#34495e'),
('Autres', 'Autres types de signalements', 'plus', '#95a5a6');

-- --------------------------------------------------------

--
-- Structure de la table `signalements`
--

CREATE TABLE `signalements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `type_signalement_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `province` varchar(100) NOT NULL,
  `ville` varchar(100) DEFAULT NULL,
  `commune` varchar(100) DEFAULT NULL,
  `quartier` varchar(100) DEFAULT NULL,
  `nom_rue` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `description` text NOT NULL,
  `urgence` enum('Faible','Moyen','Urgent','Critique') DEFAULT 'Moyen',
  `circulation` enum('Normale','Ralentie','Partiellement','Bloquée') DEFAULT 'Normale',
  `statut` enum('En attente','En cours','Résolu','Rejeté') DEFAULT 'En attente',
  `nom_citoyen` varchar(255) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email_citoyen` varchar(255) DEFAULT NULL,
  `photo_url` varchar(500) DEFAULT NULL,
  `commentaire_admin` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resolved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `type_signalement_id` (`type_signalement_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_province` (`province`),
  KEY `idx_statut` (`statut`),
  KEY `idx_urgence` (`urgence`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `signalements_ibfk_1` FOREIGN KEY (`type_signalement_id`) REFERENCES `types_signalements` (`id`),
  CONSTRAINT `signalements_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Index et contraintes supplémentaires
--

-- Trigger pour générer automatiquement le code de signalement
DELIMITER $$
CREATE TRIGGER `generate_signalement_code` BEFORE INSERT ON `signalements` FOR EACH ROW 
BEGIN
    IF NEW.code IS NULL OR NEW.code = '' THEN
        SET NEW.code = CONCAT('NZELA-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(LAST_INSERT_ID() + 1, 4, '0'));
    END IF;
END$$
DELIMITER ;

-- Vue pour les statistiques
CREATE VIEW `v_signalements_stats` AS 
SELECT 
    t.nom as type_name,
    s.statut,
    s.urgence,
    s.province,
    COUNT(*) as total,
    AVG(CASE WHEN s.resolved_at IS NOT NULL THEN 
        TIMESTAMPDIFF(HOUR, s.created_at, s.resolved_at) 
    END) as avg_resolution_hours
FROM signalements s
JOIN types_signalements t ON s.type_signalement_id = t.id
WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY t.nom, s.statut, s.urgence, s.province;

COMMIT;