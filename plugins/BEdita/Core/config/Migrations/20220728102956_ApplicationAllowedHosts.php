<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class ApplicationAllowedHosts extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function change()
    {
        $type = in_array('json', $this->getAdapter()->getColumnTypes()) ? 'json' : 'text';

        $this->table('applications')
            ->addColumn('allowed_hosts', $type, [
                'comment' => 'Hosts allowed for this application, restrictions applied if specified',
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->update();
    }
}
