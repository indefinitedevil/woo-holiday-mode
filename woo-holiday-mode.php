<?php
/*
  Plugin Name: WooCommerce Holiday Mode
  Plugin URI: https://www.drunkoncaffeine.com/
  Description: Adds holiday mode options to WooCommerce
  Author: indefinitedevil
  Version: 1.0
  Author URI: https://www.drunkoncaffeine.com/
 */
 
class Woo_Holiday_Mode {
    public static function setup() {
        add_action('admin_menu', [self::class, 'admin_menu']);
        add_action('admin_init', [self::class, 'admin_init']);
        add_action('init', [self::class, 'init']);
        add_action('woocommerce_product_get_stock', [self::class, 'woocommerce_product_get_stock']);
        add_action('woocommerce_product_get_backorders', [self::class, 'woocommerce_product_get_backorders']);
    }
    
    public static function admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Holiday Mode', 'woo_holiday_mode'),
            __('Holiday Mode', 'woo_holiday_mode'),
            'manage_woocommerce',
            'woo_holiday_mode',
            [self::class, 'admin_page']
        );
    }
    
    public static function admin_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        settings_errors('holiday_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('woo_holiday_mode');
                do_settings_sections('woo_holiday_mode';
                submit_button(__('Save settings', 'woo_holiday_mode'));
                ?>
            </form>
        </div>
        <?php
    }
    
    public static function admin_init() {
        $settings = [
            'woo_holiday_mode' => [
                'label' => __('Holiday Mode', 'woo_holiday_mode'),
                'page' => 'woo_holiday_mode',
                'fields' => [
                    'enable_holiday_mode' => [
                        'label' => __('Enable', 'woo_holiday_mode'),
                        'callable' => [self::class, 'boolean_field'],
                        'args' => [
                            'note' => __('Mark site as in vacation mode.', 'woo_holiday_mode'),
                            'default' => 0,
                        ],
                    ],
                    'enable_holiday_backorder' => [
                        'label' => __('Change to backorders', 'woo_holiday_mode'),
                        'callable' => [self::class, 'boolean_field'],
                        'args' => [
                            'note' => __('Make all holiday purchases backorders.', 'woo_holiday_mode'),
                            'default' => 0,
                        ],
                    ],
                    'disable_holiday_sale' => [
                        'label' => __('Prevent sales', 'woo_holiday_mode'),
                        'callable' => [self::class, 'boolean_field'],
                        'args' => [
                            'note' => __('Prevent purchases while on holiday.', 'woo_holiday_mode'),
                            'default' => 0,
                        ],
                    ],
                    'holiday_message' => [
                        'label' => __('Holiday message', 'woo_holiday_mode'),
                        'callable' => [self::class, 'text_field'],
                        'args' => [
                            'note' => __('What to show customers.', 'woo_holiday_mode'),
                            'default' => __('We can\'t take orders right now.', 'woo_holiday_mode'),
                        ],
                    ],
                ],
            ],
        ];
        foreach ($settings as $section_id => $section) {
            if (empty($section['callable'])) {
                $section['callable'] = null;
            }
            add_settings_section($section_id, $section['label'], $section['callable'], $section['page']);
            foreach ($section['fields'] as $field_id => $field) {
                register_setting($section['page'], $field_id);
                $args = $field['args'];
                if (empty($args['id'])) {
                    $args['id'] = $field_id;
                }
                $args['name'] = $field_id;
                add_settings_field(
                    $field_id,
                    $field['label'],
                    $field['callable'],
                    $section['page'],
                    $section_id,
                    $args
                );
            }
        }
    }

    public static function text_field($args) {
        ?>
        <input type="text" id="<?php echo esc_attr($args['id']); ?>"
               name="<?php echo esc_attr($args['name']); ?>"
               value="<?php echo get_option($args['id'], isset($args['default']) ? $args['default'] : ''); ?>"
        />
        <?php if (isset($args['note'])) : ?>
            <p><?php echo esc_html($args['note']); ?></p>
        <?php endif; ?>
        <?php
    }
    public static function boolean_field($args) {
        $args['choices'] = array(
            1 => _x('Yes', 'woo_holiday_mode', 'boolean'),
            0 => _x('No', 'woo_holiday_mode', 'boolean')
        );
        self::choice_field($args);
    }

    public static function choice_field($args) {
        $current_value = get_option($args['id'], isset($args['default']) ? $args['default'] : null);
        foreach ($args['choices'] as $value => $label) :
            ?>
            <label>
                <input type="radio" name="<?php echo esc_attr($args['name']); ?>"
                       value="<?php echo $value; ?>" <?php echo $value == $current_value ? 'checked="checked"' : ''; ?>/>
                <?php echo $label; ?>
            </label>
            &nbsp;&nbsp;&nbsp;&nbsp;
        <?php
        endforeach;
        ?>
        <?php if (isset($args['note'])) : ?>
            <p><?php echo esc_html($args['note']); ?></p>
        <?php endif; ?>
        <?php
    }
    
    public static function init() {
        if (get_option('enable_holiday_mode')) {
            if (get_option('disable_holiday_sale')) {
                remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
                remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
                remove_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20 );
                remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
            }
            add_action( 'woocommerce_before_main_content', [self::class, 'disable_notice'], 5 );
            add_action( 'woocommerce_before_cart', [self::class, 'disable_notice'], 5 );
            add_action( 'woocommerce_before_checkout_form', [self::class, 'disable_notice'], 5 );
        }
    }
    
    public static function disable_notice() {
        wc_print_notice(get_option('holiday_message'));
    }
    
    public static function woocommerce_product_get_stock($stock) {
        return get_option('enable_holiday_mode') && get_option('enable_holiday_backorder') && $stock > 0 ? 0 : $stock;
    }
    
    public static function woocommerce_product_get_backorders($backorders) {
        return get_option('enable_holiday_mode') && get_option('enable_holiday_backorder') ? true : $backorders;
    }
}
 
add_action('plugins_loaded', array(Woo_Product_Report::class, 'setup'));
