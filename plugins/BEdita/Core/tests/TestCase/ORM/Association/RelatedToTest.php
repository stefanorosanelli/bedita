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

namespace BEdita\Core\Test\TestCase\ORM\Association;

use BEdita\Core\ORM\Association\RelatedTo;
use Cake\Database\Expression\QueryExpression;
use Cake\ORM\Query;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * @coversDefaultClass \BEdita\Core\ORM\Association\RelatedTo
 */
class RelatedToTest extends TestCase
{

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.BEdita/Core.ObjectTypes',
        'plugin.BEdita/Core.Relations',
        'plugin.BEdita/Core.RelationTypes',
        'plugin.BEdita/Core.Objects',
        'plugin.BEdita/Core.Profiles',
        'plugin.BEdita/Core.Locations',
        'plugin.BEdita/Core.ObjectRelations',
    ];

    /**
     * Data provider for `testGetSubQueryForMatching` test case.
     *
     * @return array
     */
    public function getSubQueryForMatchingProvider()
    {
        return [
            'simple' => [
                [
                    2 => 'title one',
                    3 => 'title two',
                ],
                'Documents',
                'Test',
            ],
            'simple (inverse)' => [
                [
                    4 => 'Gustavo',
                ],
                'Profiles',
                'InverseTest',
            ],
            'with conditions' => [
                [
                    2 => 'title one',
                ],
                'Documents',
                'Test',
                [
                    'conditions' => [
                        'Test.title' => 'title two',
                    ],
                ],
            ],
            'with query builder' => [
                [
                    2 => 'title one',
                ],
                'Documents',
                'Test',
                [
                    'queryBuilder' => function (Query $query) {
                        return $query->where([
                            'Test.title' => 'title two',
                        ]);
                    },
                ],
            ],
        ];
    }

    /**
     * Test method to obtain sub-query for matching.
     *
     * @param array $expected Expected result.
     * @param string $table Table name.
     * @param string $association Association name.
     * @param array $options Additional options.
     * @return void
     *
     * @dataProvider getSubQueryForMatchingProvider()
     * @covers ::getSubQueryForMatching()
     */
    public function testGetSubQueryForMatching(array $expected, $table, $association, array $options = [])
    {
        $table = TableRegistry::getTableLocator()->get($table);
        $association = $table->getAssociation($association);
        if (!($association instanceof RelatedTo)) {
            static::fail('Wrong association type');

            return;
        }

        $subQuery = $association->getSubQueryForMatching($options);

        static::assertInstanceOf(Query::class, $subQuery);

        $result = $table->find('list')
            ->where(function (QueryExpression $exp) use ($table, $subQuery) {
                return $exp->in($table->aliasField($table->getPrimaryKey()), $subQuery);
            })
            ->toArray();

        static::assertEquals($expected, $result);
    }

    /**
     * Data provider for `testIsSourceAbstract()`
     *
     * @return array
     */
    public function isAbstractProvider()
    {
        return [
            'abstract' => [
                true,
                'Objects',
            ],
            'concrete' => [
                false,
                'Profiles',
            ],
            'concreteBecauseNotAnObjectType' => [
                false,
                'Relations',
            ],
        ];
    }

    /**
     * Test if source table is abstract
     *
     * @param bool $expected The expected value
     * @param string $table The source table name
     * @return void
     *
     * @dataProvider isAbstractProvider
     * @covers ::isSourceAbstract()
     * @covers ::isAbstract()
     */
    public function testIsSourceAbstract($expected, $table)
    {
        $relatedTo = new RelatedTo('SourceAbstract');
        $relatedTo->setSource(TableRegistry::getTableLocator()->get($table));
        static::assertSame($expected, $relatedTo->isSourceAbstract());
    }

    /**
     * Test if target table is abstract
     *
     * @param bool $expected The expected value
     * @param string $table The target table name
     * @return void
     *
     * @dataProvider isAbstractProvider
     * @covers ::isTargetAbstract()
     * @covers ::isAbstract()
     */
    public function testIsTargetAbstract($expected, $table)
    {
        $relatedTo = new RelatedTo('SourceAbstract');
        $relatedTo->setTarget(TableRegistry::getTableLocator()->get($table));
        static::assertSame($expected, $relatedTo->isTargetAbstract());
    }

    /**
     * Data provider for testIsInverse()
     *
     * @return array
     */
    public function isInverseProvider(): array
    {
        return [
            'direct' => [
                false,
                [
                    'foreignKey' => 'left_id',
                ],
            ],
            'inverse' => [
                true,
                [
                    'foreignKey' => 'right_id',
                ],
            ],
            'inverseCustom' => [
                true,
                [
                    'foreignKey' => 'left_id',
                    'inverseKey' => 'left_id',
                ],
            ],
            'inverseMultiCustom' => [
                true,
                [
                    'foreignKey' => ['left_id', 'custom_key'],
                    'inverseKey' => ['left_id', 'custom_key'],
                ],
            ],
        ];
    }

    /**
     * Test if related association is inverse.
     *
     * @param bool $expected The value expected.
     * @param array $options The options for the association.
     * @return void
     *
     * @dataProvider isInverseProvider()
     * @covers ::isInverse()
     * @covers ::_options()
     * @covers ::setInverseKey()
     * @covers ::getInverseKey()
     */
    public function testIsInverse($expected, $options): void
    {
        $relatedTo = new RelatedTo('Alias', $options);
        static::assertEquals($expected, $relatedTo->isInverse());
    }
}
