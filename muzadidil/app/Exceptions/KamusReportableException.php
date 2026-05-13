<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Base class for every domain exception in the Zasha codebase.
 *
 * Provides:
 *  - Static factory pattern (children expose ::someReason() factories).
 *  - Structured payload for the kamus.zasha.online aggregator.
 *  - Consistent JSON response shape (Indonesian user message).
 */
abstract class KamusReportableException extends Exception
{
    protected string $domain = 'app';

    protected string $reasonCode = 'unknown';

    protected int $httpStatus = 422;

    /** @var array<string, mixed> */
    protected array $context = [];

    /** @var array<string, mixed> */
    protected array $publicData = [];

    public function withContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    public function withPublicData(array $data): static
    {
        $this->publicData = array_merge($this->publicData, $data);

        return $this;
    }

    public function domain(): string
    {
        return $this->domain;
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * Structured payload pushed to kamus.zasha.online for cross-service correlation.
     *
     * @return array<string, mixed>
     */
    public function toKamusPayload(): array
    {
        return [
            'domain' => $this->domain,
            'reason_code' => $this->reasonCode,
            'message' => $this->getMessage(),
            'http_status' => $this->httpStatus,
            'context' => $this->context,
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'domain' => $this->domain,
                'code' => $this->reasonCode,
                'message' => $this->getMessage(),
                'data' => $this->publicData,
            ],
        ], $this->httpStatus);
    }

    public function report(): bool
    {
        // Log structured payload locally; Phase 6 wires this to kamus.zasha.online.
        logger()->channel(config('logging.default'))->warning(
            sprintf('[%s] %s', $this->domain, $this->reasonCode),
            $this->toKamusPayload(),
        );

        return true;
    }

    protected static function make(
        string $reasonCode,
        string $message,
        int $httpStatus = 422,
        ?Throwable $previous = null,
    ): static {
        $instance = new static($message, 0, $previous);
        $instance->reasonCode = $reasonCode;
        $instance->httpStatus = $httpStatus;

        return $instance;
    }
}
