<?php

add_action(
  'woocommerce_admin_order_data_after_billing_address',
  'paywong_display_order_meta_data',
  10,
  1
);

function paywong_display_order_meta_data($order)
{
  $tx_hash = $order->get_meta('tx_hash');
  $explorer_url = $order->get_meta('explorer_url');
  $paywong_payment_id = $order->get_meta('paywong_payment_id');

  if ($paywong_payment_id) {
    echo '<p><strong>Paywong Payment ID:</strong><br/>' .
      $paywong_payment_id .
      '</p>';
  }

  if ($tx_hash and $explorer_url) {
    echo '<p><strong>Transaction Hash:</strong><br/><a style="word-break: break-all;" href=' .
      $explorer_url .
      ' target="_blank">' .
      $tx_hash .
      '</a></p>';
  }
}
