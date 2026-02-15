<?php

namespace App\Providers;

use App\Events\FxOfferMatched;
use App\Events\SuspiciousActivityDetected;
use App\Events\TransactionExecuted;
use App\Listeners\HandleSuspiciousActivity;
use App\Listeners\NotifyFxOfferMatch;
use App\Listeners\RecordTransactionFee;
use App\Listeners\SendTransactionNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
        $this->registerEvents();
    }

    /**
     * Register domain events and listeners.
     */
    protected function registerEvents(): void
    {
        Event::listen(TransactionExecuted::class, SendTransactionNotification::class);
        Event::listen(TransactionExecuted::class, RecordTransactionFee::class);
        Event::listen(SuspiciousActivityDetected::class, HandleSuspiciousActivity::class);
        Event::listen(FxOfferMatched::class, NotifyFxOfferMatch::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
