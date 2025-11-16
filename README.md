# E-commerce Platform - PHP + JSON

Plataforma completa de e-commerce con backoffice administrativo, diseño responsive moderno, sistema de themes intercambiables, y gestión de productos mediante archivos JSON.

## Stack Tecnológico

- **Backend**: PHP puro (sin frameworks)
- **Base de datos**: Archivos JSON
- **Deployment**: Servidor con acceso FTP únicamente
- **Frontend**: HTML5, CSS3, JavaScript vanilla
- **Emails**: PHPMailer
- **Pagos**: Mercadopago SDK

## Características Principales

### E-commerce
- ✅ Sistema de themes completos intercambiables (4 themes incluidos)
- ✅ Carrusel de imágenes configurable desde admin
- ✅ Búsqueda y filtros de productos
- ✅ Sistema de favoritos/wishlist
- ✅ Reviews y testimonios con moderación
- ✅ Tracking de pedidos para clientes
- ✅ SEO básico por producto
- ✅ Experiencia mobile optimizada

### Inventario y Ventas
- ✅ Control de inventario con alertas automáticas
- ✅ Códigos de descuento y promociones
- ✅ Gestión completa de órdenes con estados
- ✅ Archivo de órdenes antiguas
- ✅ Acciones masivas en órdenes
- ✅ Multi-moneda (ARS/USD) con API automática

### Notificaciones
- ✅ **Sistema completo de notificaciones (Email + Telegram)**
- ✅ **Templates de email profesionales y personalizables**
- ✅ **Backoffice para configurar notificaciones**
- ✅ 7 tipos de emails (confirmación, aprobado, rechazado, pendiente, enviado, admin)
- ✅ Notificaciones Telegram en tiempo real

### Pagos
- ✅ **Integración completa con Mercadopago (incluye webhook seguro)**
- ✅ **Manejo de eventos post-venta (chargebacks, merchant_order)**
- ✅ Validación de webhooks (IP whitelisting, HMAC opcional)
- ✅ Pago presencial con instrucciones personalizadas
- ✅ Soporte para modo sandbox y producción

### Administración
- ✅ Dashboard personalizable con widgets
- ✅ **Header unificado en todas las páginas del admin**
- ✅ Gestión de productos con galería de imágenes
- ✅ Gestión de ventas con filtros avanzados
- ✅ Gestión de promociones y cupones
- ✅ Configuración centralizada (sitio, footer, hero, carrusel, etc.)
- ✅ Sistema de backup automático
- ✅ Modo mantenimiento
- ✅ Logs de acciones administrativas

### Seguridad
- ✅ Passwords hasheados con bcrypt
- ✅ Rate limiting (5 intentos / 15 minutos en login)
- ✅ Rate limiting en webhook (100 requests/minuto)
- ✅ CSRF tokens en todos los formularios
- ✅ Validación estricta de uploads de imágenes
- ✅ HTTPS enforcement
- ✅ Security headers (X-Frame-Options, etc.)
- ✅ Carpeta `/data/passwords/` protegida con .htaccess
- ✅ File locking en operaciones JSON
- ✅ Sanitización de todos los inputs
- ✅ **Validación de IP de Mercadopago en webhook**
- ✅ **Logging completo de webhooks y notificaciones**

## Estructura del Proyecto

```
/
├── index.php                 # Sitio público
├── producto.php             # Detalle de producto
├── carrito.php              # Carrito de compras
├── checkout.php             # Proceso de checkout
├── buscar.php               # Búsqueda de productos
├── favoritos.php            # Lista de favoritos
├── pedido.php               # Tracking de pedido
├── track.php                # Tracking público de pedido
├── gracias.php              # Confirmación de pago exitoso
├── error.php                # Error en pago
├── pendiente.php            # Pago pendiente
├── pagar-mercadopago.php    # Formulario de pago MP
├── procesar-pago-mp.php     # Procesamiento de pago MP
├── crear-preferencia-mp.php # Creación de preferencia MP
├── webhook.php              # Webhook de Mercadopago (seguro)
├── maintenance.php          # Modo mantenimiento
├── preview.php              # Preview de themes
├── admin/                   # Backoffice
│   ├── index.php           # Dashboard personalizable
│   ├── login.php           # Login admin
│   ├── productos-listado.php   # Listado de productos
│   ├── productos-nuevo.php     # Crear producto
│   ├── productos-editar.php    # Editar producto
│   ├── productos-archivados.php # Productos archivados
│   ├── ventas.php          # Gestión de ventas
│   ├── archivo-ventas.php  # Ventas archivadas
│   ├── promociones-listado.php # Gestión de promociones
│   ├── cupones-listado.php     # Gestión de cupones
│   ├── reviews-listado.php     # Gestión de reviews
│   ├── notificaciones.php  # Configuración de notificaciones
│   ├── config-sitio.php    # Configuración del sitio
│   ├── config-footer.php   # Configuración del footer
│   ├── config-hero.php     # Configuración del hero
│   ├── config-carrusel.php # Configuración del carrusel
│   ├── config-productos-heading.php # Encabezado de productos
│   ├── config-dashboard.php # Configuración del dashboard
│   ├── config-payment.php  # Configuración de pagos
│   ├── config-moneda.php   # Configuración de moneda
│   ├── config-mantenimiento.php # Modo mantenimiento
│   ├── config-themes.php   # Selector de themes
│   ├── config-analytics.php # Google Analytics, Facebook Pixel
│   ├── backup.php          # Sistema de backup
│   ├── verificar-pago-mp.php # Herramienta de verificación de pagos
│   ├── reprocesar-pago-mp.php # Reprocesar pagos manualmente
│   └── includes/
│       ├── header.php      # Header unificado del admin
│       ├── sidebar.php     # Sidebar del admin
│       └── MODAL_GUIDELINES.md # Guía de diseño de modales
├── data/                    # Archivos JSON
│   ├── products.json       # Listado de productos
│   ├── products/           # Detalles de productos individuales
│   ├── orders.json         # Órdenes activas
│   ├── archived_orders.json # Órdenes archivadas
│   ├── promotions.json     # Promociones
│   ├── coupons.json        # Cupones
│   ├── reviews.json        # Reviews
│   ├── admin_logs.json     # Logs de acciones administrativas
│   ├── webhook_log.json    # Logs de webhooks (auto-generado)
│   ├── backups/            # Backups automáticos
│   ├── rate_limits/        # Rate limiting data
│   └── passwords/          # Usuarios (protegido con .htaccess)
├── images/                  # Imágenes
│   ├── products/           # Fotos de productos
│   ├── hero/               # Imágenes hero
│   ├── carousel/           # Imágenes del carrusel
│   └── themes/             # Previews de themes
├── assets/                  # Assets del sitio
│   └── logos/              # Logos del sitio
├── themes/                  # Themes del sitio
│   ├── README.md           # Documentación del sistema de themes
│   ├── _base/              # Estilos base compartidos
│   ├── minimal/            # Tema minimalista
│   ├── bold/               # Tema atrevido
│   ├── elegant/            # Tema elegante
│   └── fresh/              # Tema fresco
├── includes/                # Archivos PHP compartidos
│   ├── functions.php       # Funciones core
│   ├── auth.php            # Sistema de autenticación
│   ├── rate_limit.php      # Rate limiting
│   ├── products.php        # CRUD de productos
│   ├── orders.php          # Gestión de órdenes
│   ├── coupons.php         # Gestión de cupones
│   ├── email.php           # Sistema de emails con templates
│   ├── telegram.php        # Sistema de notificaciones Telegram
│   ├── mercadopago.php     # Integración Mercadopago
│   ├── upload.php          # Upload de imágenes
│   ├── theme-loader.php    # Cargador de themes
│   ├── tracking-scripts.php # Scripts de analytics (<head>)
│   ├── tracking-body.php   # Scripts de analytics (<body>)
│   ├── tracking-events.php # Eventos de analytics
│   ├── header.php          # Header del sitio público
│   ├── footer.php          # Footer del sitio público
│   ├── carousel.css        # Estilos del carrusel
│   ├── mobile-menu.css     # Estilos del menú mobile
│   └── carts.js            # Funciones del carrito
├── config/                  # Configuración
│   ├── README.md           # Documentación del sistema de configuración
│   ├── theme.json          # Theme activo (versionado en git)
│   ├── currency.json       # Multi-moneda (versionado en git)
│   ├── maintenance.json    # Modo mantenimiento (versionado en git)
│   ├── hero.json           # Hero image config (versionado en git)
│   ├── carousel.json       # Carrusel config (versionado en git)
│   ├── products-heading.json # Encabezado de productos (versionado en git)
│   ├── dashboard.json      # Dashboard config (versionado en git)
│   ├── analytics.json      # Analytics config (versionado en git)
│   ├── site.json           # Configuración del sitio (NO versionado, se configura desde admin)
│   ├── footer.json         # Configuración del footer (NO versionado, se configura desde admin)
│   ├── payment.json        # Configuración de pagos (NO versionado, se configura desde admin)
│   ├── email.json          # Notificaciones email (NO versionado, se configura desde admin)
│   └── telegram.json       # Notificaciones Telegram (NO versionado, se configura desde admin)
├── templates/               # Templates de email
│   └── email/
│       ├── order_confirmation.php # Confirmación de orden
│       ├── order_paid.php  # Alias de payment_approved
│       ├── payment_approved.php # Pago aprobado
│       ├── payment_pending.php # Pago pendiente
│       ├── payment_rejected.php # Pago rechazado
│       ├── order_shipped.php # Orden enviada
│       ├── admin_new_order.php # Nueva orden (admin)
│       └── admin_chargeback_alert.php # Alerta de contracargo (admin)
├── vendor/                  # Librerías externas
│   ├── phpmailer/
│   └── mercadopago/
├── docs/                    # Documentación
│   ├── PRD-Ecommerce-Platform-FINAL.md # Especificaciones completas
│   ├── CHANGELOG.md        # Historial de cambios
│   ├── ESTADO_DEL_PROYECTO.md # Estado actual y roadmap
│   ├── NOTIFICACIONES.md   # Sistema de emails
│   ├── NOTIFICACIONES_BACKOFFICE.md # UI de notificaciones
│   ├── SISTEMA_NOTIFICACIONES_COMPLETO.md # Estado completo
│   ├── MERCADOPAGO.md      # Integración de pagos
│   ├── MERCADOPAGO_ESTADOS.md # Estados de pago
│   ├── WEBHOOK_SECURITY.md # Seguridad de webhooks
│   ├── TRACKING.md         # Analytics
│   ├── THEMES-SYSTEM-DESIGN.md # Diseño del sistema de themes
│   ├── THEMES-SYSTEM-SUMMARY.md # Resumen de themes
│   ├── CAROUSEL-V2-README.md # Sistema de carrusel
│   ├── TESTING.md          # Guía de testing
│   ├── COMO_HACER_MERGE_A_MAIN.md # Git workflow
│   ├── SETUP-DEPLOY.md     # Deploy automático
│   ├── DEPLOY-SUBDIRECTORIO.md # Deploy en subdirectorio
│   └── MODAL_GUIDELINES.md # Guía de modales (en admin/includes/)
├── mcp-server/              # MCP Server
│   ├── README.md
│   └── mcp-config.json
├── .gitignore
├── .htaccess
├── init-config.sh          # Script de inicialización
└── README.md               # Este archivo
```

## Instalación

### Requisitos

- PHP 7.4 o superior
- Acceso FTP al servidor
- Extensiones PHP: json, mbstring, fileinfo, curl

### Setup Inicial

1. **Clonar el repositorio**
   ```bash
   git clone <repository-url>
   cd shop
   ```

2. **Instalar dependencias (si usas Composer)**
   ```bash
   composer install
   ```

   O descargar manualmente:
   - PHPMailer: https://github.com/PHPMailer/PHPMailer
   - Mercadopago SDK: https://github.com/mercadopago/sdk-php
   - Colocar en `/vendor/`

3. **Inicializar archivos de configuración**
   ```bash
   chmod +x init-config.sh
   ./init-config.sh
   ```

   Este script creará los archivos de configuración básicos con valores por defecto.

4. **Configurar permisos**
   ```bash
   chmod 755 data/
   chmod 644 data/*.json
   chmod 700 data/passwords/
   chmod 755 images/
   chmod 755 assets/
   ```

5. **Subir vía FTP (si es producción)**
   - Subir todos los archivos al servidor
   - Asegurar que `data/passwords/.htaccess` esté presente
   - Verificar permisos de escritura en `/data/`, `/config/`, `/images/`, `/assets/`

6. **Configurar el servidor web**
   - Asegurar que el archivo `.htaccess` esté habilitado (para Apache)
   - Configurar SSL (HTTPS) - requerido para Mercadopago

### Configuración Inicial

1. **Acceder al admin**
   - URL: `https://tudominio.com/admin`
   - Usuario: `admin`
   - Contraseña: `password`
   - ⚠️ **IMPORTANTE**: Cambiar la contraseña inmediatamente

2. **Configurar el sitio desde el panel de administración**

   Todas las configuraciones se realizan desde el backoffice:

   **Configuración General** (Admin → Configuración del Sitio)
   - Nombre del sitio, descripción, keywords
   - Logo y contacto
   - WhatsApp (número y mensaje predeterminado)
   - Redes sociales (Facebook, Instagram, Twitter)

   **Configuración del Footer** (Admin → Configuración → Footer)
   - Diseño (simple/avanzado)
   - Columnas personalizables
   - Links, teléfonos, horarios
   - Redes sociales

   **Configuración del Hero** (Admin → Configuración → Hero)
   - Imagen principal
   - Título y subtítulo
   - Color de fondo

   **Configuración del Carrusel** (Admin → Configuración → Carrusel)
   - Imágenes del carrusel
   - Links y textos

   **Notificaciones Email** (Admin → Notificaciones → Email)
   - Configuración SMTP (Gmail, etc.)
   - Emails de origen y destinatarios
   - Activar/desactivar notificaciones por tipo
   - Probar envío de emails

   **Notificaciones Telegram** (Admin → Notificaciones → Telegram)
   - Token del bot de Telegram
   - Chat ID
   - Activar/desactivar notificaciones
   - Configurar umbrales

   **Medios de Pago** (Admin → Configuración → Medios de Pago)
   - Mercadopago (tokens sandbox/producción, webhooks)
   - Pago presencial (instrucciones)
   - Seguridad del webhook (IP whitelisting)

   **Multi-moneda** (Admin → Configuración → Moneda)
   - Configurar ARS/USD
   - Tipo de cambio manual o automático (API: Dólar API)

   **Analytics** (Admin → Configuración → Analytics)
   - Google Analytics 4
   - Facebook Pixel
   - Google Tag Manager

3. **Seleccionar theme**
   - Ir a "Configuración" → "Themes"
   - Elegir uno de los 4 themes disponibles
   - Vista previa antes de aplicar

4. **Crear productos**
   - Ir a "Productos" → "Agregar Producto"
   - Completar información básica
   - Subir hasta 10 imágenes por producto
   - Configurar SEO
   - Definir stock y alertas

**Nota importante:** Ya NO es necesario editar manualmente archivos como `config/site.json`, `config/email.json`, `config/payment.json`, etc. Todo se gestiona desde el panel de administración.

## Credenciales por Defecto

**Admin:**
- Usuario: `admin`
- Contraseña: `password`

⚠️ **Cambiar inmediatamente después del primer login**

## Modo Mantenimiento

Para activar el modo mantenimiento:

1. Ir a Admin → Configuración → Mantenimiento
2. Activar y personalizar mensaje
3. Los admins pueden acceder normalmente
4. Bypass URL: `/?bypass=codigo-secreto`

## Sistema de Backup

**Manual:**
- Ir a Admin → Backup
- Click en "Crear Backup Ahora"
- Descargar archivo ZIP

**Automático** (opcional):
- Configurar cron job para backup diario
- Mantiene últimos 7 backups automáticamente

## Multi-Moneda

El sistema soporta ARS y USD:

- Moneda principal: ARS
- Moneda secundaria: USD
- Tipo de cambio manual o vía API
- API recomendada: https://dolarapi.com

## Sistema de Notificaciones

### Notificaciones Email

El sistema incluye **7 templates de email profesionales** con diseño responsive:

**Para clientes:**
- Confirmación de orden
- Pago aprobado
- Pago pendiente
- Pago rechazado
- Envío de pedido

**Para administradores:**
- Nueva orden recibida
- Alerta de chargeback

**Configuración:**
1. Ir a Admin → Notificaciones → Email
2. Seleccionar método: PHP `mail()` o SMTP
3. Configurar credenciales SMTP (si aplica)
4. Activar/desactivar notificaciones específicas
5. Configurar emails destinatarios
6. Probar envío de emails

Ver documentación completa en `docs/NOTIFICACIONES.md`

### Notificaciones Telegram

Recibe notificaciones en tiempo real en Telegram:

**Configuración:**
1. Crear un bot con @BotFather
2. Obtener el token del bot
3. Iniciar conversación con el bot
4. Obtener tu Chat ID (usar @userinfobot)
5. Configurar en Admin → Notificaciones → Telegram
6. Activar notificaciones deseadas

**Eventos soportados:**
- Nueva orden
- Pago aprobado
- Pago pendiente
- Pago rechazado
- Chargeback
- Envío de pedido
- Stock bajo

Ver documentación completa en `docs/NOTIFICACIONES_BACKOFFICE.md`

## Integración Mercadopago

### Modo Sandbox (Testing)

1. Obtener credenciales de test en: https://www.mercadopago.com.ar/developers/
2. Configurar en Admin → Configuración → Medios de Pago
3. Activar "Modo Sandbox"
4. Usar tarjetas de prueba de Mercadopago

### Modo Producción

1. Obtener credenciales de producción
2. Configurar access token de producción
3. Cambiar a "Modo Producción"
4. Configurar webhook URL (se muestra en el admin)

### Webhook Seguro

El sistema incluye medidas de seguridad para el webhook:

- ✅ Validación de IP de Mercadopago (recomendado)
- ✅ Validación de firma HMAC-SHA256 (opcional)
- ✅ Validación de timestamp (opcional)
- ✅ Rate limiting (100 requests/minuto)
- ✅ Validación de topic y resource
- ✅ Logging completo de eventos
- ✅ Manejo de eventos duplicados

Ver documentación completa en `docs/WEBHOOK_SECURITY.md`

## Themes Incluidos

1. **Minimal** - Diseño limpio y minimalista (Azul/Púrpura)
2. **Bold** - Vibrante y llamativo (Rojo/Negro)
3. **Elegant** - Sofisticado y lujoso (Negro/Dorado)
4. **Fresh** - Moderno y friendly (Verde/Naranja)

Para cambiar theme:
- Admin → Configuración → Themes → Seleccionar → Aplicar

Cada theme incluye ~60 variables CSS personalizables.

Ver documentación completa en `themes/README.md`

## Desarrollo

### Estructura de Código

- `includes/functions.php` - Funciones core
- `includes/products.php` - CRUD de productos
- `includes/orders.php` - Gestión de órdenes
- `includes/auth.php` - Autenticación
- `includes/rate_limit.php` - Rate limiting
- `includes/email.php` - Sistema de emails
- `includes/telegram.php` - Notificaciones Telegram
- `includes/mercadopago.php` - Integración Mercadopago

### Funciones Principales

```php
// Lectura/escritura JSON con file locking
$data = read_json($file);
write_json($file, $data);

// Productos
$products = get_all_products();
$product = get_product_by_id($id);
create_product($data);
update_product($id, $data);
delete_product($id);

// Órdenes
$orders = get_all_orders();
$order = get_order_by_id($id);
create_order($data);
update_order_status($id, $status);

// Stock
update_stock($product_id, $quantity, $reason);
$low_stock = get_low_stock_products();

// Autenticación
$result = authenticate_admin($username, $password);
create_admin_session($user);
require_admin(); // En páginas protegidas

// Emails
send_email_template('payment_approved', $order, $to);
send_telegram_notification('payment_approved', $order);
```

## Roadmap de Desarrollo

Ver archivo `docs/PRD-Ecommerce-Platform-FINAL.md` para el roadmap completo.

**Fases completadas:**
- ✅ Fase 1: Setup y Core
- ✅ Fase 2: Frontend Público Básico
- ✅ Fase 3: Mobile Experience
- ✅ Fase 4: Sistema de Themes
- ✅ Fase 5: Backoffice Core
- ✅ Fase 6: Sistema de Órdenes
- ✅ Fase 7: Sistema de Pagos (Mercadopago)
- ✅ Fase 8: Sistema de Notificaciones

**Próximas fases:**
- ⏳ Mejoras de UX/UI
- ⏳ Analytics avanzado
- ⏳ Optimizaciones de performance

## Soporte y Documentación

### Documentación del Proyecto

- **README principal**: Este archivo
- **Sistema de configuración**: `config/README.md`
- **PRD completo**: `docs/PRD-Ecommerce-Platform-FINAL.md`
- **Changelog**: `docs/CHANGELOG.md`
- **Estado del proyecto**: `docs/ESTADO_DEL_PROYECTO.md`
- **Sistema de notificaciones**: `docs/NOTIFICACIONES.md`
- **Backoffice de notificaciones**: `docs/NOTIFICACIONES_BACKOFFICE.md`
- **Mercadopago**: `docs/MERCADOPAGO.md`
- **Seguridad del webhook**: `docs/WEBHOOK_SECURITY.md`
- **Sistema de themes**: `themes/README.md`
- **Merge workflow**: `docs/COMO_HACER_MERGE_A_MAIN.md`
- **Deploy automático**: `docs/SETUP-DEPLOY.md`

### Referencias Externas

- Documentación de PHP: https://www.php.net/manual/
- Mercadopago API: https://www.mercadopago.com.ar/developers/
- PHPMailer: https://github.com/PHPMailer/PHPMailer
- Telegram Bot API: https://core.telegram.org/bots/api
- Dólar API: https://dolarapi.com

## Licencia

Copyright © 2025. Todos los derechos reservados.

## Notas Importantes

- **Ejecutar `./init-config.sh` antes de configurar el sitio** (crea archivos de configuración básicos)
- **Archivos de configuración sensibles NO se versionan en git** (están en .gitignore)
- **Configurar todo desde el panel de administración** (no editar archivos JSON manualmente)
- **Cambiar contraseña de admin al primer login**
- **Empezar con Mercadopago en modo sandbox** para pruebas
- **Hacer backups regulares antes de cambios importantes**
- **Verificar permisos de escritura en `/data/`, `/config/`, `/images/`, `/assets/`**
- **Probar emails en ambiente de desarrollo primero**
- **Configurar y probar el webhook de Mercadopago antes de producción**
- **Revisar logs del webhook regularmente**: `data/webhook_log.json`
- **Configurar Telegram es opcional pero recomendado** para alertas en tiempo real
- **Tus configuraciones se preservan entre merges de Git** (cada branch/entorno mantiene las suyas)

## Checklist Pre-Deployment

- [ ] Ejecutado `./init-config.sh` en el servidor
- [ ] Contraseña de admin cambiada desde el panel
- [ ] Permisos de archivos correctos (`/data/`, `/config/`, `/images/`, `/assets/` con escritura)
- [ ] `.htaccess` en `/data/passwords/`
- [ ] **Configuración del Sitio** (Admin → Configuración del Sitio)
  - [ ] Nombre, descripción, contacto
  - [ ] Logo (opcional)
  - [ ] WhatsApp y redes sociales
- [ ] **Configuración del Footer** (Admin → Configuración → Footer)
- [ ] **Configuración del Hero** (Admin → Configuración → Hero)
- [ ] **SMTP configurado y probado** (Admin → Notificaciones → Email)
- [ ] **Notificaciones email configuradas y probadas**
- [ ] **Telegram bot configurado (opcional)** (Admin → Notificaciones → Telegram)
- [ ] **Mercadopago configurado** (Admin → Configuración → Medios de Pago)
  - [ ] Tokens de sandbox/producción
  - [ ] Webhook URL configurado en panel de Mercadopago
  - [ ] IP whitelisting verificado
- [ ] **Analytics configurado (opcional)** (Admin → Configuración → Analytics)
- [ ] SSL certificado instalado
- [ ] Backup inicial creado
- [ ] Theme seleccionado (Admin → Configuración → Themes)
- [ ] Productos de prueba agregados
- [ ] Proceso de compra testeado end-to-end
- [ ] **Probar recepción de notificaciones** (email y Telegram)
- [ ] **Verificar logs del webhook** (`data/webhook_log.json`)
