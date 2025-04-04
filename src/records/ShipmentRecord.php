<?php

namespace craftsnippets\shippingtoolbox\records;

use Craft;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;
use craft\records\Element;

use craftsnippets\shippingtoolbox\helpers\DbTables;

class ShipmentRecord extends ActiveRecord
{
    public static function tableName()
    {
        return DbTables::SHIPMENTS;
    }
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }
}
