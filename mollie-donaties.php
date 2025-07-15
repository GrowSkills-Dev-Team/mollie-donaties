<?php
/**
 * Plugin Name: Mollie Donaties
 * Description: Eenvoudige donatie plugin met Mollie iDEAL
 * Version: 1.0
 * Author: Henri Kok
 */

defined('ABSPATH') || exit;

// Include de plugin update checker
require plugin_dir_path(__FILE__) . 'plugin-update-checker-master/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/GrowSkills-Dev-Team/mollie-donaties',
    __FILE__,
    'mollie-donaties' // dit moet overeenkomen met de plugin directory/slug
);

$updateChecker->getVcsApi()->enableReleaseAssets();

// Start session voor payment tracking
add_action('init', function() {
  if (!session_id()) {
    session_start();
  }
});

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/class-mollie-donaties.php';
require_once __DIR__ . '/includes/webhook-handler.php';
require_once __DIR__ . '/includes/settings-page.php';

register_activation_hook(__FILE__, ['MollieDonaties\Mollie_Donaties', 'install']);
add_action('init', ['MollieDonaties\Mollie_Donaties', 'upgrade_database']); // Database upgrades bij elke init
add_shortcode('mollie_donation_form', ['MollieDonaties\Mollie_Donaties', 'render_form']);
add_shortcode('mollie_payment_status', ['MollieDonaties\Mollie_Donaties', 'render_payment_status']);
add_action('init', ['MollieDonaties\Mollie_Donaties', 'handle_form_submit']);


\MollieDonaties\Mollie_Settings::init();

// Database upgrade functie
add_action('plugins_loaded', function() {
  $current_version = get_option('mollie_donaties_db_version', '1.0');
  $new_version = '1.1';
  
  if (version_compare($current_version, $new_version, '<')) {
    \MollieDonaties\Mollie_Donaties::upgrade_database();
    update_option('mollie_donaties_db_version', $new_version);
  }
});
