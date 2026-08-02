<?php
/**
 * Script para gerar hashes de senha para os usuários padrão
 * Execute: php generate_user_hashes.php
 */

echo "=== Gerador de Hashes de Senha ===\n\n";
echo "Hash para 'usuario1':\n";
echo password_hash('usuario1', PASSWORD_DEFAULT) . "\n\n";
echo "Hash para 'usuario2':\n";
echo password_hash('usuario2', PASSWORD_DEFAULT) . "\n\n";
echo "Copie esses hashes para database.sql e migrate_user_types.sql\n";
?>

