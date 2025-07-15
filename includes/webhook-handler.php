<?php
use Mollie\Api\MollieApiClient;

add_action('init', function () {
  if (strpos($_SERVER['REQUEST_URI'], '/mollie-donation-webhook') !== false) {
    $mollie = new MollieApiClient();
    $mollie->setApiKey(\MollieDonaties\Mollie_Settings::get_api_key());

    $payment_id = $_POST['id'] ?? null;

    if (!$payment_id) {
      http_response_code(400);
      exit;
    }

    try {
      $payment = $mollie->payments->get($payment_id);
      global $wpdb;
      
      // Update payment status
      $wpdb->update(
        $wpdb->prefix . 'mollie_donations',
        ['status' => $payment->status],
        ['payment_id' => $payment_id]
      );
      
      // Handle recurring payments
      if ($payment->status === 'paid' && isset($payment->metadata->recurring_interval)) {
        $donation = $wpdb->get_row($wpdb->prepare(
          "SELECT * FROM {$wpdb->prefix}mollie_donations WHERE payment_id = %s",
          $payment_id
        ));
        
        if ($donation && $donation->is_recurring && !$donation->subscription_id) {
          // Create subscription after first payment is successful
          $subscription = $mollie->subscriptions->createFor($donation->customer_id, [
            "amount" => [
              "currency" => "EUR",
              "value" => number_format($donation->amount, 2, '.', ''),
            ],
            "interval" => $donation->recurring_interval,
            "description" => "Terugkerende donatie van " . $donation->name,
            "webhookUrl" => home_url('/mollie-donation-webhook/'),
          ]);
          
          // Update donation record with subscription ID
          $wpdb->update(
            $wpdb->prefix . 'mollie_donations',
            ['subscription_id' => $subscription->id],
            ['id' => $donation->id]
          );
        }
      }
      
      http_response_code(200);
    } catch (\Exception $e) {
      error_log('Mollie webhook error: ' . $e->getMessage());
      http_response_code(500);
    }

    exit;
  }
});
