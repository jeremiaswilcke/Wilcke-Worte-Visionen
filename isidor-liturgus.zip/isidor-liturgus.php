<?php
/**
 * Plugin Name: Isidor Liturgus
 * Description: Dienstplanung für Gottesdienste - Nutzt existierende Messen
 * Version: 2.5.1
 * Author: Wilcke Worte & Visionen
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('ISIDOR_LITURGUS_VERSION', '2.5.1');
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
