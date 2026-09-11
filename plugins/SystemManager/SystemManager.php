<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\SystemManager;

use Core\Request;
use Core\SecretStore;
use Plugins\Forms\Forms;

if (!defined('IN_KAMI')) die();

final class SystemManager extends \Core\BasePlugin
{
    private ?Forms $forms = null;
    private ?array $declarations = null;

    public function overview(array $instanceParams = []): string
    {
        return $this->render('overview', [
            'settings_url' => $this->url('settings'),
            'languages_url' => $this->url('languages'),
            'domains_url' => $this->url('domains'),
            'secrets_url' => $this->url('secrets'),
        ]);
    }

    public function settings(array $instanceParams = []): string
    {
        return $this->renderSettings();
    }

    public function settingsSave(array $instanceParams = []): string
    {
        $request = Request::all();
        $values = is_array($request['settings'] ?? null) ? $request['settings'] : [];

        \DB::beginTransaction();
        try {
            foreach ($this->declarations() as $name => $declaration) {
                $scope = (string)($declaration['scope'] ?? '');
                if (!in_array($scope, ['global', 'global_domain'], true)) {
                    continue;
                }

                $raw = $values[$name] ?? null;
                $value = $this->normalizeSettingValue($name, $declaration, $raw);
                $stored = $this->encodeGlobalValue($value);

                $result = \DB::query(
                    'INSERT INTO global_settings(varname, value) VALUES($1, $2)
                     ON CONFLICT (varname) DO UPDATE SET value=EXCLUDED.value',
                    [$name, $stored]
                );
                if ($result === false) {
                    throw new \RuntimeException("Failed to save setting {$name}.");
                }
            }

            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit system settings.');
            }
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }

        \Cache::del('globals:settings');

        return $this->renderSettings(
            $this->notice($this->phrase('settings_saved', 'System settings saved.')),
            $values
        );
    }

    public function languages(array $instanceParams = []): string
    {
        return $this->renderLanguages();
    }

    public function languagesSave(array $instanceParams = []): string
    {
        $request = Request::all();
        $rawLanguages = $request['active_languages'] ?? [];
        $rawLanguages = is_array($rawLanguages) ? $rawLanguages : [$rawLanguages];

        $available = array_map(
            'strval',
            \DB::getArr('SELECT lang_code FROM languages ORDER BY lang_code')
        );
        $availableMap = array_fill_keys($available, true);
        $active = [];

        foreach ($rawLanguages as $rawLanguage) {
            $language = trim((string)$rawLanguage);
            if ($language === '') {
                continue;
            }
            if (!isset($availableMap[$language])) {
                throw new \InvalidArgumentException("Unknown language {$language}.");
            }
            $active[$language] = $language;
        }

        if ($active === []) {
            throw new \InvalidArgumentException('At least one system language must remain active.');
        }

        $usage = $this->domainLanguageUsage();
        $missingRequired = array_diff(array_keys($usage), array_keys($active));
        if ($missingRequired !== []) {
            $language = (string)reset($missingRequired);
            $domains = implode(', ', $usage[$language] ?? []);
            throw new \InvalidArgumentException(
                "Language {$language} is still used by: {$domains}."
            );
        }

        $params = array_values($active);
        $placeholders = implode(', ', array_map(
            static fn(int $index): string => '$' . ($index + 1),
            array_keys($params)
        ));

        $result = \DB::query(
            "UPDATE languages SET is_active = lang_code IN ({$placeholders})",
            $params
        );
        if ($result === false) {
            throw new \RuntimeException('Failed to update system languages.');
        }

        return $this->renderLanguages(
            $this->notice($this->phrase('languages_saved', 'System languages saved.'))
        );
    }

    public function domains(array $instanceParams = []): string
    {
        $rows = \DB::query(
            'SELECT d.domain_id, d.domain_name, d.is_root, d.domain_config,
                    t.system_name AS theme_name
             FROM domains d
             LEFT JOIN themes t USING(theme_id)
             ORDER BY d.is_root DESC NULLS LAST, d.domain_name'
        );

        $html = '';
        while ($row = \DB::fetchRow($rows)) {
            $config = $this->decodeJson($row['domain_config'] ?? null);
            $languages = is_array($config['languages'] ?? null)
                ? implode(', ', $config['languages'])
                : '';
            $rootBadge = !empty($row['is_root'])
                ? ' <span class="admin-page-description">(root)</span>'
                : '';

            $html .= $this->render('domain-row', [
                'domain_name' => $this->escape((string)$row['domain_name']),
                'theme' => $this->escape((string)($row['theme_name'] ?? '—')),
                'languages' => $this->escape($languages ?: '—'),
                'root_badge' => $rootBadge,
                'edit_url' => $this->url('domainEdit', ['domainId' => (int)$row['domain_id']]),
            ]);
        }

        return $this->render('domains', [
            'domain_rows' => $html,
            'create_url' => $this->url('domainEdit'),
            'back_url' => $this->url('overview'),
        ]);
    }

    public function domainEdit(array $instanceParams = []): string
    {
        $domainId = (int)$this->param('domainId', 0);

        return $this->renderDomainEditor($domainId);
    }

    public function domainSave(array $instanceParams = []): string
    {
        $request = Request::all();
        $domainId = (int)($request['domain_id'] ?? 0);
        $domainSettings = is_array($request['domain_settings'] ?? null)
            ? $request['domain_settings']
            : [];
        $overrides = is_array($request['overrides'] ?? null)
            ? $request['overrides']
            : [];

        $existing = $domainId > 0
            ? \DB::getRow('SELECT * FROM domains WHERE domain_id=$1', [$domainId])
            : null;

        if ($domainId > 0 && !$existing) {
            throw new \RuntimeException('Domain not found.');
        }

        $domainName = $this->normalizeHost((string)($request['domain_name'] ?? ''));
        $title = trim((string)($request['title'] ?? ''));
        $description = trim((string)($request['description'] ?? ''));
        $themeId = (int)($request['theme_id'] ?? 0);

        if (!\DB::getOne('SELECT 1 FROM themes WHERE theme_id=$1', [$themeId])) {
            throw new \InvalidArgumentException('Invalid theme.');
        }

        $collision = \DB::getOne(
            'SELECT 1 FROM domains WHERE domain_name=$1 AND ($2::int=0 OR domain_id<>$2) LIMIT 1',
            [$domainName, $domainId]
        );
        if ($collision) {
            throw new \InvalidArgumentException('Domain name is already in use.');
        }

        $aliasCollision = \DB::getOne(
            'SELECT 1 FROM domain_aliases WHERE alias_name=$1 AND ($2::int=0 OR domain_id<>$2) LIMIT 1',
            [$domainName, $domainId]
        );
        if ($aliasCollision) {
            throw new \InvalidArgumentException('Domain name is already used as an alias.');
        }

        $config = $existing ? $this->decodeJson($existing['domain_config'] ?? null) : [];

        foreach ($this->declarations() as $name => $declaration) {
            $scope = (string)($declaration['scope'] ?? '');
            if ($scope === 'domain') {
                $config[$name] = $this->normalizeSettingValue(
                    $name,
                    $declaration,
                    $domainSettings[$name] ?? null
                );
                continue;
            }

            if ($scope === 'global_domain') {
                if (!empty($overrides[$name])) {
                    $config[$name] = $this->normalizeSettingValue(
                        $name,
                        $declaration,
                        $domainSettings[$name] ?? null
                    );
                } else {
                    unset($config[$name]);
                }
            }
        }

        $languages = is_array($config['languages'] ?? null) ? $config['languages'] : [];
        $defaultLanguage = (string)($config['default_language'] ?? '');
        if ($languages === [] || !in_array($defaultLanguage, $languages, true)) {
            throw new \InvalidArgumentException('Default language must be enabled for the domain.');
        }

        $aliases = $this->normalizeAliases((string)($request['aliases'] ?? ''), $domainName);
        $this->assertAliasAvailability($aliases, $domainId);

        \DB::beginTransaction();
        try {
            $json = $this->json($config);

            if ($existing) {
                $result = \DB::query(
                    'UPDATE domains
                     SET domain_name=$1, title=$2, description=$3, theme_id=$4, domain_config=$5::jsonb
                     WHERE domain_id=$6',
                    [$domainName, $title, $description, $themeId, $json, $domainId]
                );
                if ($result === false) {
                    throw new \RuntimeException('Failed to update domain.');
                }
            } else {
                $created = \DB::query(
                    'INSERT INTO domains(domain_name, title, description, theme_id, domain_config, is_root)
                     VALUES($1, $2, $3, $4, $5::jsonb, false)
                     RETURNING domain_id',
                    [$domainName, $title, $description, $themeId, $json]
                );
                if (!is_array($created) || empty($created['domain_id'])) {
                    throw new \RuntimeException('Failed to create domain.');
                }
                $domainId = (int)$created['domain_id'];
            }

            \DB::query('DELETE FROM domain_aliases WHERE domain_id=$1', [$domainId]);
            foreach ($aliases as $alias) {
                $result = \DB::query(
                    'INSERT INTO domain_aliases(domain_id, alias_name) VALUES($1, $2)',
                    [$domainId, $alias]
                );
                if ($result === false) {
                    throw new \RuntimeException("Failed to save alias {$alias}.");
                }
            }

            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit domain changes.');
            }
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }

        \Cache::del('globals:domains');
        \Cache::del('d_' . $domainId . ':config');

        return $this->renderDomainEditor(
            $domainId,
            $this->notice($this->phrase('domain_saved', 'Domain saved.'))
        );
    }

    public function secrets(array $instanceParams = []): string
    {
        return $this->renderSecrets();
    }

    public function secretSave(array $instanceParams = []): string
    {
        $request = Request::all();
        $namespace = trim((string)($request['namespace'] ?? ''));
        $name = trim((string)($request['secret_name'] ?? ''));
        $value = (string)($request['secret_value'] ?? '');
        $domainId = $this->secretDomainId((string)($request['scope'] ?? 'global'));

        if ($value === '') {
            throw new \InvalidArgumentException('Secret value cannot be empty.');
        }
        if ($domainId !== null && !\DB::getOne('SELECT 1 FROM domains WHERE domain_id=$1', [$domainId])) {
            throw new \InvalidArgumentException('Invalid secret domain.');
        }

        SecretStore::set($namespace, $name, $value, $domainId);

        return $this->renderSecrets(
            $this->notice($this->phrase('secret_saved', 'Secret saved.'))
        );
    }

    public function secretDelete(array $instanceParams = []): string
    {
        $request = Request::all();
        $namespace = trim((string)($request['namespace'] ?? ''));
        $name = trim((string)($request['secret_name'] ?? ''));
        $domainRaw = (string)($request['domain_id'] ?? '');
        $domainId = $domainRaw === '' ? null : (int)$domainRaw;

        SecretStore::delete($namespace, $name, $domainId);

        return $this->renderSecrets(
            $this->notice($this->phrase('secret_deleted', 'Secret deleted.'))
        );
    }

    private function renderSettings(string $notice = '', ?array $submittedValues = null): string
    {
        $fields = '';
        foreach ($this->declarations() as $name => $declaration) {
            $scope = (string)($declaration['scope'] ?? '');
            if (!in_array($scope, ['global', 'global_domain'], true)) {
                continue;
            }

            $value = $submittedValues !== null && array_key_exists($name, $submittedValues)
                ? $submittedValues[$name]
                : (GLOBAL_SETTINGS[$name] ?? $declaration['default'] ?? null);
            $value = $this->decodeMultipleSettingValue($declaration, $value);

            $field = $this->settingField(
                $name,
                $declaration,
                $value,
                'settings[' . $name . ']'
            );

            $fields .= $this->render('setting-row', [
                'field' => $field,
                'description' => $this->escape($this->settingDescription($name, $declaration)),
            ]);
        }

        return $this->render('settings', [
            'settings_fields' => $fields,
            'save_url' => $this->url('settingsSave'),
            'back_url' => $this->url('overview'),
            'notice' => $notice,
        ]);
    }

    private function renderDomainEditor(int $domainId, string $notice = ''): string
    {
        $domain = $domainId > 0
            ? \DB::getRow('SELECT * FROM domains WHERE domain_id=$1', [$domainId])
            : null;

        if ($domainId > 0 && !$domain) {
            throw new \RuntimeException('Domain not found.');
        }

        $config = $domain ? $this->decodeJson($domain['domain_config'] ?? null) : [];
        $propertyFields = '';
        $propertyFields .= $this->render('setting-row', [
            'field' => $this->forms()->renderField([
                'name' => 'domain_name',
                'type' => 'string',
                'label' => $this->phrase('domain_name', 'Domain name'),
                'value' => (string)($domain['domain_name'] ?? ''),
                'required' => true,
            ]),
            'description' => '',
        ]);
        $propertyFields .= $this->render('setting-row', [
            'field' => $this->forms()->renderField([
                'name' => 'title',
                'type' => 'string',
                'label' => $this->phrase('title', 'Title'),
                'value' => (string)($domain['title'] ?? ''),
            ]),
            'description' => '',
        ]);
        $propertyFields .= $this->render('setting-row', [
            'field' => $this->forms()->renderField([
                'name' => 'description',
                'type' => 'textarea',
                'label' => $this->phrase('description', 'Description'),
                'value' => (string)($domain['description'] ?? ''),
            ]),
            'description' => '',
        ]);
        $propertyFields .= $this->render('setting-row', [
            'field' => $this->forms()->renderField([
                'name' => 'theme_id',
                'type' => 'select',
                'label' => $this->phrase('theme', 'Theme'),
                'value' => (int)($domain['theme_id'] ?? (DOMAIN_CONFIG['theme_id'] ?? 0)),
                'options' => $this->themeOptions(),
                'required' => true,
            ]),
            'description' => '',
        ]);

        $aliases = '';
        if ($domainId > 0) {
            $rows = \DB::query(
                'SELECT alias_name FROM domain_aliases WHERE domain_id=$1 ORDER BY alias_name',
                [$domainId]
            );
            $values = [];
            while ($row = \DB::fetchRow($rows)) {
                $values[] = (string)$row['alias_name'];
            }
            $aliases = implode("\n", $values);
        }
        $propertyFields .= $this->render('setting-row', [
            'field' => $this->forms()->renderField([
                'name' => 'aliases',
                'type' => 'textarea',
                'label' => $this->phrase('aliases', 'Aliases'),
                'value' => $aliases,
                'rows' => 4,
            ]),
            'description' => $this->escape($this->phrase('aliases_help', 'One hostname per line.')),
        ]);

        $domainFields = '';
        $overrideFields = '';
        foreach ($this->declarations() as $name => $declaration) {
            $scope = (string)($declaration['scope'] ?? '');
            if ($scope === 'domain') {
                $value = array_key_exists($name, $config)
                    ? $config[$name]
                    : ($declaration['default'] ?? null);
                $domainFields .= $this->render('setting-row', [
                    'field' => $this->settingField(
                        $name,
                        $declaration,
                        $value,
                        'domain_settings[' . $name . ']'
                    ),
                    'description' => $this->escape($this->settingDescription($name, $declaration)),
                ]);
                continue;
            }

            if ($scope === 'global_domain') {
                $hasOverride = array_key_exists($name, $config);
                $globalValue = GLOBAL_SETTINGS[$name] ?? $declaration['default'] ?? null;
                $value = $hasOverride ? $config[$name] : $globalValue;
                $overrideFields .= $this->render('override-row', [
                    'override_name' => $this->escape('overrides[' . $name . ']'),
                    'checked' => $hasOverride ? ' checked' : '',
                    'field' => $this->settingField(
                        $name,
                        $declaration,
                        $value,
                        'domain_settings[' . $name . ']',
                        (string)($declaration['type'] ?? '') === 'plugin_id' && $domainId > 0
                            ? ['domain_ids' => [$domainId]]
                            : []
                    ),
                    'description' => $this->escape($this->settingDescription($name, $declaration)),
                    'global_value' => $this->escape($this->displayValue($globalValue)),
                ]);
            }
        }

        return $this->render('domain-edit', [
            'page_title' => $this->escape(
                $domain
                    ? $this->phrase('edit_domain', 'Edit domain') . ': ' . (string)$domain['domain_name']
                    : $this->phrase('new_domain', 'New domain')
            ),
            'domain_id' => $domainId,
            'property_fields' => $propertyFields,
            'domain_fields' => $domainFields,
            'override_fields' => $overrideFields,
            'save_url' => $this->url('domainSave'),
            'back_url' => $this->url('domains'),
            'notice' => $notice,
        ]);
    }

    private function renderSecrets(string $notice = ''): string
    {
        $scopeOptions = [
            ['value' => 'global', 'label' => $this->phrase('global', 'Global')],
        ];
        $domains = \DB::query('SELECT domain_id, domain_name FROM domains ORDER BY domain_name');
        $domainNames = [];
        while ($domain = \DB::fetchRow($domains)) {
            $id = (int)$domain['domain_id'];
            $domainNames[$id] = (string)$domain['domain_name'];
            $scopeOptions[] = [
                'value' => 'domain:' . $id,
                'label' => (string)$domain['domain_name'],
            ];
        }

        $secretFields = $this->forms()->renderField([
            'name' => 'namespace',
            'type' => 'string',
            'label' => $this->phrase('namespace', 'Namespace'),
            'required' => true,
        ]);
        $secretFields .= $this->forms()->renderField([
            'name' => 'secret_name',
            'type' => 'string',
            'label' => $this->phrase('secret_name', 'Secret name'),
            'required' => true,
        ]);
        $secretFields .= $this->forms()->renderField([
            'name' => 'scope',
            'type' => 'select',
            'label' => $this->phrase('scope', 'Scope'),
            'value' => 'global',
            'options' => $scopeOptions,
        ]);
        $secretFields .= '<div class="form-field"><label for="sm-secret-value">'
            . $this->escape($this->phrase('secret_value', 'Secret value'))
            . '</label><textarea id="sm-secret-value" name="secret_value" required></textarea></div>';

        $rows = \DB::query(
            'SELECT namespace, secret_name, domain_id, updated_at
             FROM secrets
             ORDER BY namespace, secret_name, domain_id NULLS FIRST'
        );
        $secretRows = '';
        while ($row = \DB::fetchRow($rows)) {
            $domainId = $row['domain_id'] === null ? null : (int)$row['domain_id'];
            $scope = $domainId === null
                ? $this->phrase('global', 'Global')
                : ($domainNames[$domainId] ?? ('Domain #' . $domainId));

            $secretRows .= $this->render('secret-row', [
                'namespace' => $this->escape((string)$row['namespace']),
                'namespace_attr' => $this->escape((string)$row['namespace']),
                'secret_name' => $this->escape((string)$row['secret_name']),
                'secret_name_attr' => $this->escape((string)$row['secret_name']),
                'scope' => $this->escape($scope),
                'updated' => $this->escape((string)$row['updated_at']),
                'domain_id' => $domainId === null ? '' : (string)$domainId,
                'delete_url' => $this->url('secretDelete'),
                'confirm' => $this->escapeJs($this->phrase('confirm_delete_secret', 'Delete this stored secret?')),
            ]);
        }

        if ($secretRows === '') {
            $secretRows = '<tr><td colspan="5" class="admin-empty-state">'
                . $this->escape($this->phrase('no_secrets', 'No secrets stored.'))
                . '</td></tr>';
        }

        return $this->render('secrets', [
            'secret_fields' => $secretFields,
            'secret_rows' => $secretRows,
            'save_url' => $this->url('secretSave'),
            'back_url' => $this->url('overview'),
            'notice' => $notice,
        ]);
    }

    private function settingField(
        string $name,
        array $declaration,
        mixed $value,
        string $fieldName,
        array $params = []
    ): string {
        $type = (string)($declaration['type'] ?? 'string');
        $field = [
            'name' => $fieldName,
            'label' => $this->settingTitle($name, $declaration),
            'value' => $value,
        ];
        if ($params !== []) {
            $field['params'] = $params;
        }

        if (!empty($declaration['multiple'])) {
            $field['multiple'] = true;
        }

        if ($type === 'boolean') {
            $field['type'] = 'checkbox';
            return $this->forms()->renderField($field);
        }

        if ($type === 'integer') {
            $field['type'] = 'integer';
            $field['params'] = [];
            if (isset($declaration['min'])) {
                $field['params']['min'] = (int)$declaration['min'];
            }
            if (isset($declaration['max'])) {
                $field['params']['max'] = (int)$declaration['max'];
            }
            return $this->forms()->renderField($field);
        }

        if ($type === 'usergroup') {
            $field['type'] = 'select';
            $field['options'] = $this->usergroupOptions();
            return $this->forms()->renderField($field);
        }

        if ($type === 'plugin_id') {
            $field['type'] = 'plugin_id';
            return $this->forms()->renderField($field);
        }

        if ($type === 'language' || $type === 'languages') {
            $field['type'] = 'select';
            $field['options'] = $this->languageOptions();
            if ($type === 'languages') {
                $field['multiple'] = true;
            }
            return $this->forms()->renderField($field);
        }

        if ($type === 'textarea') {
            $field['type'] = 'textarea';
            if (isset($declaration['rows'])) {
                $field['params'] = ['rows' => (int)$declaration['rows']];
            }
            return $this->forms()->renderField($field);
        }

        if ($type === 'timezone') {
            $field['type'] = 'select';
            $field['options'] = array_map(
                static fn(string $timezone): array => ['value' => $timezone, 'label' => $timezone],
                \DateTimeZone::listIdentifiers()
            );
            return $this->forms()->renderField($field);
        }

        $field['type'] = 'string';
        return $this->forms()->renderField($field);
    }

    private function normalizeSettingValue(string $name, array $declaration, mixed $raw): mixed
    {
        $type = (string)($declaration['type'] ?? 'string');

        if ($type === 'plugin_id') {
            $values = !empty($declaration['multiple'])
                ? (is_array($raw) ? $raw : [$raw])
                : [$raw];
            $result = [];

            foreach ($values as $rawValue) {
                if (is_string($rawValue)) {
                    $rawValue = trim($rawValue);
                }
                if ($rawValue === '' || $rawValue === null) {
                    continue;
                }
                if (filter_var($rawValue, FILTER_VALIDATE_INT) === false || (int)$rawValue < 1) {
                    throw new \InvalidArgumentException("Invalid plugin ID for {$name}.");
                }

                $pluginId = (int)$rawValue;
                if (!\DB::getOne('SELECT 1 FROM plugins WHERE plugin_id=$1', [$pluginId])) {
                    throw new \InvalidArgumentException("Unknown plugin for {$name}.");
                }
                $result[$pluginId] = $pluginId;
            }

            $result = array_values($result);
            if (!empty($declaration['multiple'])) {
                return $result;
            }
            return $result[0] ?? null;
        }

        if (!empty($declaration['multiple'])) {
            $values = is_array($raw) ? $raw : [$raw];
            $result = [];
            foreach ($values as $value) {
                if (!is_scalar($value)) {
                    continue;
                }
                $value = trim((string)$value);
                if ($value === '') {
                    continue;
                }
                $result[] = $value;
            }
            return $result;
        }

        if ($type === 'boolean') {
            return in_array($raw, [true, 1, '1', 'true', 'on'], true);
        }

        if ($type === 'integer' || $type === 'usergroup') {
            if (is_string($raw)) {
                $raw = trim($raw);
            }
            if ($raw === '' || filter_var($raw, FILTER_VALIDATE_INT) === false) {
                throw new \InvalidArgumentException("Invalid integer value for {$name}.");
            }
            $value = (int)$raw;
            if (isset($declaration['min']) && $value < (int)$declaration['min']) {
                throw new \InvalidArgumentException("Value for {$name} is too small.");
            }
            if (isset($declaration['max']) && $value > (int)$declaration['max']) {
                throw new \InvalidArgumentException("Value for {$name} is too large.");
            }
            if ($type === 'usergroup' && !\DB::getOne(
                'SELECT 1 FROM usergroups WHERE usergroup_id=$1',
                [$value]
            )) {
                throw new \InvalidArgumentException("Unknown user group for {$name}.");
            }
            return $value;
        }

        if ($type === 'timezone') {
            $value = trim((string)$raw);
            if (!in_array($value, \DateTimeZone::listIdentifiers(), true)) {
                throw new \InvalidArgumentException("Unknown timezone for {$name}.");
            }
            return $value;
        }

        if ($type === 'language') {
            $value = trim((string)$raw);
            if (!$this->activeLanguageExists($value)) {
                throw new \InvalidArgumentException("Unknown active language for {$name}.");
            }
            return $value;
        }

        if ($type === 'languages') {
            $values = is_array($raw) ? $raw : [$raw];
            $result = [];
            foreach ($values as $value) {
                $language = trim((string)$value);
                if ($language === '') {
                    continue;
                }
                if (!$this->activeLanguageExists($language)) {
                    throw new \InvalidArgumentException("Unknown active language {$language}.");
                }
                $result[$language] = $language;
            }
            if ($result === []) {
                throw new \InvalidArgumentException('At least one domain language is required.');
            }
            return array_values($result);
        }

        return trim((string)$raw);
    }

    private function decodeMultipleSettingValue(array $declaration, mixed $value): mixed
    {
        if (empty($declaration['multiple']) || !is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $value;
    }

    private function usergroupOptions(): array
    {
        $rows = \DB::query(
            'SELECT usergroup_id, uuid, system_name FROM usergroups'
        );
        $options = [];
        while ($row = \DB::fetchRow($rows)) {
            $translation = getTranslation((string)$row['uuid']) ?? [];
            $title = (string)($translation['title'] ?? $row['system_name']);
            $options[] = [
                'value' => (int)$row['usergroup_id'],
                'label' => $title . ' (' . (string)$row['system_name'] . ')',
                'system_name' => (string)$row['system_name'],
            ];
        }
        return \Core\Translation::sortByTitle($options, 'label', 'system_name');
    }

    private function renderLanguages(string $notice = ''): string
    {
        $usage = $this->domainLanguageUsage();
        $rows = \DB::query(
            'SELECT lang_code, lang_name, is_active::int AS is_active
             FROM languages
             ORDER BY lang_name'
        );

        $languageRows = '';
        while ($row = \DB::fetchRow($rows)) {
            $code = (string)$row['lang_code'];
            $domains = $usage[$code] ?? [];
            $locked = $domains !== [];
            $active = (int)$row['is_active'] === 1 || $locked;
            $usageText = $locked ? implode(', ', $domains) : '—';

            $languageRows .= $this->render('language-row', [
                'language_name' => $this->escape((string)$row['lang_name']),
                'language_code' => $this->escape($code),
                'checked' => $active ? ' checked' : '',
                'disabled' => $locked ? ' disabled' : '',
                'required_value' => $locked
                    ? '<input type="hidden" name="active_languages[]" value="' . $this->escape($code) . '">'
                    : '',
                'used_by' => $this->escape($usageText),
            ]);
        }

        return $this->render('languages', [
            'language_rows' => $languageRows,
            'save_url' => $this->url('languagesSave'),
            'back_url' => $this->url('overview'),
            'notice' => $notice,
        ]);
    }

    /** @return array<string, array<int, string>> */
    private function domainLanguageUsage(): array
    {
        $usage = [];
        $rows = \DB::query(
            'SELECT domain_name, domain_config FROM domains ORDER BY domain_name'
        );

        while ($row = \DB::fetchRow($rows)) {
            $config = $this->decodeJson($row['domain_config'] ?? null);
            $languages = is_array($config['languages'] ?? null)
                ? $config['languages']
                : [];
            $defaultLanguage = trim((string)($config['default_language'] ?? ''));
            if ($defaultLanguage !== '') {
                $languages[] = $defaultLanguage;
            }

            foreach (array_unique(array_map('strval', $languages)) as $language) {
                $language = trim($language);
                if ($language === '') {
                    continue;
                }
                $usage[$language][(string)$row['domain_name']] = (string)$row['domain_name'];
            }
        }

        foreach ($usage as $language => $domains) {
            $usage[$language] = array_values($domains);
        }

        return $usage;
    }

    private function languageOptions(): array
    {
        $rows = \DB::query(
            'SELECT lang_code, lang_name FROM languages WHERE is_active ORDER BY lang_name'
        );
        $options = [];
        while ($row = \DB::fetchRow($rows)) {
            $options[] = [
                'value' => (string)$row['lang_code'],
                'label' => (string)$row['lang_name'] . ' (' . (string)$row['lang_code'] . ')',
            ];
        }
        return $options;
    }

    private function themeOptions(): array
    {
        $rows = \DB::query('SELECT theme_id, uuid, system_name FROM themes');
        $options = [];
        while ($row = \DB::fetchRow($rows)) {
            $translation = getTranslation((string)$row['uuid']) ?? [];
            $options[] = [
                'value' => (int)$row['theme_id'],
                'label' => (string)($translation['title'] ?? $row['system_name']),
                'system_name' => (string)$row['system_name'],
            ];
        }
        return \Core\Translation::sortByTitle($options, 'label', 'system_name');
    }

    private function activeLanguageExists(string $language): bool
    {
        return $language !== '' && (bool)\DB::getOne(
            'SELECT 1 FROM languages WHERE lang_code=$1 AND is_active LIMIT 1',
            [$language]
        );
    }

    private function normalizeAliases(string $raw, string $domainName): array
    {
        $aliases = [];
        foreach (preg_split('/\R+/', $raw) ?: [] as $value) {
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            $alias = $this->normalizeHost($value);
            if ($alias !== $domainName) {
                $aliases[$alias] = $alias;
            }
        }
        return array_values($aliases);
    }

    private function assertAliasAvailability(array $aliases, int $domainId): void
    {
        foreach ($aliases as $alias) {
            if (\DB::getOne(
                'SELECT 1 FROM domains WHERE domain_name=$1 AND ($2::int=0 OR domain_id<>$2) LIMIT 1',
                [$alias, $domainId]
            )) {
                throw new \InvalidArgumentException("Alias {$alias} is already a domain name.");
            }
            if (\DB::getOne(
                'SELECT 1 FROM domain_aliases WHERE alias_name=$1 AND ($2::int=0 OR domain_id<>$2) LIMIT 1',
                [$alias, $domainId]
            )) {
                throw new \InvalidArgumentException("Alias {$alias} is already in use.");
            }
        }
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(rtrim(trim($host), '.'));
        if (
            $host === ''
            || strlen($host) > 253
            || !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $host)
        ) {
            throw new \InvalidArgumentException('Invalid domain name.');
        }
        return $host;
    }

    private function secretDomainId(string $scope): ?int
    {
        if ($scope === 'global') {
            return null;
        }
        if (!preg_match('/^domain:(\d+)$/', $scope, $matches)) {
            throw new \InvalidArgumentException('Invalid secret scope.');
        }
        $domainId = (int)$matches[1];
        if ($domainId < 1) {
            throw new \InvalidArgumentException('Invalid secret domain.');
        }
        return $domainId;
    }

    private function declarations(): array
    {
        if ($this->declarations !== null) {
            return $this->declarations;
        }

        $path = __DIR__ . '/system_settings.json';
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Unable to read SystemManager settings declaration.');
        }

        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid SystemManager settings declaration.');
        }

        return $this->declarations = $decoded;
    }

    private function forms(): Forms
    {
        $plugin = $this->forms ??= $this->plugins->get('Forms');
        if (!$plugin instanceof Forms) {
            throw new \RuntimeException('Forms plugin is not available.');
        }
        return $plugin;
    }

    private function encodeGlobalValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            return $this->json($value);
        }
        return (string)$value;
    }

    private function displayValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }
        return (string)$value;
    }

    private function settingTitle(string $name, array $declaration): string
    {
        return $this->phrase(
            'setting_' . $name,
            (string)($declaration['title'] ?? $name)
        );
    }

    private function settingDescription(string $name, array $declaration): string
    {
        return $this->phrase(
            'setting_' . $name . '_help',
            (string)($declaration['description'] ?? '')
        );
    }

    private function phrase(string $key, string $fallback): string
    {
        return (string)($this->phrases[$key] ?? $fallback);
    }

    private function url(string $action, array $params = []): string
    {
        $url = '/' . PAGE_SLUG . '/' . $this->prefix . '-action/' . $action;
        foreach ($params as $key => $value) {
            $url .= '/' . $this->prefix . '-' . $key . '/' . rawurlencode((string)$value);
        }
        return $url;
    }

    private function notice(string $message, string $kind = 'success'): string
    {
        return '<div class="admin-notice admin-notice-' . $this->escape($kind) . '">'
            . $this->escape($message)
            . '</div>';
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapeJs(string $value): string
    {
        return str_replace(
            ["\\", "'", "\r", "\n", '</'],
            ['\\\\', "\\'", '\\r', '\\n', '<\\/'],
            $value
        );
    }
}
