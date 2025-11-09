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

- ✅ Sistema de themes completos intercambiables (4 themes incluidos)
- ✅ Control de inventario con alertas automáticas
- ✅ **Sistema completo de notificaciones (Email + Telegram)**
- ✅ **Templates de email profesionales y personalizables**
- ✅ **Backoffice para configurar notificaciones**
- ✅ Búsqueda y filtros de productos
- ✅ Sistema de favoritos/wishlist
- ✅ Códigos de descuento y promociones
- ✅ Reviews y testimonios con moderación
- ✅ Tracking de pedidos para clientes
- ✅ Multi-moneda (ARS/USD)
- ✅ SEO básico por producto
- ✅ Modo mantenimiento
- ✅ Sistema de backup automático
- ✅ Experiencia mobile optimizada
- ✅ **Integración completa con Mercadopago (incluye webhook seguro)**
- ✅ **Manejo de eventos post-venta (chargebacks, merchant_order)**
- ✅ **Header unificado en todas las páginas del admin**

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
├── admin/                   # Backoffice
│   ├── index.php           # Dashboard
│   ├── login.php           # Login admin
│   ├── productos.php       # Gestión de productos
│   ├── ventas.php          # Gestión de ventas
│   ├── promociones.php     # Gestión de promociones
│   ├── cupones.php         # Gestión de cupones
│   ├── reviews.php         # Gestión de reviews
│   ├── notificaciones.php  # Configuración de notificaciones
│   ├── themes.php          # Selector de themes
│   ├── backup.php          # Sistema de backup
│   ├── config.php          # Configuración del sitio
│   └── includes/
│       ├── header.php      # Header unificado del admin
│       └── sidebar.php     # Sidebar del admin
├── data/                    # Archivos JSON
│   ├── products.json       # Listado de productos
│   ├── products/           # Detalles de productos
│   ├── orders.json         # Órdenes
│   ├── promotions.json     # Promociones
│   ├── coupons.json        # Cupones
│   ├── reviews.json        # Reviews
│   ├── wishlists.json      # Listas de deseos
│   ├── visits.json         # Estadísticas de visitas
│   ├── newsletters.json    # Suscriptores
│   ├── backups/            # Backups automáticos
│   └── passwords/          # Usuarios (protegido con .htaccess)
├── images/                  # Imágenes
│   ├── products/           # Fotos de productos
│   ├── hero/               # Imágenes hero
│   └── themes/             # Previews de themes
├── themes/                  # Themes del sitio
│   ├── minimal/
│   ├── bold/
│   ├── elegant/
│   └── fresh/
├── includes/                # Archivos PHP compartidos
│   ├── header.php
│   ├── footer.php
│   ├── functions.php       # Funciones core
│   ├── auth.php            # Sistema de autenticación
│   ├── rate_limit.php      # Rate limiting
│   ├── products.php        # CRUD de productos
│   ├── email.php           # Sistema de emails con templates
│   ├── telegram.php        # Sistema de notificaciones Telegram
│   └── mercadopago.php     # Integración Mercadopago
├── config/                  # Configuración
│   ├── site.json           # Configuración del sitio (se configura desde el admin)
│   ├── theme.json          # Theme activo
│   ├── currency.json       # Multi-moneda
│   ├── maintenance.json    # Modo mantenimiento
│   ├── payment.json        # Configuración de pagos (se configura desde el admin)
│   ├── email.json          # Notificaciones email (se configura desde el admin)
│   ├── telegram.json       # Notificaciones Telegram (se configura desde el admin)
│   ├── hero.json           # Hero image config
│   ├── footer.json         # Configuración del footer (se configura desde el admin)
│   ├── credentials.php.example  # Plantilla de credenciales (legacy, opcional)
│   └── README.md           # Documentación del sistema de configuración
├── templates/               # Templates de email
│   └── email/
│       ├── order_confirmation.php
│       ├── payment_approved.php
│       ├── payment_pending.php
│       ├── payment_rejected.php
│       ├── order_shipped.php
│       ├── admin_new_order.php
│       └── admin_chargeback_alert.php
├── webhook.php              # Webhook de Mercadopago (seguro)
└── vendor/                  # Librerías externas
    ├── phpmailer/
    └── mercadopago/
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

2. **Inicializar archivos de configuración**
   ```bash
   ./init-config.sh
   ```
   Este script crea automáticamente todos los archivos de configuración desde sus plantillas `.example`

3. **Configurar permisos**
   ```bash
   chmod 755 data/
   chmod 644 data/*.json
   chmod 700 data/passwords/
   ```

4. **Subir vía FTP**
   - Subir todos los archivos al servidor
   - Asegurar que `data/passwords/.htaccess` esté presente
   - Verificar permisos de escritura en `/data/` y `/config/`

5. **Instalar dependencias**
   - Descargar PHPMailer desde: https://github.com/PHPMailer/PHPMailer
   - Descargar Mercadopago SDK desde: https://github.com/mercadopago/sdk-php
   - Subir a `/vendor/` vía FTP

6. **Ejecutar init-config.sh en el servidor** (si no lo hiciste localmente)
   ```bash
   ./init-config.sh
   ```

### Configuración Inicial

1. **Acceder al admin**
   - URL: `https://tudominio.com/admin`
   - Usuario: `admin`
   - Contraseña: `password`
   - ⚠️ **IMPORTANTE**: Cambiar la contraseña inmediatamente

2. **Configurar el sitio desde el panel de administración**

   Todas las configuraciones se realizan desde el backoffice, sin necesidad de editar archivos manualmente:

   - **Configuración General** (Admin → Configuración del Sitio)
     - Nombre del sitio, descripción, keywords
     - Logo y contacto
     - Redes sociales (Facebook, Instagram, Twitter)
     - WhatsApp (número y mensaje predeterminado)
     - Google Analytics y Facebook Pixel

   - **Configuración del Footer** (Admin → Configuración → Footer)
     - Diseño (simple/avanzado)
     - Columnas personalizables
     - Links, teléfonos, horarios
     - Redes sociales

   - **Notificaciones Email** (Admin → Notificaciones → Email)
     - Configuración SMTP (Gmail, etc.)
     - Emails de origen y destinatarios
     - Activar/desactivar notificaciones por tipo
     - Probar envío de emails

   - **Notificaciones Telegram** (Admin → Notificaciones → Telegram)
     - Token del bot de Telegram
     - Chat ID
     - Activar/desactivar notificaciones
     - Configurar umbrales (ej: pedidos de alto valor)

   - **Medios de Pago** (Admin → Configuración → Medios de Pago)
     - Mercadopago (tokens sandbox/producción, webhooks)
     - Pago presencial (instrucciones)
     - Seguridad del webhook (firma HMAC, IP whitelisting)

   - **Multi-moneda** (Admin → Configuración → Moneda)
     - Configurar ARS/USD
     - Tipo de cambio manual o automático

3. **Seleccionar theme**
   - Ir a "Themes"
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

## Seguridad Implementada

- ✅ Passwords hasheados con bcrypt
- ✅ Rate limiting (5 intentos / 15 minutos en login)
- ✅ Rate limiting en webhook (60 requests/minuto)
- ✅ CSRF tokens en todos los formularios
- ✅ Validación estricta de uploads de imágenes
- ✅ HTTPS enforcement
- ✅ Security headers (X-Frame-Options, etc.)
- ✅ Carpeta `/data/passwords/` protegida con .htaccess
- ✅ File locking en operaciones JSON
- ✅ Logs de acciones administrativas
- ✅ Sanitización de todos los inputs
- ✅ **Validación de firma HMAC-SHA256 en webhook**
- ✅ **Verificación de IP de Mercadopago**
- ✅ **Logging completo de webhooks y notificaciones**

## Modo Mantenimiento

Para activar el modo mantenimiento:

1. Ir a admin → "Modo Mantenimiento"
2. Activar y personalizar mensaje
3. Los admins pueden acceder normalmente
4. Bypass URL: `/?bypass=codigo-secreto`

## Sistema de Backup

**Manual:**
- Ir a admin → "Backup"
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
- API recomendada: https://api.bluelytics.com.ar

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
1. Ir a admin → Notificaciones → Email
2. Activar/desactivar notificaciones específicas
3. Configurar emails destinatarios (admin)
4. Personalizar remitente y asunto
5. Probar envío de emails

Ver documentación completa en `docs/NOTIFICACIONES.md`

### Notificaciones Telegram

Recibe notificaciones en tiempo real en Telegram:

**Configuración:**
1. Crear un bot con @BotFather
2. Obtener el token del bot
3. Iniciar conversación con el bot
4. Obtener tu Chat ID
5. Configurar en admin → Notificaciones → Telegram
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
2. Configurar en admin → Configuración → Medios de Pago
3. Activar "Modo Sandbox"
4. Usar tarjetas de prueba de Mercadopago

### Modo Producción

1. Obtener credenciales de producción
2. Configurar access token de producción
3. Cambiar a "Modo Producción"
4. Configurar webhook URL (se muestra en el admin)

### Webhook Seguro

El sistema incluye medidas de seguridad avanzadas para el webhook:

- ✅ Validación de firma HMAC-SHA256
- ✅ Verificación de IP de Mercadopago
- ✅ Rate limiting (60 requests/minuto)
- ✅ Validación de topic y resource
- ✅ Logging completo de eventos
- ✅ Manejo de eventos duplicados

Ver documentación completa en `WEBHOOK_SECURITY.md`

## Themes Incluidos

1. **Minimal** - Diseño limpio y minimalista
2. **Bold** - Vibrante y llamativo
3. **Elegant** - Sofisticado y lujoso
4. **Fresh** - Moderno y friendly

Para cambiar theme:
- Admin → Themes → Seleccionar → Aplicar

## Desarrollo

### Estructura de Código

- `includes/functions.php` - Funciones core
- `includes/products.php` - CRUD de productos
- `includes/auth.php` - Autenticación
- `includes/rate_limit.php` - Rate limiting

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

// Stock
update_stock($product_id, $quantity, $reason);
$low_stock = get_low_stock_products();

// Autenticación
$result = authenticate_admin($username, $password);
create_admin_session($user);
require_admin(); // En páginas protegidas
```

## Roadmap de Desarrollo

Ver archivo `docs/PRD-Ecommerce-Platform-FINAL.md` para el roadmap completo.

**Fases completadas:**
- ✅ Fase 1: Setup y Core (COMPLETADO)

**Próximas fases:**
- ⏳ Fase 2: Frontend Público Básico
- ⏳ Fase 3: Mobile Experience
- ⏳ Fase 4: Sistema de Themes
- ⏳ Fase 5: Backoffice Core
- ⏳ Fase 6-12: Ver PRD

## Soporte y Documentación

### Documentación del Proyecto

- **PRD completo**: `docs/PRD-Ecommerce-Platform-FINAL.md`
- **Sistema de notificaciones**: `docs/NOTIFICACIONES.md`
- **Backoffice de notificaciones**: `docs/NOTIFICACIONES_BACKOFFICE.md`
- **Seguridad del webhook**: `WEBHOOK_SECURITY.md`

### Referencias Externas

- Documentación de PHP: https://www.php.net/manual/
- Mercadopago API: https://www.mercadopago.com.ar/developers/
- PHPMailer: https://github.com/PHPMailer/PHPMailer
- Telegram Bot API: https://core.telegram.org/bots/api

## Licencia

Copyright © 2025. Todos los derechos reservados.

## Notas Importantes

- **Ejecutar `./init-config.sh` antes de configurar el sitio**
- **Archivos de configuración NO se versionan en git** (están en .gitignore)
- **Configurar todo desde el panel de administración** (no editar archivos JSON manualmente)
- **Cambiar contraseña de admin al primer login**
- **Empezar con Mercadopago en modo sandbox**
- **Hacer backups regulares antes de cambios importantes**
- **Verificar permisos de escritura en `/data/` y `/config/`**
- **Probar emails en ambiente de desarrollo primero**
- **Configurar y probar el webhook de Mercadopago antes de producción**
- **Verificar que la validación HMAC esté activa en producción**
- **Revisar logs del webhook regularmente**: `data/webhook_log.json`
- **Configurar Telegram es opcional pero recomendado para alertas en tiempo real**
- **Tus configuraciones se preservan entre merges de Git** (cada branch/entorno mantiene las suyas)

## Checklist Pre-Deployment

- [ ] Ejecutado `./init-config.sh` en el servidor
- [ ] Contraseña de admin cambiada desde el panel
- [ ] Permisos de archivos correctos (`/data/` y `/config/` con escritura)
- [ ] `.htaccess` en `/data/passwords/`
- [ ] **Configuración del Sitio** (Admin → Configuración del Sitio)
  - [ ] Nombre, descripción, contacto
  - [ ] WhatsApp y redes sociales
  - [ ] Google Analytics (opcional)
- [ ] **Configuración del Footer** (Admin → Configuración → Footer)
- [ ] **SMTP configurado y probado** (Admin → Notificaciones → Email)
- [ ] **Notificaciones email configuradas y probadas**
- [ ] **Telegram bot configurado (opcional)** (Admin → Notificaciones → Telegram)
- [ ] **Mercadopago configurado** (Admin → Configuración → Medios de Pago)
  - [ ] Tokens de sandbox/producción
  - [ ] Webhook URL configurado
  - [ ] Validación HMAC activa
  - [ ] IP whitelisting verificado
- [ ] SSL certificado instalado
- [ ] Backup inicial creado
- [ ] Theme seleccionado (Admin → Themes)
- [ ] Productos de prueba agregados
- [ ] Proceso de compra testeado end-to-end
- [ ] **Probar recepción de notificaciones (email y Telegram)**
- [ ] **Verificar logs del webhook** (`data/webhook_log.json`)
