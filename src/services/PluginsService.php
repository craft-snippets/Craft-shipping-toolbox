<?php

namespace craftsnippets\shippingtoolbox\services;

use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\Country\CountryRepository;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;
use Craft;
use craft\base\Element;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as CommercePlugin;
use craft\elements\Address;
use craft\elements\Asset;
use craft\events\DefineAttributeHtmlEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\helpers\FileHelper;
use craft\helpers\Queue;
use craft\helpers\UrlHelper;
use craft\models\VolumeFolder;
use craft\services\Addresses;
use craftsnippets\shippingtoolbox\addressFormatters\CustomAddressFormatter;
use craftsnippets\shippingtoolbox\helpers\Common;
use craftsnippets\shippingtoolbox\ShippingToolbox;
use yii\base\Component;
use craftsnippets\shippingtoolbox\elements\Shipment;
use craftsnippets\shippingtoolbox\elements\ShipmentInfo;
use craft\models\Volume;
use craftsnippets\baseshippingplugin\ShippingPlugin;
use yii\base\Event;
use craftsnippets\shippingtoolbox\elements\actions\UpdateParcelsStatusAction;
use craftsnippets\shippingtoolbox\elements\actions\PrintLabelsAction;
use iio\libmergepdf\Merger;
use craftsnippets\shippingtoolbox\jobs\UpdateParcelStatusJob;
use craft\fields\Email;
use craft\fields\PlainText;
use craft\helpers\Template;
use craft\events\ModelEvent;

class PluginsService extends Component
{

    public function getAllShippingPlugins()
    {
        $all = Craft::$app->plugins->getAllPlugins();
        $shippingPlugins = array_filter($all, function($single){
            return $single instanceof ShippingPlugin;
        });
        return $shippingPlugins;
    }

    public function getPluginByHandle($handle)
    {
        foreach ($this->getAllShippingPlugins() as $single) {
            if ($single->handle === $handle) {
                return $single;
            }
        }
        return null;
    }

    public function getSitebarItems($handleActive = null)
    {
        $plugins = $this->getAllShippingPlugins();
        $handle = ShippingToolbox::getInstance()->handle;
        $items = [
            [
                'label' => Craft::t('shipping-toolbox','Main settings'),
                'url' => UrlHelper::cpUrl('settings/plugins/' . $handle),
                'selected' => is_null($handleActive),
            ]
        ];
        foreach ($plugins as $plugin) {
            $items[] = [
                'label' => $plugin->getShippingName(),
                'url' => $plugin->getSettingsUrl(),
                'selected' => $plugin->handle == $handleActive,
            ];
        }
        return $items;
    }

    public function insertShippingForm()
    {
        Craft::$app->view->hook('cp.commerce.order.edit.main-pane', function(array &$context) {
            $order = $context['order'];

            // never on unfinished order page
            if($order->isCompleted == false){
                return;
            }

            $html = '';
            foreach($this->getAllShippingPlugins() as $plugin){
                if($plugin->canIncludeShippingForm($order)){
                    $html .= $plugin->renderShippingForm($order);
                }
            }

            // data of missing plugin
            $allPluginHandles = array_column($this->getAllShippingPlugins(), 'handle');
            $shipmentsForOrder = $shipmentElement = Shipment::find()->orderId($order->id)->all();
            $allShipmentPluginHandles = array_column($shipmentsForOrder, 'pluginHandle');
            $missingHandles = array_diff($allShipmentPluginHandles, $allPluginHandles);
            $missingHandles = array_unique($missingHandles);

            foreach ($missingHandles as $handle){
                $template = 'shipping-toolbox/shipment-no-plugin.twig';
                $contextMissing = [
                    'order' => $order,
                    'handle' => $handle,
                    'reloadOnRequest' => ShippingToolbox::getInstance()->settings->reloadOnRequest,
                ];
                $html .= Craft::$app->view->renderTemplate($template, $contextMissing, Craft::$app->view::TEMPLATE_MODE_CP);
            }

            return $html;
        });
    }

    public function getLocationOptions()
    {
        $options = [
            [
                'label' => Craft::t('shipping-toolbox', 'Select'),
                'value' => null,
            ]
        ];
        $inventoryLocations = CommercePlugin::getInstance()->getInventoryLocations()->getAllInventoryLocations();
        $options = array_merge($options, array_map(function($location){
            return [
                'label' => $location->name,
                'value' => $location->id,
            ];
        }, $inventoryLocations->toArray()));
        return $options;
    }

    public function getSenderAddress(Order $order, array $requestSettings): ?Address
    {
        $senderLocationId = $requestSettings['senderLocationId'] ?? ShippingToolbox::getInstance()->getSettings()->defaultLocationId;
        if(is_null($senderLocationId)){
            return null;
        }
        $pickupLocation = CommercePlugin::getInstance()->getInventoryLocations()->getInventoryLocationById((int)$senderLocationId);
        if(is_null($pickupLocation)){
            return null;
        }
        $pickupAddressCraft = $pickupLocation->getAddress();
        return $pickupAddressCraft;
    }

    public function createShipmentDetails(?int $orderId, $pluginHandle, array $requestSettings = [])
    {
        // datepicker creates hidden input with separate id
        if(isset($requestSettings['pickupDate-date'])){
            $requestSettings['pickupDate'] = $requestSettings['pickupDate-date'];
        }

        // get order
        $order = Order::find()->id($orderId)->one();
        if(is_null($order)){
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('shipping-toolbox','Order does not exist.'),
                'errorType' => null,
            ]);
        }

        // get plugin
        $plugin = Craft::$app->plugins->getPlugin($pluginHandle);
        if(is_null($plugin)){
            return [
                'success' => false,
                'error' => Craft::t('shipping-toolbox','Shipping plugin with this handle does not exist.'),
            ];
        }

        // if shipping method set
        if(is_null($order->shippingMethod)){
            return [
                'success' => false,
                'error' => Craft::t('shipping-toolbox', 'Shipping method is not set for this order.'),
            ];
        }

        // check if this plugin is allowed for this order shipping method
        if(!$plugin->isAllowedForOrder($order)){
            return [
                'success' => false,
                'error' => Craft::t('shipping-toolbox','Shipping plugin is not allowed for this orders shipping method.'),
            ];
        }

        // check if order is completed
        if($order->isCompleted == false){
            return [
                'success' => false,
                'error' => Craft::t('shipping-toolbox','Order is not completed.'),
            ];
        }

        // check if plugin has correct settings
        if(!$plugin->hasCorrectSettings()){
            return [
                'success' => false,
                'error' => Craft::t('shipping-toolbox','This shipping plugin is not correctly configured.'),
            ];
        }

        // check if volume for labels set
        if(is_null($this->getLabelAssetVolume())){
            return [
                'success' => false,
                'error' => Craft::t('shipping-toolbox','Asset volume used for storing parcel labels is not set.'),
            ];
        }

        // validate addresses

        // validate delivery address
        $deliveryAddressCraft = $order->shippingAddress;
        try {
            $plugin->getPluginService()->validateAddress($deliveryAddressCraft, true);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => Craft::t('shipping-toolbox', 'Shipping address:') . ' ' . $e->getMessage(),
            ];
        }

        // validate sender address
        // if required, lack of sender address causes error
        $senderAddress = $this->getSenderAddress($order, $requestSettings);
        if($plugin->senderAddressRequired() == true && is_null($senderAddress)){
            return [
                'success' => false,
                'error' => Craft::t('shipping-toolbox', 'No pickup location selected.'),
                'errorType' => 'Pickup address validation',
            ];
        }

        // if sender not required, validate it only if it exists
        if(!is_null($senderAddress)){
            try {
                $plugin->getPluginService()->validateAddress($senderAddress, false);
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error' => Craft::t('shipping-toolbox', 'Pickup address:') . ' ' . $e->getMessage(),
                ];
            }
        }

        // todo
        // check if shipment of this specific type do not already exist for this order

        $result = $plugin->getPluginService()->createShipmentDetails($order, $requestSettings);
        if($result['success'] == false){
            return $result;
        }

        // make sure status is updated after creation
        if($plugin->updateImmediatelyAfterCreation() == true){
            $result = $this->updateParcelsStatus($orderId, $pluginHandle);
        }else{
//            $shipmentIds = [$result['shipment']->id];
//            $this->pushUpdateStatusJob($shipmentIds);
        }

//        $order->reapplyShippingData();
        unset($result['shipment']);
        return $result;
    }

    public function removeShipmentDetails(?int $orderId, $pluginHandle)
    {
        $order = Order::find()->id($orderId)->one();
        if(is_null($order)){
            return [
                'success' => false,
                'error' => Craft::t('shipping-toolbox', 'Order not found.'),
            ];
        }

        $plugin = Craft::$app->plugins->getPlugin($pluginHandle);
        $shipmentDetails = $plugin->getShipmentDetails($order);

        if(!$shipmentDetails->canRemoveParcels()){
            return [
                'success' => false,
                'error' => Craft::t('shipping-toolbox', 'Cannot remove parcels for this order.'),
            ];
        }

        $result = $plugin->getPluginService()->removeShipmentDetails($order, $shipmentDetails);

        if($result['success'] == false){
            return $result;
        }

        // remove from db
        $shipmentElement = $shipmentDetails->shipmentElement;

        if(is_null($shipmentElement)){
            return [
                'success' => false,
                'error' => Craft::t('shipping-toolbox', 'Shipment for this order was not found.'),
            ];
        }

        $resultRemove = Craft::$app->elements->deleteElement($shipmentElement);
        if(!$resultRemove){
            return [
                'success' => false,
                'error' => implode(' ', $shipmentElement->getErrorSummary(true)),
            ];
        }

        Common::addLog('Remove shipment data for order ID ' . $order->id);

        return [
            'success' => true,
//            'status' => $statusString,
            'status' => 'ok',
        ];
    }

    public function removeShipmentDetailsFromDatabase(?int $orderId, $pluginHandle)
    {
        $order = Order::find()->id($orderId)->one();
        if(is_null($order)){
            return [
                'success' => false,
                'error' => Craft::t('shipping-toolbox', 'Order not found.'),
            ];
        }

        $shipmentElement = Shipment::find()->orderId($orderId)->one();
        if(is_null($shipmentElement)){
            return [
                'success' => false,
                'status' => Craft::t('shipping-toolbox', 'Shipment for this order was not found.'),
            ];
        }

        // remove from db
        $resultRemove = Craft::$app->elements->deleteElement($shipmentElement);
        if(!$resultRemove){
            return [
                'success' => false,
                'status' => implode(' ', $shipmentElement->getErrorSummary(true)),
            ];
        }

        Common::addLog('NO API CALL - Remove shipment data for order ID ' . $order->id);

        return [
            'success' => true,
//            'status' => $statusString,
            'status' => 'ok',
        ];
    }

    public function updateParcelsStatus($orderId, $pluginHandle)
    {
        $order = Order::find()->id($orderId)->one();

        if(is_null($order)){
            return [
                'success' => false,
                'error' => Craft::t('shipping-toolbox', 'Order not found.'),
            ];
        }

        $plugin = Craft::$app->plugins->getPlugin($pluginHandle);
        $shipmentDetails = $plugin->getShipmentDetails($order);

        if(!$shipmentDetails->updateParcelsActionAllowed()){
            return [
                'success' => false,
                'error' => 'Not allowed',
            ];
        }

        $result = $plugin->getPluginService()->updateParcelsStatus($order, $shipmentDetails);

        if($result['success'] == false){
            return $result;
        }

        $json = $result['json'];

        $shipmentElement = $shipmentDetails->shipmentElement;
        $shipmentElement->propertiesJson = $json;
        $saveResult = Craft::$app->elements->saveElement($shipmentElement, true, true, true);

        if(!$saveResult){
            return [
                'success' => false,
                'status' => implode(' ', $shipmentElement->getErrorSummary(true)),
            ];
        }


//        $order->reapplyShippingData();

        // check if delivered, if yes set proper order status
        $allParcelsDelivered = true;
        $parcels = $result['parcels'];
        foreach ($parcels as $parcel){
            if(!$parcel->getIsDelivered()){
                $allParcelsDelivered = false;
            }
        }

        $deliveredStatus = $this->getDeliveredOrderStatus();
        if($allParcelsDelivered && !is_null($deliveredStatus)){
            $order->orderStatusId = $deliveredStatus->id;
        }

        $saveOrderResult = Craft::$app->elements->saveElement($order);

        if(!$saveOrderResult){
            return [
                'success' => false,
                'error' => implode(' ', $order->getErrorSummary(true)),
                'errorType' => 'Order validation',
            ];
        }

        Common::addLog('update order ID ' . $order->id);

        return [
            'success' => true,
//            'status' => $statusString,
            'status' => 'ok',
        ];
    }


    public function saveShipmentData(string $propertiesJson, Order $order, $plugin, $pdfContent = null): Shipment
    {
        $asset = null;
        if(!is_null($pdfContent)){
            $asset = $this->saveShipmentLabel($order, $pdfContent, $plugin);
        }

        $element = new Shipment([
            'propertiesJson' => $propertiesJson,
            'orderId' => $order->id,
            'pluginHandle' => $plugin->handle,
            'labelAssetId' => $asset->id ?? null,
        ]);
        $result = Craft::$app->elements->saveElement($element, true, true, true);
//        var_dump($element->getErrors());
        return $element;
    }

    public function saveShipmentLabel(Order $order, $pdfContent, $plugin)
    {
        $fileName = 'order-' . $order->id . ' - ' . $plugin::getLabelFolderName() . '.pdf';
        $title = 'Parce label for order ' . $order->id . ' - ' . $plugin::getLabelFolderName();

        // folder
        $folderName = 'orders';
        $volumeId = $this->getLabelAssetVolume()->id;

        $folder = Craft::$app->assets->findFolder([
            'volumeId' => $volumeId,
            'path' => $folderName . '/',
        ]);

        // create folder if it is missing
        if ($folder === null) {
            $rootFolder = Craft::$app->assets->getRootFolderByVolumeId($volumeId);
            $folder = new VolumeFolder([
                'parentId' => $rootFolder->id,
                'volumeId' => $volumeId,
                'name' => $folderName,
                'path' => $folderName . '/',
            ]);
            Craft::$app->assets->createFolder($folder);
        }

        // create pdf asset
        $tempPath = Craft::$app->getPath()->getTempPath() . '/' . $order->id . '-temp.pdf';
        FileHelper::writeToFile($tempPath, $pdfContent);

        $asset = new Asset();
        $asset->tempFilePath = $tempPath;
        $asset->filename = $fileName;
        $asset->title = $title;
        $asset->newFolderId = $folder->id;
        $asset->volumeId = $volumeId;
        $asset->avoidFilenameConflicts = true;
        $asset->setScenario(Asset::SCENARIO_CREATE);

        $saveAsset = Craft::$app->getElements()->saveElement($asset);
        return $asset;
    }

    public function getPhoneField()
    {
        $fieldId = ShippingToolbox::getInstance()->getSettings()->phoneFieldId;
        if(!$fieldId){
            return null;
        }

        // if field exists
        $field = Craft::$app->getFields()->getFieldById($fieldId);
        if(!$field){
            return null;
        }

        // if field is assigned to address field layout
        $addressFields = Craft::$app->getFields()->getLayoutByType(Address::class)->getCustomFields();
        $addressFieldsIds = array_column($addressFields, 'id');
        if(!in_array($fieldId, $addressFieldsIds)){
            return null;
        }

        // if text field
        if(get_class($field) != \craft\fields\PlainText::class){
            return null;
        }

        return $field;
    }

    public function getEmailField()
    {
        $fieldId = ShippingToolbox::getInstance()->getSettings()->pickupAddressEmailFieldId;
        if(!$fieldId){
            return null;
        }

        // if field exists
        $field = Craft::$app->getFields()->getFieldById($fieldId);
        if(!$field){
            return null;
        }

        // if field is assigned to address field layout
        $addressFields = Craft::$app->getFields()->getLayoutByType(Address::class)->getCustomFields();
        $addressFieldsIds = array_column($addressFields, 'id');
        if(!in_array($fieldId, $addressFieldsIds)){
            return null;
        }

        // if email field
        if(get_class($field) != Email::class){
            return null;
        }

        return $field;
    }

    public function getLabelAssetVolume(): ?Volume
    {
        $volumeId = ShippingToolbox::getInstance()->getSettings()->labelAssetVolumeId;
        if(is_null($volumeId)){
            return null;
        }
        $volume = Craft::$app->getVolumes()->getVolumeById($volumeId);
        return $volume;
    }

    public function getDeliveredOrderStatus()
    {
        $orderStatusId = ShippingToolbox::getInstance()->getSettings()->deliveredOrderStatusId;
        if(is_null($orderStatusId)){
            return null;
        }
        $status = CommercePlugin::getInstance()->getOrderStatuses()->getOrderStatusById($orderStatusId);
        return $status;
    }

    public function registerElementActions()
    {
        Event::on(
            Order::class,
            Element::EVENT_REGISTER_ACTIONS,
            function (RegisterElementActionsEvent $event) {

//                foreach($this->getAllShippingPlugins() as $plugin){
//                    // create
//                    if(!is_null($plugin->getCreateParcelsActionClass())) {
//                        $event->actions[] = $plugin->getCreateParcelsActionClass();
//                    }
//                }

                // update
                $event->actions[] = UpdateParcelsStatusAction::class;
                // print label
                $event->actions[] = PrintLabelsAction::class;
            }
        );

        Event::on(
            Order::class,
            Element::EVENT_REGISTER_HTML_ATTRIBUTES,
            function (\craft\events\RegisterElementHtmlAttributesEvent $event) {
                $order = $event->sender;

//                foreach($this->getAllShippingPlugins() as $plugin){
//                    $shippingDetails = $plugin->getShipmentDetails($order);
//                    if($shippingDetails->createParcelsActionAllowed()){
//                        $event->htmlAttributes['data-' . $plugin->handle . '-create-allowed'] = true;
//                    }
//                }

                if ($this->canUpdateShipmentForOrder($order)) {
                    $event->htmlAttributes['data-craft-shipping-toolbox-update-allowed'] = true;
                }
                if ($this->canPrintLabelForOrder($order)) {
                    $event->htmlAttributes['data-craft-shipping-print-label-allowed'] = true;
                }
            }

        );

    }

    public function canUpdateShipmentForOrder(Order $order)
    {
        if($order->isCompleted == false){
            return false;
        }
        $shipmentExists = Shipment::find()->orderId($order->id)->exists();
        if(!$shipmentExists){
            return false;
        }
        return true;
    }

    public function canPrintLabelForOrder(Order $order)
    {
        $shipmentExists = Shipment::find()->orderId($order->id)->exists();
        if(!$shipmentExists){
            return false;
        }
        // to do check if shipment has label asset assigned
        return true;
    }

    public function getShipmentDetailsForOrder($order)
    {
        $propertiesJson = null;
        $shipmentElement = Shipment::find()->orderId($order->id)->one();
        if(is_null($shipmentElement)){
            return null;
        }
        $propertiesJson = $shipmentElement->propertiesJson;

        $plugin = $this->getPluginByHandle($shipmentElement->pluginHandle);
        if(is_null($plugin)){
            return null;
        }

        $class = $plugin::getShipmentDetailsClass();
        $obj = new $class([
            'order' => $order,
            'plugin' => $plugin,
            'jsonData' => $propertiesJson,
            'shipmentElement' => $shipmentElement,
        ]);
        return $obj;
    }

    public function printLabels($orderIds)
    {
        $orders = Order::find()->id($orderIds)->all();
        if(empty($orders)){
            echo Craft::t('shipping-toolbox', 'Print labels error - no orders found.');
        }
        $assets = [];
        foreach ($orders as $order){
            $shipmentDetails = ShippingToolbox::getInstance()->plugins->getShipmentDetailsForOrder($order);
            $asset = $shipmentDetails->getPdfAsset();
            if(!is_null($asset)){
                $assets[] = $asset;
            }
        }
        if(empty($assets)){
            echo Craft::t('shipping-toolbox', 'Print labels error - no files found.');
        }
        if(count($assets) == 1){
            $first = reset($assets);
            $pdfContent = $first->getContents();
        }else{
            $merger = new Merger;
            foreach ($assets as $asset){
                $merger->addRaw($asset->getContents());
            }
            $pdfContent = $merger->merge();
        }
        $title = array_column($orders, 'id');
        $title = join('-', $title);
        $title = 'Shipping-toolbox-orders-'.$title.'.pdf';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: inline; filename="'.$title.'"');
        echo $pdfContent;
    }

    public function updateAllOrdersParcels()
    {
        // todo not older tha month
        // todo ignore complete
        $shipmentIds = Shipment::find()->ids();
        $this->pushUpdateStatusJob($shipmentIds);
    }

    public function pushUpdateStatusJob($shipmentIds)
    {

        Queue::push(new UpdateParcelStatusJob([
            'shipmentIds' => $shipmentIds,
        ]));
        Common::addLog('Shipping Toolbox - pushed update parcels job. Shipment IDs: ' . json_encode($shipmentIds));
    }

    public function registerTableAttributes()
    {
        $attributeKey = 'shippingToolboxStatus';

        Event::on(
            Order::class,
            Order::EVENT_REGISTER_TABLE_ATTRIBUTES,
            function(RegisterElementTableAttributesEvent $e) use ($attributeKey){
                $e->tableAttributes[$attributeKey] = [
                    'label' => Craft::t('shipping-toolbox', 'Shipping Toolbox parcels status'),
                ];
            });

        Event::on(
            Order::class,
            Order::EVENT_DEFINE_ATTRIBUTE_HTML,
            function(DefineAttributeHtmlEvent $e) use ($attributeKey){
                if($e->attribute === $attributeKey){
                    $order = $e->sender;
                    $shipment = Shipment::find()->orderId($order->id)->one();
                    if(is_null($shipment)){
                        $e->html = '';
                    }else{
                        if(!is_null($shipment->getShipmentDetails())){
                            $e->html = $shipment->getShipmentDetails()->getIndexColumnStatusesSummary();
                        }else{
                            $e->html = '';
                        }

                    }
                }
            }
        );
    }

    public function getParcelShopCodeForOrder(Order $order, string $pluginHandle): ?string
    {
        $value = $this->getOrderSavedShipmentProperty($order, $pluginHandle, 'parcelShopCode');
        $value = trim($value);
        if(empty($value)){
            return null;
        }
        return $value;
    }

    public function registerAddressFormatter()
    {
        if(ShippingToolbox::getInstance()->settings->useAddressFormatter == false){
            return;
        }
        if(is_null($this->getPhoneField())){
            return;
        }
        Event::on(
            \craft\web\Application::class,
            \craft\web\Application::EVENT_INIT,
            function () {
                Craft::$app->set('addresses', new Addresses([
                    'formatter' => new CustomAddressFormatter(
                        new AddressFormatRepository(),
                        new CountryRepository(),
                        new SubdivisionRepository()
                    ),
                ]));
            }
        );
    }

    public function renderParcelShopSelect(Order $order, string $pluginHandle)
    {
        $plugin = $this->getPluginByHandle($pluginHandle);
        if(is_null($plugin->getParcelShopSelectWidgetTemplate())){
            return null;
        }
        $path = $plugin->getParcelShopSelectWidgetTemplate();
        $context = [
            'order' => $order,
            'plugin' => $plugin,
            'pluginHandle' => $plugin->handle,
        ];
        $html = Craft::$app->view->renderTemplate($path, $context, Craft::$app->view::TEMPLATE_MODE_SITE);
        $html = Template::raw($html);
        return $html;
    }

    const SHIPMENT_INFO_PREFIX = 'shipment-info';

    public static function sanitizeInput($input) {
        // Remove NULL bytes
        $input = str_replace("\0", '', $input);
        // Strip HTML and PHP tags
        $input = strip_tags($input);
        return $input;
    }

    public function saveShipmentInfoEvent()
    {
        // save on order save, if it is incomplete and specific url param is present

        Event::on(
            Order::class,
            Order::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {

                $order = $event->sender;

                // only for incomplete
                if($order->isCompleted == true){
                    return;
                }

                // get input values
                $sentInfo = Craft::$app->getRequest()->getBodyParam(self::SHIPMENT_INFO_PREFIX);
                $shippingPluginHandle = $sentInfo['plugin-handle'] ?? null;
                $infoValues = $sentInfo['values'] ?? null;
                if(is_null($shippingPluginHandle) || is_null($infoValues)){
                    return;
                }

                // if plugin exists and has info details class
                $plugin = $this->getPluginByHandle($shippingPluginHandle);
                if(is_null($plugin) || is_null($plugin->getShipmentInfContentsClass())){
                    return;
                }

                // get existing element or create new
                $shippingInfo = ShipmentInfo::find()
                ->orderId($order->id)
                ->pluginHandle($shippingPluginHandle)->one();

                if(is_null($shippingInfo)){
                    $shippingInfo = new ShipmentInfo([
                        'orderId' => $order->id,
                        'pluginHandle' => $shippingPluginHandle,
                    ]);
                }

                // assign data to info contents obj
                $contentsObj = $shippingInfo->getContents();
                foreach ($contentsObj->getJsonProperties() as $property){
                    $valueKey = $property['value'];
                    if(!isset($infoValues[$valueKey])){
                        continue;
                    }
                    $contentsObj->{$valueKey} = self::sanitizeInput($infoValues[$valueKey]);
                }
                $json = $contentsObj->encodeData();

                // save info element
                $shippingInfo->propertiesJson = $json;
                $result = Craft::$app->elements->saveElement($shippingInfo, true, true, true);

            }
        );

    }

    public function getOrderSavedShipmentInfo($order, $pluginHandle = null)
    {
        $shipmentInfo = ShipmentInfo::find()->orderId($order->id);
        if(!is_null($pluginHandle)){
            $shipmentInfo = $shipmentInfo->pluginHandle($pluginHandle);
        }
        $shipmentInfo = $shipmentInfo->one();
        if(is_null($shipmentInfo)){
            return [];
        }
        $content = $shipmentInfo->getContents()->outputSavedData();
        return $content;
    }

    public function getOrderSavedShipmentProperty($order, $pluginHandle, $property)
    {
        $values = $this->getOrderSavedShipmentInfo($order, $pluginHandle);
        $value = null;
        foreach ($values as $item){
            if($item['key'] == $property){
                $value = $item['value'];
            }
        }
        return $value;
    }

    public function shipmentInfoParamName($param, $pluginHandle)
    {
        return self::SHIPMENT_INFO_PREFIX . '[' . 'values' . ']' . '[' . $param . ']';
    }

    public function renderShipmentInfoWidget($order, $pluginHandle, $param)
    {
        $plugin = $this->getPluginByHandle($pluginHandle);
        if(is_null($plugin) || is_null($plugin->getShipmentInfContentsClass())){
            return null;
        }
        $html = (string)$plugin->getShipmentInfContentsClass()::render($order, $param);
        if(is_null($html)){
            return null;
        }
        $html = Template::raw($html);
        return $html;
    }

}
