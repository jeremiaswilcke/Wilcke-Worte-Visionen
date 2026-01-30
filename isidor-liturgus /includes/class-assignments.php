<?php
/**
 * Assignments Management
 */
class Liturgus_Assignments {
    
    public function __construct() {
        add_action('wp_ajax_liturgus_signup', [$this, 'ajax_signup']);
        add_action('wp_ajax_liturgus_cancel', [$this, 'ajax_cancel']);
        add_action('wp_ajax_liturgus_swap_request', [$this, 'ajax_swap_request']);
        add_action('init', [$this, 'handle_ical_export']);
        add_action('init', [$this, 'handle_swap_from_email']);
    }
    
    /**
     * Tausch aus Email annehmen
     */
    public function handle_swap_from_email() {
        if (!isset($_GET['liturgus_swap']) || $_GET['liturgus_swap'] !== 'accept') {
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_die('Bitte einloggen um Tausch anzunehmen.');
        }
        
        $swap_id = intval($_GET['swap_id']);
        $token = sanitize_text_field($_GET['token']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        
        // Swap holen
        $swap = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}liturgus_swaps WHERE id = %d AND status = 'pending'",
            $swap_id
        ), ARRAY_A);
        
        if (!$swap) {
            wp_die('Tausch-Anfrage nicht gefunden oder bereits bearbeitet.');
        }
        
        // Token prüfen (mit beiden assignment IDs)
        $expected_token = wp_hash($swap['id'] . $swap['to_user_id'] . $swap['from_assignment_id'] . $swap['to_assignment_id']);
        if ($token !== $expected_token) {
            wp_die('Ungültiger Token.');
        }
        
        // User prüfen
        if ($swap['to_user_id'] != $user_id) {
            wp_die('Dieser Tausch ist nicht für dich bestimmt.');
        }
        
        // TAUSCH DURCHFÜHREN
        $assignment_table = $wpdb->prefix . 'liturgus_assignments';
        
        // User IDs zwischenspeichern
        $user_a = $swap['from_user_id']; // Anfragender
        $user_b = $swap['to_user_id'];   // Annehmender
        
        // Assignment A → User B
        $wpdb->update(
            $assignment_table,
            ['user_id' => $user_b],
            ['id' => $swap['from_assignment_id']]
        );
        
        // Assignment B → User A
        $wpdb->update(
            $assignment_table,
            ['user_id' => $user_a],
            ['id' => $swap['to_assignment_id']]
        );
        
        // Swap als accepted markieren
        $wpdb->update(
            $wpdb->prefix . 'liturgus_swaps',
            ['status' => 'accepted', 'processed_at' => current_time('mysql')],
            ['id' => $swap_id]
        );
        
        // Emails senden
        $from_user = get_userdata($swap['from_user_id']);
        $to_user = get_userdata($swap['to_user_id']);
        
        // Email an Anfragenden
        wp_mail(
            $from_user->user_email,
            'Tausch angenommen!',
            "Hallo " . $from_user->display_name . ",\n\n" .
            $to_user->display_name . " hat deinen Tausch angenommen!\n" .
            "Du bist nun vom Dienst ausgetragen.\n\n" .
            "Dashboard: " . liturgus_get_dashboard_url()
        );
        
        // Redirect mit Erfolg
        wp_redirect(add_query_arg('swap_accepted', '1', liturgus_get_dashboard_url()));
        exit;
    }
    
    /**
     * iCal Export
     */
    public function handle_ical_export() {
        if (!isset($_GET['liturgus_export']) || $_GET['liturgus_export'] != 'ical') {
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_die('Nicht eingeloggt');
        }
        
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        $from = date('Y-m-d');
        $to = date('Y-m-d', strtotime('+6 months'));
        
        $assignments = self::get_for_user($user_id, $from, $to);
        
        // iCal Header
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="meine-dienste.ics"');
        
        echo "BEGIN:VCALENDAR\r\n";
        echo "VERSION:2.0\r\n";
        echo "PRODID:-//Isidor Liturgus//DE\r\n";
        echo "CALSCALE:GREGORIAN\r\n";
        echo "METHOD:PUBLISH\r\n";
        echo "X-WR-CALNAME:Meine Dienste\r\n";
        echo "X-WR-TIMEZONE:Europe/Vienna\r\n";
        
        foreach ($assignments as $a) {
            $slot = Liturgus_Slots::get($a['slot_key']);
            $datetime = $a['messe_date'] . ' ' . $a['messe_time'];
            $start = date('Ymd\THis', strtotime($datetime));
            $end = date('Ymd\THis', strtotime($datetime . ' +1 hour'));
            
            $summary = $slot['label'];
            if ($a['is_backup']) {
                $summary .= ' (Backup)';
            }
            $summary .= ' - ' . $a['messe_title'];
            
            echo "BEGIN:VEVENT\r\n";
            echo "UID:" . md5($a['id'] . $user_id) . "@liturgus\r\n";
            echo "DTSTAMP:" . date('Ymd\THis\Z') . "\r\n";
            echo "DTSTART:" . $start . "\r\n";
            echo "DTEND:" . $end . "\r\n";
            echo "SUMMARY:" . $summary . "\r\n";
            echo "DESCRIPTION:Dienst: " . $slot['label'] . "\\nPosition: " . $a['position'] . "\r\n";
            echo "LOCATION:Pfarre Mariabrunn\r\n";
            echo "STATUS:CONFIRMED\r\n";
            echo "END:VEVENT\r\n";
        }
        
        echo "END:VCALENDAR\r\n";
        exit;
    }
    
    /**
     * AJAX: Tausch-Anfrage
     */
    public function ajax_swap_request() {
        check_ajax_referer('liturgus_nonce', 'nonce');
        
        if (!current_user_can('liturgus_signup')) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }
        
        $my_assignment_id = intval($_POST['my_assignment_id']);
        $their_assignment_id = intval($_POST['their_assignment_id']);
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        $result = self::create_swap_request($my_assignment_id, $their_assignment_id, $message);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => 'Tausch-Anfrage gesendet!']);
    }
    
    /**
     * AJAX: Eintragen
     */
    public function ajax_signup() {
        check_ajax_referer('liturgus_nonce', 'nonce');
        
        if (!current_user_can('liturgus_signup')) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }
        
        $messe_id = intval($_POST['messe_id']);
        $slot_key = sanitize_text_field($_POST['slot_key']);
        $is_backup = !empty($_POST['is_backup']);
        
        $result = self::signup($messe_id, $slot_key, $is_backup);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => 'Erfolgreich eingetragen!']);
    }
    
    /**
     * Eintragen (Hauptlogik)
     */
    public static function signup($messe_id, $slot_key, $is_backup = false, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Slot prüfen
        $slot = Liturgus_Slots::get($slot_key);
        if (!$slot) {
            return new WP_Error('invalid_slot', 'Ungültiger Dienst');
        }
        
        $table = $wpdb->prefix . 'liturgus_assignments';
        
        // Bereits eingetragen?
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE messe_id = %d AND slot_key = %s AND user_id = %d AND status = 'assigned'",
            $messe_id, $slot_key, $user_id
        ));
        
        if ($exists) {
            return new WP_Error('already_signed', 'Bereits eingetragen');
        }
        
        // Nächste freie Position finden
        $max_pos = $is_backup ? $slot['backup_positions'] : $slot['positions'];
        
        if ($max_pos <= 0) {
            return new WP_Error('no_positions', 'Keine Positionen verfügbar');
        }
        
        // Alle Positionen durchgehen
        for ($pos = 1; $pos <= $max_pos; $pos++) {
            $taken = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE messe_id = %d AND slot_key = %s AND position = %d AND is_backup = %d AND status = 'assigned'",
                $messe_id, $slot_key, $pos, $is_backup
            ));
            
            if (!$taken) {
                // Position ist frei!
                $inserted = $wpdb->insert($table, [
                    'messe_id' => $messe_id,
                    'slot_key' => $slot_key,
                    'position' => $pos,
                    'is_backup' => $is_backup ? 1 : 0,
                    'user_id' => $user_id,
                    'status' => 'assigned',
                    'created_at' => current_time('mysql')
                ]);
                
                if ($inserted) {
                    // Email versenden
                    self::send_signup_email($user_id, $messe_id, $slot_key, $is_backup);
                    return true;
                }
            }
        }
        
        return new WP_Error('all_full', 'Alle Positionen besetzt');
    }
    
    /**
     * AJAX: Austragen
     */
    public function ajax_cancel() {
        check_ajax_referer('liturgus_nonce', 'nonce');
        
        if (!current_user_can('liturgus_signup')) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }
        
        $assignment_id = intval($_POST['assignment_id']);
        
        $result = self::cancel($assignment_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => 'Erfolgreich ausgetragen!']);
    }
    
    /**
     * Austragen
     */
    public static function cancel($assignment_id, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $table = $wpdb->prefix . 'liturgus_assignments';
        
        // Prüfen ob User berechtigt
        $assignment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $assignment_id
        ), ARRAY_A);
        
        if (!$assignment) {
            return new WP_Error('not_found', 'Zuweisung nicht gefunden');
        }
        
        if ($assignment['user_id'] != $user_id && !current_user_can('liturgus_manage')) {
            return new WP_Error('no_permission', 'Keine Berechtigung');
        }
        
        // Löschen
        $deleted = $wpdb->delete($table, ['id' => $assignment_id]);
        
        if (!$deleted) {
            return new WP_Error('delete_failed', 'Löschen fehlgeschlagen');
        }
        
        // BACKUP NACHRÜCKEN wenn Hauptdienst gekündigt
        if ($assignment['is_backup'] == 0) {
            // Gibt es einen Backup?
            $backup = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE messe_id = %d AND slot_key = %s AND is_backup = 1 AND status = 'assigned' ORDER BY position LIMIT 1",
                $assignment['messe_id'],
                $assignment['slot_key']
            ), ARRAY_A);
            
            if ($backup) {
                // Backup zum Hauptdienst machen
                $wpdb->update(
                    $table,
                    ['is_backup' => 0],
                    ['id' => $backup['id']]
                );
                
                // Email an Backup: Du bist jetzt Hauptdienst
                self::send_backup_promoted_email($backup);
            }
        }
        
        // Email versenden
        self::send_cancel_email($assignment);
        
        return true;
    }
    
    /**
     * Erstelle Tausch-Anfrage
     */
    public static function create_swap_request($my_assignment_id, $their_assignment_id, $message = '') {
        global $wpdb;
        
        $from_user_id = get_current_user_id();
        
        // Mein Assignment prüfen
        $my_assignment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}liturgus_assignments WHERE id = %d AND user_id = %d",
            $my_assignment_id, $from_user_id
        ), ARRAY_A);
        
        if (!$my_assignment) {
            return new WP_Error('invalid', 'Dein Dienst nicht gefunden');
        }
        
        // Deren Assignment prüfen
        $their_assignment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}liturgus_assignments WHERE id = %d",
            $their_assignment_id
        ), ARRAY_A);
        
        if (!$their_assignment) {
            return new WP_Error('invalid', 'Ziel-Dienst nicht gefunden');
        }
        
        $to_user_id = $their_assignment['user_id'];
        
        // Tausch-Anfrage speichern
        $table = $wpdb->prefix . 'liturgus_swaps';
        $inserted = $wpdb->insert($table, [
            'from_user_id' => $from_user_id,
            'to_user_id' => $to_user_id,
            'from_assignment_id' => $my_assignment_id,
            'to_assignment_id' => $their_assignment_id,
            'message' => $message,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ]);
        
        if (!$inserted) {
            return new WP_Error('db_error', 'Fehler beim Speichern');
        }
        
        // Email an Ziel-User
        self::send_swap_email($my_assignment_id, $their_assignment_id, $message);
        
        return true;
    }
    
    /**
     * Email bei Tausch-Anfrage
     */
    private static function send_swap_email($my_assignment_id, $their_assignment_id, $message) {
        global $wpdb;
        
        // Mein Dienst (von Anfragenden)
        $my = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, p.post_title as messe_title, pm1.meta_value as messe_date, pm2.meta_value as messe_time
            FROM {$wpdb->prefix}liturgus_assignments a
            JOIN {$wpdb->posts} p ON a.messe_id = p.ID
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_is_date'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_is_time'
            WHERE a.id = %d",
            $my_assignment_id
        ), ARRAY_A);
        
        // Deren Dienst (von Ziel-Person)
        $their = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, p.post_title as messe_title, pm1.meta_value as messe_date, pm2.meta_value as messe_time
            FROM {$wpdb->prefix}liturgus_assignments a
            JOIN {$wpdb->posts} p ON a.messe_id = p.ID
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_is_date'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_is_time'
            WHERE a.id = %d",
            $their_assignment_id
        ), ARRAY_A);
        
        $from_user = get_userdata($my['user_id']);
        $to_user = get_userdata($their['user_id']);
        
        if (!$to_user || !$to_user->user_email) {
            return;
        }
        
        $my_slot = Liturgus_Slots::get($my['slot_key']);
        $their_slot = Liturgus_Slots::get($their['slot_key']);
        
        $subject = 'Tausch-Anfrage von ' . $from_user->display_name;
        
        $body = "Hallo " . $to_user->display_name . ",\n\n";
        $body .= $from_user->display_name . " möchte mit dir tauschen:\n\n";
        
        $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $body .= "DU GIBST AB:\n";
        $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $body .= "Dienst: " . $their_slot['label'] . "\n";
        $body .= "Datum: " . date('D d.m.Y', strtotime($their['messe_date'])) . " um " . $their['messe_time'] . "\n";
        $body .= "Messe: " . $their['messe_title'] . "\n\n";
        
        $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $body .= "DU BEKOMMST:\n";
        $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $body .= "Dienst: " . $my_slot['label'] . "\n";
        $body .= "Datum: " . date('D d.m.Y', strtotime($my['messe_date'])) . " um " . $my['messe_time'] . "\n";
        $body .= "Messe: " . $my['messe_title'] . "\n\n";
        
        if ($message) {
            $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $body .= "NACHRICHT:\n";
            $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $body .= $message . "\n\n";
        }
        
        // Swap-ID holen
        $swap = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}liturgus_swaps WHERE from_assignment_id = %d AND to_assignment_id = %d AND status = 'pending' ORDER BY created_at DESC LIMIT 1",
            $my_assignment_id, $their_assignment_id
        ), ARRAY_A);
        
        if ($swap) {
            // Token erstellen
            $token = wp_hash($swap['id'] . $to_user->ID . $my_assignment_id . $their_assignment_id);
            
            // Annahme-Link
            $accept_url = add_query_arg([
                'liturgus_swap' => 'accept',
                'swap_id' => $swap['id'],
                'token' => $token
            ], liturgus_get_dashboard_url());
            
            $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $body .= "TAUSCH ANNEHMEN:\n";
            $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $body .= $accept_url . "\n\n";
        }
        
        $body .= "Oder kontaktiere " . $from_user->display_name . " direkt:\n";
        $body .= "E-Mail: " . $from_user->user_email . "\n";
        
        if ($from_user->user_phone = get_user_meta($from_user_id, 'phone', true)) {
            $body .= "Telefon: " . $from_user->user_phone . "\n";
        }
        
        wp_mail($to_user->user_email, $subject, $body);
    }
    
    /**
     * Email bei Eintragen
     */
    private static function send_signup_email($user_id, $messe_id, $slot_key, $is_backup) {
        global $wpdb;
        
        $user = get_userdata($user_id);
        if (!$user || !$user->user_email) {
            return;
        }
        
        $messe = get_post($messe_id);
        $slot = Liturgus_Slots::get($slot_key);
        $date = get_post_meta($messe_id, '_is_date', true);
        $time = get_post_meta($messe_id, '_is_time', true);
        
        $subject = 'Dienst-Bestätigung: ' . $slot['label'];
        
        $body = "Hallo " . $user->display_name . ",\n\n";
        $body .= "Du hast dich erfolgreich für folgenden Dienst eingetragen:\n\n";
        $body .= "Dienst: " . $slot['label'];
        if ($is_backup) {
            $body .= " (Backup)";
        }
        $body .= "\n";
        $body .= "Datum: " . date('d.m.Y', strtotime($date)) . "\n";
        $body .= "Zeit: " . $time . "\n";
        $body .= "Messe: " . $messe->post_title . "\n\n";
        $body .= "Vielen Dank für dein Engagement!\n\n";
        $body .= "Deine Dienste kannst du hier verwalten:\n";
        $body .= liturgus_get_dashboard_url() . "\n";
        
        wp_mail($user->user_email, $subject, $body);
    }
    
    /**
     * Email bei Austragen
     */
    private static function send_cancel_email($assignment) {
        $user = get_userdata($assignment['user_id']);
        if (!$user || !$user->user_email) {
            return;
        }
        
        $messe = get_post($assignment['messe_id']);
        $slot = Liturgus_Slots::get($assignment['slot_key']);
        $date = get_post_meta($assignment['messe_id'], '_is_date', true);
        $time = get_post_meta($assignment['messe_id'], '_is_time', true);
        
        $subject = 'Dienst-Austragung: ' . $slot['label'];
        
        $body = "Hallo " . $user->display_name . ",\n\n";
        $body .= "Du wurdest von folgendem Dienst ausgetragen:\n\n";
        $body .= "Dienst: " . $slot['label'] . "\n";
        $body .= "Datum: " . date('d.m.Y', strtotime($date)) . "\n";
        $body .= "Zeit: " . $time . "\n";
        $body .= "Messe: " . $messe->post_title . "\n\n";
        
        wp_mail($user->user_email, $subject, $body);
    }
    
    /**
     * Email: Backup wurde zum Hauptdienst
     */
    private static function send_backup_promoted_email($assignment) {
        $user = get_userdata($assignment['user_id']);
        if (!$user || !$user->user_email) {
            return;
        }
        
        $messe = get_post($assignment['messe_id']);
        $slot = Liturgus_Slots::get($assignment['slot_key']);
        $date = get_post_meta($assignment['messe_id'], '_is_date', true);
        $time = get_post_meta($assignment['messe_id'], '_is_time', true);
        
        $subject = 'Du rückst nach: ' . $slot['label'];
        
        $body = "Hallo " . $user->display_name . ",\n\n";
        $body .= "Der Hauptdienst hat sich ausgetragen. Du rückst vom Ersatzdienst zum HAUPTDIENST nach:\n\n";
        $body .= "Dienst: " . $slot['label'] . "\n";
        $body .= "Datum: " . date('d.m.Y', strtotime($date)) . " um " . $time . "\n";
        $body .= "Messe: " . $messe->post_title . "\n\n";
        $body .= "Bitte bestätige, dass du den Dienst übernehmen kannst!\n";
        $body .= "Dashboard: " . liturgus_get_dashboard_url() . "\n";
        
        wp_mail($user->user_email, $subject, $body);
    }
    
    /**
     * Hole Zuweisungen für Messe
     */
    public static function get_for_messe($messe_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}liturgus_assignments WHERE messe_id = %d AND status = 'assigned' ORDER BY slot_key, is_backup, position",
            $messe_id
        ), ARRAY_A);
    }
    
    /**
     * Hole Zuweisungen für User
     */
    public static function get_for_user($user_id, $from_date, $to_date) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, p.post_title as messe_title, pm1.meta_value as messe_date, pm2.meta_value as messe_time
            FROM {$wpdb->prefix}liturgus_assignments a
            JOIN {$wpdb->posts} p ON a.messe_id = p.ID
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_is_date'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_is_time'
            WHERE a.user_id = %d 
            AND a.status = 'assigned'
            AND pm1.meta_value BETWEEN %s AND %s
            ORDER BY pm1.meta_value, pm2.meta_value",
            $user_id, $from_date, $to_date
        ), ARRAY_A);
    }
    
    /**
     * Hole freie Slots MIT ZUWEISUNGEN
     */
    public static function get_vacant_with_assignments($from_date, $to_date) {
        global $wpdb;
        
        $messen = get_posts([
            'post_type' => 'isidor_messe',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_key' => '_is_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => [[
                'key' => '_is_date',
                'value' => [$from_date, $to_date],
                'compare' => 'BETWEEN',
                'type' => 'DATE'
            ]]
        ]);
        
        $result = [];
        $all_slots = Liturgus_Slots::get_all();
        
        foreach ($messen as $messe) {
            $assignments = self::get_for_messe($messe->ID);
            
            foreach ($all_slots as $slot) {
                // Sammle Zuweisungen für diesen Slot
                $assigned_main = [];
                $assigned_backup = [];
                
                foreach ($assignments as $a) {
                    if ($a['slot_key'] == $slot['slot_key']) {
                        $user = get_userdata($a['user_id']);
                        $name = $user ? $user->display_name : 'Unbekannt';
                        
                        if ($a['is_backup']) {
                            $assigned_backup[] = $name;
                        } else {
                            $assigned_main[] = $name;
                        }
                    }
                }
                
                // Berechne freie Positionen
                $vacant_main = $slot['positions'] - count($assigned_main);
                $vacant_backup = $slot['backup_positions'] - count($assigned_backup);
                
                $result[] = [
                    'messe_id' => $messe->ID,
                    'messe_title' => $messe->post_title,
                    'messe_date' => get_post_meta($messe->ID, '_is_date', true),
                    'messe_time' => get_post_meta($messe->ID, '_is_time', true),
                    'slot_key' => $slot['slot_key'],
                    'slot_label' => $slot['label'],
                    'assigned_main' => $assigned_main,
                    'assigned_backup' => $assigned_backup,
                    'vacant_main' => $vacant_main,
                    'vacant_backup' => $vacant_backup,
                    'total_main' => $slot['positions'],
                    'total_backup' => $slot['backup_positions']
                ];
            }
        }
        
        return $result;
    }
}
