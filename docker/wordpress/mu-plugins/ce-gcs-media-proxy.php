<?php
/**
 * Plugin Name: CE GCS Media Proxy
 * Description: Serves WP-Stateless GCS objects through the site origin (org policy blocks public bucket ACLs).
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', static function (): void {
    $object = isset($_GET['ce_gcs']) ? (string) $_GET['ce_gcs'] : '';
    if ($object === '') {
        return;
    }

    $object = ltrim(rawurldecode($object), '/');
    if ($object === ''
        || str_contains($object, '..')
        || str_contains($object, '\\')
        || str_contains($object, "\0")
    ) {
        status_header(400);
        exit('Invalid object');
    }

    wp_safe_redirect(ce_gcs_authenticated_media_url($object), 302);
    exit;
}, 0);

/**
 * Return the Laravel-authenticated media endpoint.
 */
function ce_gcs_authenticated_media_url(string $object): string
{
    $origin = rtrim((string) getenv('APP_URL'), '/');
    if ($origin === '') {
        $origin = preg_replace('#/wordpress/?$#', '', untrailingslashit(home_url('/'))) ?: '';
    }

    return $origin.'/wordpress-media?object='.rawurlencode(ltrim($object, '/'));
}

/**
 * @param  string  $url
 */
function ce_gcs_proxy_rewrite_url(string $url): string
{
    $bucket = defined('WP_STATELESS_MEDIA_BUCKET') ? (string) WP_STATELESS_MEDIA_BUCKET : (string) getenv('WORDPRESS_GCS_BUCKET');
    if ($bucket === '' || $url === '') {
        return $url;
    }

    $prefix = 'https://storage.googleapis.com/'.$bucket.'/';
    if (!str_starts_with($url, $prefix)) {
        return $url;
    }

    $object = substr($url, strlen($prefix));
    return ce_gcs_authenticated_media_url($object);
}

add_filter('wp_get_attachment_url', static function ($url) {
    return ce_gcs_proxy_rewrite_url((string) $url);
}, 100);

add_filter('wp_calculate_image_srcset', static function ($sources) {
    if (!is_array($sources)) {
        return $sources;
    }

    foreach ($sources as $width => $source) {
        if (!empty($source['url'])) {
            $sources[$width]['url'] = ce_gcs_proxy_rewrite_url((string) $source['url']);
        }
    }

    return $sources;
}, 100);

add_filter('the_content', static function ($content) {
    $bucket = defined('WP_STATELESS_MEDIA_BUCKET') ? (string) WP_STATELESS_MEDIA_BUCKET : (string) getenv('WORDPRESS_GCS_BUCKET');
    if ($bucket === '' || !is_string($content) || $content === '') {
        return $content;
    }

    $prefix = 'https://storage.googleapis.com/'.$bucket.'/';
    return preg_replace_callback(
        '#'.preg_quote($prefix, '#').'([^"\'\s]+)#',
        static function (array $matches): string {
            return ce_gcs_authenticated_media_url(rawurldecode($matches[1]));
        },
        $content
    ) ?? $content;
}, 100);
