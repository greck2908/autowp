<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable;
use Application\Model\DbTable\Picture;
use Application\Model\DbTable\Vehicle\ParentTable as VehicleParent;
use Application\Paginator\Adapter\Zend1DbTableSelect;

use Zend_Db_Expr;

class CategoryController extends AbstractActionController
{
    private $otherCategoryName = 'Other';

    private $cache;

    private $textStorage;

    /**
     * @var DbTable\Vehicle
     */
    private $itemTable;

    /**
     * @var DbTable\Vehicle\Item
     */
    private $itemLanguageTable;

    public function __construct($cache, $textStorage)
    {
        $this->cache = $cache;
        $this->textStorage = $textStorage;

        $this->itemTable = new DbTable\Vehicle();
        $this->itemLanguageTable = new DbTable\Vehicle\Language();
    }

    private function getOwnVehiclesAndEnginesCount($categoryId)
    {
        $db = $this->itemTable->getAdapter();

        //TODO: group by cars.id
        $select = $db->select()
            ->from('cars', new Zend_Db_Expr('COUNT(1)'))
            ->where('cars.item_type_id IN (?)', [
                DbTable\Item\Type::ENGINE,
                DbTable\Item\Type::VEHICLE
            ])
            ->where('not cars.is_group')
            ->join('car_parent', 'cars.id = car_parent.car_id', null)
            ->where('car_parent.parent_id = ?', $categoryId);

        return $db->fetchOne($select);
    }

    private function getVehiclesAndEnginesCount($categoryId)
    {
        $db = $this->itemTable->getAdapter();

        $select = $db->select()
            ->from('cars', new Zend_Db_Expr('COUNT(1)'))
            ->where('cars.item_type_id IN (?)', [
                DbTable\Item\Type::ENGINE,
                DbTable\Item\Type::VEHICLE
            ])
            ->where('not cars.is_group')
            ->join('item_parent_cache', 'cars.id = item_parent_cache.item_id', null)
            ->where('item_parent_cache.parent_id = ?', $categoryId);

        return $db->fetchOne($select);
    }

    public function indexAction()
    {
        $language = $this->language();

        $key = 'CATEGORY_INDEX48_' . $language;

        $categories = $this->cache->getItem($key, $success);
        if (! $success) {
            $categories = [];

            $rows = $this->itemTable->fetchAll(
                $this->itemTable->select(true)
                    ->where('item_type_id = ?', DbTable\Item\Type::CATEGORY)
                    ->joinLeft('car_parent', 'cars.id = car_parent.car_id', null)
                    ->where('car_parent.parent_id is null')
                    ->order('name')
            );

            foreach ($rows as $row) {
                $langRow = $this->itemLanguageTable->fetchRow([
                    'language = ?' => $language,
                    'car_id = ?'   => $row->id
                ]);

                $carsCount = $this->getVehiclesAndEnginesCount($row->id);

                $categories[] = [
                    'id'             => $row->id,
                    'url'            => $this->url()->fromRoute('categories', [
                        'action'           => 'category',
                        'category_catname' => $row->catname,
                    ]),
                    'name'           => $langRow ? $langRow->name : $row->name,
                    'short_name'     => $langRow ? $langRow->name : $row->name,//$langRow ? $langRow->short_name : $row->short_name,
                    'cars_count'     => $carsCount,
                    'new_cars_count' => $carsCount //$row->getWeekCarsCount(),
                ];
            }

            $this->cache->setItem($key, $categories);
        }

        $pictureTable = $this->catalogue()->getPictureTable();
        foreach ($categories as &$category) {
            $picture = $pictureTable->fetchRow(
                $pictureTable->select(true)
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                    ->where('pictures.status IN (?)', [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW])
                    ->where('item_parent_cache.parent_id = ?', $category['id'])
                    ->order([
                        new Zend_Db_Expr('picture_item.perspective_id = 7 DESC'),
                        new Zend_Db_Expr('picture_item.perspective_id = 8 DESC'),
                        new Zend_Db_Expr('picture_item.perspective_id = 1 DESC')
                    ])
                    ->limit(1)
            );

            $image = null;
            if ($picture) {
                $image = $this->imageStorage()->getFormatedImage($picture->getFormatRequest(), 'picture-thumb');
            }

            $category['top_picture'] = [
                'image' => $image
            ];
        }

        return [
            'categories' => $categories
        ];
    }

    private function categoriesMenuActive(&$menu, $currentCategory, $isOther)
    {
        $activeFound = false;
        foreach ($menu as &$item) {
            $item['active'] = false;

            if (($item['isOther'] ? $isOther : ! $isOther) && ($item['id'] == $currentCategory->id)) {
                $activeFound = true;
                $item['active'] = true;
            }
            if ($this->categoriesMenuActive($item['categories'], $currentCategory, $isOther)) {
                $activeFound = true;
                $item['active'] = true;
            }
        }

        return $activeFound;
    }

    private function categoriesMenu($parent, $language, $maxDeep)
    {
        $categories = [];

        if ($maxDeep > 0) {

            $select = $this->itemTable->select(true)
                ->where('cars.item_type_id = ?', DbTable\Item\Type::CATEGORY)
                ->order($this->catalogue()->carsOrdering());

            if ($parent) {
                $select
                    ->join('car_parent', 'cars.id = car_parent.car_id', null)
                    ->where('car_parent.parent_id = ?', $parent->id);
            } else {
                $select
                    ->joinLeft('car_parent', 'cars.id = car_parent.car_id', null)
                    ->where('car_parent.parent_id IS NULL');
            }

            $rows = $this->itemTable->fetchAll($select);
            foreach ($rows as $row) {
                $langRow = $this->itemLanguageTable->fetchRow([
                    'language = ?' => $language,
                    'car_id = ?'   => $row->id
                ]);

                $carsCount = $this->getVehiclesAndEnginesCount($row->id);

                $category = [
                    'id'             => $row->id,
                    'url'            => $this->url()->fromRoute('categories', [
                        'action'           => 'category',
                        'category_catname' => $row->catname,
                        'other'            => false,
                        'car_id'           => null,
                        'page'             => null
                    ]),
                    'name'           => $langRow ? $langRow->name : $row->name,
                    'short_name'     => $langRow ? $langRow->name : $row->name,//$langRow ? $langRow->short_name : $row->short_name,
                    'cars_count'     => $carsCount,
                    'new_cars_count' => 0,
                    'categories'     => $this->categoriesMenu($row, $language, $maxDeep - 1),
                    'isOther'        => false
                ];

                $categories[] = $category;
            }

            if ($parent && count($categories)) {
                $ownCarsCount = $this->getOwnVehiclesAndEnginesCount($parent->id);
                if ($ownCarsCount > 0) {
                    $categories[] = [
                        'id'             => $parent->id,
                        'url'            => $this->url()->fromRoute('categories', [
                            'action'           => 'category',
                            'category_catname' => $parent->catname,
                            'other'            => true,
                            'car_id'           => null,
                            'page'             => null
                        ]),
                        'short_name'     => $this->otherCategoryName,
                        'cars_count'     => $ownCarsCount,
                        'new_cars_count' => $ownCarsCount, //$parent->getWeekOwnCarsCount(),
                        'isOther'        => true,
                        'categories'     => []
                    ];
                }
            }
        }

        usort($categories, function ($a, $b) {
            if ($a["short_name"] == $this->otherCategoryName) {
                return 1;
            }
            if ($b["short_name"] == $this->otherCategoryName) {
                return -1;
            }
            return strcmp($a["short_name"], $b["short_name"]);
        });

        return $categories;
    }

    private function doCategoryAction($callback)
    {
        $language = $this->language();

        $currentCategory = $this->itemTable->fetchRow([
            'catname = ?' => (string)$this->params('category_catname')
        ]);
        $isOther = (bool)$this->params('other');

        if (! $currentCategory) {
            return $this->notFoundAction();
        }

        $categoryLang = $this->itemLanguageTable->fetchRow([
            'language = ?' => $language,
            'car_id = ?'   => $currentCategory->id
        ]);

        $breadcrumbs = [[
            'name' => $categoryLang && $categoryLang->name ? $categoryLang->name : $currentCategory->name,
            'url'  => $this->url()->fromRoute('categories', [
                'action'           => 'category',
                'category_catname' => $currentCategory->catname,
                'other'            => false,
                'car_id'           => null,
                'path'             => [],
                'page'             => 1
            ])
        ]];

        $topCategory = $currentCategory;

        while (true) {
            $parentCategory =$this->itemTable->fetchRow(
                $this->itemTable->select(true)
                    ->join('car_parent', 'cars.id = car_parent.parent_id', null)
                    ->where('car_parent.car_id = ?', $topCategory->id)
            );
            if (!$parentCategory) {
                break;
            }
            
            $topCategory = $parentCategory;

            $categoryLang = $this->itemLanguageTable->fetchRow([
                'language = ?' => $language,
                'car_id = ?'   => $parentCategory->id
            ]);

            $name = $categoryLang && $categoryLang->name // short_name
                ? $categoryLang->name // short_name
                : $parentCategory->name; // short_name

            array_unshift($breadcrumbs, [
                'name' => $name,
                'url'  => $this->url()->fromRoute('categories', [
                    'action'           => 'category',
                    'category_catname' => $parentCategory->catname,
                    'other'            => false,
                    'car_id'           => null,
                    'path'             => [],
                    'page'             => 1
                ])
            ]);
        }

        $categoryLang = $this->itemLanguageTable->fetchRow([
            'language = ?' => $language,
            'car_id = ?'   => $currentCategory->id
        ]);

        $carId = $this->params('car_id');
        $topCar = null;
        $currentCar = null;
        if ($carId) {
            $topCar = $this->itemTable->fetchRow(
                $this->itemTable->select(true)
                    ->where('cars.id = ?', $carId)
                    ->join('item_parent_cache', 'cars.id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = ?', $currentCategory->id)
            );
        }

        $path = [];

        if ($topCar) {
            $path = $this->params('path');
            $path = $path ? (array)$path : [];

            $breadcrumbs[] = [
                'name' => $this->car()->formatName($topCar, $language),
                'url'  => $this->url()->fromRoute('categories', [
                    'action'           => 'category',
                    'category_catname' => $currentCategory->catname,
                    'other'            => false,
                    'car_id'           => $topCar->id,
                    'path'             => [],
                    'page'             => 1
                ])
            ];

            $currentCar = $topCar;

            $breadcrumbsPath = [];

            foreach ($path as $pathNode) {
                $childCar = $this->itemTable->fetchRow(
                    $this->itemTable->select(true)
                        ->join('car_parent', 'cars.id = car_parent.car_id', null)
                        ->where('car_parent.parent_id = ?', $currentCar->id)
                        ->where('car_parent.catname = ?', $pathNode)
                );

                if (! $childCar) {
                    return $this->notFoundAction();
                }

                $breadcrumbsPath[] = $pathNode;

                $breadcrumbs[] = [
                    'name' => $this->car()->formatName($childCar, $language),
                    'url'  => $this->url()->fromRoute('categories', [
                        'action'           => 'category',
                        'category_catname' => $currentCategory->catname,
                        'other'            => false,
                        'car_id'           => $topCar->id,
                        'path'             => $breadcrumbsPath,
                        'page'             => 1
                    ])
                ];

                $currentCar = $childCar;
            }
        }

        $key = 'CATEGORY_MENU330_' . $topCategory->id . '_' . $language;

        $menu = $this->cache->getItem($key, $success);
        if (! $success) {
            $menu = $this->categoriesMenu($topCategory, $language, 2);

            $this->cache->setItem($key, $menu);
        }

        $this->categoriesMenuActive($menu, $currentCategory, $isOther);

        $sideBarModel = new ViewModel([
            'categories' => $menu,
            'category'   => $currentCategory,
            'isOther'    => $isOther,
            'deep'       => 1
        ]);
        $sideBarModel->setTemplate('application/category/menu');
        $this->layout()->addChild($sideBarModel, 'sidebar');

        $data = [
            'category'     => $currentCategory,
            'categoryLang' => $categoryLang,
            'isOther'      => $isOther,
        ];

        $result = $callback($language, $topCategory, $currentCategory,
            $categoryLang, $isOther, $topCar, $path, $currentCar, $breadcrumbs);

        if (is_array($result)) {
            return array_replace($data, $result);
        }

        return $result;
    }

    public function categoryAction()
    {
        return $this->doCategoryAction(function (
            $language,
            $topCategory,
            $currentCategory,
            $categoryLang,
            $isOther,
            $topCar,
            $path,
            $currentCar,
            $breadcrumbs
        ) {

            $haveSubcategories = (bool)$this->itemTable->fetchRow(
                $this->itemTable->select(true)
                    ->where('cars.item_type_id = ?', DbTable\Item\Type::CATEGORY)
                    ->join('car_parent', 'cars.id = car_parent.car_id', null)
                    ->where('car_parent.parent_id = ?', $currentCategory->id)
            );
            
            $select = $this->itemTable->select(true)
                ->join('car_parent', 'cars.id = car_parent.car_id', null)
                ->order($this->catalogue()->carsOrdering());

            if ($topCar) {
                $select->where('car_parent.parent_id = ?', $currentCar->id);
            } else {
                $select->where('car_parent.parent_id = ?', $currentCategory->id);
                
                if ($isOther) {
                    $select->where('cars.item_type_id <> ?', DbTable\Item\Type::CATEGORY);
                } else {
                    if ($haveSubcategories) {
                        $select->where('cars.item_type_id = ?', DbTable\Item\Type::CATEGORY);
                    }
                }
            }
            
            $paginator = new \Zend\Paginator\Paginator(
                new Zend1DbTableSelect($select)
            );

            $paginator
                ->setItemCountPerPage($this->catalogue()->getCarsPerPage())
                ->setCurrentPageNumber($this->params('page'));

            $users = new User();
            $contributors = [];
            /*$contributors = $users->fetchAll(
                $users->select(true)
                    ->join('category_item', 'users.id = category_item.user_id', null)
                    ->join('category_parent', 'category_item.category_id = category_parent.category_id', null)
                    ->where('category_parent.parent_id = ?', $currentCategory->id)
                    ->where('not users.deleted')
                    ->group('users.id')
            );*/

            $title = '';
            if ($currentCategory) {
                if ($topCar) {
                    if ($currentCar) {
                        $title = $this->car()->formatName($currentCar, $language);
                    } else {
                        $title = $this->car()->formatName($topCar, $language);
                    }
                } else {
                    $title = $categoryLang ? $categoryLang->name : $currentCategory->name;
                }
            }

            $carParentTable = new VehicleParent();

            $listData = $this->car()->listData($paginator->getCurrentItems(), [
                'useFrontPictures' => $haveSubcategories,
                'disableLargePictures' => true,
                'picturesDateSort' => true,
                'detailsUrl' => function ($listCar) use (
                    $topCar,
                    $currentCar,
                    $carParentTable,
                    $currentCategory,
                    $isOther,
                    $path
                ) {

                    if ($listCar->item_type_id == DbTable\Item\Type::CATEGORY) {
                        return $this->url()->fromRoute('categories', [
                            'action'           => 'category',
                            'category_catname' => $listCar->catname
                        ]);
                    }
                    
                    $carParentAdapter = $carParentTable->getAdapter();
                    $hasChilds = (bool)$carParentAdapter->fetchOne(
                        $carParentAdapter->select()
                            ->from($carParentTable->info('name'), new Zend_Db_Expr('1'))
                            ->where('parent_id = ?', $listCar->id)
                    );

                    if (! $hasChilds) {
                        return false;
                    }

                    // found parent row
                    if ($currentCar) {
                        if (count($path)) {
                            $carParentRow = $carParentTable->fetchRow([
                                'car_id = ?'    => $listCar->id,
                                'parent_id = ?' => $currentCar->id
                            ]);
                            if ($carParentRow) {
                                $currentPath = array_merge($path, [
                                    $carParentRow->catname
                                ]);
                            } else {
                                $currentPath = false;
                            }
                        } else {
                            $carParentRow = $carParentTable->fetchRow([
                                'car_id = ?'    => $listCar->id,
                                'parent_id = ?' => $currentCar->id
                            ]);
                            if ($carParentRow) {
                                $currentPath = array_merge($path, [
                                    $carParentRow->catname
                                ]);
                            } else {
                                $currentPath = false;
                            }
                        }

                        if (! $currentPath) {
                            return false;
                        }
                    } else {
                        $currentPath = [];
                    }

                    $url = $this->url()->fromRoute('categories', [
                        'action'           => 'category',
                        'category_catname' => $currentCategory->catname,
                        'other'            => $isOther,
                        'car_id'           => $topCar ? $topCar->id : $listCar->id,
                        'path'             => $currentPath,
                        'page'             => 1
                    ]);

                    return $url;
                },
                'allPicturesUrl' => function ($listCar) use (
                    $topCar,
                    $currentCar,
                    $carParentTable,
                    $currentCategory,
                    $isOther,
                    $path
                ) {
                    
                    if ($listCar->item_type_id == DbTable\Item\Type::CATEGORY) {
                        return $this->url()->fromRoute('categories', [
                            'action'           => 'category-pictures',
                            'category_catname' => $listCar->catname,
                            'car_id'           => $topCar ? $topCar->id : null,
                            'path'             => $path,
                        ]);
                    }

                    // found parent row
                    if ($currentCar) {
                        if (count($path)) {
                            $carParentRow = $carParentTable->fetchRow([
                                'car_id = ?'    => $listCar->id,
                                'parent_id = ?' => $currentCar->id
                            ]);
                            if ($carParentRow) {
                                $currentPath = array_merge($path, [
                                    $carParentRow->catname
                                ]);
                            } else {
                                $currentPath = false;
                            }
                        } else {
                            $carParentRow = $carParentTable->fetchRow([
                                'car_id = ?'    => $listCar->id,
                                'parent_id = ?' => $currentCar->id
                            ]);
                            if ($carParentRow) {
                                $currentPath = array_merge($path, [
                                    $carParentRow->catname
                                ]);
                            } else {
                                $currentPath = false;
                            }
                        }

                        if (! $currentPath) {
                            return false;
                        }
                    } else {
                        $currentPath = [];
                    }

                    $url = $this->url()->fromRoute('categories', [
                        'action'           => 'category-pictures',
                        'category_catname' => $currentCategory->catname,
                        'other'            => $isOther,
                        'car_id'           => $topCar ? $topCar->id : $listCar->id,
                        'path'             => $currentPath,
                        'page'             => 1
                    ]);

                    return $url;
                },
                'pictureUrl'           => function (
                    $listCar,
                    $picture
                ) use (
                    $currentCategory,
                    $isOther,
                    $topCar,
                    $currentCar,
                    $carParentTable,
                    $path
                ) {

                    if ($listCar->item_type_id == DbTable\Item\Type::CATEGORY) {
                        return $this->url()->fromRoute('categories', [
                            'action'           => 'category-picture',
                            'category_catname' => $listCar->catname,
                            'car_id'           => $topCar ? $topCar->id : $listCar->id,
                            'picture_id'       => $picture['identity'] ? $picture['identity'] : $picture['id']
                        ]);
                    }
                    
                    // found parent row
                    if ($currentCar) {
                        if (count($path)) {
                            $carParentRow = $carParentTable->fetchRow([
                                'car_id = ?'    => $listCar->id,
                                'parent_id = ?' => $currentCar->id
                            ]);
                            if ($carParentRow) {
                                $currentPath = array_merge($path, [
                                    $carParentRow->catname
                                ]);
                            } else {
                                $currentPath = false;
                            }
                        } else {
                            $carParentRow = $carParentTable->fetchRow([
                                'car_id = ?'    => $listCar->id,
                                'parent_id = ?' => $currentCar->id
                            ]);
                            if ($carParentRow) {
                                $currentPath = array_merge($path, [
                                    $carParentRow->catname
                                ]);
                            } else {
                                $currentPath = false;
                            }
                        }

                        if (! $currentPath) {
                            return false;
                        }
                    } else {
                        $currentPath = [];
                    }

                    return $this->url()->fromRoute('categories', [
                        'action'           => 'category-picture',
                        'category_catname' => $currentCategory->catname,
                        'other'            => $isOther,
                        'car_id'           => $topCar ? $topCar->id : $listCar->id,
                        'path'             => $currentPath,
                        'picture_id'       => $picture['identity'] ? $picture['identity'] : $picture['id']
                    ]);
                }
            ]);

            $description = null;
            if ($categoryLang['text_id']) {
                $description = $this->textStorage->getText($categoryLang['text_id']);
            }

            return [
                'title'            => $title,
                'breadcrumbs'      => $breadcrumbs,
                'paginator'        => $paginator,
                'contributors'     => $contributors,
                'listData'         => $listData,
                'urlParams'        => [
                    'action'           => 'category',
                    'category_catname' => $currentCategory->catname,
                    'other'            => $isOther,
                    'car_id'           => $topCar ? $topCar->id : null,
                    'path'             => $path
                ],
                'description'     => $description
            ];
        });
    }

    public function categoryPicturesAction()
    {
        return $this->doCategoryAction(function (
            $language,
            $topCategory,
            $currentCategory,
            $categoryLang,
            $isOther,
            $topCar,
            $path,
            $currentCar,
            $breadcrumbs
        ) {

            $pictureTable = $this->catalogue()->getPictureTable();

            $select = $pictureTable->select(true)
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->where('pictures.status IN (?)', [
                    Picture::STATUS_NEW, Picture::STATUS_ACCEPTED
                ])
                ->order($this->catalogue()->picturesOrdering());

            if ($topCar) {
                $select
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = ?', $currentCar->id);
            } else {
                $select
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = ?', $currentCategory->id)
                    ->group('pictures.id');
            }

            $paginator = new \Zend\Paginator\Paginator(
                new Zend1DbTableSelect($select)
            );

            $paginator
                ->setItemCountPerPage($this->catalogue()->getPicturesPerPage())
                ->setCurrentPageNumber($this->params('page'));

            $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

            $picturesData = $this->pic()->listData($select, [
                'width' => 4,
                'url'   => function ($picture) use ($currentCategory, $isOther, $topCar, $path) {
                    return $this->url()->fromRoute('categories', [
                        'action'           => 'category-picture',
                        'category_catname' => $currentCategory->catname,
                        'other'            => $isOther,
                        'car_id'           => $topCar ? $topCar->id : null,
                        'path'             => $path,
                        'picture_id'       => $picture['identity'] ? $picture['identity'] : $picture['id']
                    ]);
                }
            ]);

            return [
                'breadcrumbs'  => $breadcrumbs,
                'paginator'    => $paginator,
                'picturesData' => $picturesData,
            ];
        });
    }

    public function categoryPictureAction()
    {
        return $this->doCategoryAction(function (
            $language,
            $topCategory,
            $currentCategory,
            $categoryLang,
            $isOther,
            $topCar,
            $path,
            $currentCar,
            $breadcrumbs
        ) {

            $pictureTable = $this->catalogue()->getPictureTable();

            $select = $pictureTable->select(true)
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->where('pictures.status IN (?)', [
                    Picture::STATUS_NEW, Picture::STATUS_ACCEPTED
                ])
                ->order($this->catalogue()->picturesOrdering());

            if ($topCar) {
                $select
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = ?', $currentCar->id);
            } else {
                $select
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = ?', $currentCategory->id);
            }

            $selectRow = clone $select;

            $pictureId = (string)$this->params('picture_id');

            $selectRow
                ->where('pictures.id = ?', $pictureId)
                ->where('pictures.identity IS NULL');

            $picture = $selectRow->getTable()->fetchRow($selectRow);

            if (! $picture) {
                $selectRow = clone $select;

                $selectRow->where('pictures.identity = ?', $pictureId);

                $picture = $selectRow->getTable()->fetchRow($selectRow);
            }

            if (! $picture) {
                return $this->notFoundAction();
            }

            return [
                'breadcrumbs' => $breadcrumbs,
                'picture'     => array_replace(
                    $this->pic()->picPageData($picture, $select, []),
                    [
                        'gallery2'   => true,
                        'galleryUrl' => $this->url()->fromRoute('categories', [
                            'action'           => 'category-picture-gallery',
                            'category_catname' => $currentCategory->catname,
                            'other'            => $isOther,
                            'car_id'           => $topCar ? $topCar->id : null,
                            'path'             => $path,
                            'picture_id'       => $picture['identity'] ? $picture['identity'] : $picture['id']
                        ])
                    ]
                )
            ];
        });
    }

    public function categoryPictureGalleryAction()
    {
        return $this->doCategoryAction(function (
            $language,
            $topCategory,
            $currentCategory,
            $categoryLang,
            $isOther,
            $topCar,
            $path,
            $currentCar,
            $breadcrumbs
        ) {

            $pictureTable = $this->catalogue()->getPictureTable();

            $select = $pictureTable->select(true)
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->where('pictures.status IN (?)', [
                    Picture::STATUS_NEW, Picture::STATUS_ACCEPTED
                ])
                ->order($this->catalogue()->picturesOrdering());

            if ($topCar) {
                $select
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = ?', $currentCar->id);
            } else {
                $select
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                    ->group('pictures.id')
                    ->where('item_parent_cache.parent_id = ?', $currentCategory->id);
            }

            $selectRow = clone $select;

            $pictureId = (string)$this->params('picture_id');

            $selectRow
                ->where('pictures.id = ?', $pictureId)
                ->where('pictures.identity IS NULL');

            $picture = $selectRow->getTable()->fetchRow($selectRow);

            if (! $picture) {
                $selectRow = clone $select;

                $selectRow->where('pictures.identity = ?', $pictureId);

                $picture = $selectRow->getTable()->fetchRow($selectRow);
            }

            if (! $picture) {
                return $this->notFoundAction();
            }

            return new JsonModel($this->pic()->gallery2($select, [
                'page'        => $this->params()->fromQuery('page'),
                'pictureId'   => $this->params()->fromQuery('pictureId'),
                'reuseParams' => true,
                'urlParams'   => [
                    'action' => 'category-picture'
                ]
            ]));
        });
    }
}
