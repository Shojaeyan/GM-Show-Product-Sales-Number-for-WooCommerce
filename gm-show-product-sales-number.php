<?php
/*
Plugin Name: GM Show Product Sales Number
Description: Show real sales count + ability to add fake sales number and customize the message from the admin panel (globally or per-product).
Version: 1.5
Author: Hossein Shojaeyan
Author URI: https://t.me/GuideMaster
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: gm-show-product-sales-number
Domain Path: /languages
*/
if (!defined('ABSPATH')) {
    exit;
}

// Add main GM menu and submenu in WP Admin
add_action('admin_menu', function () {
    add_menu_page(
        __('GuideMaster Settings', 'gm-show-product-sales-number'),
        'GM',
        'manage_options',
        'gm-main',
        function () {
            echo '<div class="wrap"><h1>' . esc_html__('Welcome to GM Plugin Suite', 'gm-show-product-sales-number') . '</h1><p>' . esc_html__('Please select one of the GM plugins from the left menu.', 'gm-show-product-sales-number') . '</p></div>';
        },
        'dashicons-chart-bar',
        59
    );

    add_submenu_page(
        'gm-main',
        __('Fake Sales Settings', 'gm-show-product-sales-number'),
        __('Fake Sales (WooCommerce)', 'gm-show-product-sales-number'),
        'manage_options',
        'gm-sales-settings',
        'gm_sales_settings_page'
    );
});

// Display the settings form in submenu
function gm_sales_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Fake Sales Settings', 'gm-show-product-sales-number'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('gm_sales_group');
            do_settings_sections('gm-sales-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings and display fields
add_action('admin_init', function () {
    add_settings_section('gm_sales_section', '', null, 'gm-sales-settings');

    add_settings_field('gm_default_fake_sales_min', __('Default Min Fake Sales', 'gm-show-product-sales-number'), function () {
        echo '<input type="number" name="gm_default_fake_sales_min" value="' . esc_attr(get_option('gm_default_fake_sales_min', '1')) . '" class="small-text">';
    }, 'gm-sales-settings', 'gm_sales_section');

    add_settings_field('gm_default_fake_sales_max', __('Default Max Fake Sales', 'gm-show-product-sales-number'), function () {
        echo '<input type="number" name="gm_default_fake_sales_max" value="' . esc_attr(get_option('gm_default_fake_sales_max', '9')) . '" class="small-text">';
    }, 'gm-sales-settings', 'gm_sales_section');

    add_settings_field('gm_default_prefix', __('Default Prefix Text (before number)', 'gm-show-product-sales-number'), function () {
    echo '<input type="text" name="gm_default_prefix" value="' . esc_attr(get_option('gm_default_prefix', __('So far', 'gm-show-product-sales-number'))) . '" class="regular-text">';
}, 'gm-sales-settings', 'gm_sales_section');


    add_settings_field('gm_default_suffix', __('Default Suffix Text (after number)', 'gm-show-product-sales-number'), function () {
        echo '<input type="text" name="gm_default_suffix" value="' . esc_attr(get_option('gm_default_suffix', __('people have purchased','gm-show-product-sales-number'))) . '" class="regular-text">';
    }, 'gm-sales-settings', 'gm_sales_section');

    register_setting('gm_sales_group', 'gm_default_fake_sales_min', 'absint');
    register_setting('gm_sales_group', 'gm_default_fake_sales_max', 'absint');
    register_setting('gm_sales_group', 'gm_default_prefix', 'sanitize_text_field');
    register_setting('gm_sales_group', 'gm_default_suffix', 'sanitize_text_field');
});

// Add custom fields to product edit page
add_action('woocommerce_product_options_general_product_data', function () {
    echo '<div class="options_group">';
    wp_nonce_field('gm_save_sales_data', 'gm_sales_nonce');

    woocommerce_wp_text_input([
        'id' => '_fake_sales_count',
        'label' => __('Fake Sales Count (for this product)', 'gm-show-product-sales-number'),
        'desc_tip' => true,
        'type' => 'number',
        'custom_attributes' => ['min' => '0'],
    ]);

    woocommerce_wp_text_input([
        'id' => '_sales_message_prefix',
        'label' => __('Message Prefix (for this product)', 'gm-show-product-sales-number'),
        'desc_tip' => true,
        'type' => 'text',
    ]);

    woocommerce_wp_text_input([
        'id' => '_sales_message_suffix',
        'label' => __('Message Suffix (for this product)', 'gm-show-product-sales-number'),
        'desc_tip' => true,
        'type' => 'text',
    ]);

    echo '</div>';
});

// Save product custom fields
add_action('woocommerce_process_product_meta', function ($post_id) {
    if (!current_user_can('edit_product', $post_id)) {
        return;
    }

    // Validate Nonce
    $nonce = isset($_POST['gm_sales_nonce']) ? sanitize_text_field(wp_unslash($_POST['gm_sales_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'gm_save_sales_data')) {
        wp_die(esc_html__('Security error: Invalid nonce.', 'gm-show-product-sales-number'));
    }

    // Save data safely
    $fake_sales_count = isset($_POST['_fake_sales_count']) ? absint(wp_unslash($_POST['_fake_sales_count'])) : 0;
    update_post_meta($post_id, '_fake_sales_count', max(0, $fake_sales_count));

    $sales_message_prefix = isset($_POST['_sales_message_prefix']) ? sanitize_text_field(wp_unslash($_POST['_sales_message_prefix'])) : '';
    update_post_meta($post_id, '_sales_message_prefix', $sales_message_prefix);

    $sales_message_suffix = isset($_POST['_sales_message_suffix']) ? sanitize_text_field(wp_unslash($_POST['_sales_message_suffix'])) : '';
    update_post_meta($post_id, '_sales_message_suffix', $sales_message_suffix);
});

// Display sales count on product page
add_action('woocommerce_before_add_to_cart_form', function () {
    global $product;

    if (!$product instanceof WC_Product) {
        return;
    }

    $real_sales = absint($product->get_total_sales());
    $product_id = $product->get_id();
    $meta = get_post_meta($product_id);

    $fake_sales = !empty($meta['_fake_sales_count'][0]) ? absint($meta['_fake_sales_count'][0]) : '';
    $prefix = !empty($meta['_sales_message_prefix'][0]) ? sanitize_text_field($meta['_sales_message_prefix'][0]) : '';
    $suffix = !empty($meta['_sales_message_suffix'][0]) ? sanitize_text_field($meta['_sales_message_suffix'][0]) : '';

    if ($fake_sales === '') {
        $min = absint(get_option('gm_default_fake_sales_min', 1));
        $max = absint(get_option('gm_default_fake_sales_max', 9));
        $fake_sales = wp_rand($min, $max);
    }

    if ($prefix === '') {
        $prefix = sanitize_text_field(get_option('gm_default_prefix', '🔥 So far'));
    }
    if ($suffix === '') {
        $suffix = sanitize_text_field(get_option('gm_default_suffix', 'people have purchased'));
    }

    $total = $fake_sales + $real_sales;

    if ($total > 0) {
        printf(
            '<p class="gm-sales-message">%s %d %s</p>',
            esc_html($prefix),
            esc_html($total),
            esc_html($suffix)
        );
    }
});

// Add custom styles to product page
add_action('wp_head', function () {
    echo '<style>.gm-sales-message { color: #444; font-weight: bold; margin-bottom: 15px; }</style>';
});
