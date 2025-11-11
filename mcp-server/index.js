#!/usr/bin/env node

import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from '@modelcontextprotocol/sdk/types.js';
import mercadopago from 'mercadopago';
import { readFile } from 'fs/promises';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Load payment configuration
async function loadConfig() {
  try {
    const configPath = join(__dirname, '..', 'config', 'payment.json');
    const configData = await readFile(configPath, 'utf-8');
    return JSON.parse(configData);
  } catch (error) {
    console.error('Error loading config:', error);
    return null;
  }
}

// Initialize Mercadopago
async function initMercadoPago() {
  const config = await loadConfig();
  if (!config || !config.mercadopago) {
    throw new Error('Mercadopago configuration not found');
  }

  const isSandbox = config.mercadopago.mode === 'sandbox';
  const accessToken = isSandbox
    ? config.mercadopago.access_token_sandbox
    : config.mercadopago.access_token_prod;

  if (!accessToken) {
    throw new Error('Mercadopago access token not configured');
  }

  mercadopago.configure({
    access_token: accessToken,
  });

  return { config: config.mercadopago, isSandbox };
}

// Create MCP Server
const server = new Server(
  {
    name: 'mercadopago-mcp-server',
    version: '1.0.0',
  },
  {
    capabilities: {
      tools: {},
    },
  }
);

// List available tools
server.setRequestHandler(ListToolsRequestSchema, async () => {
  return {
    tools: [
      {
        name: 'create_payment',
        description: 'Create a new payment in Mercadopago',
        inputSchema: {
          type: 'object',
          properties: {
            transaction_amount: {
              type: 'number',
              description: 'Payment amount',
            },
            description: {
              type: 'string',
              description: 'Payment description',
            },
            payment_method_id: {
              type: 'string',
              description: 'Payment method ID (e.g., visa, master)',
            },
            payer_email: {
              type: 'string',
              description: 'Payer email',
            },
            external_reference: {
              type: 'string',
              description: 'External reference (e.g., order ID)',
            },
          },
          required: ['transaction_amount', 'description', 'payment_method_id', 'payer_email'],
        },
      },
      {
        name: 'get_payment',
        description: 'Get payment information by ID',
        inputSchema: {
          type: 'object',
          properties: {
            payment_id: {
              type: 'string',
              description: 'Payment ID',
            },
          },
          required: ['payment_id'],
        },
      },
      {
        name: 'search_payments',
        description: 'Search payments by criteria',
        inputSchema: {
          type: 'object',
          properties: {
            external_reference: {
              type: 'string',
              description: 'External reference to search',
            },
            status: {
              type: 'string',
              description: 'Payment status (approved, pending, rejected, etc.)',
            },
            limit: {
              type: 'number',
              description: 'Maximum number of results (default: 10)',
            },
          },
        },
      },
      {
        name: 'refund_payment',
        description: 'Refund a payment',
        inputSchema: {
          type: 'object',
          properties: {
            payment_id: {
              type: 'string',
              description: 'Payment ID to refund',
            },
            amount: {
              type: 'number',
              description: 'Amount to refund (optional, full refund if not specified)',
            },
          },
          required: ['payment_id'],
        },
      },
      {
        name: 'get_config',
        description: 'Get current Mercadopago configuration (mode, webhook settings)',
        inputSchema: {
          type: 'object',
          properties: {},
        },
      },
    ],
  };
});

// Handle tool calls
server.setRequestHandler(CallToolRequestSchema, async (request) => {
  try {
    const { config, isSandbox } = await initMercadoPago();

    switch (request.params.name) {
      case 'create_payment': {
        const { transaction_amount, description, payment_method_id, payer_email, external_reference } = request.params.arguments;

        const payment_data = {
          transaction_amount: Number(transaction_amount),
          description,
          payment_method_id,
          payer: {
            email: payer_email,
          },
        };

        if (external_reference) {
          payment_data.external_reference = external_reference;
        }

        const response = await mercadopago.payment.create(payment_data);

        return {
          content: [
            {
              type: 'text',
              text: JSON.stringify(response.body, null, 2),
            },
          ],
        };
      }

      case 'get_payment': {
        const { payment_id } = request.params.arguments;
        const response = await mercadopago.payment.get(Number(payment_id));

        return {
          content: [
            {
              type: 'text',
              text: JSON.stringify(response.body, null, 2),
            },
          ],
        };
      }

      case 'search_payments': {
        const { external_reference, status, limit = 10 } = request.params.arguments;

        const filters = {
          limit: Number(limit),
        };

        if (external_reference) {
          filters.external_reference = external_reference;
        }
        if (status) {
          filters.status = status;
        }

        const response = await mercadopago.payment.search({
          qs: filters,
        });

        return {
          content: [
            {
              type: 'text',
              text: JSON.stringify(response.body, null, 2),
            },
          ],
        };
      }

      case 'refund_payment': {
        const { payment_id, amount } = request.params.arguments;

        const refund_data = {};
        if (amount) {
          refund_data.amount = Number(amount);
        }

        const response = await mercadopago.refund.create({
          payment_id: Number(payment_id),
          ...refund_data,
        });

        return {
          content: [
            {
              type: 'text',
              text: JSON.stringify(response.body, null, 2),
            },
          ],
        };
      }

      case 'get_config': {
        return {
          content: [
            {
              type: 'text',
              text: JSON.stringify({
                mode: isSandbox ? 'sandbox' : 'production',
                enabled: config.enabled,
                webhook_url: config.webhook_url,
                webhook_security: config.webhook_security,
              }, null, 2),
            },
          ],
        };
      }

      default:
        throw new Error(`Unknown tool: ${request.params.name}`);
    }
  } catch (error) {
    return {
      content: [
        {
          type: 'text',
          text: `Error: ${error.message}\n\nStack: ${error.stack}`,
        },
      ],
      isError: true,
    };
  }
});

// Start server
async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  console.error('Mercadopago MCP Server running on stdio');
}

main().catch((error) => {
  console.error('Server error:', error);
  process.exit(1);
});
