# 🔧 Configuración para Subdirectorio (peu.net/shop/)

Tu sitio está en un subdirectorio (`https://peu.net/shop/`) y necesita configuración especial para que las rutas funcionen correctamente.

## ✅ Ya Configurado

1. ✅ **config.php** - Define `BASE_PATH = '/shop'`
2. ✅ **Función `url()`** - Genera URLs correctas automáticamente
3. ✅ **Función `redirect()`** - Redirects con BASE_PATH
4. ✅ **index.php** - CSS y navegación actualizados

## 🚀 Cómo Usar

### En HTML/PHP:

**❌ INCORRECTO (No funciona en subdirectorio):**
```php
<link rel="stylesheet" href="/css/style.css">
<a href="/productos.php">Productos</a>
```

**✅ CORRECTO:**
```php
<link rel="stylesheet" href="<?php echo url('/css/style.css'); ?>">
<a href="<?php echo url('/productos.php'); ?>">Productos</a>
```

### En Redirects PHP:

**❌ INCORRECTO:**
```php
header('Location: /admin/dashboard.php');
```

**✅ CORRECTO:**
```php
redirect('/admin/dashboard.php');
// o
header('Location: ' . url('/admin/dashboard.php'));
```

## 📁 Archivos que Necesitan Actualización

Estos archivos tienen rutas absolutas que deben actualizarse:

###  Archivos Públicos:
- [ ] buscar.php
- [ ] producto.php
- [ ] carrito.php
- [ ] checkout.php
- [ ] favoritos.php
- [ ] track.php
- [ ] pedido.php
- [ ] gracias.php
- [ ] error.php

### Archivos Admin:
- [ ] admin/index.php
- [ ] admin/login.php
- [ ] admin/productos-*.php
- [ ] admin/cupones-*.php
- [ ] admin/config-*.php
- [ ] admin/includes/header.php
- [ ] admin/includes/sidebar.php

## 🔍 Buscar Rutas Problemáticas

Ejecuta este comando para encontrar archivos con rutas absolutas:

```bash
grep -r 'href="/' --include="*.php" . | grep -v 'http'
grep -r 'src="/' --include="*.php" .
grep -r "Location: /" --include="*.php" .
```

## 🛠️ Actualización Rápida

Para cada archivo problemático:

1. **Abrir el archivo**
2. **Buscar** todas las ocurrencias de:
   - `href="/..."`
   - `src="/..."`
   - `action="/..."`
   - `header('Location: /...`

3. **Reemplazar** usando `url()`:
   ```php
   // Antes
   href="/admin/productos.php"

   // Después
   href="<?php echo url('/admin/productos.php'); ?>"
   ```

4. **Para redirects**, usar la función `redirect()`:
   ```php
   // Antes
   header('Location: /admin/dashboard.php');
   exit;

   // Después
   redirect('/admin/dashboard.php');
   ```

## ⚙️ Ajustar BASE_PATH

Si cambias de ubicación, edita `config.php`:

```php
// Para raíz del dominio
define('BASE_PATH', '');

// Para subdirectorio
define('BASE_PATH', '/shop');  // ← Tu configuración actual

// Para otro subdirectorio
define('BASE_PATH', '/tienda');
```

## 🧪 Probar

Después de actualizar archivos:

1. Visita: `https://peu.net/shop/`
2. Verifica que:
   - ✅ Los CSS cargan
   - ✅ La navegación funciona
   - ✅ El admin es accesible
   - ✅ Los redirects funcionan

## 📝 Notas

- **NO incluir `/` al final** de BASE_PATH
- **SÍ incluir `/` al inicio** de BASE_PATH
- La función `url()` maneja automáticamente las `/`
- Para rutas relativas (sin `/`), úsalas directamente

## 🔗 Enlaces Útimos

- `config.php` - Configuración de BASE_PATH
- `includes/functions.php` - Carga config.php

---

**¿Problemas?** Verifica que `config.php` esté incluido en todos los archivos que usan `url()`.
