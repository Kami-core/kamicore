<?php
/**
 * KamiCore
 * SPDX-License-Identifier: Apache-2.0
 */
declare(strict_types=1);
namespace Plugins\SimpleSEO;
if (!defined('IN_KAMI')) die();

use Core\Content;
use Core\Request;
use Core\Response;
use Core\User;
use Core\Utils\JsonTool;

trait Manager
{
    private ?array $draft = null;
    private string $managerMessage = '';
    private string $previewHtml = '';

    private function t(string $key): string
    {
        return (string)($this->phrases[$key] ?? $key);
    }

    private function assertManage(): void
    {
        if (!User::canPlugin((int)$this->id, 'manage')) {
            throw new \RuntimeException('SEO access denied.');
        }
    }

    private function selection(): array
    {
        $section = (string)Request::param('section', $this->prefix, 'pages');
        if (!in_array($section, ['pages','types','schemas','general','sitemap'], true)) $section = 'pages';
        $id = (string)Request::param('id', $this->prefix, '');
        $copy = (string)Request::param('copy', $this->prefix, '');
        return [$section, $id, $copy];
    }

    private function managerUrl(string $section, string $id = '', string $action = 'manage', array $extra = []): string
    {
        $params = ['section'=>$section];
        if ($id !== '') $params['id'] = $id;
        $url = $this->actionUrl($action, array_replace($params, $extra));
        return (LANG === DOMAIN_CONFIG['default_language'] ? '' : '/' . LANG) . $url;
    }

    private function csrf(): string
    {
        $sessionId = \Core\Session::id();
        if (!$sessionId) throw new \RuntimeException('A session is required.');
        // Persist the token with the session so forms also work with caching disabled.
        $token = \DB::getOne("SELECT data->>'seo_csrf' FROM sessions WHERE domain_id=$1 AND session_id=$2", [DOMAIN_ID,$sessionId]);
        if (!is_string($token) || $token === '') {
            $candidate = bin2hex(random_bytes(32));
            $token = \DB::query("UPDATE sessions SET data=jsonb_set(COALESCE(data,'{}'::jsonb),'{seo_csrf}',
                to_jsonb(COALESCE(NULLIF(data->>'seo_csrf',''),$3::text))) WHERE domain_id=$1 AND session_id=$2 RETURNING data->>'seo_csrf' AS token",
                [DOMAIN_ID,$sessionId,$candidate]);
        }
        if (!is_string($token) || $token === '') throw new \RuntimeException('Unable to prepare form token.');
        return $token;
    }

    private function assertPost(): void
    {
        $this->assertManage();
        if (Request::method() !== 'POST'
            || !hash_equals($this->csrf(), (string)Request::input('seo_csrf', ''))) {
            throw new \RuntimeException('Invalid form token. Reload the page and try again.');
        }
    }

    public function manage(array $instanceParams = []): string
    {
        $this->assertManage();
        $this->addCss('/plugins/SimpleSEO/assets/manager.css');
        $this->addJs('/plugins/SimpleSEO/assets/manager.js');
        [$section, $id, $copy] = $this->selection();
        $tabs = '';
        foreach (['pages','types','schemas','general','sitemap'] as $tab) {
            $tabs .= '<a class="' . ($section === $tab ? 'is-active' : '') . '" href="' .
                self::escape($this->managerUrl($tab)) . '">' . self::escape($this->t($tab)) . '</a>';
        }
        $navigation = '';
        $editor = '';
        if ($section === 'general') {
            $editor = $this->generalEditor();
        } elseif ($section === 'sitemap') {
            $editor = $this->sitemapEditor();
        } else {
            $records = $this->managerRecords($section);
            if ($id === '' && $records !== []) $id = (string)array_key_first($records);
            $navigation = '<aside class="seo-sidebar"><label class="seo-search">' . self::escape($this->t('search')) .
                '<input type="search" data-seo-search placeholder="' . self::escape($this->t('search_hint')) . '"></label>';
            $navigation .= '<label>' . self::escape($this->t('filter')) . '<select data-seo-filter><option value="">' .
                self::escape($this->t('all')) . '</option><option value="configured">' . self::escape($this->t('configured')) .
                '</option><option value="default">' . self::escape($this->t('default')) . '</option></select></label>';
            if ($section === 'schemas') {
                $navigation .= '<a class="seo-button" href="' . self::escape($this->managerUrl('schemas','new')) . '">' .
                    self::escape($this->t('new_schema')) . '</a>';
            }
            $navigation .= '<p class="seo-muted"><span data-seo-count>' . count($records) . '</span> / ' . count($records) . '</p><nav class="seo-records">';
            foreach ($records as $key => $record) {
                $navigation .= '<a data-seo-record data-status="' . ($record['configured'] ? 'configured' : 'default') .
                    '" class="' . ((string)$key === $id ? 'is-active' : '') . '" href="' .
                    self::escape($this->managerUrl($section,(string)$key)) . '"><strong>' .
                    self::escape($record['title']) . '</strong><small>' . self::escape($record['subtitle']) . '</small><span>' .
                    self::escape($this->t($record['configured'] ? 'configured' : 'default')) . '</span></a>';
            }
            $navigation .= '</nav><p data-seo-empty hidden>' . self::escape($this->t('no_results')) . '</p></aside>';
            if ($section === 'schemas') {
                $editor = $this->schemaEditor($id, $copy);
            } elseif (isset($records[$id])) {
                $editor = $this->metadataEditor($section, (int)$id, $records[$id]);
            } else {
                $editor = '<p>' . self::escape($this->t('no_results')) . '</p>';
            }
        }
        $message = $this->managerMessage !== '' ? '<div class="seo-notice" role="status">' . self::escape($this->managerMessage) . '</div>' : '';
        return $this->render('manager', [
            'heading'=>self::escape($this->t('heading')),
            'domain'=>self::escape(DOMAIN_CONFIG['name_orig']),
            'tabs'=>$tabs, 'message'=>$message,
            'navigation'=>$navigation, 'editor'=>$editor . $this->previewHtml,
            'layout_class'=>in_array($section, ['general','sitemap'], true) ? 'seo-single' : '',
            'unsaved'=>self::escape($this->t('unsaved')),
        ]);
    }

    private function managerRecords(string $section): array
    {
        $records = [];
        if ($section === 'schemas') {
            foreach ($this->schemas() as $key => $schema) {
                $records[$key] = ['title'=>$schema['title'], 'subtitle'=>$key . ($schema['enabled'] ? '' : ' · ' . $this->t('disabled')),
                    'configured'=>$schema['customized']];
            }
            return $records;
        }
        if ($section === 'pages') {
            $rows = $this->rows('SELECT p.*, s.page_id IS NOT NULL AS configured FROM pages p
                LEFT JOIN seo_pages s USING(page_id) WHERE p.domain_id=$1 ORDER BY p.page_slug', [DOMAIN_ID]);
            foreach ($rows as $row) {
                $translation = \Core\Translation::get($row['uuid']) ?? [];
                $records[(string)$row['page_id']] = [
                    'title'=>(string)($translation['title'] ?? $row['system_name']),
                    'subtitle'=>'/' . trim($row['page_slug'],'/'), 'configured'=>(bool)$row['configured'],
                    'page'=>$row,
                ];
            }
        } else {
            $rows = $this->rows('SELECT c.*, s.ct_id IS NOT NULL AS configured FROM content_types c
                LEFT JOIN seo_types s ON s.ct_id=c.ct_id AND s.domain_id=$1 WHERE c.has_slug=true ORDER BY c.system_name', [DOMAIN_ID]);
            foreach ($rows as $row) {
                $type = Content::getContentType((int)$row['ct_id']);
                $records[(string)$row['ct_id']] = ['title'=>$type['title'],'subtitle'=>$row['system_name'],
                    'configured'=>(bool)$row['configured'],'type'=>$type];
            }
        }
        return $records;
    }

    private function formStart(string $section, string $id): string
    {
        return '<form method="post" data-seo-form action="' . self::escape($this->managerUrl($section,$id,'save')) . '">' .
            '<input type="hidden" name="seo_csrf" value="' . self::escape($this->csrf()) . '">';
    }

    private function field(string $name, string $label, string $value, bool $textarea = false, string $extra = ''): string
    {
        $control = $textarea
            ? '<textarea name="' . self::escape($name) . '" rows="3" ' . $extra . '>' . self::escape($value) . '</textarea>'
            : '<input type="text" name="' . self::escape($name) . '" value="' . self::escape($value) . '" ' . $extra . '>';
        return '<label class="seo-field"><span>' . self::escape($label) . '</span>' . $control . '</label>';
    }

    private function select(string $name, string $label, array $choices, string $selected): string
    {
        $html = '<label class="seo-field"><span>' . self::escape($label) . '</span><select name="' . self::escape($name) . '">';
        foreach ($choices as $value => $text) {
            $html .= '<option value="' . self::escape((string)$value) . '"' . ((string)$value === $selected ? ' selected' : '') . '>' .
                self::escape((string)$text) . '</option>';
        }
        return $html . '</select></label>';
    }

    private function languageFields(array $metadata, array $fields): string
    {
        $html = '<div class="seo-language-tabs" role="tablist">';
        foreach (DOMAIN_CONFIG['languages'] as $index => $language) {
            $html .= '<button type="button" role="tab" data-seo-language="' . self::escape($language) .
                '" aria-selected="' . ($language === LANG ? 'true' : 'false') . '">' . strtoupper($language) . '</button>';
        }
        $html .= '</div>';
        foreach (DOMAIN_CONFIG['languages'] as $index => $language) {
            $html .= '<div data-seo-language-panel="' . self::escape($language) . '"' . ($language !== LANG ? ' hidden' : '') . '>';
            foreach ($fields as $field => $label) {
                $html .= $this->field('metadata['.$language.']['.$field.']', $this->t($label),
                    (string)($metadata[$language][$field] ?? ''), str_contains($field,'description'), 'data-seo-insert-target');
            }
            $html .= '</div>';
        }
        return $html;
    }

    private function metadataEditor(string $section, int $id, array $record): string
    {
        $data = $this->draft ?? $this->record($section, $id);
        $defaultSchema = 'webpage';
        $html = '<h2>' . self::escape($record['title']) . '</h2><p class="seo-muted">' . self::escape($this->t('metadata_help')) . '</p>';
        $html .= $this->formStart($section,(string)$id);
        $html .= $this->languageFields($data['metadata'], [
            'title'=>'title','description'=>'description','image'=>'image',
            'og_title'=>'og_title','og_description'=>'og_description','canonical'=>'canonical',
        ]);
        $html .= '<div class="seo-grid">' . $this->select('robots',$this->t('robots'),[
            'index, follow'=>'index, follow','noindex, follow'=>'noindex, follow','noindex, nofollow'=>'noindex, nofollow',
        ],$data['options']['robots'] ?? 'index, follow');
        $html .= $this->select('og_type',$this->t('og_type'),['website'=>'website','article'=>'article'], $data['options']['og_type'] ?? ($section === 'types' ? 'article' : 'website')) . '</div>';
        $html .= '<fieldset><legend>' . self::escape($this->t('schemas')) . '</legend><div class="seo-schema-choices">';
        $selected = (array)($data['options']['schemas'] ?? [$defaultSchema]);
        // Keep existing Home assignments working while presenting the new WebPage model.
        $selected = array_values(array_unique(array_map(
            static fn(string $key): string => $key === 'home' ? 'webpage' : $key,
            array_filter($selected, 'is_string')
        )));
        foreach ($this->schemas() as $key => $schema) {
            $html .= '<label><input type="checkbox" name="schemas[]" value="' . self::escape($key) . '"' .
                (in_array($key,$selected,true) ? ' checked' : '') . '> ' . self::escape($schema['title']) .
                (!$schema['enabled'] ? ' (' . self::escape($this->t('disabled')) . ')' : '') . '</label>';
        }
        $html .= '</div></fieldset>';
        $fields = $section === 'types' ? array_keys($record['type']['schema']['fields'] ?? []) : [];
        $html .= $this->placeholderHelp($fields);
        $html .= $this->previewControls($section, $id);
        $html .= $this->formActions($section,(string)$id) . '</form>';
        return $html;
    }

    private function placeholderHelp(array $fields = []): string
    {
        $keys = array_values(array_unique(array_merge(['title','summary','description','page_title','site_name','site_url',
            'site_logo','canonical_url','language','image','item_id','item_slug','created_at','updated_at'], $fields)));
        $html = '<details class="seo-help"><summary>' . self::escape($this->t('placeholders')) . '</summary><p>' .
            self::escape($this->t('placeholder_help')) . '</p><div class="seo-tokens">';
        foreach ($keys as $key) {
            $token = '{{'.$key.'}}';
            $html .= '<button type="button" data-seo-token="' . self::escape($token) . '">' . self::escape($token) . '</button>';
        }
        return $html . '</div></details>';
    }

    private function previewControls(string $section, int $id = 0): string
    {
        $html = '<details class="seo-preview-controls"><summary>' . self::escape($this->t('preview_settings')) . '</summary>';
        $html .= $this->select('preview_language',$this->t('language'),array_combine(DOMAIN_CONFIG['languages'],DOMAIN_CONFIG['languages']),
            (string)Request::input('preview_language', LANG));
        if ($section !== 'pages') {
            $choices = [];
            foreach ($this->managerRecords('pages') as $pageId => $page) $choices[$pageId] = $page['title'] . ' — ' . $page['subtitle'];
            $html .= $this->select('preview_page',$this->t('preview_page'),$choices,(string)Request::input('preview_page',array_key_first($choices) ?? ''));
        }
        if ($section !== 'pages') {
            $items = $this->rows('SELECT item_id,item_slug FROM content_items WHERE item_slug IS NOT NULL AND item_slug<>\'\'' .
                ($section === 'types' ? ' AND ct_id=$1' : '') . ' ORDER BY item_id DESC LIMIT 100', $section === 'types' ? [$id] : []);
            $choices = [''=>'—'];
            foreach ($items as $item) $choices[$item['item_id']] = '#'.$item['item_id'].' · '.$item['item_slug'];
            $html .= $this->select('preview_item',$this->t('preview_item'),$choices,(string)Request::input('preview_item',''));
        }
        return $html . '<p class="seo-muted">' . self::escape($this->t('preview_help')) . '</p></details>';
    }

    private function formActions(string $section, string $id, bool $preview = true): string
    {
        $html = '<div class="seo-actions"><button type="submit" class="seo-primary">' . self::escape($this->t('save')) . '</button>';
        if ($preview) $html .= '<button type="submit" formaction="' . self::escape($this->managerUrl($section,$id,'preview')) . '">' . self::escape($this->t('preview')) . '</button>';
        return $html . '</div>';
    }

    private function schemaEditor(string $id, string $copy): string
    {
        $schemas = $this->schemas();
        if ($id !== 'new' && !isset($schemas[$id])) return '<p>' . self::escape($this->t('no_results')) . '</p>';
        $schema = $this->draft ?? ($id === 'new' ? ($schemas[$copy] ?? ['title'=>'','template'=>"{\n  \"@context\": \"https://schema.org\",\n  \"@type\": \"WebPage\",\n  \"name\": \"{{title}}\"\n}",'enabled'=>true]) : $schemas[$id]);
        $html = '<h2>' . self::escape($id === 'new' ? $this->t('new_schema') : $schema['title']) . '</h2>';
        $html .= $this->formStart('schemas',$id);
        $html .= $this->field('schema_key',$this->t('schema_key'),$id === 'new' ? (string)($schema['schema_key'] ?? '') : $id,
            false, $id !== 'new' ? 'readonly' : 'required pattern="[a-z][a-z0-9_-]*"');
        $html .= $this->field('schema_title',$this->t('title'),(string)$schema['title'],false,'required');
        $html .= '<label class="seo-check"><input type="checkbox" name="enabled" value="1"' . (!empty($schema['enabled']) ? ' checked' : '') . '> ' .
            self::escape($this->t('enabled')) . '</label>';
        $html .= '<p class="seo-muted">' . self::escape($this->t('schema_help')) . '</p>';
        $html .= $this->field('template','JSON-LD',(string)$schema['template'],true,'class="seo-code" spellcheck="false" data-seo-json data-seo-insert-target data-valid="' . self::escape($this->t('valid_json')) . '" data-invalid="' . self::escape($this->t('invalid_json')) . '"');
        $html .= '<p data-seo-json-status aria-live="polite"></p>';
        $allFields = [];
        foreach ($this->managerRecords('types') as $type) $allFields = array_merge($allFields,array_keys($type['type']['schema']['fields'] ?? []));
        $html .= $this->placeholderHelp($allFields);
        $html .= $this->previewControls('schemas');
        $html .= $this->formActions('schemas',$id);
        if ($id !== 'new') {
            $html .= '<div class="seo-secondary-actions"><a href="' . self::escape($this->managerUrl('schemas','new','manage',['copy'=>$id])) . '">' .
                self::escape($this->t('duplicate')) . '</a>';
            $action = !empty($schemas[$id]['preset']) ? 'resetSchema' : 'deleteSchema';
            $label = !empty($schemas[$id]['preset']) ? 'restore' : 'delete';
            $html .= '<button type="submit" formaction="' . self::escape($this->managerUrl('schemas',$id,$action)) .
                '" data-seo-confirm="' . self::escape($this->t('confirm_'.$label)) . '">' . self::escape($this->t($label)) . '</button></div>';
        }
        return $html . '</form>';
    }

    private function generalEditor(): string
    {
        $settings = $this->draft ?? $this->defaults();
        return '<h2>' . self::escape($this->t('general')) . '</h2>' . $this->formStart('general','') .
            $this->languageFields($settings['metadata'] ?? [], ['site_name'=>'site_name','title_format'=>'title_format']) .
            $this->field('image',$this->t('default_image'),(string)($settings['image'] ?? '')) .
            $this->field('site_logo',$this->t('site_logo'),(string)($settings['site_logo'] ?? '')) .
            $this->placeholderHelp() . $this->formActions('general','',false) . '</form>';
    }

    private function sitemapEditor(): string
    {
        $domainId = $this->sitemapDomainId();
        $domain = \DB::getRow('SELECT domain_id, domain_name, domain_config FROM domains WHERE domain_id=$1', [$domainId]);
        if (!$domain) throw new \RuntimeException('Domain not found.');

        $settings = $this->sitemapSettingsForDomain($domainId, (string)$domain['domain_name']);
        $domainOptions = '';
        foreach ($this->rows('SELECT domain_id, domain_name FROM domains ORDER BY domain_name') as $row) {
            $id = (int)$row['domain_id'];
            $domainOptions .= '<option value="' . $id . '" data-url="'
                . self::escape($this->managerUrl('sitemap', '', 'manage', ['domain'=>(string)$id])) . '"'
                . ($id === $domainId ? ' selected' : '') . '>'
                . self::escape((string)$row['domain_name']) . '</option>';
        }

        $forms = $this->plugins->get('Forms');
        if (!$forms instanceof \Plugins\Forms\Forms) {
            throw new \RuntimeException('Forms plugin is required by SimpleSEO sitemap management.');
        }
        $excludeField = $forms->renderField([
            'name' => 'exclude_languages',
            'type' => 'lang_code',
            'multiple' => true,
            'value' => $settings['exclude_languages'],
            'label' => $this->t('exclude_languages'),
            'description' => $this->t('exclude_languages_help'),
        ]);

        $outputFile = (string)$settings['output_file'];
        $status = $this->sitemapFileStatus($outputFile);
        $html = '<h2>' . self::escape($this->t('sitemap')) . '</h2>'
            . '<p class="seo-muted">' . self::escape($this->t('sitemap_help')) . '</p>'
            . '<label class="seo-field"><span>' . self::escape($this->t('domain')) . '</span>'
            . '<select data-seo-sitemap-domain>' . $domainOptions . '</select></label>'
            . '<form method="post" data-seo-form action="'
            . self::escape($this->managerUrl('sitemap', '', 'saveSitemap', ['domain'=>(string)$domainId])) . '">'
            . '<input type="hidden" name="seo_csrf" value="' . self::escape($this->csrf()) . '">'
            . '<input type="hidden" name="domain_id" value="' . $domainId . '">'
            . $excludeField
            . $this->field('output_file', $this->t('sitemap_output_file'), $outputFile)
            . '<p class="seo-muted">' . self::escape($this->t('sitemap_output_help')) . '</p>'
            . $status
            . '<div class="seo-actions"><button type="submit" class="seo-primary">'
            . self::escape($this->t('save')) . '</button>'
            . '<button type="submit" formaction="'
            . self::escape($this->managerUrl('sitemap', '', 'generateSitemapNow', ['domain'=>(string)$domainId])) . '">'
            . self::escape($this->t('generate_sitemap')) . '</button></div></form>';
        return $html;
    }

    private function sitemapDomainId(): int
    {
        $domainId = (int)Request::param('domain', $this->prefix, DOMAIN_ID);
        return $domainId > 0 && \DB::getOne('SELECT 1 FROM domains WHERE domain_id=$1', [$domainId])
            ? $domainId
            : DOMAIN_ID;
    }

    private function sitemapFileStatus(string $outputFile): string
    {
        try {
            $path = $this->normalizeSitemapOutputFile($outputFile);
        } catch (\Throwable) {
            return '';
        }
        $absolute = rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (!is_file($absolute)) {
            return '<p class="seo-muted">' . self::escape($this->t('sitemap_not_generated')) . '</p>';
        }
        return '<p class="seo-muted">' . self::escape(sprintf(
            $this->t('sitemap_file_status'),
            date('Y-m-d H:i:s', (int)filemtime($absolute)),
            number_format((int)filesize($absolute))
        )) . '</p>';
    }

    private function inputDraft(string $section, string $id): array
    {
        $post = Request::all();
        if ($section === 'schemas') {
            $key = $id === 'new' ? trim((string)($post['schema_key'] ?? '')) : $id;
            if (!preg_match('/^[a-z][a-z0-9_-]{0,79}$/', $key)) throw new \InvalidArgumentException($this->t('invalid_key'));
            $template = trim((string)($post['template'] ?? ''));
            if (strlen($template) > 100000) throw new \InvalidArgumentException('Template exceeds 100 KB.');
            Schema::validate($template);
            $title = trim((string)($post['schema_title'] ?? ''));
            if ($title === '') throw new \InvalidArgumentException($this->t('title_required'));
            return ['schema_key'=>$key,'title'=>$title,'template'=>$template,'enabled'=>!empty($post['enabled'])];
        }
        $metadata = [];
        $fields = $section === 'general' ? ['site_name','title_format'] : ['title','description','image','canonical','og_title','og_description'];
        foreach (DOMAIN_CONFIG['languages'] as $lang) {
            foreach ($fields as $field) {
                $value = $post['metadata'][$lang][$field] ?? '';
                if (!is_string($value) || strlen($value) > 10000) throw new \InvalidArgumentException('Invalid metadata value.');
                $metadata[$lang][$field] = trim($value);
            }
        }
        if ($section === 'general') return ['metadata'=>$metadata,'image'=>trim((string)($post['image'] ?? '')),'site_logo'=>trim((string)($post['site_logo'] ?? ''))];
        $robots = (string)($post['robots'] ?? 'index, follow');
        if (!in_array($robots,['index, follow','noindex, follow','noindex, nofollow'],true)) throw new \InvalidArgumentException('Invalid robots value.');
        $keys = $post['schemas'] ?? [];
        if (!is_array($keys) || count(array_filter($keys,'is_string')) !== count($keys)
            || array_diff($keys,array_keys($this->schemas()))) throw new \InvalidArgumentException('Unknown schema.');
        $ogType = (string)($post['og_type'] ?? 'website');
        if (!in_array($ogType,['website','article'],true)) throw new \InvalidArgumentException('Invalid Open Graph type.');
        return ['metadata'=>$metadata,'options'=>['robots'=>$robots,'og_type'=>$ogType,'schemas'=>array_values(array_unique($keys))]];
    }

    private function assertTarget(string $section, string $id): void
    {
        if ($section === 'pages' && !\DB::getOne('SELECT 1 FROM pages WHERE page_id=$1 AND domain_id=$2', [(int)$id, DOMAIN_ID])) {
            throw new \RuntimeException('Page not found in this domain.');
        }
        if ($section === 'types' && !\DB::getOne('SELECT 1 FROM content_types WHERE ct_id=$1 AND has_slug=true', [(int)$id])) {
            throw new \RuntimeException('Content type not found.');
        }
    }

    private function write(string $sql, array $params): void
    {
        if (\DB::query($sql,$params) === false) throw new \RuntimeException('Failed to save SEO data.');
    }

    public function save(array $instanceParams = []): string
    {
        $this->assertPost();
        [$section,$id] = $this->selection();
        try {
            $this->assertTarget($section,$id);
            $data = $this->inputDraft($section,$id);
            if ($section === 'schemas') {
                if ($id === 'new' && isset($this->schemas()[$data['schema_key']])) throw new \InvalidArgumentException($this->t('key_exists'));
                $this->write('INSERT INTO seo_schemas(domain_id,schema_key,title,template,enabled) VALUES($1,$2,$3,$4,$5)
                    ON CONFLICT(domain_id,schema_key) DO UPDATE SET title=EXCLUDED.title,template=EXCLUDED.template,enabled=EXCLUDED.enabled',
                    [DOMAIN_ID,$data['schema_key'],$data['title'],$data['template'],$data['enabled'] ? 'true':'false']);
                $id = $data['schema_key'];
            } elseif ($section === 'general') {
                // Preserve inactive languages and settings owned by other SEO sections.
                $current = $this->defaults();
                $data['metadata'] = array_replace($current['metadata'] ?? [], $data['metadata']);
                $settings = array_replace($current, $data);
                $this->write('INSERT INTO seo_domains(domain_id,settings) VALUES($1,$2::jsonb)
                    ON CONFLICT(domain_id) DO UPDATE SET settings=EXCLUDED.settings', [DOMAIN_ID,JsonTool::encode($settings,false)]);
                $this->domainSettings = $settings;
            } else {
                $data['metadata'] = array_replace($this->record($section,(int)$id)['metadata'],$data['metadata']);
                if ($section === 'pages') {
                    $this->write('INSERT INTO seo_pages(page_id,metadata,options) VALUES($1,$2::jsonb,$3::jsonb)
                        ON CONFLICT(page_id) DO UPDATE SET metadata=EXCLUDED.metadata,options=EXCLUDED.options',
                        [(int)$id,JsonTool::encode($data['metadata'],false),JsonTool::encode($data['options'],false)]);
                } else {
                    $this->write('INSERT INTO seo_types(domain_id,ct_id,metadata,options) VALUES($1,$2,$3::jsonb,$4::jsonb)
                        ON CONFLICT(domain_id,ct_id) DO UPDATE SET metadata=EXCLUDED.metadata,options=EXCLUDED.options',
                        [DOMAIN_ID,(int)$id,JsonTool::encode($data['metadata'],false),JsonTool::encode($data['options'],false)]);
                }
            }
        } catch (\Throwable $e) {
            return $this->formError($section,$e);
        }
        if ($notifications = $this->plugins->get('Notifications')) $notifications->store(User::getId() ?: null,$this->t('saved'),'success');
        return Response::seeOther($this->managerUrl($section,$id));
    }

    private function formError(string $section, \Throwable $error): string
    {
        $post = Request::all();
        $this->draft = $section === 'schemas'
            ? ['schema_key'=>(string)($post['schema_key'] ?? ''),'title'=>(string)($post['schema_title'] ?? ''),
               'template'=>(string)($post['template'] ?? ''),'enabled'=>!empty($post['enabled'])]
            : ['metadata'=>is_array($post['metadata'] ?? null) ? $post['metadata'] : [],
               'options'=>['robots'=>$post['robots'] ?? 'index, follow','og_type'=>$post['og_type'] ?? 'website','schemas'=>$post['schemas'] ?? []],
               'image'=>$post['image'] ?? '', 'site_logo'=>$post['site_logo'] ?? ''];
        $this->managerMessage = $error->getMessage();
        return $this->manage();
    }

    public function preview(array $instanceParams = []): string
    {
        $this->assertPost();
        [$section,$id] = $this->selection();
        try {
            $this->assertTarget($section,$id);
            $draft = $this->inputDraft($section,$id);
            $lang = (string)Request::input('preview_language',LANG);
            if (!in_array($lang,DOMAIN_CONFIG['languages'],true)) throw new \InvalidArgumentException('Unknown language.');
            $pageId = $section === 'pages' ? (int)$id : (int)Request::input('preview_page',0);
            $page = \DB::getRow('SELECT * FROM pages WHERE page_id=$1 AND domain_id=$2', [$pageId,DOMAIN_ID]);
            if (!$page) throw new \InvalidArgumentException('Select a page for preview.');
            $itemId = $section === 'pages' ? 0 : (int)Request::input('preview_item',0);
            $item = $itemId ? Content::getItem($itemId,$lang) : [];
            if ($item && !User::canContent((int)$item['ct_id'],'view')) throw new \RuntimeException('Content access denied.');
            if ($section === 'types' && (!$item || (int)$item['ct_id'] !== (int)$id)) throw new \InvalidArgumentException($this->t('select_item'));
            if ($section === 'schemas') {
                $this->schemaCache = $this->schemas();
                $this->schemaCache['_preview'] = array_replace($draft,['enabled'=>true]);
                $override = $this->record($item ? 'types' : 'pages', (int)($item['ct_id'] ?? $page['page_id']));
                $override['options']['schemas'] = ['_preview'];
            } else {
                $override = $draft;
            }
            $result = $this->build($page,$item,$lang,['_seo_preview'=>true],[], $override);
            $this->draft = $draft;
            $this->previewHtml = '<section class="seo-preview"><h2>' . self::escape($this->t('preview')) .
                '</h2><p class="seo-muted">' . self::escape($result['canonical']) . '</p><h3>' . self::escape($result['title']) .
                '</h3><p>' . self::escape($result['description']) . '</p>' .
                ($result['warnings'] ? '<p class="seo-notice">' . self::escape(implode("\n",$result['warnings'])) . '</p>' : '') .
                '<label>' . self::escape($this->t('generated')) . '<textarea readonly class="seo-code" rows="16">' .
                self::escape('<title>' . \Core\Html::escape($result['title']) . '</title>' . "\n" . $result['html']) . '</textarea></label></section>';
            unset($this->schemaCache['_preview']);
            return $this->manage();
        } catch (\Throwable $e) {
            return $this->formError($section,$e);
        }
    }

    private function sitemapInputSettings(): array
    {
        $post = Request::all();
        $domainId = (int)($post['domain_id'] ?? $this->sitemapDomainId());
        $domainName = (string)(\DB::getOne('SELECT domain_name FROM domains WHERE domain_id=$1', [$domainId]) ?? '');
        if ($domainId < 1 || $domainName === '') throw new \InvalidArgumentException('Unknown domain.');

        $exclude = $post['exclude_languages'] ?? [];
        $exclude = is_array($exclude) ? $exclude : [$exclude];
        $known = array_fill_keys(array_map('strval', \DB::getArr('SELECT lang_code FROM languages')), true);
        $exclude = array_values(array_unique(array_filter(array_map(
            static fn(mixed $language): string => strtolower(trim((string)$language)),
            $exclude
        ), static fn(string $language): bool => $language !== '' && isset($known[$language]))));

        $outputFile = trim((string)($post['output_file'] ?? ''));
        if ($outputFile === '') $outputFile = $this->defaultSitemapOutputFile($domainName);
        $outputFile = $this->normalizeSitemapOutputFile($outputFile);
        return [$domainId, ['exclude_languages'=>$exclude, 'output_file'=>$outputFile]];
    }

    private function persistSitemapSettings(int $domainId, array $sitemap): void
    {
        $settings = JsonTool::decodeArray(\DB::getOne(
            'SELECT settings FROM seo_domains WHERE domain_id=$1', [$domainId]
        ));
        $settings['sitemap'] = $sitemap;
        $this->write(
            'INSERT INTO seo_domains(domain_id,settings) VALUES($1,$2::jsonb)
             ON CONFLICT(domain_id) DO UPDATE SET settings=EXCLUDED.settings',
            [$domainId, JsonTool::encode($settings, false)]
        );
        if ($domainId === DOMAIN_ID) $this->domainSettings = $settings;
    }

    public function saveSitemap(array $instanceParams = []): string
    {
        $this->assertPost();
        try {
            [$domainId, $settings] = $this->sitemapInputSettings();
            $this->persistSitemapSettings($domainId, $settings);
            if ($notifications = $this->plugins->get('Notifications')) {
                $notifications->store(User::getId() ?: null, $this->t('sitemap_settings_saved'), 'success');
            }
            return Response::seeOther($this->managerUrl('sitemap', '', 'manage', ['domain'=>(string)$domainId]));
        } catch (\Throwable $error) {
            $this->managerMessage = $error->getMessage();
            return $this->manage();
        }
    }

    public function generateSitemapNow(array $instanceParams = []): string
    {
        $this->assertPost();
        try {
            [$domainId, $settings] = $this->sitemapInputSettings();
            $this->persistSitemapSettings($domainId, $settings);
            $result = $this->generateSitemap($domainId);
            $this->managerMessage = sprintf(
                $this->t('sitemap_generated'),
                (int)$result['urls'],
                (int)$result['files'],
                (string)$result['path']
            );
            if ($result['warnings'] !== []) {
                $this->managerMessage .= "\n" . implode("\n", $result['warnings']);
            }
        } catch (\Throwable $error) {
            $this->managerMessage = $error->getMessage();
        }
        return $this->manage();
    }

    public function deleteSchema(array $instanceParams = []): string
    {
        $this->assertPost();
        [$section,$id] = $this->selection();
        try {
            $schema = $this->schemas()[$id] ?? null;
            if ($section !== 'schemas' || !$schema || $schema['preset']) throw new \InvalidArgumentException('Only custom schemas can be deleted.');
            $used = \DB::getOne("SELECT 1 FROM seo_pages s JOIN pages p USING(page_id)
                WHERE p.domain_id=$1 AND s.options->'schemas' ? $2
                UNION ALL SELECT 1 FROM seo_types WHERE domain_id=$1 AND options->'schemas' ? $2 LIMIT 1", [DOMAIN_ID,$id]);
            if ($used) throw new \InvalidArgumentException($this->t('schema_in_use'));
            $this->write('DELETE FROM seo_schemas WHERE domain_id=$1 AND schema_key=$2',[DOMAIN_ID,$id]);
            return Response::seeOther($this->managerUrl('schemas'));
        } catch (\Throwable $e) {
            return $this->formError($section,$e);
        }
    }

    public function resetSchema(array $instanceParams = []): string
    {
        $this->assertPost();
        [$section,$id] = $this->selection();
        if ($section !== 'schemas' || empty($this->schemas()[$id]['preset'])) throw new \InvalidArgumentException('Unknown schema preset.');
        $this->write('DELETE FROM seo_schemas WHERE domain_id=$1 AND schema_key=$2',[DOMAIN_ID,$id]);
        return Response::seeOther($this->managerUrl('schemas',$id));
    }
}
