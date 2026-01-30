<?php
/**
 * Plugin Name: Isidor Liturgus
 * Description: Dienstplanung für Gottesdienste - Nutzt existierende Messen
 * Version: 2.5.3
 * Author: Wilcke Worte & Visionen
 * Requires PHP: 7.4
 * GitHub Plugin URI: https://github.com/Kaiserliche-Hoheit/isidor-liturgus
 * Primary Branch: main
 */

if (!defined('ABSPATH')) exit;

define('ISIDOR_LITURGUS_VERSION', '2.5.3');
define('ISIDOR_LITURGUS_PATH', plugin_dir_path(__FILE__));
define('ISIDOR_LITURGUS_URL', plugin_dir_url(__FILE__));

// Core Classes
require_once ISIDOR_LITURGUS_PATH . 'includes/class-database.php';
require_once ISIDOR_LITURGUS_PATH . 'includes/class-slots.php';
require_once ISIDOR_LITURGUS_PATH . 'includes/class-assignments.php';
require_once ISIDOR_LITURGUS_PATH . 'includes/class-dashboard.php';
require_once ISIDOR_LITURGUS_PATH . 'includes/class-admin.php';
require_once ISIDOR_LITURGUS_PATH . 'includes/class-reminders.php';

// Activation/Deactivation
register_activation_hook(__FILE__, ['Liturgus_Database', 'activate']);
register_deactivation_hook(__FILE__, ['Liturgus_Database', 'deactivate']);

/**
 * Findet die Dashboard-Seite automatisch (mit Caching)
 * 
 * @return string URL zur Dashboard-Seite
 */
function liturgus_get_dashboard_url() {
    // Erst Cache prüfen (transient für 1 Stunde)
    $cached_url = get_transient('liturgus_dashboard_url');
    if ($cached_url !== false) {
        return $cached_url;
    }
    
    // Suche Seite mit [liturgus_dashboard] Shortcode
    global $wpdb;
    $page = $wpdb->get_row(
        "SELECT ID FROM {$wpdb->posts} 
         WHERE post_type = 'page' 
         AND post_status = 'publish' 
         AND post_content LIKE '%[liturgus_dashboard%' 
         ORDER BY ID ASC 
         LIMIT 1"
    );
    
    if ($page) {
        $url = get_permalink($page->ID);
        set_transient('liturgus_dashboard_url', $url, HOUR_IN_SECONDS);
        return $url;
    }
    
    // Fallback: Option prüfen (falls manuell gesetzt)
    $manual_page_id = get_option('liturgus_dashboard_page_id');
    if ($manual_page_id && get_post_status($manual_page_id) === 'publish') {
        $url = get_permalink($manual_page_id);
        set_transient('liturgus_dashboard_url', $url, HOUR_IN_SECONDS);
        return $url;
    }
    
    // Letzter Fallback: Home URL (besser als 404)
    return home_url('/');
}

/**
 * Cache invalidieren wenn Seiten gespeichert werden
 */
add_action('save_post_page', function($post_id) {
    $content = get_post_field('post_content', $post_id);
    if (strpos($content, '[liturgus_dashboard') !== false) {
        delete_transient('liturgus_dashboard_url');
    }
});

/**
 * Dashboard-Seite automatisch erstellen (AJAX)
 */
add_action('wp_ajax_liturgus_create_dashboard_page', function() {
    check_ajax_referer('liturgus_create_page', 'nonce');
    
    if (!current_user_can('publish_pages')) {
        wp_send_json_error(['message' => 'Keine Berechtigung']);
    }
    
    // Prüfen ob bereits existiert
    global $wpdb;
    $exists = $wpdb->get_var(
        "SELECT ID FROM {$wpdb->posts} 
         WHERE post_type = 'page' 
         AND post_status = 'publish' 
         AND post_content LIKE '%[liturgus_dashboard%' 
         LIMIT 1"
    );
    
    if ($exists) {
        wp_send_json_error(['message' => 'Dashboard-Seite existiert bereits', 'url' => get_permalink($exists)]);
    }
    
    // Seite erstellen
    $page_id = wp_insert_post([
        'post_title'   => 'Dienste',
        'post_name'    => 'dienste',
        'post_content' => '[liturgus_dashboard]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_author'  => get_current_user_id()
    ]);
    
    if (is_wp_error($page_id)) {
        wp_send_json_error(['message' => $page_id->get_error_message()]);
    }
    
    // Cache löschen
    delete_transient('liturgus_dashboard_url');
    
    wp_send_json_success([
        'message' => 'Dashboard-Seite erstellt!',
        'url' => get_permalink($page_id),
        'edit_url' => get_edit_post_link($page_id, 'raw')
    ]);
});

/**
 * Admin-Hinweis wenn keine Dashboard-Seite existiert
 */
add_action('admin_notices', function() {
    // Nur auf Liturgus Admin-Seiten
    if (!isset($_GET['page']) || strpos($_GET['page'], 'liturgus') === false) {
        return;
    }
    
    // Nur für Admins
    if (!current_user_can('publish_pages')) {
        return;
    }
    
    // Prüfen ob Dashboard-Seite existiert
    global $wpdb;
    $exists = $wpdb->get_var(
        "SELECT ID FROM {$wpdb->posts} 
         WHERE post_type = 'page' 
         AND post_status = 'publish' 
         AND post_content LIKE '%[liturgus_dashboard%' 
         LIMIT 1"
    );
    
    if ($exists) {
        return;
    }
    
    $nonce = wp_create_nonce('liturgus_create_page');
    ?>
    <div class="notice notice-warning" id="liturgus-dashboard-notice">
        <p>
            <strong>Liturgus:</strong> Es existiert noch keine Dashboard-Seite mit dem <code>[liturgus_dashboard]</code> Shortcode.
            Links in E-Mails führen ins Leere!
        </p>
        <p>
            <button type="button" class="button button-primary" id="liturgus-create-page-btn">
                Dashboard-Seite jetzt erstellen
            </button>
            <span id="liturgus-create-status" style="margin-left: 10px;"></span>
        </p>
    </div>
    <script>
    jQuery(function($) {
        $('#liturgus-create-page-btn').on('click', function() {
            var $btn = $(this);
            var $status = $('#liturgus-create-status');
            
            $btn.prop('disabled', true).text('Wird erstellt...');
            
            $.post(ajaxurl, {
                action: 'liturgus_create_dashboard_page',
                nonce: '<?php echo $nonce; ?>'
            }, function(response) {
                if (response.success) {
                    $status.html('<span style="color:green;">✓ ' + response.data.message + '</span> <a href="' + response.data.url + '" target="_blank">Seite anzeigen</a>');
                    $btn.hide();
                    setTimeout(function() {
                        $('#liturgus-dashboard-notice').fadeOut();
                    }, 3000);
                } else {
                    $status.html('<span style="color:red;">✗ ' + response.data.message + '</span>');
                    $btn.prop('disabled', false).text('Dashboard-Seite jetzt erstellen');
                }
            });
        });
    });
    </script>
    <?php
});

// Initialize
add_action('plugins_loaded', function() {
    new Liturgus_Slots();
    new Liturgus_Assignments();
    new Liturgus_Dashboard();
    new Liturgus_Reminders();
    
    if (is_admin()) {
        new Liturgus_Admin();
    }
});
