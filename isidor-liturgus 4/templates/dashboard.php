<?php
/**
 * Dashboard Template
 */
if (!defined('ABSPATH')) exit;

$user_id = get_current_user_id();
$today = date('Y-m-d');
$end = date('Y-m-d', strtotime('+3 weeks'));

// Meine Dienste
$my_assignments = Liturgus_Assignments::get_for_user($user_id, $today, $end);

// Freie Dienste MIT NAMEN
$vacant = Liturgus_Assignments::get_vacant_with_assignments($today, $end);
?>

<div class="liturgus-dashboard">
    
    <!-- Meine Dienste (OBEN) -->
    <div class="liturgus-section">
        <div class="liturgus-section-header">
            <h2>Meine Dienste</h2>
            <?php if (!empty($my_assignments)): ?>
                <a href="<?php echo add_query_arg(['liturgus_export' => 'ical']); ?>" class="liturgus-btn liturgus-btn-sm">📅 iCal Export</a>
            <?php endif; ?>
        </div>
        
        <?php if (empty($my_assignments)): ?>
            <p>Keine Dienste im gewählten Zeitraum.</p>
        <?php else: ?>
            <table class="liturgus-table">
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Zeit</th>
                        <th>Messe</th>
                        <th>Dienst</th>
                        <th>Pos.</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_assignments as $a): 
                        $slot = Liturgus_Slots::get($a['slot_key']);
                    ?>
                        <tr>
                            <td><?php echo date('d.m.Y', strtotime($a['messe_date'])); ?></td>
                            <td><?php echo $a['messe_time']; ?></td>
                            <td><?php echo esc_html($a['messe_title']); ?></td>
                            <td>
                                <?php echo esc_html($slot['label']); ?>
                                <?php if ($a['is_backup']): ?>
                                    <span class="liturgus-badge liturgus-badge-backup">Backup</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $a['position']; ?></td>
                            <td>
                                <button onclick="liturgusOpenSwap(<?php echo $a['id']; ?>, '<?php echo esc_js($slot['label']); ?>', '<?php echo date('D d.m.Y', strtotime($a['messe_date'])); ?> <?php echo $a['messe_time']; ?>', '<?php echo esc_js($a['messe_title']); ?>')" class="liturgus-btn liturgus-btn-sm">↔️ Tauschen</button>
                                <button onclick="liturgusCancel(<?php echo $a['id']; ?>)" class="liturgus-btn liturgus-btn-sm liturgus-btn-danger">Austragen</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Freie Dienste (UNTEN) -->
    <div class="liturgus-section">
        <h2>Freie Dienste (nächste 3 Wochen)</h2>
        
        <?php if (empty($vacant)): ?>
            <p>Keine freien Dienste</p>
        <?php else: ?>
            <div class="liturgus-grid">
                <?php 
                $grouped = [];
                foreach ($vacant as $v) {
                    $grouped[$v['messe_id']]['info'] = [
                        'title' => $v['messe_title'],
                        'date' => $v['messe_date'],
                        'time' => $v['messe_time']
                    ];
                    $grouped[$v['messe_id']]['slots'][] = $v;
                }
                
                foreach ($grouped as $messe_id => $data): 
                ?>
                    <div class="liturgus-card">
                        <div class="liturgus-card-header">
                            <strong><?php echo date('D d.m', strtotime($data['info']['date'])); ?></strong>
                            <span><?php echo $data['info']['time']; ?></span>
                        </div>
                        <div class="liturgus-card-body">
                            <?php foreach ($data['slots'] as $slot): ?>
                                <div class="liturgus-slot">
                                    <div class="liturgus-slot-info">
                                        <strong><?php echo esc_html($slot['slot_label']); ?></strong>
                                        
                                        <?php if (!empty($slot['assigned_main'])): ?>
                                            <div class="liturgus-names">
                                                <?php foreach ($slot['assigned_main'] as $name): ?>
                                                    <span class="liturgus-name"><?php echo esc_html($name); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($slot['assigned_backup'])): ?>
                                            <div class="liturgus-names liturgus-names-backup">
                                                <?php foreach ($slot['assigned_backup'] as $name): ?>
                                                    <span class="liturgus-name liturgus-name-backup">🔄 <?php echo esc_html($name); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="liturgus-actions">
                                        <?php if ($slot['vacant_main'] > 0): ?>
                                            <button onclick="liturgusSignup(<?php echo $messe_id; ?>, '<?php echo esc_js($slot['slot_key']); ?>', false)" class="liturgus-btn liturgus-btn-sm" title="<?php echo $slot['vacant_main']; ?> freie Position(en)">✓</button>
                                        <?php endif; ?>
                                        <?php if ($slot['vacant_backup'] > 0): ?>
                                            <button onclick="liturgusSignup(<?php echo $messe_id; ?>, '<?php echo esc_js($slot['slot_key']); ?>', true)" class="liturgus-btn liturgus-btn-sm liturgus-btn-backup" title="<?php echo $slot['vacant_backup']; ?> freie Backup-Position(en)">B</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
</div>

<!-- Tausch-Modal -->
<div id="liturgus-swap-modal" class="liturgus-modal" style="display:none;">
    <div class="liturgus-modal-content">
        <div class="liturgus-modal-header">
            <h3>Dienst tauschen</h3>
            <span class="liturgus-modal-close" onclick="liturgusCloseSwap()">&times;</span>
        </div>
        <div class="liturgus-modal-body">
            <div style="background: #f0f0f0; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <strong>Dein Dienst:</strong>
                <p id="liturgus-swap-my-service" style="margin: 5px 0 0 0; font-size: 14px;"></p>
            </div>
            
            <label>
                <strong>Tauschen mit welchem Dienst?</strong>
                <select id="liturgus-swap-target" class="liturgus-input" style="font-size: 14px;">
                    <option value="">Dienst auswählen...</option>
                    <?php
                    // Alle Dienste anderer User im Zeitraum
                    global $wpdb;
                    $all_assignments = $wpdb->get_results($wpdb->prepare(
                        "SELECT a.id, a.slot_key, a.is_backup, a.user_id, 
                                p.post_title as messe_title, 
                                pm1.meta_value as messe_date, 
                                pm2.meta_value as messe_time,
                                u.display_name as user_name
                        FROM {$wpdb->prefix}liturgus_assignments a
                        JOIN {$wpdb->posts} p ON a.messe_id = p.ID
                        LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_is_date'
                        LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_is_time'
                        JOIN {$wpdb->users} u ON a.user_id = u.ID
                        WHERE a.status = 'assigned' 
                        AND a.user_id != %d
                        AND pm1.meta_value BETWEEN %s AND %s
                        ORDER BY pm1.meta_value, pm2.meta_value",
                        $user_id, $today, $end
                    ), ARRAY_A);
                    
                    foreach ($all_assignments as $assign) {
                        $slot = Liturgus_Slots::get($assign['slot_key']);
                        $date_str = date('D d.m.Y', strtotime($assign['messe_date']));
                        $backup_str = $assign['is_backup'] ? ' (Ersatz)' : '';
                        
                        echo '<option value="' . $assign['id'] . '">';
                        echo esc_html($assign['user_name']) . ': ';
                        echo esc_html($slot['label']) . $backup_str . ' | ';
                        echo $date_str . ' ' . esc_html($assign['messe_time']) . ' | ';
                        echo esc_html($assign['messe_title']);
                        echo '</option>';
                    }
                    ?>
                </select>
            </label>
            
            <div style="background: #fff3cd; padding: 10px; border-radius: 5px; margin-top: 15px; font-size: 13px;">
                ℹ️ <strong>Hinweis:</strong> Beide Dienste werden getauscht. Du übernimmst deren Dienst, sie übernehmen deinen.
            </div>
            
            <label style="margin-top: 15px;">
                <strong>Nachricht (optional):</strong>
                <textarea id="liturgus-swap-message" class="liturgus-input" rows="3" placeholder="z.B. Ich kann an diesem Tag leider nicht..."></textarea>
            </label>
            
            <input type="hidden" id="liturgus-swap-my-assignment-id">
        </div>
        <div class="liturgus-modal-footer">
            <button onclick="liturgusCloseSwap()" class="liturgus-btn liturgus-btn-secondary">Abbrechen</button>
            <button onclick="liturgusSubmitSwap()" class="liturgus-btn">Tausch-Anfrage senden</button>
        </div>
    </div>
</div>
