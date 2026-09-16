# PluginManager — узгоджене ТЗ

## 0. Базове керування плагінами

PluginManager насамперед виконує стандартні задачі керування життєвим циклом плагінів.

Мінімальний UI повинен підтримувати:

- перегляд установлених/доступних плагінів;
- встановлення плагіна;
- активацію/деактивацію плагіна для одного або кількох доменів;
- перегляд поточної версії;
- оновлення;
- uninstall;
- перехід до setup presets, якщо плагін їх має;
- редагування settings плагіна.

### Domain activation

Плагін може бути встановлений у системі, але активований лише для вибраних доменів.

Активація/деактивація використовує наявну модель:

```text
plugins
plugin_domains
```

PluginManager не створює для цього окремого механізму.

### Settings UI

PluginManager повинен рендерити форму settings згідно з декларацією плагіна.

Setting із:

```json
"is_global": true
```

редагує глобальне значення.

Non-global setting може мати доменне значення.

Зберігання:

```text
plugins.settings
    global setting → actual value
    local setting  → fallback/default value

plugin_domains.local_settings
    domain-specific value
```

Для non-global setting:

```text
plugin_domains.local_settings
→ plugins.settings fallback
```

PluginManager використовує штатний Forms і наявний механізм plugin settings, не створюючи власної системи конфігурації.

Install/update/uninstall виконуються через ExtensionManager/System. PluginManager відповідає за UI, перевірки, попередження та запуск відповідної системної операції, але не дублює низькорівневу логіку встановлення extension.

---

## 1. Загальний принцип

PluginManager — опціональний плагін, який автоматизує дії, доступні вручну через PageManager та інші системні плагіни.

Видалення PluginManager не повинно обмежувати базову функціональність Kami.

Setup складається з двох незалежних частин:

- **Recipe PageManager-а** — описує, як у конкретному сайті заведено створювати певний клас сторінок.
- **Setup preset плагіна** — описує, які сторінки та instances потрібні самому плагіну, не знаючи структури конкретного сайту.

## 2. Page recipes

Recipes належать PageManager-у, зберігаються в `pgm_recipes`, редагуються через PageManager і можуть створюватися/видалятися незалежно від PluginManager.

PluginManager не читає таблицю recipes напряму. Він отримує recipe та його resolved state через публічний API PageManager (`getRecipe()`, `getRecipes()`, `resolveRecipe()`).

Базові recipe keys:

- `public-home`
- `public-page`
- `user-area`
- `staff-area`
- `admin`

Recipe key є строгим контрактом. PluginManager нічого не вгадує.

Якщо setup вимагає відсутній recipe, користувач повинен явно вибрати заміну.

### JSON payload recipe

```json
{
  "page_prefix": "admin-",
  "default_navigation_menus": [
    "admin"
  ],
  "layout": "admin",
  "wrappers": {
    "top_plugins": [],
    "sidebar_plugins": [
      {
        "plugin": "Navigation",
        "handler": "menu",
        "instance_params": {
          "menu_key": "admin"
        }
      }
    ],
    "main_plugins": []
  }
}
```

`recipe_key`, `name`, `description` зберігаються окремими колонками й у payload не дублюються.

`default_navigation_menus` завжди є масивом. Він може бути порожнім.

Усі посилання на layouts/plugins використовують `system_name`, а не numeric ID або translated title.

## 3. Setup presets плагіна

Setup presets зберігаються в:

```text
install/setup.json
```

Файл опціональний.

Відсутній `setup.json` означає, що автоматичного setup для плагіна немає.

Один файл може містити кілька незалежних presets. Наслідування, composition та `extends` не підтримуються.

Приблизна структура:

```json
{
  "presets": {
    "standard": {
      "pages": [
        {
          "system_name": "orders",
          "title": "Orders",
          "titles": {
            "uk": "Замовлення"
          },
          "slug": "orders",
          "recipe_name": "staff-area",
          "wrappers": {
            "main_plugins": [
              {
                "handler": "orders",
                "instance_params": {}
              }
            ]
          }
        }
      ]
    }
  }
}
```

`system_name` обов'язковий.

`title` та `titles` опціональні.

Fallback для title:

```text
titles[plugin default language]
→ title
→ system_name
```

Жодного beautify/humanize для `system_name`.

Plugin name у setup page instance не вказується: preset належить самому плагіну.

Page instance визначає лише `plugin + handler + instance_params`. `action` у page instance або setup preset не зберігається.
Під час runtime action визначається у такому порядку:

```text
Request {plugin_prefix}-action
→ handler.default_action
```

Request action виконується лише якщо він оголошений у handler, вибраному для цього instance. Action не перемикає handler автоматично. Якщо той самий plugin має на сторінці інші instances з іншими handler-ами, вони продовжують виконувати власні `default_action`.

## 4. Setup wizard

Процес максимально інтерактивний, реалізація — vanilla JS + AJAX.

Послідовність:

1. вибір плагіна;
2. вибір домену або списку доменів;
3. вибір setup preset;
4. побудова списку сторінок;
5. перевірка recipes;
6. перевірка layouts;
7. перевірка wrappers;
8. перевірка `system_name`;
9. перевірка `prefix + slug`;
10. вибір navigation menus;
11. фінальна повторна серверна перевірка;
12. `Apply`.

До `Apply` БД не змінюється.

Проміжний resolved plan живе на frontend. Server-side draft/session/token не створюється.

Перед `Apply` сервер повторно перевіряє весь plan.

## 5. Вирішення колізій

Усі колізії показуються безпосередньо біля конкретної сторінки.

Приклади:

- missing recipe → вибір іншого recipe;
- missing wrapper → вибір wrapper;
- missing layout → вибір layout;
- duplicate `system_name` → editable input;
- duplicate URL → editable slug;
- missing menu → вибір іншого або відмова від navigation.

PluginManager може запропонувати очевидний варіант:

```text
admin-products
→ admin-products_2
```

але нічого мовчки не змінює.

Для кожної заміни зберігаються:

```text
requested value
resolved value
```

Replacement є page-specific.

## 6. Створення сторінки

Після формування валідного page plan кожна сторінка створюється послідовно:

```text
create page
→ add recipe instances
→ add preset instances
→ add navigation items
```

Для створення використовуються методи PageManager та Navigation через PluginRegistry.

PluginManager не повинен дублювати їхню низькорівневу логіку.

Усі сторінки одного setup на одному домені створюються в одній DB transaction.

Вибір кількох доменів у UI означає незалежний запуск того самого setup у циклі для кожного домену.

## 7. Navigation

Setup preset плагіна нічого не знає про структуру navigation конкретного сайту.

Recipe містить лише список `menu_key`:

```json
"default_navigation_menus": [
  "main",
  "footer"
]
```

Створена сторінка додається останнім top-level item.

Багаторівневу структуру, порядок та інше користувач редагує вручну через Navigation.

## 8. Recipe instances

Recipe може містити стандартні instances, які не мають стосунку до плагіна, що зараз встановлюється.

Якщо plugin із recipe відсутній:

- показати notice;
- пропустити instance;
- setup не блокувати.

Handler/instance_params жорстко не валідовуються.

Якщо manifest дозволяє дешево помітити очевидну помилку handler — можна показати notice без блокування setup.

## 9. Setup history та resources

Зберігаються навіть якщо `repair/reconfigure` ще не реалізовані.

`plugin_manager_setup_resources` містить:

- `setup_id`
- `resource_key`
- `resource_type`
- `resource_id`
- `resource_uuid`
- `ownership`
- `recipe_id`
- `recipe_snapshot`
- `config`
- timestamps

Додати:

```sql
UNIQUE (setup_id, resource_key)
```

`resource_key` генерує PluginManager, а не автор `setup.json`.

`config` використовується для:

```json
{
  "source": "preset",
  "requested": {},
  "resolved": {}
}
```

`recipe_snapshot` зберігає фактичний стан recipe на момент setup. `recipe_id` посилається на `pgm_recipes` і має `ON DELETE SET NULL`, щоб history переживала видалення recipe.

## 10. History semantics

Мінімальні actions:

```text
install
update
setup
remove_from_site
uninstall
```

Status:

```text
success
failed
```

Окремі actions типу `failed_setup` не потрібні.

History повинна переживати uninstall плагіна.

## 11. Remove plugin from site

Це окремий універсальний інструмент, який не залежить від того, чи створював PluginManager поточну конфігурацію.

PluginManager знаходить усі сторінки, де присутній plugin.

Для кожної сторінки користувач обирає:

```text
delete page
remove plugin
do nothing
```

`remove plugin` видаляє всі instances цього плагіна з конкретної сторінки.

## 12. Repair / reconfigure

Не є вимогою першої версії.

Але всі provenance/history/snapshot/resolved data збираються відразу, щоб такі функції можна було додати пізніше.

## 13. DB migrations плагінів

Основний формат — SQL.

Приклад:

```text
install/db/migrations/
    001_initial.sql
    002_something.sql
```

SQL-файли не керують `BEGIN/COMMIT`; транзакцією керує ExtensionManager/System.

JSON DSL для БД не створюємо.

Виконувані PHP migrations/hooks можуть бути додані пізніше без зміни базової архітектури.

## 14. Plugin DB namespace

Manifest містить чистий prefix:

```json
"prefix": "shop"
```

Формат:

```text
^[a-z][a-z0-9]*$
```

Prefix:

- lowercase ASCII;
- унікальний;
- без `_`, `-`, пробілів та Unicode;
- не може використовувати core reserved prefix.

Для URL:

```text
shop-action
```

Для БД:

```text
shop_orders
shop_order_items
```

Plugin-owned tables повинні мати форму:

```text
{prefix}_*
```

Це використовується для визначення ownership під час uninstall.

Core reserved namespaces включають щонайменше:

```text
content
domain
field
global
item
language
log
media
migration
mime
page
permission
plugin
session
theme
token
translation
user
usergroup
```

## 15. Uninstall

Перед видаленням plugin-owned tables користувач отримує явне destructive warning.

Таблиці визначаються через `{prefix}_*`.

Content types, створені плагіном, пропонуються користувачу для видалення окремо.

Якщо content type видаляється, його items видаляються штатним механізмом Content.

## 16. Package metadata / dependencies / compatibility

Для групування у PluginManager пакет може вказати одну основну категорію:

```json
"category": "content"
```

`category` — необов’язковий lowercase key. Ієрархії категорій немає. Якщо поле відсутнє або некоректне, PluginManager використовує `others`. Категорія впливає лише на представлення пакета і не бере участі в activation, ACL чи dependency resolution. PluginManager не обмежує категорії фіксованим whitelist: відомі keys можуть мати локалізовані назви, а невідомі коректні keys відображаються автоматично.

У manifest вказуються лише жорсткі dependencies:

```json
"dependencies": [
  "Forms",
  "Navigation"
]
```

Optional/recommended dependencies залишаються в документації.

Версії plugin dependencies локально поки не описуються.

Перевірка:

```text
dependency exists → OK
dependency missing → error
```

Складну version compatibility між незалежними сторонніми plugins у майбутньому вирішує repository/package layer.

Плагін також вказує:

```json
"min_kami_version": "1.0.0"
```

Kami намагається забезпечувати backward compatibility для старих plugins.

Окремі PHP/PostgreSQL/Redis requirements у plugin manifest не описуються: підтримуване середовище визначає саме ядро Kami.

## 17. Plugin settings

Існуюча модель зберігається.

Setting має `is_global`.

Global values зберігаються в:

```text
plugins.settings
```

Local/domain values:

```text
plugin_domains.local_settings
```

Для non-global setting значення `plugins.settings` є fallback.

Setting може мати `translatable: true`. У цьому випадку value зберігається у тому самому settings JSON як map мовних значень:

```json
{
  "date_format": {
    "en": "M j, Y",
    "uk": "d.m.Y"
  }
}
```

Це language-specific value, а не запис у таблиці `translations`. Для local/domain setting мовний map зберігається у `plugin_domains.local_settings`; global fallback лишається у `plugins.settings`.

Runtime-плагін може отримати значення для поточної мови через `BasePlugin::getLocalizedSetting()`. Fallback: requested/current language → domain default language → plugin default language → first available value.

Новий механізм settings для PluginManager не потрібний.

## 18. PostgreSQL style

Для рядкових полів:

```text
text
```

використовується за замовчуванням.

`varchar(n)` використовується лише коли максимальна довжина є реальною частиною моделі/валідації.

Після завершення проектування треба одним проходом оновити `001_initial.sql` відповідно до всіх остаточних рішень.

## 19. Create page from recipe у PageManager

PageManager напряму підтримує:

```text
Create page from recipe
```

UI:

- recipe select;
- page title;
- slug;
- domain;
- Create.

PageManager є власником recipes і сам резолвить recipe для вибраного домену/theme/layout. Створення виконується атомарно:

```text
create page
→ add recipe instances
→ add default navigation items
```

PluginManager використовує той самий API PageManager для setup presets і додає поверх resolved recipe власні preset instances та setup history/resources.

## 20. Plugin-owned virtual endpoints

Плагін може оголосити один або кілька власних root endpoints у manifest:

```json
"endpoints": {
  "auth": "routeAuth",
  "webhook": "routeWebhook"
}
```

Під час install/update декларації синхронізуються з `plugin_endpoints`.
Endpoint активний на домені лише якщо плагін має відповідний запис у `plugin_domains`.
Один root endpoint може належати лише одному активному плагіну в межах одного домену.
Системні endpoints ядра (`ajax`, `api`) зарезервовані.

Endpoint method повинен бути `public` і приймати рівно один `Core\Request`:

```php
public function routeAuth(\Core\Request $request): void
{
    // Plugin-owned request and response handling.
}
```

Після dispatch ядро не застосовує page routing, plugin ACL, CSRF policy, API authentication,
response format або rendering. Authentication, authorization, validation, security checks і
формування response повністю належать плагіну.

Усе після root segment також належить плагіну. Наприклад, для endpoint `auth` плагін сам
інтерпретує `/auth/google`, `/auth/prepare/google` тощо через `Request::segments()`.
