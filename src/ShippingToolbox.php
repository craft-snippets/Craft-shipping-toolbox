<?php

namespace craftsnippets\shippingtoolbox;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Addresses;
use craft\services\Elements;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
use craftsnippets\shippingtoolbox\addressFormatters\CustomAddressFormatter;
use craftsnippets\shippingtoolbox\elements\Shipment;
use craftsnippets\shippingtoolbox\elements\ShipmentInfo;
use craftsnippets\shippingtoolbox\models\Settings;
use craftsnippets\shippingtoolbox\services\PluginsService;
use craftsnippets\shippingtoolbox\utilities\ShippingUtility;
use craftsnippets\shippingtoolbox\variables\ShippingToolboxVariable;
use yii\base\Event;

class ShippingToolbox extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => ['plugins' => PluginsService::class],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->attachEventHandlers();

        Craft::$app->onInit(function() {
            // ...
        });

        $this->plugins->insertShippingForm();
        $this->plugins->registerTableAttributes();
        $this->plugins->registerAddressFormatter();
        $this->plugins->saveShipmentInfoEvent();

    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    public function getSettingsResponse(): mixed
    {

        $title = Craft::t('shipping-toolbox','Shipping toolbox settings');

        $context = [
            'settings' => $this->getSettings(),
        ];
        $sidebarContext = [
            'links' => ShippingToolbox::getInstance()->plugins->getSitebarItems(),
        ];

        $screen = Craft::$app->controller->asCpScreen()
            ->title($title)
            ->contentTemplate('shipping-toolbox/settings/settings-main', $context)
            ->pageSidebarTemplate('shipping-toolbox/settings/settings-sidebar', $sidebarContext)
            ->action('plugins/save-plugin-settings')
        ;
        return $screen;
    }

    private function attachEventHandlers(): void
    {
        // register shipment element class
        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = Shipment::class;
            $event->types[] = ShipmentInfo::class;
        });

        // element actions
        $this->plugins->registerElementActions();

        // utility
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITIES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = ShippingUtility::class;
        });

        // variable
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $variable = $event->sender;
                $variable->set('shippingToolbox', ShippingToolboxVariable::class);
            }
        );

    }

}
