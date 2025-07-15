<?php
namespace MollieDonaties;

use Mollie\Api\MollieApiClient;

class Mollie_Donaties {
  public static function install() {
    global $wpdb;
    $table = $wpdb->prefix . 'mollie_donations';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
            switch ($donation->recurring_interval) {
          case '1 day': $recurring_text = 'dagelijks'; break;
          case '1 month': $recurring_text = 'maandelijks'; break;id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(255),
      email VARCHAR(255),
      amount DECIMAL(10,2),
      payment_id VARCHAR(255),
      status VARCHAR(100),
      is_recurring TINYINT(1) DEFAULT 0,
      recurring_interval VARCHAR(20) NULL,
      mandate_id VARCHAR(255) NULL,
      customer_id VARCHAR(255) NULL,
      subscription_id VARCHAR(255) NULL,
      project_id INT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
  }
  
  public static function upgrade_database() {
    global $wpdb;
    $table = $wpdb->prefix . 'mollie_donations';
    
    // Check if recurring columns exist, if not add them
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'is_recurring'");
    if (empty($columns)) {
      $wpdb->query("ALTER TABLE $table ADD COLUMN is_recurring TINYINT(1) DEFAULT 0");
      $wpdb->query("ALTER TABLE $table ADD COLUMN recurring_interval VARCHAR(20) NULL");
      $wpdb->query("ALTER TABLE $table ADD COLUMN mandate_id VARCHAR(255) NULL");
      $wpdb->query("ALTER TABLE $table ADD COLUMN customer_id VARCHAR(255) NULL");
      $wpdb->query("ALTER TABLE $table ADD COLUMN subscription_id VARCHAR(255) NULL");
    }
    
    // Check if project_id column exists, if not add it
    $project_columns = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'project_id'");
    if (empty($project_columns)) {
      $wpdb->query("ALTER TABLE $table ADD COLUMN project_id INT NULL");
    }
  }

  public static function render_form() {
    ob_start();
    include dirname(__DIR__) . '/templates/form.php';
    return ob_get_clean();
  }

  public static function handle_form_submit() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mollie_donation'])) {
      $name = sanitize_text_field($_POST['name']);
      $email = sanitize_email($_POST['email']);
      
      // Project ID (optioneel) - zoek naar project_id velden
      $project_id = null;
      $enabled_post_types = get_option('mollie_enabled_post_types', array());
      
      foreach ($enabled_post_types as $post_type) {
        $field_name = 'project_id_' . $post_type;
        if (!empty($_POST[$field_name])) {
          $project_id = intval($_POST[$field_name]);
          break; // Neem de eerste gevonden project ID
        }
      }
      
      // Bepaal het bedrag op basis van de keuze
      $amount_type = sanitize_text_field($_POST['amount_type']);
      if ($amount_type === 'custom') {
        $amount = floatval($_POST['custom_amount']);
      } else {
        $amount = floatval($amount_type);
      }
      
      // Check voor recurring donatie
      $recurring_interval = sanitize_text_field($_POST['recurring_interval'] ?? 'one_time');
      $is_recurring = $recurring_interval !== 'one_time';
      
      // Validatie: minimum bedrag van €1
      if ($amount < 1) {
        wp_die("Het minimum donatiebedrag is €1,00");
      }
      
      // Validatie: recurring interval
      if ($is_recurring && !in_array($recurring_interval, ['1 day', '1 month', '3 months', '1 year'])) {
        wp_die("Ongeldige recurring interval geselecteerd: " . $recurring_interval);
      }

      try {
        $mollie = new MollieApiClient();
        $mollie->setApiKey(\MollieDonaties\Mollie_Settings::get_api_key());

        // Detecteer of we lokaal draaien
        $site_url = home_url();
        $is_local = (strpos($site_url, '.local') !== false || 
                    strpos($site_url, 'localhost') !== false || 
                    strpos($site_url, '127.0.0.1') !== false);

        if ($is_recurring) {
          // Voor recurring payments moeten we eerst een customer aanmaken
          $customer = $mollie->customers->create([
            "name" => $name,
            "email" => $email,
          ]);

          // Maak een eerste payment aan voor mandate creatie
          $payment_data = [
            "amount" => [
              "currency" => "EUR",
              "value" => number_format($amount, 2, '.', ''),
            ],
            "description" => "Eerste donatie (terugkerende donatie)",
            "redirectUrl" => home_url('/bedankt/'),
            "customerId" => $customer->id,
            "sequenceType" => "first",
            "metadata" => [
              "name" => $name,
              "email" => $email,
              "recurring_interval" => $recurring_interval,
            ],
          ];
          
          // Voor recurring payments: laat Mollie automatisch de beste method kiezen
          // Dit voorkomt errors als bepaalde methods niet zijn geactiveerd
        } else {
          // Eenmalige donatie
          $payment_data = [
            "amount" => [
              "currency" => "EUR",
              "value" => number_format($amount, 2, '.', ''),
            ],
            "description" => "Donatie van $name",
            "redirectUrl" => home_url('/bedankt/'),
            "metadata" => [
              "name" => $name,
              "email" => $email,
            ],
            "method" => "ideal", // Alleen voor eenmalige donaties
          ];
        }

        // Voeg webhook alleen toe als we niet lokaal draaien
        if (!$is_local) {
          $payment_data["webhookUrl"] = home_url('/mollie-donation-webhook/');
        }

        $payment = $mollie->payments->create($payment_data);

        // Sla payment ID op in session voor gebruik op bedankt pagina
        if (!session_id()) {
          session_start();
        }
        $_SESSION['mollie_payment_id'] = $payment->id;

        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'mollie_donations', [
          'name' => $name,
          'email' => $email,
          'amount' => $amount,
          'payment_id' => $payment->id,
          'status' => 'open',
          'is_recurring' => $is_recurring ? 1 : 0,
          'recurring_interval' => $is_recurring ? $recurring_interval : null,
          'customer_id' => $is_recurring ? $customer->id : null,
          'project_id' => $project_id,
        ]);

        wp_redirect($payment->getCheckoutUrl());
        exit;
      } catch (\Exception $e) {
        wp_die("Fout bij betaling aanmaken: " . $e->getMessage());
      }
    }
  }

  public static function check_payment_status($payment_id) {
    if (!$payment_id) {
      return null;
    }

    try {
      $mollie = new MollieApiClient();
      $mollie->setApiKey(\MollieDonaties\Mollie_Settings::get_api_key());
      
      $payment = $mollie->payments->get($payment_id);
      
      // Update de status in de database
      global $wpdb;
      $wpdb->update(
        $wpdb->prefix . 'mollie_donations',
        ['status' => $payment->status],
        ['payment_id' => $payment_id]
      );
      
      return $payment;
    } catch (\Exception $e) {
      return null;
    }
  }

  public static function get_payment_message($payment) {
    if (!$payment) {
      $message = get_option('mollie_message_default', 'Bedankt voor je interesse in doneren!');
      return [
        'type' => 'info',
        'message' => $message
      ];
    }

    // Haal donatie gegevens op uit de database
    global $wpdb;
    $donation = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM {$wpdb->prefix}mollie_donations WHERE payment_id = %s",
      $payment->id
    ));

    switch ($payment->status) {
      case 'paid':
        $message = get_option('mollie_message_paid', 'Bedankt voor je donatie! Je betaling is succesvol verwerkt.');
        $type = 'success';
        break;
      
      case 'canceled':
        $message = get_option('mollie_message_canceled', 'Je betaling is geannuleerd. Je kunt het opnieuw proberen als je wilt.');
        $type = 'warning';
        break;
      
      case 'failed':
        $message = get_option('mollie_message_failed', 'Er is iets misgegaan met je betaling. Probeer het opnieuw of neem contact op.');
        $type = 'error';
        break;
      
      case 'expired':
        $message = get_option('mollie_message_expired', 'De betaling is verlopen. Je kunt een nieuwe donatie doen als je wilt.');
        $type = 'warning';
        break;
      
      case 'pending':
      case 'open':
        $message = get_option('mollie_message_pending', 'Je betaling wordt nog verwerkt. Dit kan een paar minuten duren.');
        $type = 'info';
        break;
      
      default:
        $message = get_option('mollie_message_default', 'Je betaling heeft status: ' . $payment->status);
        $type = 'info';
        break;
    }

    // Vervang placeholders
    if ($donation) {
      $message = str_replace('[bedrag]', '€' . number_format($donation->amount, 2, ',', '.'), $message);
      $message = str_replace('[naam]', $donation->name, $message);
      $message = str_replace('[email]', $donation->email, $message);
      
      // Specifieke keuze placeholder
      $specifieke_keuze = '';
      if (!empty($donation->project_id)) {
        $project = get_post($donation->project_id);
        if ($project) {
          $post_type_object = get_post_type_object($project->post_type);
          $specifieke_keuze = 'voor ' . strtolower($post_type_object->labels->singular_name) . ' "' . $project->post_title . '"';
        }
      }
      $message = str_replace('[specifieke_keuze]', $specifieke_keuze, $message);
      
      // Recurring payment placeholders
      if ($donation->is_recurring) {
        $recurring_text = '';
        switch ($donation->recurring_interval) {
          case '1 month': $recurring_text = 'maandelijks'; break;
          case '3 months': $recurring_text = 'per kwartaal'; break;
          case '1 year': $recurring_text = 'jaarlijks'; break;
        }
        $message = str_replace('[recurring_interval]', $recurring_text, $message);
        $message = str_replace('[donation_type]', 'terugkerende donatie', $message);
      } else {
        $message = str_replace('[recurring_interval]', '', $message);
        $message = str_replace('[donation_type]', 'eenmalige donatie', $message);
      }
    } else {
      // Als er geen donatie data is, vervang placeholders met lege strings
      $message = str_replace('[specifieke_keuze]', '', $message);
    }
    
    // Vervang payment-gerelateerde placeholders
    $message = str_replace('[status]', $payment->status, $message);
    $message = str_replace('[payment_id]', $payment->id, $message);

    return [
      'type' => $type,
      'message' => $message
    ];
  }

  public static function render_payment_status() {
    // Probeer payment ID te krijgen uit URL of session
    $payment_id = $_GET['payment_id'] ?? null;
    
    if (!$payment_id) {
      if (!session_id()) {
        session_start();
      }
      $payment_id = $_SESSION['mollie_payment_id'] ?? null;
      
      // Verwijder payment ID uit session nadat we het hebben gebruikt
      if ($payment_id) {
        unset($_SESSION['mollie_payment_id']);
      }
    }
    
    // Als we nog steeds geen payment ID hebben, probeer de laatste uit de database
    if (!$payment_id) {
      global $wpdb;
      $latest_payment = $wpdb->get_row(
        "SELECT payment_id FROM {$wpdb->prefix}mollie_donaties ORDER BY id DESC LIMIT 1"
      );
      if ($latest_payment) {
        $payment_id = $latest_payment->payment_id;
      }
    }
    
    if (!$payment_id) {
      return '<div class="mollie-payment-status"><p>Bedankt voor je interesse in doneren!</p></div>';
    }
    
    $payment = self::check_payment_status($payment_id);
    $message_data = self::get_payment_message($payment);
    
    $css_class = 'mollie-payment-status mollie-' . $message_data['type'];
    
    // Gebruik wp_kses_post om veilige HTML toe te staan
    $safe_message = wp_kses_post($message_data['message']);
    
    return '<div class="' . $css_class . '"><p>' . $safe_message . '</p></div>';
  }
  
  public static function cancel_subscription($subscription_id, $customer_id) {
    try {
      $mollie = new MollieApiClient();
      $mollie->setApiKey(\MollieDonaties\Mollie_Settings::get_api_key());
      
      $subscription = $mollie->subscriptions->getFor($customer_id, $subscription_id);
      $subscription->cancel();
      
      // Update database record
      global $wpdb;
      $wpdb->update(
        $wpdb->prefix . 'mollie_donations',
        ['status' => 'canceled'],
        ['subscription_id' => $subscription_id]
      );
      
      return true;
    } catch (\Exception $e) {
      error_log('Error canceling subscription: ' . $e->getMessage());
      return false;
    }
  }
  
  public static function get_subscription_status($subscription_id, $customer_id) {
    try {
      $mollie = new MollieApiClient();
      $mollie->setApiKey(\MollieDonaties\Mollie_Settings::get_api_key());
      
      return $mollie->subscriptions->getFor($customer_id, $subscription_id);
    } catch (\Exception $e) {
      return null;
    }
  }
}
