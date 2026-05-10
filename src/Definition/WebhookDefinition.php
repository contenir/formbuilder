<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Definition;

/**
 * Webhook fired by the WebhookRegistrar after a successful submission.
 *
 * Each webhook posts a JSON payload to {@see $url} containing the form,
 * entry, and submitted values. When {@see $secret} is set, the
 * registrar adds an `X-Contenir-Signature: sha256=<hex>` header so the
 * receiving system can verify authenticity.
 *
 * @phpstan-type HeaderMap array<string, string>
 */
final class WebhookDefinition
{
    /** @param HeaderMap $headers */
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $url,
        public readonly string $method = 'POST',
        public readonly ?string $secret = null,
        public readonly array $headers = [],
        public readonly bool $enabled = true,
        public readonly int $sort = 0,
    ) {
    }
}
