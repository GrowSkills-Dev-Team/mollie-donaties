<?php
use Mollie\Api\MollieApiClient;

add_action('init', function () {
  if (strpos($_SERVER['REQUEST_URI'], '/mollie-donation-webhook') !== false) {
    // Log webhook call voor debugging
    error_log('Mollie webhook called with data: ' . print_r($_POST, true));
    
    $mollie = new MollieApiClient();
    $mollie->setApiKey(\MollieDonaties\Mollie_Settings::get_api_key());

    $payment_id = $_POST['id'] ?? null;

    if (!$payment_id) {
      error_log('Mollie webhook: No payment ID provided');
      http_response_code(400);
      exit;
    }

    try {
      $payment = $mollie->payments->get($payment_id);
      global $wpdb;
      
      error_log('Mollie webhook: Processing payment ' . $payment_id . ' with status ' . $payment->status);
      
      // Update payment status
      $update_result = $wpdb->update(
        $wpdb->prefix . 'mollie_donations',
        ['status' => $payment->status],
        ['payment_id' => $payment_id]
      );
      
      if ($update_result === false) {
        error_log('Mollie webhook: Failed to update payment status in database');
      }
      
      // Handle recurring payments
      if ($payment->status === 'paid' && isset($payment->metadata->recurring_interval)) {
        error_log('Mollie webhook: Processing recurring payment');
        
        $donation = $wpdb->get_row($wpdb->prepare(
          "SELECT * FROM {$wpdb->prefix}mollie_donations WHERE payment_id = %s",
          $payment_id
        ));
        
        if (!$donation) {
          error_log('Mollie webhook: Donation record not found for payment ' . $payment_id);
        } elseif (!$donation->is_recurring) {
          error_log('Mollie webhook: Donation is not marked as recurring');
        } elseif ($donation->subscription_id) {
          error_log('Mollie webhook: Subscription already exists: ' . $donation->subscription_id);
        } elseif (!$donation->customer_id) {
          error_log('Mollie webhook: No customer ID found for recurring donation');
        } else {
          // Create subscription after first payment is successful
          error_log('Mollie webhook: Creating subscription for customer ' . $donation->customer_id);
          
          $subscription = $mollie->subscriptions->createFor($donation->customer_id, [
            "amount" => [
              "currency" => "EUR",
              "value" => number_format($donation->amount, 2, '.', ''),
            ],
            "interval" => $donation->recurring_interval,
            "description" => "Terugkerende donatie van " . $donation->name,
            "webhookUrl" => home_url('/mollie-donation-webhook/'),
          ]);
          
          error_log('Mollie webhook: Subscription created: ' . $subscription->id);
          
          // Update donation record with subscription ID
          $wpdb->update(
            $wpdb->prefix . 'mollie_donations',
            ['subscription_id' => $subscription->id],
            ['id' => $donation->id]
          );
        }
      }
      
      error_log('Mollie webhook: Successfully processed payment ' . $payment_id);
      http_response_code(200);
      echo 'OK';
    } catch (\Exception $e) {
      error_log('Mollie webhook error: ' . $e->getMessage());
      error_log('Mollie webhook error trace: ' . $e->getTraceAsString());
      http_response_code(500);
      echo 'Error: ' . $e->getMessage();
    }

    exit;
  }
});
