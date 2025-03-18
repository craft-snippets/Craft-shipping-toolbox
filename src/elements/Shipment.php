<?php

namespace craftsnippets\shippingtoolbox\elements;

use Craft;
use craft\base\Element;
use craft\elements\User;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\UrlHelper;
use craft\web\CpScreenResponseBehavior;
use craftsnippets\shippingtoolbox\elements\conditions\ShipmentCondition;
use craftsnippets\shippingtoolbox\elements\db\ShipmentQuery;
use yii\web\Response;
use craft\helpers\Db;
use yii\base\InvalidConfigException;
use craftsnippets\shippingtoolbox\helpers\DbTables;
use craftsnippets\shippingtoolbox\records\ShipmentRecord;
use craft\commerce\elements\Order;

use craftsnippets\shippingtoolbox\ShippingToolbox;

class Shipment extends Element
{

    public $parcelsJson;
    public $propertiesJson;
    public $orderId;
    public $labelAssetId;
    public $codAmount;
    public $codCurrency;
    public $pluginHandle;

    public static function displayName(): string
    {
        return Craft::t('shipping-toolbox', 'Shipment');
    }

    public static function lowerDisplayName(): string
    {
        return Craft::t('shipping-toolbox', 'shipment');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('shipping-toolbox', 'Shipments');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('shipping-toolbox', 'shipments');
    }

    public static function refHandle(): ?string
    {
        return 'shipment';
    }

    public static function trackChanges(): bool
    {
        return true;
    }

    public static function hasTitles(): bool
    {
        return false;
    }

    public static function hasUris(): bool
    {
        return true;
    }

    public static function isLocalized(): bool
    {
        return false;
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function find(): ElementQueryInterface
    {
        return Craft::createObject(ShipmentQuery::class, [static::class]);
    }

    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(ShipmentCondition::class, [static::class]);
    }

    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('shipping-toolbox', 'All shipments'),
            ],
        ];
    }

    protected static function defineActions(string $source): array
    {
        // List any bulk element actions here
        return [];
    }

    protected static function includeSetStatusAction(): bool
    {
        return true;
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            'slug' => Craft::t('app', 'Slug'),
            'uri' => Craft::t('app', 'URI'),
            [
                'label' => Craft::t('app', 'Date Created'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'Date Updated'),
                'orderBy' => 'elements.dateUpdated',
                'attribute' => 'dateUpdated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'ID'),
                'orderBy' => 'elements.id',
                'attribute' => 'id',
            ],
            // ...
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'slug' => ['label' => Craft::t('app', 'Slug')],
            'uri' => ['label' => Craft::t('app', 'URI')],
            'link' => ['label' => Craft::t('app', 'Link'), 'icon' => 'world'],
            'id' => ['label' => Craft::t('app', 'ID')],
            'uid' => ['label' => Craft::t('app', 'UID')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('app', 'Date Updated')],
            // ...
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'link',
            'dateCreated',
            // ...
        ];
    }

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }

    public function getUriFormat(): ?string
    {
        // If shipments should have URLs, define their URI format here
        return null;
    }

    protected function previewTargets(): array
    {
        $previewTargets = [];
        $url = $this->getUrl();
        if ($url) {
            $previewTargets[] = [
                'label' => Craft::t('app', 'Primary {type} page', [
                    'type' => self::lowerDisplayName(),
                ]),
                'url' => $url,
            ];
        }
        return $previewTargets;
    }

    protected function route(): array|string|null
    {
        // Define how shipments should be routed when their URLs are requested
        return [
            'templates/render',
            [
                'template' => 'site/template/path',
                'variables' => ['shipment' => $this],
            ]
        ];
    }

    public function canView(User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('viewShipments');
    }

    public function canSave(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('saveShipments');
    }

    public function canDuplicate(User $user): bool
    {
        if (parent::canDuplicate($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('saveShipments');
    }

    public function canDelete(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('deleteShipments');
    }

    public function canCreateDrafts(User $user): bool
    {
        return true;
    }

    protected function cpEditUrl(): ?string
    {
        return sprintf('shipments/%s', $this->getCanonicalId());
    }

    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('shipments');
    }

    public function prepareEditScreen(Response $response, string $containerId): void
    {
        /** @var Response|CpScreenResponseBehavior $response */
        $response->crumbs([
            [
                'label' => self::pluralDisplayName(),
                'url' => UrlHelper::cpUrl('shipments'),
            ],
        ]);
    }

    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            if (!$isNew) {
                $record = ShipmentRecord::findOne($this->id);
                if (!$record) {
                    throw new InvalidConfigException("Invalid Shipment ID: $this->id");
                }
            } else {
                $record = new ShipmentRecord();
                $record->id = (int)$this->id;
            }

            $record->parcelsJson = $this->parcelsJson;
            $record->propertiesJson = $this->propertiesJson;
            $record->orderId = $this->orderId;
            $record->labelAssetId = $this->labelAssetId;
            $record->codAmount = $this->codAmount;
            $record->codCurrency = $this->codCurrency;
            $record->pluginHandle = $this->pluginHandle;

            $dirtyAttributes = array_keys($record->getDirtyAttributes());
            $record->save(false);
            $this->setDirtyAttributes($dirtyAttributes);
        }

        parent::afterSave($isNew);
    }

    private $shipmentDetailsCache;

    public function getShipmentDetails()
    {
        if(!is_null($this->shipmentDetailsCache)){
            return $this->shipmentDetailsCache;
        }

        if(is_null($this->orderId)){
            return null;
        }

        $order = Order::find()->id($this->orderId)->one();
        $plugin = ShippingToolbox::getInstance()->plugins->getPluginByHandle($this->pluginHandle);
        if(is_null($plugin) or is_null($order)){
            return;
        }

        $class = $plugin::getShipmentDetailsClass();
        $obj = new $class([
            'order' => $order,
            'plugin' => $plugin,
            'jsonData' => $this->propertiesJson,
            'shipmentElement' => $this,
        ]);
        $this->shipmentDetailsCache = $obj;
        return $this->shipmentDetailsCache;
    }

}
