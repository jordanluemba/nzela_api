-- Script de migration pour ajouter le système d'administration
-- Phase 1 : Modification de la base de données

-- 1. Ajouter les colonnes pour les rôles dans la table users
ALTER TABLE users 
ADD COLUMN role ENUM('citoyen', 'admin', 'superadmin') DEFAULT 'citoyen' AFTER email,
ADD COLUMN permissions JSON DEFAULT NULL AFTER role,
ADD COLUMN created_by INT DEFAULT NULL AFTER permissions,
ADD COLUMN last_activity DATETIME DEFAULT NULL AFTER last_login;

-- 2. Ajouter une clé étrangère pour created_by
ALTER TABLE users 
ADD CONSTRAINT fk_users_created_by 
FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

-- 3. Créer un super administrateur par défaut
-- Mot de passe: admin123 (à changer en production)
INSERT INTO users (email, password_hash, first_name, last_name, role, is_active, created_at) 
VALUES (
    'admin@nzela.local', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- admin123
    'Super', 
    'Administrateur', 
    'superadmin', 
    1, 
    NOW()
);

-- 4. Ajouter index pour optimiser les requêtes
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_active_role ON users(is_active, role);
CREATE INDEX idx_users_last_activity ON users(last_activity);

-- 5. Modifier la table signalements pour améliorer la gestion admin
ALTER TABLE signalements 
ADD COLUMN assigned_to INT DEFAULT NULL AFTER statut,
ADD COLUMN admin_notes TEXT DEFAULT NULL AFTER assigned_to,
ADD COLUMN resolved_at DATETIME DEFAULT NULL AFTER admin_notes,
ADD COLUMN resolved_by INT DEFAULT NULL AFTER resolved_at;

-- 6. Ajouter les clés étrangères pour le suivi admin
ALTER TABLE signalements 
ADD CONSTRAINT fk_signalements_assigned_to 
FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_signalements_resolved_by 
FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL;

-- 7. Ajouter index pour les requêtes admin
CREATE INDEX idx_signalements_assigned ON signalements(assigned_to);
CREATE INDEX idx_signalements_statut_assigned ON signalements(statut, assigned_to);
CREATE INDEX idx_signalements_resolved ON signalements(resolved_by, resolved_at);

-- 8. Créer la table pour les sessions admin (optionnel, pour sécurité renforcée)
CREATE TABLE admin_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(128) UNIQUE NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin_sessions_token (session_token),
    INDEX idx_admin_sessions_user (user_id, is_active),
    INDEX idx_admin_sessions_expires (expires_at)
);

-- 9. Créer la table pour l'audit des actions admin
CREATE TABLE admin_audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50) NOT NULL, -- 'user', 'signalement', 'type', etc.
    target_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_audit_admin (admin_id),
    INDEX idx_audit_target (target_type, target_id),
    INDEX idx_audit_date (created_at)
);

-- 10. Mettre à jour les types de signalement pour la gestion admin
ALTER TABLE types_signalement 
ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER description,
ADD COLUMN created_by INT DEFAULT NULL AFTER is_active,
ADD COLUMN updated_by INT DEFAULT NULL AFTER created_by,
ADD COLUMN updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER updated_by;

-- 11. Ajouter les clés étrangères pour les types
ALTER TABLE types_signalement 
ADD CONSTRAINT fk_types_created_by 
FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_types_updated_by 
FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL;

-- 12. Ajouter index pour les types
CREATE INDEX idx_types_active ON types_signalement(is_active);
CREATE INDEX idx_types_created_by ON types_signalement(created_by);

COMMIT;