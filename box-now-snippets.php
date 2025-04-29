<?php
// 1. JavaScript dio – dohvaća podatke iz localStorage i šalje ih AJAX-om
add_action('wp_footer', 'boxnow_checkout_custom_script');
function boxnow_checkout_custom_script() {
    if (is_checkout()) :
	?>
		<script>
		jQuery(function($) {
			// console.log('BoxNow listener aktiviran.');

			// Neposredno slušanje postMessage događaja
			window.addEventListener('message', function(event) {
				if (event.data && event.data.boxnowLockerId) {
					const lockerData = {
						locker_id: event.data.boxnowLockerId,
						locker_ime: event.data.boxnowLockerName,
						locker_adresa: event.data.boxnowLockerAddressLine1,
						locker_grad: event.data.boxnowLockerAddressLine2
					};

					// console.log('Primljeni podaci iz BoxNow widgeta:', lockerData);

					// Slanje podataka na server putem AJAX-a
					$.ajax({
						url: '<?php echo admin_url("admin-ajax.php"); ?>',
						method: 'POST',
						dataType: 'json', // Postavi dataType na JSON
						data: {
							action: 'save_boxnow_locker_info',
							locker_data: lockerData
						},
						success: function(response) {
                                // console.log('✅ Podaci o paketomatu uspješno poslani na server.', response);
						},
						error: function(xhr, status, error) {
							console.error('❌ Greška prilikom slanja podataka:', error);
						}
					});
				}
			});
		});
		</script>
	<?php
    endif;
}

// 2. PHP: Prima podatke i sprema u sesiju
add_action('wp_ajax_save_boxnow_locker_info', 'save_boxnow_locker_info');
add_action('wp_ajax_nopriv_save_boxnow_locker_info', 'save_boxnow_locker_info');
function save_boxnow_locker_info() {
    // Provjeri ako WooCommerce session postoji
    if ( ! WC()->session ) {
        WC()->session = new WC_Session_Handler();
    }

    // Provjeri postoji li 'locker_data' u POST-u
    if ( isset($_POST['locker_data']) ) {
        $locker_data = $_POST['locker_data'];

        // Pohrani podatke u WooCommerce session
        WC()->session->set('boxnow_locker_info', [
            'ime' => sanitize_text_field($locker_data['locker_ime']),
            'adresa' => sanitize_text_field($locker_data['locker_adresa']),
            'grad' => sanitize_text_field($locker_data['locker_grad']),
        ]);
		
    } else {
        wp_send_json_error('❌ Podaci nisu poslani.');
    }
}

// 3. Spremi locker info u meta podatke narudžbe
add_action('woocommerce_checkout_create_order', 'add_boxnow_info_to_order', 20, 2);
function add_boxnow_info_to_order($order, $data) {
    // Provjeri je li WooCommerce session postavljen
    if (!WC()->session) {
        WC()->session = new WC_Session_Handler();
    }

    // Dohvati podatke iz WooCommerce sesije
    $locker_info = WC()->session->get('boxnow_locker_info');

    // Provjeri je li locker_info prisutno
    if (!empty($locker_info)) {
        // Spremi podatke u meta podatke narudžbe
        $order->update_meta_data('_boxnow_locker_info', $locker_info);

        // Očisti podatke iz sesije nakon što su spremljeni
        WC()->session->__unset('boxnow_locker_info');
    }
}

// Dodaj locker info u email iznad billing adrese
add_filter('woocommerce_email_order_meta_fields', function ($fields, $sent_to_admin, $order) {
    // Provjeri sve metode dostave u narudžbi
    $is_boxnow = false;
    foreach ($order->get_shipping_methods() as $shipping_method) {
        if ($shipping_method->get_method_id() === 'box_now_delivery') {
            $is_boxnow = true;
            break;
        }
    }

    // Ako nije odabrana Box Now dostava, ne dodaj ništa
    if (!$is_boxnow) {
        return $fields;
    }

    // Učitaj spremljene podatke
    $locker_info = $order->get_meta('_boxnow_locker_info');

    if (!empty($locker_info)) {
        // Formatiraj podatke u HTML za e-mail
        $fields['boxnow_notice'] = [
            'label' => '',
            'value' => '<div style="border: 1px solid #ccc; padding: 10px; background: #f9f9f9; font-size: 14px;"><strong>BOX NOW paketomat:</strong> ' . esc_html($locker_info['ime']) . '<br>🏠 <strong>Adresa:</strong> ' . esc_html($locker_info['adresa']) . '<br>📍 <strong>Grad:</strong> ' . esc_html($locker_info['grad']) . '</div>',
        ];
    }

    return $fields;
}, 10, 3);
