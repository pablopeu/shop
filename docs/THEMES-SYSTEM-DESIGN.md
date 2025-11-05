# 🎨 Sistema de Themes - Diseño de Arquitectura

**Versión:** 1.0
**Fecha:** 2025-11-05
**Rama:** feature/themes
**Estado:** Diseño en progreso

---

## 📊 Análisis del Estado Actual

### ✅ Lo que Ya Existe

1. **Estructura básica de themes:**
   - `/config/theme.json` - Configuración del theme activo
   - `/themes/{theme_name}/` - Directorios preparados (vacíos)
   - Themes disponibles: minimal, elegant, fresh, bold

2. **Carga dinámica en páginas:**
   - `index.php`
   - `producto.php`
   - `carrito.php`
   - `checkout.php`
   - `buscar.php`
   - `favoritos.php`
   - `track.php`
   - `pedido.php`
   - `gracias.php`

3. **Componentes con CSS separado:**
   - `/includes/carousel.css`
   - `/includes/mobile-menu.css`

### ⚠️ Problemas Actuales

1. **CSS inline masivo** - Cientos de líneas de estilos dentro de etiquetas `<style>` en cada página
2. **Duplicación de código** - Los mismos estilos repetidos en múltiples páginas
3. **Themes vacíos** - Los directorios existen pero no hay archivos CSS
4. **Sin variables** - Colores y valores hardcodeados en todo el código
5. **Sin documentación** - No hay guía de cómo crear o modificar themes

---

## 🎯 Objetivos del Sistema de Themes

### Objetivos Principales

1. **Separación de estilos** - Mover todo el CSS inline a archivos de theme
2. **Personalización fácil** - Cambiar colores, tipografías y estilos desde variables
3. **Themes predefinidos** - Múltiples opciones visuales listas para usar
4. **Escalabilidad** - Fácil agregar nuevos themes sin modificar código
5. **Mantenibilidad** - Un solo lugar para cada estilo

### Objetivos Secundarios

1. **Admin interface** - Selector de themes desde el panel de administración
2. **Preview** - Vista previa de themes antes de activarlos
3. **Customización** - Permitir ajustes personalizados sobre themes base
4. **Responsive** - Todos los themes deben funcionar en móviles

---

## 🏗️ Arquitectura Propuesta

### 1. Estructura de Directorios

```
/themes/
├── _base/                    # Estilos base compartidos
│   ├── reset.css            # CSS reset/normalize
│   ├── layout.css           # Grid, containers, layout básico
│   ├── components.css       # Componentes comunes
│   └── utilities.css        # Clases de utilidad
│
├── minimal/                  # Theme minimal (actual)
│   ├── theme.json           # Configuración del theme
│   ├── theme.css            # Estilos principales
│   ├── variables.css        # Variables CSS del theme
│   └── preview.jpg          # Imagen de preview
│
├── elegant/                  # Theme elegant
│   ├── theme.json
│   ├── theme.css
│   ├── variables.css
│   └── preview.jpg
│
├── fresh/                    # Theme fresh
│   ├── theme.json
│   ├── theme.css
│   ├── variables.css
│   └── preview.jpg
│
└── bold/                     # Theme bold
    ├── theme.json
    ├── theme.css
    ├── variables.css
    └── preview.jpg
```

### 2. Sistema de Cascada de Estilos

```
Orden de carga (de general a específico):

1. /themes/_base/reset.css          ← Reset/normalize
2. /themes/_base/layout.css         ← Layout básico
3. /themes/_base/components.css     ← Componentes base
4. /themes/{active}/variables.css   ← Variables del theme
5. /themes/{active}/theme.css       ← Estilos del theme
6. /includes/carousel.css           ← Componentes específicos
7. /includes/mobile-menu.css        ← Componentes específicos
8. [Estilos inline específicos]     ← Solo para casos muy particulares
```

### 3. Sistema de Variables CSS

Cada theme define sus variables en `variables.css`:

```css
:root {
    /* Colores Principales */
    --color-primary: #667eea;
    --color-primary-dark: #5568d3;
    --color-primary-light: #7c92f0;

    --color-secondary: #764ba2;
    --color-secondary-dark: #5f3a7e;
    --color-secondary-light: #8d5cb5;

    /* Colores de Estado */
    --color-success: #4caf50;
    --color-warning: #ff9800;
    --color-error: #f44336;
    --color-info: #2196f3;

    /* Colores Neutros */
    --color-text: #333333;
    --color-text-light: #666666;
    --color-text-lighter: #999999;

    --color-bg: #ffffff;
    --color-bg-light: #f5f5f5;
    --color-bg-dark: #e0e0e0;

    --color-border: #e0e0e0;
    --color-shadow: rgba(0, 0, 0, 0.1);

    /* Tipografía */
    --font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    --font-family-heading: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;

    --font-size-xs: 12px;
    --font-size-sm: 14px;
    --font-size-base: 16px;
    --font-size-lg: 18px;
    --font-size-xl: 20px;
    --font-size-2xl: 24px;
    --font-size-3xl: 32px;
    --font-size-4xl: 48px;

    --font-weight-normal: 400;
    --font-weight-medium: 500;
    --font-weight-semibold: 600;
    --font-weight-bold: 700;

    --line-height-tight: 1.2;
    --line-height-normal: 1.5;
    --line-height-relaxed: 1.8;

    /* Espaciado */
    --spacing-xs: 4px;
    --spacing-sm: 8px;
    --spacing-md: 16px;
    --spacing-lg: 24px;
    --spacing-xl: 32px;
    --spacing-2xl: 48px;
    --spacing-3xl: 64px;

    /* Bordes */
    --border-radius-sm: 4px;
    --border-radius-md: 6px;
    --border-radius-lg: 12px;
    --border-radius-xl: 16px;
    --border-radius-full: 9999px;

    --border-width: 1px;
    --border-width-thick: 2px;

    /* Sombras */
    --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
    --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.15);

    /* Transiciones */
    --transition-fast: 0.15s ease;
    --transition-base: 0.3s ease;
    --transition-slow: 0.5s ease;

    /* Breakpoints (para uso en JS) */
    --breakpoint-mobile: 480px;
    --breakpoint-tablet: 768px;
    --breakpoint-desktop: 1024px;
    --breakpoint-wide: 1200px;
}
```

### 4. Esquema de theme.json

Cada theme tiene un archivo de configuración:

```json
{
    "name": "Minimal",
    "slug": "minimal",
    "version": "1.0.0",
    "description": "Un diseño limpio y minimalista con énfasis en la simplicidad",
    "author": "Shop Team",
    "preview_image": "/themes/minimal/preview.jpg",

    "features": {
        "dark_mode": false,
        "animations": "subtle",
        "border_style": "rounded",
        "shadow_style": "soft"
    },

    "colors": {
        "primary": "#667eea",
        "secondary": "#764ba2",
        "success": "#4caf50",
        "warning": "#ff9800",
        "error": "#f44336"
    },

    "typography": {
        "font_family": "system-ui",
        "heading_weight": "600",
        "base_size": "16px"
    },

    "compatibility": {
        "requires_php": "7.4",
        "requires_css": "3",
        "mobile_optimized": true,
        "rtl_support": false
    },

    "created_at": "2025-11-05",
    "updated_at": "2025-11-05"
}
```

---

## 📝 Configuración Global Extendida

### /config/theme.json (actualizado)

```json
{
    "active_theme": "minimal",
    "allow_theme_switching": true,
    "custom_overrides": {
        "enabled": false,
        "primary_color": null,
        "font_family": null
    },
    "cache": {
        "enabled": true,
        "ttl": 3600
    },
    "updated_at": "2025-11-05T00:00:00Z",
    "updated_by": "admin"
}
```

---

## 🔧 Componentes del Sistema

### 1. Loader de Themes

**Ubicación:** `/includes/theme-loader.php`

Funcionalidad:
- Leer configuración de theme activo
- Cargar archivos CSS en orden correcto
- Inyectar variables CSS personalizadas
- Cache de configuración

### 2. Theme Manager (Admin)

**Ubicación:** `/admin/themes.php`

Funcionalidad:
- Listar todos los themes disponibles
- Preview de themes
- Activar/desactivar themes
- Personalización de variables
- Upload de themes personalizados

### 3. Theme Validator

**Ubicación:** `/includes/theme-validator.php`

Funcionalidad:
- Validar estructura de themes
- Verificar archivos requeridos
- Validar theme.json
- Reportar errores

---

## 🎨 Themes Predefinidos

### 1. Minimal (Default)

**Descripción:** Diseño limpio y minimalista
**Colores:** Azul/Púrpura suave
**Estilo:** Moderno, espacioso, simple
**Target:** E-commerce general, productos premium

### 2. Elegant

**Descripción:** Diseño sofisticado y elegante
**Colores:** Negro/Dorado
**Estilo:** Lujo, premium, serif fonts
**Target:** Joyería, moda, productos de lujo

### 3. Fresh

**Descripción:** Diseño vibrante y energético
**Colores:** Verde/Naranja brillante
**Estilo:** Fresco, juvenil, bold
**Target:** Productos orgánicos, comida, lifestyle

### 4. Bold

**Descripción:** Diseño atrevido e impactante
**Colores:** Rojo/Negro
**Estilo:** Fuerte, contrastante, llamativo
**Target:** Tecnología, deportes, gaming

---

## 🚀 Plan de Implementación

### Fase 1: Fundación (Preparación)
1. ✅ Crear estructura de directorios _base/
2. ✅ Extraer CSS común a archivos base
3. ✅ Crear sistema de variables CSS
4. ✅ Actualizar theme.json schema

### Fase 2: Theme Minimal (Migración)
1. ⏳ Crear minimal/variables.css
2. ⏳ Crear minimal/theme.css
3. ⏳ Crear minimal/theme.json
4. ⏳ Migrar CSS inline de todas las páginas
5. ⏳ Testing completo

### Fase 3: Themes Adicionales
1. ⏳ Implementar theme Elegant
2. ⏳ Implementar theme Fresh
3. ⏳ Implementar theme Bold
4. ⏳ Crear previews de cada theme

### Fase 4: Sistema de Administración
1. ⏳ Crear theme-loader.php
2. ⏳ Crear admin/themes.php
3. ⏳ Implementar selector de themes
4. ⏳ Implementar preview de themes
5. ⏳ Sistema de personalización

### Fase 5: Optimización
1. ⏳ Sistema de cache
2. ⏳ Minificación de CSS
3. ⏳ Testing de performance
4. ⏳ Documentación final

---

## 📏 Estándares y Convenciones

### Nomenclatura de Clases CSS

Usar metodología BEM (Block Element Modifier):

```css
/* Bloque */
.product-card { }

/* Elemento */
.product-card__image { }
.product-card__title { }
.product-card__price { }

/* Modificador */
.product-card--featured { }
.product-card--out-of-stock { }
```

### Organización de Archivos CSS

```css
/* 1. Variables y configuración */
@import 'variables.css';

/* 2. Reset y base */
* { box-sizing: border-box; }

/* 3. Layout */
.container { }
.grid { }

/* 4. Componentes */
.header { }
.nav { }
.button { }

/* 5. Páginas específicas */
.home { }
.product-detail { }

/* 6. Utilities */
.text-center { }
.mt-4 { }

/* 7. Media queries */
@media (max-width: 768px) { }
```

### Comentarios en CSS

```css
/* ===================================
   SECCIÓN PRINCIPAL
   =================================== */

/* Subsección
   ----------------------------- */

/* Comentario de una línea */

/**
 * Comentario de bloque
 * para explicaciones largas
 */
```

---

## 🔍 Consideraciones Técnicas

### Performance

1. **CSS Crítico:** Inline solo estilos above-the-fold
2. **Lazy Loading:** Cargar estilos no críticos de forma diferida
3. **Minificación:** Todos los CSS deben minificarse en producción
4. **Cache:** Cache de 1 hora para archivos de theme

### Compatibilidad

1. **Navegadores:** Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
2. **Móviles:** iOS 14+, Android 10+
3. **CSS Features:** Variables CSS, Grid, Flexbox, Custom Properties

### Accesibilidad

1. **Contraste:** Mínimo 4.5:1 para texto normal
2. **Focus States:** Visibles en todos los elementos interactivos
3. **Screen Readers:** Clases de utilidad no afectan semántica

---

## 📚 Referencias y Recursos

### Inspiración de Diseño
- Material Design
- Tailwind CSS
- Bootstrap 5
- Apple Human Interface Guidelines

### Herramientas
- CSS Variables
- PostCSS (futuro)
- CSS Grid & Flexbox
- Media Queries

---

## ✅ Checklist de Validación

Cada theme debe cumplir:

- [ ] Incluye theme.json válido
- [ ] Incluye variables.css con todas las variables requeridas
- [ ] Incluye theme.css con todos los estilos
- [ ] Incluye preview.jpg (1200x800px)
- [ ] Funciona en móviles
- [ ] Funciona en tablets
- [ ] Funciona en desktop
- [ ] Pasa validación de contraste WCAG AA
- [ ] No tiene errores de CSS
- [ ] Compatible con todos los componentes
- [ ] Documentado en README

---

**Próximo Paso:** Implementar Fase 1 - Fundación

