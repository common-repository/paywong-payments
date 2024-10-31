<?php

/**
 * Plugin Name: Paywong Payments
 * Plugin URI: https://github.com/Paywong/plugin-woocommerce
 * Author Name: Paywong
 * Author URI: https://paywong.com
 * Description: Start accepting crypto payments in minutes.
 * Version: 2.0.6
 * License: 0.1.0
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * text-domain: wc-paywong
 */

if (
  !in_array(
    'woocommerce/woocommerce.php',
    apply_filters('active_plugins', get_option('active_plugins'))
  )
) {
  return;
}

add_action('plugins_loaded', 'paywong_checkout_init');

function paywong_checkout_init()
{
  if (class_exists('WC_Payment_Gateway')) {
    require __DIR__ . '/includes/class-paywong-gateway.php';
    require __DIR__ . '/includes/order-meta-data-paywong.php';
  }
}

add_filter('woocommerce_payment_gateways', 'add_paywong_checkout');

function add_paywong_checkout($gateways)
{
  $gateways[] = 'WC_Paywong_Checkout';
  return $gateways;
}

?>
