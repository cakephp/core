<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Core;

use BadMethodCallException;
use InvalidArgumentException;
use LogicException;

/**
 * A trait that provides a set of static methods to manage configuration
 * for classes that provide an adapter facade or need to have sets of
 * configuration data registered and manipulated.
 *
 * Implementing objects are expected to declare a static `$_dsnClassMap` property.
 */
trait StaticConfigTrait
{

    /**
     * Configuration sets.
     *
     * @var array
     */
    protected static $_config = [];

    /**
     * This method can be used to define configuration adapters for an application.
     *
     * To change an adapter's configuration at runtime, first drop the adapter and then
     * reconfigure it.
     *
     * Adapters will not be constructed until the first operation is done.
     *
     * ### Usage
     *
     * Assuming that the class' name is `Cache` the following scenarios
     * are supported:
     *
     * Setting a cache engine up.
     *
     * ```
     * Cache::setConfig('default', $settings);
     * ```
     *
     * Injecting a constructed adapter in:
     *
     * ```
     * Cache::setConfig('default', $instance);
     * ```
     *
     * Configure multiple adapters at once:
     *
     * ```
     * Cache::setConfig($arrayOfConfig);
     * ```
     *
     * @param string|array $key The name of the configuration, or an array of multiple configs.
     * @param array $config An array of name => configuration data for adapter.
     * @throws \BadMethodCallException When trying to modify an existing config.
     * @throws \LogicException When trying to store an invalid structured config array.
     * @return void
     */
    public static function setConfig($key, $config = null)
    {
        if ($config === null) {
            if (!is_array($key)) {
                throw new LogicException('If config is null, key must be an array.');
            }
            foreach ($key as $name => $settings) {
                static::setConfig($name, $settings);
            }

            return;
        }

        if (isset(static::$_config[$key])) {
            throw new BadMethodCallException(sprintf('Cannot reconfigure existing key "%s"', $key));
        }

        if (is_object($config)) {
            $config = ['className' => $config];
        }

        if (isset($config['url'])) {
            $parsed = static::parseDsn($config['url']);
            unset($config['url']);
            $config = $parsed + $config;
        }

        if (isset($config['engine']) && empty($config['className'])) {
            $config['className'] = $config['engine'];
            unset($config['engine']);
        }
        static::$_config[$key] = $config;
    }

    /**
     * Reads existing configuration.
     *
     * @param string $key The name of the configuration.
     * @return array|null Array of configuration data.
     */
    public static function getConfig($key)
    {
        return isset(static::$_config[$key]) ? static::$_config[$key] : null;
    }

    /**
     * This method can be used to define configuration adapters for an application
     * or read existing configuration.
     *
     * To change an adapter's configuration at runtime, first drop the adapter and then
     * reconfigure it.
     *
     * Adapters will not be constructed until the first operation is done.
     *
     * ### Usage
     *
     * Assuming that the class' name is `Cache` the following scenarios
     * are supported:
     *
     * Reading config data back:
     *
     * ```
     * Cache::config('default');
     * ```
     *
     * Setting a cache engine up.
     *
     * ```
     * Cache::config('default', $settings);
     * ```
     *
     * Injecting a constructed adapter in:
     *
     * ```
     * Cache::config('default', $instance);
     * ```
     *
     * Configure multiple adapters at once:
     *
     * ```
     * Cache::config($arrayOfConfig);
     * ```
     *
     * @deprecated 3.4.0 Use setConfig()/getConfig() instead.
     * @param string|array $key The name of the configuration, or an array of multiple configs.
     * @param array|null $config An array of name => configuration data for adapter.
     * @return array|null Null when adding configuration or an array of configuration data when reading.
     * @throws \BadMethodCallException When trying to modify an existing config.
     */
    public static function config($key, $config = null)
    {
        if ($config !== null || is_array($key)) {
            static::setConfig($key, $config);

            return null;
        }

        return static::getConfig($key);
    }

    /**
     * Drops a constructed adapter.
     *
     * If you wish to modify an existing configuration, you should drop it,
     * change configuration and then re-add it.
     *
     * If the implementing objects supports a `$_registry` object the named configuration
     * will also be unloaded from the registry.
     *
     * @param string $config An existing configuration you wish to remove.
     * @return bool Success of the removal, returns false when the config does not exist.
     */
    public static function drop($config)
    {
        if (!isset(static::$_config[$config])) {
            return false;
        }
        if (isset(static::$_registry)) {
            static::$_registry->unload($config);
        }
        unset(static::$_config[$config]);

        return true;
    }

    /**
     * Returns an array containing the named configurations
     *
     * @return array Array of configurations.
     */
    public static function configured()
    {
        return array_keys(static::$_config);
    }

    /**
     * Parses a DSN into a valid connection configuration
     *
     * This method allows setting a DSN using formatting similar to that used by PEAR::DB.
     * The following is an example of its usage:
     *
     * ```
     * $dsn = 'mysql://user:pass@localhost/database?';
     * $config = ConnectionManager::parseDsn($dsn);
     *
     * $dsn = 'Cake\Log\Engine\FileLog://?types=notice,info,debug&file=debug&path=LOGS';
     * $config = Log::parseDsn($dsn);
     *
     * $dsn = 'smtp://user:secret@localhost:25?timeout=30&client=null&tls=null';
     * $config = Email::parseDsn($dsn);
     *
     * $dsn = 'file:///?className=\My\Cache\Engine\FileEngine';
     * $config = Cache::parseDsn($dsn);
     *
     * $dsn = 'File://?prefix=myapp_cake_core_&serialize=true&duration=+2 minutes&path=/tmp/persistent/';
     * $config = Cache::parseDsn($dsn);
     * ```
     *
     * For all classes, the value of `scheme` is set as the value of both the `className`
     * unless they have been otherwise specified.
     *
     * Note that querystring arguments are also parsed and set as values in the returned configuration.
     *
     * @param string $dsn The DSN string to convert to a configuration array
     * @return array The configuration array to be stored after parsing the DSN
     * @throws \InvalidArgumentException If not passed a string, or passed an invalid string
     */
    public static function parseDsn($dsn)
    {
        if (empty($dsn)) {
            return [];
        }

        if (!is_string($dsn)) {
            throw new InvalidArgumentException('Only strings can be passed to parseDsn');
        }

        $pattern = '/^(?P<scheme>[\w\\\\]+):\/\/((?P<user>.*?)(:(?P<password>.*?))?@)?' .
            '((?P<host>[.\w\\\\]+)(:(?P<port>\d+))?)?' .
            '(?P<path>\/[^?]*)?(\?(?P<query>.*))?$/';
        preg_match($pattern, $dsn, $parsed);

        if (empty($parsed)) {
            throw new InvalidArgumentException("The DSN string '{$dsn}' could not be parsed.");
        }
        foreach ($parsed as $k => $v) {
            if (is_int($k)) {
                unset($parsed[$k]);
            }
            if ($v === '') {
                unset($parsed[$k]);
            }
        }

        $query = '';

        if (isset($parsed['query'])) {
            $query = $parsed['query'];
            unset($parsed['query']);
        }

        parse_str($query, $queryArgs);

        foreach ($queryArgs as $key => $value) {
            if ($value === 'true') {
                $queryArgs[$key] = true;
            } elseif ($value === 'false') {
                $queryArgs[$key] = false;
            } elseif ($value === 'null') {
                $queryArgs[$key] = null;
            }
        }

        if (isset($parsed['user'])) {
            $parsed['username'] = $parsed['user'];
        }

        if (isset($parsed['pass'])) {
            $parsed['password'] = $parsed['pass'];
        }

        unset($parsed['pass'], $parsed['user']);
        $parsed = $queryArgs + $parsed;

        if (empty($parsed['className'])) {
            $classMap = static::getDsnClassMap();

            $parsed['className'] = $parsed['scheme'];
            if (isset($classMap[$parsed['scheme']])) {
                $parsed['className'] = $classMap[$parsed['scheme']];
            }
        }

        return $parsed;
    }

    /**
     * Updates the DSN class map for this class.
     *
     * @param array $map Additions/edits to the class map to apply.
     * @return void
     */
    public static function setDsnClassMap(array $map)
    {
        static::$_dsnClassMap = $map + static::$_dsnClassMap;
    }

    /**
     * Returns the DSN class map for this class.
     *
     * @return array
     */
    public static function getDsnClassMap()
    {
        return static::$_dsnClassMap;
    }

    /**
     * Returns or updates the DSN class map for this class.
     *
     * @deprecated 3.4.0 Use setDsnClassMap()/getDsnClassMap() instead.
     * @param array|null $map Additions/edits to the class map to apply.
     * @return array
     */
    public static function dsnClassMap(array $map = null)
    {
        if ($map !== null) {
            static::setDsnClassMap($map);
        }

        return static::getDsnClassMap();
    }
}
