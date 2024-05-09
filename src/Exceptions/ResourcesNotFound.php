<?php

namespace EscolaLms\RevenueCatIntegration\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ResourcesNotFound extends Exception
{
    public function __construct(string $message = null)
    {
        parent::__construct($message ?? __('Resources not found.'));
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

