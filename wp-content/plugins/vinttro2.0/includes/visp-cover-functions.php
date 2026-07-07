<?php


add_shortcode('cover_admin', function() {
    if ( ! is_user_logged_in() ) {
        return '<p>Please <a href="/login">log in</a> to view your dashboard.</p>';
    }

    $user_id = get_current_user_id();

    ob_start(); 
    ?>
    <div class="vinttro-dashboard vinttro-admin-dashboard-view">
        <div class="dashboard-grid">
            <?php echo vinttro_get_cover_admin_panel( $user_id ); ?>          
        </div>
    </div>
    <?php
    return ob_get_clean();
});
function vinttro_get_cover_admin_panel($user_id) {
    // Read from wp-config constant instead of $_ENV
    $crm_base_url = defined('SUITECRM_BASE_URL') ? SUITECRM_BASE_URL : 'https://uatcrm.vinttro.co.uk';
    
    $rfqs = get_user_meta($user_id, 'vinttro_cover_rfqs', true);
    if (empty($rfqs) || !is_array($rfqs)) return '';

    ob_start();
    ?>
    <style>
        .vehicle-issue-summary-line {
            transition: opacity 0.2s ease-in-out;
        }
        .vehicle-issue-summary-line:hover {
            opacity: 0.8;
        }
        /* Forces all columns to align with the top row text (the Registration) */
        .vehicle-main-row td {
            vertical-align: top !important;
            padding-top: 10px;
        }
    </style>
    <script>
        function vinttroToggleIssues(triggerElement) {
            var targetId = triggerElement.getAttribute('data-toggle-target');
            var targetRow = document.getElementById(targetId);
            
            if (targetRow) {
                if (targetRow.style.display === 'table-row') {
                    targetRow.style.display = 'none';
                } else {
                    targetRow.style.display = 'table-row';
                }
            }
        }
    </script>

        <div class="dashboard-panel fleet-container" style="margin-bottom: 30px;">
            <h3>✍️ Quotes: <?php echo esc_html($fleet['name'] ?? 'Unnamed'); ?></h3>
            <table class="fleet-table">
                <thead>
                    <tr>
                        <th>Quote Request</th>
                        <th>Personnel</th>
                        <th>Next MOT</th>
                        <th>Next Service</th>
                        <th>Last Check</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rfqs as $rfq) : 
                        $insurer_rfqs = $rfq['insurer_rfqs'] ?? [];
                        $has_insurer_rfqs = !empty($insurer_rfqs) && is_array($insurer_rfqs);
                        
                        $status_class = 'status-safe';
                        
                        // Define the strict 4-tier SuiteCRM severity configuration schema
                        $irfq_config = [
                            'new' => [
                                'color' => '#cfcad72c',
                                'desc'  => 'New no respone from insurer',
                                'count' => 0
                            ],
                            'rejected' => [
                                'color' => '#89170a',
                                'desc'  => 'Rejected (Insurer has rejected the quote)',
                                'count' => 0
                            ],
                            'quoted' => [
                                'color' => '#055a3f',
                                'desc'  => 'Quoted (Insurer has provided a quote)',

                                'count' => 0
                            ]
                        ];

                        if ($has_insurer_rfqs) {
                            $severities = array_map('strtolower', array_column($insurer_rfqs, 'severity'));
                            
                            // Map parent status dot colors
                            if (in_array('critical', $severities) || in_array('major', $severities) || in_array('high', $severities)) {
                                $status_class = 'status-critical';
                            } elseif (in_array('moderate', $severities) || in_array('medium', $severities)) {
                                $status_class = 'status-warning';
                            } else {
                                $status_class = 'status-info';
                            }

                            // Hydrate counts (with backwards-compatible fallback mapping)
                            foreach ($insurer_rfqs as $i_rfq) {
                                $status = strtolower($i_rfq['status'] ?? 'new');

                                if (isset($sev_config[$status])) {
                                    $sev_config[$status]['count']++;
                                }
                            }
                        }

                        // Normalized warning triangle SVG used uniformly for a cleaner aesthetic
                        $warning_triangle_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 2px;"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
                    ?>
                        <tr class="rfq-row" style="main-row <?php echo $has_insurer_rfqs ? 'has-issues' : 'no-issues'; ?>">
                            <td class="rfq-cell">
                                <?php 
                                $crm_id = $rfq['id'] ?? ''; 
                                $reg_text = esc_html($rfq['name'] ?? 'N/A');
                                $unique_row_id = 'issues-' . (!empty($crm_id) ? $crm_id : md5($reg_text));
                                
                                if (!empty($crm_id)) : ?>
                                    <a href="<?php echo esc_url(rtrim($crm_base_url, '/')) . '/#/visp_cover_rfq/record/' . esc_attr($crm_id); ?>" 
                                       target="_blank" 
                                       title="View in CRM: <?php echo esc_attr(($car['make'] ?? '') . ' ' . ($car['model'] ?? '')); ?>"
                                       style="text-decoration: none; color: inherit;">
                                        <span class="vehicle-reg"><?php echo $reg_text; ?></span>
                                    </a>
                                <?php else : ?>
                                    <span class="vehicle-reg" title="<?php echo esc_attr(($car['make'] ?? '') . ' ' . ($car['model'] ?? '')); ?>">
                                        <?php echo $reg_text; ?>
                                    </span>
                                <?php endif; ?>

                                <span class="vehicle-status-dot <?php echo $status_class; ?>" title="<?php echo $has_insurer_rfqs ? 'Issues Reported' : 'All Clear'; ?>"></span>

                                <?php if ($has_insurer_rfqs) : ?>
                                    <div class="vehicle-issue-summary-line" onclick="vinttroToggleIssues(this)" data-toggle-target="<?php echo esc_attr($unique_row_id); ?>" title="Outstanding Issues" style="margin-top: 6px; margin-left: 12px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; cursor: pointer; user-select: none;">
                                        <span style="font-size: 0.85em; color: #666; font-weight: 600;">Insurer Response:</span>
                                        
                                        <?php foreach ($irfq_config as $key => $config) : ?>
                                            <span style="color: <?php echo $config['color']; ?>; display: inline-flex; align-items: center; font-size: 0.85em; font-weight: bold;" title="<?php echo esc_attr($config['desc']); ?>">
                                                <?php echo $warning_triangle_svg; ?><?php echo $config['count']; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="personnel-cell" style="line-height: 1.5;">
                            </td>

                            <td><?php echo vinttro_render_date_pill($car['date_next_mot'] ?? '', 'future', 7, 21); ?></td>
                            <td><?php echo vinttro_render_date_pill($car['date_next_service'] ?? '', 'future', 7, 21); ?></td>
                            <td><?php echo vinttro_render_date_pill($car['date_last_check'] ?? '', 'past', 21, 10); ?></td>
                        </tr>

                        <?php if ($has_insurer_rfqs) : 
       
                        ?>
                            <tr id="<?php echo esc_attr($unique_row_id); ?>" class="vehicle-issues-row" style="display: none;">
                                <td colspan="5"> 
                                    <div class="issues-expanded-box">
                                        <ul class="issue-detailed-list" style="list-style: none; padding-left: 0; margin-top: 4px;">
                                            <?php foreach ($insurer_rfqs as $insurer_rfq) : 
                                                $requested_date = '';
                                                if (!empty($insurer_rfq['date_requested'])) {
                                                    $requested_ts = strtotime($insurer_rfq['date_requested']);
                                                    $requested_date = $requested_ts ? date('d/m/Y', $requested_ts) : $insurer_rfq['date_requested'];
                                                }

                                                $issue_id = $insurer_rfq['id'] ?? '';

                                                // Normalize item severity for inner row style output
                                                $sev_key = strtolower($insurer_rfq['severity'] ?? 'minor');
                                                if ($sev_key === 'high') $sev_key = 'major';
                                                if ($sev_key === 'medium') $sev_key = 'moderate';
                                                if ($sev_key === 'low' || $sev_key === 'info') $sev_key = 'minor';

                                                $item_color = $sev_config[$sev_key]['color'] ?? '#6c757d';
                                            ?>
                                                <li style="display: flex; align-items: flex-start; margin-bottom: 8px; color: <?php echo $item_color; ?>;">
                                                    <span class="issue-icon" style="flex-shrink: 0; display: inline-flex; align-items: center; height: 20px;">
                                                        <?php echo $warning_triangle_svg; ?>
                                                    </span>
                                                    <span style="color: #333;">
                                                        <?php if (!empty($issue_id)) : ?>
                                                            <a href="<?php echo esc_url(rtrim($crm_base_url, '/')) . '/#/visp_vehicle_issue/record/' . esc_attr($issue_id) . '?offset=1'; ?>" 
                                                               target="_blank" 
                                                               title="View Issue in CRM"
                                                               style="color: inherit; text-decoration: none;">
                                                                <strong><?php echo esc_html($insurer_rfq['name']); ?>:</strong>
                                                            </a>
                                                        <?php else : ?>
                                                            <strong><?php echo esc_html($insurer_rfq['name']); ?>:</strong>
                                                        <?php endif; ?>
                                                        
                                                        <?php echo esc_html($insurer_rfq['insurer']); ?>
                                                        <span class="issue-date" style="color: #777; font-size: 0.9em; margin-left: 6px;">- Reported: <?php echo esc_html($requested_date); ?></span>
                                                    </span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php 
    return ob_get_clean();
}