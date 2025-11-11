# Mercadopago MCP Server

Este servidor MCP (Model Context Protocol) expone herramientas para interactuar con la API de Mercadopago directamente desde Claude.

## ¿Qué es MCP?

MCP (Model Context Protocol) es un protocolo que permite a Claude interactuar con servicios externos a través de "herramientas". Este servidor expone herramientas específicas para Mercadopago.

## Instalación

```bash
cd mcp-server
npm install
```

## Configuración

El servidor lee automáticamente la configuración de `config/payment.json`. Asegúrate de tener configurado:

- `access_token_sandbox` o `access_token_prod`
- `mode`: "sandbox" o "production"

## Herramientas Disponibles

### 1. create_payment
Crea un nuevo pago en Mercadopago.

**Parámetros:**
- `transaction_amount` (número): Monto del pago
- `description` (string): Descripción del pago
- `payment_method_id` (string): ID del método de pago (visa, master, etc.)
- `payer_email` (string): Email del pagador
- `external_reference` (string, opcional): Referencia externa (ej: ID de orden)

**Ejemplo:**
```json
{
  "transaction_amount": 100.50,
  "description": "Compra en tienda",
  "payment_method_id": "visa",
  "payer_email": "comprador@example.com",
  "external_reference": "ORDER-123"
}
```

### 2. get_payment
Obtiene información de un pago por su ID.

**Parámetros:**
- `payment_id` (string): ID del pago

**Ejemplo:**
```json
{
  "payment_id": "1234567890"
}
```

### 3. search_payments
Busca pagos según criterios.

**Parámetros:**
- `external_reference` (string, opcional): Referencia externa
- `status` (string, opcional): Estado del pago (approved, pending, rejected, etc.)
- `limit` (número, opcional): Cantidad máxima de resultados (default: 10)

**Ejemplo:**
```json
{
  "external_reference": "ORDER-123",
  "status": "approved",
  "limit": 5
}
```

### 4. refund_payment
Reembolsa un pago.

**Parámetros:**
- `payment_id` (string): ID del pago a reembolsar
- `amount` (número, opcional): Monto a reembolsar (si no se especifica, reembolsa el total)

**Ejemplo:**
```json
{
  "payment_id": "1234567890",
  "amount": 50.00
}
```

### 5. get_config
Obtiene la configuración actual de Mercadopago.

**Sin parámetros**

## Uso con Claude

Para usar este servidor MCP con Claude Code, debes configurarlo en tu archivo de configuración de MCP (generalmente `~/.config/claude/mcp.json` o similar):

```json
{
  "mcpServers": {
    "mercadopago": {
      "command": "node",
      "args": ["/home/user/shop/mcp-server/index.js"],
      "env": {}
    }
  }
}
```

## Testing

Para probar el servidor manualmente:

```bash
npm start
```

El servidor se ejecutará y esperará comandos en stdin siguiendo el protocolo MCP.

## Seguridad

- Las credenciales se leen desde `config/payment.json`
- Nunca expongas tu access token de producción en código
- El servidor solo funciona localmente (stdio)
- Modo sandbox recomendado para desarrollo y testing

## Estados de Pago en Mercadopago

- `approved`: Pago aprobado
- `pending`: Pago pendiente
- `in_process`: En proceso
- `rejected`: Rechazado
- `cancelled`: Cancelado
- `refunded`: Reembolsado
- `charged_back`: Contracargo

## Troubleshooting

### Error: "Mercadopago access token not configured"
- Verifica que `config/payment.json` tenga el access token configurado
- Asegúrate de estar usando el token correcto según el modo (sandbox/production)

### Error al crear pago
- Verifica que todos los parámetros requeridos estén presentes
- En modo sandbox, usa emails de prueba de Mercadopago
- Revisa los logs de error para más detalles

## Referencias

- [Mercadopago API Reference](https://www.mercadopago.com.ar/developers/es/reference)
- [Model Context Protocol](https://modelcontextprotocol.io/)
- [MCP SDK](https://github.com/anthropics/mcp-sdk)
