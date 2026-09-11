<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

/**
 * Plugin handler ACL is enforced during user action dispatch.
 * Plugin lifecycle and internal method calls are not subject to handler ACL.
 */

namespace Core;

if(!IN_KAMI) die();

abstract class BasePlugin {

	public protected(set) bool $active = false;

	public protected(set) ?int $id;
	public protected(set) ?string $uuid;
	public protected(set) ?string $name;

	public protected(set) ?array $config = null;
	public protected(set) ?array $settings = null;

	public protected(set) ?array $translation = null;
	public protected(set) ?array $phrases = null;

	public protected(set) ?string $prefix = null;

    protected ?array $data = null;

	protected PluginRegistry $plugins;

	protected array $layoutParams = [];

	protected ?string $requestAction = null;
	private static int $invocationSequence = 0;
	private ?string $invocationToken = null;

	// Default handlers => [methods]
	protected static array $default_handlers = [
		'view' => [
			'title' => 'View',
			'actions' => ['view'],
			'default_action' => 'view',
		],
		'manage' => [
			'title' => 'Edit',
			'actions' => ['edit'],
			'default_action' => 'edit'
		]
	];

    public function __construct(PluginRegistry $plugins) {
		$this->plugins = $plugins;

		$class = static::class;
		$pos = strrpos($class, '\\');
		$this->name = $pos === false ? $class : substr($class, $pos + 1);

		$full_data = \Cache::get('d_'.DOMAIN_ID.":plugin:{$this->name}");

		if(!$full_data) {
			$main_data = \DB::getRow(
				'select * from plugins where system_name=$1',
				[$this->name]
			) ?: null;
			if(!$main_data || !$main_data['is_active']) {
				trigger_error("Plugin is not installed/activated - {$this->name}", E_USER_WARNING);
				return false;
			}

			$domain_data = \DB::getRow(
				'select local_settings from plugin_domains where plugin_id=$1 and domain_id=$2',
				[(int)$main_data['plugin_id'], DOMAIN_ID]
			) ?: null;
			if(!$domain_data) {
				trigger_error("Plugin is not allowed on this domain", E_USER_WARNING);
				return false;
			}

			$locals = json_decode($domain_data['local_settings'] ?? "", true) ?? [];
			$baseSettings = json_decode($main_data['settings'] ?? "", true) ?? [];

			$this->settings = array_replace($baseSettings, $locals);
			$this->config = json_decode($main_data['config'], true);

			$full_data = [
				"id" => $main_data['plugin_id'],
				"uuid" => $main_data['uuid'],
				"config" => $this->config,
				"settings" => $this->settings,
				"prefix" => $main_data['plugin_prefix'],
			];

			\Cache::set('d_'.DOMAIN_ID.":plugin:{$this->name}", $full_data);
		} else {
			$this->config = $full_data['config'];
			$this->settings = $full_data['settings'];
		}

		$this->uuid = $full_data['uuid'];
		$this->id = $full_data['id'];

		$this->prefix = $full_data['prefix'];

		$this->translation = Translation::get(
			$this->uuid,
			null,
			$this->config['default_language'] ?? null
		) ?? [];
		$this->phrases = $this->translation['phrases'] ?? [];

		$requestAction = \Core\Request::peekParam('action', $this->prefix, null, false);
		$this->requestAction = is_string($requestAction) && $requestAction !== ''
			? $requestAction
			: null;

		// Plugin-specified init function
		if(method_exists($this, "init")) $this->init();

		$this->active = true;
    }

    public function getLayoutParams(): array
	{
		return $this->layoutParams;
	}

	public function render(string $template_name, array $params = [], ?bool $cacheable = false):string {
		$params['phrases'] = $this->phrases;
		return \Core\Renderer::render($template_name, $this->name, $params, $cacheable);
	}

    private function handlerDefinitions(): array
    {
        if (isset($this->config['handlers']) && is_array($this->config['handlers'])) {
            return $this->config['handlers'];
        }

        if (property_exists($this, 'handlers') && is_array($this->handlers)) {
            return $this->handlers;
        }

        return self::$default_handlers;
    }

    public function resolveActionHandler(?string $action): ?string {
        if (!$action) {
            return null;
        }

        $handlers = $this->handlerDefinitions();
        $resolvedHandler = null;

        foreach ($handlers as $handler => $handlerConfig) {
            if (!is_string($handler) || !is_array($handlerConfig)) {
                continue;
            }

            $actions = $handlerConfig['actions'] ?? [];
            if (!is_array($actions)) {
                continue;
            }

            $containsAction = array_key_exists($action, $actions)
                || in_array($action, $actions, true);

            if (!$containsAction) {
                continue;
            }

            if ($resolvedHandler !== null && $resolvedHandler !== $handler) {
                throw new \RuntimeException(
                    "Action {$action} is declared in multiple handlers for plugin {$this->name}."
                );
            }

            $resolvedHandler = $handler;
        }

        return $resolvedHandler;
    }

    public function resolveActionMethod(?string $action): ?string {
		if(!$action) return null;

		// 1. Explicit mapping
		if (isset($this->actionMap[$action])) {
			return $this->actionMap[$action];
		}

		// 2. Auto convert snake_case → camelCase
		$method = lcfirst(str_replace('_', '', ucwords($action, "_")));

		return method_exists($this, $method) ? $method : null;
	}

    public function isApiAction(string $action): bool
    {
        $handler = $this->resolveActionHandler($action);
        if ($handler === null) {
            return false;
        }

        $actions = $this->handlerDefinitions()[$handler]['actions'] ?? [];
        if (!is_array($actions) || !array_key_exists($action, $actions)) {
            return false;
        }

        $actionConfig = $actions[$action];
        return is_array($actionConfig) && ($actionConfig['api'] ?? false) === true;
    }

    public function invokeAction(string $action, array $params = []): mixed
    {
        if (!$this->active) {
            throw new PluginActionException(
                "Plugin {$this->name} is not active.",
                PluginActionException::NOT_FOUND
            );
        }

        $handler = $this->resolveActionHandler($action);
        if ($handler === null) {
            throw new PluginActionException(
                "Action {$action} is not declared for plugin {$this->name}.",
                PluginActionException::NOT_FOUND
            );
        }

        if (!User::canPlugin((int)$this->id, $handler)) {
            throw new PluginActionException(
                "Handler {$handler} is not allowed for plugin {$this->name}.",
                PluginActionException::FORBIDDEN
            );
        }

        $method = $this->resolveActionMethod($action);
        if ($method === null || !method_exists($this, $method)) {
            trigger_error(
                "Declared action {$action} has no callable method in {$this->name}.",
                E_USER_WARNING
            );
            throw new PluginActionException(
                "Action {$action} is not callable for plugin {$this->name}.",
                PluginActionException::NOT_FOUND
            );
        }

        return $this->$method($params);
    }

    /**
     * Return all plugin settings.
     */
    public function getSettings(): array {
        return $this->settings;
    }

    /**
     * Return one plugin setting.
     */
    public function getSetting(string $key) {
        return $this->settings[$key] ?? null;
    }

    public function getLocalizedSetting(string $key, ?string $lang = null): mixed
    {
        $value = $this->settings[$key] ?? null;
        if (!is_array($value)) {
            return $value;
        }

        $lang = strtolower(trim((string)($lang ?? (defined('LANG') ? LANG : ''))));
        if ($lang !== '' && array_key_exists($lang, $value)) {
            return $value[$lang];
        }

        $fallbacks = [];
        if (defined('DOMAIN_CONFIG') && !empty(DOMAIN_CONFIG['default_language'])) {
            $fallbacks[] = strtolower((string)DOMAIN_CONFIG['default_language']);
        }
        if (!empty($this->config['default_language'])) {
            $fallbacks[] = strtolower((string)$this->config['default_language']);
        }

        foreach (array_unique($fallbacks) as $fallback) {
            if (array_key_exists($fallback, $value)) {
                return $value[$fallback];
            }
        }

        return $value !== [] ? reset($value) : null;
    }

    /**
     * Replace the in-memory plugin settings.
     */
    public function saveSettings(array $settings): void {
        $this->settings = $settings;
		
    }

    /**
     * Lifecycle hook called once after page plugin processing is complete.
     */
    public function finalize(array $layoutParams): void
    {
    }

	public function handle(array $instance_params = []):string {

		if(!$this->active) {
			trigger_error("Plugin is not active - skip handle", E_USER_WARNING);
			return "";
		}

		$previousInvocationToken = $this->invocationToken;
		$this->invocationToken = $this->name
			. ':' . spl_object_id($this)
			. ':' . (++self::$invocationSequence);

		try {
			$handlers = $this->handlerDefinitions();
			$handler = $instance_params['handler']
				?? $this->config['default_handler']
				?? array_key_first($handlers);
			$handlerConfig = $handlers[$handler] ?? [];
			$allowedActions = $handlerConfig['actions'] ?? [];
			$defaultAction = $handlerConfig['default_action']
				?? $handlerConfig['default']
				?? (is_array($allowedActions) ? array_key_first($allowedActions) : null);

			$requestActionAllowed = $this->requestAction !== null && (
				empty($this->config['handlers'])
				|| in_array($this->requestAction, $allowedActions, true)
				|| array_key_exists($this->requestAction, $allowedActions)
			);
			if ($requestActionAllowed) {
				\Core\Request::consumePathParam('action', $this->prefix);
			}

			$actionName = $requestActionAllowed ? $this->requestAction : $defaultAction;

			$isAllowed = $actionName !== null && (
				in_array($actionName, $allowedActions, true)
				|| array_key_exists($actionName, $allowedActions)
			);

			if ($isAllowed) {
				try {
					return $this->invokeAction($actionName, $instance_params);
				} catch (PluginActionException $e) {
					if ($e->getCode() === PluginActionException::FORBIDDEN) {
						return '';
					}
				}
			}

			if(isset($instance_params['template']) && !empty($instance_params['template'])) {
				return $this->render($instance_params['template'], $instance_params);
			} else {
				trigger_error(
					"No callable action exists for {$this->name} "
					. "(request={$this->requestAction}, default={$defaultAction}, handler={$handler})",
					E_USER_WARNING
				);
				return "";
			}
		} finally {
			$this->invocationToken = $previousInvocationToken;
		}
	}

    /**
     * Helper methods.
     */
    protected function param(string $name, mixed $default = null): mixed {
        return \Core\Request::param($name, $this->prefix, $default);
    }

    protected function params(array $names, array $data = []): array {
        foreach ($names as $name) {
            if (!is_string($name) || $name === '') {
                continue;
            }
            $data[$name] = $this->param($name, $data[$name] ?? null);
        }
        return $data;
    }

    /**
     * Return the item resolved from the current frontend route and accept it for this invocation.
     */
    protected function routedItem(): ?array {
        $item = $this->peekRoutedItem();
        if ($item === null) {
            return null;
        }

        \Core\Request::claimRoutedItem($this->currentInvocationToken());
        return $item;
    }

    /**
     * Inspect the routed item without accepting responsibility for the route.
     */
    protected function peekRoutedItem(): ?array {
        $itemId = \Core\Request::routedItemId();
        if ($itemId === null) {
            return null;
        }

        $item = \Core\Content::getItem($itemId);
        return $item !== [] ? $item : null;
    }

    /**
     * Withdraw this invocation's routed-item acceptance without affecting other instances.
     */
    protected function declineRoutedItem(): void {
        \Core\Request::declineRoutedItem($this->currentInvocationToken());
    }

    private function currentInvocationToken(): string {
        if ($this->invocationToken === null) {
            throw new \LogicException('Routed item claims are available only during plugin handle().');
        }

        return $this->invocationToken;
    }

    protected function log(string $message, string $level = 'info'): void {
        error_log("[$level][" . $this->name . "] $message");
    }

}
