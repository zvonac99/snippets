<?php
// 1. JavaScript dio – dohvaća podatke iz localStorage i šalje ih AJAX-om
add_action('wp_footer', 'boxnow_checkout_custom_script');
function boxnow_checkout_custom_script() {
    if (is_checkout()) {
        ?>
        <script>
		jQuery(function($) {
			$('form.checkout').on('checkout_place_order', function() {
				const shippingMethod = $('input[name^="shipping_method"]:checked').val();
				localStorage.setItem('boxnow_debug_step_1', 'Odabrana dostava: ' + shippingMethod);

				if (shippingMethod === 'box_now_delivery') {
					const lockerRaw = localStorage.getItem('box_now_selected_locker');
					if (lockerRaw) {
						try {
							const locker = JSON.parse(lockerRaw);
							localStorage.setItem('boxnow_debug_step_2', 'Dohvaćeni locker: ' + JSON.stringify(locker));

							const lockerName = locker.boxnowLockerName;
							const addressLine1 = locker.boxnowLockerAddressLine1;
							const addressLine2 = locker.boxnowLockerAddressLine2;

							if (lockerName && addressLine1 && addressLine2) {
								$.ajax({
									url: '<?php echo admin_url("admin-ajax.php"); ?>',
									method: 'POST',
									async: false,
									data: {
										action: 'save_boxnow_locker_info',
										locker_name: lockerName,
										address1: addressLine1,
										address2: addressLine2
									},
									success: function(response) {
										localStorage.setItem('boxnow_debug_step_3', 'AJAX uspješno poslan: ' + JSON.stringify(response));
										localStorage.setItem('boxnow_last_submit_status', 'success');
									},
									error: function(xhr, status, error) {
										const responseText = xhr.responseText || 'Nema odgovora';
										localStorage.setItem('boxnow_debug_step_3', 'AJAX error: ' + error + ' | Status: ' + status + ' | Response: ' + responseText);
										localStorage.setItem('boxnow_last_submit_status', 'error');
									}

								});
							} else {
								localStorage.setItem('boxnow_debug_step_3', 'Nedostaju polja u JSON objektu.');
								localStorage.setItem('boxnow_last_submit_status', 'missing_data_fields');
							}
						} catch (err) {
							localStorage.setItem('boxnow_debug_step_2', 'Greška kod parsanja JSON-a: ' + err.message);
							localStorage.setItem('boxnow_last_submit_status', 'json_parse_error');
						}
					} else {
						localStorage.setItem('boxnow_debug_step_2', 'Nema ključa "box_now_selected_locker" u localStorage.');
						localStorage.setItem('boxnow_last_submit_status', 'missing_locker_key');
					}
				} else {
					localStorage.setItem('boxnow_debug_step_1', 'Dostava nije Box Now.');
				}
			});
		});
		</script>
        <?php
    }
}

// 2. PHP: Primanje podataka i spremanje u sesiju
add_action('wp_ajax_save_boxnow_locker_info', 'save_boxnow_locker_info');
add_action('wp_ajax_nopriv_save_boxnow_locker_info', 'save_boxnow_locker_info');
function save_boxnow_locker_info() {
    // Provjeri ako WooCommerce session postoji
    if ( ! WC()->session ) {
        WC()->session = new WC_Session_Handler();
    }

    // Pohrani podatke u WooCommerce session
    WC()->session->set('boxnow_locker_info', [
        'name' => sanitize_text_field($_POST['locker_name']),
        'address1' => sanitize_text_field($_POST['address1']),
        'address2' => sanitize_text_field($_POST['address2'])
    ]);

    wp_send_json_success('Box Now locker info spremljen u WC sesiju.');
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


// 4. Dodaj locker info u email iznad billing adrese
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
            'value' => '<div style="border: 1px solid #ccc; padding: 10px; background: #f9f9f9; font-size: 14px;"><strong>Odabrani paketomat:</strong><br>📍 <strong>Grad:</strong> ' . esc_html($locker_info['address2']) . '<br>🏠 <strong>Adresa:</strong> ' . esc_html($locker_info['address1']) . '</div>',
        ];
    }

    return $fields;
}, 10, 3);

