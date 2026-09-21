<?php
/**
 * KamiCore
 * SPDX-License-Identifier: Apache-2.0
 */
declare(strict_types=1);
namespace Plugins\SimpleSEO;
if (!defined('IN_KAMI')) die();

trait Sitemap
{
    private const SITEMAP_MAX_URLS = 50000;
    private const SITEMAP_MAX_BYTES = 45 * 1024 * 1024;
    private const SITEMAP_BATCH_SIZE = 1000;

    public function generateSitemap(int $domainId): array
    {
        $domain = \DB::getRow(
            'SELECT domain_id, domain_name, domain_config FROM domains WHERE domain_id=$1',
            [$domainId]
        );
        if (!$domain) {
            throw new \InvalidArgumentException("Unknown domain: {$domainId}.");
        }

        $domainConfig = \Core\Utils\JsonTool::decodeArray($domain['domain_config'] ?? null);
        $settings = $this->sitemapSettingsForDomain($domainId, (string)$domain['domain_name']);
        $languages = $this->sitemapLanguages($domainConfig, $settings);
        $guestGroupId = (int)(GLOBAL_SETTINGS['usergroup_guest'] ?? 0);
        if ($guestGroupId < 1) {
            throw new \RuntimeException('Guest user group is not configured.');
        }

        $outputFile = $this->normalizeSitemapOutputFile((string)$settings['output_file']);
        $origin = $this->sitemapOrigin($domainId, (string)$domain['domain_name'], $domainConfig);
        $warnings = [];
        $entries = $this->sitemapEntries($domainId, $languages, $guestGroupId, $origin, $warnings);
        $result = $this->writeSitemapFiles($outputFile, $origin, $entries);

        return array_replace($result, [
            'domain_id' => $domainId,
            'domain' => (string)$domain['domain_name'],
            'languages' => $languages,
            'warnings' => array_values(array_unique($warnings)),
        ]);
    }

    private function sitemapSettingsForDomain(int $domainId, ?string $domainName = null): array
    {
        if ($domainName === null) {
            $domainName = (string)(\DB::getOne(
                'SELECT domain_name FROM domains WHERE domain_id=$1',
                [$domainId]
            ) ?? '');
        }
        if ($domainName === '') {
            throw new \InvalidArgumentException("Unknown domain: {$domainId}.");
        }

        $settings = \Core\Utils\JsonTool::decodeArray(
            \DB::getOne('SELECT settings FROM seo_domains WHERE domain_id=$1', [$domainId])
        );
        $sitemap = is_array($settings['sitemap'] ?? null) ? $settings['sitemap'] : [];
        $exclude = is_array($sitemap['exclude_languages'] ?? null)
            ? $sitemap['exclude_languages']
            : [];
        $exclude = array_values(array_unique(array_filter(array_map(
            static fn(mixed $language): string => strtolower(trim((string)$language)),
            $exclude
        ))));
        $outputFile = trim((string)($sitemap['output_file'] ?? ''));
        if ($outputFile === '') {
            $outputFile = $this->defaultSitemapOutputFile($domainName);
        }

        return [
            'exclude_languages' => $exclude,
            'output_file' => $outputFile,
        ];
    }

    private function defaultSitemapOutputFile(string $domainName): string
    {
        $safeDomain = preg_replace('/[^A-Za-z0-9._-]+/', '_', $domainName) ?: 'domain';
        return 'sitemaps/' . $safeDomain . '/sitemap.xml';
    }

    private function sitemapLanguages(array $domainConfig, array $settings): array
    {
        $domainLanguages = is_array($domainConfig['languages'] ?? null)
            ? array_values(array_filter($domainConfig['languages'], 'is_string'))
            : [];
        if ($domainLanguages === []) {
            return [];
        }

        $active = array_fill_keys(array_map('strval', \DB::getArr(
            'SELECT lang_code FROM languages WHERE is_active=true'
        )), true);
        $excluded = array_fill_keys($settings['exclude_languages'] ?? [], true);

        return array_values(array_filter(
            $domainLanguages,
            static fn(string $language): bool => isset($active[$language])
                && !isset($excluded[strtolower($language)])
        ));
    }

    private function sitemapOrigin(int $domainId, string $domainName, array $domainConfig): string
    {
        $scheme = strtolower(trim((string)($domainConfig['scheme'] ?? 'https')));
        if (!in_array($scheme, ['http', 'https'], true)) $scheme = 'https';
        return $scheme . '://' . $domainName;
    }

    private function normalizeSitemapOutputFile(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Sitemap output file must be relative to the public root.');
        }
        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..'
                || !preg_match('/^[A-Za-z0-9._-]+$/', $segment)) {
                throw new \InvalidArgumentException('Sitemap output file contains an invalid path segment.');
            }
        }
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xml') {
            throw new \InvalidArgumentException('Sitemap output file must use the .xml extension.');
        }
        return implode('/', $segments);
    }

    private function sitemapEntries(
        int $domainId,
        array $languages,
        int $guestGroupId,
        string $origin,
        array &$warnings
    ): \Generator {
        yield from $this->sitemapPageEntries($domainId, $languages, $guestGroupId, $origin);
        yield from $this->sitemapContentEntries(
            $domainId,
            $languages,
            $guestGroupId,
            $origin,
            $warnings
        );
    }

    private function sitemapPageEntries(
        int $domainId,
        array $languages,
        int $guestGroupId,
        string $origin
    ): \Generator {
        if ($languages === []) return;

        $pages = \DB::query(
            'SELECT p.page_id, p.uuid, p.page_slug, s.options
             FROM pages p
             LEFT JOIN seo_pages s USING(page_id)
             WHERE p.domain_id=$1
             ORDER BY p.page_id',
            [$domainId]
        );
        while ($page = \DB::fetchRow($pages)) {
            if (!\Core\User::canPage((int)$page['page_id'], $guestGroupId)
                || $this->sitemapNoindex($page['options'] ?? null)) {
                continue;
            }

            foreach ($languages as $language) {
                $path = $this->sitemapPagePath(
                    (string)$page['page_slug'],
                    $language,
                    $this->sitemapDefaultLanguage($domainId)
                );
                yield ['loc' => $origin . $path, 'lastmod' => null];
            }
        }
    }

    private function sitemapContentEntries(
        int $domainId,
        array $languages,
        int $guestGroupId,
        string $origin,
        array &$warnings
    ): \Generator {
        if ($languages === []) return;

        $allowedTypeIds = \Core\User::getAllowedContentTypeIds('view', $guestGroupId);
        if ($allowedTypeIds === []) return;

        $types = \DB::query(
            'SELECT ct.ct_id, ct.system_name, viewer.system_name AS viewer_name, seo.options
             FROM content_types ct
             JOIN plugins viewer ON viewer.plugin_id=ct.canonical_viewer_plugin_id
             LEFT JOIN seo_types seo ON seo.ct_id=ct.ct_id AND seo.domain_id=$1
             WHERE ct.has_slug=true
               AND ct.ct_id=ANY($2::int[])
             ORDER BY ct.ct_id',
            [$domainId, \DB::prepareIdArray($allowedTypeIds)]
        );

        while ($type = \DB::fetchRow($types)) {
            if ($this->sitemapNoindex($type['options'] ?? null)) continue;

            $viewerName = (string)$type['viewer_name'];
            $viewer = $this->plugins->getForDomain($viewerName, $domainId);
            if (!$viewer || !method_exists($viewer, 'canonicalUrl')) {
                $warnings[] = "Content type {$type['system_name']} skipped: canonical viewer {$viewerName} is unavailable.";
                continue;
            }

            foreach ($languages as $language) {
                yield from $this->sitemapTypeLanguageEntries(
                    (int)$type['ct_id'],
                    $language,
                    $domainId,
                    $guestGroupId,
                    $origin,
                    $viewer
                );
            }
        }
    }

    private function sitemapTypeLanguageEntries(
        int $contentTypeId,
        string $language,
        int $domainId,
        int $guestGroupId,
        string $origin,
        object $viewer
    ): \Generator {
        $lastId = 0;
        while (true) {
            $defaultLanguage = $this->sitemapDefaultLanguage($domainId);
            $result = \DB::query(
                'SELECT ci.*, translation.translated_data
                 FROM content_items ci
                 LEFT JOIN LATERAL (
                     SELECT t.translated_data
                     FROM translations t
                     WHERE t.entity_uuid=ci.item_uuid
                       AND t.lang_code IN ($2, $3)
                     ORDER BY CASE WHEN t.lang_code=$2 THEN 0 ELSE 1 END
                     LIMIT 1
                 ) translation ON true
                 WHERE ci.ct_id=$1
                   AND ci.item_id>$4
                   AND ci.item_slug IS NOT NULL
                   AND ci.item_slug<>\'\'
                 ORDER BY ci.item_id
                 LIMIT $5::int',
                [$contentTypeId, $language, $defaultLanguage, $lastId, self::SITEMAP_BATCH_SIZE]
            );

            $count = 0;
            while ($row = \DB::fetchRow($result)) {
                $count++;
                $lastId = (int)$row['item_id'];
                $item = \Core\Content::prepareItem($row, $language);
                $path = $viewer->canonicalUrl($item, $language, $domainId, $guestGroupId);
                if (!is_string($path) || trim($path) === '') continue;
                $url = $this->sitemapAbsoluteUrl($path, $origin);
                if ($url === '') continue;
                yield [
                    'loc' => $url,
                    'lastmod' => $this->sitemapLastmod($row['updated_at'] ?? null),
                ];
            }
            if ($count < self::SITEMAP_BATCH_SIZE) break;
        }
    }

    private function sitemapNoindex(mixed $options): bool
    {
        $options = \Core\Utils\JsonTool::decodeArray($options);
        return str_starts_with(strtolower(trim((string)($options['robots'] ?? ''))), 'noindex');
    }

    private function sitemapDefaultLanguage(int $domainId): string
    {
        static $cache = [];
        if (!array_key_exists($domainId, $cache)) {
            $config = \Core\Utils\JsonTool::decodeArray(
                \DB::getOne('SELECT domain_config FROM domains WHERE domain_id=$1', [$domainId])
            );
            $cache[$domainId] = (string)($config['default_language'] ?? '');
        }
        return $cache[$domainId];
    }

    private function sitemapPagePath(string $pageSlug, string $language, string $defaultLanguage): string
    {
        $prefix = $language === $defaultLanguage ? '' : '/' . rawurlencode($language);
        $segments = array_values(array_filter(
            explode('/', trim($pageSlug, '/')),
            static fn(string $segment): bool => $segment !== ''
        ));
        $path = implode('/', array_map('rawurlencode', $segments));
        return $prefix . ($path !== '' ? '/' . $path : '/');
    }

    private function sitemapAbsoluteUrl(string $url, string $origin): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\x00-\x20]/', $url)) return '';
        if (preg_match('~^https?://~i', $url)) {
            return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : '';
        }
        if (!str_starts_with($url, '/') || str_starts_with($url, '//')) return '';
        return rtrim($origin, '/') . $url;
    }

    private function sitemapLastmod(mixed $value): ?string
    {
        if (!is_scalar($value) || trim((string)$value) === '') return null;
        try {
            return (new \DateTimeImmutable((string)$value, new \DateTimeZone('UTC')))
                ->format('Y-m-d\\TH:i:sP');
        } catch (\Throwable) {
            return null;
        }
    }

    private function sitemapXmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function sitemapUrlEntry(array $entry): string
    {
        $xml = "  <url>\n    <loc>" . $this->sitemapXmlEscape((string)$entry['loc']) . "</loc>\n";
        if (!empty($entry['lastmod'])) {
            $xml .= '    <lastmod>' . $this->sitemapXmlEscape((string)$entry['lastmod']) . "</lastmod>\n";
        }
        return $xml . "  </url>\n";
    }

    private function sitemapOutputPaths(string $outputFile): array
    {
        $root = realpath(ROOT_PATH);
        if ($root === false) {
            throw new \RuntimeException('Public root could not be resolved.');
        }
        $mainPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $outputFile);
        $directory = dirname($mainPath);
        if (!is_dir($directory)) {
            $this->sitemapCreateDirectory($root, $directory);
        }
        $realDirectory = realpath($directory);
        if ($realDirectory === false
            || ($realDirectory !== $root && !str_starts_with($realDirectory, $root . DIRECTORY_SEPARATOR))) {
            throw new \RuntimeException('Sitemap output directory is outside the public root.');
        }
        if (!is_writable($realDirectory)) {
            throw new \RuntimeException('Sitemap output directory is not writable.');
        }
        return [$root, $mainPath, $realDirectory];
    }

    private function sitemapCreateDirectory(string $root, string $directory): void
    {
        $relative = trim(substr($directory, strlen($root)), DIRECTORY_SEPARATOR);
        $current = $root;
        foreach ($relative === '' ? [] : explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
            $current .= DIRECTORY_SEPARATOR . $segment;
            if (is_dir($current)) continue;
            if (!mkdir($current, 0755) || !chmod($current, 0755)) {
                throw new \RuntimeException('Unable to create sitemap output directory.');
            }
        }
        if (!is_dir($directory)) {
            throw new \RuntimeException('Unable to create sitemap output directory.');
        }
    }

    private function sitemapChunkName(string $outputFile, int $index): string
    {
        $directory = dirname($outputFile);
        $base = pathinfo($outputFile, PATHINFO_FILENAME);
        $name = $base . '-' . sprintf('%04d', $index) . '.xml';
        return $directory === '.' ? $name : $directory . '/' . $name;
    }

    private function sitemapWriteAll($handle, string $data): void
    {
        $length = strlen($data);
        $written = 0;
        while ($written < $length) {
            $bytes = fwrite($handle, substr($data, $written));
            if ($bytes === false || $bytes === 0) {
                throw new \RuntimeException('Failed to write sitemap file.');
            }
            $written += $bytes;
        }
    }

    private function sitemapOpenChunk(string $directory, string $header): array
    {
        $temp = tempnam($directory, '.sitemap-');
        if ($temp === false) {
            throw new \RuntimeException('Unable to create sitemap temporary file.');
        }
        $handle = fopen($temp, 'wb');
        if ($handle === false) {
            @unlink($temp);
            throw new \RuntimeException('Unable to open sitemap temporary file.');
        }
        $this->sitemapWriteAll($handle, $header);
        return [
            'path' => $temp,
            'handle' => $handle,
            'count' => 0,
            'bytes' => strlen($header),
        ];
    }

    private function sitemapCloseChunk(array &$chunk, string $footer): void
    {
        $this->sitemapWriteAll($chunk['handle'], $footer);
        $chunk['bytes'] += strlen($footer);
        if (!fflush($chunk['handle'])) {
            fclose($chunk['handle']);
            throw new \RuntimeException('Failed to flush sitemap file.');
        }
        fclose($chunk['handle']);
        unset($chunk['handle']);
    }

    private function sitemapReplaceFile(string $temp, string $target): void
    {
        if (!chmod($temp, 0644)) {
            @unlink($temp);
            throw new \RuntimeException("Unable to set sitemap file permissions: {$target}.");
        }
        if (!@rename($temp, $target)) {
            @unlink($temp);
            throw new \RuntimeException("Unable to replace sitemap file: {$target}.");
        }
    }

    private function writeSitemapFiles(string $outputFile, string $origin, iterable $entries): array
    {
        [, $mainPath, $directory] = $this->sitemapOutputPaths($outputFile);
        $header = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        $footer = "</urlset>\n";
        $chunks = [];
        $open = null;
        $totalUrls = 0;

        try {
            foreach ($entries as $entry) {
                if (!is_array($entry) || empty($entry['loc'])) continue;
                $xml = $this->sitemapUrlEntry($entry);
                if (strlen($header) + strlen($xml) + strlen($footer) > self::SITEMAP_MAX_BYTES) {
                    throw new \RuntimeException('A single sitemap URL entry exceeds the file size limit.');
                }
                if ($open === null) {
                    $open = $this->sitemapOpenChunk($directory, $header);
                }
                if ($open['count'] > 0 && (
                    $open['count'] >= self::SITEMAP_MAX_URLS
                    || $open['bytes'] + strlen($xml) + strlen($footer) > self::SITEMAP_MAX_BYTES
                )) {
                    $this->sitemapCloseChunk($open, $footer);
                    $chunks[] = $open;
                    $open = $this->sitemapOpenChunk($directory, $header);
                }
                $this->sitemapWriteAll($open['handle'], $xml);
                $open['count']++;
                $open['bytes'] += strlen($xml);
                $totalUrls++;
            }

            if ($open === null) {
                $open = $this->sitemapOpenChunk($directory, $header);
            }
            $this->sitemapCloseChunk($open, $footer);
            $chunks[] = $open;
            $open = null;

            return $this->sitemapPublishChunks(
                $outputFile,
                $mainPath,
                $origin,
                $chunks,
                $totalUrls
            );
        } catch (\Throwable $error) {
            if (is_array($open) && isset($open['handle']) && is_resource($open['handle'])) {
                fclose($open['handle']);
            }
            if (is_array($open) && isset($open['path'])) @unlink($open['path']);
            foreach ($chunks as $chunk) {
                if (!empty($chunk['path'])) @unlink($chunk['path']);
            }
            throw $error;
        }
    }

    private function sitemapPublishChunks(
        string $outputFile,
        string $mainPath,
        string $origin,
        array $chunks,
        int $totalUrls
    ): array {
        $chunkCount = count($chunks);
        if ($chunkCount > self::SITEMAP_MAX_URLS) {
            throw new \RuntimeException('Sitemap index would exceed 50,000 child sitemaps.');
        }

        $mainUrl = rtrim($origin, '/') . '/' . $outputFile;
        if ($chunkCount === 1) {
            $this->sitemapReplaceFile((string)$chunks[0]['path'], $mainPath);
            $this->sitemapCleanupOldChunks($outputFile, []);
            return [
                'path' => $outputFile,
                'url' => $mainUrl,
                'urls' => $totalUrls,
                'files' => 1,
                'chunks' => 1,
                'index' => false,
                'bytes' => (int)filesize($mainPath),
            ];
        }

        $childFiles = [];
        $totalBytes = 0;
        foreach ($chunks as $index => $chunk) {
            $relative = $this->sitemapChunkName($outputFile, $index + 1);
            $target = realpath(dirname($mainPath)) . DIRECTORY_SEPARATOR . basename($relative);
            $this->sitemapReplaceFile((string)$chunk['path'], $target);
            $childFiles[] = $relative;
            $totalBytes += (int)filesize($target);
        }

        $indexTemp = tempnam(dirname($mainPath), '.sitemap-index-');
        if ($indexTemp === false) {
            throw new \RuntimeException('Unable to create sitemap index temporary file.');
        }
        $handle = fopen($indexTemp, 'wb');
        if ($handle === false) {
            @unlink($indexTemp);
            throw new \RuntimeException('Unable to open sitemap index temporary file.');
        }

        try {
            $generatedAt = gmdate('Y-m-d\\TH:i:s\\Z');
            $this->sitemapWriteAll($handle, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n");
            foreach ($childFiles as $childFile) {
                $childUrl = rtrim($origin, '/') . '/' . $childFile;
                $this->sitemapWriteAll($handle, "  <sitemap>\n    <loc>"
                    . $this->sitemapXmlEscape($childUrl) . "</loc>\n    <lastmod>"
                    . $generatedAt . "</lastmod>\n  </sitemap>\n");
            }
            $this->sitemapWriteAll($handle, "</sitemapindex>\n");
            if (!fflush($handle)) throw new \RuntimeException('Failed to flush sitemap index.');
            fclose($handle);
            $handle = null;
            $this->sitemapReplaceFile($indexTemp, $mainPath);
        } catch (\Throwable $error) {
            if (is_resource($handle)) fclose($handle);
            @unlink($indexTemp);
            throw $error;
        }

        $this->sitemapCleanupOldChunks($outputFile, $childFiles);
        $totalBytes += (int)filesize($mainPath);
        return [
            'path' => $outputFile,
            'url' => $mainUrl,
            'urls' => $totalUrls,
            'files' => $chunkCount + 1,
            'chunks' => $chunkCount,
            'index' => true,
            'bytes' => $totalBytes,
        ];
    }

    private function sitemapCleanupOldChunks(string $outputFile, array $keep): void
    {
        [, $mainPath, $directory] = $this->sitemapOutputPaths($outputFile);
        $stem = pathinfo($mainPath, PATHINFO_FILENAME);
        $keepNames = array_fill_keys(array_map('basename', $keep), true);
        foreach (glob($directory . DIRECTORY_SEPARATOR . $stem . '-*.xml') ?: [] as $file) {
            $name = basename($file);
            if (isset($keepNames[$name])) continue;
            if (preg_match('/^' . preg_quote($stem, '/') . '-\d{4,}\.xml$/', $name)) {
                @unlink($file);
            }
        }
    }
}
