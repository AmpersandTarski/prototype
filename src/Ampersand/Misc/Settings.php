<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Misc;

use Ampersand\Exception\InvalidConfigurationException;
use Ampersand\Misc\Extension;
use Ampersand\Model;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Psr\Log\LoggerInterface;

/**
 *
 * @author Michiel Stornebrink (https://github.com/Michiel-s)
 *
 */
class Settings
{
    /**
     * Mapping from environment variables to configuration settings
     * If specified as bool, the environment var string is transformed into a boolean value
     * !!! NOTE: update README.md in config folder when adding new environment variables
     */
    const ENV_VAR_CONFIG_MAP = [
        'AMPERSAND_DEBUG_MODE' => [
            'key' => 'global.debugMode',
            'bool' => true
        ],
        'AMPERSAND_PRODUCTION_MODE' => [
            'key' => 'global.productionEnv',
            'bool' => true
        ],
        'AMPERSAND_DBHOST' => [
            'key' => 'mysql.dbHost',
            'bool' => false
        ],
        'AMPERSAND_DBNAME' => [
            'key' => 'mysql.dbName',
            'bool' => false
        ],
        'AMPERSAND_DBUSER' => [
            'key' => 'mysql.dbUser',
            'bool' => false
        ],
        'AMPERSAND_DBPASS' => [
            'key' => 'mysql.dbPass',
            'bool' => false
        ],
        'AMPERSAND_SERVER_URL' => [
            'key' => 'global.serverURL',
            'bool' => false
        ]
    ];

    const COMPILER_VAR_CONFIG_MAP = [
        'compiler.env' => 'compiler.env',
        'compiler.modelHash' => 'compiler.modelHash',
        'compiler.version' => 'compiler.version',
        'global.contextName' => 'global.contextName'
    ];

    /**
     * Logger
     */
    protected LoggerInterface $logger;

    /**
     * Array of all settings
     *
     * Setting keys (e.g. global.debugMode) are case insensitive
     */
    protected array $settings = [];

    /**
     * List of configured extensions
     *
     * @var \Ampersand\Misc\Extension[]
     */
    protected array $extensions = [];

    /**
     * Constructor
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->loadSettingsYamlFile(dirname(__FILE__) . '/defaultSettings.yaml');
    }

    /**
     * Load settings file
     *
     * Use overwriteAllowed to specify if already set settings may be overwritten
     */
    public function loadSettingsFromCompiler(Model $model, bool $overwriteAllowed = true): self
    {
        $filePath = $model->getFilePath('settings');

        $this->logger->info("Loading settings from Ampersand compiler");

        $fileSystem = new Filesystem;
        if (!$fileSystem->exists($filePath)) {
            throw new InvalidConfigurationException("Cannot load settings file. Specified path does not exist: '{$filePath}'");
        }

        $decoder = new JsonDecode(false);
        $compilerSettings = $decoder->decode(file_get_contents($filePath), JsonEncoder::FORMAT);

        // Only the variables mapped in the COMPILER_VAR_CONFIG_MAP are used
        foreach (self::COMPILER_VAR_CONFIG_MAP as $var => $config) {
            $this->set($config, $compilerSettings->{$var}, $overwriteAllowed);
        }
        
        return $this;
    }

    /**
     * Load settings file (yaml format)
     *
     * Use overwriteAllowed to specify if already set settings may be overwritten
     */
    public function loadSettingsYamlFile(string $filePath, bool $overwriteAllowed = true, bool $fileMustExist = true): self
    {
        $fileSystem = new Filesystem;
        if (!$fileSystem->exists($filePath)) {
            if ($fileMustExist) {
                throw new InvalidConfigurationException("Cannot load settings file. Specified path does not exist: '{$filePath}'");
            } else {
                $this->logger->info("Configuration file does not exist: '{$filePath}'");
                return $this;
            }
        }

        $this->logger->info("Loading configuration from {$filePath}");

        $encoder = new YamlEncoder();
        $file = $encoder->decode(file_get_contents($filePath), YamlEncoder::FORMAT);

        // Process specified settings
        foreach ((array)$file['settings'] as $setting => $value) {
            $this->set($setting, $value, $overwriteAllowed);
        }

        // Process specified extensions
        foreach ((array)$file['extensions'] as $extName => $data) {
            $bootstrapFile = isset($data['bootstrap']) ? $this->get('global.absolutePath') . "/" . $data['bootstrap'] : null;
            
            if (isset($data['config'])) {
                // Reference to another config file
                if (is_string($data['config'])) {
                    $configFile = $this->get('global.absolutePath') . "/" . $data['config'];
                    $this->loadSettingsYamlFile($configFile, false); // extensions settings are not allowed to overwrite existing settings
                // Extension config is provided
                } elseif (is_array($data['config'])) {
                    foreach ($data['config'] as $setting => $value) {
                        $this->set($setting, $value, false);
                    }
                // Extension config is provided
                } else {
                    throw new InvalidConfigurationException("Unable to load config for extension '{$extName}' in '{$filePath}'");
                }
            }

            $this->extensions[] = new Extension($extName, $bootstrapFile);
        }

        // Process additional config files
        if (isset($file['config'])) {
            if (!is_array($file['config'])) {
                throw new InvalidConfigurationException("Unable to process additional config files in {$filePath}. List expected, non-list provided.");
            }

            foreach ($file['config'] as $path) {
                $configFile = $this->get('global.absolutePath') . "/" . $path;
                $this->loadSettingsYamlFile($configFile, true);
            }
        }

        return $this;
    }

    public function loadSettingsFromEnv(): void
    {
        $this->logger->info("Loading env settings");

        foreach (self::ENV_VAR_CONFIG_MAP as $env => $config) {
            $value = getenv($env, true);
            if ($value !== false) {
                // convert to boolean value if needed
                if ($config['bool']) {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }
                $this->set($config['key'], $value);
            }
        }
    }

    /**
     * Get a specific setting
     */
    public function get(string $setting, mixed $defaultIfNotSet = null): mixed
    {
        $setting = strtolower($setting); // use lowercase

        if (!array_key_exists($setting, $this->settings) && is_null($defaultIfNotSet)) {
            throw new InvalidConfigurationException("Setting '{$setting}' is not specified");
        }

        return $this->settings[$setting] ?? $defaultIfNotSet;
    }

    /**
     * Set a specific setting to a (new) value
     *
     * If overwriteAllowed is set, the value will overwrite any value that is previously set
     * Otherwise, throws exception when setting was already set
     */
    public function set(string $setting, mixed $value = null, bool $overwriteAllowed = true): void
    {
        $setting = strtolower($setting); // use lowercase
        
        if (array_key_exists($setting, $this->settings) && !$overwriteAllowed) {
            throw new InvalidConfigurationException("Setting '{$setting}' is set already; overwrite is not allowed");
        }

        $this->settings[$setting] = $value;
        $this->logger->debug("Setting '{$setting}' to '{$value}'");
    }

    /**
     * Get list of configured extensions
     *
     * @return \Ampersand\Misc\Extension[]
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**********************************************************************************************
     * TYPED GETTERS FOR CERTAIN SETTINGS
     *********************************************************************************************/
    
    public function getDataDirectory(): string
    {
        $configValue = $this->get('global.dataPath');
        $appDir = $this->get('global.absolutePath');

        // Defaults to [global.absolutePath]/data
        if (is_null($configValue)) {
            return $appDir . '/data';
        }

        // If specified as absolute path -> return it
        // Otherwise append to default application path
        $fs = new Filesystem();
        if ($fs->isAbsolutePath($configValue)) {
            $path = $configValue;
        } else {
            $path = "{$appDir}/data/{$configValue}";
        }

        // Check that path is really a directory and exists
        if (!is_dir($path)) {
            // Try to create the directory
            if (!mkdir($path, 0777, true)) {
                throw new InvalidConfigurationException("Specified data directory '{$path}' is not a directory, cannot be created or is not accessible");
            }
        }

        return $path;
    }
}
