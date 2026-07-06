<?php
/**
 * Main Dashboard Shortcode: [vinttro_dashboard]
 */
add_shortcode('vinttro_dashboard', function() {
    if (!is_user_logged_in()) {
        return '<p>Please <a href="/login">log in</a> to view your dashboard.</p>';
    }

    $user_id = get_current_user_id();
    $user_info = get_userdata($user_id);

    ob_start(); 
    ?>
    <style>
        .vinttro-dashboard { max-width: 1200px; margin: 0 auto; }
        .dashboard-grid { 
            display: flex; 
            flex-direction: column; 
            gap: 20px; 
        }
        .top-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
    </style>

    <div class="vinttro-dashboard">
        <header class="dashboard-welcome" style="margin-bottom: 20px;">
            <h2>Welcome back, <?php echo esc_html($user_info->first_name); ?>!</h2>
            <p>Member Status: <strong><?php echo get_user_meta($user_id, 'vinttro_membership_status', true) ?: 'Active'; ?></strong></p>
        </header>

        <div class="dashboard-grid">
            <div class="top-row">
                <?php echo vinttro_get_reminders_panel($user_id); ?>
                <?php echo vinttro_get_garage_panel($user_id); ?>
            </div>
            
            <?php echo vinttro_get_fleet_panels($user_id); ?>           
        </div>
    </div>
    <?php
    return ob_get_clean();
});

add_shortcode('fleet_dashboard', function() {
    if (!is_user_logged_in()) {
        return '<p>Please <a href="/login">log in</a> to view your dashboard.</p>';
    }

    $user_id = get_current_user_id();
    $user_info = get_userdata($user_id);

    ob_start(); 
    ?>
    <style>
        .vinttro-dashboard { max-width: 1200px; margin: 0 auto; }
        .dashboard-grid { 
            display: flex; 
            flex-direction: column; 
            gap: 20px; 
        }
        .top-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
    </style>

    <div class="vinttro-dashboard">

        <div class="dashboard-grid">
            <?php echo vinttro_get_fleet_panels($user_id); ?>           
        </div>
    </div>
    <?php
    return ob_get_clean();
});
add_shortcode('fleet_admin', function() {
    if ( ! is_user_logged_in() ) {
        return '<p>Please <a href="/login">log in</a> to view your dashboard.</p>';
    }

    $user_id = get_current_user_id();

    ob_start(); 
    ?>
    <div class="vinttro-dashboard vinttro-admin-dashboard-view">
        <div class="dashboard-grid">
            <?php echo vinttro_get_fleet_panels( $user_id ); ?>          
        </div>
    </div>
    <?php
    return ob_get_clean();
});


/**
 * Logic for the Reminders Panel
 */
function vinttro_get_reminders_panel($user_id) {
    $mot = get_user_meta($user_id, 'vinttro_mot_date', true);
    $ins = get_user_meta($user_id, 'vinttro_insurance_renewal', true);

    ob_start();
    ?>
    <div class="dashboard-panel reminders-panel">
        <h3>📅 My Reminders</h3>
        <div class="reminder-item">
            <span class="label">Next MOT:</span>
            <span class="value"><?php echo $mot ? date('d M Y', strtotime($mot)) : '<em>Not set</em>'; ?></span>
        </div>
        <div class="reminder-item">
            <span class="label">Insurance Renewal:</span>
            <span class="value"><?php echo $ins ? date('d M Y', strtotime($ins)) : '<em>Not set</em>'; ?></span>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Logic for the Garage Panel
 */
function vinttro_get_garage_panel($user_id) {
    $garage = get_user_meta($user_id, 'vinttro_garage', true);

    if (empty($garage) || !is_array($garage)) {
        return '<div class="dashboard-panel"><h3>🏎️ My Garage</h3><p>No vehicles found.</p></div>';
    }

    ob_start();
    ?>
    <div class="dashboard-panel">
        <h3>🏎️ My Garage</h3>
        <div class="garage-list">
            <?php foreach ($garage as $car) : ?>
                <div class="garage-item" style="border-bottom: 1px solid #eee; padding: 10px 0;">
                    <strong><?php echo esc_html($car['make'] . ' ' . $car['model']); ?></strong><br>
                    <small>Reg: <?php echo esc_html($car['reg']); ?></small><br>
                    <small>MOT Due: <?php echo vinttro_render_date_pill($car['date_next_mot'] ?? ''); ?></small><br>
                    <small>Next Service: <?php echo vinttro_render_date_pill($car['date_next_service'] ?? ''); ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Logic for the Fleets Panels
 */
/**
 * 1. PURE LOGIC HELPER
 * Determines the raw RAG status string ('R', 'A', 'G') for any given date field.
 */
function vinttro_get_date_rag($date_string, $type = 'future', $red_threshold = 7, $amber_threshold = 21) {
    if (empty($date_string)) return 'G'; 
    
    $now = time();
    $target_date = strtotime($date_string);
    if (!$target_date) return 'G';

    if ($type === 'past') {
        $days_ago = floor(($now - $target_date) / (60 * 60 * 24));
        if ($days_ago > $red_threshold)   return 'R';
        if ($days_ago > $amber_threshold) return 'A';
    } else {
        $days_remaining = floor(($target_date - $now) / (60 * 60 * 24));
        if ($target_date < $now || $days_remaining < $red_threshold) return 'R';
        if ($days_remaining < $amber_threshold)                      return 'A';
    }

    return 'G';
}

/**
 * 2. UPDATED DATE PILL RENDERER
 * Now relies cleanly on the pure data helper above.
 */
function vinttro_render_date_pill($date_string, $type = 'future', $red_threshold = 7, $amber_threshold = 21) {
    if (empty($date_string)) {
        return '<span class="status-pill status-none">N/A</span>';
    }

    $target_date = strtotime($date_string);
    if (!$target_date) return '<span class="status-pill status-none">Invalid Date</span>';
    
    $formatted_date = date('d/m/Y', $target_date);
    $rag = vinttro_get_date_rag($date_string, $type, $red_threshold, $amber_threshold);

    // Map RAG letters to your CSS classes
    $class_map = [
        'R' => 'status-urgent', // Red
        'A' => 'status-soon',   // Amber
        'G' => 'status-ok'      // Green
    ];
    $class = $class_map[$rag] ?? 'status-ok';

    return sprintf('<span class="status-pill %s">%s</span>', $class, esc_html($formatted_date));
}

/**
 * 3. THE VEHICLE SCORE CALCULATOR
 * Translates a vehicle's complete status profile into a single priority integer.
 */
function vinttro_calculate_vehicle_priority_score($car) {
    $score = 0;

    // --- 🛠️ Tier 1: Outstanding Mechanical Issues (Highest Weight) ---
    $issues = $car['outstanding_issues'] ?? [];
    if (!empty($issues)) {
        $severities = array_column($issues, 'severity');
        if (in_array('critical', $severities) || in_array('high', $severities)) {
            $score += 1000; // Crashing mechanical issues win instantly
        } elseif (in_array('major', $severities)) {
            $score += 500;
        } elseif (in_array('moderate', $severities) || in_array('medium', $severities)) {
            $score += 400;
        } else {
            $score += 300;
        }
    }

    // --- 🚨 Tier 2: Next MOT RAG Matrix Values ---
    $mot_rag = vinttro_get_date_rag($car['date_next_mot'] ?? '', 'future', 7, 21);
    if ($mot_rag === 'R') $score += 200;
    if ($mot_rag === 'A') $score += 100;

    // --- 🔧 Tier 3: Next Service RAG Matrix Values ---
    $service_rag = vinttro_get_date_rag($car['date_next_service'] ?? '', 'future', 7, 21);
    if ($service_rag === 'R') $score += 20;
    if ($service_rag === 'A') $score += 10;

    // --- 📋 Tier 4: Last Check RAG Matrix Values ---
    $check_rag = vinttro_get_date_rag($car['date_last_check'] ?? '', 'past', 21, 10);
    if ($check_rag === 'R') $score += 2;
    if ($check_rag === 'A') $score += 1;

    return $score;
}

/**
 * 4. THE MAIN PANEL RENDERER (CLEAN TEXT-ONLY ISSUES TOGGLE)
 */
function vinttro_get_fleet_panels($user_id) {
    // Read from wp-config constant instead of $_ENV
    $crm_base_url = defined('SUITECRM_BASE_URL') ? SUITECRM_BASE_URL : 'https://uatcrm.vinttro.co.uk';
    
    $fleets = get_user_meta($user_id, 'vinttro_fleets', true);
    if (empty($fleets) || !is_array($fleets)) return '';

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
    <?php
    foreach ($fleets as $fleet) : 
        $vehicles = $fleet['vehicles'] ?? [];

        // 🔀 UNIFIED SORTING: Sort descending by total calculated weight score
        if (!empty($vehicles) && is_array($vehicles)) {
            usort($vehicles, function($a, $b) {
                $score_a = vinttro_calculate_vehicle_priority_score($a);
                $score_b = vinttro_calculate_vehicle_priority_score($b);
                
                if ($score_a === $score_b) return 0;
                return ($score_a > $score_b) ? -1 : 1; 
            });
        }
    ?>
        <div class="dashboard-panel fleet-container" style="margin-bottom: 30px;">
            <h3>🚚 Fleet: <?php echo esc_html($fleet['name'] ?? 'Unnamed'); ?></h3>
            <table class="fleet-table">
                <thead>
                    <tr>
                        <th>Vehicle (Reg)</th>
                        <th>Personnel</th>
                        <th>Next MOT</th>
                        <th>Next Service</th>
                        <th>Last Check</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vehicles as $car) : 
                        $issues = $car['outstanding_issues'] ?? [];
                        $has_issues = !empty($issues);
                        
                        $status_class = 'status-safe';
                        
                        // Define the strict 4-tier SuiteCRM severity configuration schema
                        $sev_config = [
                            'critical' => [
                                'color' => '#dc3545',
                                'desc'  => 'Critical (Vehicle is unsafe to operate; do not drive.)',
                                'count' => 0
                            ],
                            'major' => [
                                'color' => '#fd7e14',
                                'desc'  => 'Major (Immediate safety or mechanical risk; requires urgent repair.)',
                                'count' => 0
                            ],
                            'moderate' => [
                                'color' => '#ffc107',
                                'desc'  => 'Moderate (Potential risk or performance degradation if not addressed soon.)',
                                'count' => 0
                            ],
                            'minor' => [
                                'color' => '#6c757d',
                                'desc'  => 'Minor (Cosmetic or non-essential; no impact on safety or performance.)',
                                'count' => 0
                            ]
                        ];

                        if ($has_issues) {
                            $severities = array_map('strtolower', array_column($issues, 'severity'));
                            
                            // Map parent status dot colors
                            if (in_array('critical', $severities) || in_array('major', $severities) || in_array('high', $severities)) {
                                $status_class = 'status-critical';
                            } elseif (in_array('moderate', $severities) || in_array('medium', $severities)) {
                                $status_class = 'status-warning';
                            } else {
                                $status_class = 'status-info';
                            }

                            // Hydrate counts (with backwards-compatible fallback mapping)
                            foreach ($issues as $issue) {
                                $sev = strtolower($issue['severity'] ?? 'minor');
                                if ($sev === 'high') $sev = 'major';
                                if ($sev === 'medium') $sev = 'moderate';
                                if ($sev === 'low' || $sev === 'info') $sev = 'minor';

                                if (isset($sev_config[$sev])) {
                                    $sev_config[$sev]['count']++;
                                }
                            }
                        }

                        // Normalized warning triangle SVG used uniformly for a cleaner aesthetic
                        $warning_triangle_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 2px;"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
                    ?>
                        <tr class="vehicle-main-row <?php echo $has_issues ? 'has-issues' : 'no-issues'; ?>">
                            <td class="vehicle-cell">
                                <?php 
                                $crm_id = $car['id'] ?? ''; 
                                $reg_text = esc_html($car['reg'] ?? 'N/A');
                                $unique_row_id = 'issues-' . (!empty($crm_id) ? $crm_id : md5($reg_text));
                                
                                if (!empty($crm_id)) : ?>
                                    <a href="<?php echo esc_url(rtrim($crm_base_url, '/')) . '/#/visp_vehicle/record/' . esc_attr($crm_id); ?>" 
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

                                <span class="vehicle-status-dot <?php echo $status_class; ?>" title="<?php echo $has_issues ? 'Issues Reported' : 'All Clear'; ?>"></span>

                                <?php if ($has_issues) : ?>
                                    <div class="vehicle-issue-summary-line" onclick="vinttroToggleIssues(this)" data-toggle-target="<?php echo esc_attr($unique_row_id); ?>" title="Outstanding Issues" style="margin-top: 6px; margin-left: 12px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; cursor: pointer; user-select: none;">
                                        <span style="font-size: 0.85em; color: #666; font-weight: 600;">Issues:</span>
                                        
                                        <?php foreach ($sev_config as $key => $config) : ?>
                                            <span style="color: <?php echo $config['color']; ?>; display: inline-flex; align-items: center; font-size: 0.85em; font-weight: bold;" title="<?php echo esc_attr($config['desc']); ?>">
                                                <?php echo $warning_triangle_svg; ?><?php echo $config['count']; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="personnel-cell" style="line-height: 1.5;">
                                <?php 
                                $driver_name = trim($car['main_driver'] ?? '');
                                $driver_phone = $car['main_driver_phone'] ?? '';
                                $driver_email = $car['main_driver_email'] ?? '';

                                $coord_name = trim($car['coordinator'] ?? '');
                                $coord_phone = $car['coordinator_phone'] ?? '';
                                $coord_email = $car['coordinator_email'] ?? '';

                                // Check if coordinator is explicitly active and not assigned as 'Unassigned'
                                $has_valid_coordinator = (!empty($coord_name) && strtolower($coord_name) !== 'unassigned');

                                // Crisp structural inline icon SVGs
                                $steering_wheel_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#495057" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 6px;"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="2.5"/><path d="M12 2v7.5"/><path d="m19 19-4.5-4.5"/><path d="M5 19l4.5-4.5"/></svg>';
                                $cog_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#777" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 7px;"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1.51-1H21a2 2 0 0 1 0-4h-.09a1.65 1.65 0 0 0-1.51 1z M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1.51-1V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';
                                ?>

                                <?php if (!empty($driver_name)) : ?>
                                    <div class="driver-row" style="display: flex; align-items: center; flex-wrap: wrap; margin-bottom: 2px;">
                                        <?php echo $steering_wheel_svg; ?>
                                        <strong class="driver-name" style="color: #333;"><?php echo esc_html($driver_name); ?></strong>
                                        
                                        <span class="driver-actions" style="display: inline-flex; gap: 6px; margin-left: 8px; align-items: center;">
                                            <?php if (!empty($driver_phone)) : ?>
                                                <a href="tel:<?php echo esc_attr(str_replace(' ', '', $driver_phone)); ?>" title="Call Driver: <?php echo esc_attr($driver_phone); ?>" style="color: #0073aa; display: inline-block;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                                </a>
                                            <?php endif; ?>

                                            <?php if (!empty($driver_email)) : ?>
                                                <a href="mailto:<?php echo esc_attr($driver_email); ?>" title="Email Driver: <?php echo esc_attr($driver_email); ?>" style="color: #0073aa; display: inline-block;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                                </a>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($has_valid_coordinator) : ?>
                                    <div class="coordinator-row" style="display: flex; align-items: center; flex-wrap: wrap; font-size: 0.82em; color: #666; margin-top: 4px; padding-left: 2px;">
                                        <?php echo $cog_svg; ?>
                                        <span class="coord-name" style="letter-spacing: 0.2px;"><?php echo esc_html($coord_name); ?></span>
                                        
                                        <span class="coord-actions" style="display: inline-flex; gap: 6px; margin-left: 8px; align-items: center; opacity: 0.8;">
                                            <?php if (!empty($coord_phone)) : ?>
                                                <a href="tel:<?php echo esc_attr(str_replace(' ', '', $coord_phone)); ?>" title="Call Coordinator: <?php echo esc_attr($coord_phone); ?>" style="color: #0073aa; display: inline-block;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                                </a>
                                            <?php endif; ?>

                                            <?php if (!empty($coord_email)) : ?>
                                                <a href="mailto:<?php echo esc_attr($coord_email); ?>" title="Email Coordinator: <?php echo esc_attr($coord_email); ?>" style="color: #0073aa; display: inline-block;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                                </a>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <?php if (empty($driver_name) && !$has_valid_coordinator) : ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>

                            <td><?php echo vinttro_render_date_pill($car['date_next_mot'] ?? '', 'future', 7, 21); ?></td>
                            <td><?php echo vinttro_render_date_pill($car['date_next_service'] ?? '', 'future', 7, 21); ?></td>
                            <td><?php echo vinttro_render_date_pill($car['date_last_check'] ?? '', 'past', 21, 10); ?></td>
                        </tr>

                        <?php if ($has_issues) : 
                            // 📊 Severity Priority Mapping for details row sub-sorting
                            $severity_priority = [
                                'critical' => 1,
                                'major'    => 2,
                                'high'     => 2,
                                'moderate' => 3, 
                                'medium'   => 3,
                                'minor'    => 4,
                                'low'      => 4,
                                'info'     => 5
                            ];

                            usort($issues, function($a, $b) use ($severity_priority) {
                                $prio_a = $severity_priority[strtolower($a['severity'] ?? '')] ?? 99;
                                $prio_b = $severity_priority[strtolower($b['severity'] ?? '')] ?? 99;
                                return $prio_a <=> $prio_b;
                            });
                        ?>
                            <tr id="<?php echo esc_attr($unique_row_id); ?>" class="vehicle-issues-row" style="display: none;">
                                <td colspan="5"> 
                                    <div class="issues-expanded-box">
                                        <ul class="issue-detailed-list" style="list-style: none; padding-left: 0; margin-top: 4px;">
                                            <?php foreach ($issues as $issue) : 
                                                $issue_date = '';
                                                if (!empty($issue['date_issue_reported'])) {
                                                    $issue_ts = strtotime($issue['date_issue_reported']);
                                                    $issue_date = $issue_ts ? date('d/m/Y', $issue_ts) : $issue['date_issue_reported'];
                                                }

                                                $issue_id = $issue['id'] ?? '';

                                                // Normalize item severity for inner row style output
                                                $sev_key = strtolower($issue['severity'] ?? 'minor');
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
                                                                <strong><?php echo esc_html($issue['name']); ?>:</strong>
                                                            </a>
                                                        <?php else : ?>
                                                            <strong><?php echo esc_html($issue['name']); ?>:</strong>
                                                        <?php endif; ?>
                                                        
                                                        <?php echo esc_html($issue['description']); ?>
                                                        <span class="issue-date" style="color: #777; font-size: 0.9em; margin-left: 6px;">- Reported: <?php echo esc_html($issue_date); ?></span>
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
    <?php endforeach;
    return ob_get_clean();
}