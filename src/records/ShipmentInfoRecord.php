<?php

namespace craftsnippets\shippingtoolbox\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use craftsnippets\shippingtoolbox\helpers\DbTables;
use yii\db\ActiveQueryInterface;

class ShipmentInfoRecord extends ActiveRecord
{
    public static function tableName()
    {
        return DbTables::SHIPMENT_INFO;
    }
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }
}