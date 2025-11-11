#!/bin/bash
###############################################################################
# Script de Testing en Producción
#
# Este script ejecuta tests reales en tu tienda que DEJAN REGISTRO de todo:
# - Órdenes de prueba que quedan en el sistema
# - Uso de cupones y descuentos
# - Validación de stock insuficiente
# - Emails y notificaciones generadas
# - Logs de todas las operaciones
#
# IMPORTANTE: Los datos NO se borran al finalizar.
# Todo queda registrado para que puedas verificar en el backoffice.
#
# Uso: ./run-production-test.sh
###############################################################################

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Banner
echo ""
echo -e "${BLUE}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║     TESTING EN PRODUCCIÓN - Verificación Completa           ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Advertencia
echo -e "${YELLOW}⚠️  IMPORTANTE:${NC}"
echo -e "${YELLOW}   Este script crea órdenes REALES que quedarán en tu sistema.${NC}"
echo -e "${YELLOW}   NO se borran automáticamente.${NC}"
echo ""
echo -n "¿Deseas continuar? (s/N): "
read -r response

if [[ ! "$response" =~ ^[Ss]$ ]]; then
    echo -e "${RED}Test cancelado.${NC}"
    exit 0
fi

echo ""

# Verificar PHP
if ! command -v php &> /dev/null; then
    echo -e "${RED}❌ Error: PHP no está instalado${NC}"
    exit 1
fi

# Verificar archivo
if [ ! -f "test-production.php" ]; then
    echo -e "${RED}❌ Error: test-production.php no encontrado${NC}"
    exit 1
fi

echo -e "${BLUE}🚀 Ejecutando tests de producción...${NC}"
echo ""

# Ejecutar tests
php test-production.php

# Capturar resultado
EXIT_CODE=$?

echo ""

# Mostrar resultado
if [ $EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                  ✓ TESTS COMPLETADOS                         ║${NC}"
    echo -e "${GREEN}╚══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${GREEN}✅ Tests de producción completados exitosamente!${NC}"
    echo ""
    echo -e "${BLUE}📋 Próximos pasos:${NC}"
    echo "  1. Abre tu backoffice de administración"
    echo "  2. Revisa la sección de Órdenes/Ventas"
    echo "  3. Verifica los emails enviados"
    echo "  4. Revisa los logs de operaciones"
    echo "  5. Chequea las notificaciones de Telegram (si están configuradas)"
    echo ""
    echo -e "${BLUE}📄 Log detallado guardado en: production-test-log.json${NC}"
    echo ""
else
    echo -e "${RED}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║                  ✗ TESTS FALLARON                            ║${NC}"
    echo -e "${RED}╚══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${RED}⚠️  Algunos tests fallaron. Revisa el output arriba.${NC}"
    echo ""
fi

exit $EXIT_CODE
