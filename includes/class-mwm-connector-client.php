<?php
/**
 * Magento Connector Client
 *
 * Communicates with the magento-connector.php file on the Magento server
 *
 * @package Magento_Wordpress_Migrator
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWM_Connector_Client {

    /**
     * Connector URL
     */
    private $connector_url;

    /**
     * API Key
     */
    private $api_key;

    /**
     * Request timeout in seconds
     */
    private $timeout = 30;

    /**
     * Constructor
     */
    public function __construct($connector_url, $api_key) {
        
        $this->connector_url = rtrim($connector_url, '/');
        $this->api_key = $api_key;
    }

    /**
     * Test connection to Magento connector
     *
     * @return array Result with 'success' boolean and 'message' string
     */
    public function test_connection() {
        $result = $this->make_request('test');

        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => 'Connection failed: ' . $result->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body($result);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('MWM Connector: Invalid JSON response from test endpoint');
            error_log('MWM Connector: Response body: ' . $body);
            error_log('MWM Connector: JSON error: ' . json_last_error_msg());
            return array(
                'success' => false,
                'message' => 'Invalid JSON response from connector. ' .
                           'JSON Error: ' . json_last_error_msg() . '. ' .
                           'Raw response (first 200 chars): ' . substr($body, 0, 200)
            );
        }

        if (isset($data['success']) && $data['success']) {
            return array(
                'success' => true,
                'message' => isset($data['message']) ? $data['message'] : 'Connection successful',
                'magento_version' => isset($data['magento_version']) ? $data['magento_version'] : 'Unknown'
            );
        }

        return array(
            'success' => false,
            'message' => isset($data['message']) ? $data['message'] : 'Unknown error'
        );
    }

    /**
     * Get products from Magento
     *
     * @param int $limit Number of products to retrieve
     * @param int $page Page number
     * @return array|WP_Error
     */
    public function get_products($limit = 100, $page = 1) {
        $result = $this->make_request('products', array(
            'limit' => $limit,
            'page' => $page
        ));
        if (is_wp_error($result)) {
            return $result;
        }

        $body = wp_remote_retrieve_body($result);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('MWM Connector: Invalid JSON response');
            error_log('MWM Connector: Response body: ' . $body);
            error_log('MWM Connector: JSON error: ' . json_last_error_msg());
            return new WP_Error(
                'invalid_json',
                'Invalid JSON response from connector. ' .
                'JSON Error: ' . json_last_error_msg() . '. ' .
                'Raw response (first 200 chars): ' . substr($body, 0, 200)
            );
        }

        if (isset($data['success']) && $data['success']) {
            return $data;
        }

        return new WP_Error(
            'connector_error',
            isset($data['message']) ? $data['message'] : 'Unknown error'
        );
    }

    /**
     * Get a single product by SKU
     *
     * @param string $sku Product SKU
     * @return array|WP_Error
     */
    public function get_product($sku) {
        $result = $this->make_request('product', array(
            'sku' => $sku
        ));

        if (is_wp_error($result)) {
            return $result;
        }

        $body = wp_remote_retrieve_body($result);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('MWM Connector: Invalid JSON response');
            error_log('MWM Connector: Response body: ' . $body);
            error_log('MWM Connector: JSON error: ' . json_last_error_msg());
            return new WP_Error(
                'invalid_json',
                'Invalid JSON response from connector. ' .
                'JSON Error: ' . json_last_error_msg() . '. ' .
                'Raw response (first 200 chars): ' . substr($body, 0, 200)
            );
        }

        if (isset($data['success']) && $data['success']) {
            return $data['product'];
        }

        return new WP_Error(
            'connector_error',
            isset($data['message']) ? $data['message'] : 'Unknown error'
        );
    }

    /**
     * Get total count of products
     *
     * @return int|WP_Error
     */
    public function get_products_count() {
        $result = $this->make_request('products_count');

        if (is_wp_error($result)) {
            return $result;
        }

        $body = wp_remote_retrieve_body($result);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('MWM Connector: Invalid JSON response');
            error_log('MWM Connector: Response body: ' . $body);
            error_log('MWM Connector: JSON error: ' . json_last_error_msg());
            return new WP_Error(
                'invalid_json',
                'Invalid JSON response from connector. ' .
                'JSON Error: ' . json_last_error_msg() . '. ' .
                'Raw response (first 200 chars): ' . substr($body, 0, 200)
            );
        }

        if (isset($data['success']) && $data['success']) {
            return intval($data['count']);
        }

        return new WP_Error(
            'connector_error',
            isset($data['message']) ? $data['message'] : 'Unknown error'
        );
    }

    /**
     * Get categories from Magento
     *
     * @param int|null $parent_id Parent category ID (null for all)
     * @return array|WP_Error
     */
    public function get_categories($parent_id = null) {
        $params = array();
        if ($parent_id !== null) {
            $params['parent_id'] = $parent_id;
        }

        $result = $this->make_request('categories', $params);

        if (is_wp_error($result)) {
            return $result;
        }

        $body = wp_remote_retrieve_body($result);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('MWM Connector: Invalid JSON response');
            error_log('MWM Connector: Response body: ' . $body);
            error_log('MWM Connector: JSON error: ' . json_last_error_msg());
            return new WP_Error(
                'invalid_json',
                'Invalid JSON response from connector. ' .
                'JSON Error: ' . json_last_error_msg() . '. ' .
                'Raw response (first 200 chars): ' . substr($body, 0, 200)
            );
        }

        if (isset($data['success']) && $data['success']) {
            return $data;
        }

        return new WP_Error(
            'connector_error',
            isset($data['message']) ? $data['message'] : 'Unknown error'
        );
    }

    /**
     * Get a single category by ID
     *
     * @param int $category_id Category ID
     * @return array|WP_Error
     */
    public function get_category($category_id) {
        $result = $this->make_request('category', array(
            'id' => $category_id
        ));

        if (is_wp_error($result)) {
            return $result;
        }

        $body = wp_remote_retrieve_body($result);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('MWM Connector: Invalid JSON response');
            error_log('MWM Connector: Response body: ' . $body);
            error_log('MWM Connector: JSON error: ' . json_last_error_msg());
            return new WP_Error(
                'invalid_json',
                'Invalid JSON response from connector. ' .
                'JSON Error: ' . json_last_error_msg() . '. ' .
                'Raw response (first 200 chars): ' . substr($body, 0, 200)
            );
        }

        if (isset($data['success']) && $data['success']) {
            return $data['category'];
        }

        return new WP_Error(
            'connector_error',
            isset($data['message']) ? $data['message'] : 'Unknown error'
        );
    }

    /**
     * Get total count of categories
     *
     * @return int|WP_Error
     */
    public function get_categories_count() {
        $result = $this->make_request('categories_count');

        if (is_wp_error($result)) {
            return $result;
        }

        $body = wp_remote_retrieve_body($result);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('MWM Connector: Invalid JSON response');
            error_log('MWM Connector: Response body: ' . $body);
            error_log('MWM Connector: JSON error: ' . json_last_error_msg());
            return new WP_Error(
                'invalid_json',
                'Invalid JSON response from connector. ' .
                'JSON Error: ' . json_last_error_msg() . '. ' .
                'Raw response (first 200 chars): ' . substr($body, 0, 200)
            );
        }

        if (isset($data['success']) && $data['success']) {
            return intval($data['count']);
        }

        return new WP_Error(
            'connector_error',
            isset($data['message']) ? $data['message'] : 'Unknown error'
        );
    }

    /**
     * Get orders from Magento
     *
     * @param int $limit Number of orders to retrieve
     * @param int $page Page number
     * @return array|WP_Error
     */
    public function get_orders($limit = 100, $page = 1) {
        $result = $this->make_request('orders', array(
            'limit' => $limit,
            'page' => $page
        ));

        if (is_wp_error($result)) {
            return $result;
        }

        $body = wp_remote_retrieve_body($result);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('MWM Connector: Invalid JSON response');
            error_log('MWM Connector: Response body: ' . $body);
            error_log('MWM Connector: JSON error: ' . json_last_error_msg());
            return new WP_Error(
                'invalid_json',
                'Invalid JSON response from connector. ' .
                'JSON Error: ' . json_last_error_msg() . '. ' .
                'Raw response (first 200 chars): ' . substr($body, 0, 200)
            );
        }

        if (isset($data['success']) && $data['success']) {
            return $data;
        }

        return new WP_Error(
            'connector_error',
            isset($data['message']) ? $data['message'] : 'Unknown error'
        );
    }

    /**
     * Get total count of orders
     *
     * @return int|WP_Error
     */
    public function get_orders_count() {
        $result = $this->make_request('orders_count');

        if (is_wp_error($result)) {
            return $result;
        }

        $body = wp_remote_retrieve_body($result);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('MWM Connector: Invalid JSON response');
            error_log('MWM Connector: Response body: ' . $body);
            error_log('MWM Connector: JSON error: ' . json_last_error_msg());
            return new WP_Error(
                'invalid_json',
                'Invalid JSON response from connector. ' .
                'JSON Error: ' . json_last_error_msg() . '. ' .
                'Raw response (first 200 chars): ' . substr($body, 0, 200)
            );
        }

        if (isset($data['success']) && $data['success']) {
            return intval($data['count']);
        }

        return new WP_Error(
            'connector_error',
            isset($data['message']) ? $data['message'] : 'Unknown error'
        );
    }

    /**
     * Get order items for a specific order
     *
     * @param int $order_id Order entity ID
     * @return array|WP_Error
     */
    public function get_order_items($order_id) {
        $result = $this->make_request('order_items', array(
            'order_id' => $order_id
        ));

        if (is_wp_error($result)) {
            return $result;
        }

        $body = wp_remote_retrieve_body($result);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('MWM Connector: Invalid JSON response');
            error_log('MWM Connector: Response body: ' . $body);
            error_log('MWM Connector: JSON error: ' . json_last_error_msg());
            return new WP_Error(
                'invalid_json',
                'Invalid JSON response from connector. ' .
                'JSON Error: ' . json_last_error_msg() . '. ' .
                'Raw response (first 200 chars): ' . substr($body, 0, 200)
            );
        }

        if (isset($data['success']) && $data['success']) {
            return $data;
        }

        return new WP_Error(
            'connector_error',
            isset($data['message']) ? $data['message'] : 'Unknown error'
        );
    }

    /**
     * Get customers from Magento
     *
     * @param int $limit Number of customers to retrieve
     * @param int $page Page number
     * @return array|WP_Error
     */
    public function get_customers($limit = 100, $page = 1) {
        $result = $this->make_request('customers', array(
            'limit' => $limit,
            'page' => $page
        ));

        if (is_wp_error($result)) {
            return $result;
        }

        $body = wp_remote_retrieve_body($result);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('MWM Connector: Invalid JSON response');
            error_log('MWM Connector: Response body: ' . $body);
            error_log('MWM Connector: JSON error: ' . json_last_error_msg());
            return new WP_Error(
                'invalid_json',
                'Invalid JSON response from connector. ' .
                'JSON Error: ' . json_last_error_msg() . '. ' .
                'Raw response (first 200 chars): ' . substr($body, 0, 200)
            );
        }

        if (isset($data['success']) && $data['success']) {
            return $data;
        }

        return new WP_Error(
            'connector_error',
            isset($data['message']) ? $data['message'] : 'Unknown error'
        );
    }

    /**
     * Get total count of customers
     *
     * @return int|WP_Error
     */
    public function get_customers_count() {
        
        $result = $this->make_request('customers_count');
      
        if (is_wp_error($result)) {
            return $result;
        }

        $body = wp_remote_retrieve_body($result);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('MWM Connector: Invalid JSON response');
            error_log('MWM Connector: Response body: ' . $body);
            error_log('MWM Connector: JSON error: ' . json_last_error_msg());
            return new WP_Error(
                'invalid_json',
                'Invalid JSON response from connector. ' .
                'JSON Error: ' . json_last_error_msg() . '. ' .
                'Raw response (first 200 chars): ' . substr($body, 0, 200)
            );
        }

        if (isset($data['success']) && $data['success']) {
            return intval($data['count']);
        }

        return new WP_Error(
            'connector_error',
            isset($data['message']) ? $data['message'] : 'Unknown error'
        );
    }

    /**
     * Ping connector (no authentication required)
     *
     * @return array Result with 'success' boolean and message
     */
    public function ping() {
        $result = $this->make_request('ping');

        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => 'Ping failed: ' . $result->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body($result);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('MWM Connector: Invalid JSON response from ping endpoint');
            error_log('MWM Connector: Response body: ' . $body);
            error_log('MWM Connector: JSON error: ' . json_last_error_msg());
            return array(
                'success' => false,
                'message' => 'Invalid JSON response from connector. ' .
                           'JSON Error: ' . json_last_error_msg() . '. ' .
                           'Raw response (first 200 chars): ' . substr($body, 0, 200)
            );
        }

        return $data;
    }

    /**
     * Test debug endpoint (requires authentication but no Magento loading)
     *
     * @return array|WP_Error
     */
    public function test_debug() {
        $result = $this->make_request('test_debug');

        if (is_wp_error($result)) {
            return $result;
        }

        $body = wp_remote_retrieve_body($result);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('MWM Connector: Invalid JSON response from test_debug endpoint');
            error_log('MWM Connector: Response body: ' . $body);
            error_log('MWM Connector: JSON error: ' . json_last_error_msg());
            return new WP_Error(
                'invalid_json',
                'Invalid JSON response from connector. ' .
                'JSON Error: ' . json_last_error_msg() . '. ' .
                'Raw response (first 200 chars): ' . substr($body, 0, 200)
            );
        }

        if (isset($data['success']) && $data['success']) {
            return $data;
        }

        return new WP_Error(
            'connector_error',
            isset($data['message']) ? $data['message'] : 'Unknown error'
        );
    }

    /**
     * Make a request to the connector
     *
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @return array|WP_Error
     */
    private function make_request($endpoint, $params = array()) {
        $url = $this->connector_url . '?endpoint=' . urlencode($endpoint);

        if (!empty($params)) {
            $url .= '&' . http_build_query($params);
        }

        // Debug: Log the request URL
        error_log('MWM Connector: ==================================================');
        error_log('MWM Connector: REQUEST START');
        error_log('MWM Connector: Endpoint: ' . $endpoint);
        error_log('MWM Connector: Full URL: ' . $url);
        error_log('MWM Connector: API Key (first 8 chars): ' . substr($this->api_key, 0, 8) . '...');
        $args = array(
            'timeout' => $this->timeout,
            'headers' => array(
                'X-Magento-Connector-Key' => $this->api_key,
                'Accept' => 'application/json'
            ),
            'sslverify' => false // May need to be true in production
        );

        error_log('MWM Connector: Request args: ' . print_r($args, true));
        $response = wp_remote_get($url, $args);

        // Debug: Log response details
        if (is_wp_error($response)) {
            error_log('MWM Connector: ❌ WP ERROR');
            error_log('MWM Connector: Error code: ' . $response->get_error_code());
            error_log('MWM Connector: Error message: ' . $response->get_error_message());
            error_log('MWM Connector: REQUEST END');
            error_log('MWM Connector: ==================================================');
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_headers = wp_remote_retrieve_headers($response);
            $body = wp_remote_retrieve_body($response);

            error_log('MWM Connector: ✅ RESPONSE RECEIVED');
            error_log('MWM Connector: HTTP Status: ' . $response_code);
            error_log('MWM Connector: Response Headers: ' . print_r($response_headers, true));
            error_log('MWM Connector: Content-Type: ' . (isset($response_headers['content-type']) ? $response_headers['content-type'] : 'not set'));
            error_log('MWM Connector: Response body length: ' . strlen($body) . ' bytes');
            error_log('MWM Connector: Response body (first 1000 chars): ' . substr($body, 0, 1000));

            // Try to decode JSON
            $data = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                error_log('MWM Connector: ✅ JSON is valid');
                error_log('MWM Connector: Decoded data: ' . print_r($data, true));
            } else {
                error_log('MWM Connector: ❌ JSON INVALID');
                error_log('MWM Connector: JSON Error: ' . json_last_error_msg());
                error_log('MWM Connector: JSON Error Code: ' . json_last_error());
            }

            error_log('MWM Connector: REQUEST END');
            error_log('MWM Connector: ==================================================');
        }

        return $response;
    }
}
