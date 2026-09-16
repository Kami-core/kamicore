<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\ThemeManager;

use Core\Utils\JsonTool;

if (!defined('IN_KAMI')) die();

final class ThemeManager extends \Core\BasePlugin
{
    public function overview(array $contextVars = []): string
    {
        $this->assertManagerAccess();
        return $this->renderOverview();
    }

    public function lifecycle(array $contextVars = []): string
    {
        $this->assertManagerAccess();
        $data = \Core\Request::all();
        $operation = trim((string)($data['operation'] ?? ''));

        try {
            $result = match ($operation) {
                'install' => $this->installTheme(
                    $this->validateReference((string)($data['theme_folder'] ?? ''))
                ),
                'update' => $this->updateTheme(
                    $this->validateReference((string)($data['theme_folder'] ?? ''))
                ),
                'uninstall' => $this->uninstallTheme(
                    $this->validateReference((string)($data['theme'] ?? ''))
                ),
                default => throw new \InvalidArgumentException('Unknown theme lifecycle operation.'),
            };
        } catch (\Throwable $e) {
            return $this->renderOverview($this->notice($e->getMessage(), 'error'));
        }

        if (!$result) {
            return $this->renderOverview(
                $this->notice($this->phrase('operation_failed', 'Theme operation failed.'), 'error')
            );
        }

        if ($notifications = $this->plugins->get('Notifications')) {
            $message = match ($operation) {
                'install' => $this->phrase('installed_success', 'Theme installed.'),
                'update' => $this->phrase('updated_success', 'Theme updated.'),
                'uninstall' => $this->phrase('uninstalled_success', 'Theme uninstalled.'),
                default => $this->phrase('operation_success', 'Theme operation completed.'),
            };
            $notifications->store(\Core\User::getId(), $message, 'success');
        }

        return \Core\Response::seeOther($this->managerUrl());
    }

    public function installTheme(string $folder): bool
    {
        return \Core\ExtensionManager::installTheme($folder);
    }

    public function updateTheme(string $folder): bool
    {
        return \Core\ExtensionManager::updateTheme($folder);
    }

    public function uninstallTheme(string $systemName): bool
    {
        return \Core\ExtensionManager::uninstallTheme($systemName);
    }

    private function renderOverview(string $notice = ''): string
    {
        $rows = [];

        foreach ($this->themeCatalog() as $theme) {
            $actions = '';

            if (!$theme['installed']) {
                $actions = '<form method="post" action="' . $this->managerUrl('lifecycle') . '">'
                    . '<input type="hidden" name="operation" value="install">'
                    . '<input type="hidden" name="theme_folder" value="' . \Core\Html::escape((string)$theme['folder']) . '">'
                    . '<button class="admin-button admin-button-primary" type="submit">'
                    . \Core\Html::escape($this->phrase('install', 'Install'))
                    . '</button></form>';
            } else {
                if ($theme['files_present']) {
                    $actions .= '<form method="post" action="' . $this->managerUrl('lifecycle') . '">'
                        . '<input type="hidden" name="operation" value="update">'
                        . '<input type="hidden" name="theme_folder" value="' . \Core\Html::escape((string)$theme['folder']) . '">'
                        . '<button class="admin-button admin-button-action" type="submit">'
                        . \Core\Html::escape($this->phrase('update', 'Update'))
                        . '</button></form>';
                }

                if ($theme['usage'] === []) {
                    $actions .= '<form method="post" action="' . $this->managerUrl('lifecycle') . '" '
                        . 'onsubmit="return confirm(' . $this->jsString($this->phrase('confirm_uninstall', 'Uninstall this theme?')) . ')">'
                        . '<input type="hidden" name="operation" value="uninstall">'
                        . '<input type="hidden" name="theme" value="' . \Core\Html::escape($theme['system_name']) . '">'
                        . '<button class="admin-button admin-button-danger" type="submit">'
                        . \Core\Html::escape($this->phrase('uninstall', 'Uninstall'))
                        . '</button></form>';
                }
            }

            $rows[] = [
                'template' => 'theme-row',
                'params' => [
                    'title' => \Core\Html::escape($theme['title']),
                    'system_name' => \Core\Html::escape($theme['system_name']),
                    'version' => \Core\Html::escape($theme['version'] !== '' ? $theme['version'] : '—'),
                    'status' => \Core\Html::escape($this->statusLabel($theme)),
                    'usage' => \Core\Html::escape($theme['usage'] !== [] ? implode(', ', $theme['usage']) : '—'),
                    'actions' => $actions !== '' ? $actions : '—',
                ],
            ];
        }

        return $this->render('themes', [
            'notice' => $notice,
            'theme_rows' => $rows,
        ]);
    }

    /**
     * @return list<array{
     *     system_name: string,
     *     folder: ?string,
     *     title: string,
     *     version: string,
     *     installed: bool,
     *     files_present: bool,
     *     usage: list<string>
     * }>
     */
    private function themeCatalog(): array
    {
        $installed = [];
        $usage = $this->themeUsage();
        $result = \DB::query(
            'SELECT theme_id, uuid, system_name, theme_version
             FROM themes'
        );

        while ($row = \DB::fetchRow($result)) {
            $installed[(string)$row['system_name']] = $row;
        }

        $catalog = [];
        $themesDirectory = ROOT_PATH . 'themes';

        foreach (scandir($themesDirectory) ?: [] as $folder) {
            if ($folder === '.' || $folder === '..') {
                continue;
            }

            $directory = $themesDirectory . '/' . $folder;
            $manifestPath = $directory . '/manifest.json';
            if (!is_dir($directory) || !is_file($manifestPath)) {
                continue;
            }

            try {
                $bundle = JsonTool::loadFile($manifestPath, true);
                if (($bundle['type'] ?? null) !== 'theme' || !is_array($bundle['data'] ?? null)) {
                    continue;
                }

                $manifest = $bundle['data'];
                $systemName = trim((string)($manifest['info']['name'] ?? ''));
                if ($systemName === '') {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            $row = $installed[$systemName] ?? null;
            $translation = $row ? (\Core\Translation::get((string)$row['uuid']) ?? []) : [];
            $themeId = $row ? (int)$row['theme_id'] : 0;

            $catalog[$systemName] = [
                'system_name' => $systemName,
                'folder' => $folder,
                'title' => (string)($translation['title']
                    ?? $manifest['info']['title']
                    ?? $systemName),
                'version' => trim((string)($manifest['info']['version']
                    ?? $row['theme_version']
                    ?? '')),
                'installed' => $row !== null,
                'files_present' => true,
                'usage' => $usage[$themeId] ?? [],
            ];

            unset($installed[$systemName]);
        }

        // Keep DB records visible when the corresponding theme directory is missing.
        foreach ($installed as $systemName => $row) {
            $translation = \Core\Translation::get((string)$row['uuid']) ?? [];
            $themeId = (int)$row['theme_id'];

            $catalog[$systemName] = [
                'system_name' => $systemName,
                'folder' => null,
                'title' => (string)($translation['title'] ?? $systemName),
                'version' => trim((string)($row['theme_version'] ?? '')),
                'installed' => true,
                'files_present' => false,
                'usage' => $usage[$themeId] ?? [],
            ];
        }

        return \Core\Translation::sortByTitle(array_values($catalog));
    }

    /**
     * Return domains that depend on each theme either directly or through page layouts.
     *
     * @return array<int, list<string>>
     */
    private function themeUsage(): array
    {
        $usage = [];
        $result = \DB::query(
            'SELECT DISTINCT used.theme_id, d.domain_name
             FROM (
                 SELECT theme_id, domain_id
                 FROM domains
                 UNION
                 SELECT l.theme_id, p.domain_id
                 FROM pages p
                 JOIN theme_layouts l ON l.layout_id=p.layout_id
             ) used
             JOIN domains d ON d.domain_id=used.domain_id
             ORDER BY d.domain_name'
        );

        while ($row = \DB::fetchRow($result)) {
            $themeId = (int)$row['theme_id'];
            $usage[$themeId][] = (string)$row['domain_name'];
        }

        return $usage;
    }

    private function statusLabel(array $theme): string
    {
        if (!$theme['installed']) {
            return $this->phrase('not_installed', 'Not installed');
        }
        if (!$theme['files_present']) {
            return $this->phrase('files_missing', 'Files missing');
        }
        return $this->phrase('installed', 'Installed');
    }

    private function validateReference(string $value): string
    {
        $value = trim($value);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $value)) {
            throw new \InvalidArgumentException('Invalid theme reference.');
        }
        return $value;
    }

    private function managerUrl(string $action = 'overview'): string
    {
        $url = \Core\Url::path((string) PAGE_SLUG);

        return $action === 'overview'
            ? $url
            : \Core\Url::pathParams(
                $url,
                ['action' => $action],
                $this->prefix
            );
    }

    private function notice(string $message, string $kind = 'success'): string
    {
        return '<div class="admin-notice admin-notice-' . \Core\Html::escape($kind) . '">'
            . \Core\Html::escape($message)
            . '</div>';
    }

    private function phrase(string $key, string $fallback): string
    {
        return (string)($this->phrases[$key] ?? $fallback);
    }

private function jsString(string $value): string
    {
        return \Core\Html::escape(\Core\Utils\JsonTool::encodeForHtml($value));
    }

    private function assertManagerAccess(): void
    {
        $user = defined('USERDATA') && is_array(USERDATA)
            ? USERDATA
            : \Core\User::getUser();
        $rootGroupId = (int)(GLOBAL_SETTINGS['usergroup_root'] ?? -1);

        if ((int)($user['usergroup_id'] ?? 0) !== $rootGroupId) {
            throw new \RuntimeException('ThemeManager access denied.');
        }
    }
}
