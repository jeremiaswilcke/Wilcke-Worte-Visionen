<?php
/**
 * Slots Management
 */
class Liturgus_Slots {
    
    public function __construct() {
        // Nichts zu initialisieren - statische Methoden
    }
    
    /**
     * Hole alle aktiven Slots
     */
    public static function get_all() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}liturgus_slots WHERE active = 1 ORDER BY sort_order",
            ARRAY_A
        );
    }
    
    /**
     * Hole einen Slot nach Key
     */
    public static function get($slot_key) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}liturgus_slots WHERE slot_key = %s",
                $slot_key
            ),
            ARRAY_A
        );
    }
}
