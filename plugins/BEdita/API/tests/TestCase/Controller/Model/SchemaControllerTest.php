<?php
/**
 * BEdita, API-first content management framework
 * Copyright 2017 ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */

namespace BEdita\API\Test\TestCase\Controller\Model;

use BEdita\API\TestSuite\IntegrationTestCase;

/**
 * {@see \BEdita\API\Controller\Model\SchemaController} Test Case
 *
 * @coversDefaultClass \BEdita\API\Controller\Model\SchemaController
 */
class SchemaControllerTest extends IntegrationTestCase
{
    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.BEdita/Core.media',
        'plugin.BEdita/Core.locations',
        'plugin.BEdita/Core.property_types',
        'plugin.BEdita/Core.properties',
    ];

    /**
     * Data provider for `testJsonSchema`
     *
     * @return array
     */
    public function jsonSchemaProvider()
    {
        return [
            'objects' => [
                'objects',
            ],
            'roles' => [
                'roles',
                'application/schema+json'
            ],
            'users' => [
                'users',
                'text/html'
            ],
        ];
    }

    /**
     * Test `jsonSchema` method.
     *
     * @param string $type Type name
     * @param string $accept Accept request header
     * @return void
     *
     * @covers ::jsonSchema()
     * @covers ::initialize()
     * @dataProvider jsonSchemaProvider
     */
    public function testJsonSchema($type, $accept = '')
    {
        $expected = [
            'definitions' => [],
            '$id' => "http://api.example.com/model/schema/$type",
            '$schema' => 'http://json-schema.org/draft-06/schema#',
            'type' => 'object',
        ];
        $headers = empty($accept) ? [] : ['Accept' => $accept];
        $this->configRequestHeaders('GET', $headers);
        $this->get("/model/schema/$type");
        $result = json_decode((string)$this->_response->getBody(), true);

        $this->assertResponseCode(200);
        $this->assertContentType('application/schema+json');
        static::assertArraySubset($expected, $result);
    }
}