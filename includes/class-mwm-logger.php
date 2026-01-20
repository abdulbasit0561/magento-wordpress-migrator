<?php
/**
 * Logger Class
 *
 * Handles logging of migration activities
 *
 * @package Magento_WordPress_Migrator
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWM_Logger {

    /**
     * Log levels
     */
    const LEVEL_INFO = 'info';
    const LEVEL_SUCCESS = 'success';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';

    /**
     * Log an entry to database
     *
     * @param string $level Log level (info, success, warning, error)
     * @param string $action Action being performed
     * @param string $item_id ID of the item being processed
     * @param string $message Log message
     * @return int|false Log entry ID
     */
    public static function log($level = 'info', $action = '', $item_id = '', $message = '') {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mwm_migration_logs';

        $result = $wpdb->insert(
            $table_name,
            array(
                'migration_type' => $action,
                'item_id' => substr($item_id, 0, 255),
                'item_type' => '',
                'status' => $level,
                'message' => substr($message, 0, 1000), // Limit message length
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Log migration start
     *
     * @param string $type Migration type
     * @return int|false Log entry ID
     */
    public static function log_migration_start($type) {
        return self::log(
            self::LEVEL_INFO,
            $type . '_start',
            '',
            sprintf(__('Started %s migration', 'magento-wordpress-migrator'), $type)
        );
    }

    /**
     * Log migration complete
     *
     * @param string $type Migration type
     * @param array $stats Statistics
     * @return int|false Log entry ID
     */
    public static function log_migration_complete($type, $stats = array()) {
        $message = sprintf(
            __('Completed %s migration. Processed: %d, Successful: %d, Failed: %d',
                'magento-wordpress-migrator'),
            $type,
            $stats['processed'] ?? 0,
            $stats['successful'] ?? 0,
            $stats['failed'] ?? 0
        );

        return self::log(self::LEVEL_INFO, $type . '_complete', '', $message);
    }

    /**
     * Log item success
     *
     * @param string $action Action performed
     * @param string $item_id Item ID
     * @param string $message Success message
     * @return int|false Log entry ID
     */
    public static function log_success($action, $item_id, $message) {
        return self::log(self::LEVEL_SUCCESS, $action, $item_id, $message);
    }

    /**
     * Log item error
     *
     * @param string $action Action performed
     * @param string $item_id Item ID
     * @param string $message Error message
     * @return int|false Log entry ID
     */
    public static function log_error($action, $item_id, $message) {
        return self::log(self::LEVEL_ERROR, $action, $item_id, $message);
    }

    /**
     * Get logs from database
     *
     * @param int $limit Number of logs to retrieve
     * @param int $offset Offset
     * @param array $filters Filters to apply
     * @return array Logs
     */
    public static function get_logs($limit = 100, $offset = 0, $filters = array()) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mwm_migration_logs';

        $where = "1=1";
        $params = array();

        if (!empty($filters['level'])) {
            $where .= " AND status = %s";
            $params[] = $filters['level'];
        }

        if (!empty($filters['action'])) {
            $where .= " AND migration_type LIKE %s";
            $params[] = '%' . $filters['action'] . '%';
        }

        if (!empty($filters['item_id'])) {
            $where .= " AND item_id = %s";
            $params[] = $filters['item_id'];
        }

        if (!empty($filters['date_from'])) {
            $where .= " AND created_at >= %s";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where .= " AND created_at <= %s";
            $params[] = $filters['date_to'];
        }

        if (!empty($params)) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                array_merge($params, array($limit, $offset))
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                array($limit, $offset)
            );
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Get total log count
     *
     * @param array $filters Filters
     * @return int Total count
     */
    public static function get_log_count($filters = array()) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mwm_migration_logs';

        $where = "1=1";
        $params = array();

        if (!empty($filters['level'])) {
            $where .= " AND status = %s";
            $params[] = $filters['level'];
        }

        if (!empty($filters['action'])) {
            $where .= " AND migration_type LIKE %s";
            $params[] = '%' . $filters['action'] . '%';
        }

        if (!empty($params)) {
            $query = $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE {$where}", $params);
        } else {
            $query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where}";
        }

        return (int) $wpdb->get_var($query);
    }

    /**
     * Clear old logs
     *
     * @param int $days Number of days to keep
     * @return int|false Number of rows deleted
     */
    public static function clear_old_logs($days = 30) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mwm_migration_logs';

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }

    /**
     * Clear all logs
     *
     * @return int|false Number of rows deleted
     */
    public static function clear_all_logs() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mwm_migration_logs';

        return $wpdb->query("TRUNCATE TABLE {$table_name}");
    }

    /**
     * Get log counts by level
     *
     * @return array Counts
     */
    public static function get_log_counts() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mwm_migration_logs';

        $counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$table_name} GROUP BY status",
            ARRAY_A
        );

        $result = array(
            'info' => 0,
            'success' => 0,
            'warning' => 0,
            'error' => 0,
            'total' => 0
        );

        foreach ($counts as $count) {
            if (isset($result[$count['status']])) {
                $result[$count['status']] = (int) $count['count'];
            }
            $result['total'] += (int) $count['count'];
        }

        return $result;
    }

    /**
     * Get recent errors
     *
     * @param int $limit Number of errors to retrieve
     * @return array Error logs
     */
    public static function get_recent_errors($limit = 10) {
        return self::get_logs($limit, 0, array('level' => self::LEVEL_ERROR));
    }

    /**
     * Get migration statistics
     *
     * @param string $migration_type Migration type
     * @return array Statistics
     */
    public static function get_migration_stats($migration_type = '') {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mwm_migration_logs';

        $where = !empty($migration_type) ? $wpdb->prepare("WHERE migration_type LIKE %s", $migration_type . '%') : '';

        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed,
                MAX(created_at) as last_run
            FROM {$table_name}
            {$where}",
            ARRAY_A
        );

        return array(
            'total' => (int) $stats['total'],
            'successful' => (int) $stats['successful'],
            'failed' => (int) $stats['failed'],
            'last_run' => $stats['last_run']
        );
    }

    /**
     * Log to WordPress error log (for debugging)
     *
     * @param string $message Message to log
     */
    public static function debug_log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Magento Migrator] ' . $message);
        }
    }
}
