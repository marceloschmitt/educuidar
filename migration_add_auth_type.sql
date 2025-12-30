-- Migration: Add auth_type field to users table
-- This allows users to have either local password or LDAP authentication

ALTER TABLE users 
ADD COLUMN auth_type ENUM('local', 'ldap') NOT NULL DEFAULT 'local' 
AFTER password;

-- Update existing users based on user_type
-- Administradores default to local, nivel1 and nivel2 default to ldap
UPDATE users SET auth_type = 'local' WHERE user_type = 'administrador';
UPDATE users SET auth_type = 'ldap' WHERE user_type IN ('nivel1', 'nivel2');

