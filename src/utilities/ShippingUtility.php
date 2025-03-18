<?php

namespace craftsnippets\shippingtoolbox\utilities;
use Craft;
use craft\base\Utility;
use craft\helpers\UrlHelper;

class ShippingUtility extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('shipping-toolbox', 'Shipping Toolbox utility');
    }

    static function id(): string
    {
        return 'shipping-toolbox-utility';
    }

    public static function iconPath(): ?string
    {
        return null;
    }

    static function contentHtml(): string
    {
        $txt = Craft::t('shipping-toolbox', 'Update parcels statuses');
        $url = UrlHelper::actionUrl('shipping-toolbox/shipment/push-parcels-statuses-update-job');
        $html = '<a href="'.$url.'" type="submit" class="btn submit">'.$txt.'</a>';
        return $html;
    }
}