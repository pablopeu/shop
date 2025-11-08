# Integración de Mercadopago

## 📋 Resumen

Este documento describe la implementación completa de Mercadopago usando **Checkout Bricks** (formulario embebido) en la plataforma de e-commerce.

### ¿Por qué Checkout Bricks?

Inicialmente se implementó usando **Checkout Preferences** (redirección a Mercadopago), pero se migró a **Checkout Bricks** por las siguientes razones:

**Problemas con Checkout Preferences:**
- ❌ Testing en sandbox bloqueado por verificación de códigos de seguridad
- ❌ Emails de códigos de seguridad enviados a direcciones de prueba inaccesibles
- ❌ Imposible completar flujo de pago en sandbox sin credenciales de producción
- ❌ Cliente abandona el sitio durante el pago (redirección externa)

**Ventajas de Checkout Bricks:**
- ✅ Formulario de pago embebido en el sitio (mejor UX)
- ✅ Testing directo con tarjetas de prueba (sin usuarios de prueba)
- ✅ Sin problemas de códigos de seguridad
- ✅ Validación en tiempo real del SDK
- ✅ Flujo de pago más rápido y seguro
- ✅ Cliente nunca abandona el sitio

## 🎯 Funcionalidades Implementadas

### 1. Clase MercadoPago (includes/mercadopago.php)
- Wrapper simple para la API de Mercadopago sin necesidad de Composer
- Creación de pagos directos (createPayment)
- Consulta de información de pagos
- Validación de firmas de webhook
- Soporte para modo sandbox y producción

### 2. Configuración de Pagos (admin/config-payment.php)
- Panel de administración para configurar credenciales
- Configuración separada para sandbox y producción
- Toggle para cambiar entre modos
- URL del webhook auto-generada y copiable
- Configuración de pago presencial

### 3. Página de Pago (pagar-mercadopago.php)
- Formulario de pago embebido usando Mercadopago SDK v2
- Checkout Bricks (cardPayment Brick)
- Formulario permanece dentro del sitio (sin redirección externa)
- Resumen de orden visible durante el pago
- Conversión automática USD → ARS

### 4. Procesador de Pago (procesar-pago-mp.php)
- Backend que procesa pagos via API de Mercadopago
- Validación de datos de orden y token
- Creación de pago usando X-Idempotency-Key
- Actualización de estado de orden
- Reducción de stock al aprobar pago
- Respuesta JSON para el frontend

### 5. Integración en Checkout (checkout.php)
- Redirección a página de pago embebida
- External reference con ID de orden
- Información del comprador pre-cargada

### 6. Webhook (webhook.php)
- Recepción de notificaciones de Mercadopago
- Validación opcional de firmas
- Actualización automática de estado de órdenes
- Manejo de stock:
  - Reducción al aprobar pago
  - Restauración al rechazar/cancelar
- Log de webhooks para debugging

### 7. Páginas de Respuesta
- **gracias.php**: Confirmación de pedido exitoso
- **pendiente.php**: Pago en proceso
- **error.php**: Error en el pago

### 8. Admin - Gestión de Ventas (admin/ventas.php)
- Visualización de link de pago en detalles de orden
- Botón para copiar link de pago
- Estado del pago (aprobado, pendiente, rechazado)
- Método de pago visible en listado

## 🔧 Configuración

### Paso 1: Obtener Credenciales de Mercadopago

1. Ingresá a [Mercadopago Developers](https://www.mercadopago.com.ar/developers/panel)
2. Creá una aplicación o usá una existente
3. Copiá las credenciales:
   - Access Token Sandbox (TEST-xxx)
   - Access Token Producción (APP_USR-xxx)
   - Public Key Sandbox
   - Public Key Producción

### Paso 2: Configurar en el Admin

1. Ingresá al admin
2. Ir a **Configuración → Medios de Pago**
3. Completar:
   - Habilitar Mercadopago: ✅
   - Modo Sandbox: ✅ (para testing)
   - Access Token Sandbox: `TEST-xxx`
   - Public Key Sandbox: `TEST-xxx`
   - Access Token Producción: `APP_USR-xxx` (cuando estés listo)
   - Public Key Producción: `APP_USR-xxx`
4. Copiar URL del Webhook
5. Guardar configuración

### Paso 3: Configurar Webhook en Mercadopago

1. Ir a tu aplicación en Mercadopago Developers
2. Ir a **Webhooks**
3. Agregar nueva URL: `https://tudominio.com/webhook.php`
4. Eventos: Seleccionar "Payments"
5. Guardar

## 📊 Flujo de Pago

### Cliente Realiza una Compra

```
1. Cliente agrega productos al carrito
2. Va al checkout
3. Completa datos personales
4. Selecciona "Mercadopago" como método de pago
5. Click en "Confirmar Pedido"
6. Sistema crea orden y redirige a pagar-mercadopago.php
```

### Cliente Completa el Pago (Checkout Bricks)

```
1. Cliente ve formulario de pago embebido en el sitio
2. Ingresa datos de tarjeta
3. Mercadopago SDK valida datos en tiempo real
4. Click en botón de pago
5. Frontend envía datos a procesar-pago-mp.php
```

### Backend Procesa el Pago

```php
// procesar-pago-mp.php procesa el pago via API
$mp = new MercadoPago($access_token, $sandbox_mode);
$payment = $mp->createPayment([
    'transaction_amount' => $total_ars,
    'token' => $card_token,
    'payment_method_id' => $payment_method_id,
    'payer' => [...],
    'external_reference' => $order_id
]);

// Actualiza estado de orden inmediatamente
if ($payment['status'] === 'approved') {
    $order['status'] = 'cobrada';
    // Reduce stock
}
```

### Cliente es Redirigido

```
1. Backend retorna resultado del pago
2. Frontend redirige según resultado:
   - Aprobado: gracias.php
   - Pendiente: gracias.php (con mensaje de pendiente)
   - Rechazado: error.php
```

### Webhook Actualiza la Orden (Opcional)

```
1. Mercadopago envía notificación al webhook (cambios de estado posteriores)
2. webhook.php valida y procesa la notificación
3. Actualiza estado de la orden si cambió
4. Reduce/restaura stock según corresponda
5. (Futuro) Envía email al cliente
```

## 🎨 Estados de Pago

### Mapeo Mercadopago → Sistema

| Estado MP | Estado Orden | Acción Stock |
|-----------|--------------|--------------|
| approved | cobrada | Reduce stock |
| pending | pendiente | Sin cambios |
| in_process | pendiente | Sin cambios |
| rejected | rechazada | Restaura stock |
| cancelled | cancelada | Restaura stock |
| refunded | cancelada | Restaura stock |
| charged_back | cancelada | Restaura stock |

## 🔒 Seguridad

### Validación de Webhook

El webhook verifica:
- ✅ Formato de datos JSON válido
- ✅ Tipo de notificación (payment)
- ✅ Existencia de payment ID
- ✅ Orden existe en el sistema
- ✅ (Opcional) Firma x-signature

### Protección de Stock

- Stock se reduce SOLO cuando pago es aprobado
- Stock se restaura si pago es rechazado/cancelado
- Flag `stock_reduced` previene reducciones duplicadas
- Log de todos los cambios de stock

## 📝 Logs y Debugging

### Webhook Logs

Los webhooks se registran en `data/webhook_log.json`:

```json
{
  "timestamp": "2025-11-06 10:30:45",
  "message": "Payment details retrieved",
  "data": {
    "payment": {...}
  }
}
```

Últimos 100 webhooks se mantienen para debugging.

### Ver Logs

```php
// En desarrollo, puedes leer el archivo directamente
$logs = json_decode(file_get_contents('data/webhook_log.json'), true);
print_r($logs);
```

## 🧪 Testing en Sandbox

### Tarjetas de Prueba

Con **Checkout Bricks**, las tarjetas de prueba funcionan directamente en el formulario embebido:

**Para pagos APROBADOS:**
- Número: `5031 7557 3453 0604`
- CVV: `123`
- Vencimiento: Cualquier fecha futura
- Titular: `APRO`

**Para pagos RECHAZADOS:**
- Número: `5031 4332 1540 6351`
- CVV: `123`
- Vencimiento: Cualquier fecha futura
- Titular: `OTHE`

**Para pagos PENDIENTES:**
- Número: `5031 4559 4657 0761`
- CVV: `123`
- Vencimiento: Cualquier fecha futura
- Titular: `EXPI`

**Ventajas de Checkout Bricks para Testing:**
- ✅ No requiere crear usuarios de prueba
- ✅ No hay emails de códigos de seguridad
- ✅ Formulario se puede probar directamente
- ✅ Validación en tiempo real
- ✅ Funciona igual en sandbox y producción

### URLs de Testing

- Sandbox: Usar credenciales TEST-xxx
- Webhook URL debe ser pública (usar ngrok en desarrollo)

```bash
# Si estás en local, usa ngrok
ngrok http 8000
# Copia la URL https://xxx.ngrok-free.app y agregala como webhook
```

## 🚀 Pasar a Producción

### Checklist

- [ ] Cambiar "Modo Sandbox" a OFF
- [ ] Verificar Access Token de Producción configurado
- [ ] Verificar Public Key de Producción configurado
- [ ] Webhook configurado con URL de producción
- [ ] Probar una compra real de bajo monto
- [ ] Verificar que webhook esté recibiendo notificaciones
- [ ] Verificar que stock se reduce correctamente
- [ ] Verificar emails de confirmación (cuando se implemente)

## 🔍 Troubleshooting

### El pago no se confirma

1. Verificar que el webhook esté configurado en Mercadopago
2. Revisar logs en `data/webhook_log.json`
3. Verificar que la URL del webhook sea accesible públicamente
4. Verificar credenciales (sandbox vs producción)

### Stock no se reduce

1. Verificar en logs del webhook que se procesó el pago
2. Verificar que `stock_reduced` no esté ya en `true`
3. Verificar que el estado del pago sea `approved`

### Webhook retorna error 500

1. Revisar logs de PHP
2. Verificar permisos de escritura en `data/orders.json`
3. Verificar permisos de escritura en `data/webhook_log.json`

## 📚 Referencias

- [Mercadopago API Docs](https://www.mercadopago.com.ar/developers/es/reference)
- [Checkout Bricks](https://www.mercadopago.com.ar/developers/es/docs/checkout-bricks/landing)
- [Card Payment Brick](https://www.mercadopago.com.ar/developers/es/docs/checkout-bricks/card-payment-brick/introduction)
- [Webhooks](https://www.mercadopago.com.ar/developers/es/docs/your-integrations/notifications/webhooks)
- [Tarjetas de Prueba](https://www.mercadopago.com.ar/developers/es/docs/checkout-bricks/additional-content/test-cards)

## 💡 Próximas Mejoras

- [ ] Envío de emails automáticos al confirmar pago
- [ ] Panel de métricas de pagos en dashboard
- [ ] Soporte para pagos en cuotas
- [ ] Payment Brick (múltiples medios de pago)
- [ ] Integración con otros medios de pago (transferencias, efectivo)
- [ ] 3DS authentication para mayor seguridad

---

**Fecha de Implementación**: Noviembre 2025
**Versión**: 2.0.0 (Checkout Bricks)
**Estado**: ✅ Implementado y listo para testing en sandbox
