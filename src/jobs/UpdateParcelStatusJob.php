<?php

namespace craftsnippets\shippingtoolbox\jobs;
use Craft;
use craft\queue\BaseJob;
use craftsnippets\shippingtoolbox\helpers\Common;
use craft\commerce\elements\Order;
use craftsnippets\shippingtoolbox\elements\Shipment;
use craftsnippets\shippingtoolbox\ShippingToolbox;

class UpdateParcelStatusJob extends BaseJob
{
    public array $shipmentIds;
//    public $plugin;
    function execute($queue): void
    {
        $shipmentIds = $this->shipmentIds;
        $query = Shipment::find()->id($shipmentIds);
        $totalElements = $query->count();
        $currentElement = 0;

        try {
            $i = 0;
            foreach ($query->each() as $shipment) {
                $i ++;
                $this->setProgress($queue, $currentElement++ / $totalElements);
                try{
                    $order = $shipment->getShipmentDetails()->order;
                    $plugin = $shipment->getShipmentDetails()->plugin;
                    ShippingToolbox::getInstance()->plugins->updateParcelsStatus($order->id, $plugin->handle);
                } catch(\Exception $e){
                    Common::addLog($e);
                }
            }
        } catch (\Exception $e) {
            // Fail silently
        }
    }

    protected function defaultDescription(): ?string
    {
        return Common::t('Shipping Toolbox updating parcels statuses');
    }
}