<?php

require 'graphql-client.php';

class WC_Paywong_Checkout extends WC_Payment_Gateway
{
  public function __construct()
  {
    $this->id = 'paywong';
    $this->icon = apply_filters(
      'woocommerce_paywong_icon',
      plugins_url('/assets/icon.png', __FILE__)
    );
    $this->has_fields = false;
    $this->method_title = __('Paywong Checkout', 'wc-paywong');
    $this->method_description = __(
      'Accept secure crypto payments in minutes',
      'wc-paywong'
    );
    $this->title = $this->get_option('title');
    $this->description = 'Powered by Paywong';

    $this->init_form_fields();
    $this->init_settings();

    add_action('woocommerce_update_options_payment_gateways_' . $this->id, [
      $this,
      'process_admin_options',
    ]);

    add_action('woocommerce_api_' . $this->id, [$this, 'webhook']);
  }

  public function init_form_fields()
  {
    $this->form_fields = apply_filters('paywong_form_fields', [
      'enabled' => [
        'title' => __('Enable/Disable', 'wc-paywong'),
        'type' => 'checkbox',
        'label' => __('Enable or Disable Paywong Checkout', 'wc-paywong'),
        'default' => 'no',
      ],
      'title' => [
        'title' => __('Add Custom Title', 'wc-paywong'),
        'type' => 'text',
        'description' => __(
          'Your customers will see this at checkout',
          'wc-paywong'
        ),
        'desc_tip' => true,
        'default' => __('Pay with Crypto', 'wc-paywong'),
      ],
      'environment' => [
        'title' => __('Environment', 'wc-paywong'),
        'type' => 'select',
        'description' => __(
          'Test your payments in Sandbox environment, update your app token accordingly.',
          'wc-paywong'
        ),
        'desc_tip' => true,
        'options' => [
          'production' => 'Production',
          'sandbox' => 'Sandbox',
          'staging' => 'Staging',
        ],
        'default' => 'production',
      ],
      'app_token_production' => [
        'title' => __('App Token (Production)', 'wc-paywong'),
        'type' => 'text',
        'description' => __(
          'Get your app token from your Paywong dashboard',
          'wc-paywong'
        ),
        'desc_tip' => true,
      ],
      'app_token_sandbox' => [
        'title' => __('App Token (Sandbox)', 'wc-paywong'),
        'type' => 'text',
        'description' => __(
          'Get your app token from your Paywong dashboard',
          'wc-paywong'
        ),
        'desc_tip' => true,
      ],
      'app_token_staging' => [
        'title' => __('App Token (Staging)', 'wc-paywong'),
        'type' => 'text',
        'description' => __(
          'Get your app token from your Paywong dashboard',
          'wc-paywong'
        ),
        'desc_tip' => true,
      ],
    ]);
  }

  public function process_payment($order_id)
  {
    $order = new WC_Order($order_id);

    $environment = $this->get_option('environment');
    $app_token = '';
    $endpoint = '';
    $checkout_url = '';

    if ($environment == 'production') {
      $app_token = $this->get_option('app_token_production');
      $endpoint = 'https://api.paywong.com/v1/graphql';
      $checkout_url = 'https://checkout.paywong.com';
    }
    if ($environment == 'staging') {
      $app_token = $this->get_option('app_token_staging');
      $endpoint = 'https://api.staging.paywong.com/v1/graphql';
      $checkout_url = 'https://staging.checkout.paywong.com';
    }
    if ($environment == 'sandbox') {
      $app_token = $this->get_option('app_token_sandbox');
      $endpoint = 'https://api.sandbox.paywong.com/v1/graphql';
      $checkout_url = 'https://sandbox.checkout.paywong.com';
    }

    $itemsArray = $order->get_items();
    $items = [];

    foreach ($itemsArray as $v) {
      $product = $v->get_product();
      $imageValue = $product->get_image();
      $imageSplitOne = explode(' ', $imageValue)[3];
      $imageSplitTwo = explode('"', $imageSplitOne)[1];

      $items[] = [
        'quantity' => $v->get_quantity(),
        'name' => $v->get_name(),
        'price' => (int) $product->get_price(),
        'imageUrl' => $imageSplitTwo,
      ];
    }

    $paywong_request = paywong_create_payment_request($endpoint, $app_token, [
      'currencyId' => strtolower($order->get_currency()),
      'subtotal' => $order->get_subtotal(),
      'discount' => (int) $order->get_discount_total(),
      'shipping' => (int) $order->get_total_shipping(),
      'tax' => (int) $order->get_total_tax(),
      'returnUrl' => $this->get_return_url($order),
      'cancelUrl' => $order->get_cancel_order_url(),
      'webhookUrl' => site_url() . '/?wc-api=paywong',
      'orderId' => strval($order_id),
      'items' => $items,
    ]);

    if (!array_key_exists('data', $paywong_request)) {
      wc_add_notice(
        $paywong_request['errors'][0]['message'] .
          ". Please contact the website's administrator.",
        'error'
      );
      error_log($paywong_request['errors'][0]['message']);
      return [
        'result' => 'failure',
      ];
    }

    $paywong_payment_id =
      $paywong_request['data']['createPayment']['paymentId'];

    $order->update_meta_data('paywong_payment_id', $paywong_payment_id);
    $order->save();

    return [
      'result' => 'success',
      'redirect' => $paywong_request['data']['createPayment']['paymentUrl'],
    ];
  }

  public function webhook()
  {
    header('HTTP/1.1 200 OK');

    $data = json_decode(file_get_contents('php://input'), true);

    $payment = $data['payment'];
    $explorerUrl = $data['explorerUrl'];
    $order_id = $payment['orderId'];
    $payment_id = $payment['id'];
    $status = $payment['status'];
    $txHash = $payment['txHash'];

    if (!$order_id or !$payment_id or !$status) {
      return;
    }

    $order = wc_get_order($order_id);

    if ($status == 'LOCKED') {
      WC()->cart->empty_cart();
    }

    if ($status == 'MINING') {
      $order->update_meta_data('tx_hash', $txHash);
      $order->update_meta_data('explorer_url', $explorerUrl);
      $order->save();
    }

    if ($status == 'PAID') {
      $order->payment_complete();
    }

    if ($status === 'FAILED') {
      $order->update_status(
        'failed',
        __('Paywong has rejected the payment', 'wc-paywong')
      );
    }

    die();

    return [
      'result' => 'success',
    ];
  }
}
