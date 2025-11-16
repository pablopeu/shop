#!/bin/bash

# Script de inicialización de archivos de configuración
# Crea archivos JSON necesarios con valores por defecto si no existen

echo "Inicializando archivos de configuración..."

# Colores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Crear directorio config si no existe
mkdir -p config

# Crear directorio data si no existe
mkdir -p data

# =====================================
# ARCHIVOS DE CONFIGURACIÓN (config/)
# =====================================

# config/site.json - Configuración del sitio (NO versionado)
if [ ! -f "config/site.json" ]; then
    cat > config/site.json << 'EOF'
{
    "site_name": "Mi Tienda",
    "site_description": "Tienda online de productos",
    "site_keywords": "tienda, productos, ecommerce",
    "site_owner": "",
    "contact_email": "contacto@mitienda.com",
    "contact_phone": "",
    "footer_text": "",
    "whatsapp": {
        "enabled": false,
        "number": "",
        "message": "Hola! Me interesa un producto de su tienda",
        "custom_link": "",
        "display_text": ""
    },
    "whatsapp_number": "",
    "logo": {
        "enabled": false,
        "path": "",
        "alt": "Logo"
    }
}
EOF
    echo -e "${GREEN}✓${NC} Creado: config/site.json"
else
    echo -e "  Ya existe: config/site.json (no se sobrescribe)"
fi

# config/footer.json - Configuración del footer (NO versionado)
if [ ! -f "config/footer.json" ]; then
    cat > config/footer.json << 'EOF'
{
    "layout": "simple",
    "columns": [],
    "social_links": {
        "facebook": "",
        "instagram": "",
        "twitter": "",
        "linkedin": "",
        "youtube": ""
    }
}
EOF
    echo -e "${GREEN}✓${NC} Creado: config/footer.json"
else
    echo -e "  Ya existe: config/footer.json (no se sobrescribe)"
fi

# config/email.json - Configuración de email (NO versionado)
if [ ! -f "config/email.json" ]; then
    cat > config/email.json << 'EOF'
{
    "enabled": false,
    "method": "mail",
    "from_email": "noreply@mitienda.com",
    "from_name": "Mi Tienda",
    "admin_email": "admin@mitienda.com",
    "smtp": {
        "host": "",
        "port": 587,
        "username": "",
        "password": "",
        "encryption": "tls"
    },
    "notifications": {
        "customer": {
            "order_confirmation": true,
            "payment_approved": true,
            "payment_pending": true,
            "payment_rejected": true,
            "order_shipped": true
        },
        "admin": {
            "new_order": true,
            "payment_approved": true,
            "chargeback_alert": true,
            "low_stock_alert": true
        }
    }
}
EOF
    echo -e "${GREEN}✓${NC} Creado: config/email.json"
else
    echo -e "  Ya existe: config/email.json (no se sobrescribe)"
fi

# config/telegram.json - Configuración de Telegram (NO versionado)
if [ ! -f "config/telegram.json" ]; then
    cat > config/telegram.json << 'EOF'
{
    "enabled": false,
    "bot_token": "",
    "chat_id": "",
    "notifications": {
        "new_order": true,
        "payment_approved": true,
        "payment_pending": false,
        "payment_rejected": true,
        "chargeback_alert": true,
        "order_shipped": false,
        "low_stock_alert": true
    },
    "thresholds": {
        "high_value_order": 50000
    }
}
EOF
    echo -e "${GREEN}✓${NC} Creado: config/telegram.json"
else
    echo -e "  Ya existe: config/telegram.json (no se sobrescribe)"
fi

# config/payment.json - Configuración de pagos (NO versionado)
if [ ! -f "config/payment.json" ]; then
    cat > config/payment.json << 'EOF'
{
    "mercadopago": {
        "enabled": false,
        "mode": "sandbox",
        "webhook_url": "",
        "webhook_security": {
            "validate_signature": false,
            "validate_timestamp": false,
            "validate_ip": true,
            "max_timestamp_age_minutes": 5
        }
    },
    "presencial": {
        "enabled": true,
        "instructions": "Por favor coordine el pago y retiro por WhatsApp"
    }
}
EOF
    echo -e "${GREEN}✓${NC} Creado: config/payment.json"
else
    echo -e "  Ya existe: config/payment.json (no se sobrescribe)"
fi

# config/theme.json - Theme activo (versionado)
if [ ! -f "config/theme.json" ]; then
    cat > config/theme.json << 'EOF'
{
    "active_theme": "minimal"
}
EOF
    echo -e "${GREEN}✓${NC} Creado: config/theme.json"
else
    echo -e "  Ya existe: config/theme.json (no se sobrescribe)"
fi

# config/currency.json - Multi-moneda (versionado)
if [ ! -f "config/currency.json" ]; then
    cat > config/currency.json << 'EOF'
{
    "primary": "ARS",
    "secondary": "USD",
    "dollar_type": "blue",
    "api_enabled": true,
    "manual_override": false,
    "exchange_rate": 1500,
    "exchange_rate_source": "manual"
}
EOF
    echo -e "${GREEN}✓${NC} Creado: config/currency.json"
else
    echo -e "  Ya existe: config/currency.json (no se sobrescribe)"
fi

# config/maintenance.json - Modo mantenimiento (versionado)
if [ ! -f "config/maintenance.json" ]; then
    cat > config/maintenance.json << 'EOF'
{
    "enabled": false,
    "bypass_code": "",
    "message": "Sitio en mantenimiento. Volveremos pronto."
}
EOF
    echo -e "${GREEN}✓${NC} Creado: config/maintenance.json"
else
    echo -e "  Ya existe: config/maintenance.json (no se sobrescribe)"
fi

# config/hero.json - Hero image config (versionado)
if [ ! -f "config/hero.json" ]; then
    cat > config/hero.json << 'EOF'
{
    "enabled": false,
    "title": "",
    "subtitle": "",
    "image": "",
    "background_color": "#667eea"
}
EOF
    echo -e "${GREEN}✓${NC} Creado: config/hero.json"
else
    echo -e "  Ya existe: config/hero.json (no se sobrescribe)"
fi

# config/carousel.json - Carrusel config (versionado)
if [ ! -f "config/carousel.json" ]; then
    cat > config/carousel.json << 'EOF'
{
    "slides": []
}
EOF
    echo -e "${GREEN}✓${NC} Creado: config/carousel.json"
else
    echo -e "  Ya existe: config/carousel.json (no se sobrescribe)"
fi

# config/products-heading.json - Encabezado de productos (versionado)
if [ ! -f "config/products-heading.json" ]; then
    cat > config/products-heading.json << 'EOF'
{
    "enabled": true,
    "title": "Nuestros Productos",
    "subtitle": "Descubre nuestra selección"
}
EOF
    echo -e "${GREEN}✓${NC} Creado: config/products-heading.json"
else
    echo -e "  Ya existe: config/products-heading.json (no se sobrescribe)"
fi

# config/dashboard.json - Dashboard config (versionado)
if [ ! -f "config/dashboard.json" ]; then
    cat > config/dashboard.json << 'EOF'
{
    "widgets_order": [
        "stock_bajo",
        "productos_activos",
        "sin_stock",
        "ordenes_totales",
        "ingreso_neto_ventas",
        "promociones",
        "cupones",
        "reviews_pendientes"
    ],
    "widgets": {
        "productos_activos": true,
        "stock_bajo": true,
        "sin_stock": true,
        "ordenes_totales": true,
        "ingreso_neto_ventas": true,
        "promociones": true,
        "cupones": true,
        "reviews_pendientes": true
    },
    "quick_actions_order": [
        "productos",
        "ventas",
        "cupones",
        "reviews",
        "config"
    ],
    "quick_actions": {
        "productos": true,
        "ventas": true,
        "cupones": true,
        "reviews": true,
        "config": true
    }
}
EOF
    echo -e "${GREEN}✓${NC} Creado: config/dashboard.json"
else
    echo -e "  Ya existe: config/dashboard.json (no se sobrescribe)"
fi

# config/analytics.json - Analytics config (versionado)
if [ ! -f "config/analytics.json" ]; then
    cat > config/analytics.json << 'EOF'
{
    "google_analytics": {
        "enabled": false,
        "measurement_id": ""
    },
    "facebook_pixel": {
        "enabled": false,
        "pixel_id": ""
    },
    "google_tag_manager": {
        "enabled": false,
        "container_id": ""
    }
}
EOF
    echo -e "${GREEN}✓${NC} Creado: config/analytics.json"
else
    echo -e "  Ya existe: config/analytics.json (no se sobrescribe)"
fi

# =====================================
# ARCHIVOS DE DATOS (data/)
# =====================================

# data/products.json - Listado de productos
if [ ! -f "data/products.json" ]; then
    cat > data/products.json << 'EOF'
{
    "products": []
}
EOF
    echo -e "${GREEN}✓${NC} Creado: data/products.json"
else
    echo -e "  Ya existe: data/products.json (no se sobrescribe)"
fi

# data/orders.json - Órdenes
if [ ! -f "data/orders.json" ]; then
    cat > data/orders.json << 'EOF'
{
    "orders": []
}
EOF
    echo -e "${GREEN}✓${NC} Creado: data/orders.json"
else
    echo -e "  Ya existe: data/orders.json (no se sobrescribe)"
fi

# data/archived_orders.json - Órdenes archivadas
if [ ! -f "data/archived_orders.json" ]; then
    cat > data/archived_orders.json << 'EOF'
{
    "orders": []
}
EOF
    echo -e "${GREEN}✓${NC} Creado: data/archived_orders.json"
else
    echo -e "  Ya existe: data/archived_orders.json (no se sobrescribe)"
fi

# data/promotions.json - Promociones
if [ ! -f "data/promotions.json" ]; then
    cat > data/promotions.json << 'EOF'
{
    "promotions": []
}
EOF
    echo -e "${GREEN}✓${NC} Creado: data/promotions.json"
else
    echo -e "  Ya existe: data/promotions.json (no se sobrescribe)"
fi

# data/coupons.json - Cupones
if [ ! -f "data/coupons.json" ]; then
    cat > data/coupons.json << 'EOF'
{
    "coupons": []
}
EOF
    echo -e "${GREEN}✓${NC} Creado: data/coupons.json"
else
    echo -e "  Ya existe: data/coupons.json (no se sobrescribe)"
fi

# data/reviews.json - Reviews
if [ ! -f "data/reviews.json" ]; then
    cat > data/reviews.json << 'EOF'
{
    "reviews": []
}
EOF
    echo -e "${GREEN}✓${NC} Creado: data/reviews.json"
else
    echo -e "  Ya existe: data/reviews.json (no se sobrescribe)"
fi

# data/admin_logs.json - Logs de acciones administrativas
if [ ! -f "data/admin_logs.json" ]; then
    cat > data/admin_logs.json << 'EOF'
{
    "logs": []
}
EOF
    echo -e "${GREEN}✓${NC} Creado: data/admin_logs.json"
else
    echo -e "  Ya existe: data/admin_logs.json (no se sobrescribe)"
fi

# =====================================
# DIRECTORIOS NECESARIOS
# =====================================

# Crear directorios necesarios
mkdir -p data/products
mkdir -p data/rate_limits
mkdir -p data/passwords
mkdir -p images/products
mkdir -p images/hero
mkdir -p images/carousel
mkdir -p images/themes
mkdir -p images/logos
mkdir -p images/footer

echo ""
echo -e "${GREEN}Inicialización completada!${NC}"
echo ""
echo "Nota: Los archivos de configuración han sido creados con valores por defecto."
echo "Configura todo desde el panel de administración (Admin → Configuración)."
echo ""
echo "Archivos NO versionados en git (configuración específica del entorno):"
echo "  - config/site.json"
echo "  - config/footer.json"
echo "  - config/email.json"
echo "  - config/telegram.json"
echo "  - config/payment.json"
echo ""
echo "Archivos SÍ versionados en git (configuración compartida):"
echo "  - config/theme.json"
echo "  - config/currency.json"
echo "  - config/maintenance.json"
echo "  - config/hero.json"
echo "  - config/carousel.json"
echo "  - config/products-heading.json"
echo "  - config/dashboard.json"
echo "  - config/analytics.json"
echo ""
