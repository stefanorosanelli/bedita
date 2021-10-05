<?php
use Migrations\AbstractMigration;

class OnTree extends AbstractMigration
{
    /**
     * {@inheritDoc}
     */
    public function up()
    {
        $this->table('object_types')
            ->addColumn('on_tree', 'boolean', [
                'comment' => 'Is object type allowed on tree?',
                'default' => true,
                'null' => false,
                'after' => 'core_type',
            ])
            ->update();
    }

    /**
     * {@inheritDoc}
     */
    public function down()
    {
        $this->table('streams')
            ->removeColumn('on_tree')
            ->update();
    }
}
