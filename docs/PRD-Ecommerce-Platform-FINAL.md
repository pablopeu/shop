# Product Requirements Document (PRD)
## E-commerce Platform - PHP + JSON

---

## 1. RESUMEN EJECUTIVO

### 1.1 Objetivo del Proyecto
Desarrollo de una plataforma de e-commerce completa con backoffice administrativo, diseño responsive moderno, sistema de themes intercambiables, y gestión de productos mediante archivos JSON.

### 1.2 Stack Tecnológico
- **Backend**: PHP puro
- **Base de datos**: Archivos JSON (productos, configuración, usuarios)
- **Deployment**: Servidor con acceso FTP únicamente
- **Frontend**: HTML5, CSS3, JavaScript vanilla (responsive nativo)
- **Emails**: PHPMailer para envío de notificaciones
- **Pagos**: Mercadopago SDK (modo sandbox y producción)

### 1.3 Características Principales
- Sistema de themes completos intercambiables
- Control de inventario con alertas automáticas
- Notificaciones por email automatizadas
- Búsqueda y filtros de productos
- Sistema de favoritos/wishlist
- Códigos de descuento y promociones
- Reviews y testimonios
- Tracking de pedidos para clientes
- Multi-moneda (ARS/USD)
- SEO básico por producto
- Modo mantenimiento
- Sistema de backup automático
- Experiencia mobile optimizada
- Integración completa con Mercadopago

---

## 2. ARQUITECTURA DEL SISTEMA

### 2.1 Estructura de Archivos
```
/
├── index.php (sitio público)
├── producto.php (detalle de producto)
├── carrito.php
├── checkout.php
├── buscar.php
├── favoritos.php
├── pedido.php (tracking)
├── admin/ (backoffice)
│   ├── index.php (dashboard)
│   ├── login.php
│   ├── productos.php
│   ├── ventas.php
│   ├── promociones.php
│   ├── cupones.php
│   ├── reviews.php
│   ├── themes.php
│   ├── backup.php
│   └── config.php
├── data/
│   ├── products.json (listado general)
│   ├── products/
│   │   ├── {product-id}.json
│   │   └── ...
│   ├── orders.json
│   ├── promotions.json
│   ├── coupons.json
│   ├── reviews.json
│   ├── wishlists.json
│   ├── visits.json
│   ├── newsletters.json
│   ├── backups/
│   └── passwords/ (con .htaccess para bloqueo web)
│       └── users.json
├── images/
│   ├── products/
│   │   └── {product-id}/
│   │       ├── 1.jpg
│   │       └── ... (hasta 10 fotos)
│   ├── hero/
│   └── themes/
├── themes/
│   ├── minimal/
│   │   ├── theme.css
│   │   ├── preview.jpg
│   │   └── config.json
│   ├── bold/
│   ├── elegant/
│   └── fresh/
├── includes/
│   ├── header.php
│   ├── footer.php
│   ├── functions.php
│   ├── email.php
│   └── mercadopago.php
├── vendor/
│   ├── phpmailer/
│   └── mercadopago/
└── config/
    ├── hero.json
    ├── payment.json
    ├── site.json
    ├── theme.json
    ├── currency.json
    ├── maintenance.json
    └── credentials.php (no incluir en Git)
```

### 2.2 Seguridad

**Autenticación y Passwords:**
- Todos los passwords almacenados con hash (password_hash de PHP)
- Carpeta `/data/passwords/` protegida con .htaccess
- Sesiones seguras para backoffice con regeneración de ID
- Validación y sanitización de todos los inputs

**Protección contra Ataques:**
- **Rate Limiting en Login**: Máximo 5 intentos en 15 minutos
- **CSRF Tokens**: Implementado en todos los formularios del admin
- **Validación de uploads**:
  - Solo JPG, PNG, WebP permitidos
  - Verificación de MIME type real (no solo extensión)
  - Límite de tamaño: 5MB por imagen
  - Renombrado de archivos para evitar inyección
- **Logs de Admin**: Registro de todas las acciones críticas
- **HTTPS Enforcement**: Redirect automático y headers de seguridad
- **Security Headers**: X-Frame-Options, X-Content-Type-Options, etc.

---

## 3. FUNCIONALIDADES - SITIO PÚBLICO

### 3.1 Página Principal (Home)

**Componentes:**
- Hero image configurable desde backoffice
- Subtítulo editable
- Grid de productos responsive
- Footer personalizable
- WhatsApp button flotante
- Selector de moneda (ARS/USD)

**Visualización de Productos:**
- Primera foto del producto
- Nombre del producto
- Badge de stock bajo (si stock < 5 unidades)
- Indicador "Sin stock" si corresponde
- Promedio de estrellas de reviews
- Precio normal
- Si hay promoción vigente:
  - Precio original tachado
  - Precio con descuento destacado
  - Badge "PROMOCIÓN"
- Botón "Agregar al carrito" (deshabilitado si sin stock)
- Icono de corazón para agregar a favoritos

### 3.2 Página de Producto Individual

**URL**: Única y compartible con SEO friendly slug (ej: `/producto.php?slug=remera-algodon-azul`)

**Componentes:**
- **Galería de hasta 10 fotos con:**
  - Click para zoom/lightbox
  - Navegación con flechas y swipe en mobile
  - Thumbnails clickeables
  - Contador "3/10"
- Nombre y descripción del producto
- Meta tags para SEO (title, description, og:image)
- Stock disponible visible
- Reviews y rating promedio
- Precio (con promoción aplicada si corresponde)
- Campo para código de descuento
- Botón "Agregar al carrito"
- Botón para agregar a favoritos
- **Botón de compartir mejorado:**
  - Copiar link
  - WhatsApp (con mensaje pre-cargado)
  - Facebook
  - Twitter

**Orden de fotos**: Configurable desde backoffice

**Sección de Reviews:**
- Mostrar últimos 5 reviews aprobados
- Rating con estrellas
- Nombre del usuario y fecha
- Texto del comentario
- Botón "Ver todos los comentarios"
- Formulario para agregar review (solo usuarios logueados)

### 3.3 Sistema de Búsqueda y Filtros

**Buscador:**
- Campo de búsqueda visible en header
- Búsqueda por nombre y descripción
- Resultados en tiempo real (AJAX opcional)
- Página de resultados `/buscar.php?q=...`

**Filtros:**
- Ordenar por:
  - Más nuevos
  - Precio: menor a mayor
  - Precio: mayor a menor
  - Mejores reviews
  - En promoción
- Filtro por rango de precio (slider)
- Filtro por disponibilidad (Con stock / Todos)

### 3.4 Wishlist / Favoritos

**Funcionalidades:**
- Icono de corazón en cada producto
- Contador en header "❤️ Favoritos (3)"
- Página dedicada `/favoritos.php`
- Persistencia:
  - localStorage para usuarios no logueados
  - JSON en servidor para usuarios logueados
- Posibilidad de mover productos a carrito directamente
- Compartir wishlist (link único)

### 3.5 Carrito de Compras

**Funcionalidades:**
- Ver productos agregados
- Modificar cantidades (respetando stock disponible)
- Eliminar productos
- Indicador de stock disponible por producto
- Ver subtotal
- Campo para código de cupón
- Ver promociones aplicadas
- Ver descuentos por cupón
- Ver total final
- Selector de moneda (ARS/USD)
- Botón para proceder al checkout

**Mobile Experience:**
- Carrito como bottom sheet deslizable
- Sticky "Ver carrito" button
- Animaciones smooth

### 3.6 Sistema de Autenticación

**Opciones de login:**
1. Google OAuth
2. Apple Sign-In
3. Registro manual (email + password)

**Datos requeridos:**
- Inicial: Nombre y Email (automático con OAuth)
- Datos de envío: Solicitados solo al momento de checkout si no están completos
- No obligatorios hasta finalizar compra con envío

### 3.7 Proceso de Checkout

**Flujos:**

**A. Compra con envío:**
1. Verificar datos de envío completos
2. Si faltan datos → Solicitar completar
3. Verificar stock disponible
4. Aplicar promociones y cupones
5. Seleccionar método de pago
6. Generar orden

**B. Pago presencial:**
1. Seleccionar "Retiro en local / Pago presencial"
2. Generar orden sin procesador de pagos
3. Estado: "Pendiente de pago"
4. Email de confirmación con instrucciones

**C. Pago online (Mercadopago):**
1. Generar link de pago con Mercadopago SDK
2. Modo sandbox para testing
3. Redirigir al procesador
4. Webhook con validación de firma
5. Manejo de pagos rechazados con reintentos
6. Email de confirmación al aprobar

**Validaciones:**
- Verificar stock antes de generar orden
- Reducir stock automáticamente al confirmar pago
- Restaurar stock si pago es rechazado o cancelado
- Timeout de reserva de stock (15 minutos)

### 3.8 Tracking de Pedido

**URL**: `/pedido.php?id={order-id}&token={secure-token}`

**Componentes:**
- Timeline visual del estado:
  - 📦 Pendiente
  - ✅ Cobrada
  - 🚚 Enviada
  - 🏠 Entregada
- Información del pedido
- Productos comprados
- Total pagado
- Número de tracking (si fue agregado por admin)
- Link al transportista (Correo Argentino, etc.)
- Información de contacto del vendedor

### 3.9 Últimos Productos Vistos

**Implementación:**
- localStorage del navegador
- Almacena últimos 10 productos visitados
- Sección en home o footer
- Slider horizontal responsive
- Click para ir directo al producto

### 3.10 Responsive Design

**Mobile First Approach:**
- Breakpoints: Mobile (< 768px), Tablet (768-1024px), Desktop (> 1024px)
- Hero images optimizadas para cada dispositivo
- Menú hamburguesa en mobile

**Touch Gestures:**
- Swipe horizontal en galería de fotos
- Pull to refresh en listados
- Long press para opciones rápidas

**Optimizaciones Mobile:**
- Sticky "Agregar al carrito" en página de producto
- Carrito como bottom sheet (drawer desde abajo)
- Menú hamburguesa mejorado con animaciones
- Teclados contextuales:
  - Numérico para teléfono
  - Email para campos de email
  - Búsqueda para buscador

**Performance Mobile:**
- Lazy loading agresivo
- Imágenes responsive con srcset
- Reducción de animaciones en conexiones lentas

---

## 4. FUNCIONALIDADES - BACKOFFICE

### 4.1 Acceso

**URL**: `/admin`

**Login:**
- Usuario y contraseña (hash almacenado)
- Rate limiting (5 intentos / 15 minutos)
- Log de intentos fallidos
- Sesión con timeout configurable
- CSRF token en formulario de login

### 4.2 Dashboard Principal

**Layout:**
- **Header**: 
  - Izquierda: "{Nombre del Sitio} ADMIN"
  - Derecha: Link "Ver sitio público" (nueva pestaña)
- **Sidebar izquierdo** con navegación:
  - Dashboard
  - Productos
  - Ventas
  - Promociones
  - Cupones
  - Reviews
  - Themes
  - Búsqueda
  - Hero/Carrusel
  - Subtítulo
  - Footer
  - Backup
  - Modo Mantenimiento
  - Configuración
  - [Pie] Cerrar sesión

**Métricas del Dashboard:**
- Selector de período: Día / Semana / Mes / Año
- Ventas totales del período
- Ventas pendientes
- Ventas cobradas sin enviar
- Productos activos
- Productos con stock bajo (< 5 unidades)
- Promociones activas
- Cupones activos
- Reviews pendientes de aprobación

**Alertas del Dashboard:**
- ⚠️ Productos sin stock
- ⚠️ Productos con stock bajo
- ⚠️ Reviews pendientes de moderación
- ⚠️ Backups desactualizados (> 7 días)

### 4.3 Gestión de Productos

**Listado:**
- Tabla con columnas:
  - Thumbnail (primera foto)
  - Nombre
  - Precio
  - Stock (con badge rojo si < 5)
  - Rating promedio
  - Estado (Activo/Inactivo)
  - Acciones (Editar | Eliminar)
- Filtros: Todos / Activos / Sin stock / Stock bajo
- Botón "Agregar Producto" (top derecha)
- Drag & drop para reordenar productos

**Formulario de Producto:**

**Información Básica:**
- Nombre
- Descripción
- Precio (ARS)
- Precio USD (calculado automáticamente o manual)
- Stock (cantidad numérica)
- Alerta stock bajo (configurar umbral)
- Estado (Activo/Inactivo)

**SEO:**
- URL Slug (auto-generado, editable)
- Meta Title (60 caracteres máx)
- Meta Description (160 caracteres máx)
- Alt text por imagen

**Imágenes:**
- Upload de hasta 10 fotos
- Validación automática:
  - Formato: JPG, PNG, WebP
  - Tamaño máx: 5MB
  - MIME type verificado
- Drag & drop para reordenar fotos
- Editar alt text por imagen
- Crop/resize básico (opcional)

**Otros:**
- Generación automática de ID único
- Fecha de creación/actualización visible

### 4.4 Gestión de Inventario

**Dashboard de Inventario:**
- Vista general de stock
- Productos críticos (sin stock)
- Productos con stock bajo
- Historial de movimientos

**Alertas Automáticas:**
- Email cuando producto llega a stock configurado
- Listado de productos que necesitan reposición
- Exportar listado a CSV

**Ajustes de Stock:**
- Incrementar/Decrementar manual con motivo
- Log de cambios (quién, cuándo, por qué)
- Reservas temporales (durante checkout)

### 4.5 Gestión de Ventas

**Filtros:**
- Todas / Pendientes / Cobradas / Enviadas / Canceladas / Rechazadas

**Tabla de Ventas:**
| Fecha | Cliente | Contacto | Total | Moneda | Estado | Acciones |
|-------|---------|----------|-------|--------|--------|----------|
| DD/MM/YYYY | Nombre | Email/Tel | $XXX | ARS/USD | Dropdown editable | 👁️ 📧 |

**Acciones:**
- 👁️ Ver detalles
- 📧 Enviar email al cliente
- 📋 Copiar info del pedido

**Estados disponibles:**
- Pendiente
- Cobrada
- Enviada
- Entregada
- Cancelada
- Rechazada
- Cobrada sin enviar

**Exportación:**
- Botón "Exportar CSV" (top derecha)

**Detalle de Venta:**
- Información del cliente
- Productos comprados
- Subtotal, descuentos, cupón aplicado, total
- Estado actual con timeline
- Historial de cambios de estado (quién, cuándo)
- Datos de envío
- Campo para número de tracking
- Link de pago (si aplica)
- Estado de Mercadopago (aprobado, rechazado, pendiente)
- Notas adicionales (campo editable)
- Botón "Reenviar email de confirmación"
- Ajuste de stock (si se cancela orden)

### 4.6 Gestión de Promociones

**Listado de Promociones:**
| Nombre | Descuento | Período | Productos | Estado | Acciones |
|--------|-----------|---------|-----------|--------|----------|
| Black Friday | 25% | 20/11-27/11 | 15 | Activa | ✏️ 🗑️ |

**Formulario de Promoción:**
- Nombre de la promoción
- Tipo de descuento:
  - Porcentaje (%)
  - Monto fijo ($)
- Aplicación:
  - Todo el sitio
  - Productos específicos (selector múltiple)
- Condición:
  - Cualquier valor de compra
  - A partir de $X
- Período:
  - Permanente
  - Fecha inicio y fin
- Estado (Activa/Inactiva)

### 4.7 Gestión de Cupones

**Listado de Cupones:**
| Código | Descuento | Usos | Límite | Válido hasta | Estado | Acciones |
|--------|-----------|------|--------|--------------|--------|----------|
| VERANO25 | 25% | 43 | 100 | 31/03/2025 | Activo | ✏️ 🗑️ |

**Formulario de Cupón:**
- Código (texto único, ej: VERANO2025, BIENVENIDO10)
- Tipo de descuento:
  - Porcentaje (%)
  - Monto fijo ($)
- Compra mínima ($X o sin mínimo)
- Límite de usos:
  - Ilimitado
  - X usos totales
  - 1 uso por usuario
- Válido desde / hasta (fechas)
- Aplicable a:
  - Todo el sitio
  - Productos específicos
- No acumulable con promociones (checkbox)
- Estado (Activo/Inactivo)

**Reportes:**
- Cupones más usados
- Ingresos por cupón
- Exportar a CSV

### 4.8 Gestión de Reviews

**Listado de Reviews:**
| Producto | Usuario | Rating | Fecha | Estado | Acciones |
|----------|---------|--------|-------|--------|----------|
| Remera | Juan P. | ⭐⭐⭐⭐⭐ | 15/01 | Pendiente | ✅ ❌ 👁️ |

**Estados:**
- Pendiente (requiere aprobación)
- Aprobado
- Rechazado

**Detalle de Review:**
- Producto asociado
- Usuario (nombre y email)
- Rating (1-5 estrellas)
- Comentario completo
- Fecha de creación
- Botones: Aprobar / Rechazar / Eliminar

**Configuración:**
- Requerir aprobación antes de publicar (on/off)
- Permitir reviews anónimos (on/off)
- Permitir solo a usuarios con compra verificada (on/off)

### 4.9 Sistema de Themes

**Selector de Theme:**
- Vista previa de cada theme
- Radio buttons o cards clickeables
- Preview en modal antes de aplicar
- Botón "Aplicar Theme"

**Themes Incluidos:**

1. **Minimal**
   - Colores: Blanco, negro, gris claro
   - Fuente: Inter, sans-serif
   - Espaciado: Generoso
   - Estilo: Limpio, espacios blancos

2. **Bold**
   - Colores: Vibrantes, contrastes fuertes
   - Fuente: Montserrat Bold
   - Estilo: Llamativo, youth-oriented
   - Botones grandes

3. **Elegant**
   - Colores: Negro, dorado, blanco
   - Fuente: Playfair Display (serif)
   - Estilo: Lujo, sofisticado
   - Bordes delgados

4. **Fresh**
   - Colores: Pasteles (mint, lavanda, peach)
   - Fuente: Poppins
   - Bordes: Muy redondeados
   - Estilo: Moderno, friendly

**Estructura de Theme:**
```
/themes/minimal/
  ├── theme.css (estilos completos)
  ├── preview.jpg (screenshot)
  └── config.json
```

**config.json:**
```json
{
  "name": "Minimal",
  "description": "Diseño limpio y minimalista",
  "author": "Tu nombre",
  "version": "1.0",
  "colors": {
    "primary": "#000000",
    "secondary": "#666666",
    "accent": "#ffffff"
  },
  "fonts": {
    "heading": "Inter",
    "body": "Inter"
  }
}
```

**Aplicación:**
- Al seleccionar theme, se actualiza `/config/theme.json`
- El sitio público lee este config y carga el CSS correspondiente
- No se modifica código PHP

### 4.10 Sistema de Backup

**Panel de Backup:**
- Botón "Crear Backup Ahora"
- Listado de backups existentes:
  - Fecha y hora
  - Tamaño del archivo
  - Botón Descargar
  - Botón Restaurar (con confirmación)
  - Botón Eliminar

**Contenido del Backup:**
- Todos los archivos JSON de `/data/`
- Configuraciones de `/config/`
- Opcionalmente: Imágenes (checkbox, puede ser pesado)

**Formato:**
- Archivo ZIP descargable
- Nombre: `backup_YYYY-MM-DD_HH-MM.zip`

**Automatización (Opcional):**
- Cron job para backup diario
- Mantener últimos 7 backups automáticamente

### 4.11 Modo Mantenimiento

**Configuración:**
- Switch On/Off
- Mensaje personalizable
- Imagen de fondo (opcional)
- Tiempo estimado (opcional)

**Comportamiento:**
- Usuarios ven página de mantenimiento
- Admin puede acceder normalmente (por IP o sesión)
- URL de bypass: `/index.php?bypass=tu-codigo-secreto`

**Página de Mantenimiento:**
- Logo del sitio
- Mensaje: "Estamos realizando mejoras"
- Tiempo estimado
- Ícono de herramientas animado
- Email de contacto

### 4.12 Configuración del Sitio

**Hero/Carrusel:**
- Upload de imagen principal
- Texto sobre la imagen (opcional)
- Link (opcional)

**Subtítulo:**
- Campo de texto editable para subtítulo del home

**Footer:**
- Editor de texto para contenido del footer
- Links de redes sociales
- Información de contacto

**Medios de Pago:**
- Access Token de Mercadopago (producción)
- Access Token de Mercadopago Sandbox (testing)
- Modo: Sandbox / Producción (toggle)
- Webhook URL (auto-generada, copiar)
- Habilitar/Deshabilitar pago presencial
- Instrucciones para pago presencial

**WhatsApp:**
- Número de WhatsApp (con código de país)
- Mensaje predeterminado
- Habilitar/Deshabilitar botón flotante

**Multi-Moneda:**
- Moneda principal: ARS
- Moneda secundaria: USD
- Tipo de cambio manual o API
- API recomendada: https://api.bluelytics.com.ar (para dólar blue)
- Actualización: Manual / Automática diaria

**Email/SMTP:**
- Email remitente (ej: ventas@tutienda.com)
- Nombre del remitente
- SMTP Host
- SMTP Port
- SMTP Usuario
- SMTP Password
- Email de admin (para recibir notificaciones)

**SEO General:**
- Título del sitio
- Descripción del sitio
- Keywords
- Google Analytics ID (opcional)
- Facebook Pixel (opcional)

---

## 5. ESPECIFICACIONES TÉCNICAS

### 5.1 Estructura JSON

**products.json** (Listado general)
```json
{
  "products": [
    {
      "id": "unique-id-123",
      "name": "Producto 1",
      "slug": "producto-1",
      "price_ars": 1000,
      "price_usd": 5,
      "stock": 15,
      "stock_alert": 5,
      "thumbnail": "/images/products/unique-id-123/1.jpg",
      "rating_avg": 4.5,
      "rating_count": 12,
      "active": true,
      "order": 1,
      "created_at": "2025-01-15T10:30:00Z"
    }
  ]
}
```

**products/{id}.json** (Detalle de producto)
```json
{
  "id": "unique-id-123",
  "name": "Producto 1",
  "slug": "producto-1",
  "description": "Descripción completa...",
  "price_ars": 1000,
  "price_usd": 5,
  "stock": 15,
  "stock_alert": 5,
  "active": true,
  "seo": {
    "title": "Producto 1 - Tu Tienda",
    "description": "Compra Producto 1 al mejor precio",
    "keywords": "producto, comprar, online"
  },
  "images": [
    {
      "url": "/images/products/unique-id-123/1.jpg",
      "alt": "Producto 1 vista frontal",
      "order": 1
    },
    {
      "url": "/images/products/unique-id-123/2.jpg",
      "alt": "Producto 1 detalle",
      "order": 2
    }
  ],
  "created_at": "2025-01-15T10:30:00Z",
  "updated_at": "2025-01-20T15:45:00Z"
}
```

**promotions.json**
```json
{
  "promotions": [
    {
      "id": "promo-1",
      "name": "Black Friday",
      "type": "percentage",
      "value": 25,
      "scope": "specific",
      "products": ["unique-id-123", "unique-id-456"],
      "min_purchase": 0,
      "start_date": "2025-11-20",
      "end_date": "2025-11-27",
      "active": true,
      "permanent": false
    }
  ]
}
```

**coupons.json**
```json
{
  "coupons": [
    {
      "id": "coupon-1",
      "code": "VERANO25",
      "type": "percentage",
      "value": 25,
      "min_purchase": 5000,
      "max_uses": 100,
      "uses_count": 43,
      "one_per_user": false,
      "start_date": "2025-01-01",
      "end_date": "2025-03-31",
      "applicable_to": "all",
      "products": [],
      "not_combinable": false,
      "active": true,
      "created_by": "admin",
      "created_at": "2024-12-15T10:00:00Z"
    }
  ]
}
```

**reviews.json**
```json
{
  "reviews": [
    {
      "id": "review-1",
      "product_id": "unique-id-123",
      "user_id": "user-123",
      "user_name": "Juan Pérez",
      "user_email": "juan@email.com",
      "rating": 5,
      "comment": "Excelente producto, muy buena calidad",
      "status": "approved",
      "verified_purchase": true,
      "created_at": "2025-01-15T16:30:00Z",
      "approved_at": "2025-01-15T18:00:00Z",
      "approved_by": "admin"
    }
  ]
}
```

**orders.json**
```json
{
  "orders": [
    {
      "id": "order-1",
      "order_number": "ORD-2025-00001",
      "user_id": "user-123",
      "date": "2025-01-15T14:30:00Z",
      "items": [
        {
          "product_id": "unique-id-123",
          "name": "Producto 1",
          "price_ars": 1000,
          "price_usd": 5,
          "quantity": 2,
          "promotion_applied": "promo-1",
          "final_price_ars": 1500,
          "final_price_usd": 7.5
        }
      ],
      "currency": "ARS",
      "subtotal": 2000,
      "discount_promotion": 500,
      "discount_coupon": 0,
      "coupon_code": null,
      "total": 1500,
      "status": "pending",
      "status_history": [
        {
          "status": "pending",
          "date": "2025-01-15T14:30:00Z",
          "user": "system"
        }
      ],
      "payment_method": "mercadopago",
      "payment_status": "pending",
      "payment_id": "mp-123456",
      "payment_link": "https://...",
      "tracking_number": null,
      "tracking_url": null,
      "tracking_token": "abc123def456",
      "shipping_address": {
        "name": "Juan Pérez",
        "address": "Calle 123",
        "city": "Buenos Aires",
        "postal_code": "1234",
        "phone": "1122334455"
      },
      "notes": "",
      "emails_sent": {
        "confirmation": true,
        "status_update": false
      }
    }
  ]
}
```

**wishlists.json**
```json
{
  "wishlists": [
    {
      "user_id": "user-123",
      "products": ["unique-id-123", "unique-id-456"],
      "updated_at": "2025-01-15T12:00:00Z"
    }
  ]
}
```

**visits.json**
```json
{
  "products": {
    "unique-id-123": {
      "total_visits": 245,
      "last_visit": "2025-01-15T18:45:00Z"
    }
  }
}
```

**config/theme.json**
```json
{
  "active_theme": "minimal",
  "updated_at": "2025-01-15T10:00:00Z",
  "updated_by": "admin"
}
```

**config/currency.json**
```json
{
  "primary": "ARS",
  "secondary": "USD",
  "exchange_rate": 200,
  "exchange_rate_source": "manual",
  "api_enabled": false,
  "last_update": "2025-01-15T10:00:00Z"
}
```

**config/maintenance.json**
```json
{
  "enabled": false,
  "message": "Estamos realizando mejoras. Volvemos pronto!",
  "estimated_time": "2 horas",
  "bypass_code": "secret123",
  "allowed_ips": ["127.0.0.1"]
}
```

### 5.2 Autenticación OAuth

**Google:**
- Usar Google Sign-In API
- Scope: email, profile
- Obtener: name, email

**Apple:**
- Usar Sign in with Apple
- Obtener: name, email

**Manual:**
- Registro con email y password
- Verificación de email (opcional pero recomendado)
- Password con mínimo 8 caracteres

### 5.3 Mercadopago Integration

**SDK:**
```php
// Usar SDK oficial de Mercadopago
require_once 'vendor/mercadopago/sdk/lib/mercadopago.php';
```

**Configuración:**
- Access Token de producción
- Access Token de sandbox
- Toggle para cambiar entre modos

**Crear Preferencia de Pago:**
```php
$preference = new MercadoPago\Preference();
$preference->items = [$item];
$preference->back_urls = [
  "success" => "https://tutienda.com/gracias.php",
  "failure" => "https://tutienda.com/error.php",
  "pending" => "https://tutienda.com/pendiente.php"
];
$preference->notification_url = "https://tutienda.com/webhook.php";
$preference->save();
```

**Webhook:**
- Validar firma de Mercadopago (x-signature header)
- Verificar tipo de notificación
- Actualizar estado de orden
- Enviar email de confirmación
- Ajustar stock

**Manejo de Estados:**
- `approved` → Orden cobrada, reducir stock
- `rejected` → Orden rechazada, restaurar stock, notificar usuario
- `pending` → En proceso
- `cancelled` → Cancelada por usuario, restaurar stock

### 5.4 Sistema de Emails

**PHPMailer Setup:**
```php
require 'vendor/phpmailer/phpmailer/PHPMailer.php';
require 'vendor/phpmailer/phpmailer/SMTP.php';

$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;
$mail->Username = 'tu-email@gmail.com';
$mail->Password = 'tu-app-password';
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port = 587;
```

**Emails a Enviar:**

1. **Cliente - Confirmación de Orden:**
   - Subject: "Confirmación de pedido #{order_number}"
   - Contenido: Resumen del pedido, total, link de tracking

2. **Cliente - Cambio de Estado:**
   - Subject: "Tu pedido ha sido {nuevo_estado}"
   - Contenido: Actualización, siguiente paso

3. **Cliente - Envío con Tracking:**
   - Subject: "Tu pedido está en camino"
   - Contenido: Número de tracking, link de seguimiento

4. **Admin - Nueva Orden:**
   - Subject: "Nueva orden recibida - #{order_number}"
   - Contenido: Resumen rápido, link a admin

5. **Admin - Stock Bajo:**
   - Subject: "Alerta: Stock bajo en {product_name}"
   - Contenido: Producto, stock actual, link a producto

6. **Cliente - Review Aprobado:**
   - Subject: "Tu opinión ha sido publicada"
   - Contenido: Agradecimiento, link al producto

**Templates:**
- Usar HTML con CSS inline
- Responsive email templates
- Logo de la tienda
- Colores de la marca

### 5.5 .htaccess para /data/passwords/

```apache
Order Deny,Allow
Deny from all
```

### 5.6 Seguridad - Implementación

**Rate Limiting:**
```php
// Archivo: includes/rate_limit.php
function check_rate_limit($identifier, $max_attempts = 5, $period = 900) {
    $file = "data/rate_limits/{$identifier}.json";
    // Lógica de rate limiting
    // Retorna true si está permitido, false si excede límite
}
```

**CSRF Protection:**
```php
// Generar token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Validar token
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die('CSRF token inválido');
}
```

**Upload Validation:**
```php
function validate_image_upload($file) {
    // Verificar tipo MIME real
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowed)) {
        return false;
    }
    
    // Verificar tamaño
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB
        return false;
    }
    
    return true;
}
```

**HTTPS Enforcement:**
```php
// En inicio de cada página pública
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}
```

**Security Headers:**
```php
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000');
```

**File Locking (para JSON):**
```php
function write_json($file, $data) {
    $fp = fopen($file, 'w');
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}
```

---

## 6. DISEÑO Y UX

### 6.1 Principios de Diseño

- **Moderno y limpio**: Espacios blancos, tipografía clara
- **Mobile First**: Optimizado para móviles primero
- **Accesibilidad**: Contrastes adecuados, textos alt en imágenes
- **Performance**: Imágenes optimizadas, lazy loading
- **Themeable**: Sistema completamente personalizable sin tocar código
- **Touch-friendly**: Botones grandes, gestures intuitivos
- **Fast feedback**: Animaciones rápidas, loading states claros
- **Progressive enhancement**: Funciona sin JS, mejor con JS

### 6.2 Componentes Clave

**Hero Image:**
- Full width, height responsive
- Texto sobre imagen (opcional)
- Call-to-action button

**Product Cards:**
- Hover effects, transiciones suaves
- Badges (promoción, sin stock, nuevo)
- Rating con estrellas
- Botón de favorito

**Carrito:**
- Sidebar o modal en desktop
- Bottom sheet en mobile
- Persistente en sesión
- Contador visible en header

**Botones CTA:**
- Destacados, consistentes
- Estados: normal, hover, active, disabled
- Loading state con spinner

**Formularios:**
- Validación en tiempo real
- Mensajes claros de error
- Labels animados
- Autocomplete cuando corresponde

**WhatsApp Button Flotante:**
- Posición: Fixed bottom-right
- Icono de WhatsApp verde
- Animación de pulso suave
- Tooltip al hover: "Consultanos"
- Click abre WhatsApp Web/App

**Lightbox de Imágenes:**
- Overlay oscuro (80% opacidad)
- Imagen centrada, max-width 90vw
- Flechas de navegación grandes
- Botón cerrar (X) top-right
- Swipe para navegar en mobile
- ESC para cerrar

**Toast Notifications:**
- Para mensajes temporales
- Position: top-right
- Auto-dismiss en 3 segundos
- Tipos: success (verde), error (rojo), info (azul)

**Skeleton Loaders:**
- Para carga de productos
- Animación shimmer
- Mantiene layout durante carga

**Bottom Sheet (Mobile):**
- Para carrito en mobile
- Desliza desde abajo
- Overlay al fondo
- Handle para arrastrar
- Snap positions: cerrado, medio, completo

### 6.3 Sistema de Themes

Cada theme define:
- Colores primarios, secundarios, accent
- Fuentes para headings y body
- Espaciados (padding, margins)
- Border radius
- Tamaño de botones
- Estilo de cards (shadow, border)

**Implementación:**
```html
<!-- En header.php -->
<?php
$theme_config = json_decode(file_get_contents('config/theme.json'), true);
$active_theme = $theme_config['active_theme'];
?>
<link rel="stylesheet" href="/themes/<?= $active_theme ?>/theme.css">
```

---

## 7. CASOS DE USO

### 7.1 Usuario Compra un Producto con Promoción y Cupón

1. Usuario navega el sitio
2. Ve producto con promoción destacada y rating de estrellas
3. Click en producto para ver detalles
4. Ve fotos en lightbox, lee reviews
5. Click en "Agregar al carrito"
6. Tooltip confirma "Agregado al carrito ✓"
7. Va al carrito, ve el descuento de promoción aplicado
8. Ingresa cupón "VERANO25" → Descuento adicional aplicado
9. Procede al checkout
10. Login con Google
11. Completa datos de envío
12. Selecciona moneda (ARS)
13. Selecciona Mercadopago
14. Es redirigido al link de pago (sandbox en testing)
15. Paga exitosamente
16. Webhook valida pago y actualiza estado
17. Stock se reduce automáticamente
18. Recibe email de confirmación con link de tracking
19. Admin recibe notificación de nueva orden

### 7.2 Admin Crea una Promoción

1. Admin ingresa al backoffice
2. Navega a "Promociones"
3. Click en "Nueva Promoción"
4. Completa formulario:
   - Nombre: "Verano 2025"
   - Tipo: 20% de descuento
   - Productos: Selecciona 5 productos
   - Período: 01/01/2025 - 31/03/2025
5. Guarda la promoción
6. Promoción aparece como "Activa" en el listado
7. Productos seleccionados muestran la promo en el sitio público inmediatamente

### 7.3 Cliente Retira en Local

1. Usuario agrega productos al carrito
2. Procede al checkout
3. Selecciona "Retiro en local / Pago presencial"
4. Orden se genera sin pago online
5. Usuario recibe email de confirmación con:
   - Número de orden
   - Resumen de productos
   - Instrucciones de retiro
   - Link de tracking
6. Admin ve la orden como "Pendiente"
7. Cliente retira en local y paga
8. Admin actualiza estado a "Cobrada" desde el listado
9. Cliente recibe email de actualización de estado

### 7.4 Usuario Agrega Producto a Favoritos

1. Usuario navega productos
2. Click en ícono de corazón en un producto
3. Corazón se llena (animación)
4. Tooltip: "Agregado a favoritos ✓"
5. Contador en header se actualiza "❤️ (3)"
6. Usuario puede ir a "/favoritos.php" para ver lista
7. Desde favoritos puede agregar al carrito o eliminar

### 7.5 Cliente Consulta Estado de Pedido

1. Cliente recibe email con link de tracking
2. Click en link → Redirige a `/pedido.php?id=ORD-123&token=abc...`
3. Ve timeline del pedido:
   - ✅ Pendiente (15/01 14:30)
   - ✅ Cobrada (15/01 15:00)
   - 🚚 Enviada (16/01 10:00) ← Estado actual
   - ⏳ Entregada (pendiente)
4. Ve número de tracking: CA123456789AR
5. Link a Correo Argentino para tracking externo
6. Información de contacto si tiene dudas

### 7.6 Admin Cambia Theme del Sitio

1. Admin ingresa a backoffice
2. Navega a "Themes"
3. Ve 4 themes con preview
4. Click en "Bold"
5. Modal muestra preview más grande
6. Click en "Aplicar Theme"
7. Confirmación: "¿Cambiar al theme Bold?"
8. Confirma
9. Sistema actualiza `/config/theme.json`
10. Mensaje de éxito: "Theme aplicado correctamente"
11. Link "Ver sitio" para verificar
12. Sitio público ahora usa theme Bold

### 7.7 Admin Crea Cupón de Descuento

1. Admin ingresa a backoffice
2. Navega a "Cupones"
3. Click en "Nuevo Cupón"
4. Completa:
   - Código: PRIMERACOMPRA
   - Descuento: 15%
   - Compra mínima: $2000
   - Límite: 1 uso por usuario
   - Válido hasta: 31/12/2025
5. Guarda
6. Cupón aparece como "Activo"
7. Clientes pueden usar "PRIMERACOMPRA" en checkout

### 7.8 Cliente Deja Review

1. Cliente compra producto
2. Días después, visita página del producto
3. Ve formulario de review (porque está logueado y compró el producto)
4. Selecciona 5 estrellas
5. Escribe: "Excelente calidad, muy satisfecho"
6. Submit
7. Mensaje: "Gracias! Tu opinión será revisada"
8. Admin recibe notificación
9. Admin aprueba review desde backoffice
10. Review aparece en página del producto
11. Cliente recibe email: "Tu opinión ha sido publicada"

### 7.9 Admin Recibe Alerta de Stock Bajo

1. Cliente compra último producto en stock
2. Stock baja de 5 unidades (umbral configurado)
3. Sistema envía email a admin:
   - "Alerta: Stock bajo en Producto X"
   - Stock actual: 4 unidades
   - Link directo al producto en admin
4. Admin entra al backoffice
5. Dashboard muestra alerta roja: "3 productos con stock bajo"
6. Admin navega a "Productos"
7. Filtra por "Stock bajo"
8. Ve productos que necesitan reposición
9. Edita productos y actualiza stock

### 7.10 Admin Crea Backup

1. Admin ingresa a backoffice
2. Navega a "Backup"
3. Ve último backup: "7 días atrás" (alerta naranja)
4. Click en "Crear Backup Ahora"
5. Progress bar mientras se genera
6. Backup completado: "backup_2025-01-15_10-30.zip (2.3 MB)"
7. Botón "Descargar" disponible
8. Admin descarga ZIP
9. Mensaje: "Backup creado exitosamente"

---

## 8. REQUERIMIENTOS NO FUNCIONALES

### 8.1 Performance

**Carga y Optimización:**
- Carga inicial < 3 segundos
- Imágenes optimizadas (WebP + fallback)
- Lazy loading de imágenes
- Minificación de CSS/JS
- Critical CSS inline en `<head>` para above-the-fold
- Preload fonts: `<link rel="preload">` para fuentes principales
- Defer JS: Todo el JS con `defer` excepto crítico
- Service Worker (opcional): Para cache offline

**Compresión y Cache:**
```apache
# .htaccess
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/plain text/css text/javascript
</IfModule>

<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType image/jpg "access plus 1 year"
  ExpiresByType image/jpeg "access plus 1 year"
  ExpiresByType image/png "access plus 1 year"
  ExpiresByType image/webp "access plus 1 year"
  ExpiresByType text/css "access plus 1 month"
  ExpiresByType text/javascript "access plus 1 month"
</IfModule>
```

### 8.2 Compatibilidad

**Navegadores:**
- Chrome, Firefox, Safari, Edge (últimas 2 versiones)

**Dispositivos:**
- Desktop, Tablet, Mobile

**Resoluciones:**
- Desde 320px hasta 4K

**Testing Específico:**
- iOS Safari: Gestures y bottom sheet
- Android Chrome: PWA capabilities
- Touch events: Fully supported
- Keyboard navigation: Accesibilidad con Tab

### 8.3 Escalabilidad

**Capacidad:**
- Hasta 20 productos inicialmente (sistema preparado para más)
- Hasta 10 fotos por producto
- JSON files con estructura extensible

**Consideraciones:**
- JSON suficiente para < 50-100 productos
- File locking para evitar corrupción en escrituras simultáneas
- Monitoreo de tamaño de archivos JSON
- Índices en memoria para búsquedas rápidas

### 8.4 Mantenibilidad

**Documentación:**
- Código comentado
- Estructura modular
- Documentación de funciones principales
- README con instrucciones de deployment

**Logs y Monitoreo:**
- Logs estructurados: Por tipo (error, warning, info)
- Versionado de JSON: Cada cambio registra quién y cuándo
- Rollback capability: Restaurar desde backup fácilmente
- Health check endpoint: `/health.php` para monitoreo
- Admin action logs: Auditoría de cambios críticos

---

## 9. ROADMAP DE DESARROLLO

### Fase 1: Setup y Core (Semana 1-2)
- [ ] Estructura de directorios y archivos
- [ ] Sistema de autenticación (manual)
- [ ] CRUD de productos (backend)
- [ ] Sistema de inventario y control de stock
- [ ] Lectura/escritura de JSON con file locking
- [ ] Rate limiting básico

### Fase 2: Frontend Público Básico (Semana 2-3)
- [ ] Página principal con grid de productos
- [ ] Indicadores de stock y ratings
- [ ] Página de detalle de producto
- [ ] Lightbox para galería de imágenes
- [ ] Búsqueda y filtros
- [ ] Carrito de compras
- [ ] Sistema de favoritos/wishlist
- [ ] Sistema de checkout básico
- [ ] Responsive design base

### Fase 3: Mobile Experience (Semana 3)
- [ ] Touch gestures en galería
- [ ] Bottom sheet para carrito en mobile
- [ ] Sticky buttons
- [ ] Teclados contextuales
- [ ] Menu hamburguesa mejorado
- [ ] Testing exhaustivo en dispositivos reales

### Fase 4: Sistema de Themes (Semana 4)
- [ ] Diseño de 4 themes completos
  - [ ] Minimal
  - [ ] Bold
  - [ ] Elegant
  - [ ] Fresh
- [ ] Panel de selección de themes en admin
- [ ] Preview de themes
- [ ] Sistema de aplicación de themes

### Fase 5: Backoffice Core (Semana 4-5)
- [ ] Login admin con rate limiting
- [ ] Dashboard con métricas
- [ ] Gestión de productos (UI)
- [ ] Alertas de stock
- [ ] Gestión de ventas (UI)
- [ ] Email por cambio de estado
- [ ] Configuración del sitio

### Fase 6: Características Avanzadas (Semana 5-6)
- [ ] Sistema de promociones
- [ ] Sistema de cupones
- [ ] Gestión de reviews
- [ ] OAuth (Google y Apple)
- [ ] Hero image editable
- [ ] Reordenamiento drag & drop
- [ ] SEO por producto
- [ ] Multi-moneda

### Fase 7: Email System (Semana 6)
- [ ] Setup de PHPMailer
- [ ] Templates de emails HTML
- [ ] Emails de confirmación de orden
- [ ] Emails de cambio de estado
- [ ] Emails de stock bajo a admin
- [ ] Emails de reviews aprobados
- [ ] Testing de envío de emails

### Fase 8: Integración Mercadopago (Semana 7)
- [ ] SDK de Mercadopago
- [ ] Configuración sandbox/producción
- [ ] Crear preferencias de pago
- [ ] Webhook con validación de firma
- [ ] Manejo de todos los estados de pago
- [ ] Reintentos de pago rechazado
- [ ] Testing en sandbox

### Fase 9: Seguridad Reforzada (Semana 7-8)
- [ ] Implementar CSRF tokens
- [ ] Validación exhaustiva de uploads
- [ ] HTTPS enforcement
- [ ] Security headers
- [ ] Logs de admin
- [ ] Penetration testing básico

### Fase 10: Features Complementarias (Semana 8)
- [ ] Sistema de tracking de pedidos
- [ ] WhatsApp button flotante
- [ ] Últimos productos vistos
- [ ] Contador de visitas
- [ ] Modo mantenimiento
- [ ] Sistema de backup

### Fase 11: Testing y Optimización (Semana 9)
- [ ] Testing de flujos completos
- [ ] Testing en múltiples dispositivos reales
- [ ] Testing de emails en diferentes clientes
- [ ] Load testing (simular múltiples usuarios)
- [ ] Optimización de performance
- [ ] Lighthouse audit (90+ score)
- [ ] Fix de bugs críticos

### Fase 12: Deploy y Documentación (Semana 10)
- [ ] Deployment vía FTP a servidor de producción
- [ ] Configuración de emails (SMTP)
- [ ] Configuración de Mercadopago producción
- [ ] Primer backup manual
- [ ] Documentación final actualizada
- [ ] Manual de usuario para admin
- [ ] Video tutorial básico
- [ ] Capacitación al cliente
- [ ] Checklist de go-live

---

## 10. CRITERIOS DE ACEPTACIÓN

### Para Sitio Público:
- ✅ Se visualiza correctamente en mobile, tablet y desktop
- ✅ Hero image se carga desde configuración del admin
- ✅ Productos se muestran con precio y promoción aplicada
- ✅ Sistema de themes cambia el look sin tocar código
- ✅ Lightbox de imágenes funciona en todos los dispositivos
- ✅ Búsqueda retorna resultados relevantes
- ✅ Filtros funcionan correctamente
- ✅ Favoritos persisten correctamente
- ✅ Carrito funciona correctamente
- ✅ Códigos de cupón aplican descuentos correctamente
- ✅ Reviews aprobados se muestran en productos
- ✅ Checkout completa órdenes exitosamente
- ✅ Tracking de pedido funciona con link único
- ✅ URLs de productos son compartibles
- ✅ WhatsApp button abre chat correctamente
- ✅ Multi-moneda muestra precios en ARS y USD
- ✅ Touch gestures funcionan en mobile
- ✅ Bottom sheet de carrito es smooth en mobile
- ✅ Indicadores de stock son precisos

### Para Backoffice:
- ✅ Login seguro con usuario/password
- ✅ Rate limiting bloquea intentos de brute force
- ✅ CSRF tokens previenen ataques
- ✅ Dashboard muestra métricas actualizadas
- ✅ Alertas de stock bajo funcionan correctamente
- ✅ Se pueden crear, editar y eliminar productos
- ✅ Se pueden crear, editar y eliminar promociones
- ✅ Sistema de cupones crea y valida cupones correctamente
- ✅ Reviews se pueden aprobar/rechazar
- ✅ Themes se pueden cambiar desde admin
- ✅ Estado de ventas es editable desde el listado
- ✅ Se puede exportar ventas a CSV
- ✅ Hero image es configurable
- ✅ Validación de uploads rechaza archivos inválidos
- ✅ Backup crea archivos ZIP descargables
- ✅ Modo mantenimiento muestra página personalizada
- ✅ SEO por producto guarda correctamente
- ✅ Multi-moneda se configura fácilmente
- ✅ Logs de admin registran acciones críticas

### Para Emails:
- ✅ Email de confirmación de orden se envía correctamente
- ✅ Email de cambio de estado se envía al actualizar
- ✅ Email a admin por nueva orden funciona
- ✅ Email de stock bajo se envía al alcanzar umbral
- ✅ Email de review aprobado llega al usuario
- ✅ Templates HTML se ven bien en Gmail, Outlook, Apple Mail
- ✅ Links en emails funcionan correctamente

### Para Mercadopago:
- ✅ Modo sandbox funciona para testing
- ✅ Link de pago se genera correctamente
- ✅ Webhook valida firma de Mercadopago
- ✅ Pago aprobado actualiza estado y reduce stock
- ✅ Pago rechazado restaura stock y notifica usuario
- ✅ Todos los estados se manejan correctamente

### Para Sistema General:
- ✅ Passwords almacenados con hash
- ✅ Carpeta /data/passwords/ inaccesible vía web
- ✅ File locking previene corrupción de JSON
- ✅ Security headers están configurados
- ✅ HTTPS redirect funciona
- ✅ Logs estructurados se generan correctamente
- ✅ Health check endpoint responde
- ✅ No hay errores de PHP en producción
- ✅ Sistema funciona solo con acceso FTP
- ✅ JSON files se actualizan correctamente
- ✅ Performance: Lighthouse score > 90
- ✅ Sin errores de consola en navegador
- ✅ Sistema funciona sin JavaScript (degradación graciosa)

---

## 11. ANEXOS

### 11.1 Glosario

- **Hero Image**: Imagen principal grande en la parte superior de la página
- **OAuth**: Protocolo de autenticación con servicios de terceros
- **Hash**: Función criptográfica unidireccional para passwords
- **Webhook**: Notificación automática de eventos (ej: pago confirmado)
- **Drag & Drop**: Arrastrar y soltar elementos con el mouse
- **Theme**: Conjunto de estilos CSS que define la apariencia del sitio
- **Lightbox**: Modal de pantalla completa para ver imágenes ampliadas
- **Bottom Sheet**: Panel que desliza desde abajo en mobile
- **Rate Limiting**: Restricción de intentos para prevenir abuso
- **CSRF**: Cross-Site Request Forgery, tipo de ataque web
- **Sandbox**: Ambiente de pruebas aislado (ej: Mercadopago Sandbox)
- **File Locking**: Mecanismo para prevenir escrituras simultáneas
- **Slug**: URL amigable (ej: "remera-azul" en vez de "?id=123")
- **Stock Alert**: Notificación cuando inventario es bajo
- **Backup**: Copia de seguridad de datos
- **Health Check**: Endpoint para verificar estado del sistema

### 11.2 Referencias

- PHP Manual: https://www.php.net/manual/
- Mercadopago API: https://www.mercadopago.com.ar/developers/
- Mercadopago SDK PHP: https://github.com/mercadopago/sdk-php
- Google Sign-In: https://developers.google.com/identity/sign-in/web
- Apple Sign In: https://developer.apple.com/sign-in-with-apple/
- PHPMailer: https://github.com/PHPMailer/PHPMailer
- Bluelytics API (dólar): https://api.bluelytics.com.ar
- Web.dev Performance: https://web.dev/performance/
- OWASP Security: https://owasp.org/www-project-web-security-testing-guide/
- Can I Use (compatibility): https://caniuse.com/

### 11.3 Checklist de Go-Live

**Pre-lanzamiento:**
- [ ] Todos los tests pasados
- [ ] Lighthouse score > 90
- [ ] No hay errores en consola
- [ ] Probado en Chrome, Firefox, Safari, Edge
- [ ] Probado en iOS y Android
- [ ] Emails funcionando en producción
- [ ] SMTP configurado correctamente
- [ ] Mercadopago en modo PRODUCCIÓN
- [ ] Webhook URL configurada en Mercadopago
- [ ] SSL certificado instalado y funcionando
- [ ] DNS apuntando correctamente
- [ ] Backup inicial creado
- [ ] .htaccess configurado
- [ ] Permisos de archivos correctos (755 dirs, 644 files)
- [ ] data/passwords/ inaccesible
- [ ] Credenciales de admin seguras
- [ ] Google Analytics instalado (si aplica)
- [ ] Robots.txt configurado
- [ ] Sitemap.xml generado
- [ ] Favicon presente
- [ ] Open Graph tags configurados
- [ ] Formularios testeados con datos reales
- [ ] Proceso de compra completo testeado
- [ ] Emails de confirmación verificados
- [ ] WhatsApp button testeado
- [ ] Modo mantenimiento probado
- [ ] Documentación entregada al cliente

**Post-lanzamiento:**
- [ ] Monitorear primeras 24 horas
- [ ] Verificar emails llegando correctamente
- [ ] Verificar pagos procesándose correctamente
- [ ] Revisar logs por errores
- [ ] Backup automático funcionando
- [ ] Google Search Console submitido
- [ ] Primeras órdenes procesadas sin issues

---

## 12. NOTAS PARA DESARROLLO

### 12.1 Comandos de Setup Inicial

```bash
# Crear estructura de directorios completa
mkdir -p data/{products,backups,passwords,rate_limits}
mkdir -p themes/{minimal,bold,elegant,fresh}
mkdir -p images/{products,hero,themes}
mkdir -p vendor/{phpmailer,mercadopago}

# Configurar permisos
chmod 755 data/
chmod 644 data/*.json
chmod 700 data/passwords/

# Crear .htaccess en data/passwords/
echo "Order Deny,Allow\nDeny from all" > data/passwords/.htaccess
```

### 12.2 Orden de Implementación Recomendado

1. **Estructura base y seguridad** (Semana 1)
   - Directorios, .htaccess, rate limiting, CSRF

2. **Sistema de inventario** (Semana 1-2)
   - JSON con stock, file locking, alertas

3. **Frontend público básico** (Semana 2-3)
   - Home, producto, carrito con stock check

4. **Mobile optimizations** (Semana 3)
   - Touch gestures, bottom sheet, responsive mejorado

5. **Sistema de themes** (Semana 4)
   - Diseñar 4 themes, panel admin, aplicación

6. **Búsqueda y filtros** (Semana 3-4)
   - AJAX search, filtros, ordenamiento

7. **Favoritos/Wishlist** (Semana 4)
   - localStorage + JSON server-side

8. **Backoffice extendido** (Semana 4-5)
   - Dashboard con alertas, gestión mejorada

9. **Sistema de reviews** (Semana 5)
   - CRUD reviews, aprobación, display

10. **Cupones** (Semana 5-6)
    - CRUD cupones, validación en checkout

11. **SEO básico** (Semana 6)
    - Meta tags, slugs, alt texts

12. **Email system** (Semana 6-7)
    - PHPMailer setup, templates, triggers

13. **Mercadopago avanzado** (Semana 7)
    - Sandbox, webhook seguro, manejo estados

14. **Features complementarias** (Semana 8)
    - Tracking, WhatsApp, últimos vistos, backup, mantenimiento

15. **Multi-moneda** (Semana 8)
    - Configuración, conversión, display

16. **Testing exhaustivo** (Semana 9)
    - Todos los flujos, devices, emails, pagos

17. **Optimización** (Semana 9)
    - Performance, Lighthouse, minificación

18. **Deploy y documentación** (Semana 10)
    - FTP, configuración producción, capacitación

### 12.3 Consideraciones Técnicas

**Restricciones del Entorno:**
- No usar base de datos SQL (JSON suficiente para 20-50 productos)
- Todo debe funcionar vía FTP (no SSH, no composer en server)
- PHP puro sin frameworks
- JavaScript vanilla (no jQuery, React, etc.)

**Librerías Externas:**
- PHPMailer: Subir vía FTP (no usar composer en server)
- Mercadopago SDK: Download y subir vía FTP
- Themes: CSS compilado localmente si usas SASS/LESS
- Imágenes: Optimizar antes de subir (tinypng.com)

**Desarrollo Local:**
- XAMPP o similar para desarrollar localmente
- Git para control de versiones
- .gitignore para no subir credenciales

**File Locking:**
- Usar `flock()` en todas las operaciones de escritura JSON
- Previene corrupción de datos en accesos simultáneos

### 12.4 Estructura de Librerías (subir vía FTP)

```
/vendor/
  ├── phpmailer/
  │   └── phpmailer/
  │       ├── PHPMailer.php
  │       ├── SMTP.php
  │       └── Exception.php
  └── mercadopago/
      └── sdk/
          └── lib/
              └── mercadopago.php
```

### 12.5 Archivo de Credenciales (NO subir a Git)

```php
// config/credentials.php (crear localmente, subir vía FTP)
<?php
return [
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'username' => 'tu-email@gmail.com',
        'password' => 'tu-app-password',
        'port' => 587
    ],
    'mercadopago' => [
        'access_token_sandbox' => 'TEST-xxx',
        'access_token_prod' => 'APP_USR-xxx'
    ],
    'admin' => [
        'email' => 'admin@tutienda.com'
    ]
];
```

### 12.6 Performance Checklist

- [ ] Imágenes en WebP con fallback JPG
- [ ] Lazy loading en todas las imágenes
- [ ] CSS minificado
- [ ] JS minificado y con defer
- [ ] Critical CSS inline
- [ ] Gzip habilitado en .htaccess
- [ ] Browser caching configurado
- [ ] No más de 50 requests por página
- [ ] Tamaño de página < 2MB
- [ ] Tiempo de carga < 3s en 3G

### 12.7 Security Checklist

- [ ] Rate limiting en login
- [ ] CSRF tokens en todos los forms
- [ ] Validación de uploads estricta
- [ ] HTTPS enforced
- [ ] Security headers configurados
- [ ] Passwords con hash fuerte
- [ ] data/passwords/ bloqueado
- [ ] Logs de acciones críticas
- [ ] Sanitización de inputs
- [ ] No exposición de errores PHP en producción
- [ ] Webhook con validación de firma

---

**Fecha de Creación**: Noviembre 2025  
**Autor**: [Tu nombre/empresa]  
**Estado**: Listo para desarrollo  
**Timeline Estimado**: 10 semanas  
**Stack**: PHP + JSON + PHPMailer + Mercadopago SDK  
**Deployment**: FTP only
