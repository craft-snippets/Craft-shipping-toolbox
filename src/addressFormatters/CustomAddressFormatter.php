<?php

namespace craftsnippets\shippingtoolbox\addressFormatters;
use CommerceGuys\Addressing\AddressInterface;
use CommerceGuys\Addressing\Formatter\DefaultFormatter;
use craftsnippets\shippingtoolbox\ShippingToolbox;
use Craft;

class CustomAddressFormatter extends DefaultFormatter
{
    public function format(AddressInterface $address, array $options = []): string
    {
        $string = parent::format($address, $options);

        // add phone field
        $phoneField = ShippingToolbox::getInstance()->plugins->getPhoneField();
        if(!is_null($phoneField)){
            $string = $string . Craft::t('shipping-toolbox','Tel. ') . $address->{$phoneField->handle};
        }
        return $string;
    }
}