<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

/**
 * SVG sprite generator.
 *
 * Usage:
 *   php tools/build_icons.php
 *
 * Optional args:
 *   --src=/path/to/icons/src
 *   --out=/path/to/icons/sprite.svg
 *   --prefix=icon-
 *   --force-stroke=1
 *   --stroke-width=2
 *   --verbose=1
 *
 * Notes:
 * - Designed for simple UI icons (paths/groups).
 * - Avoid complex SVGs with external references, scripts, embedded images, etc.    / fill="none"
 */

final class IconsSpriteBuilder
{
    public function __construct(
        private string $srcDir,
        private string $outFile,
        private string $prefix = 'icon-',
        private bool $forceStroke = true,
        private float $strokeWidth = 2.0,
        private bool $verbose = false,
    ) {}

    public function build(): void
    {
        $files = $this->scanSvgFiles($this->srcDir);
        if (!$files) {
            $this->fail("No .svg files found in: {$this->srcDir}");
        }

        $symbols = [];
        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $id = $this->prefix . $this->slug($name);

            $raw = file_get_contents($file);
            if ($raw === false) {
                $this->fail("Cannot read file: {$file}");
            }

            [$viewBox, $inner] = $this->extractSvg($raw, $file);

            // Cleanup / normalize
            $inner = $this->stripDangerous($inner);
            $inner = $this->stripComments($inner);
            $inner = $this->stripRedundantWhitespace($inner);

            // Make coloring predictable:
            // - remove explicit fills
            // - optionally enforce stroke="currentColor" and fill="none"
            $inner = $this->removeAttrEverywhere($inner, 'fill');
            if ($this->forceStroke) {
                $inner = $this->removeAttrEverywhere($inner, 'stroke');
                $inner = $this->setDefaultStroke($inner, $this->strokeWidth);
            }

            $symbols[] = $this->makeSymbol($id, $viewBox, $inner);

            if ($this->verbose) {
                echo "OK: {$id} <= " . basename($file) . PHP_EOL;
            }
        }

        $sprite = $this->wrapSprite($symbols);

        $dir = dirname($this->outFile);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->fail("Cannot create output directory: {$dir}");
        }

        $tmp = $this->outFile . '.tmp';
        if (file_put_contents($tmp, $sprite) === false) {
            $this->fail("Cannot write temp file: {$tmp}");
        }

        // Atomic replace
        if (!rename($tmp, $this->outFile)) {
            @unlink($tmp);
            $this->fail("Cannot move temp sprite to: {$this->outFile}");
        }

        echo "Sprite generated: {$this->outFile}" . PHP_EOL;
        echo "Icons count: " . count($symbols) . PHP_EOL;
    }

    private function scanSvgFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            $this->fail("Source directory not found: {$dir}");
        }

        $result = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $info */
        foreach ($it as $info) {
            if (!$info->isFile()) {
                continue;
            }
            if (strtolower($info->getExtension()) !== 'svg') {
                continue;
            }
            $result[] = $info->getPathname();
        }

        sort($result);
        return $result;
    }

    /**
     * Extract viewBox and inner content from an SVG.
     * Returns: [viewBox, innerXml]
     */
    private function extractSvg(string $raw, string $file): array
    {
        // Remove XML/doctype headers early
        $raw = preg_replace('~<\?xml[^>]*\?>~i', '', $raw) ?? $raw;
        $raw = preg_replace('~<!doctype[^>]*>~i', '', $raw) ?? $raw;

        if (!preg_match('~<svg\b([^>]*)>(.*)</svg>~is', $raw, $m)) {
            $this->fail("Invalid SVG wrapper in file: {$file}");
        }

        $attrs = $m[1] ?? '';
        $inner = $m[2] ?? '';

        $viewBox = $this->getAttr($attrs, 'viewBox');
        if ($viewBox === null) {
            // Some icon packs provide width/height only. Try to build a viewBox.
            $w = $this->getAttr($attrs, 'width');
            $h = $this->getAttr($attrs, 'height');
            $wNum = $this->parseNumber($w);
            $hNum = $this->parseNumber($h);
            if ($wNum !== null && $hNum !== null && $wNum > 0 && $hNum > 0) {
                $viewBox = "0 0 {$wNum} {$hNum}";
            } else {
                // Reasonable default for UI icon sets (most are 24x24)
                $viewBox = "0 0 24 24";
            }
        }

        return [$viewBox, $inner];
    }

    private function getAttr(string $attrs, string $name): ?string
    {
        // Matches name="..." or name='...'
        $re = '~\b' . preg_quote($name, '~') . '\s*=\s*(["\'])(.*?)\1~i';
        if (!preg_match($re, $attrs, $m)) {
            return null;
        }
        $val = trim((string)($m[2] ?? ''));
        return $val !== '' ? $val : null;
    }

    private function parseNumber(?string $val): ?int
    {
        if ($val === null) {
            return null;
        }
        // width="24px" / "24" / "24.0"
        if (!preg_match('~^\s*([0-9]+(?:\.[0-9]+)?)~', $val, $m)) {
            return null;
        }
        return (int)round((float)$m[1]);
    }

    private function makeSymbol(string $id, string $viewBox, string $inner): string
    {
        $idEsc = htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $vbEsc = htmlspecialchars($viewBox, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Keep symbol content unescaped (it is XML)
        return "<symbol id=\"{$idEsc}\" viewBox=\"{$vbEsc}\">{$inner}</symbol>";
    }

    private function wrapSprite(array $symbols): string
    {
        $body = implode("\n", $symbols);

        // Hidden sprite. Keep it simple and valid.
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true" focusable="false">
{$body}
</svg>

SVG;
    }

    private function slug(string $name): string
    {
        $s = strtolower($name);
        $s = preg_replace('~[^a-z0-9]+~', '-', $s) ?? $s;
        $s = trim($s, '-');
        return $s !== '' ? $s : 'icon';
    }

    private function stripComments(string $xml): string
    {
        return preg_replace('~<!--.*?-->~s', '', $xml) ?? $xml;
    }

    private function stripRedundantWhitespace(string $xml): string
    {
        // Do not over-minify (keeps readability).
        $xml = preg_replace('~\s{2,}~', ' ', $xml) ?? $xml;
        $xml = preg_replace('~>\s+<~', '><', $xml) ?? $xml;
        return trim($xml);
    }

    private function stripDangerous(string $xml): string
    {
        // Remove scripts and foreignObject completely (security + predictability)
        $xml = preg_replace('~<script\b.*?</script>~is', '', $xml) ?? $xml;
        $xml = preg_replace('~<foreignObject\b.*?</foreignObject>~is', '', $xml) ?? $xml;

        // Remove event handlers (onclick, onload, etc.)
        $xml = preg_replace('~\son[a-z]+\s*=\s*(["\']).*?\1~is', '', $xml) ?? $xml;

        // Remove external hrefs (rare in icons, but can happen)
        $xml = preg_replace('~\s(?:xlink:href|href)\s*=\s*(["\'])\s*https?:.*?\1~is', '', $xml) ?? $xml;

        return $xml;
    }

    private function removeAttrEverywhere(string $xml, string $attrName): string
    {
        // Remove attrName="..." and attrName='...'
        $re = '~\s' . preg_quote($attrName, '~') . '\s*=\s*(["\']).*?\1~is';
        return preg_replace($re, '', $xml) ?? $xml;
    }

    private function setDefaultStroke(string $xml, float $strokeWidth): string
    {
        // Apply default attributes to common shape tags if missing.
        // This is a pragmatic approach for icon packs (paths/lines/circles/rects/polylines/polygons).
        $tags = '(path|line|polyline|polygon|circle|rect|ellipse)';
       $xml = preg_replace_callback(
			"~<{$tags}\b([^>]*?)(\s*/?)>~i",
			function (array $m) use ($strokeWidth): string {
				$tag = $m[1];
				$attrs = $m[2] ?? '';
				$closing = $m[3] ?? ''; // either "/" or ""

				// Normalize attrs: remove any trailing slash from attributes area
				$attrs = rtrim($attrs);
				if (str_ends_with($attrs, '/')) {
					$attrs = rtrim(substr($attrs, 0, -1));
				}

				$attrs = $this->ensureAttr($attrs, 'fill', 'none');
				$attrs = $this->ensureAttr($attrs, 'stroke', 'currentColor');
				$attrs = $this->ensureAttr($attrs, 'stroke-width', $this->formatFloat($strokeWidth));
				$attrs = $this->ensureAttr($attrs, 'stroke-linecap', 'round');
				$attrs = $this->ensureAttr($attrs, 'stroke-linejoin', 'round');

				// Rebuild tag with the correct closing style
				if (trim($closing) === '/') {
					return "<{$tag}{$attrs} />";
				}
				return "<{$tag}{$attrs}>";
			},
			$xml
		) ?? $xml;

        return $xml;
    }

    private function ensureAttr(string $attrs, string $name, string $value): string
    {
        // If already present, keep existing.
        $re = '~\b' . preg_quote($name, '~') . '\s*=\s*(["\']).*?\1~i';
        if (preg_match($re, $attrs)) {
            return $attrs;
        }

        $v = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return rtrim($attrs) . " {$name}=\"{$v}\"";
    }

    private function formatFloat(float $n): string
    {
        // Avoid "2.000000"
        $s = rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
        return $s === '' ? '0' : $s;
    }

    private function fail(string $message): never
    {
        fwrite(STDERR, "ERROR: {$message}\n");
        exit(1);
    }
}

/** ---- CLI bootstrap ---- */

$argvMap = [];
foreach ($argv as $arg) {
    if (!str_starts_with($arg, '--')) {
        continue;
    }
    $arg = substr($arg, 2);
    [$k, $v] = array_pad(explode('=', $arg, 2), 2, '1');
    $argvMap[$k] = $v;
}

$root = dirname(__DIR__); // assuming tools/ is in project root

$src = $argvMap['src'] ?? ('./src');
$out = $argvMap['out'] ?? ('./sprite.svg');
$prefix = $argvMap['prefix'] ?? 'icon-';
$forceStroke = ($argvMap['force-stroke'] ?? '1') === '1';
$strokeWidth = (float)($argvMap['stroke-width'] ?? '2');
$verbose = true; //'($argvMap['verbose'] ?? '0') === '1';

$builder = new IconsSpriteBuilder(
    srcDir: $src,
    outFile: $out,
    prefix: $prefix,
    forceStroke: $forceStroke,
    strokeWidth: $strokeWidth,
    verbose: $verbose
);

$builder->build();
