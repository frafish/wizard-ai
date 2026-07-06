<?php
namespace WizardAi\Modules\Ai\Abilities;

trait WooCommerce {
    public function register_woocommerce_abilities() {
        if (!class_exists('WooCommerce')) {
            return;
        }



        wp_register_ability('woocommerce/create-product', [
            'label' => __('Create WooCommerce Product', 'wizard-ai'),
            'description' => __('Create a new WooCommerce simple product.', 'wizard-ai'),
            'category' => 'woocommerce',
            'meta' => ['plugin_name' => 'Wizard AI'],
            'execute_callback' => function($input) {
                $product = new \WC_Product_Simple();
                if (isset($input['name'])) $product->set_name($input['name']);
                if (isset($input['price'])) { $product->set_regular_price($input['price']); $product->set_price($input['price']); }
                if (isset($input['stock_quantity'])) { $product->set_manage_stock(true); $product->set_stock_quantity($input['stock_quantity']); }
                $product->save();
                return ['success' => true, 'product_id' => $product->get_id()];
            },
            'permission_callback' => function() { return current_user_can('manage_woocommerce'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Product name'],
                    'price' => ['type' => 'string', 'description' => 'Product price'],
                    'stock_quantity' => ['type' => 'integer', 'description' => 'Stock quantity']
                ],
                'required' => ['name']
            ]
        ]);

        wp_register_ability('woocommerce/update-product', [
            'label' => __('Update WooCommerce Product', 'wizard-ai'),
            'description' => __('Update an existing WooCommerce product.', 'wizard-ai'),
            'category' => 'woocommerce',
            'meta' => ['plugin_name' => 'Wizard AI'],
            'execute_callback' => function($input) {
                $product = wc_get_product($input['id']);
                if (!$product) return new \WP_Error('invalid_product', 'Product not found.');
                
                if (isset($input['price'])) { $product->set_regular_price($input['price']); $product->set_price($input['price']); }
                if (isset($input['stock_quantity'])) { $product->set_manage_stock(true); $product->set_stock_quantity($input['stock_quantity']); }
                if (isset($input['name'])) $product->set_name($input['name']);
                
                $product->save();
                return ['success' => true, 'message' => 'Product updated successfully.'];
            },
            'permission_callback' => function() { return current_user_can('manage_woocommerce'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Product ID'],
                    'name' => ['type' => 'string', 'description' => 'Product name'],
                    'price' => ['type' => 'string', 'description' => 'Product price'],
                    'stock_quantity' => ['type' => 'integer', 'description' => 'Stock quantity']
                ],
                'required' => ['id']
            ]
        ]);



        wp_register_ability('woocommerce/update-order', [
            'label' => __('Update WooCommerce Order', 'wizard-ai'),
            'description' => __('Update an existing WooCommerce order.', 'wizard-ai'),
            'category' => 'woocommerce',
            'meta' => ['plugin_name' => 'Wizard AI'],
            'execute_callback' => function($input) {
                $order = wc_get_order($input['id']);
                if (!$order) return new \WP_Error('invalid_order', 'Order not found.');
                
                if (isset($input['status'])) $order->set_status($input['status']);
                
                $order->save();
                return ['success' => true, 'message' => 'Order updated successfully.'];
            },
            'permission_callback' => function() { return current_user_can('manage_woocommerce'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Order ID'],
                    'status' => ['type' => 'string', 'description' => 'New order status (e.g. processing, completed, cancelled, refunded)']
                ],
                'required' => ['id']
            ]
        ]);

        wp_register_ability('woocommerce/get-coupons', [
            'label' => __('Get WooCommerce Coupons', 'wizard-ai'),
            'description' => __('Retrieve a list of WooCommerce coupons.', 'wizard-ai'),
            'category' => 'woocommerce',
            'meta' => ['plugin_name' => 'Wizard AI'],
            'execute_callback' => function($input) {
                $args = isset($input['args']) && is_array($input['args']) ? $input['args'] : [];
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
            },
            'permission_callback' => function() { return current_user_can('manage_woocommerce'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'args' => ['type' => 'object', 'description' => 'Arguments for wc_get_coupons (e.g. limit).']
                ]
            ]
        ]);

        wp_register_ability('woocommerce/create-coupon', [
            'label' => __('Create WooCommerce Coupon', 'wizard-ai'),
            'description' => __('Create a new WooCommerce coupon.', 'wizard-ai'),
            'category' => 'woocommerce',
            'meta' => ['plugin_name' => 'Wizard AI'],
            'execute_callback' => function($input) {
                $coupon = new \WC_Coupon();
                if (isset($input['code'])) $coupon->set_code($input['code']);
                if (isset($input['amount'])) $coupon->set_amount($input['amount']);
                if (isset($input['discount_type'])) $coupon->set_discount_type($input['discount_type']);
                $coupon->save();
                return ['success' => true, 'coupon_id' => $coupon->get_id()];
            },
            'permission_callback' => function() { return current_user_can('manage_woocommerce'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'string', 'description' => 'Coupon code'],
                    'amount' => ['type' => 'string', 'description' => 'Discount amount'],
                    'discount_type' => ['type' => 'string', 'description' => 'Discount type (e.g. percent, fixed_cart, fixed_product)']
                ],
                'required' => ['code', 'amount']
            ]
        ]);

        wp_register_ability('woocommerce/update-coupon', [
            'label' => __('Update WooCommerce Coupon', 'wizard-ai'),
            'description' => __('Update an existing WooCommerce coupon.', 'wizard-ai'),
            'category' => 'woocommerce',
            'meta' => ['plugin_name' => 'Wizard AI'],
            'execute_callback' => function($input) {
                $coupon = new \WC_Coupon($input['id']);
                if (!$coupon->get_id()) return new \WP_Error('invalid_coupon', 'Coupon not found.');
                if (isset($input['code'])) $coupon->set_code($input['code']);
                if (isset($input['amount'])) $coupon->set_amount($input['amount']);
                if (isset($input['discount_type'])) $coupon->set_discount_type($input['discount_type']);
                $coupon->save();
                return ['success' => true, 'message' => 'Coupon updated.'];
            },
            'permission_callback' => function() { return current_user_can('manage_woocommerce'); },
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Coupon ID'],
                    'code' => ['type' => 'string', 'description' => 'Coupon code'],
                    'amount' => ['type' => 'string', 'description' => 'Discount amount'],
                    'discount_type' => ['type' => 'string', 'description' => 'Discount type (e.g. percent, fixed_cart, fixed_product)']
                ],
                'required' => ['id']
            ]
        ]);

    }
}
