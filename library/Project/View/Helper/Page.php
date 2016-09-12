<?php
class Project_View_Helper_Page extends Zend_View_Helper_Abstract
{
    /**
     * @var Zend_Db_Table
     */
    protected $_pageTable;

    /**
     * @var Page_Row
     */
    protected $_doc;
    /**
     * @var int
     */
    protected $_language = 'en';

    protected $_parentsCache = array();

    protected $_pages = array();

    public function __construct()
    {
        $this->_pageTable = new Pages();

        if (Zend_Registry::isRegistered('Zend_Locale')) {
            $locale = new Zend_Locale(Zend_Registry::get('Zend_Locale'));
            $this->_language = $locale->getLanguage();
        }
    }

    public function page($value)
    {
        if ($value) {
            $doc = null;

            if ($value instanceof Zend_Db_Table_Row)
                $doc = $value;
            elseif (is_numeric($value)) {
                $doc = $this->_getPageById($value);
            }

            $this->_doc = $doc;
        }

        return $this;
    }

    public function __get($name)
    {
        if (!$this->_doc) {
            return '';
        }
        switch ($name) {
            /*case 'html':
                return $this->_doc->getHtml(array(
                    'language' => $this->_language
                ));*/

            case 'url':
                $this->_doc['url'];
                break;

            case 'name':
            case 'title':
            case 'breadcrumbs':
                $key = 'page/' . $this->_doc->id. '/' . $name;
                
                $result = $this->view->translate($key);
                if (!$result) {
                    $result = $this->view->translate($key, 'en');
                }

                return $result;

            case 'onPath':
                return $this->_isParentOrSelf($this->_currentPage, $this->_doc);
                break;
        }

        return '';
    }

    protected function _getPageById($id)
    {
        if (isset($this->_pages[$id])) {
            return $this->_pages[$id];
        }

        return $this->_pages[$id] = $this->_pageTable->find($id)->current();
    }

    protected function _isParentOrSelf($child, $parent)
    {
        if (!$parent || !$child) {
            return false;
        }

        if ($parent->id == $child->id) {
            return true;
        }

        if ($parent->id == $child->parent_id) {
            return true;
        }

        if (isset($this->_parentsCache[$child->id][$parent->id])) {
            return $this->_parentsCache[$child->id][$parent->id];
        }

        $cParent = $child->parent_id ? $this->_getPageById($child->parent_id) : false;
        $result = $this->_isParentOrSelf($cParent, $parent);

        $this->_parentsCache[$child->id][$parent->id] = $result;

        return $result;
    }

}
