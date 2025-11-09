# Configuración de Notificaciones desde el Backoffice

## 🎯 Acceso

**Ubicación:** `Admin → 🔔 Notificaciones`

Accede al panel de administración y haz click en "🔔 Notificaciones" en el menú lateral.

## ✨ Características

La interfaz del backoffice te permite configurar todo el sistema de notificaciones sin editar archivos JSON manualmente:

- ✅ **Activar/desactivar** email y Telegram con un checkbox
- ✅ **Configurar credenciales** (SMTP, bot tokens, etc.)
- ✅ **Seleccionar notificaciones** activas (checkboxes individuales)
- ✅ **Probar configuración** antes de guardar
- ✅ **Creación automática** de archivos JSON con defaults
- ✅ **Interfaz visual** intuitiva y responsive

## 📧 Configuración de Email

### Panel Izquierdo - Email

1. **Activar sistema de emails**
   - Checkbox para habilitar/deshabilitar todo el sistema

2. **Método de envío**
   - `PHP mail()` - Usa el servidor local (más simple)
   - `SMTP` - Usa servidor externo como Gmail (más confiable)

3. **Configuración básica**
   - Email remitente: `noreply@tudominio.com`
   - Nombre remitente: `Tu Tienda`
   - Email administrador: Donde recibirás alertas

4. **Configuración SMTP** (solo si seleccionas método SMTP)
   - Host: `smtp.gmail.com`
   - Puerto: `587` (TLS) o `465` (SSL)
   - Usuario: Tu email completo
   - Contraseña: **App Password** (no tu contraseña normal)
   - Encriptación: TLS o SSL

   **Para Gmail:**
   - Ve a tu cuenta Google → Seguridad
   - Activa "Verificación en 2 pasos"
   - Genera una "Contraseña de aplicación"
   - Usa esa contraseña aquí

5. **Notificaciones al Cliente**
   - ☑️ Confirmación de orden creada
   - ☑️ Pago aprobado
   - ☑️ Pago rechazado
   - ☑️ Pago pendiente
   - ☑️ Orden enviada

6. **Notificaciones al Administrador**
   - ☑️ Nueva orden recibida
   - ☑️ Pago aprobado
   - ☑️ Alerta de contracargo (crítico)
   - ☑️ Alerta de stock bajo

7. **Probar Configuración**
   - Ingresa tu email
   - Click en "📤 Enviar Test"
   - Verifica que llegue el email

8. **Guardar**
   - Click en "💾 Guardar Configuración de Email"
   - Verás mensaje de confirmación

## 📱 Configuración de Telegram

### Panel Derecho - Telegram

1. **Activar notificaciones por Telegram**
   - Checkbox para habilitar/deshabilitar

2. **Obtener Bot Token**
   - Abre Telegram
   - Busca `@BotFather`
   - Envía `/newbot`
   - Sigue instrucciones (nombre y username)
   - Copia el token que te da
   - Pégalo en "Bot Token"

3. **Obtener Chat ID**
   - Busca tu bot en Telegram
   - Envía `/start` al bot
   - Visita en tu navegador:
     ```
     https://api.telegram.org/bot<TU_TOKEN>/getUpdates
     ```
   - Busca el número en `"chat":{"id":123456789}`
   - Cópialo a "Chat ID"

   **Para grupos/canales:**
   - Agrega el bot al grupo
   - Manda un mensaje mencionando al bot
   - El chat_id será negativo (ej: `-1001234567890`)

4. **Tipos de Notificaciones**
   - ☑️ Nueva orden
   - ☑️ Pago aprobado
   - ☐ Pago rechazado (opcional)
   - ☑️ 🚨 Alerta de contracargo (crítico)
   - ☑️ Alerta de stock bajo
   - ☑️ Destacar órdenes de alto valor 🌟

5. **Umbral para Órdenes de Alto Valor**
   - Número: `50000` (o el monto que prefieras)
   - Las órdenes ≥ a este monto se marcan con 🌟

6. **Probar Configuración**
   - Click en "📤 Enviar Mensaje de Prueba"
   - Verifica que llegue el mensaje a Telegram

7. **Guardar**
   - Click en "💾 Guardar Configuración de Telegram"
   - Verás mensaje de confirmación

## 🔧 Archivos JSON

Los archivos de configuración se crean automáticamente en:

- `config/email.json` - Configuración de email
- `config/telegram.json` - Configuración de Telegram

**No necesitas crearlos manualmente**. Si no existen, se crean automáticamente con valores por defecto la primera vez que se accede al sistema.

### Estructura de email.json

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
      ...
    },
    "admin": {
      "new_order": true,
      ...
    }
  }
}
```

### Estructura de telegram.json

```json
{
  "enabled": false,
  "bot_token": "",
  "chat_id": "",
  "notifications": {
    "new_order": true,
    "payment_approved": true,
    "chargeback_alert": true,
    "high_value_threshold": 50000
  }
}
```

## ✅ Flujo de Configuración Recomendado

### Primera Vez

1. **Accede a Admin → Notificaciones**
   - Los archivos JSON se crean automáticamente

2. **Configura Email**
   - Activa el sistema
   - Completa datos básicos
   - Si usas SMTP, completa esos datos
   - Selecciona qué notificaciones quieres
   - Prueba con tu email
   - Guarda

3. **Configura Telegram (opcional)**
   - Crea tu bot con @BotFather
   - Obtén tu chat_id
   - Pega credenciales
   - Selecciona notificaciones
   - Prueba
   - Guarda

4. **¡Listo!**
   - El sistema ya está funcionando
   - Recibirás notificaciones automáticamente

### Cambios Posteriores

1. Ve a Admin → Notificaciones
2. Modifica lo que necesites
3. Guarda
4. Los cambios aplican inmediatamente

## 🧪 Testing

### Test de Email

1. En la sección de Email, abajo de todo
2. Ingresa tu email en "Email de Prueba"
3. Click "📤 Enviar Test"
4. Deberías recibir un email simple
5. Si no llega:
   - Revisa spam
   - Verifica configuración SMTP
   - Revisa logs del servidor

### Test de Telegram

1. En la sección de Telegram, abajo de todo
2. Click "📤 Enviar Mensaje de Prueba"
3. Deberías recibir un mensaje en Telegram
4. Si no llega:
   - Verifica bot_token
   - Verifica chat_id
   - Asegúrate de haber iniciado chat con el bot

## 🔍 Troubleshooting

### Email no funciona

1. **Verifica que esté activado**
   - Checkbox "Activar sistema de emails" debe estar marcado

2. **Si usas SMTP:**
   - Verifica host, puerto, usuario, contraseña
   - Para Gmail, usa "App Password" NO tu contraseña normal
   - Prueba con puerto 587 (TLS) o 465 (SSL)

3. **Revisa logs**
   - Los errores se registran en logs de PHP
   - Busca mensajes como "Email sent successfully" o errores

### Telegram no funciona

1. **Verifica bot_token**
   - Formato: `123456789:ABCdefGHI...`
   - Obténlo de @BotFather

2. **Verifica chat_id**
   - Debe ser un número
   - Puede ser negativo para grupos
   - Obtén con `/getUpdates`

3. **Inicia conversación con el bot**
   - Busca tu bot en Telegram
   - Envía `/start`
   - Luego vuelve a probar

## 💡 Tips

1. **Usa SMTP para emails importantes**
   - Gmail SMTP es más confiable que `mail()`
   - Los emails no irán a spam

2. **Prueba primero**
   - Siempre usa los botones de test antes de guardar
   - Verifica que los emails lleguen correctamente

3. **Desactiva notificaciones que no necesites**
   - No todas las notificaciones son necesarias
   - Por ejemplo, "Pago rechazado" a Telegram puede ser spam

4. **Telegram para alertas críticas**
   - Activa solo chargebacks y órdenes de alto valor
   - Recibirás notificaciones instantáneas en tu móvil

5. **Email para todo lo demás**
   - Los clientes reciben confirmaciones profesionales
   - Tienes registro escrito de todas las transacciones

## 🎯 Casos de Uso

### Solo Email (Básico)

- Activa Email con `mail()`
- Activa todas las notificaciones de cliente
- Activa alertas de admin
- Telegram: desactivado

### Email + Telegram (Recomendado)

- Email: activado con SMTP
- Todas las notificaciones de cliente activas
- Telegram: activado
- Solo chargebacks y órdenes de alto valor

### Todo Desactivado (Testing)

- Ambos desactivados mientras pruebas
- Activas cuando estés listo para producción

## 📚 Más Información

Para documentación técnica completa, consulta:
- `docs/NOTIFICACIONES.md` - Documentación técnica completa
- Templates en `templates/email/` - Personalizar emails
- `includes/email.php` - Código del sistema de email
- `includes/telegram.php` - Código del sistema de Telegram

---

**Nota:** Todos los cambios que hagas en el backoffice se guardan en los archivos JSON y aplican inmediatamente. No necesitas reiniciar nada.
