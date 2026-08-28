<?php

namespace App\Providers;

use App\Domain\Checks\Ports\DetectionEventRepository;
use App\Domain\Checks\Ports\ReferencePaymentRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentDetectionEventRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentReferencePaymentRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ReferencePaymentRepository::class, EloquentReferencePaymentRepository::class);
        $this->app->bind(DetectionEventRepository::class, EloquentDetectionEventRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
