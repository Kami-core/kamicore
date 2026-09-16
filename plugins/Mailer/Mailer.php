<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\Mailer;

use PHPMailer\PHPMailer\Exception as PhpMailerException;
use PHPMailer\PHPMailer\PHPMailer;

if (!defined('IN_KAMI')) die();

final class Mailer extends \Core\BasePlugin
{
    private const SECRET_NAMESPACE = 'Mailer';
    private const SMTP_PASSWORD_SECRET = 'smtp_password';

    public function createMessage(): MailMessage
    {
        return MailMessage::make();
    }

    public function send(MailMessage $message, ?int $domainId = null): bool
    {
        $message->validate();

        $domainId ??= defined('DOMAIN_ID') ? (int) DOMAIN_ID : null;
        $config = $this->resolveConfig($domainId, true);
        $this->validateConfig($config);

        $mailer = $this->buildPhpMailer($config);
        $this->applyMessage($mailer, $message);

        try {
            return $mailer->send();
        } catch (PhpMailerException $e) {
            $this->logMailError($domainId, $message, $e);

            throw new \RuntimeException(
                'Mail sending failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Return effective mail configuration without exposing stored secrets.
     *
     * @return array<string, mixed>
     */
    public function getConfig(?int $domainId = null): array
    {
        $domainId ??= defined('DOMAIN_ID') ? (int) DOMAIN_ID : null;
        $config = $this->resolveConfig($domainId, false);
        $config['smtp_password_configured'] = \Core\SecretStore::has(
            self::SECRET_NAMESPACE,
            self::SMTP_PASSWORD_SECRET,
            $domainId
        );

        return $config;
    }

    public function setSmtpPassword(string $password, ?int $domainId = null): void
    {
        if ($password === '') {
            throw new \InvalidArgumentException('SMTP password cannot be empty.');
        }

        \Core\SecretStore::set(
            self::SECRET_NAMESPACE,
            self::SMTP_PASSWORD_SECRET,
            $password,
            $domainId
        );
    }

    public function deleteSmtpPassword(?int $domainId = null): void
    {
        \Core\SecretStore::delete(
            self::SECRET_NAMESPACE,
            self::SMTP_PASSWORD_SECRET,
            $domainId
        );
    }

    public function hasSmtpPassword(?int $domainId = null): bool
    {
        return \Core\SecretStore::has(
            self::SECRET_NAMESPACE,
            self::SMTP_PASSWORD_SECRET,
            $domainId
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveConfig(?int $domainId, bool $includePassword): array
    {
        if ($domainId === null || $domainId === (int) DOMAIN_ID) {
            $config = $this->settings ?? [];
        } else {
            $baseSettings = \DB::getOne(
                'select settings from plugins where plugin_id=$1',
                [$this->id]
            );
            $domain = \DB::getRow(
                'select local_settings from plugin_domains where plugin_id=$1 and domain_id=$2',
                [$this->id, $domainId]
            );

            if (!$domain) {
                throw new \RuntimeException(
                    "Mailer plugin is not active on domain {$domainId}."
                );
            }

            $base = \Core\Utils\JsonTool::decodeArray($baseSettings);
            $local = \Core\Utils\JsonTool::decodeArray($domain['local_settings'] ?? null);
            $config = array_replace($base, $local);
        }

        if ($includePassword && ($config['mailer'] ?? null) === 'smtp') {
            $password = \Core\SecretStore::get(
                self::SECRET_NAMESPACE,
                self::SMTP_PASSWORD_SECRET,
                $domainId
            );

            if ($password !== null) {
                $config['password'] = $password;
            }
        }

        return $config;
    }


    private function validateConfig(array $config): void
    {
        foreach (['mailer', 'from_email', 'from_name', 'charset'] as $key) {
            if (!isset($config[$key]) || trim((string) $config[$key]) === '') {
                throw new \RuntimeException("Mail config key is missing: {$key}");
            }
        }

        if ($config['mailer'] !== 'smtp') {
            return;
        }

        foreach (['host', 'port', 'username', 'password'] as $key) {
            if (!isset($config[$key]) || trim((string) $config[$key]) === '') {
                throw new \RuntimeException("SMTP config key is missing: {$key}");
            }
        }
    }

    private function buildPhpMailer(array $config): PHPMailer
    {
        if (!class_exists(PHPMailer::class)) {
            throw new \RuntimeException(
                'PHPMailer is not available. Configure it as a third-party dependency before using Mailer.'
            );
        }

        $mailer = new PHPMailer(true);
        $mailer->CharSet = (string) $config['charset'];
        $mailer->Timeout = isset($config['timeout']) ? (int) $config['timeout'] : 15;
        $mailer->Encoding = 'base64';

        switch ($config['mailer']) {
            case 'smtp':
                $mailer->isSMTP();
                $mailer->Host = (string) $config['host'];
                $mailer->Port = (int) $config['port'];
                $mailer->SMTPAuth = true;
                $mailer->Username = (string) $config['username'];
                $mailer->Password = (string) $config['password'];

                if (!empty($config['encryption'])) {
                    $mailer->SMTPSecure = (string) $config['encryption'];
                }
                break;

            case 'mail':
                $mailer->isMail();
                break;

            case 'sendmail':
                $mailer->isSendmail();
                break;

            default:
                throw new \RuntimeException(
                    'Unsupported mailer type: ' . (string) $config['mailer']
                );
        }

        $mailer->setFrom(
            (string) $config['from_email'],
            (string) $config['from_name']
        );

        if (!empty($config['reply_to_email'])) {
            $mailer->addReplyTo(
                (string) $config['reply_to_email'],
                (string) ($config['reply_to_name'] ?? '')
            );
        }

        return $mailer;
    }

    private function applyMessage(PHPMailer $mailer, MailMessage $message): void
    {
        foreach ($message->getTo() as $recipient) {
            $mailer->addAddress($recipient['email'], $recipient['name'] ?? '');
        }

        foreach ($message->getCc() as $recipient) {
            $mailer->addCC($recipient['email'], $recipient['name'] ?? '');
        }

        foreach ($message->getBcc() as $recipient) {
            $mailer->addBCC($recipient['email'], $recipient['name'] ?? '');
        }

        foreach ($message->getReplyTo() as $recipient) {
            $mailer->addReplyTo($recipient['email'], $recipient['name'] ?? '');
        }

        foreach ($message->getAttachments() as $attachment) {
            if (!is_file($attachment['path'])) {
                throw new \RuntimeException(
                    'Attachment file not found: ' . $attachment['path']
                );
            }

            $mailer->addAttachment(
                $attachment['path'],
                $attachment['name'] ?? '',
                $attachment['encoding'] ?? 'base64',
                $attachment['mimeType'] ?? ''
            );
        }

        $mailer->Subject = $message->getSubject();
        $mailer->isHTML($message->isHtml());

        if ($message->isHtml()) {
            $mailer->Body = $message->getHtmlBody();
            $altBody = $message->getTextBody();

            if ($altBody === '') {
                $altBody = $this->makePlainTextFromHtml($message->getHtmlBody());
            }

            $mailer->AltBody = $altBody;
            return;
        }

        $mailer->Body = $message->getTextBody();
        $mailer->AltBody = $message->getTextBody();
    }

    private function makePlainTextFromHtml(string $html): string
    {
        $text = strip_tags(str_replace(
            ['<br>', '<br/>', '<br />', '</p>', '</div>'],
            ["\n", "\n", "\n", "\n", "\n"],
            $html
        ));

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\r\n|\r/u", "\n", $text);
        $text = preg_replace("/\n{3,}/u", "\n\n", (string) $text);

        return trim((string) $text);
    }

    private function logMailError(
        ?int $domainId,
        MailMessage $message,
        PhpMailerException $exception
    ): void {
        error_log(sprintf(
            '[error][Mailer] Mail send failed. domain_id=%s error=%s subject=%s to=%s',
            $domainId === null ? 'global' : (string) $domainId,
            $exception->getMessage(),
            $message->getSubject(),
            \Core\Utils\JsonTool::encode($message->getTo(), false)
        ));
    }
}
