<?php
/**
 * Runs from wp-config.php BEFORE WordPress core (wp-settings.php) loads.
 * Add custom defines / early bootstrap logic here.
 * This file is shipped in the Docker image and is not overwritten at boot.
 */

// Cloud Run terminates TLS; tell WordPress the original request was HTTPS.
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}
if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
}

define('FORCE_SSL_ADMIN', true);

// WP-Stateless (GCS). Org policy blocks SA JSON key creation, so Cloud Run ADC is used.
$wpGcsBucket = getenv('WORDPRESS_GCS_BUCKET') ?: '';
$wpGcsMode = getenv('WORDPRESS_GCS_MODE') ?: 'stateless';
$wpGcsKeyJson = getenv('WORDPRESS_GCS_KEY_JSON') ?: '';
$wpGcsUseAdc = getenv('WORDPRESS_GCS_USE_ADC');
if ($wpGcsUseAdc === false || $wpGcsUseAdc === '') {
    $wpGcsUseAdc = $wpGcsKeyJson === '' ? '1' : '0';
}

if ($wpGcsBucket !== '') {
    define('WP_STATELESS_MEDIA_BUCKET', $wpGcsBucket);
    define('WP_STATELESS_MEDIA_MODE', $wpGcsMode);
    // Keep object prefix simple; year/month folders still work via StreamWrapper once ADC auth works.
    if (!defined('WP_STATELESS_MEDIA_ROOT_DIR')) {
        define('WP_STATELESS_MEDIA_ROOT_DIR', '');
    }
    // Uniform bucket-level access: do not set per-object ACLs.
    define('WP_STATELESS_SKIP_ACL_SET', true);
    define('WP_STATELESS_MEDIA_HIDE_SETUP_ASSISTANT', true);

    if ($wpGcsKeyJson !== '') {
        define('WP_STATELESS_MEDIA_JSON_KEY', $wpGcsKeyJson);
        putenv('WORDPRESS_GCS_USE_ADC=0');
        $_ENV['WORDPRESS_GCS_USE_ADC'] = '0';
    } else {
        // Placeholder so WP-Stateless accepts config; real auth uses ADC (see apply-wp-stateless-adc.sh).
        define('WP_STATELESS_MEDIA_JSON_KEY', json_encode([
            'type' => 'service_account',
            'project_id' => getenv('GOOGLE_CLOUD_PROJECT_ID') ?: 'ce-gr-employee-info-2606st',
            'private_key_id' => 'adc',
            'private_key' => 'USE_ADC',
            'client_email' => 'adc@developer.gserviceaccount.com',
            'client_id' => '0',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ], JSON_UNESCAPED_SLASHES));
        putenv('WORDPRESS_GCS_USE_ADC=1');
        $_ENV['WORDPRESS_GCS_USE_ADC'] = '1';
    }
}
