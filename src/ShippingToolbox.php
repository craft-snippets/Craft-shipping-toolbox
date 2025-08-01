<?php

namespace craftsnippets\shippingtoolbox;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\services\Addresses;
use craft\services\Elements;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;
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


        if(Craft::$app->plugins->isPluginEnabled('commerce')){
            $this->attachEventHandlers();
            $this->plugins->insertShippingForm();
            $this->plugins->registerTableAttributes();
            $this->plugins->registerAddressFormatter();
            $this->plugins->saveShipmentInfoEvent();
        }

        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots[$this->handle] = __DIR__ . '/templates';
            }
        );

    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    public function getSettingsResponse(): mixed
    {

        $errors = [];
        if(!Craft::$app->plugins->isPluginEnabled('commerce')){
            $pluginUrl = \craft\helpers\UrlHelper::url('plugin-store/commerce');
            $text = Craft::t('shipping-toolbox','plugin is not installed and enabled.');
            $errors[] = "<a href=\"{$pluginUrl}\"><strong>Craft Commerce</strong></a> {$text}";
        }

        // commerce missing
        if(!empty($errors)){
            $textError = Craft::t('shipping-toolbox', 'plugin error');
            $title = $this->name . " - {$textError}";
            $message = join('<br>', $errors);
            return Craft::$app->controller->renderTemplate('_layouts/cp.twig', [
                'title' => $title,
                'content' => $message,
            ]);
        }

        /////

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
