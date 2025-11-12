# 🔒 Seguridad del Webhook de Mercadopago

## Resumen

Este documento describe las medidas de seguridad implementadas en el webhook de Mercadopago para prevenir fraude y ataques.

---

## 🎯 Medidas de Seguridad Implementadas

### 1. ✅ Validación de X-Signature (CRÍTICO)

**¿Qué es?**
Mercadopago firma cada notificación de webhook con un hash criptográfico (HMAC-SHA256) que se envía en el header `x-signature`. Esto permite verificar que la notificación realmente proviene de Mercadopago y no ha sido modificada.

**¿Cómo funciona?**
```
Header recibido:
x-signature: ts=1704067200,v1=abc123def456...
x-request-id: 550e8400-e29b-41d4-a716-446655440000

El webhook calcula:
manifest = "id:{payment_id};request-id:{request_id};ts:{timestamp}"
expected_hash = HMAC-SHA256(manifest, secret_key)

Si expected_hash == received_hash → Válido ✅
Si expected_hash != received_hash → Rechazado ❌ (401 Unauthorized)
```

**¿Por qué es importante?**
Sin esta validación, cualquier atacante podría:
- Marcar órdenes como pagas sin haber pagado
- Cancelar órdenes legítimas
- Manipular el stock de productos

**Estado:** ✅ Implementado en `webhook.php:43-94`

---

### 2. ✅ Validación de Timestamp (Anti-Replay)

**¿Qué es?**
Verifica que la notificación no sea muy antigua. Rechaza notificaciones con timestamps de más de 5 minutos (configurable).

**¿Cómo funciona?**
```
timestamp_recibido = 1704067200000 (milisegundos)
timestamp_actual = 1704067500000
edad = |timestamp_actual - timestamp_recibido| = 300000ms = 5 minutos

Si edad <= max_age → Válido ✅
Si edad > max_age → Rechazado ❌ (401 Unauthorized)
```

**¿Por qué es importante?**
Previene "replay attacks": un atacante captura una notificación legítima de pago aprobado y la reenvía múltiples veces para simular múltiples ventas.

**Estado:** ✅ Implementado en `webhook.php:99-133`

---

### 3. ✅ Validación de IP (Whitelist)

**¿Qué es?**
Solo acepta notificaciones desde las IPs oficiales de Mercadopago.

**IPs permitidas:**
```
209.225.49.0/24
216.33.197.0/24
216.33.196.0/24
52.67.0.0/16      (AWS South America - São Paulo)
54.94.0.0/16      (AWS South America - São Paulo)
54.232.0.0/16     (AWS South America - São Paulo)
```

**¿Cómo funciona?**
```
IP del cliente = 216.33.197.45
¿Está en algún rango permitido? → Sí ✅

IP del cliente = 123.45.67.89
¿Está en algún rango permitido? → No ❌ (403 Forbidden)
```

**Estado:** ✅ Implementado en `webhook.php:138-176`

---

### 4. ✅ Rate Limiting

**¿Qué es?**
Limita la cantidad de requests que el webhook puede recibir en un período de tiempo.

**Configuración actual:**
- Máximo: 100 requests por minuto
- Si se excede: Retorna 429 (Too Many Requests)

**¿Por qué es importante?**
Previene ataques de denegación de servicio (DoS) que podrían saturar tu servidor con miles de notificaciones falsas.

**Estado:** ✅ Implementado en `webhook.php:181-211`

---

## 🎛️ Configuración

### Paso 1: Obtener la Secret Key de Mercadopago

1. Ve a [Mercadopago Developers](https://www.mercadopago.com.ar/developers/panel)
2. Selecciona tu aplicación
3. Ve a la sección "Webhooks"
4. Haz clic en "Revelar Secret Key"
5. Copia la secret key

⚠️ **Importante:** Necesitas una secret key para SANDBOX y otra para PRODUCCIÓN.

### Paso 2: Configurar en el Admin Panel

1. Ve a **Admin → Configuración → Medios de Pago**
2. En la sección "🔒 Seguridad del Webhook":
   - Pega la **Webhook Secret - Sandbox** (para testing)
   - Pega la **Webhook Secret - Producción** (para pagos reales)
3. En "⚙️ Opciones de Seguridad Avanzadas":
   - ✅ **Validar X-Signature** (MUY RECOMENDADO - mantener activado)
   - ✅ **Validar Timestamp** (Recomendado - mantener activado)
   - ✅ **Validar IP de Mercadopago** (Opcional - puedes desactivar si tienes problemas)
   - Edad máxima del timestamp: **5 minutos** (valor recomendado)
4. Guarda la configuración

### Paso 3: Configurar la URL del Webhook en Mercadopago

1. Copia la URL del webhook desde el admin panel (hay un botón "📋 Copiar URL")
2. Ve a tu aplicación en Mercadopago Developers
3. Ve a "Webhooks"
4. Agrega la URL copiada
5. Selecciona los eventos a notificar:
   - ✅ Payments
   - ✅ Chargebacks
   - ✅ Merchant Orders

---

## 🧪 Testing

### Probar la Seguridad del Webhook

Para verificar que todo funciona correctamente:

1. **En modo Sandbox:**
   - Configura la secret key de sandbox
   - Activa todas las validaciones
   - Realiza un pago de prueba desde el checkout
   - Verifica que el webhook procesa correctamente

2. **Verificar logs:**
   - Ve a `data/webhook_log.json`
   - Busca entradas con "Signature validation passed"
   - Busca entradas con "Timestamp validation passed"

### Simular un Ataque

Para verificar que las validaciones funcionan:

```bash
# Enviar una notificación sin signature válida (debería ser rechazada)
curl -X POST https://tu-dominio.com/webhook.php \
  -H "Content-Type: application/json" \
  -d '{"type":"payment","data":{"id":"123456"}}'

# Respuesta esperada: 401 Unauthorized
```

---

## 📊 Códigos de Respuesta HTTP

| Código | Significado | Causa |
|--------|-------------|-------|
| **200** | OK | Webhook procesado correctamente |
| **400** | Bad Request | Datos inválidos (JSON mal formado) |
| **401** | Unauthorized | Fallo en validación de signature o timestamp |
| **403** | Forbidden | IP no permitida |
| **429** | Too Many Requests | Rate limit excedido |
| **500** | Internal Server Error | Error en el servidor (access token no configurado, etc.) |

---

## 🚨 Monitoreo y Alertas

### Logs del Webhook

Todos los eventos del webhook se registran en:
- **Ubicación:** `data/webhook_log.json`
- **Retención:** Últimos 100 eventos
- **Información registrada:**
  - Timestamp
  - IP del cliente
  - Presencia de x-signature
  - Resultado de validaciones
  - Datos de la notificación

### Qué buscar en los logs

**🔴 Señales de alerta:**
```json
{
  "message": "Signature validation failed",
  "timestamp": "2025-01-15 14:30:00"
}
```
→ Alguien está intentando enviar notificaciones falsas

```json
{
  "message": "Rate limit exceeded",
  "requests_in_window": 150
}
```
→ Posible ataque DoS

```json
{
  "message": "IP not in Mercadopago whitelist",
  "ip": "123.45.67.89"
}
```
→ Notificación desde IP sospechosa

**✅ Operación normal:**
```json
{
  "message": "All security validations passed - processing webhook",
  "timestamp": "2025-01-15 14:30:00"
}
```

---

## 🔧 Troubleshooting

### Problema: Webhooks no llegan o son rechazados

**Síntoma:** Los pagos se procesan pero el webhook no actualiza las órdenes.

**Soluciones:**

1. **Verificar secret key:**
   ```
   Admin → Medios de Pago → Webhook Secret
   - ¿Está configurada la secret key correcta?
   - ¿Estás usando la secret key de sandbox en modo sandbox?
   - ¿Estás usando la secret key de producción en modo producción?
   ```

2. **Revisar logs:**
   ```
   data/webhook_log.json
   - ¿Hay entradas de "Signature validation failed"?
   - ¿Qué dice el mensaje de error?
   ```

3. **Desactivar temporalmente validaciones:**
   ```
   Admin → Medios de Pago → Opciones de Seguridad Avanzadas
   - Desactiva "Validar IP" (puede causar problemas si MP cambia IPs)
   - Deja activadas "Validar X-Signature" y "Validar Timestamp"
   ```

### Problema: "Signature validation failed"

**Causas posibles:**

1. **Secret key incorrecta**
   - Verifica que copiaste la secret key completa
   - Verifica que estés usando la secret key del entorno correcto (sandbox/prod)

2. **Notificación manipulada**
   - Esto es BUENO - significa que la seguridad está funcionando
   - Alguien intentó enviar una notificación falsa

### Problema: "Timestamp too old or in future"

**Causas posibles:**

1. **Reloj del servidor desincronizado**
   ```bash
   # Verificar hora del servidor
   date

   # Sincronizar si es necesario
   sudo ntpdate -s time.nist.gov
   ```

2. **Edad máxima muy restrictiva**
   - Aumenta el valor en "Edad máxima del timestamp" (ej: de 5 a 10 minutos)

### Problema: "IP not in Mercadopago whitelist"

**Causas posibles:**

1. **Mercadopago cambió sus IPs**
   - Desactiva temporalmente "Validar IP"
   - Reporta la nueva IP para que se agregue a la lista

2. **Proxy o CDN intermediario**
   - El header `X-Forwarded-For` puede no estar configurado correctamente

---

## 🎓 Conceptos Clave

### HMAC-SHA256
Algoritmo de firma criptográfica que combina:
- Un mensaje (manifest)
- Una clave secreta (secret key)
- Función hash SHA256

Resultado: Hash único que solo puede ser generado con la clave correcta.

### Constant-time comparison
Usamos `hash_equals()` en lugar de `==` para comparar hashes.
Previene ataques de timing que podrían adivinar el hash correcto midiendo tiempos de respuesta.

### CIDR notation
Formato para representar rangos de IPs: `209.225.49.0/24`
- `/24` = primeros 24 bits fijos = 256 IPs (de .0 a .255)
- `/16` = primeros 16 bits fijos = 65,536 IPs

---

## 📚 Referencias

- [Documentación oficial de Webhooks de Mercadopago](https://www.mercadopago.com.ar/developers/es/docs/your-integrations/notifications/webhooks)
- [Validación de firmas en webhooks](https://www.mercadopago.com.br/developers/en/news/2024/02/27/Ensure-the-validity-of-notifications-sent-by-Mercado-Pago)
- [RFC 2104 - HMAC](https://tools.ietf.org/html/rfc2104)
- [OWASP Webhook Security](https://cheatsheetseries.owasp.org/cheatsheets/Webhook_Security_Cheat_Sheet.html)

---

## ✅ Checklist de Seguridad

Antes de pasar a producción, verifica:

- [ ] Secret key de producción configurada en Admin → Medios de Pago
- [ ] Secret key de sandbox configurada (para testing)
- [ ] "Validar X-Signature" activado
- [ ] "Validar Timestamp" activado
- [ ] URL del webhook configurada en panel de Mercadopago
- [ ] Webhook funcionando correctamente en modo sandbox
- [ ] Logs del webhook sin errores (`data/webhook_log.json`)
- [ ] Testing realizado con pago de prueba
- [ ] Modo sandbox desactivado (cambiar a producción)
- [ ] Testing realizado con pago real (monto bajo)

---

**Última actualización:** 2025-01-09
**Versión de seguridad:** 2.0
