<?php

namespace craftsnippets\shippingtoolbox\controllers;

use Craft;
use craft\web\Controller;
use craft\commerce\elements\Order;
use craftsnippets\shippingtoolbox\ShippingToolbox;

class ShipmentController extends Controller
{
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    public function actionCreateShipmentDetails()
    {
        $orderId = Craft::$app->getRequest()->getRequiredBodyParam('orderId');
        $pluginHandle = Craft::$app->getRequest()->getRequiredBodyParam('pluginHandle');
        $requestSettings = Craft::$app->getRequest()->getRequiredBodyParam('requestSettings');
        $requestSettings = json_decode($requestSettings, true);

        $result = ShippingToolbox::getInstance()->plugins->createShipmentDetails($orderId, $pluginHandle, $requestSettings);

        return $this->asJson([
            'success' => $result['success'],
            'error' => $result['error'] ?? null,
            'errorType' => $result['errorType'] ?? null,
        ]);
    }

    public function actionRemoveShipmentDetails()
    {
        $orderId = Craft::$app->getRequest()->getRequiredBodyParam('orderId');
        $pluginHandle = Craft::$app->getRequest()->getRequiredBodyParam('pluginHandle');

        $result = ShippingToolbox::getInstance()->plugins->removeShipmentDetails($orderId, $pluginHandle);
        return $this->asJson([
            'success' => $result['success'],
            'error' => $result['error'] ?? null,
            'errorType' => $result['errorType'] ?? null,
            'status' => $result['status'] ?? null,
        ]);
    }

    public function actionRemoveShipmentDetailsFromDatabase()
    {
        $orderId = Craft::$app->getRequest()->getRequiredBodyParam('orderId');
        $pluginHandle = Craft::$app->getRequest()->getRequiredBodyParam('pluginHandle');
        
        $result = ShippingToolbox::getInstance()->plugins->removeShipmentDetailsFromDatabase($orderId, $pluginHandle);
        return $this->asJson([
            'success' => $result['success'],
            'error' => $result['error'] ?? null,
            'errorType' => $result['errorType'] ?? null,
            'status' => $result['status'] ?? null,
        ]);
    }

    public function actionUpdateParcelsStatus()
    {
        $orderId = Craft::$app->getRequest()->getRequiredBodyParam('orderId');
        $pluginHandle = Craft::$app->getRequest()->getRequiredBodyParam('pluginHandle');
        $result = ShippingToolbox::getInstance()->plugins->updateParcelsStatus($orderId, $pluginHandle);
        return $this->asJson([
            'success' => $result['success'],
            'error' => $result['error'] ?? null,
            'errorType' => $result['errorType'] ?? null,
            'status' => $result['status'] ?? null,
        ]);
    }

    public function actionPrintLabels()
    {
        $orderIds = Craft::$app->getRequest()->getRequiredQueryParam('orderIds');
        ShippingToolbox::getInstance()->plugins->printLabels($orderIds);
    }

    public function actionPushParcelsStatusesUpdateJob()
    {
        ShippingToolbox::getInstance()->plugins->updateAllOrdersParcels();
        Craft::$app->getSession()->setNotice(Craft::t('shipping-toolbox', 'Shipping Toolbox update parcels queue job started.'));
        return $this->redirect('utilities/queue-manager');
    }

}