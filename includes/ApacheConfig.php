<?php

require_once('Config.php');

class ApacheConfig {
	var $configPath;
	var $configRoot;
	var $config;
	var $includedConfigs = array();
	var $macros = array();

	var $SingleValue = array(
		'ServerName',
		'DocumentRoot',
		'DirectoryIndex',
	);

	function __construct($configPath) {
		$this->configPath = $configPath;

		$this->config = $this->getConfigArray($configPath);

		$configRoot = dirname($configPath);

		if (isset($this->config['ServerRoot'])) {
			$configRoot = trim($this->config['ServerRoot'], '"');
		}

		$this->configRoot = rtrim($configRoot, '/');
	}

	public function getVirtualHosts() {
		$this->loadIncludedFiles($this->config);
		$this->loadMacros();
		$virtualHosts = array();
		foreach ($this->includedConfigs as $includeConfig) {
			if (isset($includeConfig['VirtualHost'])) {
				$vhostConfig = $includeConfig['VirtualHost'];
				if (!isset($vhostConfig['@'])) {
					// multiple virtual hosts in a single file
					foreach ($vhostConfig as $config) {
						$virtualHosts[] = $this->getVirtualHost($config);
					}
				} else {
					$virtualHosts[] = $this->getVirtualHost($vhostConfig);
				}
			}
		}
		return $virtualHosts;
	}

	function getConfigArray($path) {
		$config = new Config();
		$apacheConfig = $config->parseConfig($path, 'apache');
		$configArray = $apacheConfig->toArray();
		return $configArray['root'];
	}

	function getDirectiveArray($config, $directiveName) {
		$directives = array();
		if (isset($config[$directiveName])) {
			$directive = $config[$directiveName];
			if (gettype($directive) !== "array") {
				$directives[] = $directive;
			} else {
				$directives = $directive;
			}
		}
		return $directives;
	}

	private function loadIncludedFiles($config) {
		$includes = $this->getDirectiveArray($config, 'Include');
		$includesOptional = $this->getDirectiveArray($config, 'IncludeOptional');

		$includeFiles = array_merge($includes, $includesOptional);

		foreach ($includeFiles as $includeFile) {
			if (substr($includeFile, 0, 1) !== '/') {
				$includeFile = "$this->configRoot/$includeFile";
			}
			foreach (glob($includeFile) as $path) {
				if (file_exists($path)) {
					$includedConfig = $this->getConfigArray($path);
					$this->includedConfigs[] = $includedConfig;
					$this->loadIncludedFiles($includedConfig);
				}
			}
		}
	}

	private function loadMacros() {
		foreach ($this->includedConfigs as $includeConfig) {
			if (isset($includeConfig['Macro'])) {
				$macroConfig = $includeConfig['Macro'];
				if (!isset($macroConfig['@'])) {
					// multiple macros in a single file
					foreach ($macroConfig as $config) {
						$this->loadMacro($config);
					}
				} else {
					$this->loadMacro($macroConfig);
				}
			}
		}
	}

	private function loadMacro($macroConfig) {
		// macro names are case insensitive
		$macroName = strtolower($macroConfig['@'][0]);
		$parameters = array_slice($macroConfig['@'], 1);
		unset($macroConfig['@']);
		$this->macros[$macroName] = (object) array(
			'parameters' => $parameters,
			'directives' => $macroConfig,
		);
	}

	private function getVirtualHost($vhostConfig) {
		$listenSocket = explode(':', $vhostConfig['@'][0]);
		$vhost = (object) array(
			'address' => $listenSocket[0],
			'port' => $listenSocket[1],
		);
		unset($vhostConfig['@']);
		foreach ($vhostConfig as $k => $v) {
			if (!in_array($k, $this->SingleValue) && gettype($v) !== 'array') {
				$vhostConfig[$k] = array($v);
			}
		}
		$vhost->config = $this->resolveConfig($vhostConfig);
		return $vhost;
	}

	private function resolveConfig($config) {
		if (isset($config['Use'])) {
			$useDirective = $config['Use'];
			unset($config['Use']);
			if (gettype($useDirective) !== "array") {
				$useDirectives[] = $useDirective;
			} else {
				$useDirectives = $useDirective;
			}
			foreach ($useDirectives as $macro) {
				$macroArgs = preg_split('/\s+/', $macro);
				$macroName = strtolower($macroArgs[0]);
				$macroArgs = array_slice($macroArgs, 1);
				// TODO: check for missing macros
				$macro = $this->macros[$macroName];
				$macroVariables = array();
				foreach ($macro->parameters as $i => $parameter) {
					// TODO: check for missing arguments
					if (!isset($macroArgs[$i])) { continue; }
					$macroVariables[$parameter] = $macroArgs[$i];
				}
				$resolvedConfig = $this->resolveMacro($macro->directives, $macroVariables);
				foreach ($resolvedConfig as $k => $macroValue) {
					if (!in_array($k, $this->SingleValue)) {
						$k2 = isset($config[$k]) ? $config[$k] : null;
						if (isset($k2) && gettype($k2) !== 'array') {
							$k2 = array($k2);
						}
						if (!isset($k2)) {
							$k2 = array();
						}
						if (gettype($macroValue) === 'array') {
							$k2 = array_merge($k2, $macroValue);
						} else {
							$k2[] = $macroValue;
						}
						$config[$k] = $k2;
					} else {
						$config[$k] = $macroValue;
					}
				}
			}
		}
		return $config;
	}

	private function resolveMacro($config, $variables) {
		if (gettype($config) === 'array') {
			$resolvedConfig = array();
			foreach ($config as $k => $v) {
				$resolvedConfig[$k] = $this->resolveMacro($v, $variables);
			}
			$resolvedConfig = $this->resolveConfig($resolvedConfig);
		} else {
			$resolvedConfig = $config;
			foreach ($variables as $k => $v) {
				// TODO: check for missing arguments
				if (!isset($v)) { continue; }
				$resolvedConfig = preg_replace("/\\$k/", $v, $resolvedConfig);
			}
		}
		return $resolvedConfig;
	}
}
