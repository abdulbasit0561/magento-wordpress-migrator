<?php
/**
 * Magento Database Connection Class
 *
 * Handles direct database connection to Magento
 *
 * @package Magento_WordPress_Migrator
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWM_DB {

    /**
     * Database connection
     *
     * @var mysqli|PDO|null
     */
    private $connection = null;

    /**
     * Magento database host
     *
     * @var string
     */
    private $db_host;

    /**
     * Magento database name
     *
     * @var string
     */
    private $db_name;

    /**
     * Magento database user
     *
     * @var string
     */
    private $db_user;

    /**
     * Magento database password
     *
     * @var string
     */
    private $db_password;

    /**
     * Magento database port
     *
     * @var int
     */
    private $db_port;

    /**
     * Magento table prefix
     *
     * @var string
     */
    private $table_prefix;

    /**
     * Magento version (1 or 2)
     *
     * @var int|null
     */
    private $magento_version = null;

    /**
     * Constructor
     *
     * @param string $db_host Database host
     * @param string $db_name Database name
     * @param string $db_user Database username
     * @param string $db_password Database password
     * @param int $db_port Database port
     * @param string $table_prefix Table prefix
     */
    public function __construct($db_host, $db_name, $db_user, $db_password, $db_port = 3306, $table_prefix = '') {
        $this->db_host = $db_host;
        $this->db_name = $db_name;
        $this->db_user = $db_user;
        $this->db_password = $db_password;
        $this->db_port = $db_port;
        $this->table_prefix = $table_prefix;

        $this->connect();
        $this->detect_magento_version();
    }

    /**
     * Connect to Magento database
     *
     * @throws Exception If connection fails
     */
    private function connect() {
        // Use mysqli for connection
        $this->connection = new mysqli(
            $this->db_host,
            $this->db_user,
            $this->db_password,
            $this->db_name,
            $this->db_port
        );

        if ($this->connection->connect_error) {
            throw new Exception(
                sprintf(
                    __('Database connection failed: %s', 'magento-wordpress-migrator'),
                    $this->connection->connect_error
                )
            );
        }

        // Set charset
        $this->connection->set_charset('utf8mb4');
    }

    /**
     * Detect Magento version (1.x or 2.x)
     */
    private function detect_magento_version() {
        try {
            // Check for Magento 2 core_config_data table
            $result = $this->query("SHOW TABLES LIKE '%core_config_data%'");

            if ($result && $result->num_rows > 0) {
                // Check if it's Magento 2 by looking for setup_module table
                $result2 = $this->query("SHOW TABLES LIKE '%setup_module%'");
                $this->magento_version = ($result2 && $result2->num_rows > 0) ? 2 : 1;
            } else {
                $this->magento_version = 1;
            }
        } catch (Exception $e) {
            $this->magento_version = 1;
        }
    }

    /**
     * Get Magento table name with prefix
     *
     * @param string $table Table name
     * @return string Full table name
     */
    public function get_table($table) {
        return $this->table_prefix . $table;
    }

    /**
     * Test database connection
     *
     * @return array Result with success status and message
     */
    public function test_connection() {
        try {
            // Try to query core_config_data
            $table = $this->get_table('core_config_data');
            $result = $this->query("SELECT * FROM {$table} LIMIT 1");

            if (!$result) {
                return array(
                    'success' => false,
                    'message' => __('Could not read Magento configuration table', 'magento-wordpress-migrator')
                );
            }

            // Get Magento version
            $version = $this->get_magento_version();

            return array(
                'success' => true,
                'message' => sprintf(
                    __('Successfully connected to Magento %s.x database', 'magento-wordpress-migrator'),
                    $version
                ),
                'version' => $version
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Get Magento version
     *
     * @return int Magento version (1 or 2)
     */
    public function get_magento_version() {
        return $this->magento_version;
    }

    /**
     * Execute a query
     *
     * @param string $sql SQL query
     * @param bool $prepare Whether to prepare the statement (default: false)
     * @param array $params Parameters for prepared statement
     * @return mysqli_result|bool Result
     * @throws Exception If query fails
     */
    public function query($sql, $prepare = false, $params = array()) {
        if ($prepare && !empty($params)) {
            $stmt = $this->connection->prepare($sql);

            if (!$stmt) {
                throw new Exception($this->connection->error);
            }

            $types = '';
            $bind_params = array();

            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
                $bind_params[] = $param;
            }

            array_unshift($bind_params, $types);

            call_user_func_array(array($stmt, 'bind_param'), $this->refValues($bind_params));

            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();

            return $result;
        } else {
            $result = $this->connection->query($sql);

            if (!$result) {
                throw new Exception($this->connection->error);
            }

            return $result;
        }
    }

    /**
     * Get all results from query
     *
     * @param string $sql SQL query
     * @return array Results
     */
    public function get_results($sql) {
        $result = $this->query($sql);
        $rows = array();

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Get single row from query
     *
     * @param string $sql SQL query
     * @return array|null Row
     */
    public function get_row($sql) {
        $result = $this->query($sql);

        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }

        return null;
    }

    /**
     * Get single variable from query
     *
     * @param string $sql SQL query
     * @return mixed Variable
     */
    public function get_var($sql) {
        $result = $this->query($sql);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_array();
            return $row[0];
        }

        return null;
    }

    /**
     * Prepare value for SQL
     *
     * @param mixed $value Value to escape
     * @return string Escaped value
     */
    public function escape($value) {
        return $this->connection->real_escape_string($value);
    }

    /**
     * Get total number of products
     *
     * @return int Total products
     */
    public function get_total_products() {
        $table = $this->get_table('catalog_product_entity');

        if ($this->magento_version == 2) {
            $sql = "SELECT COUNT(*) FROM {$table}";
        } else {
            $sql = "SELECT COUNT(*) FROM {$table}";
        }

        return (int) $this->get_var($sql);
    }

    /**
     * Get total number of categories
     *
     * @return int Total categories
     */
    public function get_total_categories() {
        $table = $this->get_table('catalog_category_entity');

        $sql = "SELECT COUNT(*) FROM {$table} WHERE entity_id > 2"; // Exclude root categories

        return (int) $this->get_var($sql);
    }

    /**
     * Get total number of customers
     *
     * @return int Total customers
     */
    public function get_total_customers() {
        $table = $this->get_table('customer_entity');

        $sql = "SELECT COUNT(*) FROM {$table}";

        return (int) $this->get_var($sql);
    }

    /**
     * Get total number of orders
     *
     * @return int Total orders
     */
    public function get_total_orders() {
        $table = $this->get_table('sales_order');

        $sql = "SELECT COUNT(*) FROM {$table}";

        return (int) $this->get_var($sql);
    }

    /**
     * Fetch products in batches
     *
     * @param int $offset Offset
     * @param int $limit Limit
     * @return array Products
     */
    public function get_products($offset = 0, $limit = 50) {
        $table = $this->get_table('catalog_product_entity');

        $sql = "SELECT * FROM {$table} ORDER BY entity_id ASC LIMIT {$offset}, {$limit}";

        return $this->get_results($sql);
    }

    /**
     * Fetch single product with attributes
     *
     * @param int $product_id Product ID
     * @return array Product data
     */
    public function get_product($product_id) {
        $table = $this->get_table('catalog_product_entity');

        $product = $this->get_row("SELECT * FROM {$table} WHERE entity_id = {$product_id}");

        if (!$product) {
            return null;
        }

        // Get product attributes
        $product['attributes'] = $this->get_product_attributes($product_id);
        $product['categories'] = $this->get_product_categories($product_id);
        $product['media'] = $this->get_product_media($product_id);
        $product['stock_item'] = $this->get_product_stock($product_id);

        return $product;
    }

    /**
     * Get product attributes from all EAV tables
     *
     * @param int $product_id Product ID
     * @return array Attributes with all data types
     */
    public function get_product_attributes($product_id) {
        $attributes = array();

        // Attribute types and their corresponding tables
        $attribute_types = array(
            'varchar' => $this->get_table('catalog_product_entity_varchar'),
            'int' => $this->get_table('catalog_product_entity_int'),
            'text' => $this->get_table('catalog_product_entity_text'),
            'decimal' => $this->get_table('catalog_product_entity_decimal'),
            'datetime' => $this->get_table('catalog_product_entity_datetime')
        );

        $eav_attr_table = $this->get_table('eav_attribute');

        foreach ($attribute_types as $type => $table) {
            $sql = "SELECT ea.attribute_code, ea.backend_type, v.value
                    FROM {$eav_attr_table} ea
                    LEFT JOIN {$table} v ON v.attribute_id = ea.attribute_id AND v.entity_id = {$product_id}
                    WHERE ea.entity_type_id = (
                        SELECT entity_type_id FROM {$this->get_table('eav_entity_type')}
                        WHERE entity_type_code = 'catalog_product'
                        LIMIT 1
                    ) AND v.value IS NOT NULL";

            $results = $this->get_results($sql);

            if ($results) {
                foreach ($results as $row) {
                    $attributes[] = array(
                        'attribute_code' => $row['attribute_code'],
                        'value' => $row['value'],
                        'backend_type' => $row['backend_type']
                    );
                }
            }
        }

        return $attributes;
    }

    /**
     * Get product categories
     *
     * @param int $product_id Product ID
     * @return array Category IDs
     */
    public function get_product_categories($product_id) {
        $table = $this->get_table('catalog_category_product');

        $sql = "SELECT category_id FROM {$table} WHERE product_id = {$product_id}";

        $results = $this->get_results($sql);
        return wp_list_pluck($results, 'category_id');
    }

    /**
     * Get product media/images
     *
     * @param int $product_id Product ID
     * @return array Media items
     */
    public function get_product_media($product_id) {
        if ($this->magento_version == 2) {
            $table = $this->get_table('catalog_product_entity_media_gallery');
            $value_to_product = $this->get_table('catalog_product_entity_media_gallery_value');
        } else {
            $table = $this->get_table('catalog_product_entity_media_gallery');
            $value_to_product = $this->get_table('catalog_product_entity_media_gallery_value');
        }

        $sql = "SELECT m.* FROM {$table} m
                JOIN {$value_to_product} v ON v.value_id = m.value_id
                WHERE v.entity_id = {$product_id}";

        return $this->get_results($sql);
    }

    /**
     * Get product stock data
     *
     * @param int $product_id Product ID
     * @return array|false Stock data
     */
    public function get_product_stock($product_id) {
        if ($this->magento_version == 2) {
            // Magento 2 stock tables
            $stock_item_table = $this->get_table('cataloginventory_stock_item');
            $stock_status_table = $this->get_table('cataloginventory_stock_status');

            $sql = "SELECT si.qty, si.is_in_stock, si.manage_stock, si.backorders, si.use_config_manage_stock,
                    ss.stock_status
                    FROM {$stock_item_table} si
                    LEFT JOIN {$stock_status_table} ss ON ss.product_id = si.product_id
                    WHERE si.product_id = {$product_id}";

            $result = $this->get_row($sql);

            if ($result) {
                return array(
                    'qty' => (float)($result['qty'] ?? 0),
                    'is_in_stock' => (int)($result['is_in_stock'] ?? 0) === 1,
                    'manage_stock' => (int)($result['manage_stock'] ?? 0) === 1,
                    'backorders' => (int)($result['backorders'] ?? 0),
                    'stock_status' => (int)($result['stock_status'] ?? 0) === 1
                );
            }
        } else {
            // Magento 1 stock tables
            $stock_item_table = $this->get_table('cataloginventory_stock_item');

            $sql = "SELECT qty, is_in_stock, manage_stock, backorders, use_config_manage_stock
                    FROM {$stock_item_table}
                    WHERE product_id = {$product_id}";

            $result = $this->get_row($sql);

            if ($result) {
                return array(
                    'qty' => (float)($result['qty'] ?? 0),
                    'is_in_stock' => (int)($result['is_in_stock'] ?? 0) === 1,
                    'manage_stock' => (int)($result['manage_stock'] ?? 0) === 1,
                    'backorders' => (int)($result['backorders'] ?? 0)
                );
            }
        }

        // Return default stock data if not found
        return array(
            'qty' => 0,
            'is_in_stock' => true,
            'manage_stock' => false,
            'backorders' => 0
        );
    }

    /**
     * Fetch categories
     *
     * @return array Categories
     */
    public function get_categories() {
        $table = $this->get_table('catalog_category_entity');

        $sql = "SELECT * FROM {$table} WHERE entity_id > 2 ORDER BY level ASC, position ASC";

        return $this->get_results($sql);
    }

    /**
     * Fetch customers
     *
     * @param int $offset Offset
     * @param int $limit Limit
     * @return array Customers
     */
    public function get_customers($offset = 0, $limit = 50) {
        $table = $this->get_table('customer_entity');

        $sql = "SELECT * FROM {$table} ORDER BY entity_id ASC LIMIT {$offset}, {$limit}";

        return $this->get_results($sql);
    }

    /**
     * Fetch customer addresses
     *
     * @param int $customer_id Customer ID
     * @return array Addresses
     */
    public function get_customer_addresses($customer_id) {
        $table = $this->get_table('customer_address_entity');

        $sql = "SELECT * FROM {$table} WHERE parent_id = {$customer_id}";

        return $this->get_results($sql);
    }

    /**
     * Fetch orders
     *
     * @param int $offset Offset
     * @param int $limit Limit
     * @return array Orders
     */
    public function get_orders($offset = 0, $limit = 50) {
        $table = $this->get_table('sales_order');

        $sql = "SELECT * FROM {$table} ORDER BY entity_id ASC LIMIT {$offset}, {$limit}";

        return $this->get_results($sql);
    }

    /**
     * Get order items
     *
     * @param int $order_id Order ID
     * @return array Order items
     */
    public function get_order_items($order_id) {
        $table = $this->get_table('sales_order_item');

        $sql = "SELECT * FROM {$table} WHERE order_id = {$order_id}";

        return $this->get_results($sql);
    }

    /**
     * Get order addresses
     *
     * @param int $order_id Order ID
     * @param string $address_type Address type (billing or shipping)
     * @return array Address
     */
    public function get_order_address($order_id, $address_type = 'billing') {
        $table = $this->get_table('sales_order_address');

        $sql = "SELECT * FROM {$table} WHERE parent_id = {$order_id} AND address_type = '{$address_type}'";

        return $this->get_row($sql);
    }

    /**
     * Get Magento base URL
     *
     * @return string Base URL
     */
    public function get_base_url() {
        $table = $this->get_table('core_config_data');

        $sql = "SELECT value FROM {$table}
                WHERE path = 'web/unsecure/base_url' OR path = 'web/secure/base_url'
                LIMIT 1";

        $url = $this->get_var($sql);

        if (!$url) {
            return '';
        }

        return rtrim($url, '/');
    }

    /**
     * Get media base URL
     *
     * @return string Media URL
     */
    public function get_media_url() {
        $base_url = $this->get_base_url();
        return $base_url . '/media/catalog/product';
    }

    /**
     * Helper for prepared statement parameters
     */
    private function refValues($arr) {
        $refs = array();
        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }

    /**
     * Close database connection
     */
    public function close() {
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    /**
     * Destructor
     */
    public function __destruct() {
        $this->close();
    }
}
