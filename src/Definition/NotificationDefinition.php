<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Definition;

/**
 * Email notification fired by the EmailNotificationRegistrar after submission.
 *
 * Subject and body templates are passed through
 * {@see \Contenir\FormBuilder\Service\TokenReplacer} before sending.
 *
 * @phpstan-type NotificationConditions array<string, mixed>|null
 */
final class NotificationDefinition
{
    /** @param NotificationConditions $conditions */
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $trigger = 'submit',
        public readonly string $toAddress = '',
        public readonly ?string $fromAddress = null,
        public readonly ?string $replyTo = null,
        public readonly string $subject = '',
        public readonly ?string $bodyTemplate = null,
        public readonly ?array $conditions = null,
        public readonly bool $enabled = true,
        public readonly int $sort = 0,
    ) {
    }
}
