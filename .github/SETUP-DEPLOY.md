# 🚀 Configuración de Deploy Automático por FTP

Este repositorio está configurado para hacer **deploy automático** a tu hosting por FTP cada vez que hagas `git push` a cualquier branch.

## 📋 Configuración Inicial (Solo una vez)

### Paso 1: Agregar Secrets en GitHub

Ve a tu repositorio en GitHub:

1. Click en **Settings** (⚙️)
2. En el menú lateral: **Secrets and variables** → **Actions**
3. Click en **New repository secret**
4. Agrega estos 4 secrets:

| Secret Name | Valor | Descripción |
|-------------|-------|-------------|
| `FTP_SERVER` | `ftp.peu.net` | Servidor FTP |
| `FTP_USERNAME` | `shop` | Usuario FTP |
| `FTP_PASSWORD` | `piruleti123!!` | Contraseña FTP |
| `FTP_REMOTE_PATH` | `/home2/uv0023/public_html/shop/` | Ruta en el servidor |

**✅ Configuración correcta para tu hosting peu.net**

### Paso 2: Verificar la Ruta Remota

La ruta remota depende de tu hosting. Las más comunes son:

- `/public_html/`
- `/httpdocs/`
- `/www/`
- `/` (raíz)
- `/domains/tudominio.com/public_html/`

**Consulta con tu hosting** si no estás seguro.

## 🎯 ¿Cómo Funciona?

### Deploy Automático

Cada vez que hagas `git push` a **cualquier branch**:

1. ✅ GitHub Actions se ejecuta automáticamente
2. ✅ Sube todos los archivos al servidor FTP
3. ✅ Excluye archivos innecesarios (tests, .git, etc.)
4. ✅ Tu sitio se actualiza automáticamente

### Ver el Progreso

1. Ve a tu repositorio en GitHub
2. Click en **Actions** (pestaña superior)
3. Verás todos los deploys ejecutándose

## 📦 Archivos que SE SUBEN

- ✅ Todos los archivos PHP del sistema
- ✅ Carpetas: `admin/`, `includes/`, `templates/`, `config/`, `data/`
- ✅ Assets: `css/`, `js/`, `images/`
- ✅ Configuraciones necesarias

## 🚫 Archivos que NO se suben

- ❌ `.git/` y `.github/` - Control de versiones
- ❌ `test-*.php` - Scripts de testing
- ❌ `run-*.sh` - Scripts de prueba
- ❌ `*.md` - Documentación
- ❌ `.payment_credentials_path` - Config local
- ❌ Logs de testing

## 🔐 Seguridad

### Archivos Sensibles

**IMPORTANTE:** Estos archivos NO están en el repositorio (por seguridad):

- `/home/payment_credentials.json` - Credenciales de Mercadopago
- Contraseñas de email/SMTP
- API keys privadas

**Debes configurarlos manualmente** en tu servidor la primera vez.

### Recomendaciones

1. ✅ Las credenciales FTP están en GitHub Secrets (seguro)
2. ✅ NUNCA hagas commit de archivos con contraseñas
3. ✅ Mantén `.gitignore` actualizado
4. ⚠️ El archivo `config/email.json` podría tener contraseñas SMTP

## 🧪 Testing Antes de Deploy

### Opción 1: Deploy Manual (Primera vez)

Antes de activar el deploy automático, prueba subiendo manualmente:

1. Descarga el código
2. Súbelo por FTP tradicional
3. Verifica que todo funciona
4. Configura archivos sensibles en el servidor
5. Activa el deploy automático

### Opción 2: Deploy a Branch de Testing

```bash
# Crea un branch de testing
git checkout -b testing

# Haz cambios y push
git add .
git commit -m "test: Prueba de deploy"
git push origin testing

# El deploy se ejecuta automáticamente al branch testing
# Verifica que funciona antes de mergear a main
```

## 🔄 Flujo de Trabajo Recomendado

### Para Desarrollo

```bash
# 1. Crear branch para feature
git checkout -b feature/nueva-funcionalidad

# 2. Hacer cambios y commits
git add .
git commit -m "feat: Nueva funcionalidad"

# 3. Push (deploy automático a branch)
git push origin feature/nueva-funcionalidad

# 4. Verificar en el servidor que funciona

# 5. Mergear a main cuando esté listo
git checkout main
git merge feature/nueva-funcionalidad
git push origin main  # Deploy a producción
```

## 📊 Monitoreo

### Ver Logs de Deploy

1. GitHub → Actions
2. Click en el deploy que quieres ver
3. Verás:
   - ✅ Archivos subidos
   - ✅ Tiempo de ejecución
   - ❌ Errores si los hay

### Notificaciones

GitHub te enviará email si un deploy falla.

## 🆘 Troubleshooting

### Deploy falla con "Connection refused"

**Causa:** Credenciales FTP incorrectas o servidor caído.

**Solución:**
1. Verifica los secrets en GitHub
2. Prueba conectarte por FTP manualmente
3. Contacta a tu hosting

### Deploy exitoso pero archivos no se actualizan

**Causa:** Ruta remota incorrecta.

**Solución:**
1. Verifica `FTP_REMOTE_PATH` en secrets
2. Conéctate por FTP y confirma la ruta
3. Actualiza el secret en GitHub

### "Permission denied" al subir archivos

**Causa:** El usuario FTP no tiene permisos de escritura.

**Solución:**
1. Contacta a tu hosting
2. Verifica permisos de carpetas (deben ser 755)
3. Verifica permisos de archivos (deben ser 644)

### Deploy muy lento

**Causa:** Sube demasiados archivos.

**Solución:**
- El deploy solo sube archivos modificados
- Primera vez es lenta (sube todo)
- Deploys siguientes son rápidos

## 🔧 Personalización

### Excluir Archivos Adicionales

Edita `.github/workflows/deploy-ftp.yml`:

```yaml
exclude: |
  **/.git*
  # Agrega más archivos aquí
  mi-archivo.php
  carpeta-a-excluir/**
```

### Deploy Solo desde Main

Si solo quieres deploy desde `main`:

```yaml
on:
  push:
    branches:
      - main  # Solo main
```

### Deploy Manual

Si prefieres trigger manual:

```yaml
on:
  workflow_dispatch:  # Permite ejecución manual
```

## 📞 Soporte

- **GitHub Actions**: https://docs.github.com/actions
- **FTP Deploy Action**: https://github.com/SamKirkland/FTP-Deploy-Action
- **Tu Hosting**: Contacta soporte para ayuda con FTP

---

**¿Todo configurado?** Haz tu primer commit y push para probar! 🚀
