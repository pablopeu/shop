# Sistema de Notificaciones

Sistema completo de notificaciones por email y Telegram para eventos del e-commerce.

## 📋 Tabla de Contenidos

- [Características](#características)
- [Configuración Email](#configuración-email)
- [Configuración Telegram](#configuración-telegram)
- [Notificaciones Implementadas](#notificaciones-implementadas)
- [Templates de Email](#templates-de-email)
- [Integración en el Código](#integración-en-el-código)
- [Testing](#testing)

## 🎯 Características

### Email
- ✅ Soporte para PHP `mail()` nativo
- ✅ Soporte SMTP (implementación básica)
- ✅ Templates HTML profesionales con diseño responsive
- ✅ Emails multipart (HTML + texto plano)
- ✅ Configuración granular de notificaciones
- ✅ Branding personalizable
- ✅ Mensajes específicos por estado de pago

### Telegram
- ✅ Integración con Telegram Bot API
- ✅ Notificaciones a canal/chat específico
- ✅ Formato HTML con emojis
- ✅ Alertas críticas destacadas
- ✅ Control granular de notificaciones
- ✅ Umbral configurable para órdenes de alto valor

## ⚙️ Configuración Email

### Archivo: `config/email.json`

```json
{
  "enabled": true,
  "method": "mail",
  "from_email": "noreply@tienda.com",
  "from_name": "Mi Tienda",
  "admin_email": "admin@tienda.com",
  "smtp": {
    "host": "smtp.gmail.com",
    "port": 587,
    "username": "",
    "password": "",
    "encryption": "tls"
  },
  "notifications": {
    "customer": {
      "order_created": true,
      "payment_approved": true,
      "payment_rejected": true,
      "payment_pending": true,
      "order_shipped": true,
      "order_delivered": true,
      "chargeback_notice": true
    },
    "admin": {
      "new_order": true,
      "payment_approved": true,
      "payment_rejected": true,
      "chargeback_alert": true,
      "low_stock_alert": true
    }
  }
}
```

### Parámetros

| Parámetro | Descripción | Valores |
|-----------|-------------|---------|
| `enabled` | Activa/desactiva el sistema de emails | `true`, `false` |
| `method` | Método de envío | `"mail"`, `"smtp"` |
| `from_email` | Email remitente | Email válido |
| `from_name` | Nombre del remitente | Texto |
| `admin_email` | Email del administrador | Email válido |
| `notifications.customer.*` | Controla emails a clientes | `true`, `false` |
| `notifications.admin.*` | Controla emails a admin | `true`, `false` |

### Configuración SMTP

Para usar Gmail u otro proveedor SMTP:

1. Actualiza `method` a `"smtp"`
2. Configura los datos SMTP:

```json
{
  "method": "smtp",
  "smtp": {
    "host": "smtp.gmail.com",
    "port": 587,
    "username": "tu-email@gmail.com",
    "password": "tu-app-password",
    "encryption": "tls"
  }
}
```

**Nota:** Para Gmail, debes usar una "App Password", no tu contraseña normal.

## 🤖 Configuración Telegram

### Archivo: `config/telegram.json`

```json
{
  "enabled": false,
  "bot_token": "",
  "chat_id": "",
  "notifications": {
    "new_order": true,
    "payment_approved": true,
    "payment_rejected": false,
    "chargeback_alert": true,
    "low_stock_alert": true,
    "high_value_order": true,
    "high_value_threshold": 50000
  }
}
```

### Cómo Obtener Bot Token y Chat ID

#### 1. Crear un Bot

1. Abre Telegram y busca [@BotFather](https://t.me/BotFather)
2. Envía `/newbot`
3. Sigue las instrucciones (nombre y username)
4. Recibirás tu **Bot Token** (ej: `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`)
5. Copia el token a `bot_token` en la configuración

#### 2. Obtener Chat ID

**Opción A - Chat Personal:**
1. Abre Telegram y busca tu bot por su username
2. Envía `/start` al bot
3. Abre en tu navegador: `https://api.telegram.org/bot<TU_BOT_TOKEN>/getUpdates`
4. Busca el campo `"chat":{"id":123456789}`
5. Copia ese número a `chat_id`

**Opción B - Grupo/Canal:**
1. Agrega tu bot al grupo/canal
2. Envía un mensaje mencionando al bot
3. Abre: `https://api.telegram.org/bot<TU_BOT_TOKEN>/getUpdates`
4. El `chat_id` será negativo (ej: `-1001234567890`)

#### 3. Activar

```json
{
  "enabled": true,
  "bot_token": "123456789:ABCdefGHIjklMNOpqrsTUVwxyz",
  "chat_id": "123456789"
}
```

### Parámetros

| Parámetro | Descripción | Valores |
|-----------|-------------|---------|
| `enabled` | Activa/desactiva Telegram | `true`, `false` |
| `bot_token` | Token del bot de Telegram | String de BotFather |
| `chat_id` | ID del chat/canal destino | Número (puede ser negativo) |
| `notifications.*` | Tipos de notificaciones | `true`, `false` |
| `high_value_threshold` | Monto para "orden de alto valor" | Número (en moneda local) |

## 📬 Notificaciones Implementadas

### Notificaciones al Cliente (Email)

#### 1. **Order Confirmation** - Confirmación de Pedido
- **Trigger:** Orden creada exitosamente
- **Template:** `templates/email/order_confirmation.php`
- **Contenido:**
  - Número de orden
  - Resumen de productos
  - Total a pagar
  - Dirección de envío
  - Link de seguimiento
  - Estado: Pendiente de pago

#### 2. **Payment Approved** - Pago Aprobado
- **Trigger:** Pago procesado exitosamente
- **Template:** `templates/email/payment_approved.php`
- **Contenido:**
  - Confirmación de pago
  - ID de pago de Mercadopago
  - Detalles del método de pago (tarjeta, cuotas)
  - Productos comprados
  - Próximos pasos (preparación de envío)

#### 3. **Payment Rejected** - Pago Rechazado
- **Trigger:** Pago rechazado por Mercadopago
- **Template:** `templates/email/payment_rejected.php`
- **Contenido:**
  - Motivo del rechazo (mensaje específico)
  - Sugerencias de acción
  - Link para reintentar
  - Pasos para resolver el problema

#### 4. **Payment Pending** - Pago Pendiente
- **Trigger:** Pago en proceso/pendiente
- **Template:** `templates/email/payment_pending.php`
- **Contenido:**
  - Estado de pago pendiente
  - Explicación del proceso
  - Timeline de pasos siguientes
  - Tiempo estimado de confirmación

#### 5. **Order Shipped** - Pedido Enviado
- **Trigger:** Admin marca orden como "enviada"
- **Template:** `templates/email/order_shipped.php`
- **Contenido:**
  - Confirmación de envío
  - Número de tracking
  - Empresa de transporte
  - Dirección de destino
  - Link para rastrear envío

### Notificaciones al Administrador

#### 1. **New Order** - Nueva Orden (Email + Telegram)
- **Trigger:** Orden creada
- **Templates:**
  - Email: `templates/email/admin_new_order.php`
  - Telegram: `send_telegram_new_order()`
- **Contenido:**
  - Número de orden
  - Total (destacado si es alto valor)
  - Datos del cliente
  - Productos vendidos
  - Método de pago
  - Link al panel de admin

#### 2. **Payment Approved** - Pago Aprobado (Telegram)
- **Trigger:** Pago confirmado
- **Template:** `send_telegram_payment_approved()`
- **Contenido:**
  - Orden aprobada para procesar
  - Monto
  - Método de pago
  - Indicador si es orden de alto valor 🌟
  - ID de pago de MP

#### 3. **Payment Rejected** - Pago Rechazado (Telegram opcional)
- **Trigger:** Pago rechazado
- **Template:** `send_telegram_payment_rejected()`
- **Contenido:**
  - Orden rechazada
  - Cliente afectado
  - Motivo de rechazo
- **Nota:** Por defecto desactivado en config

#### 4. **Chargeback Alert** - Alerta de Contracargo (Email + Telegram)
- **Trigger:** Webhook de chargeback de Mercadopago
- **Templates:**
  - Email: `templates/email/admin_chargeback_alert.php`
  - Telegram: `send_telegram_chargeback_alert()`
- **Contenido:**
  - 🚨 ALERTA CRÍTICA
  - ID del chargeback
  - Acción (created/lost/won)
  - Datos de la orden afectada
  - Links a MP y admin panel
  - Pasos a seguir

#### 5. **Low Stock Alert** - Alerta de Stock Bajo (Telegram)
- **Trigger:** Stock de producto por debajo del mínimo
- **Template:** `send_telegram_low_stock_alert()`
- **Contenido:**
  - Producto con stock bajo
  - Cantidad actual
  - ID del producto

## 🎨 Templates de Email

Todos los templates están en `templates/email/` y siguen estas características:

### Diseño

- **Responsive:** Adaptable a móviles
- **Email-safe:** CSS inline para compatibilidad
- **Branding:** Usa colores y nombre del sitio
- **Professional:** Gradientes, sombras, tipografía moderna
- **Accesible:** Contraste adecuado, texto legible

### Estructura

```php
<?php
// Cargar configuración
$site_config = read_json(__DIR__ . '/../../config/site.json');
$site_name = $site_config['site_name'] ?? 'Mi Tienda';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="inline-css-here">
    <!-- Header con gradiente -->
    <!-- Contenido principal -->
    <!-- Footer con copyright -->
</body>
</html>
```

### Variables Disponibles

Cada template recibe variables específicas:

| Template | Variables |
|----------|-----------|
| `order_confirmation.php` | `$order` |
| `payment_approved.php` | `$order` |
| `payment_rejected.php` | `$order`, `$payment_message` |
| `payment_pending.php` | `$order` |
| `order_shipped.php` | `$order` |
| `admin_new_order.php` | `$order` |
| `admin_chargeback_alert.php` | `$order`, `$chargeback` |

### Personalización

Para personalizar los templates:

1. **Colores:** Busca los gradientes y actualiza los colores hex
2. **Logo:** Modifica el header para incluir tu logo
3. **Texto:** Edita los mensajes directamente en el HTML
4. **Estructura:** Agrega/quita secciones según necesites

Ejemplo de cambio de color:

```html
<!-- Antes -->
<td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">

<!-- Después (tema rojo) -->
<td style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
```

## 🔧 Integración en el Código

### Archivo: `includes/email.php`

Funciones principales:

```php
// Envío genérico
send_email($to, $subject, $html_body, $plain_body = '')

// Notificaciones a clientes
send_order_confirmation_email($order)
send_payment_approved_email($order)
send_payment_rejected_email($order, $status_detail)
send_payment_pending_email($order)
send_order_shipped_email($order)

// Notificaciones a admin
send_admin_new_order_email($order)
send_admin_chargeback_alert($order, $chargeback)

// Sistema de templates
get_email_template($template_name, $vars = [])
```

### Archivo: `includes/telegram.php`

Funciones principales:

```php
// Envío genérico
send_telegram_message($message, $parse_mode = 'HTML')

// Notificaciones específicas
send_telegram_new_order($order)
send_telegram_payment_approved($order)
send_telegram_payment_rejected($order)
send_telegram_chargeback_alert($order, $chargeback)
send_telegram_low_stock_alert($product, $current_stock)

// Testing
send_telegram_test()
```

### Puntos de Integración

#### 1. Checkout - Nueva Orden
**Archivo:** `checkout.php` (líneas 294-297)

```php
// Send order confirmation notifications
send_order_confirmation_email($order);
send_admin_new_order_email($order);
send_telegram_new_order($order);
```

#### 2. Procesador de Pago - Estados de Pago
**Archivo:** `procesar-pago-mp.php`

```php
// Pago aprobado (líneas 147-150)
$updated_order = $orders_data['orders'][$order_index];
send_payment_approved_email($updated_order);
send_telegram_payment_approved($updated_order);

// Pago pendiente (líneas 160-162)
$updated_order = $orders_data['orders'][$order_index];
send_payment_pending_email($updated_order);

// Pago rechazado (líneas 171-174)
$updated_order = $orders_data['orders'][$order_index];
send_payment_rejected_email($updated_order, $payment['status_detail']);
send_telegram_payment_rejected($updated_order);
```

#### 3. Webhook - Cambios de Estado Post-Venta
**Archivo:** `webhook.php`

```php
// Cambios de estado por webhook (líneas 277-295)
if ($new_order_status === 'cobrada') {
    send_payment_approved_email($updated_order);
    send_telegram_payment_approved($updated_order);
} elseif ($new_order_status === 'pendiente') {
    send_payment_pending_email($updated_order);
} elseif ($new_order_status === 'rechazada') {
    send_payment_rejected_email($updated_order, $status_detail);
    send_telegram_payment_rejected($updated_order);
}

// Chargebacks (líneas 405-420)
send_admin_chargeback_alert($updated_order, $chargeback_data);
send_telegram_chargeback_alert($updated_order, $chargeback_data);
```

## 🧪 Testing

### Test Email Básico

Crea un archivo `test-email.php` en la raíz:

```php
<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/email.php';

// Test simple
$result = send_email(
    'tu-email@example.com',
    'Test Email',
    '<h1>Hola</h1><p>Este es un email de prueba.</p>',
    'Hola. Este es un email de prueba.'
);

echo $result ? 'Email enviado!' : 'Error al enviar email';
```

### Test Telegram

Crea `test-telegram.php`:

```php
<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/telegram.php';

// Test de configuración
$result = send_telegram_test();

echo $result ? 'Telegram OK!' : 'Error en Telegram';
```

### Test Completo con Orden

```php
<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/email.php';
require_once __DIR__ . '/includes/telegram.php';

// Crear orden de prueba
$test_order = [
    'id' => 'test-' . time(),
    'order_number' => 'ORD-TEST-001',
    'customer_name' => 'Juan Pérez',
    'customer_email' => 'tu-email@example.com',
    'customer_phone' => '+54 11 1234-5678',
    'total' => 15000,
    'currency' => 'ARS',
    'status' => 'pendiente',
    'payment_method' => 'mercadopago',
    'created_at' => date('Y-m-d H:i:s'),
    'tracking_token' => 'test-token',
    'items' => [
        [
            'name' => 'Producto de Prueba',
            'quantity' => 2,
            'price' => 7500
        ]
    ]
];

// Test emails
echo "Enviando email de confirmación...\n";
send_order_confirmation_email($test_order);

echo "Enviando email a admin...\n";
send_admin_new_order_email($test_order);

// Test Telegram
echo "Enviando notificación Telegram...\n";
send_telegram_new_order($test_order);

echo "Tests completados!";
```

### Verificar Logs

Revisa los logs PHP para ver el estado de los envíos:

```bash
tail -f /var/log/apache2/error.log
# o
tail -f /var/log/php-fpm/error.log
```

Busca líneas como:
- `Email sent successfully to: ...`
- `Telegram message sent successfully`
- `Email system disabled - would send to: ...`

## 🔍 Troubleshooting

### Emails no se envían

1. **Verificar que esté habilitado:**
   ```json
   "enabled": true
   ```

2. **Revisar configuración SMTP si usas ese método**

3. **Verificar logs:** `error.log` debe mostrar intentos de envío

4. **Probar con email simple:**
   ```php
   mail('tu-email@test.com', 'Test', 'Mensaje de prueba');
   ```

### Telegram no funciona

1. **Verificar que esté habilitado:**
   ```json
   "enabled": true
   ```

2. **Verificar Bot Token:**
   - Debe tener formato: `123456789:ABCdefGHI...`
   - Verificar en BotFather

3. **Verificar Chat ID:**
   - Debe ser un número
   - Puede ser negativo para grupos
   - Hacer test con `getUpdates`

4. **Verificar conectividad:**
   ```bash
   curl https://api.telegram.org/bot<TOKEN>/getMe
   ```

### Mensajes no personalizados

- Verificar que `config/site.json` tenga `site_name` configurado
- Los templates usan esta configuración para personalizar

### Emails van a spam

1. Configurar SPF, DKIM y DMARC en tu dominio
2. Usar SMTP autenticado en lugar de `mail()`
3. Usar un servicio de email transaccional (SendGrid, Mailgun, etc.)

## 📊 Resumen de Archivos

### Configuración
- `config/email.json` - Configuración de emails
- `config/telegram.json` - Configuración de Telegram

### Sistema Core
- `includes/email.php` - Sistema de emails
- `includes/telegram.php` - Sistema de Telegram

### Templates HTML
- `templates/email/order_confirmation.php` - Confirmación de orden
- `templates/email/payment_approved.php` - Pago aprobado
- `templates/email/payment_rejected.php` - Pago rechazado
- `templates/email/payment_pending.php` - Pago pendiente
- `templates/email/order_shipped.php` - Orden enviada
- `templates/email/admin_new_order.php` - Nueva orden (admin)
- `templates/email/admin_chargeback_alert.php` - Alerta de chargeback

### Integración
- `checkout.php` - Orden creada
- `procesar-pago-mp.php` - Procesamiento de pago
- `webhook.php` - Eventos post-venta

## 🎯 Próximos Pasos Sugeridos

1. **Email de envío entregado:** Cuando la orden llega a destino
2. **SMS notifications:** Integración con Twilio/similar
3. **WhatsApp Business:** Notificaciones vía WhatsApp API
4. **Push notifications:** Para app móvil futura
5. **Preferencias de usuario:** Permitir a clientes elegir qué notificaciones recibir
6. **Email transaccional profesional:** Integrar SendGrid, Mailgun, etc.
7. **Templates personalizables desde admin:** Editor visual de templates
8. **A/B testing de emails:** Métricas de apertura y clicks
9. **Recordatorios automatizados:** Carritos abandonados, restock alerts
10. **Integración con CRM:** Sincronizar contactos y eventos

---

**Nota:** Este sistema está completamente implementado y listo para usar. Solo necesitas configurar las credenciales de email y/o Telegram según tus necesidades.
