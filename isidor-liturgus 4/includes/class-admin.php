<?php
/**
 * Liturgus Backend Admin
 */
class Liturgus_Admin {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_post_liturgus_assign', [$this, 'handle_assign']);
    }
    
    public function add_menu() {
        add_menu_page(
            'Liturgus Dienste',
            'Liturgus',
            'liturgus_assign_others',
            'liturgus-admin',
            [$this, 'render_page'],
            'dashicons-groups',
            30
        );
    }
    
    public function render_page() {
        if (!current_user_can('liturgus_assign_others')) {
            wp_die('Keine Berechtigung');
        }
        
        // Messe-Filter
        $from = $_GET['from'] ?? date('Y-m-d');
        $to = $_GET['to'] ?? date('Y-m-d', strtotime('+28 days'));
        
        $messen = get_posts([
            'post_type' => 'isidor_messe',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_key' => '_is_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => [[
                'key' => '_is_date',
                'value' => [$from, $to],
                'compare' => 'BETWEEN',
                'type' => 'DATE'
            ]]
        ]);
        
        // User mit liturgus_signup
        $users = get_users(['capability' => 'liturgus_signup']);
        
        ?>
        <div class="wrap">
            <h1>Liturgus - Dienste zuweisen</h1>
            
            <form method="get">
                <input type="hidden" name="page" value="liturgus-admin">
                Von: <input type="date" name="from" value="<?php echo esc_attr($from); ?>">
                Bis: <input type="date" name="to" value="<?php echo esc_attr($to); ?>">
                <button type="submit" class="button">Filtern</button>
            </form>
            
            <br>
            
            <?php foreach ($messen as $messe): 
                $date = get_post_meta($messe->ID, '_is_date', true);
                $time = get_post_meta($messe->ID, '_is_time', true);
                $slots = Liturgus_Slots::get_all();
                $current_assignments = Liturgus_Assignments::get_for_messe($messe->ID);
                
                // Assignments in Array umformen [slot_id => [main => user_id, backup => user_id]]
                $assignments = [];
                foreach ($current_assignments as $a) {
                    if (!isset($assignments[$a['slot_key']])) {
                        $assignments[$a['slot_key']] = ['main' => null, 'backup' => null];
                    }
                    
                    if ($a['is_backup']) {
                        $assignments[$a['slot_key']]['backup'] = $a['user_id'];
                    } else {
                        $assignments[$a['slot_key']]['main'] = $a['user_id'];
                    }
                }
            ?>
                <div class="card" style="margin-bottom: 20px;">
                    <h2><?php echo date('D d.m.Y', strtotime($date)); ?> | <?php echo $time; ?></h2>
                    <h3><?php echo esc_html($messe->post_title); ?></h3>
                    
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('liturgus_assign', 'liturgus_nonce'); ?>
                        <input type="hidden" name="action" value="liturgus_assign">
                        <input type="hidden" name="messe_id" value="<?php echo $messe->ID; ?>">
                        
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Dienst</th>
                                    <th>Hauptdienst</th>
                                    <th>Ersatz</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($slots as $slot): 
                                    $main = $assignments[$slot['slot_key']]['main'] ?? null;
                                    $backup = $assignments[$slot['slot_key']]['backup'] ?? null;
                                ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($slot['label']); ?></strong></td>
                                        <td>
                                            <select name="main[<?php echo $slot['slot_key']; ?>]">
                                                <option value="">-- Frei --</option>
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?php echo $user->ID; ?>" <?php selected($main, $user->ID); ?>>
                                                        <?php echo esc_html($user->display_name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="backup[<?php echo $slot['slot_key']; ?>]">
                                                <option value="">-- Kein Ersatz --</option>
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?php echo $user->ID; ?>" <?php selected($backup, $user->ID); ?>>
                                                        <?php echo esc_html($user->display_name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary">Zuweisungen speichern</button>
                        </p>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    public function handle_assign() {
        check_admin_referer('liturgus_assign', 'liturgus_nonce');
        
        if (!current_user_can('liturgus_assign_others')) {
            wp_die('Keine Berechtigung');
        }
        
        $messe_id = intval($_POST['messe_id']);
        $main = $_POST['main'] ?? [];
        $backup = $_POST['backup'] ?? [];
        
        global $wpdb;
        $table = $wpdb->prefix . 'liturgus_assignments';
        
        // Alle bisherigen löschen
        $wpdb->delete($table, ['messe_id' => $messe_id]);
        
        // Neue speichern
        foreach ($main as $slot_key => $user_id) {
            if ($user_id) {
                $wpdb->insert($table, [
                    'messe_id' => $messe_id,
                    'slot_key' => sanitize_text_field($slot_key),
                    'user_id' => intval($user_id),
                    'is_backup' => 0,
                    'position' => 1,
                    'status' => 'assigned',
                    'created_at' => current_time('mysql')
                ]);
                
                // Email senden
                self::send_assignment_email($user_id, $messe_id, $slot_key, false);
            }
        }
        
        foreach ($backup as $slot_key => $user_id) {
            if ($user_id) {
                $wpdb->insert($table, [
                    'messe_id' => $messe_id,
                    'slot_key' => sanitize_text_field($slot_key),
                    'user_id' => intval($user_id),
                    'is_backup' => 1,
                    'position' => 1,
                    'status' => 'assigned',
                    'created_at' => current_time('mysql')
                ]);
                
                // Email senden
                self::send_assignment_email($user_id, $messe_id, $slot_key, true);
            }
        }
        
        wp_redirect(admin_url('admin.php?page=liturgus-admin&saved=1'));
        exit;
    }
    
    /**
     * Email bei Zuweisung
     */
    private static function send_assignment_email($user_id, $messe_id, $slot_key, $is_backup) {
        $user = get_userdata($user_id);
        $messe = get_post($messe_id);
        $date = get_post_meta($messe_id, '_is_date', true);
        $time = get_post_meta($messe_id, '_is_time', true);
        $slot = Liturgus_Slots::get($slot_key);
        
        $subject = 'Dienst zugewiesen: ' . $slot['label'];
        
        $body = "Liebe/r " . $user->display_name . ",\n\n";
        $body .= "Du wurdest für einen Dienst eingeteilt:\n\n";
        $body .= "Messe: " . $messe->post_title . "\n";
        $body .= "Datum: " . date('d.m.Y', strtotime($date)) . " um " . $time . "\n";
        $body .= "Dienst: " . $slot['label'];
        
        if ($is_backup) {
            $body .= " (ERSATZDIENST)\n\n";
            $body .= "Du bist als Ersatz eingeteilt. Bitte sei bereit einzuspringen, falls der Hauptdienst ausfällt.\n";
        } else {
            $body .= "\n";
        }
        
        $body .= "\nBei Verhinderung bitte rechtzeitig im Dashboard austragen oder Tausch anfragen.\n";
        $body .= "Dashboard: " . liturgus_get_dashboard_url() . "\n";
        
        wp_mail($user->user_email, $subject, $body);
    }
}
