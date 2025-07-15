<div class="mollie-donations">
  <form method="post">
    <p><label>Naam: <input type="text" name="name" required></label></p>
    <p><label>E-mail: <input type="email" name="email" required></label></p>
    <?php
    // Project selectie indien ingeschakeld en er zijn posts
    $enabled_post_types = get_option('mollie_enabled_post_types', array());
    $has_available_projects = false;
    
    if (!empty($enabled_post_types)) {
      foreach ($enabled_post_types as $post_type) {
        $posts = get_posts([
          'post_type' => $post_type,
          'post_status' => 'publish',
          'numberposts' => -1,
          'orderby' => 'title',
          'order' => 'ASC'
        ]);
        
        if (!empty($posts)) {
          $has_available_projects = true;
          $post_type_object = get_post_type_object($post_type);
          ?>
          <p>
            <label><?php echo esc_html($post_type_object->labels->singular_name); ?> (optioneel):</label>
            <select name="project_id_<?php echo esc_attr($post_type); ?>" class="project-select">
              <option value="">Geen voorkeur</option>
              <?php foreach ($posts as $post): ?>
                <option value="<?php echo esc_attr($post->ID); ?>"><?php echo esc_html($post->post_title); ?></option>
              <?php endforeach; ?>
            </select>
            <small style="color: #666; font-size: 0.875rem; margin-top: 0.25rem; display: block;">
              Selecteer optioneel een specifiek <?php echo strtolower(esc_html($post_type_object->labels->singular_name)); ?> om aan te doneren.
            </small>
          </p>
          <?php
        }
      }
    }
    ?>
    <p>
      <label>Kies een bedrag:</label>
      <div class="amount-options">
        <label><input type="radio" name="amount_type" value="5" checked> €5</label>
        <label><input type="radio" name="amount_type" value="10"> €10</label>
        <label><input type="radio" name="amount_type" value="25"> €25</label>
        <label><input type="radio" name="amount_type" value="custom"> Eigen bedrag</label>
      </div>
    </p>
    <p class="custom-amount">
      <label>Eigen bedrag: 
        <span class="currency-input">
          €<input type="number" name="custom_amount" min="1" step="1" placeholder="0">
        </span>
      </label>
      <small class="custom-amount-help">
        Voer een bedrag in hele euro's in (minimaal €1)
      </small>
    </p>
    <p>
      <label>Donatie type:</label>
      <div class="interval-options">
        <label><input type="radio" name="recurring_interval" value="one_time" checked> Eenmalig</label>
        <label><input type="radio" name="recurring_interval" value="1 day"> Dagelijks</label>
        <label><input type="radio" name="recurring_interval" value="1 month"> Maandelijks</label>
        <label><input type="radio" name="recurring_interval" value="3 months"> Per kwartaal</label>
        <label><input type="radio" name="recurring_interval" value="1 year"> Jaarlijks</label>
      </div>
      <small class="recurring-info" style="display: none; color: #666; margin-top: 0.5rem; font-size: 0.875rem;">
        Voor terugkerende donaties kun je betalen met creditcard. In de live omgeving is ook automatische incasso beschikbaar.
      </small>
    </p>
    <p><button class="btn" type="submit" name="mollie_donation">Doneer nu</button></p>
  </form>
  
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const amountRadios = document.querySelectorAll('input[name="amount_type"]');
      const customAmountField = document.querySelector('.custom-amount');
      const customAmountInput = document.querySelector('input[name="custom_amount"]');
      const intervalRadios = document.querySelectorAll('input[name="recurring_interval"]');
      const recurringInfo = document.querySelector('.recurring-info');
      const projectSelects = document.querySelectorAll('.project-select');
      
      // Functie om selected klassen te updaten voor bedragen
      function updateSelectedStates() {
        amountRadios.forEach(radio => {
          const label = radio.closest('label');
          if (radio.checked) {
            label.classList.add('selected');
          } else {
            label.classList.remove('selected');
          }
        });
      }
      
      // Functie om selected klassen te updaten voor intervals
      function updateIntervalStates() {
        intervalRadios.forEach(radio => {
          const label = radio.closest('label');
          if (radio.checked) {
            label.classList.add('selected');
          } else {
            label.classList.remove('selected');
          }
        });
      }
      
      // Project select handlers - zorg dat alleen één project tegelijk geselecteerd kan worden
      projectSelects.forEach(select => {
        select.addEventListener('change', function() {
          if (this.value !== '') {
            // Reset alle andere project selects
            projectSelects.forEach(otherSelect => {
              if (otherSelect !== this) {
                otherSelect.value = '';
              }
            });
          }
        });
      });
      
      // Initial state
      updateSelectedStates();
      updateIntervalStates();
      
      amountRadios.forEach(radio => {
        radio.addEventListener('change', function() {
          updateSelectedStates();
          
          if (this.value === 'custom') {
            customAmountField.style.display = 'block';
            customAmountInput.required = true;
          } else {
            customAmountField.style.display = 'none';
            customAmountInput.required = false;
            customAmountInput.value = '';
          }
        });
      });
      
      // Interval radio handlers
      intervalRadios.forEach(radio => {
        radio.addEventListener('change', function() {
          updateIntervalStates();
          
          // Toon of verberg de info tekst op basis van de geselecteerde optie
          if (this.value === '1 day' || this.value === '1 month' || this.value === '3 months' || this.value === '1 year') {
            recurringInfo.style.display = 'block';
          } else {
            recurringInfo.style.display = 'none';
          }
        });
      });
    });
  </script>
</div>
