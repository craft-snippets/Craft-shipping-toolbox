<?php

namespace craftsnippets\shippingtoolbox\elements\actions;
use Craft;
use craft\base\ElementAction;
use Craft\elements\db\ElementQueryInterface;
use craftsnippets\shippingtoolbox\helpers\Common;

use craftsnippets\shippingtoolbox\elements\actions\CreateParcelsActionInterface;

abstract class BaseCreateParcelsAction extends ElementAction implements CreateParcelsActionInterface
{
//    public static function getPlugin()
//    {
//        return null;
//    }

    public static function displayName(): string
    {
        $name = static::getPlugin()::getShippingName();
        return Common::t('{name} shipping - create parcels', [
            'name' => $name,
        ]);
    }

    public function getTriggerHtml(): ?string
    {
        $handle = static::getPlugin()->handle;
        Craft::$app->getView()->registerJsWithVars(fn($type, $handle) => <<<JS
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
                                    if(single.querySelector('[data-'+$handle+'-create-allowed]') == null){
                                        allowed = false;
                                    }    
                                }
                        }                  
                        return allowed;
                    },
                });
            })();
        JS, [
            static::class,
            $handle,
        ]);
        return null;
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        $name = static::getPlugin()::getShippingName();

        $orders = $query->all();
        $successAll = true;
        $errors = [];
        foreach ($orders as $order){
            $result = static::getPlugin()->getPluginService()->createShipmentDetails($order);
            if($result['success'] == false){
                $successAll = false;
                $errors[] = $result['error'];
            }
        }

        if($successAll == true){
            $message = Common::t('{name} parcels created for the selected orders.', [
                'name' => $name,
            ]);
        }else{
            $message = Common::t('Could not create {name} parcels for the all selected orders. Errors:', [
                'name' => $name,
            ]);
            $errors = join(', ', $errors);
            $message = $message . ' ' . $errors;
        }

        $this->setMessage($message);
        return $successAll;
    }

    public function  getConfirmationMessage(): string
    {
        $name = static::getPlugin()::getShippingName();
        return Common::t('Are you sure you want to create {name} parcels for the selected orders? Default settings will be used for the each parcel.', [
            'name' => $name,
        ]);
    }
}