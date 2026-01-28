<?php
/**
 * Liturgus Reminders - Cron System
 */
class Liturgus_Reminders {
    
    public function __construct() {
        // Cron Hooks registrieren
        add_action('liturgus_weekly_reminder', [$this, 'send_weekly_reminder']);
        add_action('liturgus_evening_reminder', [$this, 'send_evening_reminder']);
        
        // Schedules aktivieren
        if (!wp_next_scheduled('liturgus_weekly_reminder')) {
            // Jeden Montag um 9:00 Uhr
            wp_schedule_event(strtotime('next Monday 09:00'), 'weekly', 'liturgus_weekly_reminder');
        }
        
        if (!wp_next_scheduled('liturgus_evening_reminder')) {
            // Täglich um 18:00 Uhr
            wp_schedule_event(strtotime('today 18:00'), 'daily', 'liturgus_evening_reminder');
        }
    }
    
    /**
     * 1 Woche vorher: Unbesetzte Dienste
     */
    public function send_weekly_reminder() {
        $from = date('Y-m-d', strtotime('+7 days'));
        $to = date('Y-m-d', strtotime('+14 days'));
        
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
        
        $vacant = [];
        
        foreach ($messen as $messe) {
            $slots = Liturgus_Slots::get_all();
            $assignments = Liturgus_Assignments::get_for_messe($messe->ID);
            
            $assigned_slots = array_column($assignments, 'slot_key');
            
            foreach ($slots as $slot) {
                if (!in_array($slot['slot_key'], $assigned_slots)) {
                    $vacant[] = [
                        'messe' => $messe,
                        'slot' => $slot
                    ];
                }
            }
        }
        
        if (empty($vacant)) {
            return; // Alles besetzt!
        }
        
        // Email an alle mit liturgus_signup
        $users = get_users(['capability' => 'liturgus_signup']);
        
        foreach ($users as $user) {
            $this->send_weekly_email($user, $vacant);
        }
    }
    
    /**
     * Abend vorher (18:00): Erinnerung an morgen
     */
    public function send_evening_reminder() {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        $messen = get_posts([
            'post_type' => 'isidor_messe',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [[
                'key' => '_is_date',
                'value' => $tomorrow,
                'compare' => '=',
                'type' => 'DATE'
            ]]
        ]);
        
        foreach ($messen as $messe) {
            $assignments = Liturgus_Assignments::get_for_messe($messe->ID);
            
            // Gruppiere nach User
            $by_user = [];
            foreach ($assignments as $a) {
                if (!isset($by_user[$a['user_id']])) {
                    $by_user[$a['user_id']] = [];
                }
                $by_user[$a['user_id']][] = $a;
            }
            
            // Email an jeden User
            foreach ($by_user as $user_id => $user_assignments) {
                $this->send_evening_email($user_id, $messe, $user_assignments);
            }
        }
    }
    
    /**
     * Weekly Email
     */
    private function send_weekly_email($user, $vacant) {
        $subject = 'Offene Dienste - Bitte melden!';
        
        $body = "Liebe/r " . $user->display_name . ",\n\n";
        $body .= "folgende Dienste sind noch unbesetzt:\n\n";
        
        $current_messe = null;
        foreach ($vacant as $v) {
            if ($current_messe != $v['messe']->ID) {
                $current_messe = $v['messe']->ID;
                $date = get_post_meta($v['messe']->ID, '_is_date', true);
                $time = get_post_meta($v['messe']->ID, '_is_time', true);
                
                $body .= "\n━━━━━━━━━━━━━━━━━━━━━━━━\n";
                $body .= date('D d.m.Y', strtotime($date)) . " | " . $time . "\n";
                $body .= $v['messe']->post_title . "\n";
                $body .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
            }
            
            $body .= "- " . $v['slot']['label'] . "\n";
        }
        
        $body .= "\n\nBitte melde dich an: " . home_url('/dienste/') . "\n";
        $body .= "\nVielen Dank!\n";
        
        wp_mail($user->user_email, $subject, $body);
    }
    
    /**
     * Evening Email
     */
    private function send_evening_email($user_id, $messe, $assignments) {
        $user = get_userdata($user_id);
        $date = get_post_meta($messe->ID, '_is_date', true);
        $time = get_post_meta($messe->ID, '_is_time', true);
        
        $subject = 'Erinnerung: Dein Dienst morgen';
        
        $body = "Liebe/r " . $user->display_name . ",\n\n";
        $body .= "Erinnerung an deinen Dienst MORGEN:\n\n";
        $body .= "Messe: " . $messe->post_title . "\n";
        $body .= "Datum: " . date('d.m.Y', strtotime($date)) . " um " . $time . "\n\n";
        $body .= "Deine Dienste:\n";
        
        foreach ($assignments as $a) {
            $slot = Liturgus_Slots::get($a['slot_key']);
            $body .= "- " . $slot['label'];
            if ($a['is_backup']) {
                $body .= " (ERSATZ)";
            }
            $body .= "\n";
        }
        
        $body .= "\nBei Verhinderung bitte SOFORT melden!\n";
        $body .= "\nViel Freude beim Dienst!\n";
        
        wp_mail($user->user_email, $subject, $body);
    }
}
