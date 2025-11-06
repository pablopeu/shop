# Integración de Mercadopago

## 📋 Resumen

Este documento describe la implementación completa de Mercadopago en la plataforma de e-commerce.

## 🎯 Funcionalidades Implementadas

### 1. Clase MercadoPago (includes/mercadopago.php)
- Wrapper simple para la API de Mercadopago sin necesidad de Composer
- Creación de preferencias de pago
- Consulta de información de pagos
- Validación de firmas de webhook
- Soporte para modo sandbox y producción

### 2. Configuración de Pagos (admin/config-payment.php)
- Panel de administración para configurar credenciales
- Configuración separada para sandbox y producción
- Toggle para cambiar entre modos
- URL del webhook auto-generada y copiable
- Configuración de pago presencial

### 3. Integración en Checkout (checkout.php)
- Creación automática de preferencias de pago
- Redirección a Mercadopago
- URLs de retorno configuradas (success, failure, pending)
- External reference con ID de orden
- Información del comprador pre-cargada

### 4. Webhook (webhook.php)
- Recepción de notificaciones de Mercadopago
- Validación opcional de firmas
- Actualización automática de estado de órdenes
- Manejo de stock:
  - Reducción al aprobar pago
  - Restauración al rechazar/cancelar
- Log de webhooks para debugging

### 5. Páginas de Respuesta
- **gracias.php**: Confirmación de pedido exitoso
- **pendiente.php**: Pago en proceso
- **error.php**: Error en el pago

### 6. Admin - Gestión de Ventas (admin/ventas.php)
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
5. Click en "Finalizar Compra"
```

### Sistema Crea Preferencia

```php
// checkout.php crea la preferencia automáticamente
$mp = new MercadoPago($access_token, $sandbox_mode);
$preference = $mp->createPreference([
    'items' => $mp_items,
    'external_reference' => $order_id,
    'back_urls' => [...],
    'notification_url' => $webhook_url
]);
```

### Cliente Paga en Mercadopago

```
1. Cliente es redirigido a Mercadopago
2. Realiza el pago
3. Es redirigido de vuelta al sitio según resultado:
   - Éxito: gracias.php
   - Pendiente: pendiente.php
   - Error: error.php
```

### Webhook Actualiza la Orden

```
1. Mercadopago envía notificación al webhook
2. webhook.php valida y procesa la notificación
3. Actualiza estado de la orden
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

Mercadopago provee tarjetas de prueba:

| Tarjeta | Resultado |
|---------|-----------|
| 5031 7557 3453 0604 | Aprobada |
| 5031 4332 1540 6351 | Rechazada |
| 5031 4559 4657 0761 | Pendiente |

CVV: Cualquier 3 dígitos
Vencimiento: Cualquier fecha futura
Nombre: APRO / OTHE / EXPI

### URLs de Testing

- Sandbox: Usar credenciales TEST-xxx
- Webhook URL debe ser pública (usar ngrok en desarrollo)

```bash
# Si estás en local, usa ngrok
ngrok http 80
# Copia la URL https://xxx.ngrok.io y agregala como webhook
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
- [Checkout Pro](https://www.mercadopago.com.ar/developers/es/docs/checkout-pro/landing)
- [Webhooks](https://www.mercadopago.com.ar/developers/es/docs/your-integrations/notifications/webhooks)
- [Tarjetas de Prueba](https://www.mercadopago.com.ar/developers/es/docs/checkout-pro/additional-content/test-cards)

## 💡 Próximas Mejoras

- [ ] Envío de emails automáticos al confirmar pago
- [ ] Panel de métricas de pagos en dashboard
- [ ] Reintentos automáticos de webhook
- [ ] Soporte para pagos en cuotas
- [ ] Integración con otros medios de pago

---

**Fecha de Implementación**: Noviembre 2025
**Versión**: 1.0.0
**Estado**: ✅ Implementado y listo para testing
