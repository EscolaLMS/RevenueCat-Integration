<?php

namespace EscolaLms\RevenueCatIntegration\Services\Contracts;

use EscolaLms\RevenueCatIntegration\Dtos\ProcessWebhookDto;

interface WebhookServiceContract
{
    public function process(ProcessWebhookDto $dto): void;
}
