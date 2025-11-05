# 🎨 Sistema de Themes

Este directorio contiene el sistema de themes para el sitio e-commerce.

## 📁 Estructura

```
/themes/
├── _base/                    # Estilos base compartidos
│   ├── reset.css            # Reset/normalize CSS
│   ├── layout.css           # Sistema de layout (grid, flex)
│   ├── components.css       # Componentes reutilizables
│   └── utilities.css        # Clases de utilidad
│
├── minimal/                  # Theme minimal
│   ├── theme.json           # Configuración del theme
│   ├── variables.css        # Variables CSS (~60 variables)
│   ├── theme.css            # Estilos específicos
│   └── preview.jpg          # Imagen de preview (pendiente)
│
├── elegant/                  # Theme elegant
├── fresh/                    # Theme fresh
├── bold/                     # Theme bold
└── README.md                # Este archivo
```

## 🚀 Uso

### En páginas PHP

```php
<?php
// Incluir el theme loader
require_once __DIR__ . '/includes/theme-loader.php';

// Leer configuración de theme activo
$theme_config = read_json(__DIR__ . '/config/theme.json');
$active_theme = $theme_config['active_theme'] ?? 'minimal';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Mi Página</title>

    <!-- Cargar CSS del theme -->
    <?php render_theme_css($active_theme); ?>

    <!-- CSS adicionales específicos de la página -->
    <link rel="stylesheet" href="/includes/carousel.css">
</head>
<body>
    <!-- Contenido -->
</body>
</html>
```

### Orden de Carga

Los archivos CSS se cargan en este orden:

1. `/themes/_base/reset.css` - Reset CSS
2. `/themes/_base/layout.css` - Layout base
3. `/themes/_base/components.css` - Componentes base
4. `/themes/_base/utilities.css` - Utilidades
5. `/themes/{active}/variables.css` - Variables del theme
6. `/themes/{active}/theme.css` - Estilos del theme
7. Componentes específicos (carousel, mobile-menu, etc.)

## 🎨 Themes Disponibles

### Minimal
- **Descripción:** Diseño limpio y minimalista
- **Colores:** Azul/Púrpura (#667eea)
- **Uso:** E-commerce general, productos premium

### Elegant
- **Descripción:** Diseño sofisticado y elegante
- **Colores:** Negro/Dorado (#000, #d4af37)
- **Uso:** Joyería, moda de lujo

### Fresh
- **Descripción:** Diseño vibrante y energético
- **Colores:** Verde/Naranja (#4caf50, #ff9800)
- **Uso:** Productos orgánicos, lifestyle

### Bold
- **Descripción:** Diseño atrevido e impactante
- **Colores:** Rojo/Negro (#e74c3c, #000)
- **Uso:** Tecnología, gaming, deportes

## 📝 Variables CSS

Cada theme define ~60 variables CSS en `variables.css`:

```css
:root {
    /* Colores */
    --color-primary: #667eea;
    --color-secondary: #764ba2;
    --color-text: #333333;

    /* Tipografía */
    --font-family: system-ui;
    --font-size-base: 16px;

    /* Espaciado */
    --spacing-md: 16px;

    /* Bordes */
    --border-radius-md: 6px;

    /* Sombras */
    --shadow-md: 0 4px 6px rgba(0,0,0,0.1);

    /* Y 50+ variables más... */
}
```

## 🔧 Crear un Nuevo Theme

1. **Crear directorio:**
   ```bash
   mkdir themes/mi-theme
   ```

2. **Crear archivos requeridos:**
   - `theme.json` - Configuración
   - `variables.css` - Variables CSS
   - `theme.css` - Estilos específicos
   - `preview.jpg` - Imagen de preview (1200x800px)

3. **Copiar plantilla:**
   ```bash
   cp themes/minimal/theme.json themes/mi-theme/
   cp themes/minimal/variables.css themes/mi-theme/
   cp themes/minimal/theme.css themes/mi-theme/
   ```

4. **Personalizar:**
   - Editar `theme.json` con nombre, descripción, etc.
   - Modificar variables en `variables.css`
   - Agregar estilos específicos en `theme.css`

5. **Activar:**
   - Cambiar `active_theme` en `/config/theme.json`
   - O usar el selector de themes desde el admin

## ✅ Validación

Cada theme debe incluir:

- [ ] `theme.json` válido con todos los campos requeridos
- [ ] `variables.css` con todas las variables estándar
- [ ] `theme.css` con estilos específicos
- [ ] `preview.jpg` (1200x800px, opcional pero recomendado)
- [ ] Compatible con móviles, tablets y desktop
- [ ] Contraste WCAG AA (mínimo 4.5:1)

## 📚 Referencias

- [Diseño Completo](/docs/THEMES-SYSTEM-DESIGN.md)
- [Resumen Ejecutivo](/docs/THEMES-SYSTEM-SUMMARY.md)
- [Variables Reference](/docs/THEMES-VARIABLES-REFERENCE.md) (pendiente)

## 🔍 Debugging

### Ver theme activo:
```php
<?php
$config = read_json('config/theme.json');
echo "Theme activo: " . $config['active_theme'];
?>
```

### Listar todos los themes:
```php
<?php
require_once 'includes/theme-loader.php';
$themes = get_available_themes();
print_r($themes);
?>
```

### Validar un theme:
```php
<?php
require_once 'includes/theme-loader.php';
$validation = validate_theme('minimal');
print_r($validation);
?>
```

---

**Versión:** 1.0.0
**Fecha:** 2025-11-05
**Estado:** ✅ Estructura base completada
