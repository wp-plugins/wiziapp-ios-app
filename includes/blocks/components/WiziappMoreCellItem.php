<?php
/**
* The more cell item component
* 
* The component knows how to return: title, actionURL
* 
* @package WiziappWordpressPlugin
* @subpackage UIComponents
* @author comobix.com plugins@comobix.com
*/
class WiziappMoreCellItem extends WiziappLayoutComponent{
    /**
    * The attribute map
    * 
    * @var array
    */
    var $attrMap = array(
        'L1' => array('title', 'actionURL'),
    );
    
    /**
    * The css classes to attach to the component according to the layout
    * 
    * @var mixed
    */
    var $layoutClasses = array(
        'L1' => 'more',
    );
    
    /**
    * The base name of the component, the application knows the component by this name
    * 
    * @var string
    */
    var $baseName = 'showMoreCellItem';

    public $htmlTemplate = '<li class="showMoreCellItem __ATTR_class__ cellItem default" data-icon="false">
                            <a class="showMore actionURL full" href="__ATTR_actionURL__" rel="inlineUpdate">
                                <span class="__ATTR_classOf-title__ title attribute text_attribute">__ATTR_title__</span>
                                <span class="more_activity"></span>
                            </a>
                            </li>';

    /**
    * constructor 
    * 
    * @uses WiziappLayoutComponent::init()
    * 
    * @param string $layout the layout name
    * @param array $data the data the components relays on
    * @return WiziappMoreCellItem
    */
    function WiziappMoreCellItem($layout='L1', $data){
        parent::init($layout, $data);    
    }
    
    /**
    * Attribute getter method
    * 
    * @return the id of the component
    */
    function get_id_attr(){
        return "more_".substr(md5($this->data[1].$this->data[0]), 0, 5);
    }
    
    /**
    * Attribute getter method
    * 
    * @return the title of the component
    */
    function get_title_attr(){
        return $this->data[0]."...";
    }
    
    /**
    * Attribute getter method
    * 
    * @return the actionURL of the component
    */
    function get_actionURL_attr(){
        return WiziappLinks::moreLink($this->data[1]);
    }   
}
