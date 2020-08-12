<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\Misc;

use Exception;
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
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Array of all settings
     * Setting keys (e.g. global.debugMode) are case insensitive
     *
     * @var array
     */
    protected $settings = [];

    /**
     * List of configured extensions
     *
     * @var \Ampersand\Misc\Extension[]
     */
    protected $extensions = [];

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
     * @param \Ampersand\Model $model
     * @param bool $overwriteAllowed specifies if already set settings may be overwritten
     * @return \Ampersand\Misc\Settings $this
     */
    public function loadSettingsFromCompiler(Model $model, bool $overwriteAllowed = true): Settings
    {
        $filePath = $model->getFilePath('settings');

        $this->logger->info("Loading settings from Ampersand compiler");

        $fileSystem = new Filesystem;
        if (!$fileSystem->exists($filePath)) {
            throw new Exception("Cannot load settings file. Specified path does not exist: '{$filePath}'", 500);
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
     * @param string $filePath
     * @param bool $overwriteAllowed specifies if already set settings may be overwritten
     * @return \Ampersand\Misc\Settings $this
     */
    public function loadSettingsYamlFile(string $filePath, bool $overwriteAllowed = true, bool $fileMustExist = true): Settings
    {
        $fileSystem = new Filesystem;
        if (!$fileSystem->exists($filePath)) {
            if ($fileMustExist) {
                throw new Exception("Cannot load settings file. Specified path does not exist: '{$filePath}'", 500);
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
                    throw new Exception("Unable to load config for extension '{$extName}' in '{$filePath}'", 500);
                }
            }

            $this->extensions[] = new Extension($extName, $bootstrapFile);
        }

        // Process additional config files
        if (isset($file['config'])) {
            if (!is_array($file['config'])) {
                throw new Exception("Unable to process additional config files in {$filePath}. List expected, non-list provided.", 500);
            }

            foreach ($file['config'] as $path) {
                $configFile = $this->get('global.absolutePath') . "/" . $path;
                $this->loadSettingsYamlFile($configFile, true);
            }
        }

        return $this;
    }

    public function loadSettingsFromEnv()
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
     *
     * @param string $setting
     * @param mixed $defaultIfNotSet
     * @return mixed
     */
    public function get(string $setting, $defaultIfNotSet = null)
    {
        $setting = strtolower($setting); // use lowercase

        if (!array_key_exists($setting, $this->settings) && is_null($defaultIfNotSet)) {
            throw new Exception("Setting '{$setting}' is not specified", 500);
        }

        return $this->settings[$setting] ?? $defaultIfNotSet;
    }

    /**
     * Set a specific setting to a (new) value
     *
     * @param string $setting
     * @param mixed $value
     * @param boolean $overwriteAllowed specifies if already set setting may be overwritten
     * @return void
     */
    public function set(string $setting, $value = null, $overwriteAllowed = true)
    {
        $setting = strtolower($setting); // use lowercase
        
        if (array_key_exists($setting, $this->settings) && !$overwriteAllowed) {
            throw new Exception("Setting '{$setting}' is set already; overwrite is not allowed", 500);
        }

        $this->settings[$setting] = $value;
        $this->logger->debug("Setting '{$setting}' to '{$value}'");
    }

    /**
     * Get list of configured extensions
     *
     * @return \Ampersand\Misc\Extension[]
     */
    public function getExtensions()
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
            throw new Exception("Specified data directory '{$path}' is not a directory, does not exist or is not accessible", 500);
        }

        return $path;
    }
}
