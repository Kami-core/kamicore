<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\ContactForm;

if (!defined('IN_KAMI')) die();

final class ContactForm extends \Core\BasePlugin
{
    private const TOKEN_PURPOSE = 'contact-form-instance';

    public function show(array $instanceParams = []): string
    {
        $this->addJs('/plugins/ContactForm/assets/contact-form.js');
        $contentTypeId = $this->positiveInt($instanceParams['content_type'] ?? null);
        if ($contentTypeId === null) {
            return $this->result('invalid_form', 'error');
        }

        try {
            $contentType = \Core\Content::getContentType($contentTypeId, LANG);
        } catch (\Throwable) {
            return $this->result('invalid_form', 'error');
        }

        $fields = is_array($contentType['schema']['fields'] ?? null)
            ? $contentType['schema']['fields']
            : [];
        uasort($fields, static fn(array $a, array $b): int =>
            ((int)($a['displayorder'] ?? 0)) <=> ((int)($b['displayorder'] ?? 0))
        );

        foreach ($fields as $name => &$field) {
            if (!is_array($field) || !empty($field['settings']['hidden'])) {
                unset($fields[$name]);
                continue;
            }
            $field['name'] = (string)$name;
        }
        unset($field);

        $token = $this->instanceToken([
            'content_type' => $contentTypeId,
            'recipient' => trim((string)($instanceParams['recipient_override'] ?? '')),
            'subject' => trim((string)($instanceParams['subject_override'] ?? '')),
        ]);

        $id = 'contact-form-' . substr(hash('sha256', $token), 0, 12);
        $resultId = $id . '-result';
        $form = $this->forms()->renderForm($fields, '/ajax/ContactForm/submit', [
            'method' => 'post',
            'ajax' => true,
            'target' => '#' . $resultId,
            'attributes' => [
                'class' => 'contact-form-fields',
                'data-callback' => 'contactFormResponse',
            ],
            'submit_label' => $this->phrases['submit'] ?? 'Send',
        ]);
        $internalFields = '<input type="hidden" name="_contact_config" value="'
            . \Core\Html::escape($token) . '">'
            . '<div class="contact-form-honeypot" aria-hidden="true" hidden>'
            . '<label>Website<input type="text" name="_contact_website" value="" '
            . 'autocomplete="off" tabindex="-1"></label></div>';
        $form = preg_replace('/<\/form>\s*$/', $internalFields . '</form>', $form, 1) ?? $form;

        $title = trim((string)($instanceParams['title'] ?? ''));
        return $this->render('contact-form', [
            'form_id' => \Core\Html::escape($id),
            'result_id' => \Core\Html::escape($resultId),
            'form_title' => $title !== ''
                ? '<h2 class="contact-form-title">' . \Core\Html::escape($title) . '</h2>'
                : '',
            'form' => $form,
        ]);
    }

    public function submit(array $data = []): string
    {
        $config = $this->verifyInstanceToken((string)($data['_contact_config'] ?? ''));
        if ($config === null) {
            return $this->result('invalid_form', 'error');
        }

        if (trim((string)($data['_contact_website'] ?? '')) !== '') {
            return $this->result('success', 'success', true);
        }

        $contentTypeId = (int)$config['content_type'];
        try {
            $contentType = \Core\Content::getContentType($contentTypeId, LANG);
        } catch (\Throwable) {
            return $this->result('invalid_form', 'error');
        }

        $payload = $this->submissionPayload($contentType, $data);
        $contextId = \Core\ClientContext::id();
        $ip = $this->remoteAddress();

        if ($this->rateLimited($contextId, $ip)) {
            $this->recordRateLimited($contentTypeId, $contextId, $ip);
            return $this->result('rate_limited', 'error');
        }

        $itemId = null;
        try {
            $normalizedPayload = $this->contentManager()->validateItemFromPlugin(
                $contentTypeId,
                $payload,
                LANG
            );
            if ($this->boolSetting('save_item', true)) {
                $created = $this->contentManager()->createItemFromPlugin([
                    'type' => $contentTypeId,
                    'lang' => LANG,
                    'data' => $normalizedPayload,
                ]);
                $itemId = isset($created['item']['item_id'])
                    ? (int)$created['item']['item_id']
                    : null;
            }
        } catch (\Core\PluginActionException | \InvalidArgumentException | \DomainException $error) {
            $this->log('Submission validation failed: ' . $error->getMessage(), 'warning');
            return $this->result('invalid_submission', 'error');
        } catch (\Throwable $error) {
            $this->log('Submission processing failed: ' . $error->getMessage(), 'error');
            return $this->result('processing_failed', 'error');
        }

        $submissionId = $this->storeSubmission(
            $contentTypeId,
            $itemId,
            $contextId,
            $ip,
            $payload
        );

        $delivery = $this->deliver($payload, $contentType, $config);
        \DB::update(
            'contact_submissions',
            ['delivery_status' => \Core\Utils\JsonTool::encode($delivery, false)],
            'submission_id=$1',
            [$submissionId]
        );

        foreach ($delivery as $state) {
            if (($state['status'] ?? '') === 'failed') {
                return $this->result('delivery_failed', 'warning', true);
            }
        }

        return $this->result('success', 'success', true);
    }

    private function submissionPayload(array $contentType, array $data): array
    {
        $structure = is_array($contentType['schema']['fields'] ?? null)
            ? $contentType['schema']['fields']
            : [];
        $payload = [];
        foreach ($structure as $name => $field) {
            if (!is_string($name) || !is_array($field) || !empty($field['settings']['hidden'])) {
                continue;
            }
            if (array_key_exists($name, $data)) {
                $payload[$name] = $data[$name];
            }
        }
        return $payload;
    }

    private function storeSubmission(
        int $contentTypeId,
        ?int $itemId,
        string $contextId,
        ?string $ip,
        array $payload
    ): int {
        $userId = \Core\User::getId();
        $id = \DB::insert('contact_submissions', [
            'domain_id' => $this->domainId,
            'content_type_id' => $contentTypeId,
            'item_id' => $itemId,
            'context_id' => $contextId,
            'user_id' => $userId > 0 ? $userId : null,
            'ip_address' => $ip,
            'user_agent' => \Core\Request::header('User-Agent'),
            'referer' => \Core\Request::header('Referer'),
            'payload' => \Core\Utils\JsonTool::encode($payload, false),
            'status' => 'submitted',
            'delivery_status' => '{}',
        ], 'submission_id');

        if (!is_numeric($id)) {
            throw new \RuntimeException('Failed to store contact submission.');
        }
        return (int)$id;
    }

    private function rateLimited(string $contextId, ?string $ip): bool
    {
        if (!$this->boolSetting('rate_limit_enabled', true)) {
            return false;
        }
        $limit = max(1, (int)($this->settings['rate_limit_count'] ?? 5));
        $window = max(1, (int)($this->settings['rate_limit_window'] ?? 300));

        $contextCount = (int)\DB::getOne(
            "SELECT count(*) FROM contact_submissions
             WHERE domain_id=$1 AND status='submitted' AND context_id=$2
               AND created_at >= CURRENT_TIMESTAMP - ($3::integer * INTERVAL '1 second')",
            [$this->domainId, $contextId, $window]
        );
        if ($contextCount >= $limit) {
            return true;
        }
        if ($ip === null) {
            return false;
        }

        return (int)\DB::getOne(
            "SELECT count(*) FROM contact_submissions
             WHERE domain_id=$1 AND status='submitted' AND ip_address=$2::inet
               AND created_at >= CURRENT_TIMESTAMP - ($3::integer * INTERVAL '1 second')",
            [$this->domainId, $ip, $window]
        ) >= $limit;
    }

    private function recordRateLimited(int $contentTypeId, string $contextId, ?string $ip): void
    {
        // Keep at most one blocked-attempt marker per context per minute.
        \DB::query(
            "INSERT INTO contact_submissions (
                domain_id, content_type_id, context_id, user_id, ip_address,
                user_agent, referer, status
             )
             SELECT $1, $2, $3, $4, $5::inet, $6, $7, 'rate_limited'
             WHERE NOT EXISTS (
                SELECT 1 FROM contact_submissions
                WHERE domain_id=$1 AND status='rate_limited' AND context_id=$3
                  AND created_at >= CURRENT_TIMESTAMP - INTERVAL '1 minute'
             )",
            [
                $this->domainId,
                $contentTypeId,
                $contextId,
                \Core\User::getId() > 0 ? \Core\User::getId() : null,
                $ip,
                \Core\Request::header('User-Agent'),
                \Core\Request::header('Referer'),
            ]
        );
    }

    private function deliver(array $payload, array $contentType, array $config): array
    {
        $channels = $this->settings['notification_channels'] ?? [];
        if (!is_array($channels)) {
            $channels = $channels === '' ? [] : [$channels];
        }
        $channels = array_values(array_unique(array_map('strval', $channels)));
        $result = [];

        if (in_array('mail', $channels, true)) {
            $result['mail'] = $this->deliverMail($payload, $contentType, $config);
        }
        return $result;
    }

    private function deliverMail(array $payload, array $contentType, array $config): array
    {
        $recipient = trim((string)($config['recipient'] ?? ''));
        if ($recipient === '') {
            $recipient = trim((string)($this->settings['recipient'] ?? ''));
        }
        $recipients = array_values(array_filter(array_map(
            'trim',
            explode(',', $recipient)
        ), static fn(string $address): bool => $address !== ''));
        if ($recipients === []) {
            return ['status' => 'failed', 'error' => 'Recipient is not configured.'];
        }
        foreach ($recipients as $address) {
            if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
                return ['status' => 'failed', 'error' => "Invalid recipient: {$address}"];
            }
        }

        $mailer = $this->plugins->get('Mailer');
        if (!$mailer instanceof \Plugins\Mailer\Mailer) {
            return ['status' => 'failed', 'error' => 'Mailer plugin is not available.'];
        }

        $subject = trim((string)($config['subject'] ?? ''));
        if ($subject === '') {
            $subject = trim((string)($this->settings['subject'] ?? 'New form submission'));
        }

        $fields = [];
        foreach ($payload as $name => $value) {
            $field = $contentType['schema']['fields'][$name] ?? [];
            $fields[] = [
                'label' => (string)($field['title'] ?? $name),
                'value' => $this->plainValue($value),
            ];
        }

        $requestDetails = [
            ['label' => 'User', 'value' => \Core\User::isGuest() ? 'Guest' : '#' . \Core\User::getId()],
            ['label' => 'IP', 'value' => $this->remoteAddress() ?? '-'],
            ['label' => 'User-Agent', 'value' => \Core\Request::header('User-Agent') ?? '-'],
            ['label' => 'Referer', 'value' => \Core\Request::header('Referer') ?? '-'],
            ['label' => 'Context', 'value' => \Core\ClientContext::id()],
        ];

        $textBody = $this->mailTextBody($fields, $requestDetails);
        $htmlBody = $this->mailHtmlBody($subject, $fields, $requestDetails);

        try {
            $message = $mailer->createMessage()
                ->setSubject($subject)
                ->setTextBody($textBody)
                ->setHtmlBody($htmlBody);
            foreach ($recipients as $address) {
                $message->addTo($address);
            }
            $mailer->send($message, $this->domainId);
            return ['status' => 'sent'];
        } catch (\Throwable $error) {
            return ['status' => 'failed', 'error' => $error->getMessage()];
        }
    }

    private function mailTextBody(array $fields, array $requestDetails): string
    {
        $lines = ['Form submission', ''];
        foreach ($fields as $field) {
            $lines[] = $field['label'] . ': ' . $field['value'];
        }
        $lines[] = '';
        $lines[] = 'Request details';
        foreach ($requestDetails as $detail) {
            $lines[] = $detail['label'] . ': ' . $detail['value'];
        }
        return implode("\n", $lines);
    }

    private function mailHtmlBody(string $subject, array $fields, array $requestDetails): string
    {
        $rows = $this->mailHtmlRows($fields);
        $details = $this->mailHtmlRows($requestDetails);
        $title = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<!doctype html><html><body style="margin:0;padding:24px;background:#f5f5f5;'
            . 'font-family:Arial,Helvetica,sans-serif;color:#222;">'
            . '<div style="max-width:720px;margin:0 auto;background:#fff;border:1px solid #ddd;'
            . 'border-radius:8px;overflow:hidden;">'
            . '<div style="padding:20px 24px;border-bottom:1px solid #e5e5e5;">'
            . '<h2 style="margin:0;font-size:20px;line-height:1.35;">' . $title . '</h2></div>'
            . '<div style="padding:8px 24px 20px;">'
            . '<table role="presentation" style="width:100%;border-collapse:collapse;">' . $rows . '</table>'
            . '<h3 style="margin:24px 0 8px;padding-top:16px;border-top:1px solid #e5e5e5;'
            . 'font-size:14px;color:#666;">Request details</h3>'
            . '<table role="presentation" style="width:100%;border-collapse:collapse;font-size:13px;'
            . 'color:#666;">' . $details . '</table>'
            . '</div></div></body></html>';
    }

    private function mailHtmlRows(array $rows): string
    {
        $html = '';
        foreach ($rows as $row) {
            $label = htmlspecialchars((string)$row['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $value = htmlspecialchars((string)$row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= '<tr>'
                . '<th style="width:160px;padding:10px 16px 10px 0;border-bottom:1px solid #eee;'
                . 'text-align:left;vertical-align:top;font-weight:600;">' . $label . '</th>'
                . '<td style="padding:10px 0;border-bottom:1px solid #eee;vertical-align:top;'
                . 'white-space:pre-wrap;overflow-wrap:anywhere;">' . $value . '</td>'
                . '</tr>';
        }
        return $html;
    }

    private function instanceToken(array $config): string
    {
        $json = \Core\Utils\JsonTool::encode($config, false);
        $payload = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $signature = \Core\Crypto::sign(self::TOKEN_PURPOSE, $this->domainId . ':' . $payload);
        return $payload . '.' . $signature;
    }

    private function verifyInstanceToken(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$payload, $signature] = $parts;
        if (!\Core\Crypto::verifySignature(
            self::TOKEN_PURPOSE,
            $this->domainId . ':' . $payload,
            $signature
        )) {
            return null;
        }

        $raw = base64_decode(strtr($payload, '-_', '+/'), true);
        if ($raw === false) {
            return null;
        }
        try {
            $config = \Core\Utils\JsonTool::decodeArray($raw);
        } catch (\Throwable) {
            return null;
        }
        return $this->positiveInt($config['content_type'] ?? null) !== null ? $config : null;
    }

    private function remoteAddress(): ?string
    {
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    private function plainValue(mixed $value): string
    {
        if (is_array($value)) {
            return \Core\Utils\JsonTool::encode($value, false);
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        return $value === null ? '' : (string)$value;
    }

    private function boolSetting(string $name, bool $default): bool
    {
        $value = $this->settings[$name] ?? $default;
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
    }

    private function positiveInt(mixed $value): ?int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return null;
        }
        $value = (int)$value;
        return $value > 0 ? $value : null;
    }

    private function result(string $phrase, string $style, bool $complete = false): string
    {
        $fallbacks = [
            'success' => 'Thank you. Your message has been received.',
            'invalid_form' => 'This form is not available.',
            'invalid_submission' => 'Please check the form fields and try again.',
            'rate_limited' => 'Too many submissions. Please try again later.',
            'delivery_failed' => 'Your message was saved, but notification delivery failed.',
            'processing_failed' => 'The form could not be processed. Please try again later.',
        ];
        return $this->render('result', [
            'style' => \Core\Html::escape($style),
            'complete_attribute' => $complete ? ' data-contact-complete' : '',
            'message' => \Core\Html::escape($this->phrases[$phrase] ?? $fallbacks[$phrase] ?? ''),
        ]);
    }

    private function forms(): \Plugins\Forms\Forms
    {
        $plugin = $this->plugins->get('Forms');
        if (!$plugin instanceof \Plugins\Forms\Forms) {
            throw new \RuntimeException('Forms plugin is not available.');
        }
        return $plugin;
    }

    private function contentManager(): \Plugins\ContentManager\ContentManager
    {
        $plugin = $this->plugins->get('ContentManager');
        if (!$plugin instanceof \Plugins\ContentManager\ContentManager) {
            throw new \RuntimeException('ContentManager plugin is not available.');
        }
        return $plugin;
    }
}
