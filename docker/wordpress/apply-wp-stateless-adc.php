<?php
/**
 * Patch WP-Stateless for Cloud Run Application Default Credentials (no JSON key).
 *
 * Patches:
 * 1) GS_Client (legacy Google\Client uploads / connection checks)
 * 2) Bootstrap::init_gs_client (StorageClient + StreamWrapper used in stateless mode)
 *
 * Usage:
 *   php apply-wp-stateless-adc.php
 *   php apply-wp-stateless-adc.php /path/to/class-gs-client.php [/path/to/class-bootstrap.php]
 */

$gsClientPath = $argv[1] ?? '/var/www/html/wordpress/wp-content/plugins/wp-stateless/lib/classes/class-gs-client.php';
$bootstrapPath = $argv[2] ?? dirname($gsClientPath).'/class-bootstrap.php';

$ok = 0;
$ok += patch_gs_client($gsClientPath) ? 1 : 0;
$ok += patch_bootstrap_storage_client($bootstrapPath) ? 1 : 0;

exit($ok > 0 ? 0 : 1);

function patch_gs_client(string $path): bool
{
    if (! is_readable($path)) {
        fwrite(STDERR, "WARN: WP-Stateless GS_Client not found at {$path}\n");

        return false;
    }

    $text = file_get_contents($path);
    if ($text === false) {
        fwrite(STDERR, "ERROR: cannot read {$path}\n");

        return false;
    }

    if (str_contains($text, 'WORDPRESS_GCS_USE_ADC') && str_contains($text, 'useApplicationDefaultCredentials')) {
        echo "WP-Stateless GS_Client ADC patch already applied\n";

        return true;
    }

    $oldAuth = <<<'PHP'
        } else {
          // May be delete warning transient if it was set
          $this->_deleteWarning();
          $this->client->setAuthConfig($this->key_json);
        }
PHP;

    $newAuth = <<<'PHP'
        } else {
          // May be delete warning transient if it was set
          $this->_deleteWarning();
          $use_adc = (getenv('WORDPRESS_GCS_USE_ADC') === '1' || getenv('WORDPRESS_GCS_USE_ADC') === 'true')
            || (is_array($this->key_json) && (($this->key_json['private_key'] ?? '') === 'USE_ADC'));
          if ($use_adc) {
            $this->client->useApplicationDefaultCredentials();
          } else {
            $this->client->setAuthConfig($this->key_json);
          }
        }
PHP;

    $oldValidate = <<<'PHP'
            if (!$json || !property_exists($json, 'private_key')) {
              throw new Exception(__('<b>Service Account JSON</b> is invalid.'));
            }
PHP;

    $newValidate = <<<'PHP'
            $use_adc = (getenv('WORDPRESS_GCS_USE_ADC') === '1' || getenv('WORDPRESS_GCS_USE_ADC') === 'true')
              || (is_object($json) && isset($json->private_key) && $json->private_key === 'USE_ADC');
            if (!$use_adc && (!$json || !property_exists($json, 'private_key'))) {
              throw new Exception(__('<b>Service Account JSON</b> is invalid.'));
            }
PHP;

    if (! str_contains($text, $oldAuth) || ! str_contains($text, $oldValidate)) {
        fwrite(STDERR, "ERROR: GS_Client auth blocks not found in {$path}\n");

        return false;
    }

    $text = str_replace($oldAuth, $newAuth, $text);
    $text = str_replace($oldValidate, $newValidate, $text);

    if (file_put_contents($path, $text) === false) {
        fwrite(STDERR, "ERROR: cannot write {$path}\n");

        return false;
    }

    echo "Applied WP-Stateless GS_Client ADC patch\n";

    return true;
}

function patch_bootstrap_storage_client(string $path): bool
{
    if (! is_readable($path)) {
        fwrite(STDERR, "WARN: WP-Stateless Bootstrap not found at {$path}\n");

        return false;
    }

    $text = file_get_contents($path);
    if ($text === false) {
        fwrite(STDERR, "ERROR: cannot read {$path}\n");

        return false;
    }

    if (str_contains($text, 'WORDPRESS_GCS_USE_ADC') && str_contains($text, "ce_wp_stateless_storage_client_config")) {
        echo "WP-Stateless StorageClient ADC patch already applied\n";

        return true;
    }

    $old = <<<'PHP'
        $json_key = json_decode($this->settings->get('sm.key_json'), true);

        if (!empty($json_key)) {
          return new StorageClient(
            [
              'keyFile' => $json_key,
              'httpHandler' => function ($request, $options) use ($httpHandler) {
                $xGoogApiClientHeader = $request->getHeaderLine('x-goog-api-client');
                $request = $request->withHeader('x-goog-api-client', $xGoogApiClientHeader);

                return call_user_func_array($httpHandler, [$request, $options]);
              },
              'authHttpHandler' => HttpHandlerFactory::build(),
            ]
          );
        }
PHP;

    $new = <<<'PHP'
        $json_key = json_decode($this->settings->get('sm.key_json'), true);

        if (!empty($json_key)) {
          // Cloud Run: org policy blocks SA JSON keys; use Application Default Credentials.
          $use_adc = (getenv('WORDPRESS_GCS_USE_ADC') === '1' || getenv('WORDPRESS_GCS_USE_ADC') === 'true')
            || (is_array($json_key) && (($json_key['private_key'] ?? '') === 'USE_ADC'));

          $ce_wp_stateless_storage_client_config = [
            'httpHandler' => function ($request, $options) use ($httpHandler) {
              $xGoogApiClientHeader = $request->getHeaderLine('x-goog-api-client');
              $request = $request->withHeader('x-goog-api-client', $xGoogApiClientHeader);

              return call_user_func_array($httpHandler, [$request, $options]);
            },
            'authHttpHandler' => HttpHandlerFactory::build(),
          ];

          if (! $use_adc) {
            $ce_wp_stateless_storage_client_config['keyFile'] = $json_key;
          }

          $project_id = getenv('GOOGLE_CLOUD_PROJECT_ID') ?: '';
          if ($project_id !== '') {
            $ce_wp_stateless_storage_client_config['projectId'] = $project_id;
          }

          return new StorageClient($ce_wp_stateless_storage_client_config);
        }
PHP;

    if (! str_contains($text, $old)) {
        fwrite(STDERR, "ERROR: Bootstrap init_gs_client block not found in {$path}\n");

        return false;
    }

    $text = str_replace($old, $new, $text);

    if (file_put_contents($path, $text) === false) {
        fwrite(STDERR, "ERROR: cannot write {$path}\n");

        return false;
    }

    echo "Applied WP-Stateless StorageClient ADC patch\n";

    return true;
}
