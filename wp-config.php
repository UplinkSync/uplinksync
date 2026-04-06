<?php
/**
 * WordPress configuration — credentials loaded from environment.
 *
 * In production, env vars are injected by the deployment pipeline
 * (sourced from HashiCorp Vault via GitLab CI/CD).
 *
 * For local development export them in your shell or use a .env loader:
 *   export DB_NAME=your_local_db DB_USER=root DB_PASSWORD='' DB_HOST=localhost
 */

define('DB_NAME',     getenv('DB_NAME')     ?: 'PLACEHOLDER');
define('DB_USER',     getenv('DB_USER')     ?: 'PLACEHOLDER');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'PLACEHOLDER');
define('DB_HOST',     getenv('DB_HOST')     ?: 'localhost');
define('DB_CHARSET',  'utf8mb4');
define('DB_COLLATE',  '');

define('AUTH_KEY',         getenv('WP_AUTH_KEY')         ?: 'PLACEHOLDER');
define('SECURE_AUTH_KEY',  getenv('WP_SECURE_AUTH_KEY')  ?: 'PLACEHOLDER');
define('LOGGED_IN_KEY',    getenv('WP_LOGGED_IN_KEY')    ?: 'PLACEHOLDER');
define('NONCE_KEY',        getenv('WP_NONCE_KEY')        ?: 'PLACEHOLDER');
define('AUTH_SALT',        getenv('WP_AUTH_SALT')        ?: 'PLACEHOLDER');
define('SECURE_AUTH_SALT', getenv('WP_SECURE_AUTH_SALT') ?: 'PLACEHOLDER');
define('LOGGED_IN_SALT',   getenv('WP_LOGGED_IN_SALT')   ?: 'PLACEHOLDER');
define('NONCE_SALT',       getenv('WP_NONCE_SALT')       ?: 'PLACEHOLDER');

$table_prefix = getenv('DB_PREFIX') ?: 'wp_';

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
require_once ABSPATH . 'wp-settings.php';
