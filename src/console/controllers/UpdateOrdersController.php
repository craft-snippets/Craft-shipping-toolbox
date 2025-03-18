<?php

namespace craftsnippets\shippingtoolbox\console\controllers;

use Craft;
use craft\console\Controller;
use yii\console\ExitCode;
use craftsnippets\shippingtoolbox\helpers\Common;
use craftsnippets\shippingtoolbox\ShippingToolbox;

class UpdateOrdersController extends Controller
{
    public function actionUpdateParcelsStatuses()
    {
        Common::addLog('Console command - update parcels statuses', 'shipping-toolbox');
        ShippingToolbox::getInstance()->plugins->updateAllOrdersParcels();
        $this->stdout("Updating parcel statuses..". PHP_EOL);
        return ExitCode::OK;
    }
}
