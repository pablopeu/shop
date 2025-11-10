<?php
/**
 * Debug Login - Test Authentication
 * Script para diagnosticar problemas de login
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

echo "🔍 DEBUG LOGIN - SISTEMA DE AUTENTICACIÓN\n";
echo str_repeat("=", 60) . "\n\n";

// Verificar que el archivo existe
$users_file = __DIR__ . '/data/passwords/users.json';
echo "1. Verificando archivo de usuarios...\n";
if (!file_exists($users_file)) {
    echo "   ❌ ERROR: El archivo no existe: $users_file\n";
    echo "   Ejecuta: php setup-admin-user.php\n";
    exit(1);
}
echo "   ✅ Archivo existe\n\n";

// Leer usuarios
echo "2. Leyendo archivo de usuarios...\n";
$users_data = read_json($users_file);
if (!isset($users_data['users']) || empty($users_data['users'])) {
    echo "   ❌ ERROR: No hay usuarios en el sistema\n";
    exit(1);
}
echo "   ✅ Usuarios encontrados: " . count($users_data['users']) . "\n\n";

// Mostrar usuarios (sin password)
echo "3. Usuarios en el sistema:\n";
foreach ($users_data['users'] as $user) {
    echo "   - Username: {$user['username']}\n";
    echo "     Email: {$user['email']}\n";
    echo "     Role: {$user['role']}\n";
    echo "     Password Hash: " . substr($user['password'], 0, 20) . "...\n\n";
}

// Test de hash
echo "4. Test de verificación de password:\n";
$test_user = $users_data['users'][0];
$test_password = 'admin123';

echo "   Probando: username='{$test_user['username']}' password='{$test_password}'\n";

// Verificar el hash directamente
$hash_stored = $test_user['password'];
$password_correct = password_verify($test_password, $hash_stored);

echo "   Hash almacenado: " . substr($hash_stored, 0, 30) . "...\n";
echo "   Password ingresado: {$test_password}\n";
echo "   Resultado password_verify(): " . ($password_correct ? "✅ CORRECTO" : "❌ INCORRECTO") . "\n\n";

// Probar función de autenticación completa
echo "5. Test de función authenticate_admin():\n";
$result = authenticate_admin($test_user['username'], $test_password);
echo "   Success: " . ($result['success'] ? "✅ SI" : "❌ NO") . "\n";
echo "   Message: {$result['message']}\n\n";

// Información del servidor
echo "6. Información del servidor PHP:\n";
echo "   PHP Version: " . PHP_VERSION . "\n";
echo "   password_hash disponible: " . (function_exists('password_hash') ? "✅ SI" : "❌ NO") . "\n";
echo "   password_verify disponible: " . (function_exists('password_verify') ? "✅ SI" : "❌ NO") . "\n\n";

// Crear un nuevo hash para comparar
echo "7. Test de creación de nuevo hash:\n";
$new_hash = password_hash($test_password, PASSWORD_DEFAULT);
echo "   Nuevo hash generado: " . substr($new_hash, 0, 30) . "...\n";
$new_verify = password_verify($test_password, $new_hash);
echo "   Verificación del nuevo hash: " . ($new_verify ? "✅ CORRECTO" : "❌ INCORRECTO") . "\n\n";

echo str_repeat("=", 60) . "\n";
echo "RESUMEN:\n";
echo str_repeat("=", 60) . "\n";

if ($password_correct && $result['success']) {
    echo "✅ AUTENTICACIÓN FUNCIONANDO CORRECTAMENTE\n\n";
    echo "Credenciales para login:\n";
    echo "  Usuario: {$test_user['username']}\n";
    echo "  Password: {$test_password}\n\n";
    echo "Si aún no puedes hacer login, verifica:\n";
    echo "  1. Que estés escribiendo correctamente las credenciales\n";
    echo "  2. Que el navegador no tenga credenciales guardadas incorrectas\n";
    echo "  3. Que no haya espacios al inicio o final del usuario/password\n";
} else {
    echo "❌ PROBLEMA DETECTADO EN LA AUTENTICACIÓN\n\n";
    echo "Solución:\n";
    echo "  1. Elimina el archivo: data/passwords/users.json\n";
    echo "  2. Ejecuta nuevamente: php setup-admin-user.php\n";
    echo "  3. Ejecuta este test nuevamente\n";
}
