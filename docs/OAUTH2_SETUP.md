# Configuración OAuth2 para Gmail

## ¿Por qué OAuth2?

OAuth2 es **mucho más seguro** que usar App Passwords:

- ✅ **No almacena contraseñas** - Solo tokens revocables
- ✅ **Tokens con expiración automática** - Se refrescan automáticamente
- ✅ **Permisos específicos** - Solo acceso para enviar emails
- ✅ **Revocación fácil** - Puedes revocar el acceso sin cambiar contraseña
- ✅ **Recomendado por Google** - Método de autenticación oficial

## Configuración en Google Cloud Console

### Paso 1: Crear Proyecto

1. Ve a [Google Cloud Console](https://console.cloud.google.com/)
2. Crea un proyecto nuevo o selecciona uno existente
3. Anota el nombre del proyecto

### Paso 2: Habilitar Gmail API

1. En el menú lateral, ve a **"APIs & Services"** → **"Library"**
2. Busca **"Gmail API"**
3. Click en **"Enable"**

### Paso 3: Configurar OAuth Consent Screen

1. Ve a **"APIs & Services"** → **"OAuth consent screen"**
2. Selecciona **"External"** (a menos que uses Google Workspace)
3. Completa la información requerida:
   - App name: Nombre de tu tienda
   - User support email: Tu email
   - Developer contact: Tu email
4. En "Scopes", agrega: `https://mail.google.com/`
5. En "Test users", agrega el email que usarás para enviar emails
6. Click en **"Save and Continue"**

### Paso 4: Crear OAuth 2.0 Client ID

1. Ve a **"APIs & Services"** → **"Credentials"**
2. Click en **"Create Credentials"** → **"OAuth 2.0 Client IDs"**
3. Tipo de aplicación: **"Web application"**
4. Nombre: `Gmail SMTP OAuth2`
5. **Authorized redirect URIs**: Agrega la URL de callback
   ```
   https://tudominio.com/admin/oauth2-callback.php
   ```
   O si estás en desarrollo local:
   ```
   http://localhost/admin/oauth2-callback.php
   ```
6. Click en **"Create"**
7. **Copia el Client ID y Client Secret** (los necesitarás en el siguiente paso)

## Configuración en el Servidor

### Paso 1: Configurar Credenciales

Edita el archivo `config/credentials.php` (si no existe, cópialo desde `credentials.php.example`):

```php
'gmail_oauth2' => [
    'client_id' => 'TU_CLIENT_ID_AQUI.apps.googleusercontent.com',
    'client_secret' => 'TU_CLIENT_SECRET_AQUI'
],
```

⚠️ **IMPORTANTE**: Nunca subas `credentials.php` a Git. Este archivo está en `.gitignore`.

### Paso 2: Configurar Email en el Admin

1. Ve a **Admin → Notificaciones → Email**
2. Completa la configuración básica:
   - **Email Remitente**: Tu email de Gmail
   - **Nombre Remitente**: Nombre de tu tienda
   - **Email Admin**: Email donde recibirás notificaciones
3. En la sección **SMTP**:
   - **Host**: `smtp.gmail.com`
   - **Puerto**: `587`
   - **Usuario SMTP**: Tu email de Gmail completo
   - **Encriptación**: `TLS`
4. En **Método de Autenticación**, selecciona: **"OAuth2 (Más seguro - Recomendado)"**
5. Click en **"Guardar Configuración de Email"**

### Paso 3: Autorizar con Google

1. Después de guardar la configuración, verás el botón **"🔑 Autorizar con Google"**
2. Click en el botón
3. Se abrirá una ventana de Google pidiéndote que autorices la aplicación
4. Selecciona la cuenta de Gmail que usarás
5. Revisa los permisos (solo enviar emails)
6. Click en **"Allow"**
7. Serás redirigido de vuelta y verás el mensaje de éxito

## Verificación

Una vez autorizado, verás:

```
✅ OAuth2 Autorizado
Email: tu-email@gmail.com
Token expira: en 59 minutos
```

El token se refrescará automáticamente cada hora, sin intervención manual.

## Probar el Envío

1. En la sección **"Probar Configuración de Email"**
2. Ingresa un email de prueba
3. Click en **"📤 Enviar Test"**
4. Deberías recibir el email en unos segundos

## Solución de Problemas

### Error: "Client ID y Client Secret no están configurados"

- Verifica que `config/credentials.php` exista y tenga los valores correctos
- Asegúrate de haber copiado el Client ID y Secret correctamente

### Error: "redirect_uri_mismatch"

- La URL de redirect en Google Cloud Console debe coincidir exactamente con:
  ```
  https://tudominio.com/admin/oauth2-callback.php
  ```
- Verifica que uses `https://` si tienes SSL, o `http://` si estás en desarrollo

### Error: "Access blocked: This app's request is invalid"

- Asegúrate de haber completado el OAuth Consent Screen
- Verifica que el email que estás usando esté en la lista de "Test users"
- La app debe estar en modo "Testing" o "Published"

### Error al enviar email: "Authentication failed"

- El token puede haber expirado. Revoca la autorización y vuelve a autorizar
- Verifica que el email en "Usuario SMTP" sea el mismo que autorizaste

## Revocar Acceso

Si necesitas revocar el acceso:

1. Click en el botón **"🗑️ Revocar Autorización"** en el admin
2. O revoca desde [Google Account Permissions](https://myaccount.google.com/permissions)

## Migración desde App Password

Si actualmente usas App Password:

1. Cambia el método de autenticación a OAuth2
2. Autoriza con Google
3. Prueba el envío de emails
4. Una vez confirmado que funciona, puedes eliminar el App Password desde tu cuenta de Google

## Seguridad

- ✅ Los tokens OAuth2 se almacenan en `config/email.json`
- ✅ Este archivo está en `.gitignore` y no se versiona
- ✅ Los tokens expiran cada hora y se refrescan automáticamente
- ✅ Puedes revocar el acceso en cualquier momento
- ✅ Solo tienen permiso para enviar emails (scope: `https://mail.google.com/`)

## Mantenimiento

El sistema OAuth2 es **automático**:

- 🔄 Los tokens se refrescan automáticamente cada hora
- 📧 No necesitas intervención manual para seguir enviando emails
- 🔐 Es más seguro que almacenar contraseñas

## Diferencia vs App Password

| Característica | App Password | OAuth2 |
|---------------|--------------|---------|
| Seguridad | ⚠️ Media | ✅ Alta |
| Almacenamiento | Contraseña en texto plano | Tokens revocables |
| Expiración | No expira | Expira cada hora (se refresca auto) |
| Revocación | Cambiar contraseña | Click en un botón |
| Recomendado por Google | ❌ No | ✅ Sí |
| Configuración | Más simple | Requiere Google Cloud Console |

**Recomendación**: Usa OAuth2 para máxima seguridad en producción.
