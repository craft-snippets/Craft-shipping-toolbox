<?php

namespace craftsnippets\shippingtoolbox\elements\conditions;

use Craft;
use craft\elements\conditions\ElementCondition;

/**
 * Shipment Info condition
 */
class ShipmentInfoCondition extends ElementCondition
{
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::conditionRuleTypes(), [
            // ...
        ]);
    }
}
