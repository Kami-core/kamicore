<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

if(!IN_KAMI) die();

function getDomainPlugins(?int $domainId=null):array {
	$domainId ??= DOMAIN_ID;

	$domain_plugins =  Cache::get('d_'.$domainId.':plugins', 'ser');

	if(!isset($domain_plugins)) {
		$domain_plugins = [];
		$dps = DB::query("select * from plugin_domains
		left join plugins using(plugin_id)
		where domain_id=$1", [$domainId]);
		while ($dp = DB::fetchRow($dps)) {
			$domain_plugins[$dp['system_name']] = [
				'id' => $dp['plugin_id'],
				'uuid' => $dp['uuid'],
				'prefix' => $dp['plugin_prefix'],
				'config' =>  json_decode($dp['config'] ?? '{}'),
			];
		}

		Cache::set('d_'.$domainId.':plugins', $domain_plugins);
	}

	return $domain_plugins;
}

function getDomainPages(?int $domainId=null):array {
	$domainId ??= DOMAIN_ID;

	$domainPages = Cache::get('d_'.$domainId.':pages');

	if (!$domainPages) {
		$domainPages = [];
		$pages = DB::query("select page_id, page_slug from pages where domain_id=$1", [$domainId]);
		while($page = DB::fetchRow($pages)) {
			$domainPages[$page['page_slug']] = $page['page_id'];
			$domainPages[$page['page_id']] = $page['page_slug'];
		}

		Cache::set('d_'.$domainId.':pages', $domainPages);
	}

	return $domainPages;

}

function generateRandomHash(): string {
    $raw = uniqid('', true) . bin2hex(random_bytes(8));

    return substr(hash('sha256', $raw), 0, 32);
}

function generateSessionId(): string {
    return bin2hex(random_bytes(32));
}

function normalizeUAgent(?string $ua = null): string {
    if ($ua === null) {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    $ua = strtolower(trim($ua));

    // Remove volatile build/version numbers
    $patterns = [
        '/chrome\/\d+\.\d+\.\d+\.\d+/' => 'chrome',
        '/firefox\/\d+\.\d+/'          => 'firefox',
        '/safari\/\d+\.\d+/'           => 'safari',
        '/edg\/\d+\.\d+/'              => 'edge',
    ];

    foreach ($patterns as $regex => $replacement) {
        $ua = preg_replace($regex, $replacement, $ua);
    }

    // Collapse multiple spaces
    $ua = preg_replace('/\s+/', ' ', $ua);

    return trim($ua);
}

function js_redirect(string $url, ?string $message = null, string $class = 'uk-alert-primary'): string
{
    $msg = $message ? htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';

    if ($url === 'back') {
        $redirect = "history.back();";
    } else {
        $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $redirect = "window.location.href = '{$safeUrl}';";
    }

    $alertBlock = '';
    $delay = 0;

    if ($msg !== '') {
        $alertBlock = "
            const div = document.createElement('div');
            div.className = 'uk-alert {$class}';
            div.setAttribute('uk-alert', '');
            div.innerHTML = `<p>{$msg}</p>`;
            document.body.appendChild(div);
            setTimeout(() => { div.remove(); }, 2500);
        ";
        // Give alert a moment before redirecting
        $delay = 3000;
    } else {
    }

    return "<script>
        (function(){
            {$alertBlock}
            setTimeout(function(){
                {$redirect}
            }, {$delay});
        })();
    </script>";
}

function renderTemplate(string $template, ?string $plugin_name = null, array $params = [], bool $cacheable = false): string {
    // 1. Try the template cache first.
    $content = null;

    if (defined('USE_CACHE') && USE_CACHE) {
        $content = Cache::get('d_'.DOMAIN_ID.":tpls:$template");

    }

    // 2. Fall back to the filesystem.
    if (!$content) {
        $templatePath = findTemplateFile($template, $plugin_name);

        if (!$templatePath) {
            throw new Exception("Template '$template' not found in theme, plugins, or core.");
        }

        $content = file_get_contents($templatePath);

        // Cache the resolved template for subsequent renders.
        if (defined('USE_CACHE') && USE_CACHE) {
            Cache::set('d_'.DOMAIN_ID.":tpls:$template", $content);
        }
    }

    $phrases = array_merge($params['phrases'] ?? [], SYSTEM_DICTIONARY);

    // 3. Substitute parameters.
    foreach ($params as $key => $value) {
        if (is_array($value)) {
    
            if (array_is_list($value)) {
                $renderedItems = '';
                foreach ($value as $item) {
                    if (is_array($item) && isset($item['template'], $item['params'])) {
						$item['params']['phrases'] = $phrases;
                        $renderedItems .= renderTemplate($item['template'], $plugin_name, $item['params']);
                    }
                }
                $value = $renderedItems;
            }
            elseif (isset($value['template'], $value['params'])) {
				$value['params']['phrases'] = $phrases;
                $value = renderTemplate($value['template'], $plugin_name, $value['params']);
            }
        }

        if(isset($value) && !is_null($value) && !is_array($value)) $content = str_replace('{{' . $key . '}}', $value, $content);
    }

    foreach ($phrases as $key => $value) {
        if($value) $content = str_replace('{{phrase.' . $key . '}}', $value, $content);
    }

    // 4. Remove unresolved placeholders.
    $content = preg_replace('/{{\s*[\w]+\s*}}/', '', $content);

    return $content;
}

/**
 * Resolve a template file using the standard lookup order.
 */
function findTemplateFile(string $template, ?string $plugin_name = null): ?string {
    $paths = [];

		$theme_path = DOMAIN_CONFIG['theme_path'];

    if ($plugin_name) {
        $paths[] = ROOT_PATH . "themes/$theme_path/templates/$plugin_name/$template.tpl";
        $paths[] = ROOT_PATH . "plugins/$plugin_name/templates/$template.tpl";
		$paths[] = ROOT_PATH . "themes/$theme_path/templates/$template.tpl";
        $paths[] = ROOT_PATH . "core/templates/$template.tpl";
    } else {
        $paths[] = ROOT_PATH . "themes/$theme_path/templates/$template.tpl";
        $paths[] = ROOT_PATH . "core/templates/$template.tpl";
    }

    foreach ($paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }

    return null;
}

/**
 * Translation functions
 */

function getTranslation(string $uuid, ?string $lang=null, ?string $entity=null) {
	$data = \Core\Translation::get($uuid, $lang);

	if($data === null) return null;

	return ($entity !== null) ? ($data[$entity] ?? null) : $data;
}

function findTranslatables($data, ?array $names=null) {
	if(is_object($data)) $data = get_object_vars($data);
	if(!is_array($data)) return false;

	if(!$names) {
		$names = ['title', 'description'];
	}

	$arr = [];

	foreach($data as $k => $v) {
		if(is_array($v) || is_object($v)) {
			$arr[$k] = findTranslatables($v, $names);
			if(!$arr[$k]) unset($arr[$k]);
		} elseif(in_array($k, $names)) {
			$arr[$k] = $v;
		}
	}

	return $arr;
}

// System names in DB (content items/types, plugins, pages etc)
function normalizeName(string $string): string
{
    $string = trim($string);
    if ($string === '') {
        return 'n-a';
    }

    if (class_exists(\Normalizer::class)) {
        $string = \Normalizer::normalize($string, \Normalizer::FORM_KC) ?? $string;
    }

    $string = transliterate($string);

    $string = strtolower($string);

    $string = preg_replace('~[^a-z0-9]+~', '_', $string) ?? '';

    $string = trim($string, '_');
    $string = preg_replace('~_{2,}~', '_', $string) ?? $string;

    return $string !== '' ? $string : 'n-a';
}

function createSlug(string $string): string
{
    $string = trim($string);
    if ($string === '') {
        return 'n-a';
    }

    if (class_exists(\Normalizer::class)) {
        $string = \Normalizer::normalize($string, \Normalizer::FORM_KC) ?? $string;
    }

    $string = transliterate($string);

    $string = strtolower($string);

    $string = preg_replace('~[^a-z0-9]+~', '-', $string) ?? '';

    $string = trim($string, '-');
    $string = preg_replace('~-{2,}~', '-', $string) ?? $string;

    return $string !== '' ? $string : 'n-a';
}

/**
 * Transliterate Ukrainian/Russian Cyrillic (and common variants) to Latin.
 * Result is not guaranteed to be ASCII-clean; createSlug() will sanitize further.
 */
function transliterate(string $string): string
{
    // Unify apostrophes/quotes and remove separators that should not produce hyphens
    $string = str_replace(
        ["’", "ʼ", "`", "´", "ʹ", "ʾ", "“", "”", "«", "»"],
        ["'", "'", "'", "'", "'", "'", '"', '"', '"', '"'],
        $string
    );

    // Remove apostrophe and soft sign-like characters (they don't add sound in slug)
    $string = str_replace(["'", "’", "ʼ", "ь", "Ь", "ъ", "Ъ"], '', $string);

    // Ukrainian-focused mapping + common Cyrillic
    static $map = [
        // UA
        'А'=>'A','а'=>'a','Б'=>'B','б'=>'b','В'=>'V','в'=>'v','Г'=>'H','г'=>'h','Ґ'=>'G','ґ'=>'g',
        'Д'=>'D','д'=>'d','Е'=>'E','е'=>'e','Є'=>'Ye','є'=>'ie','Ж'=>'Zh','ж'=>'zh','З'=>'Z','з'=>'z',
        'И'=>'Y','и'=>'y','І'=>'I','і'=>'i','Ї'=>'Yi','ї'=>'i','Й'=>'Y','й'=>'y','К'=>'K','к'=>'k',
        'Л'=>'L','л'=>'l','М'=>'M','м'=>'m','Н'=>'N','н'=>'n','О'=>'O','о'=>'o','П'=>'P','п'=>'p',
        'Р'=>'R','р'=>'r','С'=>'S','с'=>'s','Т'=>'T','т'=>'t','У'=>'U','у'=>'u','Ф'=>'F','ф'=>'f',
        'Х'=>'Kh','х'=>'kh','Ц'=>'Ts','ц'=>'ts','Ч'=>'Ch','ч'=>'ch','Ш'=>'Sh','ш'=>'sh','Щ'=>'Shch','щ'=>'shch',
        'Ю'=>'Yu','ю'=>'yu','Я'=>'Ya','я'=>'ya',

        // RU extras (if you expect them)
        'Ё'=>'Yo','ё'=>'yo','Э'=>'E','э'=>'e',
        'Ы'=>'Y','ы'=>'y',
    ];

    $string = strtr($string, $map);

    // If something still non-ASCII remains (e.g., accented latin), try iconv if available
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        if (is_string($converted) && $converted !== '') {
            $string = $converted;
        }
    }

    return $string;
}

function formatHumanDate(int $year, $month = null, $day = null): string
{
    // Keep month names explicit and locale-independent.
    $months = [
        1  => 'January',
        2  => 'February',
        3  => 'March',
        4  => 'April',
        5  => 'May',
        6  => 'June',
        7  => 'July',
        8  => 'August',
        9  => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];

    if (!$month) {
        return (string)$year;
    }

    $month = (int)$month;

    if (!isset($months[$month])) {
        throw new InvalidArgumentException('Invalid month value - '.$month);
    }

    if (!$day) {
        return $months[$month] . ' ' . $year;
    }

    return $day . ' ' . $months[$month] . ' ' . $year;
}

function getCountryCode(string $country): ?string
{
    static $aliases = [

        // Abbreviations
        'USA' => 'United States',
        'U.S.A.' => 'United States',
        'UK' => 'United Kingdom',
        'UAE' => 'United Arab Emirates',

        // Alternative names
        'Czechia' => 'Czech Republic',
        'Republic of Ireland' => 'Ireland',
        'Ivory Coast' => "Côte d'Ivoire",
        "Cote d'Ivoire" => "Côte d'Ivoire",
        'Cape Verde' => 'Cabo Verde',
        'East Timor' => 'Timor-Leste',
        'Burma' => 'Myanmar',
        'Swaziland' => 'Eswatini',
        'Vatican' => 'Vatican City',

        // Congo variants
        'DR Congo' => 'Democratic Republic of the Congo',
        'Congo DR' => 'Democratic Republic of the Congo',
        'Congo-Kinshasa' => 'Democratic Republic of the Congo',

        'Republic of the Congo' => 'Congo',
        'Congo-Brazzaville' => 'Congo',

        // Korea variants
        'Korea Republic' => 'South Korea',
        'Republic of Korea' => 'South Korea',
        'Korea' => 'South Korea',

        'DPR Korea' => 'North Korea',
        'North Korea DPR' => 'North Korea',

        // Other official names
        'Palestinian Territories' => 'Palestine',
        'State of Palestine' => 'Palestine',
        'Iran, Islamic Republic of' => 'Iran',
        'Syrian Arab Republic' => 'Syria',
        'Russian Federation' => 'Russia',
        'Republic of Moldova' => 'Moldova',
        'Viet Nam' => 'Vietnam',
        'Bolivia (Plurinational State of)' => 'Bolivia',
        'Venezuela (Bolivarian Republic of)' => 'Venezuela',
        'United Republic of Tanzania' => 'Tanzania',
        'Lao PDR' => 'Laos',
        "Lao People's Democratic Republic" => 'Laos',
        'Brunei Darussalam' => 'Brunei',
        'Federated States of Micronesia' => 'Micronesia',
        'Macedonia' => 'North Macedonia',
        'Türkiye' => 'Turkey',

        // Football associations
        'England' => 'United Kingdom',
        'Scotland' => 'United Kingdom',
        'Wales' => 'United Kingdom',
        'Northern Ireland' => 'United Kingdom',
    ];

    static $codes = [

        'Afghanistan' => 'AF',
        'Albania' => 'AL',
        'Algeria' => 'DZ',
        'Andorra' => 'AD',
        'Angola' => 'AO',
        'Antigua and Barbuda' => 'AG',
        'Argentina' => 'AR',
        'Armenia' => 'AM',
        'Australia' => 'AU',
        'Austria' => 'AT',
        'Azerbaijan' => 'AZ',

        'Bahamas' => 'BS',
        'Bahrain' => 'BH',
        'Bangladesh' => 'BD',
        'Barbados' => 'BB',
        'Belarus' => 'BY',
        'Belgium' => 'BE',
        'Belize' => 'BZ',
        'Benin' => 'BJ',
        'Bhutan' => 'BT',
        'Bolivia' => 'BO',
        'Bosnia and Herzegovina' => 'BA',
        'Botswana' => 'BW',
        'Brazil' => 'BR',
        'Brunei' => 'BN',
        'Bulgaria' => 'BG',
        'Burkina Faso' => 'BF',
        'Burundi' => 'BI',

        'Cabo Verde' => 'CV',
        'Cambodia' => 'KH',
        'Cameroon' => 'CM',
        'Canada' => 'CA',
        'Central African Republic' => 'CF',
        'Chad' => 'TD',
        'Chile' => 'CL',
        'China' => 'CN',
        'Colombia' => 'CO',
        'Comoros' => 'KM',
        'Congo' => 'CG',
        "Côte d'Ivoire" => 'CI',
        'Costa Rica' => 'CR',
        'Croatia' => 'HR',
        'Cuba' => 'CU',
        'Cyprus' => 'CY',
        'Czech Republic' => 'CZ',

        'Democratic Republic of the Congo' => 'CD',
        'Denmark' => 'DK',
        'Djibouti' => 'DJ',
        'Dominica' => 'DM',
        'Dominican Republic' => 'DO',

        'Ecuador' => 'EC',
        'Egypt' => 'EG',
        'El Salvador' => 'SV',
        'Equatorial Guinea' => 'GQ',
        'Eritrea' => 'ER',
        'Estonia' => 'EE',
        'Eswatini' => 'SZ',
        'Ethiopia' => 'ET',

        'Fiji' => 'FJ',
        'Finland' => 'FI',
        'France' => 'FR',

        'Gabon' => 'GA',
        'Gambia' => 'GM',
        'Georgia' => 'GE',
        'Germany' => 'DE',
        'Ghana' => 'GH',
        'Greece' => 'GR',
        'Grenada' => 'GD',
        'Guatemala' => 'GT',
        'Guinea' => 'GN',
        'Guinea-Bissau' => 'GW',
        'Guyana' => 'GY',

        'Haiti' => 'HT',
        'Honduras' => 'HN',
        'Hungary' => 'HU',

        'Iceland' => 'IS',
        'India' => 'IN',
        'Indonesia' => 'ID',
        'Iran' => 'IR',
        'Iraq' => 'IQ',
        'Ireland' => 'IE',
        'Israel' => 'IL',
        'Italy' => 'IT',

        'Jamaica' => 'JM',
        'Japan' => 'JP',
        'Jordan' => 'JO',

        'Kazakhstan' => 'KZ',
        'Kenya' => 'KE',
        'Kiribati' => 'KI',
        'Kuwait' => 'KW',
        'Kyrgyzstan' => 'KG',

        'Laos' => 'LA',
        'Latvia' => 'LV',
        'Lebanon' => 'LB',
        'Lesotho' => 'LS',
        'Liberia' => 'LR',
        'Libya' => 'LY',
        'Liechtenstein' => 'LI',
        'Lithuania' => 'LT',
        'Luxembourg' => 'LU',

        'Madagascar' => 'MG',
        'Malawi' => 'MW',
        'Malaysia' => 'MY',
        'Maldives' => 'MV',
        'Mali' => 'ML',
        'Malta' => 'MT',
        'Marshall Islands' => 'MH',
        'Mauritania' => 'MR',
        'Mauritius' => 'MU',
        'Mexico' => 'MX',
        'Micronesia' => 'FM',
        'Moldova' => 'MD',
        'Monaco' => 'MC',
        'Mongolia' => 'MN',
        'Montenegro' => 'ME',
        'Morocco' => 'MA',
        'Mozambique' => 'MZ',
        'Myanmar' => 'MM',

        'Namibia' => 'NA',
        'Nauru' => 'NR',
        'Nepal' => 'NP',
        'Netherlands' => 'NL',
        'New Zealand' => 'NZ',
        'Nicaragua' => 'NI',
        'Niger' => 'NE',
        'Nigeria' => 'NG',
        'North Korea' => 'KP',
        'North Macedonia' => 'MK',
        'Norway' => 'NO',

        'Oman' => 'OM',

        'Pakistan' => 'PK',
        'Palau' => 'PW',
        'Palestine' => 'PS',
        'Panama' => 'PA',
        'Papua New Guinea' => 'PG',
        'Paraguay' => 'PY',
        'Peru' => 'PE',
        'Philippines' => 'PH',
        'Poland' => 'PL',
        'Portugal' => 'PT',

        'Qatar' => 'QA',

        'Romania' => 'RO',
        'Russia' => 'RU',
        'Rwanda' => 'RW',

        'Saint Kitts and Nevis' => 'KN',
        'Saint Lucia' => 'LC',
        'Saint Vincent and the Grenadines' => 'VC',
        'Samoa' => 'WS',
        'San Marino' => 'SM',
        'Sao Tome and Principe' => 'ST',
        'Saudi Arabia' => 'SA',
        'Senegal' => 'SN',
        'Serbia' => 'RS',
        'Seychelles' => 'SC',
        'Sierra Leone' => 'SL',
        'Singapore' => 'SG',
        'Slovakia' => 'SK',
        'Slovenia' => 'SI',
        'Solomon Islands' => 'SB',
        'Somalia' => 'SO',
        'South Africa' => 'ZA',
        'South Korea' => 'KR',
        'South Sudan' => 'SS',
        'Spain' => 'ES',
        'Sri Lanka' => 'LK',
        'Sudan' => 'SD',
        'Suriname' => 'SR',
        'Sweden' => 'SE',
        'Switzerland' => 'CH',
        'Syria' => 'SY',

        'Taiwan' => 'TW',
        'Tajikistan' => 'TJ',
        'Tanzania' => 'TZ',
        'Thailand' => 'TH',
        'Timor-Leste' => 'TL',
        'Togo' => 'TG',
        'Tonga' => 'TO',
        'Trinidad and Tobago' => 'TT',
        'Tunisia' => 'TN',
        'Turkey' => 'TR',
        'Turkmenistan' => 'TM',
        'Tuvalu' => 'TV',

        'Uganda' => 'UG',
        'Ukraine' => 'UA',
        'United Arab Emirates' => 'AE',
        'United Kingdom' => 'GB',
        'United States' => 'US',
        'Uruguay' => 'UY',
        'Uzbekistan' => 'UZ',

        'Vanuatu' => 'VU',
        'Vatican City' => 'VA',
        'Venezuela' => 'VE',
        'Vietnam' => 'VN',

        'Yemen' => 'YE',

        'Zambia' => 'ZM',
        'Zimbabwe' => 'ZW',
    ];

    $country = trim($country);

    $country = preg_replace('/\s+/', ' ', $country);

    $country = str_replace(['’', '`', '´'], "'", $country);

    $country = $aliases[$country] ?? $country;

    return $codes[$country] ?? null;
}

/**
 * Read an installation-wide setting.
 */
function global_settings(string $name, mixed $default = null): mixed
{
    return \Core\Settings::global($name, $default);
}

/**
 * Read a domain-only setting without global fallback.
 */
function domain_settings(string $name, mixed $default = null): mixed
{
    return \Core\Settings::domain($name, $default);
}

/**
 * Read an effective setting: domain override first, then global value.
 */
function system_settings(string $name, mixed $default = null): mixed
{
    return \Core\Settings::get($name, $default);
}
