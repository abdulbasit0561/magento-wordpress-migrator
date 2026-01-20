<?php
/**
 * Base Migrator Class
 *
 * Provides common functionality for all migrator classes including
 * enhanced progress tracking with percentage and time remaining calculation.
 *
 * @package Magento_WordPress_Migrator
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class MWM_Migrator_Base {

    /**
     * Migration statistics
     *
     * @var array
     */
    protected $stats;

    /**
     * Constructor
     */
    public function __construct() {
        $this->stats = array(
            'total' => 0,
            'processed' => 0,
            'successful' => 0,
            'failed' => 0
        );
    }

    /**
     * Update migration progress with percentage calculation
     *
     * @param string $current_item Current item being processed
     * @param array $custom_data Custom data to add to migration data
     */
    protected function update_progress($current_item = '', $custom_data = array()) {
        $migration_data = get_option('mwm_current_migration', array());

        // Update basic stats
        $migration_data['total'] = $this->stats['total'];
        $migration_data['processed'] = $this->stats['processed'];
        $migration_data['successful'] = $this->stats['successful'];
        $migration_data['failed'] = $this->stats['failed'];
        $migration_data['current_item'] = $current_item;

        // Calculate percentage
        $percentage = 0;
        if ($this->stats['total'] > 0) {
            $percentage = round(($this->stats['processed'] / $this->stats['total']) * 100, 1);
        }
        $migration_data['percentage'] = $percentage;

        // Add estimated time remaining (after at least 5 items processed)
        if ($this->stats['processed'] >= 5 && $percentage > 0) {
            $elapsed = time() - strtotime($migration_data['started']);
            $avg_time_per_item = $elapsed / $this->stats['processed'];
            $remaining_items = $this->stats['total'] - $this->stats['processed'];
            $estimated_seconds = $avg_time_per_item * $remaining_items;

            // Format time remaining
            if ($estimated_seconds < 60) {
                $time_remaining = round($estimated_seconds) . ' ' . __('seconds', 'magento-wordpress-migrator');
            } elseif ($estimated_seconds < 3600) {
                $minutes = round($estimated_seconds / 60);
                $time_remaining = $minutes . ' ' . _n('minute', 'minutes', $minutes, 'magento-wordpress-migrator');
            } else {
                $hours = round($estimated_seconds / 3600, 1);
                $time_remaining = $hours . ' ' . _n('hour', 'hours', $hours, 'magento-wordpress-migrator');
            }
            $migration_data['time_remaining'] = $time_remaining;
        }

        // Merge any custom data
        if (!empty($custom_data)) {
            $migration_data = array_merge($migration_data, $custom_data);
        }

        update_option('mwm_current_migration', $migration_data);

        // Log progress for debugging (log every 10% or every 50 items, whichever is more frequent)
        $should_log = ($percentage > 0 && $percentage % 10 === 0) || ($this->stats['processed'] > 0 && $this->stats['processed'] % 50 === 0);
        if ($should_log || empty($current_item)) {
            error_log(sprintf('MWM: Progress %d%% (%d/%d processed, %d successful, %d failed) - %s',
                $percentage,
                $this->stats['processed'],
                $this->stats['total'],
                $this->stats['successful'],
                $this->stats['failed'],
                $current_item ? $current_item : 'Starting...'
            ));
        }
    }

    /**
     * Update statistics only
     */
    protected function update_stats() {
        $this->update_progress();
    }

    /**
     * Add error to migration data
     *
     * @param string $item Item identifier
     * @param string $error Error message
     */
    protected function add_error($item, $error) {
        $migration_data = get_option('mwm_current_migration', array());
        if (!isset($migration_data['errors'])) {
            $migration_data['errors'] = array();
        }
        $migration_data['errors'][] = array(
            'item' => $item,
            'message' => $error,
            'time' => current_time('mysql')
        );
        update_option('mwm_current_migration', $migration_data);
    }

    /**
     * Get migration statistics
     *
     * @return array Statistics
     */
    public function get_stats() {
        return $this->stats;
    }

    /**
     * Abstract method to run the migration - must be implemented by child classes
     *
     * @return array Statistics
     */
    abstract public function run();
}
