<?php
/**
 * Setup Admin User
 * Script para crear el primer usuario administrador
 *
 * INSTRUCCIONES:
 * 1. Ejecuta este script una vez: php setup-admin-user.php
 * 2. O abre en el navegador: https://tudominio.com/setup-admin-user.php
 * 3. El script creará el directorio y el usuario admin por defecto
 * 4. IMPORTANTE: Elimina este script después de usarlo por seguridad
 */

require_once __DIR__ . '/includes/functions.php';

// Verificar si el usuario ya existe
$users_file = __DIR__ . '/data/passwords/users.json';

if (file_exists($users_file)) {
    $users_data = read_json($users_file);
    if (!empty($users_data['users'])) {
        die("❌ ERROR: Ya existen usuarios en el sistema. No se pueden crear usuarios duplicados.\n\nSi olvidaste tu contraseña, contacta al administrador del sistema.\n");
    }
}

// Crear directorio si no existe
$passwords_dir = __DIR__ . '/data/passwords';
if (!is_dir($passwords_dir)) {
    mkdir($passwords_dir, 0755, true);
}

// Datos del usuario por defecto
$default_username = 'admin';
$default_password = 'admin123';
$default_email = 'admin@example.com';

// Crear usuario
$users_data = [
    'users' => [
        [
            'id' => 'admin-' . uniqid(),
            'username' => $default_username,
            'password' => password_hash($default_password, PASSWORD_DEFAULT),
            'email' => $default_email,
            'role' => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
            'last_login' => null
        ]
    ]
];

// Guardar
if (write_json($users_file, $users_data)) {
    echo "✅ Usuario administrador creado exitosamente!\n\n";
    echo "========================================\n";
    echo "CREDENCIALES DE ACCESO:\n";
    echo "========================================\n";
    echo "URL: " . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] . url('/admin/') : '/admin/') . "\n";
    echo "Usuario: {$default_username}\n";
    echo "Contraseña: {$default_password}\n";
    echo "========================================\n\n";
    echo "⚠️  IMPORTANTE:\n";
    echo "1. CAMBIA la contraseña inmediatamente después del primer login\n";
    echo "2. ELIMINA este script (setup-admin-user.php) por seguridad\n";
    echo "3. Guarda las credenciales en un lugar seguro\n\n";
} else {
    echo "❌ ERROR: No se pudo crear el archivo de usuarios.\n";
    echo "Verifica los permisos del directorio data/passwords/\n";
}
