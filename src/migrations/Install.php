<?php

namespace craftsnippets\shippingtoolbox\migrations;

use Craft;
use craft\commerce\migrations\m240228_120911_drop_order_id_and_make_line_item_cascade;
use craft\db\Migration;
use craftsnippets\shippingtoolbox\helpers\DbTables;

class Install extends Migration
{
    public function safeUp()
    {
        $this->createTable(
            DbTables::SHIPMENTS,
            [
                'id' => $this->primaryKey(),
                'uid' => $this->uid(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'parcelsJson' => $this->text(),
                'propertiesJson' => $this->text(),
                'orderId' => $this->integer(),
                'labelAssetId' => $this->integer(),
                'codAmount' => $this->float(),
                'codCurrency' => $this->string(),
                'pluginHandle' => $this->string(),
            ]
        );
        $this->addForeignKey(
            null,
            DbTables::SHIPMENTS,
            'id',
            '{{%elements}}',
            'id',
            'CASCADE',
            null
        );
    }

    public function safeDown()
    {
        $this->dropTableIfExists(DbTables::SHIPMENTS);
    }
}