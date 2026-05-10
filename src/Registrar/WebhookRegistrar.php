<?php

declare(strict_types=1);

namespace Contenir\FormBuilder\Registrar;

use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Definition\WebhookDefinition;
use Contenir\FormBuilder\Service\BuilderForm;
use Psr\Log\LoggerInterface;
use SplObserver;
use SplSubject;

use function array_values;
use function curl_close;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt_array;
use function curl_error;
use function hash_hmac;
use function is_array;
use function json_encode;
use function preg_match;
use function sprintf;
use function trim;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_SSL_VERIFYHOST;
use const CURLOPT_SSL_VERIFYPEER;
use const CURLOPT_TIMEOUT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Fires every enabled webhook configured on a form after a successful
 * submission, POSTing a JSON envelope of the form / entry / values to
 * each target URL.
 *
 * Spam-flagged submissions are skipped — the registrar is for legit
 * leads, not bot traffic.
 *
 * Failures are logged via the optional PSR-3 logger rather than
 * propagated. A webhook target that's down (or slow, or misconfigured)
 * must never block the user-facing redirect or roll back the persisted
 * entry.
 *
 * Payload shape:
 *
 * ```json
 * {
 *   "form":   { "id": 5, "slug": "contact", "title": "Contact" },
 *   "entry":  { "id": 42, "submitted_at": "...", "ip": "...", "status": "complete" },
 *   "values": { "name": "Alice", "email": "alice@example.com" }
 * }
 * ```
 *
 * When {@see WebhookDefinition::$secret} is set, the registrar adds an
 * `X-Contenir-Signature: sha256=<hex>` header — the receiver verifies by
 * computing `hash_hmac('sha256', $body, $secret)` against the raw body
 * and comparing.
 */
class WebhookRegistrar implements SplObserver
{
    public function __construct(
        private ?LoggerInterface $log = null,
        private int $timeoutSeconds = 10,
    ) {
    }

    public function update(SplSubject $subject): void
    {
        if (! $subject instanceof BuilderForm) {
            return;
        }

        $registry = $this->extractRegistry($subject);
        $form     = $registry['form'] ?? null;
        if (! $form instanceof FormDefinition) {
            return;
        }
        if ((bool) ($registry['spam'] ?? false)) {
            return;
        }
        if ($form->webhooks === []) {
            return;
        }

        $values = isset($registry['values']) && is_array($registry['values']) ? $registry['values'] : [];
        $entry  = isset($registry['entry']) && is_array($registry['entry']) ? $registry['entry'] : [];

        $payload = [
            'form'   => [
                'id'    => $form->id,
                'slug'  => $form->slug,
                'title' => $form->title,
            ],
            'entry'  => $entry,
            'values' => $values,
        ];
        $body = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        foreach ($form->webhooks as $webhook) {
            if (! $webhook->enabled) {
                continue;
            }
            $this->dispatch($webhook, $form, $body);
        }
    }

    private function dispatch(WebhookDefinition $webhook, FormDefinition $form, string $body): void
    {
        $url = trim($webhook->url);
        if ($url === '' || ! preg_match('~^https?://~i', $url)) {
            return;
        }

        $headers = [
            'Content-Type: application/json',
            'User-Agent: Contenir-FormBuilder-Webhook/1.0',
        ];
        foreach ($webhook->headers as $name => $value) {
            $headers[] = sprintf('%s: %s', $name, $value);
        }
        if ($webhook->secret !== null && $webhook->secret !== '') {
            $headers[] = 'X-Contenir-Signature: sha256=' . hash_hmac('sha256', $body, $webhook->secret);
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return;
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $webhook->method !== '' ? $webhook->method : 'POST',
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = $response === false ? curl_error($ch) : '';
        curl_close($ch);

        if ($response === false || $status >= 400) {
            $this->log?->warning(sprintf(
                'Webhook "%s" for form "%s" failed (status %d): %s',
                $webhook->name,
                $form->slug,
                $status,
                $error !== '' ? $error : 'HTTP ' . $status,
            ));
        }
    }

    /** @return array<string, mixed> */
    private function extractRegistry(BuilderForm $form): array
    {
        $registry = $form->registry;
        if ($registry instanceof \ArrayObject) {
            return (array) $registry;
        }
        return array_values([]);
    }
}
