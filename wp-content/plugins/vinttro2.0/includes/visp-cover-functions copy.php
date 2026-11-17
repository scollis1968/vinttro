<?php


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
