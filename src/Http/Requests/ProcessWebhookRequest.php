<?php

namespace EscolaLms\RevenueCatIntegration\Http\Requests;

use EscolaLms\RevenueCatIntegration\Dtos\ProcessWebhookDto;
use Illuminate\Foundation\Http\FormRequest;

class ProcessWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (!config('escolalms_revenuecat_integration.webhooks.auth.enabled')) {
            return true;
        }

        return $this->header('Authorization') != null && $this->header('Authorization') === config('escolalms_revenuecat_integration.webhooks.auth.key');
    }

    public function rules(): array
    {
        return [];
    }

    public function toDto(): ProcessWebhookDto
    {
        return ProcessWebhookDto::instantiateFromRequest($this);
    }
}
