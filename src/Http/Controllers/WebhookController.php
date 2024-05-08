<?php

namespace EscolaLms\RevenueCatIntegration\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\RevenueCatIntegration\Http\Requests\ProcessWebhookRequest;
use EscolaLms\RevenueCatIntegration\Services\Contracts\WebhookServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WebhookController extends EscolaLmsBaseController
{
    private WebhookServiceContract $webhookService;

    public function __construct(WebhookServiceContract $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    public function process(ProcessWebhookRequest $request): JsonResponse
    {
        Log::info(get_class($this) . ' RevenueCat webhook processing', $request->toArray());

        $this->webhookService->process($request->toDto());

        return $this->sendSuccess('Webhook processed');
    }
}
