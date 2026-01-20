<?php
/**
 * Magento Connector for WordPress Migration Plugin
 *
 * This file provides a simple API endpoint for the WordPress plugin
 * to connect to Magento and retrieve products and categories.
 *
 * INSTALLATION:
 * 1. Upload this file to your Magento root directory
 * 2. Visit https://your-magento-site.com/magento-connector.php?generate_key
 * 3. Copy the generated API key
 * 4. Paste the key in WordPress plugin settings (Connector API Key field)
 * 5. Use connector URL: https://your-magento-site.com/magento-connector.php
 *
 * SECURITY:
 * - Uses API key authentication
 * - Validates requests to prevent unauthorized access
 * - Only allows read operations on products and categories
 * - Logs all access attempts
 *
 * @package Magento_Connector
 * @version 1.0.0
 */

// Prevent direct access if not called properly
if (!defined('MAGENTO_CONNECTOR_RUNNING')) {
    // If there's a generate_key parameter, allow access for setup
    if (isset($_GET['generate_key'])) {
        generate_api_key();
        exit;
    }

    // Otherwise, this is an API request
    define('MAGENTO_CONNECTOR_RUNNING', true);
}

// Start output buffering IMMEDIATELY to catch any PHP warnings/errors
ob_start();

// Error reporting - log but don't display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/var/log/connector-errors.log');

// Set custom error handler to catch PHP errors and prevent them from corrupting JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("MAGENTO CONNECTOR PHP Error: [$errno] $errstr in $errfile on line $errline");
    // Don't execute PHP internal error handler
    return true;
});

/**
 * Generate API Key for First-Time Setup
 */
function generate_api_key() {
    $key = generate_secure_key();
    $config_file = dirname(__FILE__) . '/connector-config.php';

    // Create config file
    $config_content = '<?php' . "\n";
    $config_content .= '/**' . "\n";
    $config_content .= ' * Connector Configuration' . "\n";
    $config_content .= ' * Generated: ' . date('Y-m-d H:i:s') . "\n";
    $config_content .= ' */' . "\n";
    $config_content .= 'define("MAGENTO_CONNECTOR_KEY", "' . $key . '");' . "\n";

    if (file_put_contents($config_file, $config_content)) {
        // Secure the config file
        chmod($config_file, 0644);

        // Display success message
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Magento Connector - Setup Complete</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; }
        .key-display { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; margin: 15px 0; border-radius: 5px; }
        code { background: #e9ecef; padding: 3px 6px; border-radius: 3px; font-size: 14px; }
        h1 { color: #333; }
        h2 { color: #666; font-size: 18px; }
    </style>
</head>
<body>
    <h1>✓ Magento Connector Setup Complete</h1>

    <div class="success">
        <strong>Success!</strong> Your connector has been configured.
    </div>

    <h2>Your Connector API Key:</h2>
    <div class="key-display">
        <code style="font-size: 16px;">' . htmlspecialchars($key) . '</code>
    </div>

    <h2>Next Steps:</h2>
    <ol>
        <li>Copy the API key above</li>
        <li>Go to your WordPress Admin → Magento → Migrator → Settings</li>
        <li>Enter this key in the <strong>"Connector API Key"</strong> field</li>
        <li>Set <strong>"Connection Mode"</strong> to <code>Connector</code></li>
        <li>Enter connector URL: <code>' . get_current_url() . '</code></li>
        <li>Save settings and test connection</li>
    </ol>

    <h2>Security Notice:</h2>
    <p>The configuration file has been created at:</p>
    <code>' . htmlspecialchars($config_file) . '</code>

    <p><strong>Important:</strong> Keep your API key secure. Do not share it publicly.</p>

    <p><em>You can now delete the <code>?generate_key</code> parameter from your URL.</em></p>
</body>
</html>';
    } else {
        // Error creating config
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Magento Connector - Setup Error</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; }
        h1 { color: #333; }
    </style>
</head>
<body>
    <h1>✗ Setup Error</h1>
    <div class="error">
        <strong>Error:</strong> Could not create configuration file.<br><br>
        Please make sure the Magento root directory is writable by the web server.
    </div>
</body>
</html>';
    }
}

/**
 * Generate a secure random API key
 */
function generate_secure_key() {
    return bin2hex(random_bytes(32));
}

/**
 * Get current URL
 */
function get_current_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Load Magento
 */
function load_magento() {
    $mage_path = dirname(__FILE__) . '/app/Mage.php';

    if (!file_exists($mage_path)) {
        // Try Magento 2 path
        $mage2_path = dirname(__FILE__) . '/vendor/magento/framework/App/Bootstrap.php';
        if (file_exists($mage2_path)) {
            return load_magento2();
        }
        send_error('Magento installation not found. This file must be placed in Magento root directory.', 500);
    }

    // Magento 1
    require_once $mage_path;

    // Suppress any output from Magento initialization
    ob_start();

    // Initialize Magento app with error suppression
    try {
        @Mage::app();
    } catch (Exception $e) {
        ob_end_clean();
        send_error('Failed to initialize Magento: ' . $e->getMessage(), 500);
    }

    // Clean any output from Magento initialization
    ob_end_clean();

    return array('version' => 1);
}

/**
 * Load Magento 2
 */
function load_magento2() {
    $bootstrap_path = dirname(__FILE__) . '/vendor/magento/framework/App/Bootstrap.php';
    if (!file_exists($bootstrap_path)) {
        send_error('Magento 2 Bootstrap not found.', 500);
    }

    // Check if this is Magento 2
    if (!file_exists(dirname(__FILE__) . '/app/bootstrap.php')) {
        send_error('Magento 2 app/bootstrap.php not found.', 500);
    }

    try {
        // Include Magento 2 bootstrap
        require_once dirname(__FILE__) . '/app/bootstrap.php';

        // Create bootstrap instance but don't run the application
        $bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);

        // Initialize ObjectManager
        $objectManager = $bootstrap->getObjectManager();

        // Verify Magento is loaded by trying to get a basic service
        try {
            $appState = $objectManager->get(\Magento\Framework\App\State::class);
            $appState->setAreaCode('frontend');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // Area code already set - ignore
        }

        // Store ObjectManager globally for use in other functions
        $GLOBALS['MAGENTO_OBJECT_MANAGER'] = $objectManager;

        return array('version' => 2);

    } catch (Exception $e) {
        send_error('Failed to bootstrap Magento 2: ' . $e->getMessage(), 500);
    }
}

/**
 * Get media base URL
 */
function get_connector_media_url() {
    $magento = load_magento();
    if ($magento['version'] == 1) {
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product';
    } else {
        $objectManager = $GLOBALS['MAGENTO_OBJECT_MANAGER'];
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        return $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product';
    }
}


/**
 * Authenticate request
 */
function authenticate_request() {
    $config_file = dirname(__FILE__) . '/connector-config.php';

    if (!file_exists($config_file)) {
        send_error('Connector not configured. Visit ?generate_key to set up.', 401);
    }

    require_once $config_file;

    if (!defined('MAGENTO_CONNECTOR_KEY')) {
        send_error('Connector configuration invalid.', 500);
    }

    // Get API key from headers or POST data
    $api_key = '';
    if (isset($_SERVER['HTTP_X_MAGENTO_CONNECTOR_KEY'])) {
        $api_key = $_SERVER['HTTP_X_MAGENTO_CONNECTOR_KEY'];
    } elseif (isset($_POST['api_key'])) {
        $api_key = $_POST['api_key'];
    } elseif (isset($_GET['api_key'])) {
        $api_key = $_GET['api_key'];
    }

    if (empty($api_key)) {
        send_error('API key missing. Please provide X-Magento-Connector-Key header or api_key parameter.', 401);
    }

    if (!hash_equals(MAGENTO_CONNECTOR_KEY, $api_key)) {
        // Log failed attempt
        log_access('FAILED', 'Invalid API key');
        send_error('Invalid API key.', 403);
    }

    // Log successful access
    log_access('SUCCESS', 'Authenticated');

    return true;
}

/**
 * Log access attempts
 */
function log_access($status, $message) {
    $log_file = dirname(__FILE__) . '/var/log/connector-access.log';
    $log_dir = dirname($log_file);

    // Create log directory if it doesn't exist
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'];
    $request_uri = $_SERVER['REQUEST_URI'];

    $log_entry = "[$timestamp] [$status] $ip - $message - $request_uri\n";

    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
 * Send JSON response
 */
function send_response($data, $status_code = 200) {
    // Clean output buffer to prevent any PHP warnings from corrupting JSON
    ob_end_clean();

    // Restore error handler
    restore_error_handler();

    // Encode data to JSON
    $json = json_encode($data);

    // Check if JSON encoding succeeded
    if ($json === false) {
        error_log("MAGENTO CONNECTOR: JSON encode failed - " . json_last_error_msg());
        error_log("MAGENTO CONNECTOR: Data that failed to encode: " . print_r($data, true));
        // Try to send error response
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => false,
            'message' => 'Internal server error: Failed to encode response'
        ));
        exit;
    }

    http_response_code($status_code);
    header('Content-Type: application/json');
    echo $json;
    exit;
}

/**
 * Send error response
 */
function send_error($message, $status_code = 400) {
    send_response(array(
        'success' => false,
        'message' => $message
    ), $status_code);
}

/**
 * Get products from Magento
 */
function get_products($limit = 100, $page = 1) {
    $magento = load_magento();

    if ($magento['version'] == 1) {
        return get_products_magento1($limit, $page);
    } else {
        return get_products_magento2($limit, $page);
    }
}

/**
 * Get products from Magento 1
 */
function get_products_magento1($limit, $page) {
    try {
        $products = Mage::getModel('catalog/product')->getCollection()
            ->setStoreId(0) // Use admin store to get all products
            ->addAttributeToSelect('*')
            ->addAttributeToSort('entity_id', 'ASC') // Consistent pagination
            ->setPageSize($limit)
            ->setCurPage($page)
            ->load();

        $result = array();
        foreach ($products as $product) {
            // Get product media gallery for Magento 1
            $media_gallery = array();
            try {
                $gallery = $product->getMediaGalleryImages();
                if ($gallery && count($gallery) > 0) {
                    foreach ($gallery as $image) {
                        $media_gallery[] = array(
                            'value' => $image->getFile(),
                            'file' => $image->getFile(),
                            'label' => $image->getLabel() ?: $product->getName(),
                            'position' => $image->getPosition(),
                            'media_type' => 'image',
                            'disabled' => $image->getDisabled(),
                        );
                    }
                }
            } catch (Exception $e) {
                // If media gallery fails, continue without it
                error_log("Warning: Could not load media gallery for M1 product {$product->getSku()}: " . $e->getMessage());
            }

            $result[] = array(
                'entity_id' => $product->getId(),
                'id' => $product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'price' => $product->getPrice(),
                'description' => $product->getDescription(),
                'short_description' => $product->getShortDescription(),
                'weight' => $product->getWeight(),
                'status' => $product->getStatus(),
                'visibility' => $product->getVisibility(),
                'type_id' => $product->getTypeId(),
                'created_at' => $product->getCreatedAt(),
                'updated_at' => $product->getUpdatedAt(),
                'stock_data' => array(
                    'qty' => $product->getStockItem()->getQty(),
                    'is_in_stock' => $product->getStockItem()->getIsInStock(),
                ),
                'category_ids' => $product->getCategoryIds(),
                'website_ids' => $product->getWebsiteIds(),
                'media' => $media_gallery,
                'image' => $product->getImage(),
                'small_image' => $product->getSmallImage(),
                'thumbnail' => $product->getThumbnail(),
                'attributes' => get_product_attributes_magento1($product)
            );
        }

        return array(
            'success' => true,
            'products' => $result,
            'page' => $page,
            'limit' => $limit,
            'total' => $products->getSize()
        );

    } catch (Exception $e) {
        send_error('Error fetching products: ' . $e->getMessage(), 500);
    }
}

/**
 * Get product attributes from Magento 1
 */
function get_product_attributes_magento1($product) {
    $attributes = array();
    foreach ($product->getAttributes() as $attribute) {
        if ($attribute->getIsVisibleOnFront()) {
            $attributes[] = array(
                'attribute_code' => $attribute->getAttributeCode(),
                'label' => $attribute->getFrontendLabel(),
                'value' => $attribute->getFrontend()->getValue($product)
            );
        }
    }
    return $attributes;
}

/**
 * Get products from Magento 2
 */
function get_products_magento2($limit, $page) {



  try {
        $objectManager = $GLOBALS['MAGENTO_OBJECT_MANAGER'];
        
        // You might want to get storeId from context or configuration
        $storeId = 1; // Default store ID, adjust as needed

        $productCollectionFactory = $objectManager->get('\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');
        $collection = $productCollectionFactory->create();
        $collection->setStoreId(0); // Use admin store (0) instead of 1 to get all products
        $collection->addAttributeToSelect('*');
        
        // Ensure consistent ordering for pagination
        $collection->setOrder('entity_id', 'ASC');
        
        // To get ALL products (both enabled and disabled), don't filter by status
        // If you want to explicitly include both, you can do:
        $collection->addAttributeToFilter('status', ['in' => [
            \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
            \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED
        ]]);
        
        // Apply pagination
        $collection->setPageSize($limit);
        $collection->setCurPage($page);
        
        // For better performance, join stock information
        $collection->joinField(
            'qty',
            'cataloginventory_stock_item',
            'qty',
            'product_id=entity_id',
            '{{table}}.stock_id=1',
            'left'
        )->joinField(
            'is_in_stock',
            'cataloginventory_stock_item',
            'is_in_stock',
            'product_id=entity_id',
            '{{table}}.stock_id=1',
            'left'
        );
        
        $collection->load();

        $result = array();
        foreach ($collection as $product) {
            // Get stock item from loaded data or extension attributes
            $stockItem = $product->getExtensionAttributes() ? $product->getExtensionAttributes()->getStockItem() : null;

            // Get product media gallery
            $media_gallery = array();
            try {
                $mediaGalleryEntries = $product->getMediaGalleryEntries();
                if ($mediaGalleryEntries && is_array($mediaGalleryEntries)) {
                    foreach ($mediaGalleryEntries as $gallery_image) {
                        // Only include image type media, exclude videos
                        if ($gallery_image->getMediaType() === 'image' || !$gallery_image->isDisabled()) {
                            $media_gallery[] = array(
                                'value' => $gallery_image->getFile(),
                                'file' => $gallery_image->getFile(),
                                'label' => $gallery_image->getLabel() ?: $product->getName(),
                                'position' => $gallery_image->getPosition(),
                                'media_type' => $gallery_image->getMediaType(),
                                'disabled' => $gallery_image->isDisabled(),
                            );
                        }
                    }
                }
            } catch (Exception $e) {
                // If media gallery fails, continue without it
                error_log("Warning: Could not load media gallery for product {$product->getSku()}: " . $e->getMessage());
            }

            $result[] = array(
                'entity_id' => $product->getId(),
                'id' => $product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'price' => $product->getPrice(),
                'description' => $product->getDescription(),
                'short_description' => $product->getShortDescription(),
                'weight' => $product->getWeight(),
                'status' => $product->getStatus(),
                'visibility' => $product->getVisibility(),
                'type_id' => $product->getTypeId(),
                'created_at' => $product->getCreatedAt(),
                'updated_at' => $product->getUpdatedAt(),
                'stock_data' => array(
                    'qty' => $stockItem ? $stockItem->getQty() : (float) $product->getData('qty'),
                    'is_in_stock' => $stockItem ? $stockItem->getIsInStock() : (bool) $product->getData('is_in_stock'),
                ),
                'category_ids' => $product->getCategoryIds(),
                'website_ids' => $product->getWebsiteIds(),
                'media' => $media_gallery,
                'image' => $product->getImage(),
                'small_image' => $product->getSmallImage(),
                'thumbnail' => $product->getThumbnail(),
            );
        }

        // Get total count for pagination info
        $total = $collection->getSize();

        return array(
            'success' => true,
            'products' => $result,
            'total' => $total,
            'current_page' => $page,
            'page_size' => $limit,
            'total_pages' => ceil($total / $limit),
            'media_url' => get_connector_media_url()
        );

    } catch (Exception $e) {
        send_error('Error fetching products from Magento 2: ' . $e->getMessage(), 500);
    }



//   try {
//         $objectManager = $GLOBALS['MAGENTO_OBJECT_MANAGER'];

//         $productCollectionFactory = $objectManager->get('\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');
//         $collection = $productCollectionFactory->create();
//         $collection->setStoreId($storeId);
//         $collection->addAttributeToSelect('*');
//         // REMOVE these lines:
//         // $collection->setPageSize($limit);
//         // $collection->setCurPage($page);
//         $collection->load();

//         $result = array();
//         foreach ($collection as $product) {
//             $stockItem = $product->getExtensionAttributes()->getStockItem();

//             $result[] = array(
//                 'id' => $product->getId(),
//                 'sku' => $product->getSku(),
//                 'name' => $product->getName(),
//                 'price' => $product->getPrice(),
//                 'description' => $product->getDescription(),
//                 'short_description' => $product->getShortDescription(),
//                 'weight' => $product->getWeight(),
//                 'status' => $product->getStatus(),
//                 'visibility' => $product->getVisibility(),
//                 'type_id' => $product->getTypeId(),
//                 'created_at' => $product->getCreatedAt(),
//                 'updated_at' => $product->getUpdatedAt(),
//                 'stock_data' => array(
//                     'qty' => $stockItem ? $stockItem->getQty() : 0,
//                     'is_in_stock' => $stockItem ? $stockItem->getIsInStock() : false,
//                 ),
//                 'category_ids' => $product->getCategoryIds(),
//                 'website_ids' => $product->getWebsiteIds(),
//                 'image' => $product->getImage(),
//                 'small_image' => $product->getSmallImage(),
//                 'thumbnail' => $product->getThumbnail(),
//             );
//         }

//         return array(
//             'success' => true,
//             'products' => $result,
//             'total' => count($result)
//         );

//     } catch (Exception $e) {
//         send_error('Error fetching products from Magento 2: ' . $e->getMessage(), 500);
//     }
}

/**
 * Get product by SKU
 */
function get_product($sku) {
    $magento = load_magento();

    if ($magento['version'] == 1) {
        try {
            $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);

            if (!$product || !$product->getId()) {
                send_error('Product not found: ' . $sku, 404);
            }

            $result = array(
                'success' => true,
                'product' => array(
                    'id' => $product->getId(),
                    'sku' => $product->getSku(),
                    'name' => $product->getName(),
                    'price' => $product->getPrice(),
                    'description' => $product->getDescription(),
                    'short_description' => $product->getShortDescription(),
                    'weight' => $product->getWeight(),
                    'status' => $product->getStatus(),
                    'visibility' => $product->getVisibility(),
                    'type_id' => $product->getTypeId(),
                    'created_at' => $product->getCreatedAt(),
                    'updated_at' => $product->getUpdatedAt(),
                    'stock_data' => array(
                        'qty' => $product->getStockItem()->getQty(),
                        'is_in_stock' => $product->getStockItem()->getIsInStock(),
                    ),
                    'category_ids' => $product->getCategoryIds(),
                    'website_ids' => $product->getWebsiteIds(),
                    'attributes' => get_product_attributes_magento1($product)
                )
            );

            send_response($result);

        } catch (Exception $e) {
            send_error('Error fetching product: ' . $e->getMessage(), 500);
        }
    } else {
        // Magento 2
        try {
            $objectManager = $GLOBALS['MAGENTO_OBJECT_MANAGER'];
            $productRepository = $objectManager->get('\Magento\Catalog\Api\ProductRepositoryInterface');

            try {
                $product = $productRepository->get($sku);
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                send_error('Product not found: ' . $sku, 404);
            }

            $stockItem = $product->getExtensionAttributes()->getStockItem();

            // Get product images
            $media_gallery = $product->getMediaGalleryEntries();
            $media = array();
            if ($media_gallery) {
                foreach ($media_gallery as $gallery_image) {
                    $media[] = array(
                        'value' => $gallery_image->getFile(),
                        'file' => $gallery_image->getFile(),
                        'label' => $gallery_image->getLabel() ?: $product->getName(),
                        'position' => $gallery_image->getPosition(),
                        'media_type' => $gallery_image->getMediaType(),
                        'disabled' => $gallery_image->isDisabled(),
                    );
                }
            }

            $result = array(
                'success' => true,
                'product' => array(
                    'id' => $product->getId(),
                    'sku' => $product->getSku(),
                    'name' => $product->getName(),
                    'price' => $product->getPrice(),
                    'description' => $product->getDescription(),
                    'short_description' => $product->getShortDescription(),
                    'weight' => $product->getWeight(),
                    'status' => $product->getStatus(),
                    'visibility' => $product->getVisibility(),
                    'type_id' => $product->getTypeId(),
                    'created_at' => $product->getCreatedAt(),
                    'updated_at' => $product->getUpdatedAt(),
                    'stock_data' => array(
                        'qty' => $stockItem ? $stockItem->getQty() : 0,
                        'is_in_stock' => $stockItem ? $stockItem->getIsInStock() : false,
                    ),
                    'category_ids' => $product->getCategoryIds(),
                    'website_ids' => $product->getWebsiteIds(),
                    'media' => $media,
                    'image' => $product->getImage(),
                    'small_image' => $product->getSmallImage(),
                    'thumbnail' => $product->getThumbnail(),
                )
            );

            send_response($result);

        } catch (Exception $e) {
            send_error('Error fetching product: ' . $e->getMessage(), 500);
        }
    }
}

/**
 * Get categories from Magento
 */
function get_categories($parent_id = null) {
    $magento = load_magento();

    if ($magento['version'] == 1) {
        return get_categories_magento1($parent_id);
    } else {
        return get_categories_magento2($parent_id);
    }
}

/**
 * Get categories from Magento 1
 */
function get_categories_magento1($parent_id) {
    try {
        $collection = Mage::getModel('catalog/category')->getCollection()
            ->setStoreId(0)
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('is_active', 1)
            ->setOrder('name', 'ASC');

        if ($parent_id !== null) {
            $collection->addAttributeToFilter('parent_id', $parent_id);
        }

        $result = array();
        foreach ($collection as $category) {
            $result[] = array(
                'id' => $category->getId(),
                'name' => $category->getName(),
                'url_key' => $category->getUrlKey(),
                'url_path' => $category->getUrlPath(),
                'description' => $category->getDescription(),
                'display_mode' => $category->getDisplayMode(),
                'is_active' => $category->getIsActive(),
                'is_anchor' => $category->getIsAnchor(),
                'parent_id' => $category->getParentId(),
                'path' => $category->getPath(),
                'position' => $category->getPosition(),
                'level' => $category->getLevel(),
                'children_count' => $category->getChildrenCount(),
                'product_count' => $category->getProductCount(),
                'image' => $category->getImage(),
                'meta_title' => $category->getMetaTitle(),
                'meta_description' => $category->getMetaDescription(),
                'meta_keywords' => $category->getMetaKeywords()
            );
        }

        return array(
            'success' => true,
            'categories' => $result,
            'count' => count($result)
        );

    } catch (Exception $e) {
        send_error('Error fetching categories: ' . $e->getMessage(), 500);
    }
}

/**
 * Get categories from Magento 2
 */
function get_categories_magento2($parent_id = null) {
    try {
        $objectManager = $GLOBALS['MAGENTO_OBJECT_MANAGER'];
        $categoryFactory = $objectManager->get('\Magento\Catalog\Model\CategoryFactory');
        $collection = $categoryFactory->create()->getCollection();
        $collection->setStoreId(0);
        $collection->addAttributeToSelect('*');
        $collection->addAttributeToFilter('is_active', 1);
        $collection->setOrder('name', 'ASC');

        if ($parent_id !== null) {
            $collection->addAttributeToFilter('parent_id', $parent_id);
        }

        $result = array();
        foreach ($collection as $category) {
            $result[] = array(
                'id' => $category->getId(),
                'name' => $category->getName(),
                'url_key' => $category->getUrlKey(),
                'url_path' => $category->getUrlPath(),
                'description' => $category->getDescription(),
                'display_mode' => $category->getDisplayMode(),
                'is_active' => $category->getIsActive(),
                'is_anchor' => $category->getIsAnchor(),
                'parent_id' => $category->getParentId(),
                'path' => $category->getPath(),
                'position' => $category->getPosition(),
                'level' => $category->getLevel(),
                'children_count' => $category->getChildrenCount(),
                'product_count' => $category->getProductCount(),
                'image' => $category->getImage(),
                'meta_title' => $category->getMetaTitle(),
                'meta_description' => $category->getMetaDescription(),
                'meta_keywords' => $category->getMetaKeywords()
            );
        }

        return array(
            'success' => true,
            'categories' => $result,
            'count' => count($result)
        );

    } catch (Exception $e) {
        send_error('Error fetching categories: ' . $e->getMessage(), 500);
    }
}

/**
 * Get category by ID
 */
function get_category($category_id) {
    $magento = load_magento();

    if ($magento['version'] == 1) {
        try {
            $category = Mage::getModel('catalog/category')->load($category_id);

            if (!$category || !$category->getId()) {
                send_error('Category not found: ' . $category_id, 404);
            }

            $result = array(
                'success' => true,
                'category' => array(
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                    'url_key' => $category->getUrlKey(),
                    'url_path' => $category->getUrlPath(),
                    'description' => $category->getDescription(),
                    'display_mode' => $category->getDisplayMode(),
                    'is_active' => $category->getIsActive(),
                    'is_anchor' => $category->getIsAnchor(),
                    'parent_id' => $category->getParentId(),
                    'path' => $category->getPath(),
                    'position' => $category->getPosition(),
                    'level' => $category->getLevel(),
                    'children_count' => $category->getChildrenCount(),
                    'product_count' => $category->getProductCount()
                )
            );

            send_response($result);

        } catch (Exception $e) {
            send_error('Error fetching category: ' . $e->getMessage(), 500);
        }
    } else {
        // Magento 2
        try {
            $objectManager = $GLOBALS['MAGENTO_OBJECT_MANAGER'];
            $categoryFactory = $objectManager->get('\Magento\Catalog\Model\CategoryFactory');
            $category = $categoryFactory->create()->load($category_id);

            if (!$category || !$category->getId()) {
                send_error('Category not found: ' . $category_id, 404);
            }

            $result = array(
                'success' => true,
                'category' => array(
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                    'url_key' => $category->getUrlKey(),
                    'url_path' => $category->getUrlPath(),
                    'description' => $category->getDescription(),
                    'display_mode' => $category->getDisplayMode(),
                    'is_active' => $category->getIsActive(),
                    'is_anchor' => $category->getIsAnchor(),
                    'parent_id' => $category->getParentId(),
                    'path' => $category->getPath(),
                    'position' => $category->getPosition(),
                    'level' => $category->getLevel(),
                    'children_count' => $category->getChildrenCount(),
                    'product_count' => $category->getProductCount()
                )
            );

            send_response($result);

        } catch (Exception $e) {
            send_error('Error fetching category: ' . $e->getMessage(), 500);
        }
    }
}

/**
 * Get total count of products
 */
function get_products_count() {
    $magento = load_magento();

    if ($magento['version'] == 1) {
        try {
            $collection = Mage::getModel('catalog/product')->getCollection();
            $collection->setStoreId(0); // Admin store
            $count = $collection->getSize();

            send_response(array(
                'success' => true,
                'count' => $count
            ));

        } catch (Exception $e) {
            send_error('Error counting products: ' . $e->getMessage(), 500);
        }
    } else {
        // Magento 2
        try {
            $objectManager = $GLOBALS['MAGENTO_OBJECT_MANAGER'];
            $productCollectionFactory = $objectManager->get('\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');
            $collection = $productCollectionFactory->create();
            $collection->setStoreId(0); // Admin store
            $count = $collection->getSize();

            send_response(array(
                'success' => true,
                'count' => $count
            ));

        } catch (Exception $e) {
            send_error('Error counting products: ' . $e->getMessage(), 500);
        }
    }
}

/**
 * Get total count of categories
 */
function get_categories_count() {
    $magento = load_magento();

    if ($magento['version'] == 1) {
        try {
            $collection = Mage::getModel('catalog/category')->getCollection()
                ->addAttributeToFilter('is_active', 1);
            $count = $collection->getSize();

            send_response(array(
                'success' => true,
                'count' => $count
            ));

        } catch (Exception $e) {
            send_error('Error counting categories: ' . $e->getMessage(), 500);
        }
    } else {
        // Magento 2
        try {
            $objectManager = $GLOBALS['MAGENTO_OBJECT_MANAGER'];
            $categoryFactory = $objectManager->get('\Magento\Catalog\Model\CategoryFactory');
            $collection = $categoryFactory->create()->getCollection()
                ->addAttributeToFilter('is_active', 1);
            $count = $collection->getSize();

            send_response(array(
                'success' => true,
                'count' => $count
            ));

        } catch (Exception $e) {
            send_error('Error counting categories: ' . $e->getMessage(), 500);
        }
    }
}

/**
 * Get orders from Magento
 */
function get_orders($limit = 100, $page = 1) {
    $magento = load_magento();

    if ($magento['version'] == 1) {
        return get_orders_magento1($limit, $page);
    } else {
        return get_orders_magento2($limit, $page);
    }
}

/**
 * Get orders from Magento 1
 */
function get_orders_magento1($limit, $page) {
    try {
        $orders = Mage::getModel('sales/order')->getCollection()
            ->addAttributeToSelect('*')
            ->setPageSize($limit)
            ->setCurPage($page)
            ->load();

        $result = array();
        foreach ($orders as $order) {
            // Get order items
            $items = array();
            foreach ($order->getAllItems() as $item) {
                $items[] = array(
                    'item_id' => $item->getId(),
                    'sku' => $item->getSku(),
                    'name' => $item->getName(),
                    'qty_ordered' => $item->getQtyOrdered(),
                    'price' => $item->getPrice(),
                    'row_total' => $item->getRowTotal(),
                    'product_type' => $item->getProductType(),
                    'product_id' => $item->getProductId(),
                );
            }

            $result[] = array(
                'entity_id' => $order->getId(),
                'increment_id' => $order->getIncrementId(),
                'state' => $order->getState(),
                'status' => $order->getStatus(),
                'store_currency_code' => $order->getStoreCurrencyCode(),
                'order_currency_code' => $order->getOrderCurrencyCode(),
                'base_currency_code' => $order->getBaseCurrencyCode(),
                'store_to_base_rate' => $order->getStoreToBaseRate(),
                'base_to_global_rate' => $order->getBaseToGlobalRate(),
                'store_to_order_rate' => $order->getStoreToOrderRate(),
                'grand_total' => $order->getGrandTotal(),
                'subtotal' => $order->getSubtotal(),
                'discount_amount' => $order->getDiscountAmount(),
                'shipping_amount' => $order->getShippingAmount(),
                'tax_amount' => $order->getTaxAmount(),
                'customer_id' => $order->getCustomerId(),
                'customer_email' => $order->getCustomerEmail(),
                'customer_firstname' => $order->getCustomerFirstname(),
                'customer_lastname' => $order->getCustomerLastname(),
                'billing_address' => array(
                    'firstname' => $order->getBillingFirstname(),
                    'lastname' => $order->getBillingLastname(),
                    'company' => $order->getBillingCompany(),
                    'street' => $order->getBillingStreet(),
                    'city' => $order->getBillingCity(),
                    'region' => $order->getBillingRegion(),
                    'postcode' => $order->getBillingPostcode(),
                    'country_id' => $order->getBillingCountryId(),
                    'telephone' => $order->getBillingTelephone(),
                ),
                'shipping_address' => array(
                    'firstname' => $order->getShippingFirstname(),
                    'lastname' => $order->getShippingLastname(),
                    'company' => $order->getShippingCompany(),
                    'street' => $order->getShippingStreet(),
                    'city' => $order->getShippingCity(),
                    'region' => $order->getShippingRegion(),
                    'postcode' => $order->getShippingPostcode(),
                    'country_id' => $order->getShippingCountryId(),
                    'telephone' => $order->getShippingTelephone(),
                ),
                'items' => $items,
                'created_at' => $order->getCreatedAt(),
                'updated_at' => $order->getUpdatedAt(),
            );
        }

        return array(
            'success' => true,
            'orders' => $result,
            'page' => $page,
            'limit' => $limit,
            'total' => $orders->getSize()
        );

    } catch (Exception $e) {
        send_error('Error fetching orders: ' . $e->getMessage(), 500);
    }
}

/**
 * Get orders from Magento 2
 */
function get_orders_magento2($limit, $page) {
    try {
        $objectManager = $GLOBALS['MAGENTO_OBJECT_MANAGER'];

        // Get order collection
        $orderCollectionFactory = $objectManager->get('\Magento\Sales\Model\ResourceModel\Order\CollectionFactory');
        $collection = $orderCollectionFactory->create();

        $collection->addAttributeToSelect('*');
        $collection->setPageSize($limit);
        $collection->setCurPage($page);
        $collection->load();

        $result = array();
        foreach ($collection as $order) {
            $billingAddress = $order->getBillingAddress();
            $shippingAddress = $order->getShippingAddress();

            // Get order items
            $items = array();
            foreach ($order->getAllItems() as $item) {
                $items[] = array(
                    'item_id' => $item->getId(),
                    'sku' => $item->getSku(),
                    'name' => $item->getName(),
                    'qty_ordered' => $item->getQtyOrdered(),
                    'price' => $item->getPrice(),
                    'row_total' => $item->getRowTotal(),
                    'product_type' => $item->getProductType(),
                    'product_id' => $item->getProductId(),
                );
            }

            $result[] = array(
                'entity_id' => $order->getId(),
                'increment_id' => $order->getIncrementId(),
                'state' => $order->getState(),
                'status' => $order->getStatus(),
                'store_currency_code' => $order->getStoreCurrencyCode(),
                'order_currency_code' => $order->getOrderCurrencyCode(),
                'base_currency_code' => $order->getBaseCurrencyCode(),
                'grand_total' => $order->getGrandTotal(),
                'subtotal' => $order->getSubtotal(),
                'discount_amount' => $order->getDiscountAmount(),
                'shipping_amount' => $order->getShippingAmount(),
                'tax_amount' => $order->getTaxAmount(),
                'customer_id' => $order->getCustomerId(),
                'customer_email' => $order->getCustomerEmail(),
                'customer_firstname' => $order->getCustomerFirstname(),
                'customer_lastname' => $order->getCustomerLastname(),
                'billing_address' => $billingAddress ? array(
                    'firstname' => $billingAddress->getFirstname(),
                    'lastname' => $billingAddress->getLastname(),
                    'company' => $billingAddress->getCompany(),
                    'street' => $billingAddress->getStreet(),
                    'city' => $billingAddress->getCity(),
                    'region' => $billingAddress->getRegion(),
                    'postcode' => $billingAddress->getPostcode(),
                    'country_id' => $billingAddress->getCountryId(),
                    'telephone' => $billingAddress->getTelephone(),
                ) : array(),
                'shipping_address' => $shippingAddress ? array(
                    'firstname' => $shippingAddress->getFirstname(),
                    'lastname' => $shippingAddress->getLastname(),
                    'company' => $shippingAddress->getCompany(),
                    'street' => $shippingAddress->getStreet(),
                    'city' => $shippingAddress->getCity(),
                    'region' => $shippingAddress->getRegion(),
                    'postcode' => $shippingAddress->getPostcode(),
                    'country_id' => $shippingAddress->getCountryId(),
                    'telephone' => $shippingAddress->getTelephone(),
                ) : array(),
                'items' => $items,
                'created_at' => $order->getCreatedAt(),
                'updated_at' => $order->getUpdatedAt(),
            );
        }

        return array(
            'success' => true,
            'orders' => $result,
            'page' => $page,
            'limit' => $limit,
            'total' => $collection->getSize()
        );

    } catch (Exception $e) {
        send_error('Error fetching orders from Magento 2: ' . $e->getMessage(), 500);
    }
}

/**
 * Get total count of orders
 */
function get_orders_count() {
    $magento = load_magento();

    if ($magento['version'] == 1) {
        try {
            $collection = Mage::getModel('sales/order')->getCollection();
            $count = $collection->getSize();

            send_response(array(
                'success' => true,
                'count' => $count
            ));

        } catch (Exception $e) {
            send_error('Error counting orders: ' . $e->getMessage(), 500);
        }
    } else {
        // Magento 2
        try {
            $objectManager = $GLOBALS['MAGENTO_OBJECT_MANAGER'];
            $orderCollectionFactory = $objectManager->get('\Magento\Sales\Model\ResourceModel\Order\CollectionFactory');
            $collection = $orderCollectionFactory->create();
            $count = $collection->getSize();

            send_response(array(
                'success' => true,
                'count' => $count
            ));

        } catch (Exception $e) {
            send_error('Error counting orders: ' . $e->getMessage(), 500);
        }
    }
}

/**
 * Get customers from Magento
 */
function get_customers($limit = 100, $page = 1) {
    $magento = load_magento();

    if ($magento['version'] == 1) {
        return get_customers_magento1($limit, $page);
    } else {
        return get_customers_magento2($limit, $page);
    }
}

/**
 * Get customers from Magento 1
 */
function get_customers_magento1($limit, $page) {
    try {
        $collection = Mage::getModel('customer/customer')->getCollection()
            ->addAttributeToSelect('*')
            ->setPageSize($limit)
            ->setCurPage($page)
            ->load();

        $result = array();
        foreach ($collection as $customer) {
            // Get customer addresses
            $addresses = array();
            foreach ($customer->getAddresses() as $address) {
                $addresses[] = array(
                    'entity_id' => $address->getId(),
                    'firstname' => $address->getFirstname(),
                    'lastname' => $address->getLastname(),
                    'company' => $address->getCompany(),
                    'street' => $address->getStreet(),
                    'city' => $address->getCity(),
                    'region' => $address->getRegion(),
                    'region_id' => $address->getRegionId(),
                    'postcode' => $address->getPostcode(),
                    'country_id' => $address->getCountryId(),
                    'telephone' => $address->getTelephone(),
                    'fax' => $address->getFax(),
                    'default_billing' => $address->getId() == $customer->getDefaultBilling(),
                    'default_shipping' => $address->getId() == $customer->getDefaultShipping(),
                );
            }

            $result[] = array(
                'entity_id' => $customer->getId(),
                'email' => $customer->getEmail(),
                'firstname' => $customer->getFirstname(),
                'lastname' => $customer->getLastname(),
                'website_id' => $customer->getWebsiteId(),
                'store_id' => $customer->getStoreId(),
                'created_at' => $customer->getCreatedAt(),
                'updated_at' => $customer->getUpdatedAt(),
                'is_active' => $customer->getIsActive(),
                'group_id' => $customer->getGroupId(),
                'addresses' => $addresses,
            );
        }

        return array(
            'success' => true,
            'customers' => $result,
            'page' => $page,
            'limit' => $limit,
            'total' => $collection->getSize()
        );

    } catch (Exception $e) {
        send_error('Error fetching customers from Magento 1: ' . $e->getMessage(), 500);
    }
}

/**
 * Get customers from Magento 2
 */
function get_customers_magento2($limit, $page) {
    try {
        $objectManager = $GLOBALS['MAGENTO_OBJECT_MANAGER'];
        $resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
        $connection = $resource->getConnection();

        // Get customer collection
        $customerCollectionFactory = $objectManager->get(\Magento\Customer\Model\ResourceModel\Customer\CollectionFactory::class);
        $collection = $customerCollectionFactory->create();

        $collection->addAttributeToSelect('*');
        $collection->setPageSize($limit);
        $collection->setCurPage($page);
        
        // Get all customer IDs for batch order total query
        $customerIds = $collection->getAllIds();
        
        // Fetch COMPREHENSIVE order statistics for all customers in one query
        $orderStats = [];
        if (!empty($customerIds)) {
            $query = $connection->select()
                ->from(
                    ['o' => $connection->getTableName('sales_order')],
                    [
                        'customer_id',
                        // Total metrics
                        'total_spend' => 'SUM(CASE WHEN o.state IN ("processing", "complete", "closed") THEN o.grand_total ELSE 0 END)',
                        'base_total_spend' => 'SUM(CASE WHEN o.state IN ("processing", "complete", "closed") THEN o.base_grand_total ELSE 0 END)',
                        
                        // Order counts by status
                        'total_orders' => 'COUNT(o.entity_id)',
                        'completed_orders' => 'SUM(CASE WHEN o.state = "complete" THEN 1 ELSE 0 END)',
                        'processing_orders' => 'SUM(CASE WHEN o.state = "processing" THEN 1 ELSE 0 END)',
                        'cancelled_orders' => 'SUM(CASE WHEN o.state = "canceled" THEN 1 ELSE 0 END)',
                        'closed_orders' => 'SUM(CASE WHEN o.state = "closed" THEN 1 ELSE 0 END)',
                        
                        // Date metrics
                        'first_order_date' => 'MIN(o.created_at)',
                        'last_order_date' => 'MAX(o.created_at)',
                        
                        // Additional metrics
                        'avg_order_value' => 'AVG(CASE WHEN o.state IN ("processing", "complete", "closed") THEN o.grand_total END)',
                        'max_order_value' => 'MAX(CASE WHEN o.state IN ("processing", "complete", "closed") THEN o.grand_total END)',
                        'min_order_value' => 'MIN(CASE WHEN o.state IN ("processing", "complete", "closed") THEN o.grand_total END)',
                        
                        // Refund metrics
                        'total_refunded' => 'SUM(o.total_refunded)',
                        'has_refunds' => 'SUM(CASE WHEN o.total_refunded > 0 THEN 1 ELSE 0 END)'
                    ]
                )
                ->where('o.customer_id IN (?)', $customerIds)
                ->group('o.customer_id');
            
            $orderData = $connection->fetchAll($query);
            
            foreach ($orderData as $row) {
                $orderStats[$row['customer_id']] = [
                    // Spend metrics
                    'total_spend' => (float)$row['total_spend'],
                    'base_total_spend' => (float)$row['base_total_spend'],
                    
                    // Order counts
                    'total_orders' => (int)$row['total_orders'],
                    'completed_orders' => (int)$row['completed_orders'],
                    'processing_orders' => (int)$row['processing_orders'],
                    'cancelled_orders' => (int)$row['cancelled_orders'],
                    'closed_orders' => (int)$row['closed_orders'],
                    'valid_orders' => (int)($row['completed_orders'] + $row['processing_orders'] + $row['closed_orders']),
                    
                    // Date metrics
                    'first_order_date' => $row['first_order_date'],
                    'last_order_date' => $row['last_order_date'],
                    
                    // Value metrics
                    'average_order_value' => (float)$row['avg_order_value'],
                    'max_order_value' => (float)$row['max_order_value'],
                    'min_order_value' => (float)$row['min_order_value'],
                    
                    // Refund metrics
                    'total_refunded' => (float)$row['total_refunded'],
                    'has_refunds' => (bool)$row['has_refunded'],
                    'net_spend' => (float)($row['total_spend'] - $row['total_refunded'])
                ];
            }
        }
        
        $collection->load();
        
        // Get address repository
        $addressRepository = $objectManager->get(\Magento\Customer\Api\AddressRepositoryInterface::class);
        $customerRepository = $objectManager->get(\Magento\Customer\Api\CustomerRepositoryInterface::class);

        $result = array();
        foreach ($collection as $customer) {
            $customerId = $customer->getId();
            $stats = $orderStats[$customerId] ?? [
                'total_spend' => 0,
                'base_total_spend' => 0,
                'total_orders' => 0,
                'completed_orders' => 0,
                'processing_orders' => 0,
                'cancelled_orders' => 0,
                'closed_orders' => 0,
                'valid_orders' => 0,
                'first_order_date' => null,
                'last_order_date' => null,
                'average_order_value' => 0,
                'max_order_value' => 0,
                'min_order_value' => 0,
                'total_refunded' => 0,
                'has_refunds' => false,
                'net_spend' => 0
            ];
            
            $addresses = array();
            
            try {
                // ... [KEEP ALL YOUR ORIGINAL ADDRESS FETCHING CODE HERE] ...
                // Method 1: Using customer repository
                $customerData = $customerRepository->getById($customerId);
                $customerAddresses = $customerData->getAddresses();
                
                // Method 2: Alternative using customer model directly
                if (!$customerAddresses || empty($customerAddresses)) {
                    $customer->load($customerId);
                    $customerAddresses = $customer->getAddresses();
                }
                
                // Method 3: Direct SQL approach if above methods fail
                if (!$customerAddresses || empty($customerAddresses)) {
                    // ... [YOUR EXISTING SQL ADDRESS QUERY] ...
                } else {
                    // Process addresses from repository
                    if ($customerAddresses) {
                        foreach ($customerAddresses as $address) {
                            $addresses[] = array(
                                'entity_id' => $address->getId(),
                                'firstname' => $address->getFirstname(),
                                'lastname' => $address->getLastname(),
                                // ... [OTHER ADDRESS FIELDS] ...
                            );
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Error fetching addresses for customer ID " . $customerId . ": " . $e->getMessage());
            }

            $result[] = array(
                'entity_id' => $customerId,
                'email' => $customer->getEmail(),
                'firstname' => $customer->getFirstname(),
                'lastname' => $customer->getLastname(),
                'website_id' => $customer->getWebsiteId(),
                'store_id' => $customer->getStoreId(),
                'created_at' => $customer->getCreatedAt(),
                'updated_at' => $customer->getUpdatedAt(),
                'is_active' => $customer->getIsActive(),
                'group_id' => $customer->getGroupId(),
                
                // ORDER STATISTICS
                'total_orders_count' => $stats['total_orders'],
                'completed_orders' => $stats['completed_orders'],
                'processing_orders' => $stats['processing_orders'],
                'cancelled_orders' => $stats['cancelled_orders'],
                'closed_orders' => $stats['closed_orders'],
                'valid_orders' => $stats['valid_orders'],
                
                // SPEND METRICS
                'total_spend' => $stats['total_spend'],
                'base_total_spend' => $stats['base_total_spend'],
                'total_refunded' => $stats['total_refunded'],
                'net_spend' => $stats['net_spend'],
                'has_refunds' => $stats['has_refunds'],
                
                // ORDER VALUE METRICS
                'average_order_value' => $stats['average_order_value'],
                'max_order_value' => $stats['max_order_value'],
                'min_order_value' => $stats['min_order_value'],
                
                // DATE METRICS
                'first_order_date' => $stats['first_order_date'],
                'last_order_date' => $stats['last_order_date'],
                'is_returning_customer' => $stats['total_orders'] > 1,
                'days_since_last_order' => $stats['last_order_date'] 
                    ? floor((time() - strtotime($stats['last_order_date'])) / 86400) 
                    : null,
                
                // ADDRESSES (from your original code)
                'addresses' => $addresses,
                'has_addresses' => !empty($addresses),
            );
        }

        return array(
            'success' => true,
            'customers' => $result,
            'page' => $page,
            'limit' => $limit,
            'total' => $collection->getSize()
        );

    } catch (Exception $e) {
        send_error('Error fetching customers from Magento 2: ' . $e->getMessage(), 500);
    }
}
/**
 * Get total count of customers
 */
function get_customers_count() {
    $magento = load_magento();

    if ($magento['version'] == 1) {
        try {
            $collection = Mage::getModel('customer/customer')->getCollection();
            $count = $collection->getSize();

            send_response(array(
                'success' => true,
                'count' => $count
            ));

        } catch (Exception $e) {
            send_error('Error counting customers: ' . $e->getMessage(), 500);
        }
    } else {
        // Magento 2
        try {
            $objectManager = $GLOBALS['MAGENTO_OBJECT_MANAGER'];
            $customerCollectionFactory = $objectManager->get('\Magento\Customer\Model\ResourceModel\Customer\CollectionFactory');
            $collection = $customerCollectionFactory->create();
            $count = $collection->getSize();

            send_response(array(
                'success' => true,
                'count' => $count
            ));

        } catch (Exception $e) {
            send_error('Error counting customers: ' . $e->getMessage(), 500);
        }
    }
}

/**
 * Test connection
 */
function test_connection() {
    try {
        $magento = load_magento();

        send_response(array(
            'success' => true,
            'message' => 'Connection successful',
            'magento_version' => $magento['version'] == 1 ? 'Magento 1' : 'Magento 2',
            'media_url' => get_connector_media_url()
        ));

    } catch (Exception $e) {
        send_error('Connection failed: ' . $e->getMessage(), 500);
    }
}

/**
 * Simple debug test - returns basic JSON without loading Magento
 */
function test_debug() {
    // Return basic system info without loading Magento
    $info = array(
        'success' => true,
        'message' => 'Debug test successful',
        'php_version' => phpversion(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
        'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? '',
        'request_time' => date('Y-m-d H:i:s'),
        'connector_file' => __FILE__,
        'magento_root' => dirname(__FILE__),
        'mage_php_exists' => file_exists(dirname(__FILE__) . '/app/Mage.php'),
        'config_file_exists' => file_exists(dirname(__FILE__) . '/connector-config.php'),
        'error_log_writable' => is_writable(dirname(__FILE__) . '/var/log/'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'extensions' => array(
            'curl' => extension_loaded('curl'),
            'json' => extension_loaded('json'),
            'mbstring' => extension_loaded('mbstring')
        )
    );

    send_response($info);
}

// ============================================================================
// ROUTER - Handle API requests
// ============================================================================

// Only process API requests if this is not the generate_key setup
if (!isset($_GET['generate_key'])) {
    // Get request method and endpoint
    $method = $_SERVER['REQUEST_METHOD'];
    $endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';

    // Set CORS headers (if needed)
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: X-Magento-Connector-Key, Content-Type');

    // Handle OPTIONS request for CORS
    if ($method == 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // Special case: ping endpoint doesn't require authentication
    if ($endpoint === 'ping') {
        send_response(array(
            'success' => true,
            'message' => 'Connector is accessible',
            'timestamp' => time(),
            'date' => date('Y-m-d H:i:s')
        ));
    }

    // Authenticate all other requests
    authenticate_request();

    // Get request method and endpoint (re-fetch after auth)
    $method = $_SERVER['REQUEST_METHOD'];
    $endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';

    // Handle OPTIONS request for CORS
    if ($method == 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // Route the request
    try {
        switch ($endpoint) {
            case 'test':
                test_connection();
                break;

            case 'test_debug':
                // Skip Magento loading, just test basic connectivity
                test_debug();
                break;

            case 'products':
                if ($method == 'GET') {
                    $limit = isset($_GET['limit']) ? max(1, min(1000, intval($_GET['limit']))) : 100;
                    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                    send_response(get_products($limit, $page));
                } else {
                    send_error('Method not allowed. Use GET for products.', 405);
                }
                break;

            case 'product':
                if ($method == 'GET') {
                    $sku = isset($_GET['sku']) ? $_GET['sku'] : '';
                    if (empty($sku)) {
                        send_error('Product SKU is required.', 400);
                    }
                    get_product($sku);
                } else {
                    send_error('Method not allowed. Use GET for product.', 405);
                }
                break;

            case 'products_count':
                if ($method == 'GET') {
                    get_products_count();
                } else {
                    send_error('Method not allowed. Use GET for count.', 405);
                }
                break;

            case 'categories':
                if ($method == 'GET') {
                    $parent_id = isset($_GET['parent_id']) ? intval($_GET['parent_id']) : null;
                    send_response(get_categories($parent_id));
                } else {
                    send_error('Method not allowed. Use GET for categories.', 405);
                }
                break;

            case 'category':
                if ($method == 'GET') {
                    $category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
                    if (empty($category_id)) {
                        send_error('Category ID is required.', 400);
                    }
                    get_category($category_id);
                } else {
                    send_error('Method not allowed. Use GET for category.', 405);
                }
                break;

            case 'categories_count':
                if ($method == 'GET') {
                    get_categories_count();
                } else {
                    send_error('Method not allowed. Use GET for count.', 405);
                }
                break;

            case 'orders':
                if ($method == 'GET') {
                    $limit = isset($_GET['limit']) ? max(1, min(1000, intval($_GET['limit']))) : 100;
                    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                    send_response(get_orders($limit, $page));
                } else {
                    send_error('Method not allowed. Use GET for orders.', 405);
                }
                break;

            case 'orders_count':
                if ($method == 'GET') {
                    get_orders_count();
                } else {
                    send_error('Method not allowed. Use GET for count.', 405);
                }
                break;

            case 'customers':
                if ($method == 'GET') {
                    $limit = isset($_GET['limit']) ? max(1, min(1000, intval($_GET['limit']))) : 100;
                    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                    send_response(get_customers($limit, $page));
                } else {
                    send_error('Method not allowed. Use GET for customers.', 405);
                }
                break;

            case 'customers_count':
                if ($method == 'GET') {
                    get_customers_count();
                } else {
                    send_error('Method not allowed. Use GET for count.', 405);
                }
                break;

            default:
                send_error('Invalid endpoint. Valid endpoints: ping, test, test_debug, products, product, categories, category, products_count, categories_count, orders, orders_count, customers, customers_count', 404);
        }
    } catch (Exception $e) {
        error_log("MAGENTO CONNECTOR Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        send_error('Server error: ' . $e->getMessage(), 500);
    } catch (Error $e) {
        // Catch PHP 7+ Errors (TypeError, ParseError, etc.)
        error_log("MAGENTO CONNECTOR Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        send_error('Server error: ' . $e->getMessage(), 500);
    }
}
