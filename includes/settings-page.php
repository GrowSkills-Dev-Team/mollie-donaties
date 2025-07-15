<?php
namespace MollieDonaties;

class Mollie_Settings {
  public static function init() {
    add_action('admin_menu', [__CLASS__, 'add_settings_page']);
    add_action('admin_init', [__CLASS__, 'register_settings']);
    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles']);
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
    add_action('wp_ajax_test_mollie_connection', [__CLASS__, 'test_api_connection']);
  }

  public static function enqueue_admin_scripts($hook) {
    // Alleen laden op onze plugin pagina's
    if (strpos($hook, 'mollie-donaties') !== false) {
      wp_enqueue_script('jquery');
    }
  }

  public static function add_settings_page() {
    // Hoofdmenu
    add_menu_page(
      'Mollie Donaties',
      'Mollie Donaties',
      'manage_options',
      'mollie-donaties',
      [__CLASS__, 'render_main_page'],
      'dashicons-heart',  // Een passend icoon voor donaties
      30  // Positie in het menu
    );
    
    // Eerste submenu item hernoemen naar Dashboard om verwarring te voorkomen
    add_submenu_page(
      'mollie-donaties',
      'Dashboard',
      'Dashboard',
      'manage_options',
      'mollie-donaties',
      [__CLASS__, 'render_main_page']
    );
    
    // Submenu voor donaties overzicht
    add_submenu_page(
      'mollie-donaties',
      'Donatie Overzicht',
      'Overzicht',
      'manage_options',
      'mollie-donaties-overview',
      [__CLASS__, 'render_donations_page']
    );
    
    // Submenu voor instellingen
    add_submenu_page(
      'mollie-donaties',
      'Donatie Instellingen',
      'Instellingen',
      'manage_options',
      'mollie-donaties-settings',
      [__CLASS__, 'render_settings_page']
    );
    
    // Submenu voor meldingen
    add_submenu_page(
      'mollie-donaties',
      'Donatie Meldingen',
      'Meldingen',
      'manage_options',
      'mollie-donaties-messages',
      [__CLASS__, 'render_messages_page']
    );
  }

  public static function register_settings() {
    register_setting('mollie_donaties_settings', 'mollie_test_api_key');
    register_setting('mollie_donaties_settings', 'mollie_live_api_key');
    register_setting('mollie_donaties_settings', 'mollie_api_mode', [
      'sanitize_callback' => [__CLASS__, 'validate_api_settings']
    ]);
    register_setting('mollie_donaties_settings', 'mollie_enabled_post_types', [
      'sanitize_callback' => [__CLASS__, 'sanitize_post_types']
    ]);
    
    // Meldingen settings
    register_setting('mollie_donaties_messages', 'mollie_message_paid');
    register_setting('mollie_donaties_messages', 'mollie_message_failed');
    register_setting('mollie_donaties_messages', 'mollie_message_canceled');
    register_setting('mollie_donaties_messages', 'mollie_message_expired');
    register_setting('mollie_donaties_messages', 'mollie_message_pending');
    register_setting('mollie_donaties_messages', 'mollie_message_default');
  }

  public static function validate_api_settings($input) {
    $test_key = sanitize_text_field($_POST['mollie_test_api_key'] ?? '');
    $live_key = sanitize_text_field($_POST['mollie_live_api_key'] ?? '');
    $mode = sanitize_text_field($input);
    
    // Valideer dat de juiste key is ingevuld voor de gekozen modus
    if ($mode === 'test' && empty($test_key)) {
      add_settings_error(
        'mollie_api_mode',
        'missing_test_key',
        'Je hebt test modus gekozen, maar geen test API key ingevuld.',
        'error'
      );
    }
    
    if ($mode === 'live' && empty($live_key)) {
      add_settings_error(
        'mollie_api_mode',
        'missing_live_key',
        'Je hebt live modus gekozen, maar geen live API key ingevuld.',
        'error'
      );
    }
    
    // Valideer API key format
    if (!empty($test_key) && !str_starts_with($test_key, 'test_')) {
      add_settings_error(
        'mollie_test_api_key',
        'invalid_test_key_format',
        'Test API key moet beginnen met "test_".',
        'error'
      );
    }
    
    if (!empty($live_key) && !str_starts_with($live_key, 'live_')) {
      add_settings_error(
        'mollie_live_api_key',
        'invalid_live_key_format',
        'Live API key moet beginnen met "live_".',
        'error'
      );
    }
    
    return $mode;
  }

  public static function sanitize_post_types($input) {
    if (!is_array($input)) {
      return array();
    }
    
    // Valideer dat alle geselecteerde post types bestaan
    $valid_post_types = get_post_types(['public' => true, '_builtin' => false], 'names');
    $sanitized = array();
    
    foreach ($input as $post_type) {
      if (in_array($post_type, $valid_post_types)) {
        $sanitized[] = sanitize_text_field($post_type);
      }
    }
    
    return $sanitized;
  }

  public static function render_settings_page() {
    $current_mode = get_option('mollie_api_mode', 'test');
    $test_key = get_option('mollie_test_api_key');
    $live_key = get_option('mollie_live_api_key');
    
    // Check of de configuratie compleet is
    $is_configured = false;
    if ($current_mode === 'test' && !empty($test_key)) {
      $is_configured = true;
    } elseif ($current_mode === 'live' && !empty($live_key)) {
      $is_configured = true;
    }
    
    ?>
    <div class="wrap">
      <h1>Donatie Instellingen</h1>
      <p>Hier kun je je Mollie API instellingen configureren.</p>
      
      <?php if ($is_configured): ?>
        <div class="notice notice-success">
          <p><strong>✅ Configuratie Compleet!</strong> Je plugin is correct ingesteld voor <strong><?php echo ucfirst($current_mode); ?></strong> modus.</p>
        </div>
      <?php else: ?>
        <div class="notice notice-warning">
          <p><strong>⚠️ Configuratie Incompleet!</strong> Vul de juiste API key in voor de gekozen modus om donaties te kunnen ontvangen.</p>
        </div>
      <?php endif; ?>
      
      <?php settings_errors(); ?>
      
      <form method="post" action="options.php">
        <?php
        settings_fields('mollie_donaties_settings');
        do_settings_sections('mollie_donaties_settings');
        ?>
        <table class="form-table">
          <tr valign="top">
            <th scope="row">API Modus</th>
            <td>
              <select name="mollie_api_mode">
                <option value="test" <?php selected(get_option('mollie_api_mode', 'test'), 'test'); ?>>Test</option>
                <option value="live" <?php selected(get_option('mollie_api_mode', 'test'), 'live'); ?>>Live</option>
              </select>
              <p class="description">Kies tussen test modus (voor ontwikkeling) of live modus (voor echte donaties).</p>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">Mollie Test API Key</th>
            <td>
              <input type="text" id="mollie_test_api_key" name="mollie_test_api_key" value="<?php echo esc_attr(get_option('mollie_test_api_key')); ?>" size="50" placeholder="test_..." />
              <button type="button" id="test_test_connection" class="button button-secondary" style="margin-left: 0.625rem;">🔗 Test Verbinding</button>
              <div id="test_connection_result" style="margin-top: 0.625rem;"></div>
              <p class="description">Je test API key van Mollie (begint met test_).</p>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">Mollie Live API Key</th>
            <td>
              <input type="text" id="mollie_live_api_key" name="mollie_live_api_key" value="<?php echo esc_attr(get_option('mollie_live_api_key')); ?>" size="50" placeholder="live_..." />
              <button type="button" id="test_live_connection" class="button button-secondary" style="margin-left: 0.625rem;">🔗 Test Verbinding</button>
              <div id="live_connection_result" style="margin-top: 0.625rem;"></div>
              <p class="description">Je live API key van Mollie (begint met live_).</p>
            </td>
          </tr>
        </table>
        
        <h2>Project Koppeling</h2>
        <p>Hier kun je instellen welke custom post types beschikbaar zijn als projecten voor donaties.</p>
        
        <?php
        // Haal alle custom post types op
        $custom_post_types = get_post_types(['public' => true, '_builtin' => false], 'objects');
        $enabled_post_types = get_option('mollie_enabled_post_types', array());
        ?>
        
        <table class="form-table">
          <?php if (empty($custom_post_types)): ?>
            <tr valign="top">
              <td colspan="2">
                <div class="notice notice-info inline">
                  <p>Er zijn geen custom post types gevonden. Maak eerst custom post types aan om deze functie te gebruiken.</p>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <tr valign="top">
              <th scope="row">Beschikbare Post Types</th>
              <td>
                <?php foreach ($custom_post_types as $post_type => $post_type_object): ?>
                  <?php
                  // Tel aantal posts in dit post type
                  $post_count = wp_count_posts($post_type);
                  $total_posts = $post_count->publish ?? 0;
                  ?>
                  <label style="display: block; margin-bottom: 0.5rem;">
                    <input type="checkbox" 
                           name="mollie_enabled_post_types[]" 
                           value="<?php echo esc_attr($post_type); ?>"
                           <?php checked(in_array($post_type, $enabled_post_types)); ?> />
                    <strong><?php echo esc_html($post_type_object->labels->name); ?></strong>
                    <span style="color: #666; font-size: 0.875rem;">(<?php echo esc_html($post_type); ?> - <?php echo $total_posts; ?> posts)</span>
                    <?php if ($total_posts === 0): ?>
                      <span style="color: #d63638; font-size: 0.875rem; margin-left: 0.5rem;">⚠️ Geen posts gevonden</span>
                    <?php endif; ?>
                  </label>
                <?php endforeach; ?>
                <p class="description">Selecteer welke post types beschikbaar moeten zijn als projecten in het donatie formulier.</p>
                
                <?php
                // Controleer of er posts zijn in de geselecteerde post types
                $has_posts_in_enabled_types = false;
                foreach ($enabled_post_types as $enabled_type) {
                  $post_count = wp_count_posts($enabled_type);
                  if (($post_count->publish ?? 0) > 0) {
                    $has_posts_in_enabled_types = true;
                    break;
                  }
                }
                
                if (!empty($enabled_post_types) && !$has_posts_in_enabled_types): ?>
                  <div class="notice notice-warning inline" style="margin-top: 0.75rem;">
                    <p><strong>⚠️ Waarschuwing:</strong> Je hebt post types geselecteerd, maar er zijn geen gepubliceerde posts gevonden in deze post types. De project selectie zal niet zichtbaar zijn in het donatie formulier totdat er posts zijn aangemaakt.</p>
                  </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endif; ?>
        </table>
        
        <?php submit_button(); ?>
      </form>
      </form>
      
      <script>
      jQuery(document).ready(function($) {
        function testConnection(mode, apiKey, resultDiv) {
          if (!apiKey) {
            $(resultDiv).html('<div class="notice notice-error inline"><p>❌ Vul eerst een API key in</p></div>');
            return;
          }
          
          $(resultDiv).html('<div class="notice notice-info inline"><p>🔄 Verbinding testen...</p></div>');
          
          $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
              action: 'test_mollie_connection',
              mode: mode,
              api_key: apiKey,
              nonce: '<?php echo wp_create_nonce('mollie_test_connection'); ?>'
            },
            success: function(response) {
              console.log('AJAX Response:', response);
              if (response.success) {
                $(resultDiv).html('<div class="notice notice-success inline"><p>✅ ' + response.data.message + '</p></div>');
              } else {
                $(resultDiv).html('<div class="notice notice-error inline"><p>❌ ' + response.data + '</p></div>');
              }
            },
            error: function(xhr, status, error) {
              console.log('AJAX Error:', xhr.responseText);
              console.log('Status:', status);
              console.log('Error:', error);
              $(resultDiv).html('<div class="notice notice-error inline"><p>❌ AJAX fout: ' + error + ' (Zie browser console voor meer details)</p></div>');
            }
          });
        }
        
        $('#test_test_connection').click(function() {
          var apiKey = $('#mollie_test_api_key').val();
          console.log('Testing test key:', apiKey.substring(0, 10) + '...');
          testConnection('test', apiKey, '#test_connection_result');
        });
        
        $('#test_live_connection').click(function() {
          var apiKey = $('#mollie_live_api_key').val();
          console.log('Testing live key:', apiKey.substring(0, 10) + '...');
          testConnection('live', apiKey, '#live_connection_result');
        });
      });
      </script>
    </div>
    <?php
  }

  public static function render_main_page() {
    ?>
    <div class="wrap">
      <h1>Mollie Donaties</h1>
      <p>Welkom bij de Mollie Donaties plugin! Hier vind je een overzicht van de belangrijkste functionaliteiten.</p>
      
      <div class="postbox" style="margin-top: 1.25rem;">
        <div class="inside">
          <h3>🚀 Aan de slag</h3>
          <p>Om donaties te kunnen ontvangen heb je het volgende nodig:</p>
          <ol>
            <li><strong>API Keys instellen:</strong> Ga naar <a href="<?php echo admin_url('admin.php?page=mollie-donaties-settings'); ?>">Instellingen</a> en vul je Mollie API keys in</li>
            <li><strong>Projecten instellen:</strong> Kies welke post types beschikbaar zijn als project opties</li>
            <li><strong>Berichten aanpassen:</strong> Ga naar <a href="<?php echo admin_url('admin.php?page=mollie-donaties-messages'); ?>">Meldingen</a> om de teksten aan te passen</li>
          </ol>
        </div>
      </div>
      
      <div class="postbox" style="margin-top: 1.25rem;">
        <div class="inside">
          <h3>🔧 Snelle Links</h3>
          <p>
            <a href="<?php echo admin_url('admin.php?page=mollie-donaties-overview'); ?>" class="button">📊 Overzicht</a>
            <a href="<?php echo admin_url('admin.php?page=mollie-donaties-settings'); ?>" class="button">⚙️ Instellingen</a>
            <a href="<?php echo admin_url('admin.php?page=mollie-donaties-messages'); ?>" class="button">💬 Meldingen</a>
          </p>
        </div>
      </div>
      
      <div class="postbox" style="margin-top: 1.25rem;">
        <div class="inside">
          <h3>ℹ️ Plugin Informatie</h3>
          <p><strong>Versie:</strong> 1.0</p>
          <p><strong>Ontwikkelaar:</strong> Henri Kok</p>
          <p><strong>Beschrijving:</strong> Eenvoudige donatie plugin met Mollie iDEAL integratie</p>
        </div>
      </div>
    </div>
    <?php
  }

  public static function get_api_key() {
    $mode = get_option('mollie_api_mode', 'test');
    if ($mode === 'live') {
      return get_option('mollie_live_api_key');
    } else {
      return get_option('mollie_test_api_key');
    }
  }

  public static function enqueue_styles() {
    wp_enqueue_style(
      'mollie-donaties-style',
      plugin_dir_url(dirname(__FILE__)) . 'css/mollie-donaties.css',
      array(),
      '1.0.0'
    );
  }

  public static function render_messages_page() {
    ?>
    <div class="wrap">
      <h1>Donatie Meldingen</h1>
      <p>Hier kun je de berichten aanpassen die getoond worden aan donateurs na hun betaling.</p>
      
      <div class="postbox" style="margin-bottom: 1.25rem;">
        <div class="inside">
          <h3>Beschikbare Placeholders</h3>
          <p>Je kunt de volgende placeholders gebruiken in je berichten:</p>
          <ul>
            <li><code>[bedrag]</code> - Het donatiebedrag (bijv. €10,00)</li>
            <li><code>[naam]</code> - De naam van de donateur</li>
            <li><code>[email]</code> - Het e-mailadres van de donateur</li>
            <li><code>[status]</code> - De betaalstatus (paid, failed, etc.)</li>
            <li><code>[payment_id]</code> - Het unieke betaal-ID</li>
            <li><code>[donation_type]</code> - Het type donatie (eenmalige donatie of terugkerende donatie)</li>
            <li><code>[recurring_interval]</code> - De herhaling bij terugkerende donaties (maandelijks, per kwartaal, jaarlijks)</li>
            <li><code>[specifieke_keuze]</code> - Het geselecteerde project/item (bijv. 'voor project "Nieuw Speelplein"')</li>
          </ul>
          <p><strong>Voorbeeld:</strong><br>
          <code>Je [donation_type] van &lt;em&gt;€[bedrag]&lt;/em&gt; [recurring_interval] [specifieke_keuze] is ontvangen.&lt;br&gt;We sturen je een bevestiging naar [email].</code></p>
          <p><strong>Tip:</strong> Je kunt HTML gebruiken voor opmaak, zoals <code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code> en <code>&lt;br&gt;</code>.</p>
        </div>
      </div>
      
      <form method="post" action="options.php">
        <?php
        settings_fields('mollie_donaties_messages');
        do_settings_sections('mollie_donaties_messages');
        ?>
        
        <table class="form-table">
          <tr valign="top">
            <th scope="row"><label for="mollie_message_paid">Betaling Gelukt</label></th>
            <td>
              <?php
              wp_editor(
                get_option('mollie_message_paid', 'Bedankt voor je donatie! Je betaling is succesvol verwerkt.'),
                'mollie_message_paid',
                [
                  'textarea_name' => 'mollie_message_paid',
                  'media_buttons' => false,
                  'textarea_rows' => 3,
                  'teeny' => true
                ]
              );
              ?>
              <p class="description">Wordt getoond wanneer de betaling succesvol is afgerond.</p>
            </td>
          </tr>
          
          <tr valign="top">
            <th scope="row"><label for="mollie_message_failed">Betaling Mislukt</label></th>
            <td>
              <?php
              wp_editor(
                get_option('mollie_message_failed', 'Er is iets misgegaan met je betaling. Probeer het opnieuw of neem contact op.'),
                'mollie_message_failed',
                [
                  'textarea_name' => 'mollie_message_failed',
                  'media_buttons' => false,
                  'textarea_rows' => 3,
                  'teeny' => true
                ]
              );
              ?>
              <p class="description">Wordt getoond wanneer de betaling is mislukt.</p>
            </td>
          </tr>
          
          <tr valign="top">
            <th scope="row"><label for="mollie_message_canceled">Betaling Geannuleerd</label></th>
            <td>
              <?php
              wp_editor(
                get_option('mollie_message_canceled', 'Je betaling is geannuleerd. Je kunt het opnieuw proberen als je wilt.'),
                'mollie_message_canceled',
                [
                  'textarea_name' => 'mollie_message_canceled',
                  'media_buttons' => false,
                  'textarea_rows' => 3,
                  'teeny' => true
                ]
              );
              ?>
              <p class="description">Wordt getoond wanneer de betaling is geannuleerd door de gebruiker.</p>
            </td>
          </tr>
          
          <tr valign="top">
            <th scope="row"><label for="mollie_message_expired">Betaling Verlopen</label></th>
            <td>
              <?php
              wp_editor(
                get_option('mollie_message_expired', 'De betaling is verlopen. Je kunt een nieuwe donatie doen als je wilt.'),
                'mollie_message_expired',
                [
                  'textarea_name' => 'mollie_message_expired',
                  'media_buttons' => false,
                  'textarea_rows' => 3,
                  'teeny' => true
                ]
              );
              ?>
              <p class="description">Wordt getoond wanneer de betaling is verlopen.</p>
            </td>
          </tr>
          
          <tr valign="top">
            <th scope="row"><label for="mollie_message_pending">Betaling in Behandeling</label></th>
            <td>
              <?php
              wp_editor(
                get_option('mollie_message_pending', 'Je betaling wordt nog verwerkt. Dit kan een paar minuten duren.'),
                'mollie_message_pending',
                [
                  'textarea_name' => 'mollie_message_pending',
                  'media_buttons' => false,
                  'textarea_rows' => 3,
                  'teeny' => true
                ]
              );
              ?>
              <p class="description">Wordt getoond wanneer de betaling nog wordt verwerkt.</p>
            </td>
          </tr>
          
          <tr valign="top">
            <th scope="row"><label for="mollie_message_default">Standaard Melding</label></th>
            <td>
              <?php
              wp_editor(
                get_option('mollie_message_default', 'Bedankt voor je interesse in doneren!'),
                'mollie_message_default',
                [
                  'textarea_name' => 'mollie_message_default',
                  'media_buttons' => false,
                  'textarea_rows' => 3,
                  'teeny' => true
                ]
              );
              ?>
              <p class="description">Wordt getoond wanneer er geen specifieke betaling gevonden kan worden.</p>
            </td>
          </tr>
        </table>
        
        <?php submit_button('Meldingen Opslaan'); ?>
      </form>
    </div>
    <?php
  }

  public static function render_donations_page() {
    global $wpdb;
    
    // Verwerk sorteer parameters
    $allowed_orderby = ['id', 'name', 'email', 'amount', 'status', 'created_at', 'project_title', 'is_recurring'];
    $allowed_order = ['ASC', 'DESC'];
    
    $orderby = isset($_GET['orderby']) && in_array($_GET['orderby'], $allowed_orderby) ? $_GET['orderby'] : 'created_at';
    $order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), $allowed_order) ? strtoupper($_GET['order']) : 'DESC';
    
    // Build query met project join voor sortering
    $base_query = "
      SELECT d.*, p.post_title as project_title 
      FROM {$wpdb->prefix}mollie_donations d 
      LEFT JOIN {$wpdb->posts} p ON d.project_id = p.ID
    ";
    
    // Add ORDER BY clause
    if ($orderby === 'project_title') {
      $query = $base_query . " ORDER BY p.post_title $order";
    } else {
      $query = $base_query . " ORDER BY d.$orderby $order";
    }
    
    // Haal alle donaties op uit de database
    $donations = $wpdb->get_results($query, ARRAY_A);
    
    // Helper function voor sorteerbare headers
    function sortable_header($column, $title, $current_orderby, $current_order) {
      $new_order = ($current_orderby === $column && $current_order === 'ASC') ? 'DESC' : 'ASC';
      $arrow = '';
      if ($current_orderby === $column) {
        $arrow = $current_order === 'ASC' ? ' ↑' : ' ↓';
      }
      $url = add_query_arg(['orderby' => $column, 'order' => strtolower($new_order)]);
      return "<a href=\"$url\" style=\"text-decoration: none; color: inherit; font-weight: 600;\">$title$arrow</a>";
    }
    
    // Bereken statistieken
    $total_donations = count($donations);
    $total_amount = 0;
    $successful_donations = 0;
    $recurring_donations = 0;
    $one_time_donations = 0;
    
    foreach ($donations as $donation) {
      if ($donation['status'] === 'paid') {
        $total_amount += $donation['amount'];
        $successful_donations++;
      }
      
      if ($donation['is_recurring']) {
        $recurring_donations++;
      } else {
        $one_time_donations++;
      }
    }
    
    ?>
    <div class="wrap">
      <h1>Donatie Overzicht</h1>
      <p>Hier vind je een overzicht van alle ontvangen donaties. Klik op kolomtitels om te sorteren.</p>
      
      <?php
      // Verwerk bulk acties
      if (isset($_POST['bulk_action']) && isset($_POST['donation_ids']) && !empty($_POST['donation_ids'])) {
        if (!wp_verify_nonce($_POST['bulk_nonce'], 'bulk_donations_action')) {
          echo '<div class="notice notice-error"><p>Veiligheidscontrole mislukt.</p></div>';
        } else {
          $donation_ids = array_map('intval', $_POST['donation_ids']);
          $action = sanitize_text_field($_POST['bulk_action']);
          
          if ($action === 'delete') {
            $placeholders = implode(',', array_fill(0, count($donation_ids), '%d'));
            $deleted = $wpdb->query(
              $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}mollie_donations WHERE id IN ($placeholders)",
                ...$donation_ids
              )
            );
            
            if ($deleted !== false) {
              echo '<div class="notice notice-success"><p>' . $deleted . ' donatie(s) succesvol verwijderd.</p></div>';
              // Refresh data na verwijdering
              if ($orderby === 'project_title') {
                $donations = $wpdb->get_results($base_query . " ORDER BY p.post_title $order", ARRAY_A);
              } else {
                $donations = $wpdb->get_results($base_query . " ORDER BY d.$orderby $order", ARRAY_A);
              }
            } else {
              echo '<div class="notice notice-error"><p>Er is een fout opgetreden bij het verwijderen.</p></div>';
            }
          }
        }
      }
      ?>
      
      <!-- Statistieken -->
      <div style="display: flex; gap: 1.25rem; margin-bottom: 1.25rem;">
        <div class="postbox" style="flex: 1;">
          <div class="inside">
            <h3>📊 Statistieken</h3>
            <p><strong>Totaal donaties:</strong> <?php echo $total_donations; ?></p>
            <p><strong>Succesvolle donaties:</strong> <?php echo $successful_donations; ?></p>
            <p><strong>Totaal bedrag:</strong> €<?php echo number_format($total_amount, 2, ',', '.'); ?></p>
            <p><strong>Slagingspercentage:</strong> <?php echo $total_donations > 0 ? round(($successful_donations / $total_donations) * 100, 1) : 0; ?>%</p>
            <hr style="margin: 1rem 0;">
            <p><strong>🔄 Terugkerende donaties:</strong> <?php echo $recurring_donations; ?></p>
            <p><strong>💫 Eenmalige donaties:</strong> <?php echo $one_time_donations; ?></p>
          </div>
        </div>
      </div>
      
      <!-- Donaties tabel -->
      <?php if (empty($donations)): ?>
        <div class="notice notice-info">
          <p>Er zijn nog geen donaties ontvangen.</p>
        </div>
      <?php else: ?>
        <form method="post" id="donations-form">
          <?php wp_nonce_field('bulk_donations_action', 'bulk_nonce'); ?>
          
          <!-- Bulk acties -->
          <div class="tablenav top">
            <div class="alignleft actions bulkactions">
              <select name="bulk_action" id="bulk-action-selector-top">
                <option value="">Bulk acties</option>
                <option value="delete">Verwijderen</option>
              </select>
              <input type="submit" class="button action" value="Toepassen" onclick="return confirm('Weet je zeker dat je de geselecteerde donaties wilt verwijderen?');">
            </div>
          </div>
          
          <table class="wp-list-table widefat fixed striped">
            <thead>
              <tr>
                <th scope="col" class="manage-column column-cb check-column">
                  <label class="screen-reader-text" for="cb-select-all-1">Alles selecteren</label>
                  <input id="cb-select-all-1" type="checkbox">
                </th>
                <th scope="col" style="width: 3.75rem;"><?php echo sortable_header('id', 'ID', $orderby, $order); ?></th>
                <th scope="col"><?php echo sortable_header('name', 'Naam', $orderby, $order); ?></th>
                <th scope="col"><?php echo sortable_header('email', 'E-mail', $orderby, $order); ?></th>
                <th scope="col" style="width: 6.25rem;"><?php echo sortable_header('amount', 'Bedrag', $orderby, $order); ?></th>
                <th scope="col" style="width: 8.75rem;"><?php echo sortable_header('project_title', 'Project', $orderby, $order); ?></th>
                <th scope="col" style="width: 5rem;"><?php echo sortable_header('is_recurring', 'Type', $orderby, $order); ?></th>
                <th scope="col" style="width: 7.5rem;"><?php echo sortable_header('status', 'Status', $orderby, $order); ?></th>
                <th scope="col" style="width: 8.75rem;"><?php echo sortable_header('created_at', 'Datum', $orderby, $order); ?></th>
                <th scope="col" style="width: 7.5rem;">Payment ID</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($donations as $donation): ?>
                <tr>
                  <th scope="row" class="check-column">
                    <label class="screen-reader-text" for="cb-select-<?php echo $donation['id']; ?>">
                      Selecteer donatie <?php echo $donation['id']; ?>
                    </label>
                    <input id="cb-select-<?php echo $donation['id']; ?>" type="checkbox" name="donation_ids[]" value="<?php echo $donation['id']; ?>">
                  </th>
                    <td><?php echo esc_html($donation['id']); ?></td>
                  <td><?php echo esc_html($donation['name']); ?></td>
                  <td><?php echo esc_html($donation['email']); ?></td>
                    <td>€<?php echo number_format($donation['amount'], 2, ',', '.'); ?></td>
                  <td>
                    <?php 
                    if (!empty($donation['project_title'])) {
                      echo '<span style="color: #2271b1; font-weight: 500;">' . esc_html($donation['project_title']) . '</span>';
                    } else {
                      echo '<span style="color: #666;">-</span>';
                    }
                    ?>
                  </td>
                    <td>
                    <?php if ($donation['is_recurring']): ?>
                      <span style="color: #2271b1; font-weight: 600;">
                        🔄 
                        <?php 
                        switch ($donation['recurring_interval']) {
                          case '1 day': echo 'Dagelijks'; break;
                          case '1 month': echo 'Maandelijks'; break;
                          case '3 months': echo 'Kwartaal'; break;
                          case '1 year': echo 'Jaarlijks'; break;
                          default: echo 'Terugkerend';
                        }
                        ?>
                      </span>
                    <?php else: ?>
                      <span style="color: #666;">💫 Eenmalig</span>
                    <?php endif; ?>
                  </td>
                    <td>
                    <?php
                    $status_class = '';
                    $status_text = '';
                    
                    switch ($donation['status']) {
                      case 'paid':
                        $status_class = 'success';
                        $status_text = '✅ Betaald';
                        break;
                      case 'failed':
                        $status_class = 'error';
                        $status_text = '❌ Mislukt';
                        break;
                      case 'canceled':
                        $status_class = 'warning';
                        $status_text = '⚠️ Geannuleerd';
                        break;
                      case 'expired':
                        $status_class = 'warning';
                        $status_text = '⏰ Verlopen';
                        break;
                      case 'pending':
                      case 'open':
                        $status_class = 'info';
                        $status_text = '🔄 In behandeling';
                        break;
                      default:
                        $status_class = 'secondary';
                        $status_text = ucfirst($donation['status']);
                    }
                    ?>
                    <span class="button button-<?php echo $status_class; ?> button-small" style="cursor: default;">
                      <?php echo $status_text; ?>
                    </span>
                  </td>
                  <td>
                    <?php 
                    $date = new \DateTime($donation['created_at']);
                    echo $date->format('d-m-Y H:i');
                    ?>
                  </td>                  <td>
                    <code style="font-size: 0.6875rem;"><?php echo esc_html(substr($donation['payment_id'], 0, 12)) . '...'; ?></code>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </form>
        
        <style>
        .button-success {
          background: #46b450;
          border-color: #46b450;
          color: white;
        }
        .button-error {
          background: #dc3232;
          border-color: #dc3232;
          color: white;
        }
        .button-warning {
          background: #ffb900;
          border-color: #ffb900;
          color: white;
        }
        .button-info {
          background: #00a0d2;
          border-color: #00a0d2;
          color: white;
        }
        .button-secondary {
          background: #f3f5f6;
          border-color: #ddd;
          color: #333;
        }
        
        /* Sortable header styling */
        thead th a:hover {
          color: #2271b1 !important;
        }
        </style>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
          // Select all checkbox functionality
          const selectAllCheckbox = document.getElementById('cb-select-all-1');
          const individualCheckboxes = document.querySelectorAll('input[name="donation_ids[]"]');
          
          if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
              individualCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
              });
            });
          }
          
          // Update select all checkbox when individual checkboxes change
          individualCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
              const allChecked = Array.from(individualCheckboxes).every(cb => cb.checked);
              const noneChecked = Array.from(individualCheckboxes).every(cb => !cb.checked);
              
              if (selectAllCheckbox) {
                selectAllCheckbox.checked = allChecked;
                selectAllCheckbox.indeterminate = !allChecked && !noneChecked;
              }
            });
          });
        });
        </script>
      <?php endif; ?>
    </div>
    <?php
  }

  public static function test_api_connection() {
    // Verificeer nonce voor veiligheid
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mollie_test_connection')) {
      wp_send_json_error('Security check failed');
      return;
    }

    $mode = sanitize_text_field($_POST['mode'] ?? '');
    $api_key = sanitize_text_field($_POST['api_key'] ?? '');

    if (empty($api_key)) {
      wp_send_json_error('Geen API key opgegeven');
      return;
    }

    // Controleer of de API key het juiste format heeft
    if ($mode === 'test' && !str_starts_with($api_key, 'test_')) {
      wp_send_json_error('Test API key moet beginnen met "test_"');
      return;
    }
    
    if ($mode === 'live' && !str_starts_with($api_key, 'live_')) {
      wp_send_json_error('Live API key moet beginnen met "live_"');
      return;
    }

    try {
      // Gebruik curl om een simpele API call te doen
      $url = 'https://api.mollie.com/v2/methods';
      
      $curl = curl_init();
      curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
          'Authorization: Bearer ' . $api_key,
          'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 10
      ]);
      
      $response = curl_exec($curl);
      $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
      $curl_error = curl_error($curl);
      curl_close($curl);
      
      if ($curl_error) {
        wp_send_json_error('Curl fout: ' . $curl_error);
        return;
      }
      
      if ($http_code === 200) {
        $data = json_decode($response, true);
        $methods_count = isset($data['_embedded']['methods']) ? count($data['_embedded']['methods']) : 0;
        
        wp_send_json_success([
          'message' => 'Verbinding succesvol! API key werkt correct. (' . $methods_count . ' betaalmethoden beschikbaar)',
          'mode' => $mode,
          'methods_count' => $methods_count
        ]);
      } elseif ($http_code === 401) {
        wp_send_json_error('Ongeldige API key (niet geautoriseerd)');
      } elseif ($http_code === 400) {
        wp_send_json_error('Ongeldige API key (verkeerd formaat)');
      } elseif ($http_code === 403) {
        wp_send_json_error('API key heeft geen toegang tot deze functie');
      } elseif ($http_code === 404) {
        wp_send_json_error('API endpoint niet gevonden');
      } elseif ($http_code >= 500) {
        wp_send_json_error('Mollie server probleem (HTTP ' . $http_code . ')');
      } else {
        wp_send_json_error('API fout (HTTP ' . $http_code . ')');
      }
      
    } catch (\Exception $e) {
      wp_send_json_error('Verbinding mislukt: ' . $e->getMessage());
    }
  }
}
