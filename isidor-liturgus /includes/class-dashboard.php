<?php
/**
 * Dashboard
 */
class Liturgus_Dashboard {
    
    public function __construct() {
        add_shortcode('liturgus_dashboard', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    }
    
    public function enqueue() {
        if (!is_singular() || !has_shortcode(get_post()->post_content, 'liturgus_dashboard')) {
            return;
        }
        
        wp_enqueue_style('liturgus', ISIDOR_LITURGUS_URL . 'assets/liturgus.css', [], ISIDOR_LITURGUS_VERSION);
        wp_enqueue_script('liturgus', ISIDOR_LITURGUS_URL . 'assets/liturgus.js', ['jquery'], ISIDOR_LITURGUS_VERSION, true);
        
        wp_localize_script('liturgus', 'liturgusData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('liturgus_nonce')
        ]);
    }
    
    public function render() {
        if (!is_user_logged_in()) {
            return '<p>Bitte einloggen um Dienste zu verwalten.</p>';
        }
        
        ob_start();
        include ISIDOR_LITURGUS_PATH . 'templates/dashboard.php';
        return ob_get_clean();
    }
}
