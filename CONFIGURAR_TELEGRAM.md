# Configurar Notificaciones de Telegram (OPCIONAL)

Las notificaciones de Telegram son **opcionales**. El sistema funciona perfectamente sin ellas.

Si quieres recibir notificaciones en Telegram cuando:
- ✅ Se aprueba un pago
- ✅ Se rechaza un pago
- ✅ Hay un contracargo
- ✅ Stock bajo

Sigue estos pasos:

---

## 📱 Paso 1: Crear un Bot de Telegram

1. Abre Telegram y busca: **@BotFather**
2. Envía: `/newbot`
3. Asigna un nombre (ej: "PEU Shop Notificaciones")
4. Asigna un username (ej: "peu_shop_bot")
5. **Guarda el token** que te da (ej: `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`)

---

## 🆔 Paso 2: Obtener tu Chat ID

### Opción A: Usando un bot existente

1. Busca en Telegram: **@userinfobot**
2. Envíale cualquier mensaje
3. Te responderá con tu **Chat ID** (ej: `987654321`)

### Opción B: Usando la API

1. Envía un mensaje a tu bot (el que creaste en Paso 1)
2. Ve a esta URL (reemplaza `TU_BOT_TOKEN`):
   ```
   https://api.telegram.org/botTU_BOT_TOKEN/getUpdates
   ```
3. Busca `"chat":{"id":XXXXXXX}` en la respuesta

---

## ⚙️ Paso 3: Configurar Credenciales

**OPCIÓN A: Por archivo (RECOMENDADO - más seguro)**

Edita `/shop/admin/credenciales-secretas.json`:

```json
{
  "email": {
    "smtp_host": "...",
    "smtp_username": "...",
    "smtp_password": "..."
  },
  "telegram": {
    "bot_token": "123456789:ABCdefGHIjklMNOpqrsTUVwxyz",
    "chat_id": "987654321"
  }
}
```

**OPCIÓN B: Por config (menos seguro)**

Edita `/shop/config/telegram.json`:

```json
{
  "enabled": true,
  "bot_token": "123456789:ABCdefGHIjklMNOpqrsTUVwxyz",
  "chat_id": "987654321",
  "notifications": {
    "payment_approved": true,
    "payment_rejected": true
  }
}
```

---

## ✅ Paso 4: Probar

Ejecuta el script de prueba:

```bash
php /shop/admin/test-telegram.php
```

O crea un archivo temporal:

```php
<?php
require_once __DIR__ . '/includes/telegram.php';
send_telegram_test();
```

Deberías recibir un mensaje en Telegram confirmando que funciona.

---

## 🔒 Seguridad

**IMPORTANTE**: No subas el `bot_token` a Git ni lo compartas públicamente.

- ✅ Guárdalo en `admin/credenciales-secretas.json` (este archivo debe estar en `.gitignore`)
- ✅ O usa variables de entorno
- ❌ NO lo pongas en `config/telegram.json` si subes ese archivo a Git

---

## 💡 Diferencia entre archivos

- **`config/telegram.json`**: Configuración pública (enabled, qué notificaciones activar)
- **`admin/credenciales-secretas.json`**: Credenciales secretas (bot_token, chat_id)

El sistema busca primero en `credenciales-secretas.json` y si no lo encuentra, busca en `telegram.json`.

---

## ❌ Si no quieres usar Telegram

Simplemente deja `telegram.json` con `"enabled": false` y listo. El sistema funcionará normalmente sin enviar notificaciones de Telegram.

Las notificaciones por **email siempre funcionan** (al cliente y al admin).

---

## 🎯 Ejemplo de Notificación

Cuando se aprueba un pago, recibirás algo así:

```
✅ PAGO APROBADO

📝 Orden: #ORD-12345
💰 Total: $ 500.00
👤 Cliente: Simón Untroib
🆔 Payment ID: 133535068062
💳 Método: VISA **** 9078
📊 Cuotas: 1x

💵 Detalles Financieros:
   • Cobro: $ 500.00
   • Comisión MP: -$ 38.05
   • Acreditado: $ 461.95

✨ ¡Procesar y preparar para envío!
```

---

**Creado**: 2025-11-12
**Versión**: 1.0
