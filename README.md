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
- ✅ Notificaciones por email automatizadas
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
- ✅ Integración completa con Mercadopago

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
│   ├── themes.php          # Selector de themes
│   ├── backup.php          # Sistema de backup
│   └── config.php          # Configuración del sitio
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
│   ├── email.php           # Sistema de emails
│   └── mercadopago.php     # Integración Mercadopago
├── config/                  # Configuración
│   ├── site.json           # Configuración del sitio
│   ├── theme.json          # Theme activo
│   ├── currency.json       # Multi-moneda
│   ├── maintenance.json    # Modo mantenimiento
│   ├── payment.json        # Configuración de pagos
│   ├── hero.json           # Hero image config
│   └── credentials.php     # Credenciales (NO subir a Git)
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

2. **Configurar credenciales**
   - Copiar `config/credentials.php.example` a `config/credentials.php`
   - Editar con tus credenciales reales:
     - SMTP (para emails)
     - Mercadopago (tokens de sandbox y producción)
     - OAuth (Google, Apple)

3. **Configurar permisos**
   ```bash
   chmod 755 data/
   chmod 644 data/*.json
   chmod 700 data/passwords/
   ```

4. **Subir vía FTP**
   - Subir todos los archivos al servidor
   - Asegurar que `data/passwords/.htaccess` esté presente
   - Verificar permisos de escritura en `/data/`

5. **Instalar dependencias**
   - Descargar PHPMailer desde: https://github.com/PHPMailer/PHPMailer
   - Descargar Mercadopago SDK desde: https://github.com/mercadopago/sdk-php
   - Subir a `/vendor/` vía FTP

### Configuración Inicial

1. **Acceder al admin**
   - URL: `https://tudominio.com/admin`
   - Usuario: `admin`
   - Contraseña: `password`
   - ⚠️ **IMPORTANTE**: Cambiar la contraseña inmediatamente

2. **Configurar el sitio**
   - Ir a "Configuración" en el admin
   - Configurar:
     - Nombre del sitio
     - Emails SMTP
     - Mercadopago (empezar con sandbox)
     - WhatsApp
     - Multi-moneda

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

## Credenciales por Defecto

**Admin:**
- Usuario: `admin`
- Contraseña: `password`

⚠️ **Cambiar inmediatamente después del primer login**

## Seguridad Implementada

- ✅ Passwords hasheados con bcrypt
- ✅ Rate limiting (5 intentos / 15 minutos)
- ✅ CSRF tokens en todos los formularios
- ✅ Validación estricta de uploads de imágenes
- ✅ HTTPS enforcement
- ✅ Security headers (X-Frame-Options, etc.)
- ✅ Carpeta `/data/passwords/` protegida con .htaccess
- ✅ File locking en operaciones JSON
- ✅ Logs de acciones administrativas
- ✅ Sanitización de todos los inputs

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

- PRD completo: `docs/PRD-Ecommerce-Platform-FINAL.md`
- Documentación de PHP: https://www.php.net/manual/
- Mercadopago API: https://www.mercadopago.com.ar/developers/
- PHPMailer: https://github.com/PHPMailer/PHPMailer

## Licencia

Copyright © 2025. Todos los derechos reservados.

## Notas Importantes

- **NO subir `config/credentials.php` a Git**
- **Cambiar contraseña de admin al primer login**
- **Empezar con Mercadopago en modo sandbox**
- **Hacer backups regulares antes de cambios importantes**
- **Verificar permisos de archivos en producción**
- **Probar emails en ambiente de desarrollo primero**

## Checklist Pre-Deployment

- [ ] Credenciales configuradas
- [ ] Contraseña de admin cambiada
- [ ] SMTP configurado y probado
- [ ] Mercadopago en modo correcto (sandbox/prod)
- [ ] SSL certificado instalado
- [ ] Permisos de archivos correctos
- [ ] `.htaccess` en `/data/passwords/`
- [ ] Backup inicial creado
- [ ] Theme seleccionado
- [ ] Productos de prueba agregados
- [ ] Proceso de compra testeado end-to-end
