<?php
/**
 * Migration Page Class
 *
 * Handles migration interface and progress display
 *
 * @package Magento_WordPress_Migrator
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWM_Migration_Page {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_migration_section'));
    }

    /**
     * Register migration section
     */
    public function register_migration_section() {
        add_settings_section(
            'mwm_migration_main',
            __('Select Migration Type', 'magento-wordpress-migrator'),
            array($this, 'render_migration_section'),
            'mwm-migration'
        );
    }

    /**
     * Render migration section
     */
    public function render_migration_section() {
        $settings = get_option('mwm_settings', array());
        $is_configured = !empty($settings['db_host']) && !empty($settings['db_name']) && !empty($settings['db_user']);

        // if (!$is_configured) {
        //     echo '<div class="notice notice-warning inline">';
        //     echo '<p><strong>' . esc_html__('Not Configured', 'magento-wordpress-migrator') . '</strong></p>';
        //     echo '<p>' . esc_html__('Please configure your Magento database connection settings first.', 'magento-wordpress-migrator') . '</p>';
        //     echo '<p><a href="' . esc_url(admin_url('admin.php?page=magento-wp-migrator-settings')) . '" class="button button-primary">' . esc_html__('Go to Settings', 'magento-wordpress-migrator') . '</a></p>';
        //     echo '</div>';
        //     return;
        // }

        $current_migration = get_option('mwm_current_migration', array());
        $migration_in_progress = !empty($current_migration) && $current_in_progress['status'] === 'processing';

        if ($migration_in_progress) {
            $this->render_progress_display($current_migration);
        } else {
            $this->render_migration_options();
        }
    }

    /**
     * Render migration options
     */
    private function render_migration_options() {
        ?>
        <div class="mwm-migration-options">
            <div class="mwm-migration-cards">
                <!-- Products Migration Card -->
                <div class="mwm-migration-card">
                    <div class="mwm-card-icon">
                        <span class="dashicons dashicons-cart"></span>
                    </div>
                    <h3><?php esc_html_e('Products', 'magento-wordpress-migrator'); ?></h3>
                    <p><?php esc_html_e('Migrate all products including attributes, images, categories, and inventory data.', 'magento-wordpress-migrator'); ?></p>
                    <ul class="mwm-feature-list">
                        <li><?php esc_html_e('Simple and configurable products', 'magento-wordpress-migrator'); ?></li>
                        <li><?php esc_html_e('Product attributes and custom options', 'magento-wordpress-migrator'); ?></li>
                        <li><?php esc_html_e('Product images and galleries', 'magento-wordpress-migrator'); ?></li>
                        <li><?php esc_html_e('Category assignments', 'magento-wordpress-migrator'); ?></li>
                        <li><?php esc_html_e('Stock and pricing', 'magento-wordpress-migrator'); ?></li>
                    </ul>

                    <!-- Page Selection -->
                    <div class="mwm-page-selector" style="margin-top: 15px;">
                        <label for="mwm-product-page" style="display: block; margin-bottom: 5px; font-weight: 500;">
                            <?php esc_html_e('Select Page to Migrate:', 'magento-wordpress-migrator'); ?>
                        </label>
                        <select id="mwm-product-page" class="mwm-page-select" style="width: 100%; max-width: 200px;">
                            <option value="all"><?php esc_html_e('All Pages (Full Migration)', 'magento-wordpress-migrator'); ?></option>
                            <option value="1">Page 1 (Products 1-20)</option>
                            <option value="2">Page 2 (Products 21-40)</option>
                            <option value="3">Page 3 (Products 41-60)</option>
                            <option value="4">Page 4 (Products 61-80)</option>
                            <option value="5">Page 5 (Products 81-100)</option>
                            <option value="6">Page 6 (Products 101-120)</option>
                            <option value="7">Page 7 (Products 121-140)</option>
                            <option value="8">Page 8 (Products 141-160)</option>
                            <option value="9">Page 9 (Products 161-180)</option>
                            <option value="10">Page 10 (Products 181-200)</option>
                            <option value="11">Page 11 (Products 201-220)</option>
                            <option value="12">Page 12 (Products 221-240)</option>
                            <option value="13">Page 13 (Products 241-260)</option>
                            <option value="14">Page 14 (Products 261-280)</option>
                            <option value="15">Page 15 (Products 281-300)</option>
                            <option value="16">Page 16 (Products 301-320)</option>
                            <option value="17">Page 17 (Products 321-340)</option>
                            <option value="18">Page 18 (Products 341-360)</option>
                            <option value="19">Page 19 (Products 361-380)</option>
                            <option value="20">Page 20 (Products 381-400)</option>
                            <option value="21">Page 21 (Products 401-420)</option>
                        </select>
                        <p class="description" style="margin-top: 5px; font-size: 12px; color: #666;">
                            <?php esc_html_e('Select "All Pages" to migrate all products, or select a specific page to migrate only that page (20 products per page).', 'magento-wordpress-migrator'); ?>
                        </p>
                    </div>

                    <button type="button" class="button button-primary button-large mwm-start-migration" data-type="products">
                        <?php esc_html_e('Migrate Products', 'magento-wordpress-migrator'); ?>
                    </button>
                </div>

                <!-- Categories Migration Card -->
                <div class="mwm-migration-card">
                    <div class="mwm-card-icon">
                        <span class="dashicons dashicons-category"></span>
                    </div>
                    <h3><?php esc_html_e('Categories', 'magento-wordpress-migrator'); ?></h3>
                    <p><?php esc_html_e('Migrate all categories preserving hierarchy and structure.', 'magento-wordpress-migrator'); ?></p>
                    <ul class="mwm-feature-list">
                        <li><?php esc_html_e('Category hierarchy preserved', 'magento-wordpress-migrator'); ?></li>
                        <li><?php esc_html_e('Category descriptions and URLs', 'magento-wordpress-migrator'); ?></li>
                        <li><?php esc_html_e('Parent-child relationships', 'magento-wordpress-migrator'); ?></li>
                        <li><?php esc_html_e('Category images', 'magento-wordpress-migrator'); ?></li>
                        <li><?php esc_html_e('Display settings', 'magento-wordpress-migrator'); ?></li>
                    </ul>
                    <button type="button" class="button button-primary button-large mwm-start-migration" data-type="categories">
                        <?php esc_html_e('Migrate Categories', 'magento-wordpress-migrator'); ?>
                    </button>
                </div>

                <!-- Customers Migration Card -->
                <div class="mwm-migration-card">
                    <div class="mwm-card-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <h3><?php esc_html_e('Customers', 'magento-wordpress-migrator'); ?></h3>
                    <p><?php esc_html_e('Migrate customer accounts and addresses.', 'magento-wordpress-migrator'); ?></p>
                    <ul class="mwm-feature-list">
                        <li><?php esc_html_e('Customer accounts and profiles', 'magento-wordpress-migrator'); ?></li>
                        <li><?php esc_html_e('Billing and shipping addresses', 'magento-wordpress-migrator'); ?></li>
                        <li><?php esc_html_e('Customer groups', 'magento-wordpress-migrator'); ?></li>
                        <li><?php esc_html_e('Order history', 'magento-wordpress-migrator'); ?></li>
                        <li><?php esc_html_e('Default addresses', 'magento-wordpress-migrator'); ?></li>
                    </ul>
                    <button type="button" class="button button-primary button-large mwm-start-migration" data-type="customers">
                        <?php esc_html_e('Migrate Customers', 'magento-wordpress-migrator'); ?>
                    </button>
                </div>

                <!-- Orders Migration Card -->
                <div class="mwm-migration-card">
                    <div class="mwm-card-icon">
                        <span class="dashicons dashicons-list-view"></span>
                    </div>
                    <h3><?php esc_html_e('Orders', 'magento-wordpress-migrator'); ?></h3>
                    <p><?php esc_html_e('Migrate historical order data.', 'magento-wordpress-migrator'); ?></p>
                    <ul class="mwm-feature-list">
                        <li><?php esc_html_e('Order details and line items', 'magento-wordpress-migrator'); ?></li>
                        <li><?php esc_html_e('Customer information', 'magento-wordpress-migrator'); ?></li>
                        <li><?php esc_html_e('Billing and shipping addresses', 'magento-wordpress-migrator'); ?></li>
                        <li><?php esc_html_e('Payment and shipping methods', 'magento-wordpress-migrator'); ?></li>
                        <li><?php esc_html_e('Order status and comments', 'magento-wordpress-migrator'); ?></li>
                    </ul>
                    <button type="button" class="button button-primary button-large mwm-start-migration" data-type="orders">
                        <?php esc_html_e('Migrate Orders', 'magento-wordpress-migrator'); ?>
                    </button>
                </div>
            </div>

            <!-- Migration Tips -->
            <div class="mwm-migration-tips">
                <h3><?php esc_html_e('Migration Tips', 'magento-wordpress-migrator'); ?></h3>
                <ul>
                    <li><?php esc_html_e('<strong>Recommended order:</strong> Migrate categories first, then products, then customers, and finally orders.', 'magento-wordpress-migrator'); ?></li>
                    <li><?php esc_html_e('<strong>Backup first:</strong> Always backup your WordPress database before starting migration.', 'magento-wordpress-migrator'); ?></li>
                    <li><?php esc_html_e('<strong>Incremental:</strong> You can run migrations multiple times - existing data will be updated, not duplicated.', 'magento-wordpress-migrator'); ?></li>
                    <li><?php esc_html_e('<strong>Large datasets:</strong> For large stores, migration may take time. The progress indicator shows real-time status.', 'magento-wordpress-migrator'); ?></li>
                    <li><?php esc_html_e('<strong>Images:</strong> Product images are downloaded from Magento and stored in your WordPress media library.', 'magento-wordpress-migrator'); ?></li>
                </ul>
            </div>
        </div>

        <!-- Progress Modal -->
        <div id="mwm-progress-modal" style="display:none;">
            <div class="mwm-modal-overlay">
                <div class="mwm-modal-content">
                    <h2><?php esc_html_e('Migration in Progress', 'magento-wordpress-migrator'); ?></h2>

                    <div class="mwm-progress-info">
                        <p class="mwm-migration-type"><strong><?php esc_html_e('Type:', 'magento-wordpress-migrator'); ?></strong> <span id="mwm-type"></span></p>
                        <p class="mwm-current-item"><strong><?php esc_html_e('Current:', 'magento-wordpress-migrator'); ?></strong> <span id="mwm-current">...</span></p>
                        <p class="mwm-time-remaining" id="mwm-time-remaining" style="display:none;"><strong><?php esc_html_e('Estimated Time Remaining:', 'magento-wordpress-migrator'); ?></strong> <span></span></p>
                    </div>

                    <div class="mwm-startup-error" id="mwm-startup-error" style="display:none;">
                        <div class="notice notice-error inline">
                            <h4><?php esc_html_e('Migration Error', 'magento-wordpress-migrator'); ?></h4>
                            <p id="mwm-startup-error-message"></p>
                        </div>
                    </div>

                    <div class="mwm-progress-bar-container">
                        <div class="mwm-progress-bar">
                            <div class="mwm-progress-fill" id="mwm-progress-fill"></div>
                        </div>
                        <div class="mwm-progress-text" id="mwm-progress-text">0%</div>
                    </div>

                    <div class="mwm-progress-details" id="mwm-progress-details">
                        <div class="mwm-progress-detail-item">
                            <span class="detail-label">Starting...</span>
                        </div>
                    </div>

                    <div class="mwm-progress-stats">
                        <div class="mwm-stat">
                            <span class="mwm-stat-label"><?php esc_html_e('Total', 'magento-wordpress-migrator'); ?></span>
                            <span class="mwm-stat-value" id="mwm-total">0</span>
                        </div>
                        <div class="mwm-stat">
                            <span class="mwm-stat-label"><?php esc_html_e('Processed', 'magento-wordpress-migrator'); ?></span>
                            <span class="mwm-stat-value" id="mwm-processed">0</span>
                        </div>
                        <div class="mwm-stat mwm-stat-success">
                            <span class="mwm-stat-label"><?php esc_html_e('Successful', 'magento-wordpress-migrator'); ?></span>
                            <span class="mwm-stat-value" id="mwm-successful">0</span>
                        </div>
                        <div class="mwm-stat mwm-stat-error">
                            <span class="mwm-stat-label"><?php esc_html_e('Failed', 'magento-wordpress-migrator'); ?></span>
                            <span class="mwm-stat-value" id="mwm-failed">0</span>
                        </div>
                    </div>

                    <div class="mwm-progress-actions">
                        <button type="button" class="button button-secondary" id="mwm-close-modal" disabled><?php esc_html_e('Close', 'magento-wordpress-migrator'); ?></button>
                        <button type="button" class="button button-link-delete" id="mwm-cancel-migration"><?php esc_html_e('Cancel Migration', 'magento-wordpress-migrator'); ?></button>
                    </div>

                    <div class="mwm-progress-errors" id="mwm-progress-errors" style="display:none;">
                        <h4><?php esc_html_e('Errors:', 'magento-wordpress-migrator'); ?></h4>
                        <ul id="mwm-error-list"></ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render progress display
     *
     * @param array $migration_data Migration data
     */
    private function render_progress_display($migration_data) {
        ?>
        <div class="mwm-migration-in-progress">
            <div class="notice notice-info">
                <p><strong><?php esc_html_e('Migration in Progress', 'magento-wordpress-migrator'); ?></strong></p>
                <p><?php esc_html_e('Please wait while the migration completes...', 'magento-wordpress-migrator'); ?></p>
            </div>

            <div class="mwm-progress-summary">
                <p><strong><?php esc_html_e('Type:', 'magento-wordpress-migrator'); ?></strong> <?php echo esc_html(ucfirst($migration_data['type'])); ?></p>
                <p><strong><?php esc_html_e('Started:', 'magento-wordpress-migrator'); ?></strong> <?php echo esc_html($migration_data['started']); ?></p>
                <p><strong><?php esc_html_e('Status:', 'magento-wordpress-migrator'); ?></strong> <?php echo esc_html(ucfirst($migration_data['status'])); ?></p>
            </div>

            <p><a href="<?php echo esc_url(admin_url('admin.php?page=magento-wp-migrator')); ?>" class="button"><?php esc_html_e('View Dashboard', 'magento-wordpress-migrator'); ?></a></p>
        </div>
        <?php
    }
}
