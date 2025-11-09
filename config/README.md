# Configuración del Proyecto

Este directorio contiene archivos de configuración para la tienda online.

## Archivos de Configuración Específicos del Entorno

Los siguientes archivos contienen configuraciones específicas de cada entorno (producción, desarrollo, branches) y **NO se versionan en git**:

- `site.json` - Configuración general del sitio (nombre, contacto, redes sociales)
- `footer.json` - Configuración del pie de página
- `email.json` - Configuración de correo electrónico y notificaciones
- `telegram.json` - Configuración de notificaciones por Telegram
- `payment.json` - Configuración de métodos de pago (Mercadopago, etc.)

## Archivos de Ejemplo

Para cada archivo de configuración existe un archivo `.example` que contiene la estructura por defecto:

- `site.json.example`
- `footer.json.example`
- `email.json.example`
- `telegram.json.example`
- `payment.json.example`

## Inicialización

### Primera vez (nuevo entorno)

Ejecuta el script de inicialización desde la raíz del proyecto:

```bash
./init-config.sh
```

Este script copiará los archivos `.example` a sus versiones reales si no existen.

### Manual

También puedes copiar manualmente los archivos:

```bash
cp config/site.json.example config/site.json
cp config/footer.json.example config/footer.json
cp config/email.json.example config/email.json
cp config/telegram.json.example config/telegram.json
cp config/payment.json.example config/payment.json
cp data/products.json.example data/products.json
```

## Importante

1. **No versionar configuraciones**: Los archivos `.json` (sin .example) están en `.gitignore` y no deben versionarse en git.

2. **Preservar configuraciones entre merges**: Al trabajar en diferentes branches, cada uno mantiene sus propias configuraciones. Los merges NO sobrescribirán tus configuraciones locales.

3. **Actualizar archivos .example**: Si agregas nuevos campos a la configuración, actualiza también el archivo `.example` correspondiente y commitéalo para que otros desarrolladores tengan la estructura actualizada.

4. **Productos**: El archivo `data/products.json` también se maneja de la misma manera para preservar los productos cargados en cada entorno.

## Archivos de Configuración Versionados

Estos archivos SÍ se versionan en git porque contienen configuraciones de comportamiento, no datos específicos:

- `theme.json` - Configuración del tema visual
- `hero.json` - Configuración del banner hero
- `carousel.json` - Configuración del carrusel
- `products-heading.json` - Configuración de encabezados de productos
- `maintenance.json` - Configuración de modo mantenimiento
- `dashboard.json` - Configuración del dashboard
- `currency.json` - Configuración de moneda
