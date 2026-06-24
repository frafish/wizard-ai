<?php
namespace WizardAi\Modules\Ai\Traits\Abilities;

trait WooCommerce {
    public function register_woocommerce_abilities() {
        if (class_exists('WooCommerce')) {
            wp_register_ability('ai/manage-woocommerce', [
                'label' => __('Manage WooCommerce', 'wizard-ai'),
                'description' => __('Manage WooCommerce products and orders safely using native WC functions.', 'wizard-ai'),
                'category' => 'wizard-blocks',
                'execute_callback' => function($input) {
                    if (!class_exists('WooCommerce')) {
                        return new \WP_Error('wc_missing', 'WooCommerce is not installed or active.');
                    }
                    
                    $action = $input['action'];
                    $args = isset($input['args']) && is_array($input['args']) ? $input['args'] : [];

                    if ($action === 'get_products') {
                        $products = wc_get_products(array_merge(['limit' => 10], $args));
                        $data = [];
                        foreach ($products as $p) {
                            $data[] = [
                                'id' => $p->get_id(),
                                'name' => $p->get_name(),
                                'price' => $p->get_price(),
                                'stock_quantity' => $p->get_stock_quantity(),
                                'status' => $p->get_status(),
                            ];
                        }
                        return ['success' => true, 'products' => $data];
                    } elseif ($action === 'update_product') {
                        if (empty($args['id'])) return new \WP_Error('missing_id', 'Product ID is required in args.');
                        $product = wc_get_product($args['id']);
                        if (!$product) return new \WP_Error('invalid_product', 'Product not found.');
                        
                        if (isset($args['price'])) { $product->set_regular_price($args['price']); $product->set_price($args['price']); }
                        if (isset($args['stock_quantity'])) { $product->set_manage_stock(true); $product->set_stock_quantity($args['stock_quantity']); }
                        if (isset($args['name'])) $product->set_name($args['name']);
                        
                        $product->save();
                        return ['success' => true, 'message' => 'Product updated successfully.'];
                    } elseif ($action === 'create_product') {
                        $product = new \WC_Product_Simple();
                        if (isset($args['name'])) $product->set_name($args['name']);
                        if (isset($args['price'])) { $product->set_regular_price($args['price']); $product->set_price($args['price']); }
                        if (isset($args['stock_quantity'])) { $product->set_manage_stock(true); $product->set_stock_quantity($args['stock_quantity']); }
                        $product->save();
                        return ['success' => true, 'product_id' => $product->get_id()];
                    } elseif ($action === 'get_orders') {
                        $orders = wc_get_orders(array_merge(['limit' => 10], $args));
                        $data = [];
                        foreach ($orders as $o) {
                            $data[] = [
                                'id' => $o->get_id(),
                                'status' => $o->get_status(),
                                'total' => $o->get_total(),
                                'currency' => $o->get_currency(),
                                'billing_email' => $o->get_billing_email(),
                                'date_created' => $o->get_date_created() ? $o->get_date_created()->date('Y-m-d H:i:s') : null,
                            ];
                        }
                        return ['success' => true, 'orders' => $data];
                    } elseif ($action === 'update_order') {
                        if (empty($args['id'])) return new \WP_Error('missing_id', 'Order ID is required in args.');
                        $order = wc_get_order($args['id']);
                        if (!$order) return new \WP_Error('invalid_order', 'Order not found.');
                        
                        if (isset($args['status'])) $order->set_status($args['status']);
                        
                        $order->save();
                        return ['success' => true, 'message' => 'Order updated successfully.'];
                    } elseif ($action === 'get_coupons') {
                        $coupons = wc_get_coupons(array_merge(['limit' => 10], $args));
                        $data = [];
                        foreach ($coupons as $c) {
                            $data[] = [
                                'id' => $c->get_id(),
                                'code' => $c->get_code(),
                                'amount' => $c->get_amount(),
                                'discount_type' => $c->get_discount_type(),
                            ];
                        }
                        return ['success' => true, 'coupons' => $data];
                    } elseif ($action === 'create_coupon') {
                        $coupon = new \WC_Coupon();
                        if (isset($args['code'])) $coupon->set_code($args['code']);
                        if (isset($args['amount'])) $coupon->set_amount($args['amount']);
                        if (isset($args['discount_type'])) $coupon->set_discount_type($args['discount_type']);
                        $coupon->save();
                        return ['success' => true, 'coupon_id' => $coupon->get_id()];
                    } elseif ($action === 'update_coupon') {
                        if (empty($args['id'])) return new \WP_Error('missing_id', 'Coupon ID is required.');
                        $coupon = new \WC_Coupon($args['id']);
                        if (!$coupon->get_id()) return new \WP_Error('invalid_coupon', 'Coupon not found.');
                        if (isset($args['code'])) $coupon->set_code($args['code']);
                        if (isset($args['amount'])) $coupon->set_amount($args['amount']);
                        if (isset($args['discount_type'])) $coupon->set_discount_type($args['discount_type']);
                        $coupon->save();
                        return ['success' => true, 'message' => 'Coupon updated.'];
                    }

                    return new \WP_Error('invalid_action', 'Unsupported action: ' . $action);
                },
                'permission_callback' => function() { return current_user_can('manage_woocommerce'); },
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'action' => ['type' => 'string', 'enum' => ['get_products', 'create_product', 'update_product', 'get_orders', 'update_order', 'get_coupons', 'create_coupon', 'update_coupon'], 'description' => 'The action to perform.'],
                        'args' => ['type' => 'object', 'description' => 'Arguments for the action.']
                    ],
                    'required' => ['action']
                ]
            ]);
        }

    }
}
