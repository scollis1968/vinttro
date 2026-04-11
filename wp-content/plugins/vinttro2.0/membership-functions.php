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

    ob_start(); // Start output buffering
    ?>
    <div class="vinttro-dashboard">
        <header class="dashboard-welcome">
            <h2>Welcome back, <?php echo esc_html($user_info->first_name); ?>!</h2>
            <p>Member Status: <strong><?php echo get_user_meta($user_id, 'vinttro_membership_status', true) ?: 'Active'; ?></strong></p>
        </header>

        <div class="dashboard-grid">
            <?php echo vinttro_get_reminders_panel($user_id); ?>
            <?php echo vinttro_get_garage_panel($user_id); ?>
            <?php echo vinttro_get_fleet_panel($user_id); ?>           
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
function vinttro_get_garage_panel($user_id) {
    $garage = get_user_meta($user_id, 'vinttro_garage', true);

    if (empty($garage) || !is_array($garage)) {
        return '<div class="dashboard-panel"><h3>My Garage</h3><p>No vehicles found.</p></div>';
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
                    <small>MOT Due: <?php echo esc_html($car['mot_expiry']); ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function vinttro_get_fleet_panel($user_id) {
    $garage = get_user_meta($user_id, 'vinttro_garage', true);
    
    if (empty($garage) || !is_array($garage)) {
        return '<div class="dashboard-panel"><h3>🚚 My Fleet</h3><p>No vehicles found.</p></div>';
    }

    ob_start();
    ?>
    <style>
        .fleet-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .fleet-table th, .fleet-table td { text-align: left; padding: 12px 8px; border-bottom: 1px solid #eee; }
        .status-pill { 
            padding: 4px 8px; 
            border-radius: 4px; 
            font-size: 0.85em; 
            font-weight: bold; 
            color: #fff; 
            display: inline-block;
        }
        /* Color Coding */
        .status-past { background-color: #8b0000; } /* Very Red / Dark Red */
        .status-urgent { background-color: #e74c3c; } /* Red (< 1 week) */
        .status-soon { background-color: #f39c12; } /* Orange/Amber (< 3 weeks) */
        .status-ok { background-color: #27ae60; } /* Green */
        .status-none { background-color: #bdc3c7; color: #333; } /* Grey for missing dates */
    </style>

    <div class="dashboard-panel">
        <h3>🚚 My Fleet</h3>
        <table class="fleet-table">
            <thead>
                <tr>
                    <th>Vehicle</th>
                    <th>Next MOT</th>
                    <th>Next Service</th>
                    <th>Last Check</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                foreach ($garage as $car) : 
                    // 1. Filter: Only show if 'fleet' property is truthy
                    if (empty($car['fleet'])) continue;

                    echo '<tr>';
                        // Vehicle Info
                        echo '<td>';
                            echo '<strong>' . esc_html($car['reg']) . '</strong><br>';
                            echo '<small>' . esc_html($car['make'] . ' ' . $car['model']) . '</small>';
                        echo '</td>';

                        // Date Columns
                        echo '<td>' . vinttro_render_date_pill($car['mot_expiry'] ?? '') . '</td>';
                        echo '<td>' . vinttro_render_date_pill($car['service_due'] ?? '') . '</td>';
                        echo '<td>' . vinttro_render_date_pill($car['last_check'] ?? '') . '</td>';
                    echo '</tr>';
                endforeach; 
                ?>
            </tbody>
        </table>
    </div>
    <?php
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
    $diff = $target_date - $now;
    $days_remaining = floor($diff / (60 * 60 * 24));

    // Determine Logic
    if ($diff < 0) {
        $class = 'status-past'; // In the past
    } elseif ($days_remaining < 7) {
        $class = 'status-urgent'; // Less than a week
    } elseif ($days_remaining < 21) {
        $class = 'status-soon'; // Less than 3 weeks
    } else {
        $class = 'status-ok'; // Long way in the future
    }

    return sprintf('<span class="status-pill %s">%s</span>', $class, esc_html($date_string));
}