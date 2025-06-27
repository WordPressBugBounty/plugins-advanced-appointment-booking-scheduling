<?php
/*
Plugin Name:       Advanced Appointment Booking & Scheduling
Description:       Advanced Appointment Booking & Scheduling: Effortlessly manage appointments with a simple, user-friendly scheduling system.
Version:           1.8
Requires at least: 5.2
Requires PHP:      7.2
Author:            themespride
Author URI:        https://www.themespride.com/
Plugin URI:
Text Domain:       advanced-appointment-booking
License:           GPL-2.0+
*/


// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

define('ABP_VERSION', '1.8');
define('ABP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ABP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ABP_LICENCE_API_ENDPOINT', 'https://license.themespride.com/api/general/');
define('ABP_MAIN_URL', 'https://www.themespride.com/');

include_once(plugin_dir_path(__FILE__) . 'includes/class-appointment-admin.php');
include_once(plugin_dir_path(__FILE__) . 'includes/service-operations-handler.php');

register_activation_hook(__FILE__, 'abp_create_services_table');
function abp_create_services_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'appointment_services';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        service_name varchar(255) NOT NULL,
        duration int(11) NOT NULL,
        price decimal(10,2) NOT NULL,
        description text NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function abp_create_appointment_booking_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'appointment_booking';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        service_id bigint(20) NOT NULL,
        booking_date date NOT NULL,
        booking_time time NOT NULL,
        name varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        phone varchar(15) NOT NULL,
        price float NOT NULL,
        status varchar(20) DEFAULT 'pending',
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'abp_create_appointment_booking_table');

// new add
function abp_create_staff_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'abp_staff';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        service_ids TEXT,
        availability TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'abp_create_staff_table');
// end


register_activation_hook(__FILE__, 'abp_create_appointment_booking_pages');

function abp_create_appointment_booking_pages()
{
    // Create Login page
    if (!get_page_by_path('login')) {
        wp_insert_post([
            'post_title' => 'Login',
            'post_name' => 'login',
            'post_content' => '[appointment_login_form]',
            'post_status' => 'publish',
            'post_type' => 'page'
        ]);
    }

    // Create Register page
    if (!get_page_by_path('register')) {
        wp_insert_post([
            'post_title' => 'Register',
            'post_name' => 'register',
            'post_content' => '[appointment_register_form]',
            'post_status' => 'publish',
            'post_type' => 'page'
        ]);
    }

    // Create Book Appointment page
    if (!get_page_by_path('book-appointment')) {
        wp_insert_post([
            'post_title' => 'Book Appointment',
            'post_name' => 'book-appointment',
            'post_content' => '[book_appointment_form]',
            'post_status' => 'publish',
            'post_type' => 'page'
        ]);
    }

    // Create Bookings page
    if (!get_page_by_path('abp-bookings')) {
        wp_insert_post([
            'post_title' => 'Bookings',
            'post_name' => 'abp-bookings',
            'post_content' => '[abp_bookings_page]',
            'post_status' => 'publish',
            'post_type' => 'page'
        ]);
    }
}



add_action('admin_enqueue_scripts', 'abp_enqueue_admin_assets');
function abp_enqueue_admin_assets()
{
    $screen = get_current_screen();
    $style_version = filemtime(plugin_dir_path(__FILE__) . 'assets/css/style.css');

    wp_enqueue_style('abp-style', plugins_url('/assets/css/style.css', __FILE__), [], $style_version);

    wp_enqueue_script(
        'abp-admin-clean-url',
        plugin_dir_url(__FILE__) . 'assets/js/admin.js',
        [],
        ABP_VERSION,
        true
    );

    if ($screen->id == 'toplevel_page_appointment-booking-themes' || $screen->id == 'appointments_page_appointment-bookings') {

        $data = ".notice.is-dismissible {
            display: none;
        }";

        wp_add_inline_style('abp-style', $data);

    }


    if (isset($_GET['page']) && ($_GET['page'] == 'appointment-booking-admin' || $_GET['page'] == 'appointment-bookings' || $_GET['page'] == 'appointment-booking-themes')) {

        wp_enqueue_style('abp-bootstrap-css', plugins_url('/assets/lib/bootstrap.css', __FILE__), [], $style_version);
        wp_enqueue_script('abp-bootstrap-js', plugins_url('/assets/lib/bootstrap.js', __FILE__), ['jquery'], null, true);

    }

}
add_action('wp_enqueue_scripts', 'abp_enqueue_assets');
function abp_enqueue_assets()
{
    $style_version = filemtime(plugin_dir_path(__FILE__) . 'assets/css/abp-front.css');
    $script_version = filemtime(plugin_dir_path(__FILE__) . 'assets/js/booking.js');

    wp_enqueue_style('abp-style', plugins_url('/assets/css/abp-front.css', __FILE__), [], $style_version);

    wp_enqueue_script('abp-script', plugins_url('/assets/js/booking.js', __FILE__), ['jquery'], $script_version, true);
}

add_action('admin_notices', 'adv_app_book_admin_notice_with_html');
function adv_app_book_admin_notice_with_html()
{
    ?>
    <div class="notice is-dismissible adv-app-book">
        <div class="adv-app-book-notice-banner-wrap"
            style="background-image: url(<?php echo esc_url(plugins_url('includes/images/ban-plain.png', __FILE__)); ?>)">
            <div class="adv-app-book-notice-heading">
                <h1 class="adv-app-book-main-head"><?php echo esc_html('WordPress Theme Bundle'); ?></h1>
                <p class="adv-app-book-sub-head">
                    <span><?php echo esc_html('120+'); ?></span><?php echo esc_html('  Premium WordPress Themes in 1 Bundle '); ?>
                </p>
                <div class="adv-app-book-notice-btn">
                    <a class="adv-app-book-buy-btn" target="_blank"
                        href="<?php echo esc_url(ABP_MAIN_URL . 'products/wordpress-theme-bundle'); ?>"><?php echo esc_html('Buy Now'); ?></a>
                </div>
            </div>
        </div>
    </div>
    <?php
}

