<?php
/**
 * Category Migrator Class
 *
 * Handles migrating categories from Magento to WooCommerce via Connector
 *
 * @package Magento_WordPress_Migrator
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWM_Migrator_Categories {

    /**
     * Connector client
     *
     * @var MWM_Connector_Client
     */
    private $connector;

    /**
     * Migration statistics
     *
     * @var array
     */
    private $stats;

    /**
     * Category map (Magento ID => WordPress term_id)
     *
     * @var array
     */
    private $category_map = array();

    /**
     * Constructor
     *
     * @param MWM_Connector_Client $connector Connector client
     */
    public function __construct($connector) {
        if ($connector === null) {
            throw new Exception(__('Connector client is required', 'magento-wordpress-migrator'));
        }
        $this->connector = $connector;
        $this->stats = array(
            'total' => 0,
            'processed' => 0,
            'successful' => 0,
            'failed' => 0
        );
        error_log('MWM Categories Migrator: Using Connector mode');
    }

    /**
     * Run the category migration
     *
     * @return array Statistics
     */
    public function run() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            throw new Exception(__('WooCommerce is not installed or active', 'magento-wordpress-migrator'));
        }

        MWM_Logger::log_migration_start('categories');

        error_log('MWM: Starting category migration via Connector');

        try {
            // Get all categories
            $categories = $this->get_categories();
            $this->stats['total'] = count($categories);
            error_log('MWM: Total categories to migrate: ' . $this->stats['total']);

            MWM_Logger::log('info', 'category_import_count', '', sprintf(
                __('Found %d categories to migrate', 'magento-wordpress-migrator'),
                $this->stats['total']
            ));

            if ($this->stats['total'] === 0) {
                error_log('MWM: No categories found to migrate');
                MWM_Logger::log('info', 'no_categories', '', __('No categories found', 'magento-wordpress-migrator'));
                return $this->stats;
            }

            // Update migration progress
            $this->update_progress(__('Starting category migration...', 'magento-wordpress-migrator'));

            // Sort categories by level to ensure parents are imported first
            usort($categories, function($a, $b) {
                $level_a = isset($a['level']) ? intval($a['level']) : 0;
                $level_b = isset($b['level']) ? intval($b['level']) : 0;
                return $level_a - $level_b;
            });

            // Migrate categories
            foreach ($categories as $index => $category) {
                // Check if migration is cancelled
                $migration_data = get_option('mwm_current_migration', array());
                if ($migration_data['status'] === 'cancelled') {
                    error_log('MWM: Category migration cancelled');
                    return $this->stats;
                }

                error_log("MWM: Migrating category {$index}/{$this->stats['total']}");

                try {
                    $this->migrate_category($category);
                } catch (Exception $e) {
                    error_log('MWM: Error migrating category: ' . $e->getMessage());
                    $this->stats['failed']++;
                    MWM_Logger::log('error', 'category_migration_failed', '', $e->getMessage());
                }

                $this->stats['processed']++;
            }

            MWM_Logger::log_migration_complete('categories', $this->stats);
            error_log('MWM: Category migration completed - Success: ' . $this->stats['successful'] . ', Failed: ' . $this->stats['failed']);

        } catch (Exception $e) {
            error_log('MWM: Category migration failed with error: ' . $e->getMessage());
            MWM_Logger::log('error', 'category_migration_error', '', $e->getMessage());
            throw $e;
        }

        return $this->stats;
    }

    /**
     * Get categories from connector
     *
     * @return array Categories
     */
    private function get_categories() {
        error_log('MWM: Fetching categories via connector');
        $result = $this->connector->get_categories();

        if (is_wp_error($result)) {
            error_log('MWM: Connector error: ' . $result->get_error_message());
            return array();
        }

        // Handle different response structures from connector
        $categories = $result['categories'] ?? array();
        error_log('MWM: Connector returned ' . count($categories) . ' categories');

        // If categories is empty but result has data, log it for debugging
        if (empty($categories) && !empty($result)) {
            error_log('MWM: Connector result keys: ' . implode(', ', array_keys($result)));
            error_log('MWM: Full result: ' . print_r($result, true));
        }

        // Normalize category IDs - ensure entity_id field exists
        // Connector returns 'id' but migrate_category() expects 'entity_id'
        $normalized = array();
        foreach ($categories as $category) {
            if (!isset($category['entity_id']) && isset($category['id'])) {
                $category['entity_id'] = $category['id'];
            }
            $normalized[] = $category;
        }

        error_log('MWM: Returning ' . count($normalized) . ' categories after normalization');
        return $normalized;
    }

    /**
     * Migrate single category
     *
     * @param array $magento_category Magento category data
     */
    private function migrate_category($magento_category) {
        try {
            $magento_id = $magento_category['entity_id'];
            $name = $magento_category['name'] ?? $magento_category['entity_id'];

            if (empty($name)) {
                throw new Exception(__('Category name is missing', 'magento-wordpress-migrator'));
            }

            $this->update_progress(__('Migrating category:', 'magento-wordpress-migrator') . ' ' . $name);

            // Check if category already exists
            $existing_term_id = $this->get_category_by_magento_id($magento_id);

            // Determine parent
            $parent_id = 0;
            if (isset($magento_category['parent_id']) && $magento_category['parent_id'] > 2) {
                if (isset($this->category_map[$magento_category['parent_id']])) {
                    $parent_id = $this->category_map[$magento_category['parent_id']];
                } else {
                    $parent_term_id = $this->get_category_by_magento_id($magento_category['parent_id']);
                    if ($parent_term_id) {
                        $parent_id = $parent_term_id;
                    }
                }
            }

            // Get category description and slug
            $description = $this->get_category_description($magento_category);
            $slug = $this->get_category_slug($magento_category);

            if ($existing_term_id) {
                // Update existing category
                $result = wp_update_term($existing_term_id, 'product_cat', array(
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $description,
                    'parent' => $parent_id
                ));

                if (is_wp_error($result)) {
                    throw new Exception($result->get_error_message());
                }

                $term_id = $result['term_id'];
                $action = 'category_update';

            } else {
                // Create new category
                $result = wp_insert_term($name, 'product_cat', array(
                    'slug' => $slug,
                    'description' => $description,
                    'parent' => $parent_id
                ));

                if (is_wp_error($result)) {
                    throw new Exception($result->get_error_message());
                }

                $term_id = $result['term_id'];
                $action = 'category_create';
            }

            // Store Magento category ID
            update_term_meta($term_id, '_magento_category_id', $magento_id);

            // Store category metadata
            if (isset($magento_category['level'])) {
                update_term_meta($term_id, '_magento_category_level', $magento_category['level']);
            }

            if (isset($magento_category['is_active'])) {
                update_term_meta($term_id, '_magento_is_active', $magento_category['is_active']);
            }

            if (isset($magento_category['position'])) {
                update_term_meta($term_id, '_magento_position', $magento_category['position']);
            }

            // Store in map for child categories
            $this->category_map[$magento_id] = $term_id;

            $this->stats['successful']++;
            $this->stats['processed']++;
            $this->update_progress(__('Migrated:', 'magento-wordpress-migrator') . ' ' . $name);
            $this->update_stats();

            MWM_Logger::log_success($action, $magento_id, sprintf(
                __('Category migrated successfully: %s', 'magento-wordpress-migrator'),
                $name
            ));

        } catch (Exception $e) {
            $this->stats['failed']++;
            $this->stats['processed']++;
            $this->update_stats();
            $this->add_error($magento_category['entity_id'], $e->getMessage());

            MWM_Logger::log_error('category_import_failed', $magento_category['entity_id'], $e->getMessage());
        }
    }

    /**
     * Get category by Magento ID
     *
     * @param int $magento_id Magento category ID
     * @return int|false Term ID or false
     */
    private function get_category_by_magento_id($magento_id) {
        $terms = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'meta_key' => '_magento_category_id',
            'meta_value' => $magento_id
        ));

        if (!empty($terms) && !is_wp_error($terms)) {
            return $terms[0]->term_id;
        }

        return false;
    }

    /**
     * Get category description
     *
     * @param array $category Category data
     * @return string Description
     */
    private function get_category_description($category) {
        // Try to get description from various fields
        if (isset($category['description'])) {
            return $category['description'];
        }

        if (isset($category['custom_attributes'])) {
            foreach ($category['custom_attributes'] as $attr) {
                if ($attr['attribute_code'] === 'description') {
                    return $attr['value'];
                }
            }
        }

        return '';
    }

    /**
     * Get category slug
     *
     * @param array $category Category data
     * @return string Slug
     */
    private function get_category_slug($category) {
        // Try to get URL key
        if (isset($category['url_key'])) {
            return sanitize_title($category['url_key']);
        }

        if (isset($category['url_path'])) {
            return sanitize_title($category['url_path']);
        }

        return sanitize_title($category['name'] ?? 'category');
    }

    /**
     * Update migration progress
     *
     * @param string $current_item Current item being processed
     */
    private function update_progress($current_item = '') {
        $migration_data = get_option('mwm_current_migration', array());

        // Ensure total is at least equal to processed to avoid >100% progress
        if ($this->stats['processed'] > $this->stats['total']) {
            $this->stats['total'] = $this->stats['processed'];
        }

        $migration_data['total'] = $this->stats['total'];
        $migration_data['processed'] = $this->stats['processed'];
        $migration_data['successful'] = $this->stats['successful'];
        $migration_data['failed'] = $this->stats['failed'];
        $migration_data['current_item'] = $current_item;

        // Calculate percentage - cap at 100%
        $percentage = 0;
        if ($this->stats['total'] > 0) {
            $percentage = min(100, round(($this->stats['processed'] / $this->stats['total']) * 100, 1));
        }
        $migration_data['percentage'] = $percentage;

        // Add estimated time remaining
        if (!empty($migration_data['started']) && $this->stats['processed'] > 0) {
            $started_time = is_numeric($migration_data['started']) ? $migration_data['started'] : strtotime($migration_data['started']);
            $elapsed = time() - $started_time;

            // Only calculate if we have valid elapsed time
            if ($elapsed > 0) {
                $avg_time_per_item = $elapsed / $this->stats['processed'];
                $remaining_items = max(0, $this->stats['total'] - $this->stats['processed']);
                $estimated_seconds = $avg_time_per_item * $remaining_items;

                // Format time remaining - handle negative values
                if ($estimated_seconds < 0) {
                    $time_remaining = __('Calculating...', 'magento-wordpress-migrator');
                } elseif ($estimated_seconds < 60) {
                    $time_remaining = round($estimated_seconds) . ' ' . __('seconds', 'magento-wordpress-migrator');
                } elseif ($estimated_seconds < 3600) {
                    $minutes = round($estimated_seconds / 60);
                    $time_remaining = $minutes . ' ' . _n('minute', 'minutes', $minutes, 'magento-wordpress-migrator');
                } else {
                    $hours = round($estimated_seconds / 3600, 1);
                    $time_remaining = $hours . ' ' . _n('hour', 'hours', $hours, 'magento-wordpress-migrator');
                }
                $migration_data['time_remaining'] = $time_remaining;
            } else {
                $migration_data['time_remaining'] = __('Calculating...', 'magento-wordpress-migrator');
            }
        } else {
            $migration_data['time_remaining'] = __('Calculating...', 'magento-wordpress-migrator');
        }

        update_option('mwm_current_migration', $migration_data);

        // Log progress for debugging
        error_log(sprintf('MWM: Categories Progress %d%% (%d/%d processed) - %s',
            $percentage,
            $this->stats['processed'],
            $this->stats['total'],
            $current_item
        ));
    }

    /**
     * Update statistics
     */
    private function update_stats() {
        $this->update_progress();
    }

    /**
     * Add error to migration data
     *
     * @param string $item Item identifier
     * @param string $error Error message
     */
    private function add_error($item, $error) {
        $migration_data = get_option('mwm_current_migration', array());
        $migration_data['errors'][] = array(
            'item' => $item,
            'message' => $error
        );
        update_option('mwm_current_migration', $migration_data);
    }
}
