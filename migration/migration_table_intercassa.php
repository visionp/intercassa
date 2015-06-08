<?php

use yii\db\Schema;
use yii\db\Migration;

class migration_table_intercassa extends Migration
{
    public function up()
    {

        $tableOptions =NULL;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%intercassa_pays}}', [
            'id'                 =>  Schema::TYPE_PK ,
            'user_id'            =>  Schema::TYPE_INTEGER . ' NOT NULL',
            'invoice_state'      =>  Schema::TYPE_STRING . '(25) NULL',
            'invoice_id'         =>  Schema::TYPE_STRING . '(50) NULL',
            'transaction_id'     =>  Schema::TYPE_STRING . '(50) NULL',
            'checkout_purse_id'  =>  Schema::TYPE_STRING . '(50) NULL',
            'currency'           =>  Schema::TYPE_STRING . '(10) NULL',
            'amount'             =>  Schema::TYPE_DECIMAL . ' NULL',
            'paysystem_price'    =>  Schema::TYPE_DECIMAL . ' NULL',
            'checkout_refund'    =>  Schema::TYPE_DECIMAL . ' NULL',
            'description'        =>  Schema::TYPE_STRING . '(250) NULL',
            'invoice_created'    =>  Schema::TYPE_STRING . '(25) NULL',
            'invoice_processed'  =>  Schema::TYPE_STRING . '(25) NULL',
            'payway_via'         =>  Schema::TYPE_STRING . '(100) NULL',
            'created_at'         =>  Schema::TYPE_INTEGER . ' NULL',
            'updated_at'         =>  Schema::TYPE_INTEGER . ' NULL',
        ], $tableOptions);

        $this->createIndex('idx-intercassa_pays-user_id', '{{%intercassa_pays}}', 'user_id');
        $this->addForeignKey(
            'fk-intercassa_pays-user_id-user-id', '{{%intercassa_pays}}', 'user_id', '{{%user}}', 'id'
        );

    }

    public function down()
    {
        $this->dropTable('{{%intercassa_pays}}');

        return true;
    }

}
