# 🧪 Sistema de Testing Automatizado - Flujo de Checkout

Sistema completo de testing automatizado para validar el flujo de compra end-to-end, incluyendo pagos **REALES** con Mercadopago usando tarjetas de prueba oficiales.

## 📋 ¿Qué testea este sistema?

Este sistema de testing valida **todo el flujo de checkout** de tu tienda:

### ✅ Tests Incluidos

1. **Gestión de Productos**
   - Creación de productos de prueba
   - Validación de stock
   - Productos en ARS y USD

2. **Sistema de Cupones**
   - Creación de cupones de descuento
   - Validación de cupones
   - Aplicación de descuentos

3. **Checkout Presencial**
   - Creación de órdenes con pago presencial
   - Reducción de stock inmediata
   - Generación de tracking token

4. **Checkout con Mercadopago (REAL)** 🔥
   - Pagos **reales** usando tarjetas de prueba oficiales
   - Escenario 1: Pago Aprobado
   - Escenario 2: Pago Rechazado
   - Escenario 3: Pago Pendiente
   - Validación de webhooks
   - Actualización de estados de orden

5. **Validaciones de Integridad**
   - Reducción correcta de stock
   - Generación de emails
   - Estados de órdenes correctos
   - Tracking de órdenes

6. **Generación de Informes**
   - Reporte en consola con colores
   - Reporte HTML detallado y visual
   - Métricas de tiempo de ejecución
   - Detalles de cada test

## 🚀 Cómo Usar el Sistema de Testing

### Opción 1: Script Wrapper (Recomendado)

La forma más sencilla de ejecutar los tests:

```bash
# Ejecutar todos los tests (incluye Mercadopago)
./run-tests.sh

# Ejecutar solo tests locales (sin Mercadopago)
./run-tests.sh --skip-mp

# Ver ayuda
./run-tests.sh --help
```

### Opción 2: Script PHP Directo

Para más control, puedes ejecutar el script PHP directamente:

```bash
# Ejecutar todos los tests
php test-checkout-flow.php

# Saltar tests de Mercadopago
php test-checkout-flow.php --skip-mp
```

## 📦 Requisitos

### Requisitos Básicos

- PHP 7.4 o superior
- Extensiones PHP: `curl`, `json`, `mbstring`
- Permisos de escritura en carpeta `data/`

### Para Tests de Mercadopago

Los tests de Mercadopago requieren credenciales configuradas:

1. **Archivo de credenciales**: `/home/payment_credentials.json` (o la ruta configurada)
2. **Credenciales de Sandbox**: Access Token y Public Key de Mercadopago en modo Sandbox
3. **Configuración**: `config/payment.json` con `mode: "sandbox"`

### Estructura de Credenciales

El archivo de credenciales debe tener este formato:

```json
{
  "mercadopago": {
    "access_token_sandbox": "TEST-xxxxx",
    "access_token_prod": "",
    "public_key_sandbox": "TEST-xxxxx",
    "public_key_prod": "",
    "webhook_secret_sandbox": "xxxxx",
    "webhook_secret_prod": ""
  }
}
```

## 🎯 Tarjetas de Prueba de Mercadopago

El sistema usa las tarjetas de prueba **oficiales** de Mercadopago:

### Pago Aprobado
- **Número**: 5031 7557 3453 0604
- **Titular**: APRO
- **CVV**: 123
- **Vencimiento**: 11/2025

### Pago Rechazado
- **Número**: 5031 7557 3453 0604
- **Titular**: OCHO
- **CVV**: 123
- **Vencimiento**: 11/2025

### Pago Pendiente
- **Número**: 5031 7557 3453 0604
- **Titular**: CONT
- **CVV**: 123
- **Vencimiento**: 11/2025

> 💡 El nombre del titular determina el resultado del pago según la documentación oficial de Mercadopago.

## 📊 Entendiendo los Resultados

### Salida en Consola

Los tests muestran progreso en tiempo real:

```
Running: Test 1: Crear productos de prueba... ✓
Running: Test 2: Crear cupón de prueba... ✓
Running: Test 6: Checkout MP - Pago aprobado...
   → Procesando pago real con Mercadopago (escenario: approved)...
   → Pago procesado: 12345678 - Status: approved
 ✓
```

### Informe HTML

Al finalizar, se genera un informe HTML detallado:

```
📄 Informe HTML generado: test-results-20251110-153045.html
```

**Características del informe:**
- ✅ Dashboard visual con métricas
- 📊 Gráficos de tests pasados/fallidos
- ⏱️ Tiempos de ejecución por test
- 📝 Detalles completos de cada test
- 🎨 Diseño responsive y profesional

### Códigos de Salida

- `0` = Todos los tests pasaron ✅
- `1` = Algún test falló ❌

Esto permite integración con CI/CD:

```bash
./run-tests.sh
if [ $? -eq 0 ]; then
    echo "Deploy OK"
else
    echo "Tests fallaron, no hacer deploy"
fi
```

## 🧹 Limpieza Automática

El sistema **limpia automáticamente** todos los datos de prueba:

- ✓ Elimina productos de prueba creados
- ✓ Elimina órdenes de prueba
- ✓ Elimina cupones de prueba
- ✓ Restaura el estado original del sistema

**No necesitas hacer limpieza manual.**

## ⚠️ Consideraciones Importantes

### Sobre Mercadopago

1. **Modo Sandbox**: Los tests deben ejecutarse en modo Sandbox
2. **Pagos Reales**: Aunque es Sandbox, los pagos son "reales" en el sentido de que llaman a la API real de Mercadopago
3. **Límites de API**: Mercadopago tiene límites de rate limiting en Sandbox
4. **Webhooks**: Los webhooks pueden tardar unos segundos en procesarse

### Sobre el Sistema

1. **Base de Datos**: El sistema modifica archivos JSON en `data/`
2. **Stock**: Se modifica el stock real durante los tests (se restaura al finalizar)
3. **Emails**: Los emails se registran pero no se envían realmente durante los tests
4. **Tiempo**: Los tests con Mercadopago pueden tardar 1-2 minutos

## 🔍 Troubleshooting

### "Access token de Mercadopago no configurado"

**Solución:**
1. Verifica que existe el archivo de credenciales
2. Verifica que `.payment_credentials_path` apunta al archivo correcto
3. Verifica que las credenciales de Sandbox están configuradas

### "Payment credentials file not found"

**Solución:**
```bash
# Verificar ubicación del archivo
cat .payment_credentials_path

# Verificar que el archivo existe
ls -la /home/payment_credentials.json
```

### "Error en pago Mercadopago: HTTP 401"

**Solución:**
- Tus credenciales de Sandbox son incorrectas o han expirado
- Regenera las credenciales en el panel de Mercadopago

### Tests muy lentos

**Solución:**
```bash
# Saltar tests de Mercadopago para tests más rápidos
./run-tests.sh --skip-mp
```

## 📈 Integración con CI/CD

### GitHub Actions

```yaml
name: Test Checkout Flow

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'

      - name: Run Tests
        run: |
          chmod +x run-tests.sh
          ./run-tests.sh --skip-mp

      - name: Upload Test Report
        if: always()
        uses: actions/upload-artifact@v2
        with:
          name: test-report
          path: test-results-*.html
```

### GitLab CI

```yaml
test_checkout:
  stage: test
  image: php:8.1-cli
  script:
    - chmod +x run-tests.sh
    - ./run-tests.sh --skip-mp
  artifacts:
    when: always
    paths:
      - test-results-*.html
```

## 🎨 Personalizar Tests

### Agregar Nuevos Tests

Edita `test-checkout-flow.php` y agrega métodos en la clase `CheckoutFlowTester`:

```php
private function testMiNuevoTest() {
    // Tu lógica de test aquí

    if ($algo_fallo) {
        throw new Exception("Descripción del error");
    }

    return "Descripción del resultado exitoso";
}
```

Luego agrégalo a `runAllTests()`:

```php
$this->runTest('Test 11: Mi Nuevo Test', [$this, 'testMiNuevoTest']);
```

### Modificar Tarjetas de Prueba

Edita la constante `TEST_CARDS` en `test-checkout-flow.php`:

```php
define('TEST_CARDS', [
    'approved' => [
        'number' => '5031755734530604',
        'cvv' => '123',
        // ...
    ],
    // ...
]);
```

## 📚 Recursos Adicionales

- [Documentación Oficial de Mercadopago - Testing](https://www.mercadopago.com.ar/developers/es/docs/checkout-api/testing)
- [Tarjetas de Prueba de Mercadopago](https://www.mercadopago.com.ar/developers/es/docs/checkout-api/testing/test-cards)
- [Webhooks de Mercadopago](https://www.mercadopago.com.ar/developers/es/docs/checkout-api/webhooks)

## 🤝 Contribuir

Si encuentras bugs o tienes mejoras:

1. Documenta el problema claramente
2. Incluye el informe HTML generado
3. Menciona tu versión de PHP
4. Describe los pasos para reproducir

## 📄 Licencia

Este sistema de testing es parte de tu aplicación de e-commerce y sigue la misma licencia.

---

**¿Preguntas?** Revisa la sección de Troubleshooting o consulta los logs generados en `test-results-*.html`.
