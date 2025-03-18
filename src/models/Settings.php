<?php

namespace craftsnippets\shippingtoolbox\models;

use Craft;
use craft\base\Model;
use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\elements\Order;
use craft\elements\Address;
use craft\fields\Email;
use craft\fields\PlainText;
use craftsnippets\shippingtoolbox\ShippingToolbox;

/**
 * Shipping Toolbox settings
 */
class Settings extends Model
{
    public $phoneFieldId;
    public ?int $labelAssetVolumeId = null;
    public ?int $deliveredOrderStatusId = null;
    public ?int $defaultLocationId = null;
    public bool $showWidgetWhenNotAllowed = true;
    public $reloadOnRequest = true;
    public ?int $pickupAddressEmailFieldId = null;
    public $parcelShopCodeFieldId;
    public bool $useAddressFormatter = true;


    public function attributeLabels()
    {
        return [
            'phoneFieldId' => Craft::t('shipping-toolbox', 'Phone number field'),
            'labelAssetVolumeId' => Craft::t('shipping-toolbox', 'Asset volume used for storage of parcel labels'),
            'deliveredOrderStatusId' => Craft::t('shipping-toolbox', 'Order status that will be set when parcels status will be updated to "delivered" status.'),
            'defaultLocationId' => Craft::t('shipping-toolbox', 'Default sender address'),
//            'showWidgetWhenNotAllowed' => Craft::t('shipping-toolbox', ''),
//            'reloadOnRequest' => Craft::t('shipping-toolbox', ''),
            'pickupAddressEmailFieldId' => Craft::t('shipping-toolbox', 'Pickup address email field'),
            'parcelShopCodeFieldId' => Craft::t('shipping-toolbox', 'Parcel shop code field'),
            'useAddressFormatter' => Craft::t('shipping-toolbox', 'Adjust address summary formatting to display phone number'),
        ];
    }

    public function getVolumeOptions()
    {
        $volumes = Craft::$app->volumes->getAllVolumes();
        $options = array_map(function($single){
            return [
                'label' => $single->name,
                'value' => $single->id,
            ];
        }, $volumes);

        array_unshift($options, [
            'label' => Craft::t('shipping-toolbox', 'Select.'),
            'value' => null,
        ]);
        return $options;
    }

    public function getPhoneFieldOptions()
    {
        $fields = Craft::$app->getFields()->getLayoutByType(Address::class)->getCustomFields();
        $properFields = array_filter($fields, function($single){
            return get_class($single) == PlainText::class;
        });
        $options = [
            [
                'label' => Craft::t('shipping-toolbox', 'Select'),
                'value' => null,
            ]
        ];
        foreach($properFields as $single){
            $options[] = [
                'label' => $single->name,
                'value' => $single->id,
            ];
        }
        return $options;
    }

    public function getLocationOptions()
    {
        return ShippingToolbox::getInstance()->plugins->getLocationOptions();
    }

    public function getPickupAddressEmailFieldOptions()
    {
        $fields = Craft::$app->getFields()->getLayoutByType(Address::class)->getCustomFields();
        $properFields = array_filter($fields, function($single){
            return get_class($single) == Email::class;
        });
        $options = [
            [
                'label' => Craft::t('shipping-toolbox', 'Select'),
                'value' => null,
            ]
        ];
        foreach($properFields as $single){
            $options[] = [
                'label' => $single->name,
                'value' => $single->id,
            ];
        }
        return $options;
    }

    public function getDeliveredOrderStatusIdOptions()
    {
        $options = [
            [
                'label' => Craft::t('shipping-toolbox', 'Select'),
                'value' => null,
            ]
        ];
        $statuses = CommercePlugin::getInstance()->getOrderStatuses()->getAllOrderStatuses();
        $options = array_merge($options, array_map(function($status){
            return [
                'label' => $status->name,
                'value' => $status->id,
            ];
        }, $statuses->toArray()));
        return $options;
    }

    public function getParcelShopCodeOptions()
    {
        $fields = Craft::$app->getFields()->getLayoutByType(Order::class)->getCustomFields();
        $properFields = array_filter($fields, function($single){
            return get_class($single) == PlainText::class;
        });
        $options = [
            [
                'label' => Craft::t('shipping-toolbox', 'Select'),
                'value' => null,
            ]
        ];
        foreach($properFields as $single){
            $options[] = [
                'label' => $single->name,
                'value' => $single->id,
            ];
        }
        return $options;
    }

    public function attributeInstructions()
    {
        return [
            'parcelShopCodeFieldId' => Craft::t('shipping-toolbox', 'Plain text field assigned to order field layout, where shipping plugins can store parcel shop delivery code.'),
            'pickupAddressEmailFieldId' => Craft::t('shipping-toolbox', 'Select one of the email fields assigned to the address model. Value of this field will be used for the parcels pickup address email. It will NOT be used for delivery address - delivery address uses clients account email.'),
            'phoneFieldId' => Craft::t('shipping-toolbox', 'Select one of the plain text fields assigned to the address model. Value of this field will be used for the parcels generation request.'),
            'defaultLocationId' => Craft::t('shipping-toolbox', 'Select the location which address will be used as default sender address when creating parcels for orders. This setting can be overridden for the specific orders.'),
        ];
    }

    public function attributeOptions()
    {
        return [
            'parcelShopCodeFieldId' => $this->getParcelShopCodeOptions(),
            'pickupAddressEmailFieldId' => $this->getPickupAddressEmailFieldOptions(),
            'labelAssetVolumeId' => $this->getVolumeOptions(),
            'phoneFieldId' => $this->getPhoneFieldOptions(),
            'defaultLocationId' => $this->getLocationOptions(),
            'deliveredOrderStatusId' => $this->getDeliveredOrderStatusIdOptions(),
        ];
    }

}
