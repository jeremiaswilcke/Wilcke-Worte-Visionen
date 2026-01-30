<?php
/**
 * Database Setup
 */
class Liturgus_Database {
    
    public static function activate() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Tabelle 1: Dienst-Slots
        $sql1 = "CREATE TABLE {$wpdb->prefix}liturgus_slots (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slot_key VARCHAR(50) NOT NULL UNIQUE,
            label VARCHAR(100) NOT NULL,
            positions INT NOT NULL DEFAULT 1,
            backup_positions INT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1
        ) $charset;";
        
        // Tabelle 2: Zuweisungen
        $sql2 = "CREATE TABLE {$wpdb->prefix}liturgus_assignments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            messe_id BIGINT UNSIGNED NOT NULL,
            slot_key VARCHAR(50) NOT NULL,
            position INT NOT NULL,
            is_backup TINYINT(1) NOT NULL DEFAULT 0,
            user_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'assigned',
            created_at DATETIME NOT NULL,
            UNIQUE KEY unique_assignment (messe_id, slot_key, position, is_backup),
            KEY messe_id (messe_id),
            KEY user_id (user_id),
            KEY slot_key (slot_key)
        ) $charset;";
        
        // Tabelle 3: Tausch-Anfragen
        $sql3 = "CREATE TABLE {$wpdb->prefix}liturgus_swaps (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            from_user_id BIGINT UNSIGNED NOT NULL,
            to_user_id BIGINT UNSIGNED,
            from_assignment_id INT UNSIGNED NOT NULL,
            to_assignment_id INT UNSIGNED,
            message TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            KEY from_user (from_user_id),
            KEY to_user (to_user_id)
        ) $charset;";
        
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        
        // Default Slots einfügen
        self::insert_default_slots();
        
        // Capabilities
        self::add_capabilities();
    }
    
    private static function insert_default_slots() {
        global $wpdb;
        $table = $wpdb->prefix . 'liturgus_slots';
        
        $slots = [
            ['technik', 'Technik', 1, 1, 10],
            ['orgel', 'Orgel', 1, 1, 20],
            ['kantor', 'Kantor', 1, 1, 30],
            ['lektor_1', 'Lektor 1', 1, 1, 40],
            ['lektor_2', 'Lektor 2', 1, 1, 50],
            ['kommunionhelfer', 'Kommunionhelfer', 2, 2, 60],
            ['ministranten', 'Ministranten', 6, 2, 70],
            ['diakon_evangelium', 'Diakon (Evangelium)', 1, 1, 80],
            ['diakon_predigt', 'Diakon (Predigt)', 1, 1, 90],
            ['priester', 'Priester', 1, 1, 100]
        ];
        
        foreach ($slots as $slot) {
            $wpdb->insert($table, [
                'slot_key' => $slot[0],
                'label' => $slot[1],
                'positions' => $slot[2],
                'backup_positions' => $slot[3],
                'sort_order' => $slot[4],
                'active' => 1
            ]);
        }
    }
    
    private static function add_capabilities() {
        // Admin kann alles
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('liturgus_manage');
            $admin->add_cap('liturgus_signup');
            $admin->add_cap('liturgus_assign_others');
        }
        
        // Isidor-Rollen: Alle können sich anmelden
        $roles = ['isidor_pfarrer', 'isidor_diakon', 'isidor_sekretaer', 'isidor_lektor', 'isidor_kommunion', 'isidor_ministrant', 'isidor_orgel', 'isidor_technik'];
        
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                $role->add_cap('liturgus_signup');
                
                // Pfarrer, Diakon, Sekretär können auch andere zuweisen
                if (in_array($role_name, ['isidor_pfarrer', 'isidor_diakon', 'isidor_sekretaer'])) {
                    $role->add_cap('liturgus_assign_others');
                }
            }
        }
        
        // Editor kann sich anmelden
        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap('liturgus_signup');
        }
    }
    
    public static function deactivate() {
        // Cron Events entfernen
        wp_clear_scheduled_hook('liturgus_weekly_reminder');
        wp_clear_scheduled_hook('liturgus_evening_reminder');
    }
}
