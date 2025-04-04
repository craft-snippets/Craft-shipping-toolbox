<?php

namespace craftsnippets\shippingtoolbox\elements\conditions;

use Craft;
use craft\elements\conditions\ElementCondition;

/**
 * Shipment condition
 */
class ShipmentCondition extends ElementCondition
{
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::conditionRuleTypes(), [
            // ...
        ]);
    }
}
