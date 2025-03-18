<?php

namespace craftsnippets\shippingtoolbox\elements\actions;
use Craft;
use craft\base\ElementAction;
use craftsnippets\shippingtoolbox\helpers\Common;
use craftsnippets\shippingtoolbox\ShippingToolbox;

class UpdateParcelsStatusAction extends ElementAction
{
    public static function displayName(): string
    {
        return Common::t('Shipping Toolbox - update parcels status');
    }

    public function getTriggerHtml(): ?string
    {
        Craft::$app->getView()->registerJsWithVars(fn($type) => <<<JS
            (() => {
                new Craft.ElementActionTrigger({
                    type: $type,
                    bulk: true,
                    validateSelection: (selectedItems) => {
                        var allowed = true;
                        // selectedItems is object instead of regular array
                        for (let key in selectedItems) {
                                if (!isNaN(parseInt(key))) {
                                    let single = selectedItems[key];
                                    if(single.querySelector('[data-craft-shipping-toolbox-update-allowed]') == null){
                                        allowed = false;
                                    }    
                                }
                        }                  
                        return allowed;
                    },
                });
            })();
        JS, [static::class]);
        return null;
    }

    public function performAction(Craft\elements\db\ElementQueryInterface $query): bool
    {
        // cant use queue, need to refresh orders list only after all api calls end during one request
        $orders = $query->all();
        foreach ($orders as $order){
            $shipmentDetails = ShippingToolbox::getInstance()->plugins->getShipmentDetailsForOrder($order);
            ShippingToolbox::getInstance()->plugins->updateParcelsStatus($order->id, $shipmentDetails->plugin->handle);
//            $shipmentDetails->plugin->getPluginService()->updateParcelsStatus($order, $shipmentDetails);
        }
        return true;
    }

    public function  getMessage(): string
    {
        return Common::t('Shipping Toolbox - parcels status updated for the selected orders.');
    }
}