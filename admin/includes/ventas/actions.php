<?php
/**
 * Ventas Actions - Manejo de acciones POST
 * Procesa todas las acciones relacionadas con órdenes
 */

// Prevent direct access
if (!defined('ADMIN_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Handle all order-related actions
 * @return array ['message' => string, 'error' => string]
 */
function handle_order_actions() {
    $result = ['message' => '', 'error' => ''];

    // Update order status
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        $order_id = $_POST['order_id'] ?? '';
        $new_status = $_POST['status'] ?? '';

        if (update_order_status($order_id, $new_status, $_SESSION['username'])) {
            $result['message'] = 'Estado actualizado exitosamente';

            // Send notification when order is marked as shipped
            if ($new_status === 'shipped') {
                $updated_order = get_order_by_id($order_id);
                if ($updated_order && !empty($updated_order['customer_email'])) {
                    send_order_shipped_email($updated_order);
                }
            }

            // Send notification when order is marked as cobrada (paid)
            if ($new_status === 'cobrada') {
                $updated_order = get_order_by_id($order_id);
                if ($updated_order) {
                    // Send notification based on customer's contact preference
                    $contact_preference = $updated_order['contact_preference'] ?? 'email';

                    error_log("Order {$order_id} marked as cobrada. Contact preference: {$contact_preference}");

                    if ($contact_preference === 'telegram' && !empty($updated_order['telegram_chat_id'])) {
                        // Send via Telegram
                        error_log("Sending Telegram notification to chat_id: {$updated_order['telegram_chat_id']}");
                        $telegram_result = send_telegram_order_paid_to_customer($updated_order);
                        error_log("Telegram notification result: " . ($telegram_result ? 'SUCCESS' : 'FAILED'));
                    } elseif (!empty($updated_order['customer_email'])) {
                        // Send via Email (default)
                        error_log("Sending email notification to: {$updated_order['customer_email']}");
                        $email_result = send_order_paid_email($updated_order);
                        error_log("Email notification result: " . ($email_result ? 'SUCCESS' : 'FAILED'));
                    } else {
                        error_log("No valid contact method found for order {$order_id}");
                    }
                } else {
                    error_log("Could not retrieve updated order {$order_id}");
                }
            }
        } else {
            $result['error'] = 'Error al actualizar el estado';
        }
    }

    // Add tracking number
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tracking'])) {
        $order_id = $_POST['order_id'] ?? '';
        $tracking_number = sanitize_input($_POST['tracking_number'] ?? '');
        $tracking_url = sanitize_input($_POST['tracking_url'] ?? '');

        if (add_order_tracking($order_id, $tracking_number, $tracking_url)) {
            $result['message'] = 'Número de seguimiento agregado';
            log_admin_action('tracking_added', $_SESSION['username'], [
                'order_id' => $order_id,
                'tracking_number' => $tracking_number
            ]);
        } else {
            $result['error'] = 'Error al agregar el número de seguimiento';
        }
    }

    // Cancel order
    if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['id'])) {
        $order_id = $_GET['id'];
        if (cancel_order($order_id, 'Cancelado por admin', $_SESSION['username'])) {
            $result['message'] = 'Orden cancelada y stock restaurado';
        } else {
            $result['error'] = 'Error al cancelar la orden';
        }
    }

    // Handle bulk actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
        $action = $_POST['bulk_action'];
        $selected_orders = $_POST['selected_orders'] ?? [];

        if (!empty($selected_orders)) {
            $success_count = 0;
            foreach ($selected_orders as $order_id) {
                if ($action === 'archive') {
                    if (archive_order($order_id)) {
                        $success_count++;
                    }
                } elseif ($action === 'cancel') {
                    if (cancel_order($order_id, 'Cancelado en masa por admin', $_SESSION['username'])) {
                        $success_count++;
                    }
                } elseif (in_array($action, ['pending', 'cobrada', 'shipped', 'delivered'])) {
                    if (update_order_status($order_id, $action, $_SESSION['username'])) {
                        $success_count++;

                        // Send email notification when order is marked as shipped
                        if ($action === 'shipped') {
                            $updated_order = get_order_by_id($order_id);
                            if ($updated_order && !empty($updated_order['customer_email'])) {
                                send_order_shipped_email($updated_order);
                            }
                        }
                    }
                }
            }

            $result['message'] = "$success_count orden(es) procesada(s) exitosamente";
        } else {
            $result['error'] = 'No se seleccionaron órdenes';
        }
    }

    return $result;
}
