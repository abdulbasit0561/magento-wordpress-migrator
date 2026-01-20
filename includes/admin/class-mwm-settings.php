<?php
/**
 * Settings Page Class
 *
 * Handles settings page configuration and saving
 *
 * @package Magento_WordPress_Migrator
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWM_Settings {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_mwm_save_settings', array($this, 'save_settings'));
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('mwm_settings', 'mwm_settings', array($this, 'sanitize_settings'));

        // Connection Mode Selection
        add_settings_section(
            'mwm_connection_mode',
            __('Connection Mode', 'magento-wordpress-migrator'),
            array($this, 'render_connection_mode_section'),
            'magento-wp-migrator-settings'
        );

        add_settings_field(
            'connection_mode',
            __('Connection Mode', 'magento-wordpress-migrator'),
            array($this, 'render_connection_mode_field'),
            'magento-wp-migrator-settings',
            'mwm_connection_mode'
        );

        // Connector Configuration Section
        add_settings_section(
            'mwm_connector_settings',
            __('Magento Connector Configuration', 'magento-wordpress-migrator'),
            array($this, 'render_connector_section'),
            'magento-wp-migrator-settings'
        );

        add_settings_field(
            'connector_url',
            __('Connector URL', 'magento-wordpress-migrator'),
            array($this, 'render_connector_url_field'),
            'magento-wp-migrator-settings',
            'mwm_connector_settings'
        );

        add_settings_field(
            'connector_api_key',
            __('Connector API Key', 'magento-wordpress-migrator'),
            array($this, 'render_connector_api_key_field'),
            'magento-wp-migrator-settings',
            'mwm_connector_settings'
        );

        // API Configuration Section
        add_settings_section(
            'mwm_api_settings',
            __('Magento REST API Configuration', 'magento-wordpress-migrator'),
            array($this, 'render_api_section'),
            'magento-wp-migrator-settings'
        );

        add_settings_field(
            'store_url',
            __('Magento Store URL', 'magento-wordpress-migrator'),
            array($this, 'render_store_url_field'),
            'magento-wp-migrator-settings',
            'mwm_api_settings'
        );

        add_settings_field(
            'api_version',
            __('API Version', 'magento-wordpress-migrator'),
            array($this, 'render_api_version_field'),
            'magento-wp-migrator-settings',
            'mwm_api_settings'
        );

        add_settings_field(
            'consumer_key',
            __('Consumer Key', 'magento-wordpress-migrator'),
            array($this, 'render_consumer_key_field'),
            'magento-wp-migrator-settings',
            'mwm_api_settings'
        );

        add_settings_field(
            'consumer_secret',
            __('Consumer Secret', 'magento-wordpress-migrator'),
            array($this, 'render_consumer_secret_field'),
            'magento-wp-migrator-settings',
            'mwm_api_settings'
        );

        add_settings_field(
            'access_token',
            __('Access Token', 'magento-wordpress-migrator'),
            array($this, 'render_access_token_field'),
            'magento-wp-migrator-settings',
            'mwm_api_settings'
        );

        add_settings_field(
            'access_token_secret',
            __('Access Token Secret', 'magento-wordpress-migrator'),
            array($this, 'render_access_token_secret_field'),
            'magento-wp-migrator-settings',
            'mwm_api_settings'
        );

        // Advanced: Database Connection (Optional)
        add_settings_section(
            'mwm_db_settings',
            __('Advanced: Direct Database Access', 'magento-wordpress-migrator'),
            array($this, 'render_db_section'),
            'magento-wp-migrator-settings'
        );

        add_settings_field(
            'use_database',
            __('Use Database Connection', 'magento-wordpress-migrator'),
            array($this, 'render_use_database_field'),
            'magento-wp-migrator-settings',
            'mwm_db_settings'
        );

        add_settings_field(
            'db_host',
            __('Database Host', 'magento-wordpress-migrator'),
            array($this, 'render_db_host_field'),
            'magento-wp-migrator-settings',
            'mwm_db_settings'
        );

        add_settings_field(
            'db_name',
            __('Database Name', 'magento-wordpress-migrator'),
            array($this, 'render_db_name_field'),
            'magento-wp-migrator-settings',
            'mwm_db_settings'
        );

        add_settings_field(
            'db_user',
            __('Database User', 'magento-wordpress-migrator'),
            array($this, 'render_db_user_field'),
            'magento-wp-migrator-settings',
            'mwm_db_settings'
        );

        add_settings_field(
            'db_password',
            __('Database Password', 'magento-wordpress-migrator'),
            array($this, 'render_db_password_field'),
            'magento-wp-migrator-settings',
            'mwm_db_settings'
        );
    }

    /**
     * Sanitize settings
     *
     * @param array $input Input settings
     * @return array Sanitized settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        // Connection Mode
        $sanitized['connection_mode'] = sanitize_text_field($input['connection_mode'] ?? 'connector');

        // Connector Settings
        $sanitized['connector_url'] = esc_url_raw($input['connector_url'] ?? '');
        $sanitized['connector_api_key'] = sanitize_text_field($input['connector_api_key'] ?? '');

        // API Settings
        $sanitized['store_url'] = esc_url_raw($input['store_url'] ?? '');
        $sanitized['api_version'] = sanitize_text_field($input['api_version'] ?? 'V1');
        $sanitized['consumer_key'] = sanitize_text_field($input['consumer_key'] ?? '');
        $sanitized['consumer_secret'] = $input['consumer_secret'] ?? ''; // Don't sanitize secrets
        $sanitized['access_token'] = sanitize_text_field($input['access_token'] ?? '');
        $sanitized['access_token_secret'] = $input['access_token_secret'] ?? '';

        // Database Settings (Advanced)
        $sanitized['use_database'] = isset($input['use_database']) ? 1 : 0;
        $sanitized['db_host'] = sanitize_text_field($input['db_host'] ?? '');
        $sanitized['db_name'] = sanitize_text_field($input['db_name'] ?? '');
        $sanitized['db_user'] = sanitize_text_field($input['db_user'] ?? '');
        $sanitized['db_password'] = $input['db_password'] ?? '';
        $sanitized['db_port'] = isset($input['db_port']) ? intval($input['db_port']) : 3306;
        $sanitized['table_prefix'] = sanitize_text_field($input['table_prefix'] ?? '');

        return $sanitized;
    }

    /**
     * Render Connection Mode section
     */
    public function render_connection_mode_section() {
        echo '<p>' . esc_html__('Select how you want to connect to your Magento store.', 'magento-wordpress-migrator') . '</p>';
        echo '<p class="description"><strong>' . esc_html__('Recommended:', 'magento-wordpress-migrator') . '</strong> ' . esc_html__('Use the Connector for the easiest setup. Just upload a single file to your Magento installation.', 'magento-wordpress-migrator') . '</p>';
    }

    /**
     * Render Connection Mode field
     */
    public function render_connection_mode_field() {
        $settings = get_option('mwm_settings', array());
        $mode = $settings['connection_mode'] ?? 'connector';
        echo '<select name="mwm_settings[connection_mode]" id="mwm-connection-mode">';
        echo '<option value="connector"' . selected($mode, 'connector', false) . '>' . esc_html__('Connector (Recommended)', 'magento-wordpress-migrator') . '</option>';
        echo '<option value="api"' . selected($mode, 'api', false) . '>' . esc_html__('REST API', 'magento-wordpress-migrator') . '</option>';
        echo '<option value="database"' . selected($mode, 'database', false) . '>' . esc_html__('Direct Database', 'magento-wordpress-migrator') . '</option>';
        echo '</select>';
        echo '<p class="description">' . esc_html__('Connector is easiest. REST API requires OAuth setup. Database requires direct access.', 'magento-wordpress-migrator') . '</p>';
    }

    /**
     * Render Connector section
     */
    public function render_connector_section() {
        echo '<p>' . esc_html__('Configure the Magento connector for easy migration.', 'magento-wordpress-migrator') . '</p>';
        echo '<p class="description">' . esc_html__('First, upload the magento-connector.php file (included in this plugin) to your Magento root directory.', 'magento-wordpress-migrator') . '</p>';
        echo '<p class="description">' . esc_html__('Then visit: https://your-magento-site.com/magento-connector.php?generate_key', 'magento-wordpress-migrator') . '</p>';
        echo '<p class="description">' . esc_html__('Copy the generated API key and enter it below.', 'magento-wordpress-migrator') . '</p>';
    }

    /**
     * Render Connector URL field
     */
    public function render_connector_url_field() {
        $settings = get_option('mwm_settings', array());
        $value = $settings['connector_url'] ?? '';
        echo '<input type="url" name="mwm_settings[connector_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://your-magento-site.com/magento-connector.php">';
        echo '<p class="description">' . esc_html__('Full URL to the connector file on your Magento server', 'magento-wordpress-migrator') . '</p>';
    }

    /**
     * Render Connector API Key field
     */
    public function render_connector_api_key_field() {
        $settings = get_option('mwm_settings', array());
        $value = $settings['connector_api_key'] ?? '';
        echo '<input type="password" name="mwm_settings[connector_api_key]" value="' . esc_attr($value) . '" class="regular-text" placeholder="Enter API key from connector setup">';
        echo '<p class="description">' . esc_html__('API key generated when you set up the connector', 'magento-wordpress-migrator') . '</p>';

        // Test connection button
        echo '<button type="button" id="mwm-test-connector" class="button button-secondary" style="margin-top: 10px;">';
        esc_html_e('Test Connector Connection', 'magento-wordpress-migrator');
        echo '</button>';

        echo '<span id="mwm-connector-result" style="margin-left: 10px;"></span>';
    }

    /**
     * Render API section
     */
    public function render_api_section() {
        echo '<p>' . esc_html__('Enter your Magento REST API credentials below. These are required to connect to your Magento store.', 'magento-wordpress-migrator') . '</p>';
        echo '<p class="description">' . esc_html__('You can create API credentials in Magento Admin → System → Integrations → Add New Integration.', 'magento-wordpress-migrator') . '</p>';
        echo '<p class="description"><strong>' . esc_html__('Required Permissions:', 'magento-wordpress-migrator') . '</strong> ' . esc_html__('Products, Categories, Customers, Orders (Read/Write)', 'magento-wordpress-migrator') . '</p>';
    }

    /**
     * Render Store URL field
     */
    public function render_store_url_field() {
        $settings = get_option('mwm_settings', array());
        $value = $settings['store_url'] ?? '';
        echo '<input type="url" name="mwm_settings[store_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://yourstore.com" required>';
        echo '<p class="description">' . esc_html__('Full URL of your Magento store (e.g., https://mystore.com)', 'magento-wordpress-migrator') . '</p>';
    }

    /**
     * Render API Version field
     */
    public function render_api_version_field() {
        $settings = get_option('mwm_settings', array());
        $value = $settings['api_version'] ?? 'V1';
        echo '<select name="mwm_settings[api_version]">';
        echo '<option value="V1"' . selected($value, 'V1', false) . '>V1</option>';
        echo '<option value="V2"' . selected($value, 'V2', false) . '>V2</option>';
        echo '</select>';
        echo '<p class="description">' . esc_html__('Magento API version (usually V1 or V2)', 'magento-wordpress-migrator') . '</p>';
    }

    /**
     * Render Consumer Key field
     */
    public function render_consumer_key_field() {
        $settings = get_option('mwm_settings', array());
        $value = $settings['consumer_key'] ?? '';
        echo '<input type="text" name="mwm_settings[consumer_key]" value="' . esc_attr($value) . '" class="regular-text" required>';
        echo '<p class="description">' . esc_html__('Consumer Key from Magento integration', 'magento-wordpress-migrator') . '</p>';
    }

    /**
     * Render Consumer Secret field
     */
    public function render_consumer_secret_field() {
        $settings = get_option('mwm_settings', array());
        $value = $settings['consumer_secret'] ?? '';
        echo '<input type="password" name="mwm_settings[consumer_secret]" value="' . esc_attr($value) . '" class="regular-text" required>';
        echo '<p class="description">' . esc_html__('Consumer Secret from Magento integration', 'magento-wordpress-migrator') . '</p>';
    }

    /**
     * Render Access Token field
     */
    public function render_access_token_field() {
        $settings = get_option('mwm_settings', array());
        $value = $settings['access_token'] ?? '';
        echo '<input type="text" name="mwm_settings[access_token]" value="' . esc_attr($value) . '" class="regular-text" required>';
        echo '<p class="description">' . esc_html__('Access Token from Magento integration', 'magento-wordpress-migrator') . '</p>';
    }

    /**
     * Render Access Token Secret field
     */
    public function render_access_token_secret_field() {
        $settings = get_option('mwm_settings', array());
        $value = $settings['access_token_secret'] ?? '';
        echo '<input type="password" name="mwm_settings[access_token_secret]" value="' . esc_attr($value) . '" class="regular-text" required>';
        echo '<p class="description">' . esc_html__('Access Token Secret from Magento integration', 'magento-wordpress-migrator') . '</p>';

        // Test connection button
        echo '<button type="button" id="mwm-test-connection" class="button button-secondary" style="margin-top: 10px;">';
        esc_html_e('Test API Connection', 'magento-wordpress-migrator');
        echo '</button>';

        echo '<span id="mwm-connection-result" style="margin-left: 10px;"></span>';
    }

    /**
     * Render database section
     */
    public function render_db_section() {
        echo '<p>' . esc_html__('Optionally connect directly to Magento database for advanced use cases.', 'magento-wordpress-migrator') . '</p>';
        echo '<p class="description">' . esc_html__('This requires direct database access and is not recommended for most users. Use the REST API connection above.', 'magento-wordpress-migrator') . '</p>';
    }

    /**
     * Render Use Database field
     */
    public function render_use_database_field() {
        $settings = get_option('mwm_settings', array());
        $checked = isset($settings['use_database']) && $settings['use_database'] == 1 ? 'checked' : '';
        echo '<label>';
        echo '<input type="checkbox" name="mwm_settings[use_database]" value="1" ' . $checked . '>';
        echo esc_html__('Use direct database connection instead of REST API', 'magento-wordpress-migrator');
        echo '</label>';
        echo '<p class="description">' . esc_html__('Enable this only if you need direct database access. REST API is recommended.', 'magento-wordpress-migrator') . '</p>';
    }

    /**
     * Render database host field
     */
    public function render_db_host_field() {
        $settings = get_option('mwm_settings', array());
        $value = $settings['db_host'] ?? '';
        echo '<input type="text" name="mwm_settings[db_host]" value="' . esc_attr($value) . '" class="regular-text" placeholder="localhost">';
        echo '<p class="description">' . esc_html__('Usually "localhost" or an IP address', 'magento-wordpress-migrator') . '</p>';
    }

    /**
     * Render database name field
     */
    public function render_db_name_field() {
        $settings = get_option('mwm_settings', array());
        $value = $settings['db_name'] ?? '';
        echo '<input type="text" name="mwm_settings[db_name]" value="' . esc_attr($value) . '" class="regular-text" placeholder="magento_db">';
    }

    /**
     * Render database user field
     */
    public function render_db_user_field() {
        $settings = get_option('mwm_settings', array());
        $value = $settings['db_user'] ?? '';
        echo '<input type="text" name="mwm_settings[db_user]" value="' . esc_attr($value) . '" class="regular-text" placeholder="magento_user">';
    }

    /**
     * Render database password field
     */
    public function render_db_password_field() {
        $settings = get_option('mwm_settings', array());
        $value = $settings['db_password'] ?? '';
        echo '<input type="password" name="mwm_settings[db_password]" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">' . esc_html__('Leave empty to keep existing password', 'magento-wordpress-migrator') . '</p>';
    }

    /**
     * Save settings
     */
    public function save_settings() {
        check_admin_referer('mwm_settings_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'magento-wordpress-migrator'));
        }

        if (isset($_POST['mwm_settings'])) {
            $settings = $this->sanitize_settings($_POST['mwm_settings']);
            $existing = get_option('mwm_settings', array());

            // Keep existing sensitive values if not provided
            if (empty($settings['consumer_secret']) && !empty($existing['consumer_secret'])) {
                $settings['consumer_secret'] = $existing['consumer_secret'];
            }
            if (empty($settings['access_token_secret']) && !empty($existing['access_token_secret'])) {
                $settings['access_token_secret'] = $existing['access_token_secret'];
            }
            if (empty($settings['db_password']) && !empty($existing['db_password'])) {
                $settings['db_password'] = $existing['db_password'];
            }

            update_option('mwm_settings', $settings);
        }

        wp_redirect(admin_url('admin.php?page=magento-wp-migrator-settings&settings-updated=true'));
        exit;
    }
}
