<?php

namespace Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Client;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $publicKey = storage_path('oauth-public.key');
        if (file_exists($publicKey)) {
            @chmod($publicKey, 0600);
        }

        $privateKey = storage_path('oauth-private.key');
        if (file_exists($privateKey)) {
            @chmod($privateKey, 0600);
        }

        if (!Schema::hasTable('oauth_clients')) {
            Artisan::call('migrate', [
                '--path' => 'vendor/laravel/passport/database/migrations',
                '--realpath' => true,
            ]);
        }

        if (Schema::hasTable('oauth_clients') && !Client::where('personal_access_client', 1)->exists()) {
            Artisan::call('passport:install', ['--force' => true]);
        }
    }
}
