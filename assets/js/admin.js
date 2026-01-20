/**
 * Magento to WordPress Migrator - Admin JavaScript
 *
 * @package Magento_WordPress_Migrator
 */

(function($) {
    'use strict';

    // Default fallback strings (used if wp_localize_script fails)
    var defaultStrings = {
        migrating: 'Migrating...',
        completed: 'Completed',
        error: 'Error',
        confirm_cancel: 'Are you sure you want to cancel the migration?',
        connection_failed: 'Connection failed. Please check your credentials.',
        connection_success: 'Connection successful!'
    };

    // Ensure mwmAdmin object exists with required properties
    if (typeof mwmAdmin === 'undefined') {
        window.mwmAdmin = {
            ajaxurl: ajaxurl,
            nonce: '',
            strings: defaultStrings
        };
    }

    // Ensure strings object exists
    if (!mwmAdmin.strings) {
        mwmAdmin.strings = defaultStrings;
    }

    // MWM Admin object (renamed to avoid conflict)
    var mwmApp = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.checkConnection();
            this.loadStats();
            // Don't start polling here - it will be started when migration begins
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // Test connection button (API mode)
            $(document).on('click', '#mwm-test-connection', function() {
                self.testConnection();
            });

            // Test connector button (Connector mode)
            $(document).on('click', '#mwm-test-connector', function() {
                self.testConnector();
            });

            // Start migration buttons
            $(document).on('click', '.mwm-start-migration', function(e) {
                e.preventDefault();
                var type = $(this).data('type');
                self.startMigration(type);
            });

            // Cancel migration button
            $(document).on('click', '#mwm-cancel-migration', function(e) {
                e.preventDefault();
                if (confirm(mwmAdmin.strings.confirm_cancel || 'Are you sure you want to cancel the migration?')) {
                    self.cancelMigration();
                }
            });

            // Close modal button
            $(document).on('click', '#mwm-close-modal', function(e) {
                e.preventDefault();
                self.closeModal();
            });
        },

        /**
         * Test API connection
         */
        testConnection: function() {
            var self = this;
            var $button = $('#mwm-test-connection');
            var $result = $('#mwm-connection-result');

            // Get API form values
            var data = {
                action: 'mwm_test_connection',
                nonce: mwmAdmin.nonce || '',
                store_url: $('input[name="mwm_settings[store_url]"]').val(),
                api_version: $('select[name="mwm_settings[api_version]"]').val(),
                consumer_key: $('input[name="mwm_settings[consumer_key]"]').val(),
                consumer_secret: $('input[name="mwm_settings[consumer_secret]"]').val(),
                access_token: $('input[name="mwm_settings[access_token]"]').val(),
                access_token_secret: $('input[name="mwm_settings[access_token_secret]"]').val()
            };

            // Helper function to get string with fallback
            var getString = function(key) {
                return mwmAdmin.strings && mwmAdmin.strings[key] ? mwmAdmin.strings[key] : defaultStrings[key];
            };

            // Show loading state
            $button.prop('disabled', true);
            $result.html('<span class="mwm-loading"></span>');

            $.ajax({
                url: mwmAdmin.ajaxurl || ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    // Check if response exists and has expected structure
                    if (!response) {
                        $result.html('<span class="dashicons dashicons-no"></span> ' + getString('connection_failed'));
                        $result.addClass('mwm-status-error').removeClass('mwm-status-connected');
                        return;
                    }

                    if (response.success) {
                        // Success case
                        var message = (response.data && response.data.message) ? response.data.message : getString('connection_success');
                        $result.html('<span class="dashicons dashicons-yes"></span> ' + message);
                        $result.addClass('mwm-status-connected').removeClass('mwm-status-error');
                    } else {
                        // Error case from server
                        var message = (response.data && response.data.message) ? response.data.message : getString('connection_failed');
                        $result.html('<span class="dashicons dashicons-no"></span> ' + message);
                        $result.addClass('mwm-status-error').removeClass('mwm-status-connected');
                    }
                },
                error: function(xhr, status, error) {
                    // AJAX request failed (network error, server error, etc.)
                    var errorMsg = getString('connection_failed');
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMsg = xhr.responseJSON.data.message;
                    } else if (xhr.responseText) {
                        try {
                            var errorData = JSON.parse(xhr.responseText);
                            if (errorData.data && errorData.data.message) {
                                errorMsg = errorData.data.message;
                            }
                        } catch (e) {
                            // Use default error message
                        }
                    }
                    $result.html('<span class="dashicons dashicons-no"></span> ' + errorMsg);
                    $result.addClass('mwm-status-error').removeClass('mwm-status-connected');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Test connector connection
         */
        testConnector: function() {
            var self = this;
            var $button = $('#mwm-test-connector');
            var $result = $('#mwm-connector-result');

            // Get connector form values
            var data = {
                action: 'mwm_test_connector',
                nonce: mwmAdmin.nonce || '',
                connector_url: $('input[name="mwm_settings[connector_url]"]').val(),
                connector_api_key: $('input[name="mwm_settings[connector_api_key]"]').val()
            };

            // Helper function to get string with fallback
            var getString = function(key) {
                var connectorStrings = {
                    connector_testing: 'Testing connection...',
                    connector_success: 'Connection successful!',
                    connector_failed: 'Connection failed'
                };
                return connectorStrings[key] || key;
            };

            // Show loading state
            $button.prop('disabled', true);
            $result.html('<span class="mwm-loading"></span>');

            $.ajax({
                url: mwmAdmin.ajaxurl || ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    // Check if response exists and has expected structure
                    if (!response) {
                        $result.html('<span class="dashicons dashicons-no"></span> ' + getString('connector_failed'));
                        $result.addClass('mwm-status-error').removeClass('mwm-status-connected');
                        return;
                    }

                    if (response.success) {
                        // Success case
                        var message = (response.data && response.data.message) ? response.data.message : getString('connector_success');
                        $result.html('<span class="dashicons dashicons-yes"></span> ' + message);
                        $result.addClass('mwm-status-connected').removeClass('mwm-status-error');
                    } else {
                        // Error case from server
                        var message = (response.data && response.data.message) ? response.data.message : getString('connector_failed');
                        $result.html('<span class="dashicons dashicons-no"></span> ' + message);
                        $result.addClass('mwm-status-error').removeClass('mwm-status-connected');
                    }
                },
                error: function(xhr, status, error) {
                    // AJAX request failed (network error, server error, etc.)
                    var errorMsg = getString('connector_failed');
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMsg = xhr.responseJSON.data.message;
                    } else if (xhr.responseText) {
                        try {
                            var errorData = JSON.parse(xhr.responseText);
                            if (errorData.data && errorData.data.message) {
                                errorMsg = errorData.data.message;
                            }
                        } catch (e) {
                            // Use default error message
                        }
                    }
                    $result.html('<span class="dashicons dashicons-no"></span> ' + errorMsg);
                    $result.addClass('mwm-status-error').removeClass('mwm-status-connected');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Check connection status on dashboard
         */
        checkConnection: function() {
            var self = this;

            $.ajax({
                url: mwmAdmin.ajaxurl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'mwm_get_stats',
                    nonce: mwmAdmin.nonce || ''
                },
                success: function(response) {
                    if (response && response.success) {
                        self.updateConnectionStatus(true);
                    } else {
                        self.updateConnectionStatus(false);
                    }
                },
                error: function() {
                    self.updateConnectionStatus(false);
                }
            });
        },

        /**
         * Update connection status display
         */
        updateConnectionStatus: function(connected) {
            var $status = $('#mwm-connection-status');
            var successMsg = mwmAdmin.strings && mwmAdmin.strings.connection_success
                ? mwmAdmin.strings.connection_success
                : defaultStrings.connection_success;

            if (connected) {
                $status.html('<p class="mwm-status-connected"><span class="dashicons dashicons-yes-alt"></span> ' + successMsg + '</p>');
            } else {
                $status.html('<p class="mwm-status-error"><span class="dashicons dashicons-dismiss"></span> Not connected to Magento API</p>');
            }
        },

        /**
         * Load statistics
         */
        loadStats: function() {
            var self = this;

            $.ajax({
                url: mwmAdmin.ajaxurl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'mwm_get_stats',
                    nonce: mwmAdmin.nonce || ''
                },
                success: function(response) {
                    if (response && response.success && response.data) {
                        self.updateStatsDisplay(response.data);
                    }
                }
            });
        },

        /**
         * Update statistics display
         */
        updateStatsDisplay: function(stats) {
            var $stats = $('#mwm-stats');
            var failedMsg = mwmAdmin.strings && mwmAdmin.strings.connection_failed
                ? mwmAdmin.strings.connection_failed
                : defaultStrings.connection_failed;

            if (!stats) {
                $stats.html('<p>' + failedMsg + '</p>');
                return;
            }

            var html = '<div class="mwm-stats-display">';

            // Products
            html += '<div class="mwm-stat-item">';
            html += '<strong>' + (stats.products?.migrated || 0) + ' / ' + (stats.products?.total || 0) + '</strong>';
            html += '<span>Products</span>';
            html += '</div>';

            // Categories
            html += '<div class="mwm-stat-item">';
            html += '<strong>' + (stats.categories?.migrated || 0) + ' / ' + (stats.categories?.total || 0) + '</strong>';
            html += '<span>Categories</span>';
            html += '</div>';

            // Customers
            html += '<div class="mwm-stat-item">';
            html += '<strong>' + (stats.customers?.migrated || 0) + ' / ' + (stats.customers?.total || 0) + '</strong>';
            html += '<span>Customers</span>';
            html += '</div>';

            // Orders
            html += '<div class="mwm-stat-item">';
            html += '<strong>' + (stats.orders?.migrated || 0) + ' / ' + (stats.orders?.total || 0) + '</strong>';
            html += '<span>Orders</span>';
            html += '</div>';

            html += '</div>';

            $stats.html(html);
        },

        /**
         * Start migration
         */
        startMigration: function(type) {
            var self = this;

            // Show progress modal immediately
            self.showProgressModal();

            // Reset modal to initial state
            $('#mwm-startup-error').hide();
            $('#mwm-progress-bar-container').hide();
            $('#mwm-progress-details').hide();
            $('#mwm-progress-stats').hide();
            $('#mwm-cancel-migration').hide();
            $('#mwm-close-modal').prop('disabled', false);
            $('#mwm-type').text(ucfirst(type));

            // Get selected page for products
            var selectedPage = null;
            var pageLabel = '';
            if (type === 'products') {
                selectedPage = $('#mwm-product-page').val() || 'all';
                if (selectedPage === 'all') {
                    pageLabel = ' (All Pages)';
                } else {
                    pageLabel = ' (Page ' + selectedPage + ')';
                }
            }

            $('#mwm-current').text('Initializing...' + pageLabel);

            $.ajax({
                url: mwmAdmin.ajaxurl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'mwm_start_migration',
                    nonce: mwmAdmin.nonce || '',
                    migration_type: type,
                    page: selectedPage
                },
                success: function(response) {
                    if (response && response.success) {
                        // Success - hide error area and show progress elements
                        $('#mwm-startup-error').hide();
                        $('#mwm-progress-bar-container').show();
                        $('#mwm-progress-details').show();
                        $('#mwm-progress-stats').show();
                        $('#mwm-cancel-migration').show();
                        $('#mwm-close-modal').prop('disabled', true);

                        // Start polling for progress
                        self.pollProgress();
                    } else {
                        // Error occurred - show error in progress modal
                        var message = 'Failed to start migration';
                        if (response && response.data && response.data.message) {
                            message = response.data.message;
                        }

                        self.showStartupError(message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('MWM: Migration start error:', xhr, status, error);

                    // Log full response for debugging
                    console.error('MWM: Full response:', xhr.responseText);
                    console.error('MWM: Status code:', xhr.status);
                    console.error('MWM: Status text:', xhr.statusText);

                    var errorMsg = 'Failed to start migration. Please try again.';

                    // Try to extract error details from response
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMsg = xhr.responseJSON.data.message;
                    } else if (xhr.responseText) {
                        try {
                            var errorData = JSON.parse(xhr.responseText);
                            if (errorData.data && errorData.data.message) {
                                errorMsg = errorData.data.message;
                            }
                        } catch (e) {
                            // Use default error
                            errorMsg += ' Server returned: ' + xhr.statusText + ' (' + xhr.status + ')';
                        }
                    }

                    self.showStartupError(errorMsg);
                }
            });
        },

        /**
         * Show startup error in progress modal
         */
        showStartupError: function(message) {
            // Format message with line breaks
            var formattedMessage = message.replace(/\n/g, '<br>');

            $('#mwm-startup-error-message').html(formattedMessage);
            $('#mwm-startup-error').show();
            $('#mwm-cancel-migration').hide();
            $('#mwm-close-modal').prop('disabled', false);

            // Stop any polling if it was started
            if (self.pollTimer) {
                clearInterval(self.pollTimer);
                self.pollTimer = null;
            }
        },

        /**
         * Show error modal with detailed message
         */
        showErrorModal: function(message) {
            // Create modal if it doesn't exist with proper structure
            if ($('#mwm-error-modal').length === 0) {
                var modalHTML = '<div id="mwm-error-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;width:100vw;height:100vh;z-index:999999;">' +
                    '<div class="mwm-modal-overlay">' +
                        '<div class="mwm-modal-content" style="max-width:500px;">' +
                            '<h2 style="color:#d63638;">Migration Error</h2>' +
                            '<div id="mwm-error-message" style="white-space:pre-wrap;background:#f6f7f7;padding:15px;border-radius:4px;border-left:4px solid #d63638;margin:20px 0;"></div>' +
                            '<button type="button" class="button button-primary" id="mwm-close-error">Close</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';

                // Append to body properly
                $('body').append(modalHTML);
            }

            $('#mwm-error-message').text(message);
            $('#mwm-error-modal').show();

            $('#mwm-close-error').off('click').on('click', function() {
                $('#mwm-error-modal').hide();
            });
        },

        /**
         * Cancel migration
         */
        cancelMigration: function() {
            var self = this;

            $.ajax({
                url: mwmAdmin.ajaxurl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'mwm_cancel_migration',
                    nonce: mwmAdmin.nonce || ''
                },
                success: function(response) {
                    if (response && response.success) {
                        self.closeModal();
                    }
                }
            });
        },

        /**
         * Poll migration progress
         */
        pollProgress: function() {
            var self = this;
            var pollInterval = null;
            var consecutiveErrors = 0;
            var maxConsecutiveErrors = 3;

            // Clear any existing intervals
            if (self.pollTimer) {
                clearInterval(self.pollTimer);
            }

            // Start polling
            self.pollTimer = setInterval(function() {
                $.ajax({
                    url: mwmAdmin.ajaxurl || ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mwm_get_progress',
                        nonce: mwmAdmin.nonce || ''
                    },
                    timeout: 10000, // 10 second timeout
                    success: function(response) {
                        // Reset error counter on success
                        consecutiveErrors = 0;

                        if (response && response.success && response.data) {
                            self.updateProgress(response.data);

                            // Stop polling if migration is complete, cancelled, or failed
                            var status = response.data.status;
                            if (status === 'completed' || status === 'cancelled' || status === 'failed') {
                                clearInterval(self.pollTimer);
                                self.pollTimer = null;
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        // Log error for debugging
                        console.warn('MWM: Poll error - ' + status + ': ' + error);
                        consecutiveErrors++;

                        // Stop polling after too many consecutive errors
                        if (consecutiveErrors >= maxConsecutiveErrors) {
                            console.error('MWM: Stopping polling after ' + maxConsecutiveErrors + ' consecutive errors');
                            clearInterval(self.pollTimer);
                            self.pollTimer = null;
                        }
                    }
                });
            }, 2000); // Poll every 2 seconds

            // Store interval reference for cleanup
            self.pollInterval = pollInterval;
        },

        /**
         * Update progress display
         */
        updateProgress: function(data) {
            // Validate data object
            if (!data || typeof data !== 'object') {
                console.warn('MWM: Invalid progress data received');
                return;
            }

            try {
                // Update progress bar - use backend percentage if available, otherwise calculate
                var percentage = 0;
                if (data.percentage) {
                    percentage = Math.round(data.percentage);
                } else if (data.total > 0) {
                    percentage = Math.round((data.processed / data.total) * 100);
                }

                $('#mwm-progress-fill').css('width', percentage + '%');
                $('#mwm-progress-text').text(percentage + '%');

                // Update stats
                $('#mwm-total').text(data.total || 0);
                $('#mwm-processed').text(data.processed || 0);
                $('#mwm-successful').text(data.successful || 0);
                $('#mwm-failed').text(data.failed || 0);

                // Update current item with truncated text if too long
                var currentItem = data.current_item || '...';
                if (currentItem.length > 50) {
                    currentItem = currentItem.substring(0, 50) + '...';
                }
                $('#mwm-current').text(currentItem);

                // Update type
                $('#mwm-type').text(ucfirst(data.type || ''));

                // Update time remaining if available
                var $timeRemaining = $('#mwm-time-remaining');
                if ($timeRemaining.length > 0) {
                    if (data.time_remaining && data.status === 'processing') {
                        $timeRemaining.text(data.time_remaining).show();
                    } else {
                        $timeRemaining.hide();
                    }
                }

                // Update progress details with percentage breakdown
                var $details = $('#mwm-progress-details');
                if ($details.length > 0 && data.total > 0) {
                    var successRate = data.processed > 0 ? Math.round((data.successful / data.processed) * 100) : 0;
                    $details.html(
                        '<div class="mwm-progress-detail-item">' +
                        '<span class="detail-label">' + (percentage + '% Complete') + '</span> ' +
                        '<span class="detail-value">' + (data.processed + ' of ' + data.total) + '</span>' +
                        '</div>' +
                        '<div class="mwm-progress-detail-item">' +
                        '<span class="detail-label">Success Rate:</span> ' +
                        '<span class="detail-value">' + (successRate + '%') + '</span>' +
                        '</div>'
                    );
                }

                // Update errors if any
                if (data.errors && data.errors.length > 0) {
                    $('#mwm-progress-errors').show();
                    var $errorList = $('#mwm-error-list');
                    $errorList.empty();
                    // Show only last 10 errors to avoid overwhelming the UI
                    var recentErrors = data.errors.slice(-10);
                    recentErrors.forEach(function(error) {
                        $errorList.append('<li>' + esc_html(error.item) + ': ' + esc_html(error.message) + '</li>');
                    });

                    // Add error count if more than 10
                    if (data.errors.length > 10) {
                        $errorList.prepend('<li class="mwm-error-summary">... and ' + (data.errors.length - 10) + ' more errors</li>');
                    }
                }

                // Check if completed
                if (data.status === 'completed' || data.status === 'cancelled' || data.status === 'failed') {
                    $('#mwm-close-modal').prop('disabled', false);
                    $('#mwm-cancel-migration').hide();
                    $('#mwm-progress-fill').css('animation', 'none');

                        if (data.status === 'completed') {
                        var completedMsg = mwmAdmin.strings && mwmAdmin.strings.completed
                            ? mwmAdmin.strings.completed
                            : defaultStrings.completed;
                        $('#mwm-progress-text').text(completedMsg + ' - 100%');

                        // Show final summary
                        if ($details.length > 0) {
                            $details.html(
                                '<div class="mwm-progress-final-summary">' +
                                '<strong>Migration Complete!</strong><br>' +
                                'Total: ' + data.total + ' | ' +
                                'Successful: ' + data.successful + ' | ' +
                                'Failed: ' + data.failed +
                                '</div>'
                            );
                        }
                    } else if (data.status === 'failed') {
                        $('#mwm-progress-text').text('Failed');
                        if ($details.length > 0) {
                            $details.html('<div class="mwm-progress-failed">Migration failed. See errors below.</div>');
                        }
                    } else if (data.status === 'cancelled') {
                        $('#mwm-progress-text').text('Cancelled');
                        if ($details.length > 0) {
                            $details.html('<div class="mwm-progress-cancelled">Migration was cancelled.</div>');
                        }
                    }
                }

                // Update dashboard progress display
                var $dashboardProgress = $('#mwm-progress-display');
                if (data.status === 'processing') {
                    var migratingMsg = mwmAdmin.strings && mwmAdmin.strings.migrating
                        ? mwmAdmin.strings.migrating
                        : defaultStrings.migrating;
                    $dashboardProgress.html('<p>' + migratingMsg + ' ' + ucfirst(data.type) + ' (' + percentage + '%)</p>');
                }
            } catch (e) {
                console.error('MWM: Error updating progress display:', e);
            }
        },

        /**
         * Show progress modal
         */
        showProgressModal: function() {
            $('#mwm-progress-modal').show();
            $('#mwm-close-modal').prop('disabled', true);
            $('#mwm-cancel-migration').show();
            $('#mwm-progress-errors').hide();
            $('#mwm-startup-error').hide();
            // Note: Polling is now started manually after successful migration start
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('#mwm-progress-modal').hide();

            // Clear polling interval
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }

            // Reload page to show updated stats
            location.reload();
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        mwmApp.init();
    });

    // Helper functions
    function ucfirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function esc_html(str) {
        return $('<div/>').text(str).html();
    }

})(jQuery);
