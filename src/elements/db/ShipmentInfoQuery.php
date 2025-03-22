<?php

namespace craftsnippets\shippingtoolbox\elements\db;

use Craft;
use craft\elements\db\ElementQuery;

/**
 * Shipment Info query
 */
class ShipmentInfoQuery extends ElementQuery
{
    protected function beforePrepare(): bool
    {
        // todo: join the `shipmentinfos` table
        // $this->joinElementTable('shipmentinfos');

        // todo: apply any custom query params
        // ...

        return parent::beforePrepare();
    }
}
