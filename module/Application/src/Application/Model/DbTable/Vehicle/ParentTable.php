<?php

namespace Application\Model\DbTable\Vehicle;

use Application\Db\Table;
use Application\Model\DbTable;
use Application\Model\DbTable\BrandItem;
use Application\Model\DbTable\Vehicle\ParentCache;
use Application\Model\DbTable\Vehicle\Row as VehicleRow;

use Exception;

use Zend_Db_Expr;

class ParentTable extends Table
{
    protected $_name = 'item_parent';
    protected $_primary = ['item_id', 'parent_id'];

    protected $_referenceMap = [
        'Car' => [
            'columns'       => ['item_id'],
            'refTableClass' => 'Car',
            'refColumns'    => ['id']
        ],
        'Parent' => [
            'columns'       => ['parent_id'],
            'refTableClass' => 'Car',
            'refColumns'    => ['id']
        ],
    ];

    const
        TYPE_DEFAULT = 0,
        TYPE_TUNING = 1,
        TYPE_SPORT = 2,
        TYPE_DESIGN = 3;

    public function collectChildIds($id)
    {
        $cpTableName = $this->info('name');
        $adapter = $this->getAdapter();

        $toCheck = [$id];
        $ids = [];

        while (count($toCheck) > 0) {
            $ids = array_merge($ids, $toCheck);

            $toCheck = $adapter->fetchCol(
                $adapter->select()
                    ->from($cpTableName, 'item_id')
                    ->where('parent_id in (?)', $toCheck)
            );
        }

        return array_unique($ids);
    }

    public function collectParentIds($id)
    {
        $cpTableName = $this->info('name');
        $adapter = $this->getAdapter();

        $toCheck = [$id];
        $ids = [];

        while (count($toCheck) > 0) {
            $ids = array_merge($ids, $toCheck);

            $toCheck = $adapter->fetchCol(
                $adapter->select()
                    ->from($cpTableName, 'parent_id')
                    ->where('item_id in (?)', $toCheck)
            );
        }

        return array_unique($ids);
    }

    public function setParentOptions(VehicleRow $car, VehicleRow $parent, array $options)
    {
        $id = (int)$car->id;
        $parentId = (int)$parent->id;

        $row = $this->fetchRow([
            'item_id = ?'   => $id,
            'parent_id = ?' => $parentId
        ]);
        if (! $row) {
            throw new Exception("Parent not found");
        }

        $values = array_replace([
            'type'      => $row->type,
            'catname'   => $row->catname,
            'name'      => $row->name,
        ], $options);

        $row->setFromArray($values);
        $row->save();

        if (isset($options['languages'])) {
            $itemParentLanguageTable = new DbTable\Item\ParentLanguage();
            foreach ($options['languages'] as $language => $langValues) {
                $itemParentLanguageRow = $itemParentLanguageTable->fetchRow([
                    'item_id = ?'   => $id,
                    'parent_id = ?' => $parentId,
                    'language = ?'  => $language
                ]);
                if (! $itemParentLanguageRow) {
                    $itemParentLanguageRow = $itemParentLanguageTable->createRow([
                        'item_id'   => $id,
                        'parent_id' => $parentId,
                        'language'  => $language
                    ]);
                }
                $itemParentLanguageRow->setFromArray([
                    'name' => $langValues['name']
                ]);
                $itemParentLanguageRow->save();
            }
        }
    }

    public function addParent(VehicleRow $car, VehicleRow $parent, array $options = [])
    {
        if (! $parent->is_group) {
            throw new Exception("Only groups can have childs");
        }

        $allowedCombinations = [
            DbTable\Item\Type::VEHICLE => [
                DbTable\Item\Type::VEHICLE => true
            ],
            DbTable\Item\Type::ENGINE => [
                DbTable\Item\Type::ENGINE => true
            ],
            DbTable\Item\Type::CATEGORY => [
                DbTable\Item\Type::VEHICLE  => true,
                DbTable\Item\Type::CATEGORY => true
            ],
            DbTable\Item\Type::TWINS => [
                DbTable\Item\Type::VEHICLE => true
            ],
            DbTable\Item\Type::BRAND => [
                DbTable\Item\Type::BRAND   => true,
                DbTable\Item\Type::VEHICLE => true,
                DbTable\Item\Type::ENGINE  => true,
            ]
        ];

        if (! isset($allowedCombinations[$parent->item_type_id][$car->item_type_id])) {
            throw new Exception("That type of parent is not allowed for this type");
        }

        $id = (int)$car->id;
        $parentId = (int)$parent->id;

        $defaults = [
            'type'           => self::TYPE_DEFAULT,
            'catname'        => $id,
            'manual_catname' => isset($options['catname']),
            'name'           => null
        ];
        $options = array_replace($defaults, $options);
        
        if (! isset($options['type'])) {
            throw new Exception("Type cannot be null");
        }

        $parentIds = $this->collectParentIds($parentId);
        if (in_array($id, $parentIds)) {
            throw new Exception('Cycle detected');
        }

        $row = $this->fetchRow([
            'item_id = ?'   => $id,
            'parent_id = ?' => $parentId
        ]);
        if (! $row) {
            $row = $this->createRow([
                'item_id'        => $id,
                'parent_id'      => $parentId,
                'catname'        => $options['catname'],
                'manual_catname' => $options['manual_catname'] ? 1 : 0,
                'name'           => $options['name'],
                'timestamp'      => new Zend_Db_Expr('now()'),
                'type'           => $options['type']
            ]);
            $row->save();
        }

        $cpcTable = new ParentCache();
        $cpcTable->rebuildCache($car);
    }

    public function removeParent(VehicleRow $car, VehicleRow $parent)
    {
        $id = (int)$car->id;
        $parentId = (int)$parent->id;

        $row = $this->fetchRow([
            'item_id = ?'   => $id,
            'parent_id = ?' => $parentId
        ]);
        if ($row) {
            $row->delete();
        }

        $cpcTable = new ParentCache();
        $cpcTable->rebuildCache($car);
    }

    public function getPathsToBrand($carId, $brand, array $options = [])
    {
        $carId = (int)$carId;
        if (! $carId) {
            throw new Exception("carId not provided");
        }

        $brandId = $brand;
        if ($brandId instanceof \Application\Model\DbTable\BrandRow) {
            $brandId = $brandId->id;
        }


        $breakOnFirst = isset($options['breakOnFirst']) && $options['breakOnFirst'];

        $result = [];

        $limit = $breakOnFirst ? 1 : null;
        $brandItemRows = $this->fetchAll([
            'item_id = ?'   => $carId,
            'parent_id = ?' => $brandId
        ], null, $limit);
        foreach ($brandItemRows as $brandItemRow) {
            $result[] = [
                'car_catname' => $brandItemRow->catname,
                'path'        => []
            ];
        }

        if ($breakOnFirst && count($result)) {
            return $result;
        }

        $parents = $this->fetchAll([
            'item_id = ?' => $carId
        ]);

        foreach ($parents as $parent) {
            $paths = $this->getPathsToBrand($parent->parent_id, $brandId, $options);

            foreach ($paths as $path) {
                $result[] = [
                    'car_catname' => $path['car_catname'],
                    'path'        => array_merge($path['path'], [$parent->catname])
                ];
            }

            if ($breakOnFirst && count($result)) {
                return $result;
            }
        }

        return $result;
    }

    public function getPaths($carId, array $options = [])
    {
        $carId = (int)$carId;
        if (! $carId) {
            throw new Exception("carId not provided");
        }

        $breakOnFirst = isset($options['breakOnFirst']) && $options['breakOnFirst'];
        $db = $this->getAdapter();

        $result = [];
        
        $brand = $db->fetchRow(
            $db->select()
                ->from('item', ['catname'])
                ->where('item_type_id = ?', DbTable\Item\Type::BRAND)
                ->where('id = ?', $carId)
        );
        
        if ($brand) {
            $result[] = [
                'brand_catname' => $brand['catname'],
                'car_catname'   => null,
                'path'          => []
            ];
        }
        
        if ($breakOnFirst && count($result)) {
            return $result;
        }

        $parents = $this->fetchAll([
            'item_id = ?' => $carId
        ]);
        
        foreach ($parents as $parentRow) {
            
            $brand = $db->fetchRow(
                $db->select()
                    ->from('item', ['catname'])
                    ->where('item_type_id = ?', DbTable\Item\Type::BRAND)
                    ->where('id = ?', $parentRow['parent_id'])
            );
            
            if ($brand) {
                $result[] = [
                    'brand_catname' => $brand['catname'],
                    'car_catname'   => $parentRow['catname'],
                    'path'          => []
                ];
            }
        }

        if ($breakOnFirst && count($result)) {
            return $result;
        }

        foreach ($parents as $parent) {
            $paths = $this->getPaths($parent->parent_id, $options);

            foreach ($paths as $path) {
                $result[] = [
                    'brand_catname' => $path['brand_catname'],
                    'car_catname'   => $path['car_catname'],
                    'path'          => array_merge($path['path'], [$parent->catname])
                ];
            }

            if ($breakOnFirst && count($result)) {
                return $result;
            }
        }

        return $result;
    }
}
