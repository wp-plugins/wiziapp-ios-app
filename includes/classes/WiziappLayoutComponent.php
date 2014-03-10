<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* The base component class
*
* The components are the UI building blocks. The application gets all
* the data in the blog in the formats of predefined component.
* each component always have the basic: id, style, class attribtue
* but can have different other attributes depending on the component itself
* moreover, each component can have several layouts allowing us to represnt the
* data related to the component in more ways then one.
*
* @package WiziappWordpressPlugin
* @subpackage UIComponents
* @author comobix.com plugins@comobix.com
*/
class WiziappLayoutComponent{
	/**
	* The layout name
	*
	* @var string
	*/
	var $layout;

	/**
	* The data behind the component
	*
	* @var array
	*/
	var $data;

	/**
	* The attributes is exporting in the current layout
	*
	* @var mixed
	*/
	var $attributes = array();

	/**
	* The base attributes map, containing the attributes every component has
	*
	* @var array
	*/
	var $baseAttrMap = array('id', 'class', 'layout');

	/**
	* The attribute map, will be overriden but the sub class
	*
	* @var array
	*/
	var $attrMap = array();

	/**
	* Which css class to attach to which layout
	*
	* @var array
	*/
	var $layoutClasses = array();

	/**
	* The base name of the component, the application knows the component by this name
	*
	* @var string
	*/
	var $baseName = '';

	/**
	* A flag indicating if the component was found valid and processed
	*
	* @var boolean
	*/
	var $valid = FALSE;

	/**
	* An array including all the attributes we need to add after processing the layout
	* attributes according to the theme settins.
	*
	* @var array
	*/
	var $themeRemoveAttr = array();

	/**
	* An array including all the attributes we need to remove after processing the layout
	* attributes according to the theme settins.
	*
	* @var array
	*/
	var $themeAddAttr = array();

	var $attrIgnoreAddOverride = array();

    public $htmlTemplate = '';

	/**
	* Initilize the component data and start the processing
	*
	* @param string $layout the layout name
	* @param array $data the data the components relays on
	*/
	function init($layout='L1', $data, $process=TRUE){
		$this->layout = $layout;
		$this->data = $data;

		$this->getThemeOverrides();

		if ( $process ){
			$this->process();
		}
		$this->valid = TRUE;
	}

	/**
	* Check if the component was processed like if should have
	*
	* @return boolean wheter or not the component was found valid and processed
	*/
	function isValid(){
		return $this->valid;
	}

	private function processAttribute($matches){
		$attrName = $matches[2];
		if ( ! isset($matches[2]) ){
			return '';
		}

		if ( isset($this->attributes[$attrName]) ){
			return $this->attributes[$attrName];
		}

		if ( strpos($attrName, 'classOf-') === 0 ){
			$attr = explode('-', $attrName);
			$attrName = $attr[1];

			if ( ! isset($this->attributes[$attrName]) ){
				return 'hidden';
			}

			$webapp_postfix = '';
			$postfix_condition =
			( $this->attributes['class'] === 'featured_post' && $attrName === 'imageURL' ) ||
			( $this->attributes['class'] === 'general_post' && $attrName === 'numOfComments' ) ||
			$attrName === 'numOfPosts' ||
			$attrName === 'title' ||
			$attrName === 'date';
			if ( isset($attr[2]) && $attr[2] === 'webapp' && $postfix_condition ){
				$webapp_postfix = '_webapp';

				if ( $this->attributes['class'] === 'featured_post' && $attrName === 'imageURL' ){
					$webapp_postfix = ' webapp_image_overriding';
				}
			}

			if ( $attrName === 'imageURL' ){
				$attrName = 'image';
			}

			return $this->attributes['class'].'_'.$attrName.$webapp_postfix;
		}

		$methodName = "get_{$attrName}_attr";
		if ( method_exists($this, $methodName) ){
			return $this->$methodName();
		}

		return '';
	}

	/**
	* @returns array containing the processed component
	*/
	function getComponent(){
		if ( WiziappContentHandler::getInstance()->isHTML() ){
            // webapp, load the array to the right template
            $template = $this->htmlTemplate.PHP_EOL;
            $regex = '/(__ATTR_)(.*?)(__)/';

            return preg_replace_callback($regex,
                    array($this, 'processAttribute'),
                    $template);
        } else {
			return array(
				$this->baseName => $this->attributes,
			);
        }
	}

	/**
	* Attribute getter method
	*
	* @returns the css class of the component
	*/
	function get_class_attr(){
		return $this->layoutClasses[$this->layout];
	}

	/**
	* attribute getter method
	*
	* @returns the layout map for the component
	*/
	function get_layout_attr(){
		// Support dynamic layout in the application
		return 'L0';
	}

	/**
	*
	* @return the css class name of the component default layout
	*/
	function getDefaultClass(){
		return $this->layoutClasses['L1'];
	}

	function getThemeOverrides(){
		if ( class_exists('WiziappComponentsConfiguration') ){
			 $attrAdd = WiziappComponentsConfiguration::getInstance()->getAttrToAdd($this->baseName);

			if ( isset($this->attrIgnoreAddOverride[$this->layout]) ){
				foreach($attrAdd as $attr){
					if ( ! isset( $this->attrIgnoreAddOverride[$this->layout][$attr] ) || $this->attrIgnoreAddOverride[$this->layout][$attr] ){
						$this->themeAddAttr[] = $attr;
					}
				}
			} else {
				$this->themeAddAttr = $attrAdd;
			}

			$this->themeRemoveAttr = WiziappComponentsConfiguration::getInstance()->getAttrToRemove($this->baseName);
		}
	}

	function applyThemeOverrides($attributes){
		for ( $a=0, $total=count($this->themeRemoveAttr) ; $a < $total ; ++$a ){
			$key = array_search($this->themeRemoveAttr[$a], $attributes);
			if ( $key !== FALSE ){
				$removedKey = array_splice($attributes, $key, 1);
			}
		}
		$attributes = array_merge($attributes, $this->themeAddAttr);
		return($attributes);
	}

	/**
	* The main logic of the component building
	*
	* This method go over all of the attributes the component have according to
	* its layout and call the attribute getter method of the attributes if such
	* exists, if the method exists and it doesn't return null it will add it
	* to the component output.
	*
	*/
	function process(){
		if ( is_array($this->attrMap[$this->layout]) ){
			$layoutAttrMap = $this->attrMap[$this->layout];
		} else {
			$layoutAttrMap = $this->attrMap[$this->attrMap[$this->layout]];
		}

		$layoutAttrMap = $this->applyThemeOverrides($layoutAttrMap);
		$attrMap = array_merge($this->baseAttrMap, $layoutAttrMap);

		for ( $a=0, $total=count($attrMap) ; $a < $total ; ++$a ){
			$methodName = "get_{$attrMap[$a]}_attr";

			if ( method_exists($this, $methodName) ){
				$value = $this->$methodName();

				if ( $value !== NULL ){
					$this->attributes[$attrMap[$a]] = $this->$methodName();
				}
			}
		}
	}

	public function simplifyText($text){
		$text = preg_replace('/<br\\s*?\/??>/i', "\n", $text);
		$text = strip_tags($text);
		$text = stripslashes($text);
		return $text;
	}
}