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
                    <small>MOT Due: <?php echo vinttro_render_date_pill($car['mot_expiry'] ?? ''); ?></small>
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
function vinttro_get_fleet_panels($user_id) {
    $fleets = get_user_meta($user_id, 'vinttro_fleets', true);
    if (empty($fleets) || !is_array($fleets)) return '';

    ob_start();
    foreach ($fleets as $fleet) : ?>
        <div class="dashboard-panel fleet-container" style="margin-bottom: 30px;">
            <h3>🚚 Fleet: <?php echo esc_html($fleet['name'] ?? 'Unnamed'); ?></h3>
            <table class="fleet-table">
                <thead>
                    <tr>
                        <th>Vehicle (Reg)</th>
                        <th>Next MOT</th>
                        <th>Next Service</th>
                        <th>Last Check</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($fleet['vehicles'] ?? []) as $car) : 
                        $issues = $car['outstanding_issues'] ?? [];
                        $has_issues = !empty($issues);
                        
                        // Determine Status Class
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
                                <span class="vehicle-reg" title="<?php echo esc_attr(($car['make'] ?? '') . ' ' . ($car['model'] ?? '')); ?>">
                                    <?php echo esc_html($car['reg'] ?? 'N/A'); ?>
                                </span>
                                <span class="vehicle-status-dot <?php echo $status_class; ?>" title="<?php echo $has_issues ? 'Issues Reported' : 'All Clear'; ?>"></span>
                            </td>
                            <td><?php echo vinttro_render_date_pill($car['mot_expiry'] ?? ''); ?></td>
                            <td><?php echo vinttro_render_date_pill($car['date_next_service'] ?? ''); ?></td>
                            <td><?php echo vinttro_render_date_pill($car['date_last_check'] ?? ''); ?></td>
                        </tr>

                        <?php if ($has_issues) : ?>
                            <tr class="vehicle-issues-row">
                                <td colspan="4">
                                    <div class="issues-expanded-box">
                                        <strong>Outstanding Issues:</strong>
                                        <ul class="issue-detailed-list">
                                            <?php foreach ($issues as $issue) : ?>
                                                <li>
                                                    <span class="issue-severity-tag sev-<?php echo esc_attr($issue['severity']); ?>"></span>
                                                    <strong><?php echo esc_html($issue['title']); ?>:</strong> 
                                                    <?php echo esc_html($issue['description']); ?>
                                                    <span class="issue-date">- Reported: <?php echo esc_html($issue['date'] ?? ''); ?></span>
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
/**
 * Helper to determine color class and render the date
 */
function vinttro_render_date_pill($date_string) {
    if (empty($date_string)) {
        return '<span class="status-pill status-none">N/A</span>';
    }

    $now = time();
    $target_date = strtotime($date_string);
    
    // Safety check in case the date string is mangled
    if (!$target_date) return '<span class="status-pill status-none">Invalid Date</span>';

    $diff = $target_date - $now;
    $days_remaining = floor($diff / (60 * 60 * 24));

    if ($diff < 0) {
        $class = 'status-past';
    } elseif ($days_remaining < 7) {
        $class = 'status-urgent';
    } elseif ($days_remaining < 21) {
        $class = 'status-soon';
    } else {
        $class = 'status-ok';
    }

    return sprintf('<span class="status-pill %s">%s</span>', $class, esc_html($date_string));
}