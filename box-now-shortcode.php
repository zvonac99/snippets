<?php
// Shortcode za prikaz BoxNow info nakon narudžbe
function show_boxnow_locker_info_shortcode($atts) {
    if (!is_order_received_page()) {
        return ''; // Ne prikazuj ako nije stranica zahvale
    }

    // Pokušaj dohvatiti ID narudžbe iz URL-a
    $order_id = absint(get_query_var('order-received'));
    if (!$order_id) {
        return '';
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return '';
    }

    // Provjera je li BoxNow dostava
    $is_boxnow = false;
    foreach ($order->get_shipping_methods() as $method) {
        if ($method->get_method_id() === 'box_now_delivery') {
            $is_boxnow = true;
            break;
        }
    }

    if (!$is_boxnow) {
        return '';
    }

    // Dohvati podatke iz meta
    $locker_info = $order->get_meta('_boxnow_locker_info');
    if (empty($locker_info) || !is_array($locker_info)) {
        return '';
    }

    // Priprema HTML bloka
    ob_start();
    ?>
    <div style="border: 2px dashed #84c33e; padding: 15px; margin-top: 20px; background: #f8fff0;">
        <strong>📦 Dostava putem Box Now paketomata</strong><br><br>
        <strong>Grad:</strong> <?php echo esc_html($locker_info['address2']); ?><br>
        <strong>Adresa:</strong> <?php echo esc_html($locker_info['address1']); ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('boxnow_locker_info', 'show_boxnow_locker_info_shortcode');
