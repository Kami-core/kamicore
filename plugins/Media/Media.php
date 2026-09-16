<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\Media;

use Core\Request;
use Core\Response;
use Core\User;

if (!defined('IN_KAMI')) die();

final class Media extends \Core\BasePlugin
{
    private const MEDIA_DIRECTORY = 'media';
    private const MEDIA_URL = '/media/';

    private const HARD_DENIED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phtml', 'phar',
        'cgi', 'fcgi', 'pl', 'py', 'rb', 'sh', 'bash', 'zsh', 'ksh', 'ps1',
        'asp', 'aspx', 'jsp', 'jspx', 'cfm', 'cfc', 'shtml', 'ssi',
        'js', 'mjs', 'cjs', 'html', 'htm', 'xhtml', 'svg', 'svgz',
        'exe', 'com', 'bat', 'cmd', 'msi', 'dll', 'so', 'dylib', 'jar',
    ];

    private const HARD_DENIED_MIME = [
        'application/x-httpd-php',
        'application/x-php',
        'application/x-cgi',
        'application/x-sh',
        'application/x-shellscript',
        'application/x-dosexec',
        'application/x-msdownload',
        'application/javascript',
        'application/ecmascript',
        'text/javascript',
        'text/ecmascript',
        'text/html',
        'application/xhtml+xml',
        'image/svg+xml',
    ];

    private const KNOWN_MIME_BY_EXTENSION = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'avif' => ['image/avif', 'image/heif'],
        'gif' => ['image/gif'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/CDFV2', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/CDFV2', 'application/x-ole-storage'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/CDFV2', 'application/x-ole-storage'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'odp' => ['application/vnd.oasis.opendocument.presentation', 'application/zip'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
        'rtf' => ['application/rtf', 'text/rtf', 'text/plain'],
        'mp3' => ['audio/mpeg', 'audio/mp3'],
        'ogg' => ['audio/ogg', 'application/ogg'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/vnd.wave'],
        'm4a' => ['audio/mp4', 'audio/x-m4a', 'video/mp4'],
        'mp4' => ['video/mp4', 'application/mp4'],
        'webm' => ['video/webm', 'audio/webm'],
        'mov' => ['video/quicktime'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];

    public function registerBrowserAssets(): void
    {
        $this->addCss('/plugins/Media/assets/media-browser.css');
        $this->addJs('/plugins/Media/assets/media-browser.js');
    }

    public function browser(array $instanceParams = []): string
    {
        $this->registerBrowserAssets();
        $root = $this->normalizeRelativePath((string)($instanceParams['root'] ?? ''));
        $canManage = User::canPlugin((int)$this->id, 'manage');

        return $this->render('browser', [
            'root' => \Core\Html::escape($root),
            'can_manage' => $canManage ? '1' : '0',
            'list_url' => '/ajax/Media/listFiles',
            'upload_url' => '/ajax/Media/upload',
            'mkdir_url' => '/ajax/Media/createFolder',
            'rename_url' => '/ajax/Media/rename',
            'move_url' => '/ajax/Media/move',
            'delete_url' => '/ajax/Media/delete',
        ]);
    }

    public function listFiles(array $data = []): string
    {
        return $this->jsonAction(function () use ($data): array {
            $root = $this->normalizeRelativePath((string)($data['root'] ?? ''));
            $path = $this->normalizeRelativePath((string)($data['path'] ?? $root));
            $this->assertWithinBrowserRoot($path, $root);

            return [
                'status' => 'ok',
                'data' => $this->directoryPayload($path, $root),
            ];
        });
    }

    public function upload(array $data = []): string
    {
        return $this->jsonAction(function () use ($data): array {
            $currentPath = $this->normalizeRelativePath((string)($data['path'] ?? ''));
            $root = $this->normalizeRelativePath((string)($data['root'] ?? ''));
            $this->assertWithinBrowserRoot($currentPath, $root);

            $currentAbsolute = $this->resolveExistingPath($currentPath);
            if (!is_dir($currentAbsolute)) {
                throw new \InvalidArgumentException('Upload path must be an existing Media folder.');
            }
            $this->assertValidManagedPath($currentPath);

            $targetRelative = $this->getUploadDirectory($currentPath);
            $this->assertWithinBrowserRoot($targetRelative, $root);
            $targetDirectory = $this->resolvePath($targetRelative, true);
            $this->ensureDirectory($targetDirectory);

            $files = $this->normalizeUploadedFiles(Request::file('files'));
            if ($files === []) {
                throw new \InvalidArgumentException('No files were uploaded.');
            }

            $uploaded = [];
            foreach ($files as $file) {
                $uploaded[] = $this->storeUploadedFile($file, $targetRelative, $targetDirectory);
            }

            return [
                'status' => 'ok',
                'path' => $targetRelative,
                'files' => $uploaded,
            ];
        });
    }

    public function createFolder(array $data = []): string
    {
        return $this->jsonAction(function () use ($data): array {
            $path = $this->normalizeRelativePath((string)($data['path'] ?? ''));
            $root = $this->normalizeRelativePath((string)($data['root'] ?? ''));
            $this->assertWithinBrowserRoot($path, $root);

            $parentAbsolute = $this->resolveExistingPath($path);
            if (!is_dir($parentAbsolute)) {
                throw new \InvalidArgumentException('Folder parent must be an existing Media folder.');
            }
            $this->assertValidManagedPath($path);
            if ($this->splitDateTail($path)['date_depth'] > 0) {
                throw new \InvalidArgumentException('Folders can only be created in the user-managed part of Media.');
            }

            $name = $this->normalizeDirectoryName((string)($data['name'] ?? ''));
            if ($this->isYearSegment($name)) {
                throw new \InvalidArgumentException('Four-digit year folder names are reserved for Media date directories.');
            }

            $relative = $this->joinRelative($path, $name);
            $absolute = $this->resolvePath($relative, true);
            if (file_exists($absolute) || is_link($absolute)) {
                throw new \RuntimeException('A file or folder with this name already exists.');
            }
            if (!mkdir($absolute, 0755, false) && !is_dir($absolute)) {
                throw new \RuntimeException('Failed to create folder.');
            }

            return ['status' => 'ok', 'item' => $this->entryPayload($relative, $absolute)];
        });
    }

    public function rename(array $data = []): string
    {
        return $this->jsonAction(function () use ($data): array {
            $path = $this->normalizeRelativePath((string)($data['path'] ?? ''));
            $root = $this->normalizeRelativePath((string)($data['root'] ?? ''));
            $this->assertWithinBrowserRoot($path, $root);
            if ($path === '' || $path === $root) {
                throw new \InvalidArgumentException('The browser root cannot be renamed.');
            }

            $absolute = $this->resolveExistingPath($path);
            $isDirectory = is_dir($absolute);
            if ($isDirectory && $this->isDateDirectoryPath($path)) {
                throw new \InvalidArgumentException('Media date directories cannot be renamed.');
            }

            $parent = $this->parentRelative($path);
            $name = $isDirectory
                ? $this->normalizeDirectoryName((string)($data['name'] ?? ''))
                : $this->normalizeFileName((string)($data['name'] ?? ''));

            if ($isDirectory && $this->isYearSegment($name)) {
                throw new \InvalidArgumentException('Four-digit year folder names are reserved for Media date directories.');
            }

            if (!$isDirectory) {
                $this->assertAllowedStoredFileName($name);
                $newExtension = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
                $this->assertSafeMime($newExtension, $this->detectMime($absolute));
            }

            $targetRelative = $this->joinRelative($parent, $name);
            $this->assertWithinBrowserRoot($targetRelative, $root);
            $targetAbsolute = $this->resolvePath($targetRelative, true);
            if (file_exists($targetAbsolute) || is_link($targetAbsolute)) {
                throw new \RuntimeException('A file or folder with this name already exists.');
            }
            if (!rename($absolute, $targetAbsolute)) {
                throw new \RuntimeException('Failed to rename item.');
            }

            return ['status' => 'ok', 'item' => $this->entryPayload($targetRelative, $targetAbsolute)];
        });
    }

    public function move(array $data = []): string
    {
        return $this->jsonAction(function () use ($data): array {
            $path = $this->normalizeRelativePath((string)($data['path'] ?? ''));
            $destination = $this->normalizeRelativePath((string)($data['destination'] ?? ''));
            $root = $this->normalizeRelativePath((string)($data['root'] ?? ''));
            $this->assertWithinBrowserRoot($path, $root);
            $this->assertWithinBrowserRoot($destination, $root);

            if ($path === '' || $path === $root) {
                throw new \InvalidArgumentException('The browser root cannot be moved.');
            }
            $destinationAbsolute = $this->resolveExistingPath($destination);
            if (!is_dir($destinationAbsolute)) {
                throw new \InvalidArgumentException('Move destination must be an existing Media folder.');
            }
            $this->assertValidManagedPath($destination);
            if ($this->splitDateTail($destination)['date_depth'] > 0) {
                throw new \InvalidArgumentException('Move destination must be a user-managed Media folder.');
            }

            $absolute = $this->resolveExistingPath($path);
            if (is_dir($absolute)) {
                if ($this->isDateDirectoryPath($path)) {
                    throw new \InvalidArgumentException('Media date directories cannot be moved.');
                }
                $targetRelative = $this->joinRelative($destination, basename($path));
            } else {
                $parts = $this->splitDateTail($this->parentRelative($path));
                if ($parts['date_depth'] !== 2) {
                    throw new \InvalidArgumentException('Media files can only be moved from a YYYY/MM directory.');
                }
                $targetDirectory = $this->joinRelative(
                    $destination,
                    $parts['year'] . '/' . $parts['month']
                );
                $targetAbsoluteDirectory = $this->resolvePath($targetDirectory, true);
                $this->ensureDirectory($targetAbsoluteDirectory);
                $targetRelative = $this->joinRelative($targetDirectory, basename($path));
            }

            $this->assertWithinBrowserRoot($targetRelative, $root);
            if ($this->pathStartsWith($destination, $path)) {
                throw new \InvalidArgumentException('A folder cannot be moved inside itself.');
            }

            $targetAbsolute = $this->resolvePath($targetRelative, true);
            if (file_exists($targetAbsolute) || is_link($targetAbsolute)) {
                throw new \RuntimeException('A file or folder with this name already exists at the destination.');
            }
            if (!rename($absolute, $targetAbsolute)) {
                throw new \RuntimeException('Failed to move item.');
            }

            return ['status' => 'ok', 'item' => $this->entryPayload($targetRelative, $targetAbsolute)];
        });
    }

    public function delete(array $data = []): string
    {
        return $this->jsonAction(function () use ($data): array {
            $path = $this->normalizeRelativePath((string)($data['path'] ?? ''));
            $root = $this->normalizeRelativePath((string)($data['root'] ?? ''));
            $this->assertWithinBrowserRoot($path, $root);
            if ($path === '' || $path === $root) {
                throw new \InvalidArgumentException('The browser root cannot be deleted.');
            }

            $absolute = $this->resolveExistingPath($path);
            if (is_dir($absolute)) {
                $items = scandir($absolute);
                if ($items === false) {
                    throw new \RuntimeException('Failed to read folder.');
                }
                $items = array_values(array_diff($items, ['.', '..']));
                if ($items !== []) {
                    throw new \RuntimeException('Only empty folders can be deleted.');
                }
                if (!rmdir($absolute)) {
                    throw new \RuntimeException('Failed to delete folder.');
                }
            } else {
                if (!unlink($absolute)) {
                    throw new \RuntimeException('Failed to delete file.');
                }
            }

            return ['status' => 'ok'];
        });
    }

    private function directoryPayload(string $path, string $root): array
    {
        $absolute = $this->resolveExistingPath($path);
        if (!is_dir($absolute)) {
            throw new \InvalidArgumentException('Media path is not a directory.');
        }

        $items = scandir($absolute);
        if ($items === false) {
            throw new \RuntimeException('Failed to read Media directory.');
        }

        $entries = [];
        foreach ($items as $name) {
            if ($name === '.' || $name === '..' || str_starts_with($name, '.')) {
                continue;
            }

            $relative = $this->joinRelative($path, $name);
            $itemAbsolute = $absolute . DIRECTORY_SEPARATOR . $name;
            if (is_link($itemAbsolute)) {
                continue;
            }
            if (!is_file($itemAbsolute) && !is_dir($itemAbsolute)) {
                continue;
            }

            $entries[] = $this->entryPayload($relative, $itemAbsolute);
        }

        usort($entries, static function (array $a, array $b): int {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            return strnatcasecmp((string)$a['name'], (string)$b['name']);
        });

        $date = $this->splitDateTail($path);
        $canManage = User::canPlugin((int)$this->id, 'manage');

        return [
            'path' => $path,
            'root' => $root,
            'parent' => $path === $root ? null : $this->parentWithinRoot($path, $root),
            'breadcrumbs' => $this->breadcrumbs($path, $root),
            'zone' => $date['date_depth'] > 0 ? 'date' : 'user',
            'date_depth' => $date['date_depth'],
            'can_create_folder' => $canManage && $date['date_depth'] === 0,
            'can_manage' => $canManage,
            'entries' => $entries,
        ];
    }

    private function entryPayload(string $relative, string $absolute): array
    {
        $isDirectory = is_dir($absolute);
        $name = basename($relative);
        $payload = [
            'name' => $name,
            'path' => $relative,
            'type' => $isDirectory ? 'directory' : 'file',
            'modified' => filemtime($absolute) ?: null,
        ];

        if ($isDirectory) {
            $payload['url'] = null;
            $payload['mime'] = null;
            $payload['extension'] = null;
            $payload['size'] = null;
            $payload['previewable'] = false;
            $payload['system_date_directory'] = $this->isDateDirectoryPath($relative);
            return $payload;
        }

        $mime = $this->detectMime($absolute);
        $extension = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        $payload['url'] = $this->urlForPath($relative);
        $payload['mime'] = $mime;
        $payload['extension'] = $extension;
        $payload['size'] = filesize($absolute) ?: 0;
        $payload['previewable'] = str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml';
        $payload['system_date_directory'] = false;
        return $payload;
    }

    private function storeUploadedFile(array $file, string $targetRelative, string $targetDirectory): array
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->uploadErrorMessage($error));
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new \RuntimeException('Invalid uploaded file.');
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) {
            throw new \InvalidArgumentException('Empty files are not accepted.');
        }
        if ($size > $this->maxUploadBytes()) {
            throw new \InvalidArgumentException('Uploaded file exceeds the Media maximum upload size.');
        }

        $name = $this->normalizeFileName((string)($file['name'] ?? ''));
        $extension = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        $this->assertAllowedExtension($extension);

        $mime = $this->detectMime($tmpName);
        $this->assertSafeMime($extension, $mime);

        [$finalName, $targetAbsolute] = $this->uniqueTarget($targetDirectory, $name);
        if (!move_uploaded_file($tmpName, $targetAbsolute)) {
            throw new \RuntimeException('Failed to move uploaded file into Media.');
        }
        @chmod($targetAbsolute, 0644);

        $finalRelative = $this->joinRelative($targetRelative, $finalName);
        return $this->entryPayload($finalRelative, $targetAbsolute);
    }

    private function normalizeUploadedFiles(?array $files): array
    {
        if (!$files || !array_key_exists('name', $files)) {
            return [];
        }

        if (!is_array($files['name'])) {
            return [$files];
        }

        $normalized = [];
        foreach ($files['name'] as $index => $name) {
            $normalized[] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
        }
        return $normalized;
    }

    private function allowedExtensions(): array
    {
        $raw = (string)($this->getSetting('allowed_extensions') ?? '');
        $out = [];
        foreach (explode(',', $raw) as $extension) {
            $extension = strtolower(ltrim(trim($extension), '.'));
            if ($extension !== '') {
                $out[$extension] = true;
            }
        }
        return array_keys($out);
    }

    private function maxUploadBytes(): int
    {
        $mb = (int)($this->getSetting('max_upload_size') ?? 50);
        if ($mb < 1) {
            $mb = 1;
        }
        return $mb * 1024 * 1024;
    }

    private function assertAllowedExtension(string $extension): void
    {
        if ($extension === '') {
            throw new \InvalidArgumentException('Files without an extension are not accepted.');
        }
        if (in_array($extension, self::HARD_DENIED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('This file type is prohibited by Media security policy.');
        }
        if (!in_array($extension, $this->allowedExtensions(), true)) {
            throw new \InvalidArgumentException("File extension .{$extension} is not allowed.");
        }
    }

    private function assertAllowedStoredFileName(string $name): void
    {
        $extension = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        $this->assertAllowedExtension($extension);
    }

    private function assertSafeMime(string $extension, string $mime): void
    {
        $normalizedMime = strtolower(trim(explode(';', $mime, 2)[0]));
        if ($normalizedMime === '' || in_array($normalizedMime, self::HARD_DENIED_MIME, true)) {
            throw new \InvalidArgumentException('Uploaded file content is prohibited by Media security policy.');
        }

        if (str_contains($normalizedMime, 'php')
            || str_contains($normalizedMime, 'javascript')
            || str_contains($normalizedMime, 'shellscript')
            || str_contains($normalizedMime, 'x-executable')) {
            throw new \InvalidArgumentException('Uploaded file content is prohibited by Media security policy.');
        }

        $expected = self::KNOWN_MIME_BY_EXTENSION[$extension] ?? null;
        if ($expected !== null && !in_array($normalizedMime, array_map('strtolower', $expected), true)) {
            throw new \InvalidArgumentException(
                "File content type {$mime} does not match extension .{$extension}."
            );
        }
    }

    private function detectMime(string $file): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return (string)($finfo->file($file) ?: 'application/octet-stream');
    }

    private function uniqueTarget(string $directory, string $name): array
    {
        $extension = (string)pathinfo($name, PATHINFO_EXTENSION);
        $base = (string)pathinfo($name, PATHINFO_FILENAME);
        $candidate = $name;
        $counter = 1;

        while (file_exists($directory . DIRECTORY_SEPARATOR . $candidate)
            || is_link($directory . DIRECTORY_SEPARATOR . $candidate)) {
            $suffix = '-' . $counter++;
            $candidate = $extension !== '' ? $base . $suffix . '.' . $extension : $base . $suffix;
        }

        return [$candidate, $directory . DIRECTORY_SEPARATOR . $candidate];
    }

    private function getUploadDirectory(string $currentPath): string
    {
        $parts = $this->splitDateTail($currentPath);
        $date = new \DateTimeImmutable('now');
        return $this->joinRelative($parts['user_path'], $date->format('Y/m'));
    }

    private function splitDateTail(string $path): array
    {
        $segments = $path === '' ? [] : explode('/', $path);
        $count = count($segments);
        $dateDepth = 0;
        $year = null;
        $month = null;

        if ($count >= 2 && $this->isYearSegment($segments[$count - 2]) && $this->isMonthSegment($segments[$count - 1])) {
            $dateDepth = 2;
            $year = $segments[$count - 2];
            $month = $segments[$count - 1];
            $segments = array_slice($segments, 0, -2);
        } elseif ($count >= 1 && $this->isYearSegment($segments[$count - 1])) {
            $dateDepth = 1;
            $year = $segments[$count - 1];
            $segments = array_slice($segments, 0, -1);
        }

        return [
            'user_path' => implode('/', $segments),
            'date_depth' => $dateDepth,
            'year' => $year,
            'month' => $month,
        ];
    }

    private function isDateDirectoryPath(string $path): bool
    {
        return $this->splitDateTail($path)['date_depth'] > 0;
    }

    private function assertValidManagedPath(string $path): void
    {
        if ($path === '') {
            return;
        }

        $segments = explode('/', $path);
        foreach ($segments as $index => $segment) {
            if (!$this->isYearSegment($segment)) {
                continue;
            }

            $remaining = count($segments) - $index - 1;
            if ($remaining === 0) {
                return;
            }
            if ($remaining === 1 && $this->isMonthSegment($segments[$index + 1])) {
                return;
            }

            throw new \InvalidArgumentException(
                'Four-digit year folders are reserved for the final Media YYYY/MM date structure.'
            );
        }
    }

    private function isYearSegment(string $segment): bool
    {
        return preg_match('/^\d{4}$/D', $segment) === 1;
    }

    private function isMonthSegment(string $segment): bool
    {
        return preg_match('/^(0[1-9]|1[0-2])$/D', $segment) === 1;
    }

    private function mediaRoot(): string
    {
        $publicRoot = realpath(ROOT_PATH);
        if ($publicRoot === false || !is_dir($publicRoot)) {
            throw new \RuntimeException('Unable to resolve Kami public root.');
        }

        $root = rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::MEDIA_DIRECTORY;
        if (is_link($root)) {
            throw new \RuntimeException('Media root cannot be a symbolic link.');
        }
        if (!file_exists($root) && !mkdir($root, 0755, true) && !is_dir($root)) {
            throw new \RuntimeException('Unable to create Media root directory.');
        }
        if (!is_dir($root)) {
            throw new \RuntimeException('Media root is not a directory.');
        }

        $resolved = realpath($root);
        if ($resolved === false) {
            throw new \RuntimeException('Unable to resolve Media root directory.');
        }
        return $resolved;
    }

    private function resolveExistingPath(string $relative): string
    {
        $absolute = $this->resolvePath($relative, false);
        if (!file_exists($absolute) || is_link($absolute)) {
            throw new \RuntimeException('Media item does not exist.');
        }
        return $absolute;
    }

    private function resolvePath(string $relative, bool $allowMissingLeaf): string
    {
        $relative = $this->normalizeRelativePath($relative);
        $root = $this->mediaRoot();
        if ($relative === '') {
            return $root;
        }

        $segments = explode('/', $relative);
        $current = $root;
        $lastIndex = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            $current .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($current)) {
                throw new \RuntimeException('Symbolic links are not supported in Media.');
            }
            if (!file_exists($current)) {
                if ($allowMissingLeaf) {
                    continue;
                }
                throw new \RuntimeException('Media path does not exist.');
            }
            if ($index < $lastIndex && !is_dir($current)) {
                throw new \RuntimeException('Media path contains a non-directory component.');
            }
        }

        $existing = $current;
        while (!file_exists($existing)) {
            $parent = dirname($existing);
            if ($parent === $existing) {
                throw new \RuntimeException('Unable to resolve Media path.');
            }
            $existing = $parent;
        }
        $resolvedExisting = realpath($existing);
        if ($resolvedExisting === false || !$this->absolutePathInside($resolvedExisting, $root)) {
            throw new \RuntimeException('Media path escapes the configured root.');
        }

        return $current;
    }

    private function ensureDirectory(string $absolute): void
    {
        if (is_link($absolute)) {
            throw new \RuntimeException('Symbolic links are not supported in Media.');
        }
        if (!file_exists($absolute)) {
            if (!mkdir($absolute, 0755, true) && !is_dir($absolute)) {
                throw new \RuntimeException('Failed to create Media directory.');
            }
        }
        if (!is_dir($absolute)) {
            throw new \RuntimeException('Media path is not a directory.');
        }
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path === '/') {
            return '';
        }
        if (str_contains($path, "\0") || str_contains($path, '\\')) {
            throw new \InvalidArgumentException('Invalid Media path.');
        }

        $path = trim($path, '/');
        $segments = explode('/', $path);
        $clean = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Invalid Media path.');
            }
            if (preg_match('/[\x00-\x1F\x7F]/u', $segment)) {
                throw new \InvalidArgumentException('Invalid Media path.');
            }
            if (str_starts_with($segment, '.')) {
                throw new \InvalidArgumentException('Hidden Media paths are not allowed.');
            }
            $clean[] = $segment;
        }
        return implode('/', $clean);
    }

    private function normalizeDirectoryName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..') {
            throw new \InvalidArgumentException('Invalid folder name.');
        }
        if (str_starts_with($name, '.')) {
            throw new \InvalidArgumentException('Hidden folders are not allowed.');
        }
        if (str_contains($name, '/') || str_contains($name, '\\') || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
            throw new \InvalidArgumentException('Invalid folder name.');
        }
        return $name;
    }

    private function normalizeFileName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '_', $name) ?? '';
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..' || str_starts_with($name, '.')) {
            throw new \InvalidArgumentException('Invalid file name.');
        }
        return $name;
    }

    private function assertWithinBrowserRoot(string $path, string $root): void
    {
        if ($root === '') {
            return;
        }
        if ($path !== $root && !$this->pathStartsWith($path, $root)) {
            throw new \InvalidArgumentException('Requested path is outside the browser root.');
        }
    }

    private function pathStartsWith(string $path, string $prefix): bool
    {
        if ($prefix === '') {
            return true;
        }
        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    private function absolutePathInside(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    private function parentWithinRoot(string $path, string $root): ?string
    {
        $parent = $this->parentRelative($path);
        if ($root !== '' && !$this->pathStartsWith($parent, $root)) {
            return null;
        }
        return $parent;
    }

    private function parentRelative(string $path): string
    {
        if ($path === '' || !str_contains($path, '/')) {
            return '';
        }
        return substr($path, 0, strrpos($path, '/'));
    }

    private function joinRelative(string ...$parts): string
    {
        $segments = [];
        foreach ($parts as $part) {
            $part = trim($part, '/');
            if ($part !== '') {
                $segments[] = $part;
            }
        }
        return implode('/', $segments);
    }

    private function breadcrumbs(string $path, string $root): array
    {
        $breadcrumbs = [[
            'name' => $root === '' ? 'Media' : basename($root),
            'path' => $root,
        ]];
        if ($path === $root) {
            return $breadcrumbs;
        }

        $relative = $root === '' ? $path : substr($path, strlen($root) + 1);
        $current = $root;
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '') {
                continue;
            }
            $current = $this->joinRelative($current, $segment);
            $breadcrumbs[] = ['name' => $segment, 'path' => $current];
        }
        return $breadcrumbs;
    }

    private function urlForPath(string $relative): string
    {
        $segments = array_map('rawurlencode', explode('/', $relative));
        return self::MEDIA_URL . implode('/', $segments);
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file exceeds the server upload limit.',
            UPLOAD_ERR_PARTIAL => 'Uploaded file was only partially received.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server upload temporary directory is missing.',
            UPLOAD_ERR_CANT_WRITE => 'Server failed to write the uploaded file.',
            UPLOAD_ERR_EXTENSION => 'A server extension stopped the upload.',
            default => 'Unknown upload error.',
        };
    }

    private function jsonAction(callable $callback): string
    {
        try {
            $payload = $callback();
            return $this->jsonResponse($payload, 200);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->log($e->getMessage(), 'error');
            return $this->jsonResponse(['status' => 'error', 'error' => $e->getMessage()], 500);
        }
    }

    private function jsonResponse(array $payload, int $status): string
    {
        Response::addHeader('Content-Type: application/json; charset=utf-8', true, $status);
        return \Core\Utils\JsonTool::encode($payload, false);
    }

}
