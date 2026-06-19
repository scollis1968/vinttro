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
            gap: 20px; /* This replaces the <br> for spacing */
        }
        /* Style for the two-column top row if you want them side-by-side */
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
            gap: 20px; /* This replaces the <br> for spacing */
        }
        /* Style for the two-column top row if you want them side-by-side */
        .top-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
    </style>

    <div class="vinttro-dashboard">
        <header class="dashboard-welcome" style="margin-bottom: 20px;">
            <h2>Welcome back, <?php echo esc_html($user_info->first_name); ?>!</h2>
        </header>

        <div class="dashboard-grid">
            <?php echo vinttro_get_fleet_panels($user_id); ?>           
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
    if (empty($date_string)) return 'G'; // Treat empty/blank as safe or manage separately
    
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
        if (in_array('high', $severities)) {
            $score += 1000; // Crashing mechanical issues win instantly
        } elseif (in_array('medium', $severities)) {
            $score += 500;
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
 * 4. THE MAIN PANEL RENDERER
 */

function vinttro_get_fleet_panels($user_id) {
    // Retrieve the base URL from the environment variable, fallback to UAT if not set
    $crm_base_url = $_ENV['SUITECRM_BASE_URL'] ?? 'https://uatcrm.vinttro.co.uk';
    
    $fleets = get_user_meta($user_id, 'vinttro_fleets', true);
    if (empty($fleets) || !is_array($fleets)) return '';

    ob_start();
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
                        <th>Driver</th>
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
                        if ($has_issues) {
                            $severities = array_column($issues, 'severity');
                            if (in_array('high', $severities)) $status_class = 'status-critical';
                            elseif (in_array('medium', $severities)) $status_class = 'status-warning';
                            else $status_class = 'status-info';
                        }
                    ?>
                        <tr class="vehicle-main-row <?php echo $has_issues ? 'has-issues' : 'no-issues'; ?>">
                            <td class="vehicle-cell">
                                <?php 
                                $crm_id = $car['id'] ?? ''; 
                                $reg_text = esc_html($car['reg'] ?? 'N/A');
                                
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
                            </td>

                            <td class="driver-cell">
                                <?php 
                                if (!empty($car['main_driver'])) : 
                                    $driver_phone = $car['main_driver_phone'] ?? '';
                                    $driver_email = $car['main_driver_email'] ?? '';
                                ?>
                                    <span class="driver-name" style="vertical-align: middle;">
                                        <?php echo esc_html($car['main_driver']); ?>
                                    </span>
                                    
                                    <span class="driver-actions" style="display: inline-flex; gap: 8px; margin-left: 10px; vertical-align: middle; align-items: center;">
                                        <?php if (!empty($driver_phone)) : ?>
                                            <a href="tel:<?php echo esc_attr(str_replace(' ', '', $driver_phone)); ?>" 
                                               title="Call: <?php echo esc_attr($driver_phone); ?>" 
                                               style="color: #0073aa; display: inline-block;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                            </a>
                                        <?php else : ?>
                                            <span title="No phone number available" style="color: #ccc; cursor: not-allowed; display: inline-block;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!empty($driver_email)) : ?>
                                            <a href="mailto:<?php echo esc_attr($driver_email); ?>" 
                                               title="Email: <?php echo esc_attr($driver_email); ?>" 
                                               style="color: #0073aa; display: inline-block;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                            </a>
                                        <?php else : ?>
                                            <span title="No email address available" style="color: #ccc; cursor: not-allowed; display: inline-block;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                <?php else : ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>

                            <td><?php echo vinttro_render_date_pill($car['date_next_mot'] ?? '', 'future', 7, 21); ?></td>
                            <td><?php echo vinttro_render_date_pill($car['date_next_service'] ?? '', 'future', 7, 21); ?></td>
                            <td><?php echo vinttro_render_date_pill($car['date_last_check'] ?? '', 'past', 21, 10); ?></td>
                        </tr>

                        <?php if ($has_issues) : ?>
                            <tr class="vehicle-issues-row">
                                <td colspan="5"> 
                                    <div class="issues-expanded-box">
                                        <strong>Outstanding Issues:</strong>
                                        <ul class="issue-detailed-list">
                                            <?php foreach ($issues as $issue) : 
                                                $issue_date = '';
                                                if (!empty($issue['date_issue_reported'])) {
                                                    $issue_ts = strtotime($issue['date_issue_reported']);
                                                    $issue_date = $issue_ts ? date('d/m/Y', $issue_ts) : $issue['date_issue_reported'];
                                                }
                                            ?>
                                                <li>
                                                    <span class="issue-severity-tag sev-<?php echo esc_attr($issue['severity']); ?>"></span>
                                                    <strong><?php echo esc_html($issue['name']); ?>:</strong> 
                                                    <?php echo esc_html($issue['description']); ?>
                                                    <span class="issue-date">- Reported: <?php echo esc_html($issue_date); ?></span>
                                                </li> <?php endforeach; ?>
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
