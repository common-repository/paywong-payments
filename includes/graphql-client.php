<?php

function paywong_create_payment_request(
  string $endpoint,
  ?string $app_token = null,
  array $variables = []
): array {
  $query = <<<'GRAPHQL'
   mutation createPayment(
          $currencyId: String!
          $subtotal: numeric!
          $discount: numeric
          $tax: numeric
          $shipping: numeric
          $cancelUrl: String
          $orderId: String
          $returnUrl: String
          $webhookUrl: String
          $items: [PaymentItemInput!]
        ) {
        createPayment(
          args: {
            amount: {
              currencyId: $currencyId
              subtotal: $subtotal
              discount: $discount
              tax: $tax
              shipping: $shipping
            }
            paymentOptions: {
              cancelUrl: $cancelUrl
              returnUrl: $returnUrl
              webhookUrl: $webhookUrl
              orderId: $orderId
            }
            items: $items
          }
        ) {
          paymentUrl
          paymentId
        }
      }
GRAPHQL;

  $headers = ['Content-Type: application/json'];
  if (null !== $app_token) {
    $headers[] = "Authorization: Bearer $app_token";
  }

  if (
    false ===
    ($data = @file_get_contents(
      $endpoint,
      false,
      stream_context_create([
        'http' => [
          'method' => 'POST',
          'header' => $headers,
          'content' => json_encode([
            'query' => $query,
            'variables' => $variables,
          ]),
        ],
      ])
    ))
  ) {
    $error = error_get_last();
    throw new \ErrorException($error['message'], $error['type']);
  }

  return json_decode($data, true);
}
