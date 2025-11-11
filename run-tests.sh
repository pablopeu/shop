#!/bin/bash
###############################################################################
# Script de Ejecución de Tests - Sistema de Checkout
#
# Ejecuta tests automatizados del flujo completo de compra incluyendo:
# - Pagos reales con Mercadopago (tarjetas de prueba)
# - Validación de stock
# - Generación de órdenes
# - Webhooks
# - Notificaciones
#
# Uso:
#   ./run-tests.sh              # Ejecutar todos los tests (incluye MP)
#   ./run-tests.sh --skip-mp    # Saltar tests de Mercadopago
#   ./run-tests.sh --help       # Mostrar ayuda
###############################################################################

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Banner
echo ""
echo -e "${BLUE}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║       TEST RUNNER - Sistema de Checkout Automatizado        ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Verificar argumentos
SKIP_MP=""
HELP=false

for arg in "$@"; do
    case $arg in
        --skip-mp)
            SKIP_MP="--skip-mp"
            echo -e "${YELLOW}⚠️  Tests de Mercadopago DESACTIVADOS${NC}"
            ;;
        --help|-h)
            HELP=true
            ;;
        *)
            echo -e "${RED}❌ Argumento desconocido: $arg${NC}"
            HELP=true
            ;;
    esac
done

if [ "$HELP" = true ]; then
    echo "Uso: ./run-tests.sh [OPCIONES]"
    echo ""
    echo "Opciones:"
    echo "  --skip-mp     Saltar tests de Mercadopago (solo tests locales)"
    echo "  --help, -h    Mostrar esta ayuda"
    echo ""
    echo "Ejemplos:"
    echo "  ./run-tests.sh              # Ejecutar todos los tests"
    echo "  ./run-tests.sh --skip-mp    # Solo tests locales (sin MP)"
    echo ""
    exit 0
fi

# Verificar que PHP esté instalado
if ! command -v php &> /dev/null; then
    echo -e "${RED}❌ Error: PHP no está instalado${NC}"
    exit 1
fi

# Verificar versión de PHP
PHP_VERSION=$(php -r "echo PHP_VERSION;")
echo -e "${GREEN}✓ PHP Version: $PHP_VERSION${NC}"

# Verificar que el script de tests existe
if [ ! -f "test-checkout-flow.php" ]; then
    echo -e "${RED}❌ Error: test-checkout-flow.php no encontrado${NC}"
    exit 1
fi

# Verificar configuración de Mercadopago si no se está saltando
if [ -z "$SKIP_MP" ]; then
    echo -e "${BLUE}ℹ️  Verificando configuración de Mercadopago...${NC}"

    # Verificar que el archivo de credenciales existe
    if [ -f ".payment_credentials_path" ]; then
        CREDENTIALS_PATH=$(cat .payment_credentials_path)
        if [ ! -f "$CREDENTIALS_PATH" ]; then
            echo -e "${YELLOW}⚠️  Archivo de credenciales no encontrado: $CREDENTIALS_PATH${NC}"
            echo -e "${YELLOW}   Los tests de Mercadopago pueden fallar${NC}"
        else
            echo -e "${GREEN}✓ Credenciales de pago configuradas${NC}"
        fi
    else
        echo -e "${YELLOW}⚠️  Archivo .payment_credentials_path no encontrado${NC}"
        echo -e "${YELLOW}   Los tests de Mercadopago pueden fallar${NC}"
    fi
fi

echo ""
echo -e "${BLUE}🚀 Ejecutando tests...${NC}"
echo ""

# Ejecutar tests
php test-checkout-flow.php $SKIP_MP

# Capturar código de salida
EXIT_CODE=$?

echo ""

# Mostrar resultado final
if [ $EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                  ✓ TESTS COMPLETADOS                         ║${NC}"
    echo -e "${GREEN}╚══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${GREEN}🎉 Todos los tests pasaron exitosamente!${NC}"
    echo ""

    # Buscar el último informe HTML generado
    LATEST_REPORT=$(ls -t test-results-*.html 2>/dev/null | head -n1)
    if [ ! -z "$LATEST_REPORT" ]; then
        echo -e "${BLUE}📄 Informe HTML generado: ${LATEST_REPORT}${NC}"
        echo -e "${BLUE}   Abre este archivo en tu navegador para ver el informe detallado${NC}"
        echo ""
    fi
else
    echo -e "${RED}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║                  ✗ TESTS FALLARON                            ║${NC}"
    echo -e "${RED}╚══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${RED}⚠️  Algunos tests fallaron. Revisa el informe para más detalles.${NC}"
    echo ""

    # Buscar el último informe HTML generado
    LATEST_REPORT=$(ls -t test-results-*.html 2>/dev/null | head -n1)
    if [ ! -z "$LATEST_REPORT" ]; then
        echo -e "${YELLOW}📄 Informe HTML generado: ${LATEST_REPORT}${NC}"
        echo -e "${YELLOW}   Abre este archivo en tu navegador para ver los detalles de los errores${NC}"
        echo ""
    fi
fi

exit $EXIT_CODE
