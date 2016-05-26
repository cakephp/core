<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         3.3.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Shell;

use Cake\Cache\Cache;
use Cake\Console\Shell;
use Cake\Core\Configure;

/**
 * Caches Shell.
 *
 * Provides a CLI interface to clear caches.
 * This tool can be used in development or by deployment scripts when changes
 * are made that require cached data to be removed.
 */
class CachesShell extends Shell
{

    /**
     * Get the option parser for this shell.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser()
    {
        $parser = parent::getOptionParser();
        $parser->addSubcommand('list_prefixes', [
            'help' => 'Show a list of all defined cache prefixes.',
        ]);
        $parser->addSubcommand('clear_all', [
            'help' => 'Clear all caches.',
        ]);
        $parser->addSubcommand('clear', [
            'help' => 'Clear the cache for a specified prefix.',
            'parser' => [
                'description' => [
                    'Clear the cache for a particular prefix.',
                    'For example, `cake cache clear _cake_model_` will clear the model cache',
                    'Use `cake cache clear list_prefixes` to list available prefixes'
                ],
                'arguments' => [
                    'prefix' => [
                        'help' => 'The cache prefix to be cleared.',
                        'required' => true
                    ]
                ]
            ]
        ]);

        return $parser;
    }

    /**
     * Clear metadata.
     *
     * @param string|null $prefix The cache prefix to be cleared.
     * @throws Cake\Console\Exception\StopException
     * @return void
     */
    public function clear($prefix = null)
    {
        try {
            Cache::clear(false, $prefix);
        } catch (\InvalidArgumentException $e) {
            $this->abort($e->getMessage());
        }

        $this->out("<success>Cleared $prefix cache</success>");
    }

    /**
     * Clear metadata.
     *
     * @return void
     */
    public function clearAll()
    {
        if (version_compare(Configure::version(), '3.2.0', '>=')) {
            Cache::clearAll(false);
            $this->out("<success>Cleared all caches</success>");
        } else {
            $prefixes = Cache::configured();
            foreach ($prefixes as $prefix) {
                $this->clear($prefix);
            }
        }
    }

    /**
     * Show a list of all defined cache prefixes.
     *
     * @return void
     */
    public function listPrefixes()
    {
        $prefixes = Cache::configured();
        foreach ($prefixes as $prefix) {
            $this->out($prefix);
        }
    }
}
