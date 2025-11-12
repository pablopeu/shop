# 🔄 Script de Reprocesamiento de Pagos MercadoPago

## ¿Para qué sirve?

Este script permite reprocesar pagos de MercadoPago que quedaron en estado "pendiente" debido a fallos en la entrega del webhook (error 401, 403, 500, etc.).

**El script NO cobra nuevamente al cliente**, solo sincroniza el estado del pago desde la API de MercadoPago y actualiza la orden en tu sistema.

---

## 🚀 Uso Rápido

### Caso Actual: Pago ID 133535068062

Este pago fue procesado exitosamente en MercadoPago pero el webhook falló con error 401 (Unauthorized).

**Solución inmediata**:

```bash
# Opción 1: SSH
cd /home2/uv0023/public_html/shop
php reprocesar-pago.php 133535068062

# Opción 2: Navegador (después de configurar la clave)
# https://peu.net/shop/reprocesar-pago.php?payment_id=133535068062&key=TU_CLAVE
```

---

## 📋 Configuración Inicial

### 1. Cambiar la clave secreta (OBLIGATORIO para uso web)

Editar `/shop/reprocesar-pago.php` línea 25:

```php
// ANTES (inseguro):
define('REPROCESS_SECRET_KEY', 'cambiar_esta_clave_secreta_...');

// DESPUÉS (seguro):
define('REPROCESS_SECRET_KEY', 'mi_clave_super_segura_12345');
```

**⚠️ IMPORTANTE**: Usa una clave compleja y guárdala en un lugar seguro (ej: gestor de contraseñas).

---

## 🌐 Uso desde Navegador

### URL Base:
```
https://peu.net/shop/reprocesar-pago.php?payment_id=PAYMENT_ID&key=TU_CLAVE
```

### Ejemplo con el pago actual:
```
https://peu.net/shop/reprocesar-pago.php?payment_id=133535068062&key=mi_clave_super_segura_12345
```

### Resultado esperado:

```
🔄 Reprocesando Pago de MercadoPago

🔍 Payment ID: 133535068062
📡 Obteniendo detalles del pago desde MercadoPago API...

Estado del pago: approved
Monto: 500.00 ARS
Método: visa
External Ref: order-69148959781bc-aafa97b0

🔍 Buscando orden: order-69148959781bc-aafa97b0
✅ Orden encontrada - Estado actual: pendiente

🔄 Procesando estado: approved
📦 Reduciendo stock...
✅ Orden actualizada: pendiente → cobrada

📧 Enviando notificaciones...
✅ Email enviado: Sí
✅ Telegram enviado: Sí

✅ ¡Pago reprocesado exitosamente!

Resumen:
Payment ID: 133535068062
Order ID: order-69148959781bc-aafa97b0
Estado anterior: pendiente
Estado nuevo: cobrada
Monto: 500.00 ARS
Comisiones: 25.50
Neto acreditado: 474.50
```

---

## 💻 Uso desde Línea de Comandos (SSH)

### Sintaxis:
```bash
php reprocesar-pago.php PAYMENT_ID
```

### Ejemplo:
```bash
cd /home2/uv0023/public_html/shop
php reprocesar-pago.php 133535068062
```

### Ventajas del modo CLI:
- ✅ No requiere configurar clave secreta
- ✅ Output más limpio para scripts
- ✅ Fácil de integrar en cron jobs o automatizaciones
- ✅ Exit codes estándar (0 = éxito, 1 = error)

---

## 🔍 ¿Cómo obtener el Payment ID?

### Opción 1: Desde el panel de MercadoPago
1. Ir a: https://www.mercadopago.com.ar/activities
2. Buscar el pago por monto, fecha o cliente
3. El Payment ID aparece en los detalles

### Opción 2: Desde el webhook fallido (tu caso)
En el panel de Webhooks de MercadoPago:
```json
{
  "data": {
    "id": "133535068062"  ← Este es el Payment ID
  }
}
```

### Opción 3: Desde los logs PHP
Buscar en `/home2/uv0023/public_html/shop/mp_debug.log`:
```
[2025-11-12 13:19:51] PREFERENCE_CREATED
...
"preference_id": "102677-195abf18-..."
```

---

## ✅ ¿Qué hace el script?

Cuando ejecutas el script, realiza las siguientes acciones:

1. **Obtiene el pago desde MercadoPago**
   - Llama a la API: `GET /v1/payments/{payment_id}`
   - Obtiene estado, comisiones, método de pago, etc.

2. **Busca la orden asociada**
   - Usa el `external_reference` del pago
   - Encuentra la orden en `/data/orders.json`

3. **Actualiza el estado de la orden**
   - `approved` → `cobrada`
   - `pending` → `pendiente`
   - `rejected` → `rechazada`
   - etc.

4. **Registra datos completos de MercadoPago**
   - Payment ID
   - Método de pago
   - Comisiones (fee_details)
   - Monto bruto y neto
   - Referencias de transacción

5. **Maneja el stock**
   - Si `approved` → reduce stock
   - Si `rejected/refunded` → restaura stock

6. **Envía notificaciones**
   - Email al cliente (aprobado/pendiente/rechazado)
   - Telegram al admin (aprobado/rechazado)

7. **Registra en logs**
   - Todo queda en `mp_debug.log`
   - Incluye marca de "reproceso manual"

---

## 🔒 Seguridad

### Acceso Web:
- ✅ Protegido por clave secreta
- ✅ Usa `hash_equals()` para prevenir timing attacks
- ✅ Error 403 si la clave es incorrecta

### Acceso CLI:
- ✅ Solo usuarios con acceso SSH
- ✅ Sin exposición web

### Idempotencia:
- ✅ Se puede ejecutar múltiples veces sin problemas
- ✅ No cobra al cliente nuevamente
- ✅ Solo actualiza si el estado cambió

---

## 📊 Logs

Todo queda registrado en:
- **`/shop/mp_debug.log`** - Log detallado de MercadoPago
- **PHP error log** - Errores del servidor

Buscar en los logs:
```bash
grep "MANUAL_REPROCESS" mp_debug.log
grep "133535068062" mp_debug.log
```

---

## ⚠️ Errores Comunes

### Error: "MercadoPago no está configurado"
**Causa**: Falta el access token
**Solución**: Verificar `/admin/mp-credentials-prod.json`

### Error: "Orden no encontrada"
**Causa**: El `external_reference` no coincide
**Solución**: Verificar que el pago esté asociado a una orden

### Error: "Payment not found in MP"
**Causa**: El Payment ID no existe o es de otra cuenta
**Solución**: Verificar el Payment ID en el panel de MercadoPago

### Error 403 en navegador
**Causa**: Clave incorrecta
**Solución**: Verificar la clave en la URL y en el script (línea 25)

---

## 🎯 Casos de Uso

### 1. Webhook falló con error 401/403
```bash
php reprocesar-pago.php PAYMENT_ID
```

### 2. Pago manual desde MercadoPago
Si procesaste un pago directamente desde el panel de MP:
```bash
php reprocesar-pago.php PAYMENT_ID
```

### 3. Sincronizar después de migración
Si migraste datos y necesitas sincronizar estados:
```bash
for payment_id in 12345 12346 12347; do
  php reprocesar-pago.php $payment_id
done
```

### 4. Auditoría de pagos pendientes
Revisar todas las órdenes "pendiente" y reprocesar:
```bash
# (requiere script adicional para listar órdenes pendientes)
```

---

## 🚀 Siguiente Paso

**Para el pago actual (133535068062)**:

1. Ejecutar el script:
   ```bash
   php reprocesar-pago.php 133535068062
   ```

2. Verificar el resultado en:
   - `/shop/mp_debug.log`
   - Email del cliente (simon@peu.net)
   - Telegram del admin
   - Backoffice (estado debe cambiar a "cobrada")
   - Stock del producto (debe reducirse)

3. Si todo funciona:
   - ✅ El cliente recibe email de "Pago Aprobado"
   - ✅ El admin recibe notificación de Telegram
   - ✅ El stock se reduce
   - ✅ La orden queda como "cobrada"
   - ✅ Se registran comisiones y monto neto

---

## 📞 Soporte

Si el script falla, revisar:
1. `/shop/mp_debug.log` para detalles del error
2. PHP error log del servidor
3. Verificar configuración de MercadoPago en `/config/payment.json`
4. Verificar credenciales en `/admin/mp-credentials-prod.json`

---

## 📝 Notas Técnicas

- **Idempotente**: Se puede ejecutar múltiples veces sin efectos adversos
- **Atómico**: Actualiza todo o nada
- **Auditado**: Cada ejecución queda registrada en logs
- **Seguro**: No expone datos sensibles en output
- **Robusto**: Maneja errores y excepciones correctamente

---

**Creado**: 2025-11-12
**Versión**: 1.0
**Autor**: Claude (Anthropic)
