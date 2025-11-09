#!/bin/bash

# Script de inicialización de archivos de configuración
# Copia los archivos .example a sus versiones reales si no existen

echo "Inicializando archivos de configuración..."

# Colores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Array de archivos a inicializar
config_files=(
    "config/site.json"
    "config/footer.json"
    "config/email.json"
    "config/telegram.json"
    "config/payment.json"
    "data/products.json"
)

# Copiar archivos .example si no existen
for file in "${config_files[@]}"; do
    if [ ! -f "$file" ]; then
        if [ -f "$file.example" ]; then
            cp "$file.example" "$file"
            echo -e "${GREEN}✓${NC} Creado: $file"
        else
            echo -e "${YELLOW}⚠${NC} Advertencia: No se encontró $file.example"
        fi
    else
        echo -e "  Ya existe: $file (no se sobrescribe)"
    fi
done

echo ""
echo -e "${GREEN}Inicialización completada!${NC}"
echo ""
echo "Nota: Estos archivos contienen configuraciones específicas del entorno"
echo "y no se versionan en git. Edítalos según tus necesidades."
