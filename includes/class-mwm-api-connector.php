<?php
/**
 * Magento REST API Connector Class
 *
 * Handles communication with Magento REST API using OAuth 1.0a
 *
 * @package Magento_WordPress_Migrator
 */

if (!defined('ABSPATH')) {
    exit;
}

class MWM_API_Connector {

    /**
     * Magento Store URL
     *
     * @var string
     */
    private $store_url;

    /**
     * API Version (V1 or V2)
     *
     * @var string
     */
    private $api_version;

    /**
     * Consumer Key
     *
     * @var string
     */
    private $consumer_key;

    /**
     * Consumer Secret
     *
     * @var string
     */
    private $consumer_secret;

    /**
     * Access Token
     *
     * @var string
     */
    private $access_token;

    /**
     * Access Token Secret
     *
     * @var string
     */
    private $access_token_secret;

    /**
     * Request timeout in seconds
     *
     * @var int
     */
    private $timeout;

    /**
     * Constructor
     *
     * @param string $store_url Magento store URL
     * @param string $api_version API version (V1 or V2)
     * @param string $consumer_key OAuth consumer key
     * @param string $consumer_secret OAuth consumer secret
     * @param string $access_token OAuth access token
     * @param string $access_token_secret OAuth access token secret
     */
    public function __construct($store_url, $api_version, $consumer_key, $consumer_secret, $access_token, $access_token_secret) {
        $this->store_url = rtrim($store_url, '/');
        $this->api_version = $api_version;
        $this->consumer_key = $consumer_key;
        $this->consumer_secret = $consumer_secret;
        $this->access_token = $access_token;
        $this->access_token_secret = $access_token_secret;
        $this->timeout = 30;

        // Debug: Log constructor parameters
        $debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        if ($debug_mode) {
            error_log('MWM API Connector Constructor:');
            error_log('  Store URL: ' . $this->store_url);
            error_log('  API Version: ' . $this->api_version);
            error_log('  Consumer Key: ' . substr($this->consumer_key, 0, 8) . '...' . substr($this->consumer_key, -4));
            error_log('  Consumer Secret Length: ' . strlen($this->consumer_secret));
            error_log('  Access Token: ' . substr($this->access_token, 0, 8) . '...' . substr($this->access_token, -4));
            error_log('  Access Token Secret Length: ' . strlen($this->access_token_secret));
        }
    }

    /**
     * Test connection to Magento REST API
     *
     * @return array Result with success status and message
     */
    public function test_connection() {
        try {
            // Use a simple endpoint that's always accessible
            // GET /rest/V1/modules lists installed Magento modules
            // This endpoint doesn't require special resource permissions
            $result = $this->request('GET', '/modules');

            if ($result && is_array($result)) {
                // Success - we got a valid response
                $module_count = isset($result['items']) ? count($result['items']) : 0;
                return array(
                    'success' => true,
                    'message' => sprintf(
                        __('Connection successful! Magento API is accessible. Found %d modules.', 'magento-wordpress-migrator'),
                        $module_count
                    ),
                    'store_info' => array(
                        'modules_count' => $module_count,
                        'api_version' => $this->api_version
                    )
                );
            }

            return array(
                'success' => true,
                'message' => __('Connection successful! Magento API is accessible.', 'magento-wordpress-migrator')
            );

        } catch (Exception $e) {
            // Log the full error for debugging
            error_log('MWM API Test Connection Error: ' . $e->getMessage());

            return array(
                'success' => false,
                'message' => $this->format_error_message($e->getMessage())
            );
        }
    }

    /**
     * Get all products
     *
     * @param int $page Page number
     * @param int $page_size Page size
     * @param array $filters Search filters
     * @return array|false Products data
     */
    public function get_products($page = 1, $page_size = 50, $filters = array()) {
        $search_criteria = array(
            'searchCriteria' => array(
                'currentPage' => $page,
                'pageSize' => $page_size
            )
        );

        if (!empty($filters)) {
            $search_criteria['searchCriteria']['filterGroups'] = $filters;
        }

        return $this->request('GET', '/products/search', $search_criteria);
    }

    /**
     * Get single product by SKU
     *
     * @param string $sku Product SKU
     * @return array|false Product data
     */
    public function get_product($sku) {
        return $this->request('GET', '/products/' . $sku);
    }

    /**
     * Get all categories
     *
     * @return array|false Categories data
     */
    public function get_categories() {
        error_log('MWM API: Fetching categories from /categories endpoint');

        // Try different endpoints for categories
        $endpoints_to_try = array(
            '/categories',
            '/categories/list',
            '/categories?searchCriteria[pageSize]=100'
        );

        $result = false;

        foreach ($endpoints_to_try as $endpoint) {
            try {
                error_log("MWM API: Trying endpoint: $endpoint");

                $response = $this->request('GET', $endpoint);
                error_log("MWM API: Response from $endpoint: " . print_r($response, true));

                // Check if we got valid data
                if ($response && (isset($response['id']) || isset($response['items']) || (is_array($response) && !empty($response)))) {
                    error_log("MWM API: Got valid response from $endpoint");
                    $result = $this->parse_categories_response($response);
                    if (!empty($result)) {
                        error_log("MWM API: Successfully parsed categories from $endpoint");
                        break;
                    }
                }
            } catch (Exception $e) {
                error_log("MWM API: Error fetching from $endpoint: " . $e->getMessage());
                continue;
            }
        }

        if (!$result) {
            error_log('MWM API: Failed to fetch categories from any endpoint');
            return array('children' => array());
        }

        return $result;
    }

    /**
     * Parse categories API response (handles multiple response formats)
     *
     * @param array $response API response
     * @return array Parsed categories
     */
    private function parse_categories_response($response) {
        error_log('MWM API: Parsing categories response');

        // Format 1: Tree structure (root category with children_data)
        if (isset($response['id']) && isset($response['children_data'])) {
            error_log('MWM API: Detected tree structure format');
            error_log('MWM API: Root category ID: ' . $response['id'] . ', Children count: ' . count($response['children_data']));

            if (!empty($response['children_data'])) {
                $flat_categories = $this->flatten_category_tree($response['children_data']);
                error_log('MWM API: Flattened to ' . count($flat_categories) . ' categories');

                return array(
                    'total_count' => count($flat_categories),
                    'children' => $flat_categories
                );
            }
        }

        // Format 2: Paginated items array
        if (isset($response['items']) && is_array($response['items'])) {
            error_log('MWM API: Detected paginated items format with ' . count($response['items']) . ' items');

            // Convert items to expected format
            $categories = array();
            foreach ($response['items'] as $item) {
                if (isset($item['id'])) {
                    $categories[] = array(
                        'entity_id' => $item['id'],
                        'parent_id' => $item['parent_id'] ?? 2,
                        'name' => $item['name'] ?? '',
                        'is_active' => $item['is_active'] ?? 1,
                        'position' => $item['position'] ?? 0,
                        'level' => $item['level'] ?? 0,
                        'children_count' => $item['children_count'] ?? 0,
                        'path' => $item['path'] ?? ''
                    );
                }
            }

            error_log('MWM API: Converted ' . count($categories) . ' categories from items format');
            return array('children' => $categories);
        }

        // Format 3: Already flat array
        if (is_array($response) && isset($response[0]) && isset($response[0]['id'])) {
            error_log('MWM API: Detected flat array format with ' . count($response) . ' categories');
            return array('children' => $response);
        }

        // Format 4: Flat array with entity_id
        if (is_array($response) && isset($response[0]) && isset($response[0]['entity_id'])) {
            error_log('MWM API: Detected flat array format (entity_id) with ' . count($response) . ' categories');
            return array('children' => $response);
        }

        error_log('MWM API: Unknown response format');
        error_log('MWM API: Response keys: ' . implode(', ', array_keys($response)));

        return array('children' => array());
    }

    /**
     * Flatten category tree structure
     *
     * @param array $categories Category tree
     * @param int $parent_id Parent category ID
     * @return array Flattened categories
     */
    private function flatten_category_tree($categories, $parent_id = null) {
        $flat = array();

        foreach ($categories as $category) {
            // Add current category
            $category_data = array(
                'entity_id' => $category['id'],
                'parent_id' => $parent_id ?? $category['parent_id'] ?? 2,
                'name' => $category['name'] ?? '',
                'is_active' => $category['is_active'] ?? 1,
                'position' => $category['position'] ?? 0,
                'level' => $category['level'] ?? 0,
                'children_count' => $category['children_count'] ?? 0,
                'path' => $category['path'] ?? ''
            );

            $flat[] = $category_data;
            error_log("MWM API: Added category ID {$category_data['entity_id']} - {$category_data['name']}");

            // Recursively add children
            if (isset($category['children_data']) && is_array($category['children_data']) && !empty($category['children_data'])) {
                $children = $this->flatten_category_tree($category['children_data'], $category['id']);
                $flat = array_merge($flat, $children);
            }
        }

        return $flat;
    }

    /**
     * Get customers
     *
     * @param int $page Page number
     * @param int $page_size Page size
     * @return array|false Customers data
     */
    public function get_customers($page = 1, $page_size = 50) {
        $search_criteria = array(
            'searchCriteria' => array(
                'currentPage' => $page,
                'pageSize' => $page_size
            )
        );

        return $this->request('GET', '/customers/search', $search_criteria);
    }

    /**
     * Get customer by ID
     *
     * @param int $id Customer ID
     * @return array|false Customer data
     */
    public function get_customer($id) {
        return $this->request('GET', '/customers/' . $id);
    }

    /**
     * Get orders
     *
     * @param int $page Page number
     * @param int $page_size Page size
     * @param array $filters Search filters
     * @return array|false Orders data
     */
    public function get_orders($page = 1, $page_size = 50, $filters = array()) {
        $search_criteria = array(
            'searchCriteria' => array(
                'currentPage' => $page,
                'pageSize' => $page_size
            )
        );

        if (!empty($filters)) {
            $search_criteria['searchCriteria']['filterGroups'] = $filters;
        }

        return $this->request('GET', '/orders', $search_criteria);
    }

    /**
     * Get order by ID
     *
     * @param int $id Order ID
     * @return array|false Order data
     */
    public function get_order($id) {
        return $this->request('GET', '/orders/' . $id);
    }

    /**
     * Get total count of items
     *
     * @param string $endpoint API endpoint
     * @return int Total count
     */
    public function get_total_count($endpoint) {
        try {
            // Request with page size 1 to get total count
            $search_criteria = array(
                'searchCriteria' => array(
                    'currentPage' => 1,
                    'pageSize' => 1
                )
            );

            $result = $this->request('GET', $endpoint, $search_criteria);

            if (isset($result['total_count'])) {
                return (int) $result['total_count'];
            }

            return 0;

        } catch (Exception $e) {
            error_log('MWM API: Get total count failed - ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Make API request with OAuth 1.0a authentication
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array|false Response data
     * @throws Exception On request failure
     */
    private function request($method, $endpoint, $data = array()) {
        $url = $this->build_url($endpoint);

        $debug_mode = defined('WP_DEBUG') && WP_DEBUG;

        // Log the request for debugging
        error_log("MWM API Request: $method $url");
        if ($debug_mode) {
            error_log("MWM DEBUG: Endpoint: $endpoint");
            error_log("MWM DEBUG: Full URL: $url");
        }

        // Build OAuth parameters
        $oauth_params = $this->build_oauth_params($url, $method, $data);

        if ($debug_mode) {
            error_log("MWM DEBUG: OAuth Parameters:");
            foreach ($oauth_params as $key => $value) {
                if ($key === 'oauth_signature') {
                    error_log("  $key: " . substr($value, 0, 20) . '...');
                } else {
                    error_log("  $key: $value");
                }
            }
        }

        // Add OAuth parameters to URL for GET requests or body for POST
        if ($method === 'GET') {
            $url = add_query_arg($oauth_params, $url);
            if ($debug_mode) {
                error_log("MWM DEBUG: Final URL with OAuth params (first 200 chars): " . substr($url, 0, 200) . '...');
            }
        }

        $args = array(
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ),
            'sslverify' => false, // May need to be configurable
            'body' => ($method !== 'GET') ? json_encode($data) : null
        );

        // For GET requests, OAuth params are in URL
        // For POST requests, they go in the body
        if ($method !== 'GET') {
            $body_params = array_merge($data, $oauth_params);
            $args['body'] = json_encode($body_params);
            if ($debug_mode) {
                error_log("MWM DEBUG: POST body (first 200 chars): " . substr($args['body'], 0, 200) . '...');
            }
        }

        if ($debug_mode) {
            error_log("MWM DEBUG: Request args: " . print_r($args, true));
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log("MWM API Request Error: $error_message");
            if ($debug_mode) {
                error_log("MWM DEBUG: WP Error: " . print_r($response, true));
            }
            throw new Exception($error_message);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        // Log response for debugging
        error_log("MWM API Response Code: $response_code");
        error_log("MWM API Response Body: " . substr($body, 0, 500)); // Log first 500 chars

        if ($debug_mode) {
            error_log("MWM DEBUG: Response Headers: " . print_r($response_headers, true));
            error_log("MWM DEBUG: Full Response Body: " . $body);
        }

        if ($response_code >= 400) {
            $error_data = json_decode($body, true);

            // Log detailed error info
            error_log("MWM API Error Data: " . print_r($error_data, true));

            $message = isset($error_data['message']) ? $error_data['message'] : "HTTP Error $response_code";

            // Include additional error details if available
            if (isset($error_data['errors']) && is_array($error_data['errors'])) {
                foreach ($error_data['errors'] as $error) {
                    if (isset($error['message'])) {
                        $message .= ' - ' . $error['message'];
                    }
                }
            }

            if ($debug_mode && isset($error_data['parameters'])) {
                error_log("MWM DEBUG: Error Parameters: " . print_r($error_data['parameters'], true));
            }

            throw new Exception($message);
        }

        return json_decode($body, true);
    }

    /**
     * Build API URL
     *
     * @param string $endpoint API endpoint
     * @return string Full URL
     */
    private function build_url($endpoint) {
        // Remove leading slash
        $endpoint = ltrim($endpoint, '/');

        // Build URL based on API version
        // Magento 2 REST API uses /rest/V1/ for all requests
        // The "default" in path is for store code, not API version
        if ($this->api_version === 'V2') {
            // V2 setting means use simple path without store code
            return $this->store_url . '/rest/V1/' . $endpoint;
        } else {
            // V1 setting uses "default" store code in path
            return $this->store_url . '/rest/default/V1/' . $endpoint;
        }
    }

    /**
     * Build OAuth 1.0a parameters
     *
     * @param string $url Request URL
     * @param string $method HTTP method
     * @param array $data Request data
     * @return array OAuth parameters
     */
    private function build_oauth_params($url, $method, $data = array()) {
        $debug_mode = defined('WP_DEBUG') && WP_DEBUG;

        $timestamp = time();
        $nonce = wp_generate_password(12, false);

        if ($debug_mode) {
            error_log("MWM DEBUG: OAuth Timestamp: $timestamp");
            error_log("MWM DEBUG: OAuth Nonce: $nonce");
        }

        // Build base string
        $base_params = array(
            'oauth_consumer_key' => $this->consumer_key,
            'oauth_token' => $this->access_token,
            'oauth_signature_method' => 'HMAC-SHA256',
            'oauth_timestamp' => $timestamp,
            'oauth_nonce' => $nonce,
            'oauth_version' => '1.0'
        );

        // For GET, params are in URL; for POST, in body
        if ($method === 'GET') {
            $query_params = array_merge($base_params, $data);
        } else {
            $query_params = $base_params;
        }

        // Sort parameters for signature
        uksort($query_params, 'strcmp');

        if ($debug_mode) {
            error_log("MWM DEBUG: Sorted parameters for signature:");
            foreach ($query_params as $key => $value) {
                error_log("  $key => $value");
            }
        }

        // Build base string
        $base_string = $this->build_base_string($method, $url, $query_params);

        if ($debug_mode) {
            error_log("MWM DEBUG: OAuth Base String (first 200 chars): " . substr($base_string, 0, 200) . '...');
        }

        // Generate signature
        $signing_key = rawurlencode($this->consumer_secret) . '&' . rawurlencode($this->access_token_secret);

        if ($debug_mode) {
            error_log("MWM DEBUG: Signing Key (partial): " . substr($signing_key, 0, 20) . '...');
        }

        $signature = hash_hmac('sha256', $base_string, $signing_key, true);
        $encoded_signature = base64_encode($signature);

        if ($debug_mode) {
            error_log("MWM DEBUG: OAuth Signature (first 30 chars): " . substr($encoded_signature, 0, 30) . '...');
        }

        $base_params['oauth_signature'] = $encoded_signature;

        return $base_params;
    }

    /**
     * Build OAuth base string
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array $params Parameters
     * @return string Base string
     */
    private function build_base_string($method, $url, $params) {
        // Encode method and URL
        $encoded_method = strtoupper($method);
        $encoded_url = rawurlencode($url);

        // Sort parameters
        uksort($params, 'strcmp');

        // Build parameter string with proper encoding
        $param_parts = array();
        foreach ($params as $key => $value) {
            // Encode both key and value
            $param_parts[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        // Join with & and encode the entire string
        $query_string = rawurlencode(implode('&', $param_parts));

        return $encoded_method . '&' . $encoded_url . '&' . $query_string;
    }

    /**
     * Format error message
     *
     * @param string $error Raw error message
     * @return string Formatted error message
     */
    private function format_error_message($error) {
        // Check for common errors
        if (strpos($error, 'cURL error') !== false) {
            return __('Could not connect to Magento store. Please check the URL and ensure the store is accessible.', 'magento-wordpress-migrator');
        }

        if (strpos($error, '401') !== false || strpos($error, 'Unauthorized') !== false) {
            return __('Authentication failed (401). Please check your Consumer Key, Consumer Secret, Access Token, and Access Token Secret.', 'magento-wordpress-migrator');
        }

        if (strpos($error, '403') !== false || strpos($error, 'authorized to access') !== false) {
            return __('Access denied (403). The OAuth consumer does not have permission to access this resource. Please check the integration permissions in Magento admin.', 'magento-wordpress-migrator');
        }

        if (strpos($error, '404') !== false) {
            return __('API endpoint not found (404). Please check the API version setting and ensure Magento REST API is enabled.', 'magento-wordpress-migrator');
        }

        if (strpos($error, 'SSL') !== false || strpos($error, 'certificate') !== false) {
            return __('SSL certificate error. The Magento store may have an invalid or self-signed certificate.', 'magento-wordpress-migrator');
        }

        if (strpos($error, 'timed out') !== false) {
            return __('Connection timed out. The Magento store did not respond within the time limit.', 'magento-wordpress-migrator');
        }

        if (strpos($error, 'signature') !== false || strpos($error, 'oauth') !== false) {
            return __('OAuth signature error. Please verify that all API credentials are entered correctly.', 'magento-wordpress-migrator');
        }

        // Return the original error if not recognized
        return sprintf(__('Error: %s', 'magento-wordpress-migrator'), $error);
    }

    /**
     * Batch request - get multiple pages
     *
     * @param string $endpoint API endpoint
     * @param callable $callback Callback function for each item
     * @param int $page_size Items per page
     * @return array Statistics
     */
    public function batch_request($endpoint, $callback, $page_size = 20) {
        $page = 1;
        $total_items = 0;
        $processed_items = 0;
        $successful = 0;
        $failed = 0;

        while (true) {
            try {
                $search_criteria = array(
                    'searchCriteria' => array(
                        'currentPage' => $page,
                        'pageSize' => $page_size
                    )
                );

                $result = $this->request('GET', $endpoint, $search_criteria);

                if (!isset($result['items']) || empty($result['items'])) {
                    break;
                }

                // Get total count from first page
                if ($page === 1 && isset($result['total_count'])) {
                    $total_items = (int) $result['total_count'];
                }

                // Process items
                foreach ($result['items'] as $item) {
                    try {
                        $callback($item);
                        $successful++;
                    } catch (Exception $e) {
                        $failed++;
                        error_log('MWM API: Item processing failed - ' . $e->getMessage());
                    }
                    $processed_items++;
                }

                // Check if we've processed all items
                if (isset($result['total_count']) && $processed_items >= $result['total_count']) {
                    break;
                }

                // If we got less items than page size, we're done
                if (count($result['items']) < $page_size) {
                    break;
                }

                $page++;

                // Small delay to avoid overwhelming the server
                usleep(100000); // 0.1 seconds

            } catch (Exception $e) {
                error_log('MWM API: Batch request failed on page ' . $page . ' - ' . $e->getMessage());
                break;
            }
        }

        return array(
            'total' => $total_items,
            'processed' => $processed_items,
            'successful' => $successful,
            'failed' => $failed
        );
    }
}
