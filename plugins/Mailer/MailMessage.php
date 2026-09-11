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

if (!defined('IN_KAMI')) die();

final class MailMessage
{
    private array $to = [];
    private array $cc = [];
    private array $bcc = [];
    private array $replyTo = [];
    private array $attachments = [];

    private string $subject = '';
    private string $htmlBody = '';
    private string $textBody = '';
    private bool $isHtml = true;

    public static function make(): self
    {
        return new self();
    }

    public function addTo(string $email, ?string $name = null): self
    {
        $this->to[] = [
            'email' => trim($email),
            'name' => $name !== null ? trim($name) : null,
        ];

        return $this;
    }

    public function addCc(string $email, ?string $name = null): self
    {
        $this->cc[] = [
            'email' => trim($email),
            'name' => $name !== null ? trim($name) : null,
        ];

        return $this;
    }

    public function addBcc(string $email, ?string $name = null): self
    {
        $this->bcc[] = [
            'email' => trim($email),
            'name' => $name !== null ? trim($name) : null,
        ];

        return $this;
    }

    public function addReplyTo(string $email, ?string $name = null): self
    {
        $this->replyTo[] = [
            'email' => trim($email),
            'name' => $name !== null ? trim($name) : null,
        ];

        return $this;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = trim($subject);
        return $this;
    }

    public function setHtmlBody(string $htmlBody): self
    {
        $this->htmlBody = $htmlBody;
        $this->isHtml = true;
        return $this;
    }

    public function setTextBody(string $textBody): self
    {
        $this->textBody = $textBody;
        return $this;
    }

    public function setIsHtml(bool $isHtml): self
    {
        $this->isHtml = $isHtml;
        return $this;
    }

    public function addAttachment(
        string $path,
        ?string $name = null,
        string $encoding = 'base64',
        string $mimeType = ''
    ): self {
        $this->attachments[] = [
            'path' => $path,
            'name' => $name,
            'encoding' => $encoding,
            'mimeType' => $mimeType,
        ];

        return $this;
    }

    public function getTo(): array
    {
        return $this->to;
    }

    public function getCc(): array
    {
        return $this->cc;
    }

    public function getBcc(): array
    {
        return $this->bcc;
    }

    public function getReplyTo(): array
    {
        return $this->replyTo;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getHtmlBody(): string
    {
        return $this->htmlBody;
    }

    public function getTextBody(): string
    {
        return $this->textBody;
    }

    public function isHtml(): bool
    {
        return $this->isHtml;
    }

    public function validate(): void
    {
        if ($this->to === []) {
            throw new \RuntimeException(
                'Mail message must have at least one recipient.'
            );
        }

        if ($this->subject === '') {
            throw new \RuntimeException('Mail subject cannot be empty.');
        }

        if ($this->htmlBody === '' && $this->textBody === '') {
            throw new \RuntimeException(
                'Mail message must have htmlBody or textBody.'
            );
        }
    }
}
