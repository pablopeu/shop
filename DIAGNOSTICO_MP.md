# Diagnóstico de Problemas con MercadoPago

## Problema Actual

Un pago fue procesado exitosamente en MercadoPago pero:
- ❌ El usuario recibió email de "pago pendiente" en lugar de "pago aprobado"
- ❌ El admin NO recibió notificación de Telegram ni email
- ❌ En el backoffice el pago figura como "pendiente" en lugar de "cobrada"
- ❌ No se registró el número de operación de MercadoPago
- ❌ El stock no se redujo

## Causa Raíz Probable

El **webhook de MercadoPago NO se está ejecutando correctamente**. Posibles razones:

1. **MercadoPago no puede alcanzar el webhook** (problema de red/firewall)
2. **La validación de IP está rechazando el webhook** (más probable)
3. **La validación de firma (X-Signature) está fallando**
4. **El webhook secret no está configurado**

## Sistema de Logs Implementado

Hemos implementado un sistema de logs detallado:

### Archivo de Log Principal
- **Ubicación**: `/shop/mp_debug.log` (raíz del proyecto)
- **Contenido**: Todos los eventos de MercadoPago con detalles completos

### Qué revisar en los logs:

1. **Verificar si el webhook fue llamado**:
   ```
   Buscar: "WEBHOOK_RECEIVED"
   ```
   - Si NO aparece → MercadoPago no puede llamar al webhook
   - Si aparece → Continuar con paso 2

2. **Verificar validaciones**:
   ```
   Buscar: "IP_VALIDATION" y "WEBHOOK_VALIDATION"
   ```
   - Si alguna dice "FAILED" → Esa validación está bloqueando

3. **Verificar datos del pago**:
   ```
   Buscar: "PAYMENT_DETAILS"
   ```
   - Debe mostrar el estado del pago (approved, pending, etc.)
   - Debe mostrar fee_details y net_received_amount

## Solución Temporal - Desactivar Validación de IP

La validación de IP es la causa más probable. Las IPs de MercadoPago cambian frecuentemente.

### Opción 1: Desactivar validación de IP (RECOMENDADO)

Editar: `/shop/config/payment.json`

```json
{
  "mercadopago": {
    "mode": "production",
    "webhook_security": {
      "validate_signature": true,
      "validate_timestamp": true,
      "validate_ip": false,  <-- CAMBIAR A false
      "max_timestamp_age_minutes": 5
    }
  }
}
```

**Nota**: La validación de IP NO es recomendada por MercadoPago. Es mejor usar solo la validación de firma (X-Signature).

### Opción 2: Verificar webhook secret está configurado

El archivo debe existir y contener el webhook_secret:
- **Producción**: `/shop/admin/mp-credentials-prod.json`
- **Sandbox**: `/shop/admin/mp-credentials-sandbox.json`

Ejemplo de estructura:
```json
{
  "access_token": "APP_USR-...",
  "public_key": "APP_USR-...",
  "webhook_secret": "tu_webhook_secret_aqui"
}
```

El webhook_secret se obtiene desde:
https://www.mercadopago.com.ar/developers/panel/app/[APP_ID]/webhooks

## Verificar Configuración del Webhook en MercadoPago

1. Ir a: https://www.mercadopago.com.ar/developers/panel
2. Seleccionar tu aplicación
3. Ir a "Webhooks" → "Configuración"
4. Verificar:
   - URL del webhook: `https://peu.net/shop/webhook.php`
   - Eventos habilitados: "Pagos" (payment)
   - Estado: Activo ✅

## Nuevo Comportamiento

Con las correcciones implementadas, ahora el sistema:

### ✅ Registra en cada pago:
- Número de operación de MercadoPago
- Método de pago utilizado
- Comisiones de MercadoPago
- Monto bruto cobrado
- Monto neto acreditado (Cobro - Comisiones)

### ✅ Muestra en emails de pago aprobado:
```
Monto Pagado:     $500.00
Comisiones MP:    -$25.50
----------------------------
Total Acreditado: $474.50
```

### ✅ Muestra en Telegram:
```
💵 Detalles Financieros:
   • Cobro: $500.00
   • Comisión MP: -$25.50
   • Acreditado: $474.50
```

### ✅ Logs detallados en `/shop/mp_debug.log`:
- Cada webhook recibido
- Cada validación realizada
- Cada pago procesado
- Cada notificación enviada

## Próximos Pasos

1. **DESACTIVAR validación de IP** en `/shop/config/payment.json`
2. **Esperar el próximo pago** de prueba
3. **Revisar el log** `/shop/mp_debug.log` para ver qué pasó
4. **Verificar** que las notificaciones se envíen correctamente

## 🚨 SOLUCIÓN INMEDIATA: Reprocesar el Pago Actual

Para el pago que quedó pendiente (Payment ID: **133535068062**):

### Opción 1: Desde el navegador (recomendado)

1. **PRIMERO**: Cambiar la clave secreta en `/shop/reprocesar-pago.php` (línea 25):
   ```php
   define('REPROCESS_SECRET_KEY', 'tu_clave_segura_aqui');
   ```

2. Acceder a esta URL (reemplazar `TU_CLAVE` con la clave que pusiste):
   ```
   https://peu.net/shop/reprocesar-pago.php?payment_id=133535068062&key=TU_CLAVE
   ```

3. El script automáticamente:
   - ✅ Obtiene los detalles del pago desde MercadoPago
   - ✅ Actualiza la orden a "cobrada"
   - ✅ Registra comisiones y monto neto
   - ✅ Reduce el stock
   - ✅ Envía email al cliente
   - ✅ Envía notificación de Telegram
   - ✅ Registra todo en `mp_debug.log`

### Opción 2: Desde línea de comandos (SSH)

```bash
cd /home/user/shop
php reprocesar-pago.php 133535068062
```

### Opción 3: Manualmente con curl (solo si validate_ip está desactivado)

```bash
curl -X POST https://peu.net/shop/webhook.php \
  -H "Content-Type: application/json" \
  -d '{
    "type": "payment",
    "data": {
      "id": "133535068062"
    }
  }'
```

**⚠️ IMPORTANTE**:
- El script `reprocesar-pago.php` es seguro y está protegido por clave
- Solo procesa el pago, NO cobra nuevamente al cliente
- Se puede ejecutar múltiples veces sin problemas (es idempotente)
- Registra todo en los logs para auditoría

## Soporte

Si después de desactivar la validación de IP el problema persiste:
1. Revisar `/shop/mp_debug.log` para ver el error exacto
2. Verificar que el webhook_secret esté configurado
3. Verificar que la URL del webhook en MercadoPago sea correcta
