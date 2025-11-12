# 🏭 Testing en Producción - Verificación Completa

Sistema de testing que simula compras **REALES** en tu tienda y deja TODO registrado para verificación en el backoffice.

## 🎯 ¿Qué hace este sistema?

A diferencia del sistema de testing automatizado (`test-checkout-flow.php`) que limpia los datos al finalizar, este sistema:

✅ **Crea órdenes REALES** que quedan permanentemente en el sistema
✅ **Usa productos existentes** de tu catálogo
✅ **Aplica cupones reales** si están configurados
✅ **Valida límites de stock** intentando comprar más de lo disponible
✅ **Genera emails y notificaciones** reales
✅ **Registra todo en logs** de operaciones
✅ **Exporta informe JSON** con todos los detalles

## 🚀 Cómo Usar

### Ejecución con Confirmación (Recomendada)

```bash
./run-production-test.sh
```

El script te pedirá confirmación antes de ejecutar:
```
⚠️  IMPORTANTE:
   Este script crea órdenes REALES que quedarán en tu sistema.
   NO se borran automáticamente.

¿Deseas continuar? (s/N):
```

### Ejecución Directa

```bash
php test-production.php
```

## 📋 Tests Ejecutados

### 1. Verificar Productos Disponibles
- Lista todos los productos activos
- Identifica cuáles tienen stock
- Selecciona productos para usar en tests

### 2. Verificar Cupones Activos
- Busca cupones configurados en el sistema
- Filtra solo los activos
- Los usa en tests de compra

### 3. Compra Exitosa (Presencial)
- ✅ Crea una orden real con pago presencial
- ✅ Reduce el stock del producto
- ✅ Genera número de orden (ej: ORD-2025-00001)
- ✅ Envía emails de confirmación
- ✅ Crea notificación de Telegram

### 4. Compra con Cupón de Descuento
- ✅ Aplica un cupón real del sistema
- ✅ Calcula descuento correctamente
- ✅ Crea orden con precio reducido
- ✅ Incrementa contador de usos del cupón

### 5. Intento de Compra Sin Stock
- ✅ Intenta comprar más unidades de las disponibles
- ✅ Verifica que el sistema rechace la compra
- ✅ Confirma que la validación funciona correctamente

### 6. Compra Múltiples Productos
- ✅ Crea una orden con 2+ productos
- ✅ Verifica cálculo de totales
- ✅ Reduce stock de todos los productos

### 7. Verificar Emails Generados
- Revisa todas las órdenes creadas
- Cuenta emails enviados
- Valida que se registraron correctamente

### 8. Verificar Órdenes en Sistema
- Confirma que todas las órdenes se crearon
- Valida que tienen números de orden únicos
- Verifica que están en la base de datos

## 📊 Resultado Esperado

Al finalizar, verás un resumen como este:

```
╔══════════════════════════════════════════════════════════════╗
║                  RESUMEN DE TESTS                            ║
╚══════════════════════════════════════════════════════════════╝

Total de tests:  8
✓ Exitosos:      8
✗ Fallidos:      0
⏱  Tiempo total:  15.23ms

📦 ÓRDENES CREADAS (revisar en backoffice):
────────────────────────────────────────────────────────
  • Orden #ORD-2025-00001
    ID: order-xxxxx
    Total: $10000 ARS
    Estado: pending
    Método pago: presencial

  • Orden #ORD-2025-00002
    ID: order-yyyyy
    Total: $18000 ARS
    Estado: pending
    Método pago: presencial
    Cupón usado: DESCUENTO10

📊 LOG DE OPERACIONES:
────────────────────────────────────────────────────────
  [2025-11-10 14:30:15] COMPRA_PRESENCIAL
  [2025-11-10 14:30:16] COMPRA_CON_CUPON
  [2025-11-10 14:30:17] VALIDACION_STOCK
  [2025-11-10 14:30:18] COMPRA_MULTIPLE
  [2025-11-10 14:30:19] EMAILS_GENERADOS

✅ Testing completado. Revisa el backoffice para ver todos los registros.
📄 Log detallado guardado en: production-test-log.json
```

## 📁 Archivos Generados

### production-test-log.json

Informe JSON detallado con:
- Timestamp de ejecución
- Duración total en milisegundos
- Resultados de cada test
- IDs de todas las órdenes creadas
- Log completo de operaciones

**Ejemplo:**
```json
{
    "timestamp": "2025-11-10T14:30:15+00:00",
    "duration_ms": 15.23,
    "results": [...],
    "orders_created": [
        "order-691234567890",
        "order-691234567891"
    ],
    "operations": [...]
}
```

## 🔍 Verificación en Backoffice

Después de ejecutar los tests, verifica en tu panel de administración:

### 1. Sección de Órdenes/Ventas
- Busca órdenes con nota: `[TEST PRODUCCIÓN]`
- Verifica números de orden (ORD-2025-XXXXX)
- Revisa estados (pending, cobrada, etc.)
- Confirma totales y métodos de pago

### 2. Emails Enviados
- Revisa tu bandeja de entrada de `test@ejemplo.com`
- Verifica emails de:
  - Confirmación de orden
  - Uso de cupón (si aplica)
  - Notificación de pago

### 3. Notificaciones Telegram
- Si tienes Telegram configurado
- Verifica que recibiste notificaciones de:
  - Nueva orden
  - Orden con cupón aplicado

### 4. Logs del Sistema
- Revisa `data/admin_logs.json`
- Busca entradas con tipo: `order_created`
- Verifica timestamps

### 5. Stock de Productos
- Los productos usados tendrán stock reducido
- Revisa el historial de cambios de stock
- Verifica en `data/stock_logs.json`

## ⚠️ Consideraciones Importantes

### Datos Persistentes

**IMPORTANTE:** Este sistema NO limpia los datos creados.

- ✅ Las órdenes quedan en el sistema permanentemente
- ✅ El stock se reduce realmente
- ✅ Los cupones incrementan su contador de usos
- ✅ Los emails se envían a direcciones reales

### Limpieza Manual

Si necesitas eliminar las órdenes de prueba:

1. Ve al backoffice de administración
2. Sección: Órdenes/Ventas
3. Filtra por: `[TEST PRODUCCIÓN]` en las notas
4. Elimina o archiva las órdenes manualmente

O usa este comando SQL (si usas BD):
```sql
DELETE FROM orders WHERE notes LIKE '%TEST PRODUCCIÓN%';
```

### Reposición de Stock

Si necesitas restaurar el stock:

1. Identifica los productos usados en `production-test-log.json`
2. Ve al backoffice → Productos
3. Ajusta manualmente el stock de cada producto
4. O revisa `data/stock_logs.json` para ver los cambios

## 🔧 Personalización

### Cambiar Email de Prueba

Edita `test-production.php`:
```php
$customer_test = [
    'name' => 'Tu Nombre',
    'email' => 'tu@email.com',  // ← Cambia esto
    'phone' => '+54911234567'
];
```

### Agregar Más Tests

Agrega nuevos métodos a la clase `ProductionTester`:

```php
private function testMiNuevoTest() {
    // Tu lógica aquí

    return "Descripción del resultado";
}
```

Luego agrégalo en `runAllTests()`:
```php
$this->runTest('Test 9: Mi Nuevo Test', [$this, 'testMiNuevoTest']);
```

## 📈 Uso Recomendado

### Cuándo Usar Este Sistema

✅ **Después de cambios importantes** en el código de checkout
✅ **Antes de lanzar a producción** una nueva versión
✅ **Para training** de equipo de soporte
✅ **Para debugging** de problemas reportados
✅ **Para demostración** a clientes/stakeholders

### Cuándo NO Usar

❌ **Durante testing automatizado** (usa `test-checkout-flow.php --skip-mp`)
❌ **En CI/CD** (los datos quedan permanentes)
❌ **Con productos de stock limitado** (se reduce el stock real)
❌ **En producción con tráfico real** (puede causar confusión)

## 🆚 Comparación con Otros Sistemas

| Característica | test-production.php | test-checkout-flow.php --skip-mp |
|----------------|---------------------|----------------------------------|
| Limpia datos | ❌ No | ✅ Sí |
| Usa productos reales | ✅ Sí | ❌ Crea temporales |
| Reduce stock | ✅ Permanente | ✅ Temporal (se restaura) |
| Envía emails | ✅ Sí | ✅ Sí (pero marcados como test) |
| Órdenes en backoffice | ✅ Permanentes | ✅ Se borran al finalizar |
| Usa cupones reales | ✅ Sí | ❌ Crea temporales |
| Ideal para | Verificación manual | Testing automatizado |

## 💡 Tips

1. **Ejecuta en horarios de bajo tráfico** para evitar confusión con órdenes reales
2. **Documenta las ejecuciones** guardando los archivos `production-test-log.json`
3. **Revisa el backoffice inmediatamente** después de ejecutar
4. **Marca las órdenes de prueba** para fácil identificación después
5. **Mantén un registro** de cuándo ejecutaste tests de producción

## 🐛 Troubleshooting

### "No hay productos activos en el sistema"

**Causa:** Tu catálogo está vacío o todos los productos están desactivados.

**Solución:**
1. Ve al backoffice → Productos
2. Activa al menos un producto
3. Asegúrate de que tenga stock > 0

### "Stock insuficiente para probar cupón"

**Causa:** El producto seleccionado se quedó sin stock.

**Solución:**
- Esto NO es un error, es normal
- El test se salta automáticamente
- Agrega stock al producto si quieres probarlo

### Tests se saltan (SALTADO)

**Causa:** No hay datos suficientes (productos, cupones, stock).

**Solución:**
- Verifica que tengas productos con stock
- Crea cupones si quieres probar ese flujo
- El test sigue siendo válido aunque se salte

## 📚 Ver También

- `TESTING.md` - Testing automatizado con limpieza
- `run-tests.sh` - Tests automatizados sin Mercadopago
- `test-checkout-flow.php` - Sistema de testing completo

---

**¿Preguntas?** Revisa el código en `test-production.php` o consulta los logs generados.
