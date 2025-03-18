<?php

namespace craftsnippets\shippingtoolbox\variables;
use craftsnippets\shippingtoolbox\ShippingToolbox;

class ShippingToolboxVariable
{

    public function getParcelShopField()
    {
        return ShippingToolbox::getInstance()->plugins->getParcelShopField();
    }

    public function renderParcelShopSelect($order, $pluginHandle)
    {
        return ShippingToolbox::getInstance()->plugins->renderParcelShopSelect($order, $pluginHandle);
    }

}