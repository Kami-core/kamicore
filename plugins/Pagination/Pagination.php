<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\Pagination;

if (!defined('IN_KAMI')) die();

final class Pagination extends \Core\BasePlugin
{
    public function renderPagination(
        int $page,
        int $perPage,
        int $total,
        ?string $base_url = null,
        array $options = []
    ): string {
        $total = max(0, $total);
        $perPage = max(1, $perPage);
        $pages = (int) ceil($total / $perPage);

        if ($pages <= 1) {
            return '';
        }

        $page = max(1, min($page, $pages));
        $window = max(0, (int)($options['window'] ?? 2));
        $pageParam = trim((string)($options['page_param'] ?? 'pagenum'));
        $template = trim((string)($options['template'] ?? 'pagination'));
        $showPrevNext = (bool)($options['show_prev_next'] ?? true);
        $showFirstLast = (bool)($options['show_first_last'] ?? true);

        if ($pageParam === '') {
            throw new \InvalidArgumentException('Pagination page parameter cannot be empty.');
        }
        if ($template === '') {
            throw new \InvalidArgumentException('Pagination template cannot be empty.');
        }

        $baseUrl = $this->normalizeBaseUrl(
            $base_url ?? ($_SERVER['REQUEST_URI'] ?? '/'),
            $pageParam
        );

        $items = [];

        if ($showPrevNext && $page > 1) {
            $items[] = $this->linkItem(
                $this->buildUrl($baseUrl, $pageParam, $page - 1),
                '‹',
                'pagination-prev'
            );
        }

        $pageNumbers = [];
        for ($i = max(1, $page - $window); $i <= min($pages, $page + $window); $i++) {
            $pageNumbers[$i] = true;
        }

        if ($showFirstLast) {
            $pageNumbers[1] = true;
            $pageNumbers[$pages] = true;
        }

        ksort($pageNumbers, SORT_NUMERIC);
        $previousPage = null;

        foreach (array_keys($pageNumbers) as $pageNumber) {
            $pageNumber = (int)$pageNumber;

            if ($previousPage !== null && $pageNumber > $previousPage + 1) {
                $items[] = [
                    'template' => 'pagination-ellipsis',
                    'params' => [],
                ];
            }

            if ($pageNumber === $page) {
                $items[] = [
                    'template' => 'pagination-current',
                    'params' => ['label' => (string)$pageNumber],
                ];
            } else {
                $items[] = $this->linkItem(
                    $this->buildUrl($baseUrl, $pageParam, $pageNumber),
                    (string)$pageNumber
                );
            }

            $previousPage = $pageNumber;
        }

        if ($showPrevNext && $page < $pages) {
            $items[] = $this->linkItem(
                $this->buildUrl($baseUrl, $pageParam, $page + 1),
                '›',
                'pagination-next'
            );
        }

        return $this->render($template, [
            'items' => $items,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'per_page' => $perPage,
        ]);
    }

    private function linkItem(string $url, string $label, string $class = ''): array
    {
        return [
            'template' => 'pagination-item',
            'params' => [
                'url' => \Core\Html::escape($url),
                'label' => \Core\Html::escape($label),
                'class' => $class,
            ],
        ];
    }

    private function normalizeBaseUrl(string $url, string $pageParam): string
    {
        $url = trim($url);
        if ($url === '') {
            $url = '/';
        }

        $parts = parse_url($url);
        if ($parts === false) {
            throw new \InvalidArgumentException('Invalid pagination base URL.');
        }

        $path = $this->removePathParam((string)($parts['path'] ?? ''), $pageParam);

        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string)$parts['query'], $query);
            unset($query[$pageParam]);
        }

        $normalized = '';
        if (isset($parts['scheme'])) {
            $normalized .= $parts['scheme'] . '://';
        }
        if (isset($parts['user'])) {
            $normalized .= $parts['user'];
            if (isset($parts['pass'])) {
                $normalized .= ':' . $parts['pass'];
            }
            $normalized .= '@';
        }
        if (isset($parts['host'])) {
            $normalized .= $parts['host'];
        }
        if (isset($parts['port'])) {
            $normalized .= ':' . $parts['port'];
        }

        $normalized .= $path !== '' ? $path : '/';

        if ($query !== []) {
            $normalized .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $normalized .= '#' . $parts['fragment'];
        }

        return $normalized;
    }

    private function removePathParam(string $path, string $pageParam): string
    {
        if ($path === '' || $path === '/') {
            return $path;
        }

        $leadingSlash = str_starts_with($path, '/');
        $trailingSlash = str_ends_with($path, '/');
        $segments = explode('/', trim($path, '/'));
        $result = [];

        for ($i = 0, $count = count($segments); $i < $count; $i++) {
            if ($segments[$i] === $pageParam) {
                $i++;
                continue;
            }
            $result[] = $segments[$i];
        }

        $path = implode('/', $result);
        if ($leadingSlash) {
            $path = '/' . $path;
        }
        if ($trailingSlash && $path !== '/') {
            $path .= '/';
        }

        return $path;
    }

    private function buildUrl(string $baseUrl, string $pageParam, int $page): string
    {
        if ($page <= 1) {
            return $baseUrl;
        }

        $fragment = '';
        $fragmentPos = strpos($baseUrl, '#');
        if ($fragmentPos !== false) {
            $fragment = substr($baseUrl, $fragmentPos);
            $baseUrl = substr($baseUrl, 0, $fragmentPos);
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl
            . $separator
            . rawurlencode($pageParam)
            . '='
            . $page
            . $fragment;
    }
}
