<?php

namespace craftsnippets\shippingtoolbox\elements;

use Craft;
use craft\base\Element;
use craft\elements\User;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\UrlHelper;
use craft\web\CpScreenResponseBehavior;
use craftsnippets\shippingtoolbox\elements\conditions\ShipmentInfoCondition;
use craftsnippets\shippingtoolbox\elements\db\ShipmentInfoQuery;
use craftsnippets\shippingtoolbox\ShippingToolbox;
use yii\base\InvalidConfigException;
use yii\web\Response;

use craftsnippets\shippingtoolbox\records\ShipmentInfoRecord;

/**
 * Shipment Info element type
 */
class ShipmentInfo extends Element
{

    public $orderId;
    public $pluginHandle;
    public $propertiesJson;

    public static function displayName(): string
    {
        return Craft::t('shipping-toolbox', 'Shipment info');
    }

    public static function lowerDisplayName(): string
    {
        return Craft::t('shipping-toolbox', 'shipment info');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('shipping-toolbox', 'Shipment infos');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('shipping-toolbox', 'shipment infos');
    }

    public static function refHandle(): ?string
    {
        return 'shipmentinfo';
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
        return Craft::createObject(ShipmentInfoQuery::class, [static::class]);
    }

    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(ShipmentInfoCondition::class, [static::class]);
    }

    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('shipping-toolbox', 'All shipment infos'),
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
        // If shipment infos should have URLs, define their URI format here
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
        // Define how shipment infos should be routed when their URLs are requested
        return [
            'templates/render',
            [
                'template' => 'site/template/path',
                'variables' => ['shipmentInfo' => $this],
            ]
        ];
    }

    public function canView(User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('viewShipmentInfos');
    }

    public function canSave(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('saveShipmentInfos');
    }

    public function canDuplicate(User $user): bool
    {
        if (parent::canDuplicate($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('saveShipmentInfos');
    }

    public function canDelete(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('deleteShipmentInfos');
    }

    public function canCreateDrafts(User $user): bool
    {
        return true;
    }

    protected function cpEditUrl(): ?string
    {
        return sprintf('shipment-infos/%s', $this->getCanonicalId());
    }

    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('shipment-infos');
    }

    public function prepareEditScreen(Response $response, string $containerId): void
    {
        /** @var Response|CpScreenResponseBehavior $response */
        $response->crumbs([
            [
                'label' => self::pluralDisplayName(),
                'url' => UrlHelper::cpUrl('shipment-infos'),
            ],
        ]);
    }

    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            if (!$isNew) {
                $record = ShipmentInfoRecord::findOne($this->id);
                if (!$record) {
                    throw new InvalidConfigException("Invalid Shipment info ID: $this->id");
                }
            } else {
                $record = new ShipmentInfoRecord();
                $record->id = (int)$this->id;
            }

            $record->orderId = $this->orderId;
            $record->pluginHandle = $this->pluginHandle;
            $record->propertiesJson = $this->propertiesJson;

            $dirtyAttributes = array_keys($record->getDirtyAttributes());
            $record->save(false);
            $this->setDirtyAttributes($dirtyAttributes);
        }

        parent::afterSave($isNew);
    }

    private $contentCache;

    public function getContents()
    {
        if(!is_null($this->contentCache)){
            return $this->contentCache;
        }
        $plugin = ShippingToolbox::getInstance()->plugins->getPluginByHandle($this->pluginHandle);
        if(is_null($plugin)){
            return;
        }
        $class = $plugin::getShipmentInfContentsClass();
        if(is_null($class)){
            return;
        }
        $obj = new $class([
            'jsonData' => $this->propertiesJson,
        ]);
        $this->contentCache = $obj;
        return $this->contentCache;
    }

}
