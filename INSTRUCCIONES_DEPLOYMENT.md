# 📋 INSTRUCCIONES DE DEPLOYMENT

## ✅ Archivos que debes subir a tu hosting via FTP

### 1. CRÍTICO - Webhook corregido
```
webhook.php
```
**Ubicación en hosting**: Raíz del proyecto (mismo directorio donde está ahora)

**Qué hace**: Procesa correctamente todos los formatos de webhooks de MercadoPago (corrige el problema de pagos que no se procesaban)

---

### 2. NUEVO - Script de verificación
```
verificar-webhook.php
```
**Ubicación en hosting**: Raíz del proyecto

**Cómo usar**:
- Por web: `https://peu.net/shop/verificar-webhook.php?secret=peu2024secure`
- Muestra estado de webhooks, últimos recibidos, órdenes pendientes, etc.

---

### 3. YA EXISTE - Script de reprocesamiento
```
reprocesar-pago.php
```
**Estado**: Ya está en tu hosting, no necesitas subirlo de nuevo

**Cómo usar** (después de subir webhook.php):
- Por web: `https://peu.net/shop/reprocesar-pago.php?payment_id=132973453083&secret=peu2024secure`
- Esto procesará el pago pendiente (Payment ID: 132973453083)

---

### 4. DOCUMENTACIÓN (opcional)
```
SOLUCION_WEBHOOKS.md
INSTRUCCIONES_DEPLOYMENT.md
```
**Ubicación**: Raíz del proyecto
**Para qué**: Referencia futura sobre el problema y solución

---

## 🔄 PASOS A SEGUIR (EN ORDEN)

### Paso 1: Subir webhook.php vía FTP
1. Conectar a tu hosting via FTP
2. Ir al directorio `/shop/`
3. Subir `webhook.php` (reemplazar el actual)
4. Verificar permisos: 644 o similar

### Paso 2: Verificar que el webhook está funcionando
Acceder a:
```
https://peu.net/shop/verificar-webhook.php?secret=peu2024secure
```

Deberías ver:
- ✅ Archivos de log existentes
- ✅ Configuración de webhooks
- ✅ Últimos webhooks recibidos (si los hay)

### Paso 3: Reprocesar el pago pendiente
Acceder a:
```
https://peu.net/shop/reprocesar-pago.php?payment_id=132973453083&secret=peu2024secure
```

**Resultado esperado**:
```
Iniciando reprocesamiento del pago 132973453083...
✅ Pago encontrado en MercadoPago
Estado: approved
Orden: order-6914e22098d65-8f4a3193

✅ Orden encontrada en el sistema
Estado actual: pendiente

✅ Orden actualizada a: cobrada
✅ Stock reducido
✅ Email enviado a: [email del cliente]
✅ Notificación de Telegram enviada

Reprocesamiento completado exitosamente!
```

### Paso 4: Verificar en el backoffice
1. Acceder a `https://peu.net/shop/admin/ventas.php`
2. Buscar la orden `order-6914e22098d65-8f4a3193`
3. Verificar que ahora muestra estado: **Cobrada** (verde)
4. Verificar que tiene datos de MercadoPago:
   - Monto cobrado
   - Comisión MP
   - Neto recibido

### Paso 5: Verificar notificaciones
1. **Email del cliente**: Debe haber recibido email de "Pago Aprobado"
2. **Email admin**: Debe haber recibido notificación
3. **Telegram**: Debe haber recibido notificación (si está habilitado)

---

## 📊 Verificación de logs

### Ver últimas entradas del log
Por FTP, descargar y revisar:
```
/shop/mp_debug.log
```

Buscar estas entradas (después de reprocesar):
```
[PAYMENT_WEBHOOK] Procesando webhook de pago - Payment ID: 132973453083
[PAYMENT_DETAILS] Detalles del pago obtenidos
[ORDER_UPDATE] Orden actualizada: order-6914e22098d65-8f4a3193
[NOTIFICATION] Notificación EMAIL_PAYMENT_APPROVED enviada (EXITOSA)
[NOTIFICATION] Notificación TELEGRAM_PAYMENT_APPROVED enviada (EXITOSA)
```

---

## 🔮 Próximas compras

Una vez subido `webhook.php` corregido, **las próximas compras se procesarán automáticamente**:

1. Cliente completa pago en MercadoPago ✅
2. MercadoPago envía webhook a tu servidor ✅
3. Webhook es procesado correctamente ✅
4. Orden se marca como "cobrada" ✅
5. Stock se reduce automáticamente ✅
6. Cliente recibe email de confirmación ✅
7. Tú recibes notificación por Telegram ✅

**Todo esto es AUTOMÁTICO**, sin necesidad de intervención manual.

---

## 🆘 Si algo sale mal

### Error: "Acceso denegado. Clave de autenticación inválida"
**Solución**: Verifica que estás usando `secret=peu2024secure` en la URL

### Error: "Payment not found in MP"
**Solución**: El payment_id es incorrecto o no existe en MercadoPago. Verifica el ID correcto en mp_debug.log

### Error: "Order not found"
**Solución**: La orden no existe o tiene un external_reference diferente. Verifica en data/orders.json (si tienes acceso)

### Los webhooks siguen sin procesar
1. Verificar que subiste el webhook.php correcto
2. Revisar mp_debug.log buscando entradas `WEBHOOK_IGNORED`
3. Ejecutar verificar-webhook.php para ver diagnóstico completo
4. Si ves webhooks con tipo "unknown", contactar para análisis

---

## 📞 Soporte

Si después de seguir estos pasos el problema persiste:

1. Descargar `mp_debug.log` de tu hosting
2. Ejecutar `https://peu.net/shop/verificar-webhook.php?secret=peu2024secure`
3. Capturar pantalla de la salida
4. Reportar el problema con esta información

---

## ✨ Resumen

**Archivos a subir via FTP**:
- ✅ `webhook.php` (CRÍTICO)
- ✅ `verificar-webhook.php` (recomendado)
- ⚠️ `reprocesar-pago.php` (ya existe, no necesario)

**Acciones a realizar**:
1. Subir webhook.php via FTP
2. Ejecutar verificar-webhook.php
3. Ejecutar reprocesar-pago.php con payment_id=132973453083
4. Verificar en backoffice que la orden cambió a "cobrada"
5. Verificar que llegaron las notificaciones

**Resultado esperado**:
- Orden procesada correctamente ✅
- Notificaciones enviadas ✅
- Próximas compras se procesarán automáticamente ✅

---
**Última actualización**: 12 de Noviembre 2025
