<?php

namespace craftsnippets\shippingtoolbox\records;

use Craft;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;
use craft\records\Element;

use craftsnippets\shippingtoolbox\helpers\DbTables;

/**
 * Shipment Record record
 *
 * @property int $id ID
 * @property string $uid Uid
 * @property string $dateCreated Date created
 * @property string $dateUpdated Date updated
 * @property string|null $parcelsJson Parcels json
 * @property string|null $propertiesJson Properties json
 * @property int|null $orderId Order ID
 * @property int|null $labelAssetId Label asset ID
 * @property float|null $codAmount Cod amount
 * @property string|null $codCurrency Cod currency
 */
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
