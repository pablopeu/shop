# Tracking & Analytics - Guía de Integración

Este documento explica cómo está implementado el sistema de tracking y analytics en la tienda.

## Archivos Creados

### Configuración
- **`config/analytics.json`** - Almacena la configuración de Google Analytics, Facebook Pixel y Google Tag Manager

### Admin
- **`admin/config-analytics.php`** - Página de configuración en el backoffice para gestionar los IDs de tracking

### Includes
- **`includes/tracking-scripts.php`** - Scripts para incluir en el `<head>` de las páginas públicas
- **`includes/tracking-body.php`** - Scripts para incluir después del `<body>` (GTM noscript)
- **`includes/tracking-events.php`** - Funciones helper para trackear eventos personalizados

## Cómo Configurar

1. Ir a **Admin → Configuración → Tracking & Analytics** (`/admin/config-analytics.php`)

2. **Google Analytics 4 (GA4)**
   - Habilitar checkbox
   - Ingresar Measurement ID (formato: `G-XXXXXXXXXX`)
   - Guardar

3. **Facebook Pixel**
   - Habilitar checkbox
   - Ingresar Pixel ID (15 dígitos)
   - Seleccionar qué eventos trackear:
     - PageView (recomendado)
     - AddToCart
     - InitiateCheckout
     - Purchase
   - Guardar

4. **Google Tag Manager (Opcional)**
   - Habilitar checkbox
   - Ingresar Container ID (formato: `GTM-XXXXXXX`)
   - Guardar

## Integración en el Frontend

### En todas las páginas públicas

**En el `<head>`:**
```php
<?php include __DIR__ . '/includes/tracking-scripts.php'; ?>
```

**Después del `<body>`:**
```php
<?php include __DIR__ . '/includes/tracking-body.php'; ?>
```

### Eventos Personalizados

#### Agregar al carrito
```php
// En carrito.php o donde se procese el add to cart
require_once __DIR__ . '/includes/tracking-events.php';
track_add_to_cart($product, $quantity);
```

#### Iniciar Checkout
```php
// En checkout.php
require_once __DIR__ . '/includes/tracking-events.php';
track_initiate_checkout($cart_items, $total);
```

#### Compra Completada
```php
// En gracias.php después de confirmar el pago
require_once __DIR__ . '/includes/tracking-events.php';
track_purchase($order);
```

#### Ver Producto
```php
// En producto.php
require_once __DIR__ . '/includes/tracking-events.php';
track_view_content($product);
```

## Eventos que se Trackean Automáticamente

### Google Analytics 4
- **PageView** - Todas las páginas visitadas
- **Purchase** - Compras completadas (con productos e ingresos)

### Facebook Pixel
- **PageView** - Todas las páginas visitadas (si está habilitado)
- **AddToCart** - Cuando se agrega un producto al carrito (si está habilitado)
- **InitiateCheckout** - Cuando se inicia el proceso de checkout (si está habilitado)
- **Purchase** - Compras completadas con detalles (si está habilitado)
- **ViewContent** - Vista de página de producto individual

### Google Tag Manager
- Gestión centralizada de todos los tags
- Implementa tus propios triggers y variables desde el panel de GTM

## Estructura del Config

```json
{
  "google_analytics": {
    "enabled": true,
    "measurement_id": "G-XXXXXXXXXX",
    "tracking_code": "G-XXXXXXXXXX"
  },
  "facebook_pixel": {
    "enabled": true,
    "pixel_id": "123456789012345",
    "track_page_view": true,
    "track_add_to_cart": true,
    "track_purchase": true,
    "track_initiate_checkout": true
  },
  "google_tag_manager": {
    "enabled": false,
    "container_id": "GTM-XXXXXXX"
  }
}
```

## Privacidad y GDPR

⚠️ **Importante**: Si tu tienda opera en la UE o tiene usuarios europeos, debes:

1. Implementar un banner de cookies
2. Obtener consentimiento antes de cargar tracking scripts
3. Permitir a los usuarios rechazar tracking
4. Actualizar tu política de privacidad

Los scripts actuales se cargan automáticamente. Para cumplir con GDPR, necesitarás:
- Modificar `tracking-scripts.php` para verificar consentimiento
- Agregar un sistema de gestión de cookies
- Documentar qué datos se recopilan

## Testing

### Verificar Google Analytics
1. Ir a Google Analytics → Informes → Tiempo real
2. Visitar tu tienda
3. Deberías ver tu visita en tiempo real

### Verificar Facebook Pixel
1. Instalar Facebook Pixel Helper (extensión de Chrome)
2. Visitar tu tienda
3. Click en el ícono de la extensión
4. Deberías ver el pixel activo con eventos

### Verificar GTM
1. Activar "Preview Mode" en Google Tag Manager
2. Visitar tu tienda
3. Verificar que los tags se disparen correctamente

## Troubleshooting

**Los eventos no se trackean:**
- Verificar que JavaScript esté habilitado
- Abrir consola del navegador para ver errores
- Verificar que los IDs sean correctos
- Verificar que "enabled" esté en true

**Facebook Pixel no funciona:**
- Verificar que el Pixel ID sea correcto (15 dígitos)
- Verificar que los eventos estén habilitados en la config
- Usar Facebook Pixel Helper para debugging

**Google Analytics no muestra datos:**
- Esperar 24-48 horas para datos históricos
- Usar vista en tiempo real para testing inmediato
- Verificar que el Measurement ID sea correcto (G-XXXXXXXXXX)
