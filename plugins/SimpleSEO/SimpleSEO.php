<?php
/**
 * KamiCore
 * SPDX-License-Identifier: Apache-2.0
 */
declare(strict_types=1);
namespace Plugins\SimpleSEO;
if (!defined('IN_KAMI')) die();

use Core\Content;
use Core\Html;
use Core\Request;
use Core\Translation;
use Core\Utils\JsonTool;

final class SimpleSEO extends \Core\BasePlugin
{
    use Manager;
    use Sitemap;

    private bool $renderRequested = false;
    private ?array $schemaCache = null;
    private ?array $domainSettings = null;

    public function init(array $instanceParams = []): void
    {
        $this->renderRequested = true;
    }

    public function renderPlaceholder(array $instanceParams = []): string
    {
        return '{{seo-data}}';
    }

    public function finalize(array $layoutParams): void
    {
        if (!$this->renderRequested) return;
        $this->layoutParams['seo-data'] = '';
        $page = \DB::getRow('SELECT * FROM pages WHERE page_id=$1 AND domain_id=$2', [PAGE_ID, DOMAIN_ID]);
        if (!$page) return;
        $itemId = Request::routedItemId();
        $item = $itemId ? Content::getItem($itemId) : [];
        $breadcrumbs = [];
        if (($item || $page['page_slug'] !== '/') && ($plugin = $this->plugins->get('Breadcrumbs'))) {
            $breadcrumbs = $plugin->getBreadcrumbItems($layoutParams);
        }
        $result = $this->build($page, $item, LANG, $layoutParams, $breadcrumbs);
        $this->layoutParams['page_title'] = self::escape($result['title']);
        $this->layoutParams['seo-data'] = $result['html'];
        foreach ($result['errors'] as $warning) error_log('SimpleSEO: ' . $warning);
    }

    private function defaults(): array
    {
        return $this->domainSettings ??= JsonTool::decodeArray(
            \DB::getOne('SELECT settings FROM seo_domains WHERE domain_id=$1', [DOMAIN_ID])
        );
    }

    private function schemas(): array
    {
        if ($this->schemaCache !== null) return $this->schemaCache;
        $schemas = JsonTool::loadFile(__DIR__ . '/presets.json');
        foreach ($schemas as &$schema) {
            $schema['enabled'] = true;
            $schema['preset'] = true;
            $schema['customized'] = false;
        }
        unset($schema);
        foreach ($this->rows('SELECT * FROM seo_schemas WHERE domain_id=$1 ORDER BY title', [DOMAIN_ID]) as $row) {
            $key = $row['schema_key'];
            $schemas[$key] = array_replace($row, [
                'preset' => isset($schemas[$key]),
                'customized' => true,
                'enabled' => (bool)$row['enabled'],
            ]);
        }
        return $this->schemaCache = $schemas;
    }

    private function record(string $kind, int $id): array
    {
        if ($kind === 'pages') {
            $row = \DB::getRow('SELECT s.* FROM seo_pages s JOIN pages p USING(page_id)
                WHERE s.page_id=$1 AND p.domain_id=$2', [$id, DOMAIN_ID]);
        } else {
            $row = \DB::getRow('SELECT * FROM seo_types WHERE ct_id=$1 AND domain_id=$2', [$id, DOMAIN_ID]);
        }
        return ['metadata' => JsonTool::decodeArray($row['metadata'] ?? null),
            'options' => JsonTool::decodeArray($row['options'] ?? null)];
    }

    private function localized(array $values, string $lang): array
    {
        // An untranslated SEO override must not replace the current language's content.
        return is_array($values[$lang] ?? null) ? $values[$lang] : [];
    }

    private function build(array $page, array $item, string $lang, array $layout = [], array $breadcrumbs = [], ?array $override = null): array
    {
        $settings = $this->defaults();
        $site = $this->localized($settings['metadata'] ?? [], $lang);
        $record = $override ?? $this->record($item ? 'types' : 'pages', (int)($item['ct_id'] ?? $page['page_id']));
        $meta = $this->localized($record['metadata'], $lang);
        $options = $record['options'];
        $translation = Translation::get($page['uuid'], $lang) ?? [];
        $pageTitle = (string)($translation['title'] ?? $page['system_name']);
        $canonical = $this->canonical($page, $item, $lang, $layout);
        $data = is_array($item['data'] ?? null) ? $item['data'] : [];
        $values = array_replace($data, [
            'title' => (string)($item['title'] ?? $pageTitle),
            'summary' => $this->plain((string)($item['summary'] ?? $data['summary'] ?? '')),
            'description' => $this->plain((string)($item['summary'] ?? $data['summary'] ?? '')),
            'page_title' => $pageTitle,
            'site_name' => (trim((string)($site['site_name'] ?? '')) ?: DOMAIN_CONFIG['name_orig']),
            'site_logo' => $this->assetUrl((string)($settings['site_logo'] ?? '')),
            'site_url' => $this->origin() . '/',
            'canonical_url' => $canonical,
            'language' => $lang,
            'item_id' => $item['item_id'] ?? null,
            'item_slug' => $item['item_slug'] ?? '',
            'created_at' => $this->iso($item['created_at'] ?? null),
            'updated_at' => $this->iso($data['updated_at'] ?? $item['updated_at'] ?? null),
            'image' => '',
        ]);
        if (isset($data['published_at'])) $values['published_at'] = $this->iso($data['published_at']);
        $titleTemplate = trim((string)($meta['title'] ?? ''));
        if ($titleTemplate === '') $titleTemplate = (trim((string)($site['title_format'] ?? '')) ?: '{{title}}');
        $title = $this->plain(Schema::text($titleTemplate, $values));
        if ($title === '') $title = (string)$values['title'];
        $description = $this->plain(Schema::text((trim((string)($meta['description'] ?? '')) ?: '{{summary}}'), $values));
        $image = $this->assetUrl(Schema::text(trim((string)($meta['image'] ?? '')) ?: (string)($settings['image'] ?? ''), $values));
        $canonicalOverride = trim(Schema::text((string)($meta['canonical'] ?? ''), $values));
        if ($canonicalOverride !== '') {
            $canonical = $this->absolute($canonicalOverride, $lang) ?: $canonical;
        }
        $values = array_replace($values, ['title'=>$title, 'description'=>$description, 'image'=>$image, 'canonical_url'=>$canonical]);
        $robots = (string)($options['robots'] ?? 'index, follow');
        $html = '<link rel="canonical" href="' . self::escape($canonical) . '">' . "\n";
        if ($description !== '') $html .= $this->metaTag('description', $description);
        $html .= $this->metaTag('robots', $robots);
        $html .= $this->metaTag('og:title', Schema::text((string)($meta['og_title'] ?? '{{title}}'), $values) ?: $title, true);
        $html .= $this->metaTag('og:description', Schema::text((string)($meta['og_description'] ?? '{{description}}'), $values) ?: $description, true);
        $html .= $this->metaTag('og:url', $canonical, true);
        $html .= $this->metaTag('og:site_name', $values['site_name'], true);
        $html .= $this->metaTag('og:type', (string)($options['og_type'] ?? ($item ? 'article' : 'website')), true);
        if ($image !== '') $html .= $this->metaTag('og:image', $image, true);

        // Alternate links require a real translation for the displayed entity.
        foreach ($canonicalOverride === '' && empty($layout['canonical_url']) ? DOMAIN_CONFIG['languages'] : [] as $language) {
            $uuid = $item['item_uuid'] ?? $page['uuid'];
            if (!\DB::getOne('SELECT 1 FROM translations WHERE entity_uuid=$1 AND lang_code=$2', [$uuid, $language])) continue;
            $alternate = $this->canonical($page, $item, $language, $layout);
            $alternateMeta = $this->localized($record['metadata'], $language);
            if (!empty($alternateMeta['canonical'])) continue;
            $html .= '<link rel="alternate" hreflang="' . self::escape($language) . '" href="' . self::escape($alternate) . '">' . "\n";
        }

        $schemaKeys = $options['schemas'] ?? [$item ? 'webpage' : ($page['page_slug'] === '/' ? 'home' : 'webpage')];
        $graph = [];
        $warnings = [];
        $errors = [];
        $schemas = $this->schemas();
        foreach ($schemaKeys as $key) {
            if (!isset($schemas[$key]) || !$schemas[$key]['enabled']) continue;
            try {
                $node = Schema::render($schemas[$key]['template'], $values);
                if (isset($node['@graph'])) {
                    foreach ($node['@graph'] as $entry) if (is_array($entry)) $graph[] = $entry;
                } elseif ($node !== []) {
                    unset($node['@context']);
                    $graph[] = $node;
                }
                foreach (Schema::variables($schemas[$key]['template']) as $variable) {
                    if (!array_key_exists($variable, $values) || $values[$variable] === '' || $values[$variable] === null) {
                        $warnings[] = $key . ': empty value for ' . $variable;
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = $key . ': ' . $e->getMessage();
                $warnings[] = end($errors);
            }
        }
        $trail = [];
        foreach ($breadcrumbs as $crumb) {
            if (!is_array($crumb) || trim((string)($crumb['title'] ?? '')) === '') continue;
            $entry = ['@type'=>'ListItem', 'position'=>count($trail)+1, 'name'=>$this->plain((string)$crumb['title'])];
            if (!empty($crumb['link'])) $entry['item'] = $this->absolute((string)$crumb['link'], $lang);
            $trail[] = $entry;
        }
        if (count($trail) >= 2) $graph[] = ['@type'=>'BreadcrumbList', '@id'=>$canonical.'#breadcrumbs', 'itemListElement'=>$trail];
        $faq = [];
        foreach ((array)($layout['faqItems'] ?? []) as $entry) {
            if (!is_array($entry)) continue;
            $question = $this->plain((string)($entry['question'] ?? ''));
            $answer = $this->plain((string)($entry['answer'] ?? ''));
            if ($question === '' || $answer === '') continue;
            $faq[] = ['@type'=>'Question','name'=>$question,'acceptedAnswer'=>['@type'=>'Answer','text'=>$answer]];
        }
        if ($faq) $graph[] = ['@type'=>'FAQPage','@id'=>$canonical.'#faq','mainEntity'=>$faq];
        $items = [];
        foreach ((array)($layout['listItems'] ?? []) as $entry) {
            if (!is_array($entry)) continue;
            $url = $this->absolute((string)($entry['url'] ?? ''), $lang);
            if ($url === '') continue;
            $items[] = ['@type'=>'ListItem','position'=>count($items)+1,'url'=>$url,'name'=>$this->plain((string)($entry['name'] ?? ''))];
        }
        if ($items) $graph[] = ['@type'=>'ItemList','@id'=>$canonical.'#list','itemListElement'=>$items];
        // Connect page entities to automatic navigation/list nodes where applicable.
        foreach ($graph as &$node) {
            if (in_array($node['@type'] ?? '', ['WebPage','CollectionPage','AboutPage','ContactPage'], true)) {
                if (count($trail) >= 2) $node['breadcrumb'] ??= ['@id'=>$canonical.'#breadcrumbs'];
                if ($items && ($node['@type'] ?? '') === 'CollectionPage') $node['mainEntity'] ??= ['@id'=>$canonical.'#list'];
            }
        }
        unset($node);
        if ($graph) {
            $html .= '<script type="application/ld+json">' . Schema::scriptJson(['@context'=>'https://schema.org','@graph'=>$graph]) . '</script>';
        }
        return ['title'=>$title,'description'=>$description,'canonical'=>$canonical,'html'=>$html,'graph'=>$graph,'warnings'=>array_values(array_unique($warnings)), 'errors'=>$errors];
    }

    private function canonical(array $page, array $item, string $lang, array $layout): string
    {
        if (!empty($layout['canonical_url']) && ($custom = $this->absolute((string)$layout['canonical_url'], $lang)) !== '') return $custom;
        if ($item !== [] && ($viewerUrl = $this->contentCanonicalUrl($item, $lang)) !== null) {
            return $this->absolute($viewerUrl, $lang);
        }
        $path = Request::path();
        // Preview contexts use their selected page and item; live requests retain semantic pagination.
        if (empty($layout['_seo_preview'])) {
            $parts = explode('/', trim($path, '/'));
            if (in_array($parts[0] ?? '', DOMAIN_CONFIG['languages'], true)) array_shift($parts);
            $path = implode('/', $parts);
        } else {
            $path = trim((string)$page['page_slug'], '/');
            if ($item) $path = trim($path . '/' . rawurlencode((string)$item['item_slug']), '/');
        }
        return $this->absolute('/' . $path, $lang);
    }

    private function contentCanonicalUrl(array $item, string $lang): ?string
    {
        $contentTypeId = (int)($item['ct_id'] ?? 0);
        if ($contentTypeId < 1) return null;
        $viewerName = \DB::getOne(
            'SELECT p.system_name FROM content_types ct
             JOIN plugins p ON p.plugin_id=ct.canonical_viewer_plugin_id
             WHERE ct.ct_id=$1',
            [$contentTypeId]
        );
        if (!is_string($viewerName) || $viewerName === '') return null;
        $viewer = $this->plugins->getForDomain($viewerName, DOMAIN_ID);
        if (!$viewer || !method_exists($viewer, 'canonicalUrl')) return null;
        $groupId = defined('USERGROUP_ID')
            ? (int)USERGROUP_ID
            : (int)(GLOBAL_SETTINGS['usergroup_guest'] ?? 0);
        $url = $viewer->canonicalUrl($item, $lang, DOMAIN_ID, $groupId);
        return is_string($url) && $url !== '' ? $url : null;
    }

    private function origin(): string
    {
        return Request::scheme() . '://' . DOMAIN_CONFIG['name_orig'];
    }

    private function absolute(string $url, string $lang): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\x00-\x20]/', $url)) return '';
        if (preg_match('~^https?://~i', $url)) {
            return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : '';
        }
        if (str_starts_with($url, '//') || preg_match('~^[a-z][a-z0-9+.-]*:~i', $url)) return '';
        $parts = explode('/', ltrim($url, '/'));
        if (in_array($parts[0] ?? '', DOMAIN_CONFIG['languages'], true)) array_shift($parts);
        $prefix = $lang === DOMAIN_CONFIG['default_language'] ? '' : '/' . rawurlencode($lang);
        return $this->origin() . $prefix . '/' . implode('/', $parts);
    }

    private function assetUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\x00-\x20]/', $url)) return '';
        if (preg_match('~^https?://~i', $url)) return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
        if (str_starts_with($url, '//') || str_contains($url, ':')) return '';
        return $this->origin() . '/' . ltrim($url, '/');
    }

    private function iso(mixed $value): string
    {
        if (!is_scalar($value) || (string)$value === '') return '';
        try { return (new \DateTimeImmutable((string)$value, new \DateTimeZone('UTC')))->format(DATE_ATOM); }
        catch (\Throwable) { return ''; }
    }

    private function plain(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function metaTag(string $name, string $value, bool $property = false): string
    {
        if ($value === '') return '';
        return '<meta ' . ($property ? 'property' : 'name') . '="' . self::escape($name) . '" content="' . self::escape($value) . '">' . "\n";
    }

    private static function escape(string $value): string
    {
        // Values can pass through another Renderer call while assembling the full page.
        return str_replace(['{','}'], ['&#123;','&#125;'], Html::escape($value));
    }

    private function rows(string $sql, array $params = []): array
    {
        $rows = [];
        $result = \DB::query($sql, $params);
        while ($row = \DB::fetchRow($result)) $rows[] = $row;
        return $rows;
    }
}
