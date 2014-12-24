<?php
/**
* The audio cell item component
*
* The component knows how to return: title, duration, imageURL, actionURL
*
* @package WiziappWordpressPlugin
* @subpackage UIComponents
* @author comobix.com plugins@comobix.com
*/
class WiziappLinkCellItem extends WiziappLayoutComponent{
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
		'L1' => 'link',
	);

	/**
	* The base name of the component, the application knows the component by this name
	*
	* @var string
	*/
	var $baseName = 'linkCellItem';

	public $htmlTemplate = '';

	/**
	* constructor
	*
	* @uses WiziappLayoutComponent::init()
	*
	* @param string $layout the layout name
	* @param array $data the data the components relays on
	* @return WiziappLinkCellItem
	*/
	public function __construct($layout = 'L1', $data){
		parent::init($layout, $data);

		ob_start();
		?>
		<li class="linkCellItem cellItem default __ATTR_class__">
			<a class="actionURL link_to_page" href="__ATTR_actionURL__" data-transition="slide" data-ajax="false">
				<span class="attribute webapp_link_title __ATTR_class___title">__ATTR_title__</span>
			</a>
		</li>
		<?php
		$this->htmlTemplate = ob_get_clean();
	}

	/**
	* Attribute getter method
	*
	* @return the id of the component
	*/
	function get_id_attr(){
		return "link_{$this->data[0]->link_id}";
	}

	/**
	* Attribute getter method
	*
	* @return the title of the component
	*/
	function get_title_attr(){
		return $this->data[0]->link_name;
	}

	/**
	* Attribute getter method
	*
	* @return the actionURL of the component
	*/
	function get_actionURL_attr(){
		return WiziappLinks::externalLink($this->data[0]->link_url);
	}

}