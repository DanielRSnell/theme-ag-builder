<?php
// Add this to your theme's functions.php or a separate file included in functions.php

// Check for updates
add_action('admin_init', 'agnostic_check_for_updates');

function agnostic_check_for_updates()
{
    $current_version = wp_get_theme()->get('Version');
    $api_url = 'https://agnosticbuilder.com/wp-json/agnostic/v1/core';

    $response = wp_remote_get($api_url);

    if (is_wp_error($response)) {
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['version']) && version_compare($data['version'], $current_version, '>')) {
        add_action('admin_notices', 'agnostic_update_admin_notice');
    }
}

// Admin notice for available update
function agnostic_update_admin_notice()
{
    ?>
    <div class="notice notice-info is-dismissible">
        <p><?php _e('A new version of the Agnostic Builder theme is available!', 'agnostic-builder');?>
           <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=agnostic_update_theme'), 'agnostic_update_theme'); ?>">
              <?php _e('Update now', 'agnostic-builder');?>
           </a>
        </p>
    </div>
    <?php
}

// Handle the update process
add_action('admin_post_agnostic_update_theme', 'agnostic_update_theme');

function agnostic_update_theme()
{
    if (!current_user_can('update_themes')) {
        wp_die(__('You do not have sufficient permissions to update themes for this site.', 'agnostic-builder'));
    }

    check_admin_referer('agnostic_update_theme');

    $api_url = 'https://agnosticbuilder.com/wp-json/agnostic/v1/core';
    $response = wp_remote_get($api_url);

    if (is_wp_error($response)) {
        wp_die(__('Error fetching update information. Please try again later.', 'agnostic-builder'));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['download_url'])) {
        wp_die(__('Error: Update package not available.', 'agnostic-builder'));
    }

    include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    $upgrader = new Theme_Upgrader();
    $result = $upgrader->upgrade(wp_get_theme()->get_stylesheet());

    if (is_wp_error($result)) {
        wp_die($result->get_error_message());
    } else {
        wp_redirect(admin_url('themes.php?updated=true'));
        exit;
    }
}

// Optional: Add a custom update message
add_filter('pre_set_site_transient_update_themes', 'agnostic_check_for_update_message');

function agnostic_check_for_update_message($transient)
{
    if (empty($transient->checked)) {
        return $transient;
    }

    $theme = wp_get_theme();
    $stylesheet = $theme->get_stylesheet();

    $api_url = 'https://agnosticbuilder.com/wp-json/agnostic/v1/core';
    $response = wp_remote_get($api_url);

    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['version']) && version_compare($data['version'], $theme->get('Version'), '>')) {
            $transient->response[$stylesheet] = array(
                'theme' => $stylesheet,
                'new_version' => $data['version'],
                'url' => isset($data['changelog_url']) ? $data['changelog_url'] : '',
                'package' => isset($data['download_url']) ? $data['download_url'] : '',
            );
        }
    }

    return $transient;
}