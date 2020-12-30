<?php
/**
 * BEdita, API-first content management framework
 * Copyright 2016 ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */

namespace BEdita\Core\ORM\Locator;

use Cake\Cache\Cache;
use Cake\Core\App;
use Cake\Datasource\ConnectionManager;
use Cake\Log\LogTrait;
use Cake\ORM\AssociationCollection;
use Cake\ORM\Locator\TableLocator as CakeLocator;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use RuntimeException;

/**
 * Custom table locator for BEdita.
 *
 * @since 4.0.0
 */
class TableLocator extends CakeLocator
{
    use LogTrait;

    protected static $aliasesMap = [
        'users' => 'Users',
        'BEdita/Core.Users' => 'Users',
        'BEdita/Core.ObjectTypes' => 'ObjectTypes',
        'BEdita/Core.Roles' => 'Roles',
    ];

    /**
     * Cache config name for object types.
     *
     * @var string
     */
    // const CACHE_CONFIG = '_bedita_object_types_';

    // protected static $tablesMap = [];

    public function get($alias, array $options = [])
    {
        if (!empty(static::$aliasesMap[$alias])) {
            $alias = static::$aliasesMap[$alias];
            unset($options['className']);
        }

        return parent::get($alias, $options);
    }

    // public function ggget($alias, array $options = [])
    // {
    //     if (isset($this->_instances[$alias])) {
    //         if (!empty($options) && $this->_options[$alias] !== $options) {
    //             throw new RuntimeException(sprintf(
    //                 'You cannot configure "%s", it already exists in the registry.',
    //                 $alias
    //             ));
    //         }

    //         return $this->_instances[$alias];
    //     }

    //     if (!empty($options) || in_array($alias, ['History'])) {
    //         return parent::get($alias, $options);
    //     }

    //     // $instance = $this->createTable((string)$alias);
    //     $instance = Cache::remember(
    //         'table_' . $alias,
    //         function () use ($alias) {
    //             return $this->createTable($alias);
    //         },
    //         self::CACHE_CONFIG
    //     );
    //     $instance->setConnection(ConnectionManager::get($instance::defaultConnectionName()));

    //     $this->_instances[$alias] = $instance;

    //     if ($options['className'] === 'Cake\ORM\Table') {
    //         $this->_fallbacked[$alias] = $this->_instances[$alias];
    //     }

    //     return $this->_instances[$alias];
    // }

    // protected function createTable(string $alias)
    // {
    //     $this->_options[$alias] = [];
    //     list(, $classAlias) = pluginSplit($alias);
    //     $options = ['alias' => $classAlias];

    //     if (isset($this->_config[$alias])) {
    //         $options += $this->_config[$alias];
    //     }

    //     $className = $this->_getClassName($alias, $options);
    //     if ($className) {
    //         $options['className'] = $className;
    //     } else {
    //         if (empty($options['className'])) {
    //             $options['className'] = Inflector::camelize($alias);
    //         }
    //         if (!isset($options['table']) && strpos($options['className'], '\\') === false) {
    //             list(, $table) = pluginSplit($options['className']);
    //             $options['table'] = Inflector::underscore($table);
    //         }
    //         $options['className'] = 'Cake\ORM\Table';
    //     }

    //     // if (empty($options['connection'])) {
    //     //     if (!empty($options['connectionName'])) {
    //     //         $connectionName = $options['connectionName'];
    //     //     } else {
    //     //         /** @var \Cake\ORM\Table $className */
    //     //         $className = $options['className'];
    //     //         $connectionName = $className::defaultConnectionName();
    //     //     }
    //     //     $options['connection'] = ConnectionManager::get($connectionName);
    //     // }
    //     if (empty($options['associations'])) {
    //         $associations = new AssociationCollection($this);
    //         $options['associations'] = $associations;
    //     }
    //     $options['registryAlias'] = $alias;

    //     return $this->_create($options);
    // }

    /**
     * Gets the table class name.
     *
     * @param string $alias The alias name you want to get.
     * @param array $options Table options array.
     * @return string
     */
    protected function _getClassName($alias, array $options = [])
    {
        if (empty($options['className'])) {
            $options['className'] = Inflector::camelize($alias);
        }

        $className = App::className($options['className'], 'Model/Table', 'Table');
        if ($className !== false) {
            return $className;
        }

        $options['className'] = sprintf('BEdita/Core.%s', $options['className']);

        $className = App::className($options['className'], 'Model/Table', 'Table');
        if ($className !== false) {
            return $className;
        }

        // aliases starting with `_` are reserved
        if (substr($alias, 0, 1) !== '_') {
            try {
                $objectTypes = $this->get('ObjectTypes');
                $objectType = $objectTypes->get($alias);
                $options['className'] = $objectType->table;
            } catch (\Exception $e) {
                $this->log(sprintf('%s using alias "%s"', $e->getMessage(), $alias), 'warning');
            }
        }

        return App::className($options['className'], 'Model/Table', 'Table');
        // $className = $this->objectTableClass($alias);
        // if (empty($className)) {
        //     return false;
        // }

        // return App::className($className, 'Model/Table', 'Table');
    }

    /**
     * Retrieve primary key value from plural or singular type name
     *
     * @param string $alias Table alias.
     * @return string|null
     */
    // protected function objectTableClass(string $alias): ?string
    // {
    //     // aliases starting with `_` are reserved
    //     if (substr($alias, 0, 1) === '_') {
    //         return null;
    //     }

    //     if (empty(static::$tablesMap)) {
    //         static::$tablesMap = (array)Cache::remember(
    //             'tables_alias',
    //             function () {
    //                 return $this->tableAliasMap();
    //             },
    //             self::CACHE_CONFIG
    //         );
    //     }

    //     $alias = Inflector::underscore($alias);
    //     if (empty(static::$tablesMap[$alias])) {
    //         $this->log(sprintf('Object table alias %s not found', $alias), 'warning');

    //         return null;
    //     }

    //     return (string)static::$tablesMap[$alias];
    // }

    // protected function tableAliasMap(): array
    // {
    //     $types = $this->get('ObjectTypes')->find()
    //         ->select(['name', 'singular', 'plugin', 'model'])
    //         ->where(['enabled' => true])
    //         ->toArray();
    //     $map = [];
    //     array_walk(
    //         $types,
    //         function ($item) use (&$map) {
    //             /** @var \BEdita\Core\Model\Entity\ObjectType $item */
    //             $table = $item->table;
    //             $map[$item->name] = $table;
    //             $map[$item->singular] = $table;
    //         }
    //     );

    //     return $map;
    // }
}
