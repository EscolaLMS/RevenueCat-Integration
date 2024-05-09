<?php

use EscolaLms\RevenueCatIntegration\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api'], function () {
    Route::any('/webhooks/revenuecat', [WebhookController::class, 'process']);
});
