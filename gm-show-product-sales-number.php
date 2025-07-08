<?php
/*
Plugin Name: GM Show Product Sales Number for WooCommerce
Description: نمایش تعداد فروش واقعی + امکان افزودن عدد فیک و شخصی‌سازی پیام از پنل مدیریت (به‌صورت سراسری و جداگانه برای هر محصول).
Version: 1.4
Author: Hossein Shojaeyan
Author URI: https://t.me/GuideMaster
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: gm-sales-counter
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// افزودن منوی اصلی GM در پیشخوان + زیرمنو برای این پلاگین
add_action('admin_menu', function() {
    add_menu_page(
        'GuideMaster Settings', 'GM', 'manage_options', 'gm-main', function() {
            echo '<div class="wrap"><h1>Welcome to GM Plugin Suite</h1><p>لطفاً از منوی سمت چپ یکی از افزونه‌های GM را انتخاب کنید.</p></div>';
        }, 'dashicons-chart-bar', 59
    );

    add_submenu_page(
        'gm-main', 'تنظیمات فروش فیک', 'فروش فیک ووکامرس', 'manage_options', 'gm-sales-settings', 'gm_sales_settings_page'
    );
});

// نمایش فرم تنظیمات فروش فیک در زیرمنو
function gm_sales_settings_page() {
    echo '<div class="wrap"><h1>تنظیمات فروش فیک محصولات</h1><form method="post" action="options.php">';
    settings_fields('gm_sales_group');
    do_settings_sections('gm-sales-settings');
    submit_button();
    echo '</form></div>';
}

// ثبت تنظیمات در زیرمنو اختصاصی خود پلاگین
add_action('admin_init', function() {
    add_settings_section('gm_sales_section', '', null, 'gm-sales-settings');

    add_settings_field('gm_default_fake_sales_min', 'حداقل فروش فیک پیش‌فرض', function() {
        echo '<input type="number" name="gm_default_fake_sales_min" value="' . esc_attr(get_option('gm_default_fake_sales_min', '1')) . '" class="small-text">';
    }, 'gm-sales-settings', 'gm_sales_section');

    add_settings_field('gm_default_fake_sales_max', 'حداکثر فروش فیک پیش‌فرض', function() {
        echo '<input type="number" name="gm_default_fake_sales_max" value="' . esc_attr(get_option('gm_default_fake_sales_max', '9')) . '" class="small-text">';
    }, 'gm-sales-settings', 'gm_sales_section');

    add_settings_field('gm_default_prefix', 'متن پیش‌فرض قبل از عدد فروش', function() {
        echo '<input type="text" name="gm_default_prefix" value="' . esc_attr(get_option('gm_default_prefix', '🔥 تا الان')) . '" class="regular-text">';
    }, 'gm-sales-settings', 'gm_sales_section');

    add_settings_field('gm_default_suffix', 'متن پیش‌فرض بعد از عدد فروش', function() {
        echo '<input type="text" name="gm_default_suffix" value="' . esc_attr(get_option('gm_default_suffix', 'نفر خرید کرده‌اند')) . '" class="regular-text">';
    }, 'gm-sales-settings', 'gm_sales_section');

    register_setting('gm_sales_group', 'gm_default_fake_sales_min');
    register_setting('gm_sales_group', 'gm_default_fake_sales_max');
    register_setting('gm_sales_group', 'gm_default_prefix');
    register_setting('gm_sales_group', 'gm_default_suffix');
});

// افزودن فیلدهای سفارشی به محصول
add_action( 'woocommerce_product_options_general_product_data', function() {
    echo '<div class="options_group">';

    woocommerce_wp_text_input( array(
        'id' => '_fake_sales_count',
        'label' => 'تعداد فروش فیک (برای این محصول)',
        'desc_tip' => true,
        'type' => 'number',
        'custom_attributes' => array('min' => '0')
    ) );

    woocommerce_wp_text_input( array(
        'id' => '_sales_message_prefix',
        'label' => 'متن قبل از عدد (برای این محصول)',
        'desc_tip' => true,
        'type' => 'text'
    ) );

    woocommerce_wp_text_input( array(
        'id' => '_sales_message_suffix',
        'label' => 'متن بعد از عدد (برای این محصول)',
        'desc_tip' => true,
        'type' => 'text'
    ) );

    echo '</div>';
});

add_action( 'woocommerce_process_product_meta', function( $post_id ) {
    update_post_meta( $post_id, '_fake_sales_count', intval( $_POST['_fake_sales_count'] ?? 0 ) );
    update_post_meta( $post_id, '_sales_message_prefix', sanitize_text_field( $_POST['_sales_message_prefix'] ?? '' ) );
    update_post_meta( $post_id, '_sales_message_suffix', sanitize_text_field( $_POST['_sales_message_suffix'] ?? '' ) );
});

// نمایش تعداد فروش در صفحه محصول با اولویت فیلد محصول > تنظیمات کلی
add_action( 'woocommerce_before_add_to_cart_form', function() {
    global $product;

    if ( ! $product instanceof WC_Product ) return;

    $real_sales = $product->get_total_sales();

    $fake_sales = get_post_meta( $product->get_id(), '_fake_sales_count', true );
    $prefix = get_post_meta( $product->get_id(), '_sales_message_prefix', true );
    $suffix = get_post_meta( $product->get_id(), '_sales_message_suffix', true );

    // اگر مقدار محصول خالی بود، از تنظیمات عمومی استفاده یا عدد رندوم بساز
    if ( $fake_sales === '' || $fake_sales === false ) {
        $min = intval( get_option('gm_default_fake_sales_min', 1) );
        $max = intval( get_option('gm_default_fake_sales_max', 9) );
        $fake_sales = rand($min, $max);
    }

    if ( $prefix === '' ) $prefix = get_option('gm_default_prefix', '🔥 تا الان');
    if ( $suffix === '' ) $suffix = get_option('gm_default_suffix', 'نفر خرید کرده‌اند');

    $total = intval($fake_sales) + intval($real_sales);

    if ( $total > 0 ) {
        echo '<p class="woocommerce-total-sales" style="color: #444; font-weight: bold; margin-bottom: 15px;">'
           . esc_html( $prefix ) . ' ' . esc_html( $total ) . ' ' . esc_html( $suffix ) . '</p>';
    }
});
