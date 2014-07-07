<?php if (!defined('WP_WIZIAPP_BASE')) exit();

/**
* @package WiziappWordpressPlugin
* @subpackage Display
* @author comobix.com plugins@comobix.com
*
*/

class WiziappBaseScreen{

	protected $type = 'list';

	protected $name = '';

	public function getConfig($override_type=FALSE){
		$sc = new WiziappScreenConfiguration();
		$type = $this->type;
		if ( $override_type ){
			$type = $override_type;
		}
		return $sc->getScreenLayout($this->name, $type);
	}

	public function getTitle($title=''){
		if ( $title == '' ){
			$title = $this->name;
		}
		return __(WiziappConfig::getInstance()->getScreenTitle($title), 'wiziapp');
	}

	function prepareSection($page = array(), $title = '', $type = 'List', $hide = false, $show_ads = false, $css_class = ''){
		return $this->prepare($page, $title, $type, TRUE, false, $hide, $css_class, $show_ads);
	}

	function prepare($page = array(), $title = '', $type = 'Post', $sections = FALSE, $force_grouped = FALSE, $hide_separator = FALSE, $css_class = '', $show_ads = FALSE){
		$key = $sections ? 'sections' : 'items';
		$grouped = ($sections || $force_grouped) ? TRUE : FALSE;
		$css_class_name = empty($css_class) ? ( $grouped ? 'screen' : '' ) : $css_class;

		if ($grouped){
			// Verify that the app supports group, the theme might force everything to be not grouped.
			if ( ! WiziappConfig::getInstance()->allow_grouped_lists || $title == 'Links' ){
				$grouped = FALSE;
			}
		}

		$screen = array(
			'screen' => array(
				'type'    => strtolower($type),
				'title'   => $title,
				'class'   => $css_class_name,
				$key      => $page,
				'update'  => (isset($_GET['wizipage']) && $_GET['wizipage']) ? TRUE : FALSE,
				'grouped' => $grouped,
				'showAds' => $show_ads,
			)
		);

		if ( ! $hide_separator) {
			$screen['screen']['separatorColor'] = WiziappConfig::getInstance()->sep_color;
		}

		return $screen;
	}

	private function _hex2rgba($color){
		if ($color[0] == '#'){
			$color = substr($color, 1);
		}
		if (strlen($color) == 8){
			$r = $color[0].$color[1];
			$g = $color[2].$color[3];
			$b = $color[4].$color[5];
			$a = $color[6].$color[7];
		} elseif (strlen($color) == 5) {
			$r = $color[0].$color[0];
			$g = $color[1].$color[1];
			$b = $color[2].$color[2];
			$a = $color[3].$color[4];
		} else {
			// Not the supported format
			return $color;
		}
		$r = hexdec($r);
		$g = hexdec($g);
		$b = hexdec($b);

		$a = round(hexdec($a) / 255, 1);
		$rgba = "rgba({$r},{$g},{$b},{$a})";

		return $rgba;
	}

	public function output($screen_content, $back_content = false){
		if ( WiziappContentHandler::getInstance()->isHTML() ){
			$components = '';
			$gotItems = FALSE;
			// $sep = '';

			if ( $screen_content['screen']['type'] == 'list' ||
				$screen_content['screen']['type'] == 'favorites_list' ||
				$screen_content['screen']['type'] == 'gallery' ){

				/*
				if ( isset($screen_content['screen']['separatorColor']) ){
				$sep = '<li class="sep" style="background-color: '.$this->_hex2rgba($screen_content['screen']['separatorColor']).';"></li>'.PHP_EOL;
				}
				*/

				if ( empty($screen_content['screen']['items']) ){
					// We have sections
					if ( ! empty($screen_content['screen']['sections']) ){
						for ($s = 0, $total = count($screen_content['screen']['sections']); $s < $total; ++$s){
							$components .= '<ul data-role="listview">'.PHP_EOL;

							if ( ! empty($screen_content['screen']['sections'][$s]['section']['title']) ){
								$components .= '<li data-role="list-divider">'.$screen_content['screen']['sections'][$s]['section']['title'].'</li>'.PHP_EOL;
							}

							$components .= implode(PHP_EOL, $screen_content['screen']['sections'][$s]['section']['items']);
							$components .= '</ul>'.PHP_EOL;

							$gotItems = TRUE;
						}
					}
				} else {
					$components .= '<ul data-role="listview">'.PHP_EOL;
					$components .= implode(PHP_EOL, $screen_content['screen']['items']);
					$components .= '</ul>'.PHP_EOL;

					$gotItems = TRUE;
				}
			} else {
				// type == 'about'
				$gotItems = TRUE;
			}

			if ( ! $gotItems ){
				$screen_content['screen']['class'] = "{$screen_content['screen']['class']} {$screen_content['screen']['class']}_empty";
			}

			if ( $back_content !== false && ! isset($back_content['text']) ){
				$back_content['text'] = false;
			}

			// Generate full html page segment
			global $tabBar;
			$tabBar = new WiziappTabbarBuilder();
			require(WIZI_DIR_PATH . 'themes/webapp/' . $screen_content['screen']['type'] . '_screen.php');
		} else {
			// Output the normal json
			echo json_encode($screen_content);
		}
	}

	/**
	* This method will convert the page layout instruction
	* to a known component. and then it will append it to the page
	* which is passed by reference
	*
	* @param array $page
	* @param string $block
	*/
	public function appendComponentByLayout(&$page, $block){
		/**
		* Since this function is used for creating different type of pages
		* we can an unknown number of parameters depending on the
		* calling method
		*/
		$params = func_get_args();
		/**
		* Removes the first two parameters from the params array
		* since we already know them by name
		*/
		$tmpPage = array_shift($params);
		$tmpBlock = array_shift($params);
		$num = func_num_args();
		//WiziappLog::getInstance()->write('DEBUG', "Appending {$num} to page: ".print_r($params, TRUE), "content");

		$className = ucfirst($block['class']);
		$layout = $block['layout'];
		if (class_exists($className)){
			$obj = new $className($layout, $params);
			if ($obj->isValid()){
				$page[] = $obj->getComponent();
			}
		}
	}
}