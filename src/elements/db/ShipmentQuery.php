<?php

namespace craftsnippets\shippingtoolbox\elements\db;

use Craft;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;

use craftsnippets\shippingtoolbox\helpers\DbTables;

class ShipmentQuery extends ElementQuery
{

    public $parcelsJson;
    public $propertiesJson;
    public $orderId;
    public $labelAssetId;
    public $codAmount;
    public $codCurrency;
    public $pluginHandle;

    protected function beforePrepare(): bool
    {
        $this->joinElementTable(DbTables::SHIPMENTS);
        $this->query->select([
            DbTables::SHIPMENTS_NAME . '.' . 'parcelsJson',
            DbTables::SHIPMENTS_NAME . '.' . 'propertiesJson',
            DbTables::SHIPMENTS_NAME . '.' . 'orderId',
            DbTables::SHIPMENTS_NAME . '.' . 'labelAssetId',
            DbTables::SHIPMENTS_NAME . '.' . 'codAmount',
            DbTables::SHIPMENTS_NAME . '.' . 'codCurrency',
            DbTables::SHIPMENTS_NAME . '.' . 'pluginHandle',
        ]);

        if ($this->orderId) {
            $this->subQuery->andWhere(Db::parseParam(DbTables::SHIPMENTS_NAME . '.' . 'orderId', $this->orderId));
        }

        return parent::beforePrepare();
    }

    public function orderId($value): self
    {
        $this->orderId = $value;
        return $this;
    }

}
