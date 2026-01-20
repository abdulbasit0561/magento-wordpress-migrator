<?php
/**
 * Admin Class
 *
 * Handles admin menu and page initialization
 *
 * @package Magento_WordPress_Migrator
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWM_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Instantiate settings and migration page classes immediately
        // NOT in a callback, so they can hook to admin_init properly
        new MWM_Settings();
        new MWM_Migration_Page();
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Magento Migrator', 'magento-wordpress-migrator'),
            __('Magento Migrator', 'magento-wordpress-migrator'),
            'manage_options',
            'magento-wp-migrator',
            array($this, 'render_dashboard_page'),
            'dashicons-download',
            30
        );

        add_submenu_page(
            'magento-wp-migrator',
            __('Dashboard', 'magento-wordpress-migrator'),
            __('Dashboard', 'magento-wordpress-migrator'),
            'manage_options',
            'magento-wp-migrator',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'magento-wp-migrator',
            __('Settings', 'magento-wordpress-migrator'),
            __('Settings', 'magento-wordpress-migrator'),
            'manage_options',
            'magento-wp-migrator-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'magento-wp-migrator',
            __('Migration', 'magento-wordpress-migrator'),
            __('Migration', 'magento-wordpress-migrator'),
            'manage_options',
            'magento-wp-migrator-migration',
            array($this, 'render_migration_page')
        );

        add_submenu_page(
            'magento-wp-migrator',
            __('Logs', 'magento-wordpress-migrator'),
            __('Logs', 'magento-wordpress-migrator'),
            'manage_options',
            'magento-wp-migrator-logs',
            array($this, 'render_logs_page')
        );
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Magento to WordPress Migrator', 'magento-wordpress-migrator'); ?></h1>

            <div class="mwm-dashboard">
                <div class="mwm-dashboard-grid">
                    <!-- Connection Status Card -->
                    <div class="mwm-card mwm-card-connection">
                        <h2><?php esc_html_e('Connection Status', 'magento-wordpress-migrator'); ?></h2>
                        <div id="mwm-connection-status">
                            <p class="mwm-status-checking">
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e('Checking...', 'magento-wordpress-migrator'); ?>
                            </p>
                        </div>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=magento-wp-migrator-settings'); ?>" class="button">
                                <?php esc_html_e('Configure Settings', 'magento-wordpress-migrator'); ?>
                            </a>
                        </p>
                    </div>

                    <!-- Quick Stats Card -->
                    <div class="mwm-card mwm-card-stats">
                        <h2><?php esc_html_e('Migration Statistics', 'magento-wordpress-migrator'); ?></h2>
                        <div id="mwm-stats">
                            <p class="mwm-loading"><?php esc_html_e('Loading statistics...', 'magento-wordpress-migrator'); ?></p>
                        </div>
                    </div>

                    <!-- Quick Actions Card -->
                    <div class="mwm-card mwm-card-actions">
                        <h2><?php esc_html_e('Quick Actions', 'magento-wordpress-migrator'); ?></h2>
                        <div class="mwm-actions">
                            <a href="<?php echo admin_url('admin.php?page=magento-wp-migrator-migration'); ?>" class="button button-primary">
                                <?php esc_html_e('Start Migration', 'magento-wordpress-migrator'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=magento-wp-migrator-logs'); ?>" class="button">
                                <?php esc_html_e('View Logs', 'magento-wordpress-migrator'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=magento-wp-migrator-settings'); ?>" class="button">
                                <?php esc_html_e('Settings', 'magento-wordpress-migrator'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Migration Progress Card -->
                <div class="mwm-card mwm-card-progress">
                    <h2><?php esc_html_e('Current Migration Progress', 'magento-wordpress-migrator'); ?></h2>
                    <div id="mwm-progress-display">
                        <p class="mwm-no-migration"><?php esc_html_e('No migration in progress', 'magento-wordpress-migrator'); ?></p>
                    </div>
                </div>

                <!-- Recent Activity Card -->
                <div class="mwm-card mwm-card-activity">
                    <h2><?php esc_html_e('Recent Activity', 'magento-wordpress-migrator'); ?></h2>
                    <div id="mwm-recent-activity">
                        <?php $this->render_recent_activity(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Show updated message
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully.', 'magento-wordpress-migrator') . '</p></div>';
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Magento Migrator Settings', 'magento-wordpress-migrator'); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('mwm_settings');
                do_settings_sections('magento-wp-migrator-settings');
                submit_button(__('Save Settings', 'magento-wordpress-migrator'));
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render migration page
     */
    public function render_migration_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Migration', 'magento-wordpress-migrator'); ?></h1>
            <?php do_settings_sections('mwm-migration'); ?>
        </div>
        <?php
    }

    /**
     * Render logs page
     */
    public function render_logs_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $logs = MWM_Logger::get_logs(50);
        $counts = MWM_Logger::get_log_counts();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Migration Logs', 'magento-wordpress-migrator'); ?></h1>

            <div class="mwm-log-filters">
                <h2 class="screen-reader-text"><?php esc_html_e('Filter Logs', 'magento-wordpress-migrator'); ?></h2>

                <div class="mwm-log-stats">
                    <span class="mwm-stat-item">
                        <strong><?php echo esc_html($counts['total']); ?></strong>
                        <?php esc_html_e('Total', 'magento-wordpress-migrator'); ?>
                    </span>
                    <span class="mwm-stat-item mwm-stat-success">
                        <strong><?php echo esc_html($counts['success']); ?></strong>
                        <?php esc_html_e('Success', 'magento-wordpress-migrator'); ?>
                    </span>
                    <span class="mwm-stat-item mwm-stat-error">
                        <strong><?php echo esc_html($counts['error']); ?></strong>
                        <?php esc_html_e('Errors', 'magento-wordpress-migrator'); ?>
                    </span>
                    <span class="mwm-stat-item mwm-stat-warning">
                        <strong><?php echo esc_html($counts['warning']); ?></strong>
                        <?php esc_html_e('Warnings', 'magento-wordpress-migrator'); ?>
                    </span>
                </div>

                <form method="post" action="">
                    <?php wp_nonce_field('mwm_clear_logs', 'mwm_clear_logs_nonce'); ?>
                    <input type="submit" name="mwm_clear_logs" class="button" value="<?php esc_attr_e('Clear All Logs', 'magento-wordpress-migrator'); ?>" onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear all logs?', 'magento-wordpress-migrator'); ?>');">
                </form>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date/Time', 'magento-wordpress-migrator'); ?></th>
                        <th><?php esc_html_e('Status', 'magento-wordpress-migrator'); ?></th>
                        <th><?php esc_html_e('Action', 'magento-wordpress-migrator'); ?></th>
                        <th><?php esc_html_e('Item ID', 'magento-wordpress-migrator'); ?></th>
                        <th><?php esc_html_e('Message', 'magento-wordpress-migrator'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)) : ?>
                        <tr>
                            <td colspan="5"><?php esc_html_e('No logs found', 'magento-wordpress-migrator'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($logs as $log) : ?>
                            <tr>
                                <td><?php echo esc_html($log['created_at']); ?></td>
                                <td>
                                    <span class="mwm-status-badge mwm-status-<?php echo esc_attr($log['status']); ?>">
                                        <?php echo esc_html(ucfirst($log['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($log['migration_type']); ?></td>
                                <td><?php echo esc_html($log['item_id']); ?></td>
                                <td><?php echo esc_html($log['message']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php
            // Handle clear logs
            if (isset($_POST['mwm_clear_logs']) && check_admin_referer('mwm_clear_logs', 'mwm_clear_logs_nonce')) {
                MWM_Logger::clear_all_logs();
                echo '<div class="notice notice-success"><p>' . esc_html__('Logs cleared successfully', 'magento-wordpress-migrator') . '</p></div>';
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render recent activity
     */
    private function render_recent_activity() {
        $logs = MWM_Logger::get_logs(10);

        if (empty($logs)) {
            echo '<p>' . esc_html__('No recent activity', 'magento-wordpress-migrator') . '</p>';
            return;
        }

        echo '<ul class="mwm-activity-list">';
        foreach ($logs as $log) {
            $status_class = 'mwm-activity-' . $log['status'];
            echo '<li class="' . esc_attr($status_class) . '">';
            echo '<span class="mwm-activity-time">' . esc_html($log['created_at']) . '</span>';
            echo '<span class="mwm-activity-message">' . esc_html($log['message']) . '</span>';
            echo '</li>';
        }
        echo '</ul>';
    }
}
