# Estados de Pago de Mercadopago - Documentación

Este documento detalla todos los posibles estados de pago que puede devolver Mercadopago y cómo los maneja el sistema.

## Estructura de Estados

Mercadopago utiliza dos campos para describir el estado de un pago:

1. **`status`** - Estado principal del pago
2. **`status_detail`** - Detalle específico que explica la razón del estado

## Estados Principales (`status`)

### ✅ approved
**Significado**: Pago aprobado y acreditado

**Acción del sistema**:
- Estado de orden: `cobrada`
- Stock: Se reduce automáticamente
- Redirección: `/gracias.php` (página de confirmación)
- Email: Se envía confirmación de compra

**Status details comunes**:
- `accredited` - Pago acreditado exitosamente

---

### 🔐 authorized
**Significado**: Pago autorizado pero pendiente de captura

**Acción del sistema**:
- Estado de orden: `pendiente`
- Stock: Sin cambios
- Redirección: `/gracias.php` con mensaje específico
- Webhook: Actualiza cuando se capture o expire

**Cuándo ocurre**: En flujos de autorización y captura diferida

---

### ⏳ pending
**Significado**: Pago pendiente de procesamiento

**Acción del sistema**:
- Estado de orden: `pendiente`
- Stock: Sin cambios (se reducirá cuando se apruebe)
- Redirección: `/gracias.php` con mensaje de espera
- Webhook: Actualiza cuando cambie el estado

**Status details comunes**:
- `pending_contingency` - Pendiente por contingencia del sistema
- `pending_review_manual` - Requiere revisión manual (puede tomar hasta 48hs)
- `pending_waiting_payment` - Esperando que el usuario complete el pago

---

### 🔍 in_process
**Significado**: Pago en proceso de revisión

**Acción del sistema**:
- Estado de orden: `pendiente`
- Stock: Sin cambios
- Redirección: `/gracias.php` con mensaje de procesamiento
- Webhook: Actualiza cuando se complete la revisión

**Cuándo ocurre**: Cuando el sistema de antifraude está revisando la transacción

---

### ⚖️ in_mediation
**Significado**: El pago está en mediación debido a una disputa

**Acción del sistema**:
- Estado de orden: `pendiente`
- Stock: Sin cambios
- Redirección: `/gracias.php` con mensaje de mediación
- Webhook: Actualiza según resolución de la disputa
- Log: Se registra en webhook_log.json para seguimiento

**Cuándo ocurre**: Cuando el comprador o banco inicia un reclamo

---

### ❌ rejected
**Significado**: Pago rechazado

**Acción del sistema**:
- Estado de orden: `rechazada`
- Stock: Se restaura si se había reducido previamente
- Redirección: `/error.php` con mensaje específico según `status_detail`
- Email: Se notifica el rechazo

**Status details y mensajes específicos**:

#### 💳 Errores de Tarjeta
- `cc_rejected_bad_filled_card_number` - Número de tarjeta incorrecto
- `cc_rejected_bad_filled_date` - Fecha de vencimiento incorrecta
- `cc_rejected_bad_filled_security_code` - CVV incorrecto
- `cc_rejected_bad_filled_other` - Error general en datos

#### 💰 Problemas de Fondos/Límites
- `cc_rejected_insufficient_amount` - Fondos insuficientes
- `cc_rejected_invalid_installments` - Cuotas no disponibles

#### 🚫 Bloqueos
- `cc_rejected_blacklist` - Tarjeta en lista negra
- `cc_rejected_card_disabled` - Tarjeta deshabilitada
- `cc_rejected_high_risk` - Bloqueado por sistema antifraude

#### 📞 Requiere Acción
- `cc_rejected_call_for_authorize` - Requiere autorización del banco
- `cc_rejected_duplicated_payment` - Pago duplicado detectado

#### ⚠️ Otros
- `cc_rejected_card_error` - Error general de tarjeta
- `cc_rejected_max_attempts` - Máximo de intentos excedido
- `cc_rejected_other_reason` - Otro motivo de rechazo

---

### ⛔ cancelled
**Significado**: Pago cancelado

**Acción del sistema**:
- Estado de orden: `rechazada`
- Stock: Se restaura si se había reducido
- Redirección: `/error.php` con mensaje de cancelación
- Email: Se notifica la cancelación

**Cuándo ocurre**: Cuando el usuario o sistema cancela el pago

---

### ↩️ refunded
**Significado**: Pago reembolsado

**Acción del sistema**:
- Estado de orden: `cancelada`
- Stock: Se restaura automáticamente
- Webhook: Actualiza el estado de la orden
- Email: Se notifica el reembolso

**Cuándo ocurre**: Cuando el vendedor emite un reembolso total

---

### 💔 charged_back
**Significado**: Contracargo realizado

**Acción del sistema**:
- Estado de orden: `cancelada`
- Stock: Se restaura automáticamente
- Webhook: Actualiza el estado y registra el incidente
- Email: Se notifica el contracargo

**Cuándo ocurre**: Cuando el banco del comprador revierte el pago

---

## Nombres de Prueba para Testing en Sandbox

Estos nombres de titular simulan diferentes escenarios:

| Nombre | Status | Status Detail | Estado Orden | Descripción |
|--------|--------|---------------|--------------|-------------|
| **APRO** | `approved` | `accredited` | cobrada | Pago aprobado ✅ |
| **CONT** | `pending` | `pending_contingency` | pendiente | Pendiente por contingencia ⏳ |
| **OTHE** | `rejected` | `cc_rejected_other_reason` | rechazada | Rechazado - otro motivo ❌ |
| **CALL** | `rejected` | `cc_rejected_call_for_authorize` | rechazada | Requiere autorización 📞 |
| **FUND** | `rejected` | `cc_rejected_insufficient_amount` | rechazada | Fondos insuficientes 💰 |
| **SECU** | `rejected` | `cc_rejected_bad_filled_security_code` | rechazada | CVV incorrecto 🔒 |
| **EXPI** | `rejected` | `cc_rejected_bad_filled_date` | rechazada | Fecha vencida 📅 |
| **FORM** | `rejected` | `cc_rejected_bad_filled_card_number` | rechazada | Número incorrecto 💳 |

## Archivos del Sistema que Manejan Estados

### 1. `procesar-pago-mp.php` (líneas 112-139)
- Procesa la respuesta inicial del pago
- Redirige según el estado recibido
- Reduce stock para pagos aprobados

### 2. `webhook.php` (líneas 159-205)
- Recibe notificaciones de cambios de estado
- Actualiza el estado de la orden
- Maneja reducción/restauración de stock
- Registra eventos en `data/webhook_log.json`

### 3. `error.php`
- Muestra mensajes personalizados según `status_detail`
- Proporciona sugerencias específicas para cada tipo de error
- Ofrece opciones de reintentar o elegir otro método de pago

### 4. `gracias.php`
- Muestra información específica para pagos pendientes/autorizados
- Adapta el mensaje según `status_detail`
- Proporciona instrucciones de seguimiento

### 5. `includes/functions.php` (líneas 734-1025)
- Función `get_payment_message()` - Mapea estados a mensajes amigables
- Retorna: título, mensaje, icono y sugerencias
- Cubre todos los status y status_detail posibles

## Gestión de Stock

| Estado MP | Acción de Stock |
|-----------|----------------|
| `approved` | ✅ Reduce stock |
| `pending`, `in_process`, `authorized`, `in_mediation` | ⏸️ Sin cambios |
| `rejected`, `cancelled` | ↩️ Restaura stock |
| `refunded`, `charged_back` | ↩️ Restaura stock |

**Protecciones**:
- Flag `stock_reduced` en la orden previene reducciones duplicadas
- Webhook verifica el estado del flag antes de modificar stock
- Todas las operaciones se registran en logs

## Flujo de Actualización de Estados

```
1. Usuario completa pago
   ↓
2. procesar-pago-mp.php recibe respuesta
   ↓
3. Se guarda payment_status y payment_status_detail en orden
   ↓
4. Se actualiza estado de orden según mapping
   ↓
5. Usuario es redirigido (gracias.php o error.php)
   ↓
6. Mercadopago envía webhook (puede ser inmediato o posterior)
   ↓
7. webhook.php actualiza estado si cambió
   ↓
8. Se maneja stock según nuevo estado
   ↓
9. Se registra en webhook_log.json
```

## Estados No Reconocidos

Si Mercadopago envía un `status` desconocido:

1. **Webhook**: Registra en log y retorna HTTP 200 sin cambiar la orden
2. **Procesador**: Trata como rechazado y redirige a error.php
3. **Log**: Se guarda en `data/webhook_log.json` para investigación

## Logs y Debugging

### Webhook Log (`data/webhook_log.json`)
Registra:
- Fecha y hora del evento
- Payment ID y Order ID
- Status y status_detail recibidos
- Acciones tomadas (reducción/restauración de stock)
- Errores si ocurren

### Error Log PHP
Registra en `error_log`:
- Intentos de pago
- Payment IDs generados
- Errores de comunicación con API de MP

## Verificación de Pagos en Sandbox

### Opción 1: Panel de Desarrolladores
1. Ir a [developers.mercadopago.com](https://developers.mercadopago.com)
2. Entrar a la cuenta de vendedor TEST
3. Sección "Webhooks" en el sidebar
4. Ver todas las notificaciones enviadas

### Opción 2: Herramienta del Sistema
`admin/verificar-pago-mp.php`
- Consulta payment ID directamente a la API
- Muestra todos los detalles del pago
- Compara con datos en la orden del sistema
- Lista últimos 10 pedidos con MP

## Tipos de Webhooks (Topics)

El sistema maneja múltiples tipos de eventos de Mercadopago para cubrir todo el ciclo de vida de un pago:

### 1. **payment** (Pagos) ✅ IMPLEMENTADO

**Descripción**: Notificación cuando se crea un pago o cambia su estado

**Cuándo se dispara**:
- Se crea un nuevo pago
- Un pago cambia de estado (pending → approved, approved → refunded, etc.)

**Acciones del sistema**:
- Consulta detalles del pago a la API de MP
- Encuentra la orden por `external_reference`
- Actualiza estado de la orden según el estado del pago
- Maneja stock (reduce si aprobado, restaura si rechazado/cancelado)
- Guarda datos completos del pago en `mercadopago_data`
- Registra evento en `webhook_log.json`

**Archivo**: `webhook.php` (líneas 76-291)

---

### 2. **chargebacks** (Contracargos) ✅ IMPLEMENTADO

**Descripción**: Notificación cuando un cliente disputa un pago con su banco

**Cuándo se dispara**:
- Se crea un contracargo (`action: created`)
- El vendedor pierde el contracargo (`action: lost`)
- El vendedor gana el contracargo (`action: won`)

**Acciones del sistema**:
- Registra información del contracargo en array `chargebacks` de la orden
- Si el action es `created` o `lost`:
  - Cambia estado de orden a `cancelada`
  - Restaura stock automáticamente
  - Agrega entrada al historial de estados
- Guarda todos los detalles del contracargo
- Registra evento en `webhook_log.json`

**Archivo**: `webhook.php` (líneas 293-404)

**Visualización**: Se muestra en vista de detalle de venta en admin con:
- ID del contracargo
- Acción (CREATED, LOST, WON)
- Payment ID relacionado
- Fecha del evento
- Advertencia sobre restauración de stock

---

### 3. **merchant_order** (Órdenes) ✅ IMPLEMENTADO (solo logging)

**Descripción**: Notificación sobre creación y actualización de merchant orders

**Cuándo se dispara**:
- Se crea una merchant order
- Se actualiza una merchant order
- Se cierra o expira una merchant order

**Acciones del sistema**:
- Registra el evento en `webhook_log.json`
- No toma acciones específicas (más relevante para Checkout Pro)
- Para Checkout Bricks, el topic `payment` es suficiente

**Archivo**: `webhook.php` (líneas 406-434)

**Nota**: En Checkout Bricks, las órdenes se manejan principalmente vía el topic `payment`. Este topic es más importante para Checkout Pro donde hay merchant_orders explícitas.

---

### Resumen de Topics Manejados

| Topic | Estado | Prioridad | Notas |
|-------|--------|-----------|-------|
| `payment` | ✅ Completo | 🔴 Alta | Maneja todo el ciclo de vida del pago |
| `chargebacks` | ✅ Completo | 🔴 Alta | Crítico para disputas |
| `merchant_order` | ✅ Logging | 🟡 Media | Principalmente para Checkout Pro |
| `point_integration_wh` | ⚪ No aplica | 🟢 Baja | Solo para pagos presenciales con Point |
| `subscription` | ⚪ No aplica | 🟢 Baja | Solo si se implementan suscripciones |

---

### Estructura de Datos Guardados

#### Para pagos (`mercadopago_data`):
```json
{
  "payment_id": "1342310445",
  "status": "approved",
  "status_detail": "accredited",
  "transaction_amount": 1500.00,
  "currency_id": "ARS",
  "date_created": "2025-11-08T15:30:45.000Z",
  "date_approved": "2025-11-08T15:30:50.000Z",
  "payment_method_id": "visa",
  "payment_type_id": "credit_card",
  "installments": 1,
  "card_last_four_digits": "4242",
  "payer_email": "test@test.com",
  "webhook_received_at": "2025-11-08 15:31:00"
}
```

#### Para contracargos (`chargebacks`):
```json
[
  {
    "chargeback_id": "123456",
    "payment_id": "1342310445",
    "action": "created",
    "date": "2025-11-08 16:00:00",
    "data": { /* datos completos del webhook */ }
  }
]
```

---

## Consideraciones de Seguridad

1. **Verificación de webhooks**: El sistema valida que las notificaciones vengan de MP
2. **External reference**: Se usa el Order ID para vincular pagos
3. **Token tracking**: Cada orden tiene un token único para acceso seguro
4. **Stock protegido**: Operaciones idempotentes previenen duplicaciones
5. **Logging completo**: Todos los webhooks se registran en `webhook_log.json`

## Mantenimiento Futuro

Si Mercadopago agrega nuevos estados:

1. Agregar case en `webhook.php` (línea ~159)
2. Agregar case en `procesar-pago-mp.php` (línea ~112)
3. Agregar mensajes en `includes/functions.php` función `get_payment_message()`
4. Actualizar esta documentación
5. Testear en sandbox antes de producción

---

**Última actualización**: 2025-11-08
**Documentación mantenida por**: Sistema de E-commerce PHP
