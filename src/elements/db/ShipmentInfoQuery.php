<?php

namespace craftsnippets\shippingtoolbox\elements\db;

use Craft;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use craftsnippets\shippingtoolbox\helpers\DbTables;

/**
 * Shipment Info query
 */
class ShipmentInfoQuery extends ElementQuery
{

    public $orderId;
    public $pluginHandle;
    public $propertiesJson;


    protected function beforePrepare(): bool
    {
        $this->joinElementTable(DbTables::SHIPMENT_INFO);
        $this->query->select([
            DbTables::SHIPMENT_INFO_NAME . '.' . 'propertiesJson',
            DbTables::SHIPMENT_INFO_NAME . '.' . 'orderId',
            DbTables::SHIPMENT_INFO_NAME . '.' . 'pluginHandle',
        ]);

        if ($this->orderId) {
            $this->subQuery->andWhere(Db::parseParam(DbTables::SHIPMENT_INFO_NAME . '.' . 'orderId', $this->orderId));
        }

        if ($this->pluginHandle) {
            $this->subQuery->andWhere(Db::parseParam(DbTables::SHIPMENT_INFO_NAME . '.' . 'pluginHandle', $this->pluginHandle));
        }

        return parent::beforePrepare();
    }
    public function orderId($value): self
    {
        $this->orderId = $value;
        return $this;
    }

    public function pluginHandle($value): self
    {
        $this->pluginHandle = $value;
        return $this;
    }
}
