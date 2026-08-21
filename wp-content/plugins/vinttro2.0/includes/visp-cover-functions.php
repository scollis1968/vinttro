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
    $crm_base_url = defined('SUITECRM_BASE_URL') ? SUITECRM_BASE_URL : 'https://uatcrm.vinttro.co.uk';
    $rfqs = get_user_meta($user_id, 'vinttro_cover_rfqs', true);
    
    if (empty($rfqs) || !is_array($rfqs)) {
        return '<div class="dashboard-panel"><p>No open quote requests found for this account.</p></div>';
    }

    ob_start();
    ?>
    <style>
        /* Force panel and table to stretch full width */
        .dashboard-panel,
        .fleet-table {
            width: 100% !important;
            box-sizing: border-box;
        }

        .fleet-table {
            table-layout: auto; /* Ensures columns distribute evenly across full width */
        }

        .vehicle-issue-summary-line { transition: opacity 0.2s ease-in-out; }
        .vehicle-issue-summary-line:hover { opacity: 0.85; }
    .vehicle-main-row td { vertical-align: top !important; padding-top: 10px; }
        
        /* Summary Pills */
        .summary-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            color: #475569;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }

        /* Expanded Drawer & Card Layout */
        .issues-expanded-box {
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .expanded-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 16px;
        }
        .drawer-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 14px 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }
        .drawer-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f1f5f9;
        }
        .drawer-card-title {
            margin: 0;
            font-size: 0.88em;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        /* Activity Timeline Styles */
        .activity-timeline { margin-top: 8px; padding-left: 0; list-style: none; border-left: 2px solid #e2e8f0; margin-left: 10px; }
        .activity-item { position: relative; margin-bottom: 12px; padding-left: 18px; font-size: 0.88em; }
        .activity-item::before { content: ''; position: absolute; left: -6px; top: 4px; width: 10px; height: 10px; border-radius: 50%; background: #4a5568; }
        .activity-badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 0.85em; background: #edf2f7; color: #2d3748; margin-right: 6px; }
        .activity-date { color: #718096; font-size: 0.85em; margin-left: 8px; }
    </style>

    <script>
        function vinttroToggleIssues(triggerElement) {
            var targetId = triggerElement.getAttribute('data-toggle-target');
            var targetRow = document.getElementById(targetId);
            if (targetRow) {
                targetRow.style.display = (targetRow.style.display === 'table-row') ? 'none' : 'table-row';
            }
        }
    </script>

    <div class="dashboard-panel fleet-container" style="margin-bottom: 30px;">
        <h3>✍️ Open Quote Requests:</h3>
        <table class="fleet-table">
            <thead>
                <tr>
                    <th>Quote Request</th>
                    <th>Status</th>
                    <th>Received</th>
                    <th>Cover Start</th>
                    <th>Quoted</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rfqs as $rfq) : 
                    $insurer_rfqs = $rfq['insurer_rfqs'] ?? [];
                    $activities   = $rfq['activities'] ?? [];
                    $has_insurer_rfqs = !empty($insurer_rfqs) && is_array($insurer_rfqs);
                    $has_activities   = !empty($activities) && is_array($activities);
                    $has_details      = $has_insurer_rfqs || $has_activities;
                    
                    $status_class = 'status-safe';
                    $irfq_config = [
                        'new'      => ['color' => '#210fe8e0', 'desc' => 'New no response from insurer', 'count' => 0],
                        'rejected' => ['color' => '#89170a', 'desc' => 'Rejected', 'count' => 0],
                        'quoted'   => ['color' => '#055a3f', 'desc' => 'Quoted', 'count' => 0]
                    ];

                    if ($has_insurer_rfqs) {
                        $severities = array_map('strtolower', array_column($insurer_rfqs, 'severity'));
                        if (in_array('critical', $severities) || in_array('major', $severities) || in_array('high', $severities)) {
                            $status_class = 'status-critical';
                        } elseif (in_array('moderate', $severities) || in_array('medium', $severities)) {
                            $status_class = 'status-warning';
                        } else {
                            $status_class = 'status-info';
                        }

                        foreach ($insurer_rfqs as $i_rfq) {
                            $status = strtolower($i_rfq['status'] ?? 'new');
                            if (isset($irfq_config[$status])) {
                                $irfq_config[$status]['count']++;
                            }
                        }
                    }

                    $warning_triangle_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 2px;"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
                    $car_title = esc_attr(trim(($rfq['make'] ?? '') . ' ' . ($rfq['model'] ?? '')));
                ?>
                    <tr class="rfq-row main-row <?php echo $has_details ? 'has-issues' : 'no-issues'; ?>">
                        <td class="rfq-cell">
                            <?php 
                            $crm_id = $rfq['id'] ?? ''; 
                            $reg_text = esc_html($rfq['name'] ?? 'N/A');
                            $unique_row_id = 'issues-' . (!empty($crm_id) ? $crm_id : md5($reg_text));
                            
                            if (!empty($crm_id)) : ?>
                                <a href="<?php echo esc_url(rtrim($crm_base_url, '/')) . '/#/visp_cover_rfq/record/' . esc_attr($crm_id); ?>" target="_blank" title="View in CRM: <?php echo $car_title; ?>" style="text-decoration: none; color: inherit;">
                                    <span class="vehicle-reg"><?php echo $reg_text; ?></span>
                                </a>
                            <?php else : ?>
                                <span class="vehicle-reg" title="<?php echo $car_title; ?>"><?php echo $reg_text; ?></span>
                            <?php endif; ?>

                            <span class="vehicle-status-dot <?php echo $status_class; ?>"></span>

                            <?php if ($has_details) : ?>
                                <div class="vehicle-issue-summary-line" onclick="vinttroToggleIssues(this)" data-toggle-target="<?php echo esc_attr($unique_row_id); ?>" style="margin-top: 6px; margin-left: 12px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; cursor: pointer; user-select: none;">
                                    
                                    <?php if ($has_insurer_rfqs) : ?>
                                        <div class="summary-pill">
                                            <span style="font-weight: 600;">Quotes:</span>
                                            <?php foreach ($irfq_config as $key => $config) : ?>
                                                <span style="color: <?php echo $config['color']; ?>; display: inline-flex; align-items: center; font-weight: bold;" title="<?php echo esc_attr($config['desc']); ?>">
                                                    <?php echo $warning_triangle_svg; ?><?php echo $config['count']; ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($has_activities) : ?>
                                        <div class="summary-pill">
                                            <span>📋 <strong><?php echo count($activities); ?></strong> Activity Logs</span>
                                        </div>
                                    <?php endif; ?>

                                </div>
                            <?php endif; ?>
                        </td>

                        <td class="personnel-cell" style="line-height: 1.5;">
                            <?php echo esc_html($rfq['status'] ?? 'N/A'); ?>
                        </td>

                        <td><?php echo vinttro_render_date_pill($rfq['date_recieved'] ?? '', 'past', 1, 3); ?></td>
                        <td><?php echo vinttro_render_date_pill($rfq['date_cover_start'] ?? '', 'future', 7, 21); ?></td>
                        <td><?php echo vinttro_render_date_pill($rfq['date_last_check'] ?? '', 'past', 21, 10); ?></td>
                    </tr>

                    <?php if ($has_details) : ?>
                        <tr id="<?php echo esc_attr($unique_row_id); ?>" class="vehicle-issues-row" style="display: none;">
                            <td colspan="5"> 
                                <div class="issues-expanded-box">
                                    <div class="expanded-grid">

                                        <!-- INSURER RFQS CARD -->
                                        <?php if ($has_insurer_rfqs) : ?>
                                            <div class="drawer-card">
                                                <div class="drawer-card-header">
                                                    <h4 class="drawer-card-title">📄 Insurer Quotes</h4>
                                                    <span style="font-size: 0.8em; color: #64748b;"><?php echo count($insurer_rfqs); ?> Total</span>
                                                </div>
                                                <ul class="issue-detailed-list" style="list-style: none; padding-left: 0; margin: 0;">
                                                    <?php foreach ($insurer_rfqs as $insurer_rfq) : 
                                                        $requested_date = '';
                                                        if (!empty($insurer_rfq['date_requested'])) {
                                                            $requested_ts = strtotime($insurer_rfq['date_requested']);
                                                            $requested_date = $requested_ts ? date('d/m/Y', $requested_ts) : $insurer_rfq['date_requested'];
                                                        }
                                                        $status_key = strtolower($insurer_rfq['status'] ?? 'new');
                                                        $item_color = $irfq_config[$status_key]['color'] ?? '#6c757d';
                                                    ?>
                                                        <li style="display: flex; align-items: flex-start; margin-bottom: 8px; color: <?php echo $item_color; ?>;">
                                                            <span class="issue-icon" style="flex-shrink: 0; display: inline-flex; align-items: center; height: 20px;">
                                                                <?php echo $warning_triangle_svg; ?>
                                                            </span>
                                                            <span style="color: #333; font-size: 0.88em;">
                                                                <strong><?php echo esc_html($insurer_rfq['name'] ?? ''); ?>:</strong>
                                                                <?php echo esc_html($insurer_rfq['insurer'] ?? ''); ?>
                                                                <span style="color: #64748b; font-size: 0.85em; margin-left: 6px;">- Requested: <?php echo esc_html($requested_date); ?></span>
                                                            </span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>

                                        <!-- ACTIVITY TIMELINE CARD -->
                                        <?php if ($has_activities) : ?>
                                            <div class="drawer-card">
                                                <div class="drawer-card-header">
                                                    <h4 class="drawer-card-title">🕒 Activity History</h4>
                                                    <span style="font-size: 0.8em; color: #64748b;"><?php echo count($activities); ?> Logs</span>
                                                </div>
                                                <ul class="activity-timeline">
                                                    <?php foreach ($activities as $act) : 
                                                        $type = $act['type'] ?? 'Event';
                                                        
                                                        $icon_map = [
                                                            'Inbound Call'    => '📞',
                                                            'Outbound Call'   => '📲',
                                                            'Message In'      => '💬',
                                                            'Message Out'     => '✉️',
                                                            'Insurer Quote'   => '💰',
                                                            'Client Accepted' => '✅'
                                                        ];
                                                        $icon = $icon_map[$type] ?? '📌';
                                                        
                                                        $date_formatted = !empty($act['dateEvent']) ? date('d/m/Y H:i', strtotime($act['dateEvent'])) : '';
                                                    ?>
                                                        <li class="activity-item">
                                                            <span class="activity-badge"><?php echo $icon . ' ' . esc_html($type); ?></span>
                                                            <strong style="color: #2d3748;"><?php echo esc_html($act['note'] ?? ''); ?></strong>
                                                            <?php if (!empty($act['agent'])) : ?>
                                                                <span style="color: #4a5568; font-style: italic;">(<?php echo esc_html($act['agent']); ?>)</span>
                                                            <?php endif; ?>
                                                            <span class="activity-date"><?php echo esc_html($date_formatted); ?></span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>

                                    </div>
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