<?php

use Phinx\Migration\AbstractMigration;

class CreatePasswordResets extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $table = $this->table('password_resets', ['id' => false]);
        $table->addColumn('id', 'integer', ['signed' => false, 'identity' => true]);
        $table->addColumn('email', 'string', ['limit' => 255]);
        $table->addColumn('token', 'string', ['limit' => 255, 'null' => true]);
        $table->addColumn('expires_at', 'timestamp', ['null' => true]);

        $table->addIndex(array('email'));

        $table->create();
    }
}
