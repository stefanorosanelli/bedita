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

use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;

Router::plugin(
    'BEdita/API',
    ['path' => '/'],
    function (RouteBuilder $routes) {
        $routes->connect(
            '/roles',
            ['controller' => 'Roles', 'action' => 'index'],
            ['_method' => 'GET']
        );
        $routes->connect(
            '/roles/*',
            ['controller' => 'Roles', 'action' => 'view'],
            ['_method' => 'GET']
        );
        $routes->connect(
            '/roles/:role_id/users',
            ['controller' => 'Users', 'action' => 'index'],
            ['_method' => 'GET']
        );

        $routes->connect(
            '/users',
            ['controller' => 'Users', 'action' => 'index'],
            ['_method' => 'GET']
        );
        $routes->connect(
            '/users/*',
            ['controller' => 'Users', 'action' => 'view'],
            ['_method' => 'GET']
        );
    }
);