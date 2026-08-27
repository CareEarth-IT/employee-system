<?php

namespace App\Providers;

use App\Support\DepartmentPortalConfigValidator;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;
use League\Flysystem\GoogleCloudStorage\PortableVisibilityHandler;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Storage::extend('gcs', function ($app, array $config) {
            $clientConfig = array_filter([
                'projectId' => $config['project_id'] ?? null,
                'keyFilePath' => $config['key_file_path'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');

            $adapter = new GoogleCloudStorageAdapter(
                (new StorageClient($clientConfig))->bucket($config['bucket']),
                $config['path_prefix'] ?? '',
                new PortableVisibilityHandler(),
                PortableVisibilityHandler::NO_PREDEFINED_VISIBILITY,
            );

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });

        if ($this->app->environment('production') && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        if ($this->app->environment('local')) {
            $validator = $this->app->make(DepartmentPortalConfigValidator::class);
            foreach ($validator->errors() as $error) {
                Log::warning('department_portals config: '.$error);
            }
        }

        if ($this->app->runningInConsole()) {
            return;
        }

        $request = request();
        $baseUrl = $request->getBaseUrl();

        if ($baseUrl !== '') {
            config(['session.path' => $baseUrl]);
        }
    }
}
