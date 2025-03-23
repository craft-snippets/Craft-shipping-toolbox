<?php

namespace craftsnippets\shippingtoolbox\variables;
use craftsnippets\shippingtoolbox\ShippingToolbox;

class ShippingToolboxVariable
{
    public function renderParcelShopSelect($order, $pluginHandle)
    {
        return ShippingToolbox::getInstance()->plugins->renderParcelShopSelect($order, $pluginHandle);
    }

    public function getOrderSavedShipmentInfo($order, $pluginHandle = null)
    {
        return ShippingToolbox::getInstance()->plugins->getOrderSavedShipmentInfo($order, $pluginHandle);
    }

    public function shipmentInfoParamName($param, $pluginHandle)
    {
        return ShippingToolbox::getInstance()->plugins->shipmentInfoParamName($param, $pluginHandle);
    }

    public function getOrderSavedShipmentProperty($order, $pluginHandle, $param)
    {
        return ShippingToolbox::getInstance()->plugins->getOrderSavedShipmentProperty($order, $pluginHandle, $param);
    }

    public function renderShipmentInfoWidget($order, $pluginHandle, $param)
    {
        return ShippingToolbox::getInstance()->plugins->renderShipmentInfoWidget($order, $pluginHandle, $param);
    }
}