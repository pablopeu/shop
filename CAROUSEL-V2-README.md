# Carrusel V2 - Documentación

## 📋 Descripción

Esta es una nueva versión mejorada del carrusel de imágenes para el sitio de e-commerce. El Carrusel V2 ofrece una experiencia más moderna y fluida con animaciones mejoradas y mejor presentación visual.

## ✨ Características Principales

### Mejoras sobre la versión anterior:

1. **Auto-rotación optimizada**: Las imágenes se desplazan suavemente hacia la izquierda en orden secuencial
2. **Título visible**: El nombre de cada imagen aparece en la esquina inferior derecha con un diseño moderno
3. **Indicadores mejorados**: Los puntos debajo del carrusel tienen un diseño tipo "píldora" cuando están activos (más anchos)
4. **Links funcionales**: Click en cualquier parte de la imagen lleva al producto/página configurada
5. **Pausa inteligente**: El carrusel se pausa automáticamente al pasar el mouse
6. **Navegación completa**:
   - Navegación con teclado (flechas izquierda/derecha)
   - Click en puntos indicadores
   - Auto-rotación configurable
7. **Optimización de recursos**: Pausa cuando la pestaña no está visible para ahorrar rendimiento

## 📁 Archivos del Carrusel V2

Los siguientes archivos han sido creados en `/home/pablo/shop/includes/`:

- **carousel-v2.php** - Componente HTML/PHP del carrusel
- **carousel-v2.css** - Estilos y animaciones
- **carousel-v2.js** - Funcionalidad JavaScript

Además:
- **test-carousel-v2.php** - Página de prueba en la raíz del proyecto

## 🧪 Probar el Carrusel V2

Antes de integrar el carrusel en producción, puedes probarlo accediendo a:

```
http://tu-dominio.com/test-carousel-v2.php
```

O localmente:
```
http://localhost/shop/test-carousel-v2.php
```

Esta página muestra el carrusel V2 con toda su funcionalidad para que puedas verificar que funciona correctamente.

## ⚙️ Configuración

El Carrusel V2 utiliza la misma configuración que el carrusel actual, ubicada en:

```
/config/carousel.json
```

Puedes administrar la configuración desde el panel de admin:

```
http://tu-dominio.com/admin/config-carrusel.php
```

### Parámetros de configuración:

- **enabled**: `true/false` - Activa o desactiva el carrusel
- **alignment**: `"center"/"left"/"right"` - Alineación del carrusel
- **auto_advance_time**: Tiempo en milisegundos entre transiciones (ej: 5000 = 5 segundos)
- **slides**: Array de objetos con las imágenes y sus propiedades
  - **image**: Ruta de la imagen
  - **title**: Nombre que aparece en la esquina inferior derecha
  - **subtitle**: Subtítulo (no usado en V2, reservado para futuras versiones)
  - **link**: URL a la que lleva el click en la imagen

## 🔄 Cómo Reemplazar el Carrusel Actual

### Opción 1: Reemplazo Directo (Recomendado)

Una vez que hayas probado el Carrusel V2 y estés satisfecho, puedes reemplazar los archivos actuales:

```bash
cd /home/pablo/shop/includes/

# Hacer backup del carrusel actual
mv carousel.php carousel-v1-backup.php
mv carousel.css carousel-v1-backup.css
mv carousel.js carousel-v1-backup.js

# Renombrar el carrusel V2 como principal
mv carousel-v2.php carousel.php
mv carousel-v2.css carousel.css
mv carousel-v2.js carousel.js
```

**Ventaja**: No necesitas modificar ningún archivo, todo sigue funcionando automáticamente.

### Opción 2: Integración Manual

Si prefieres mantener ambas versiones, edita `index.php`:

1. **Línea 58** - Cambiar la referencia al CSS:
```php
<!-- Antes -->
<link rel="stylesheet" href="/includes/carousel.css">

<!-- Después -->
<link rel="stylesheet" href="/includes/carousel-v2.css">
```

2. **Línea 635** - Cambiar el include del PHP:
```php
<!-- Antes -->
<?php include __DIR__ . '/includes/carousel.php'; ?>

<!-- Después -->
<?php include __DIR__ . '/includes/carousel-v2.php'; ?>
```

3. **Línea 1001** - Cambiar la referencia al JS:
```php
<!-- Antes -->
<script src="/includes/carousel.js"></script>

<!-- Después -->
<script src="/includes/carousel-v2.js"></script>
```

### Opción 3: Mantener Ambos y Elegir por Configuración

Puedes crear un sistema que permita elegir qué versión usar desde la configuración. Contacta con el desarrollador para implementar esta opción.

## 🎨 Personalización

### Cambiar colores del punto activo

Edita `/includes/carousel-v2.css`, línea 125:

```css
.carousel-v2-dot.active {
    background: #667eea; /* Cambia este color */
    width: 32px;
    border-radius: 5px;
}
```

### Cambiar duración de la animación

Edita `/includes/carousel-v2.css`, líneas 72-74 y línea 106:

```css
/* Cambiar el valor de 0.6s a tu preferencia */
.carousel-v2-slide.slide-out {
    animation: slideOutToLeft 0.6s ease-in-out forwards;
}

.carousel-v2-slide.slide-in {
    animation: slideInFromRight 0.6s ease-in-out forwards;
}
```

También actualiza en `/includes/carousel-v2.js`, línea 106:

```javascript
setTimeout(() => {
    // ...código...
}, 600); // Cambiar 600ms al valor que prefieras (debe coincidir con el CSS)
```

### Cambiar estilo del título

Edita `/includes/carousel-v2.css`, líneas 107-119:

```css
.carousel-v2-title {
    position: absolute;
    bottom: 20px;
    right: 20px;
    background: rgba(0, 0, 0, 0.75);
    color: white;
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 18px;
    font-weight: 600;
    /* Personaliza estos valores */
}
```

## 🐛 Solución de Problemas

### El carrusel no se muestra

1. Verifica que el carrusel esté habilitado en `/admin/config-carrusel.php`
2. Asegúrate de que haya al menos una imagen configurada
3. Revisa la consola del navegador (F12) en busca de errores

### Las imágenes no rotan automáticamente

1. Verifica que `auto_advance_time` esté configurado en `/config/carousel.json`
2. Abre la consola del navegador y busca mensajes que digan `[CAROUSEL V2]`
3. El carrusel se pausa al pasar el mouse - mueve el mouse fuera del carrusel

### Los links no funcionan

1. Verifica que cada slide tenga un `link` configurado en `/config/carousel.json`
2. Los links se configuran desde el panel de admin

### Los títulos no aparecen

1. Verifica que cada slide tenga un `title` configurado en `/config/carousel.json`
2. Los títulos se pueden editar desde el panel de admin

## 📝 Notas Técnicas

- El carrusel es completamente responsive y se adapta a móviles, tablets y desktop
- Utiliza animaciones CSS3 para un rendimiento óptimo
- El JavaScript es vanilla (sin dependencias externas)
- Compatible con todos los navegadores modernos
- La configuración se mantiene igual que la versión anterior

## 🔮 Próximas Mejoras Planeadas

- [ ] Modo de transición personalizable (fade, slide, zoom)
- [ ] Soporte para videos además de imágenes
- [ ] Preview de miniaturas en los puntos
- [ ] Lazy loading de imágenes
- [ ] Efectos de parallax

## 📞 Soporte

Si encuentras algún problema o tienes sugerencias de mejora, contacta con el desarrollador del sistema.

---

**Versión**: 2.0
**Fecha**: 2025
**Compatibilidad**: PHP 7.4+, navegadores modernos
