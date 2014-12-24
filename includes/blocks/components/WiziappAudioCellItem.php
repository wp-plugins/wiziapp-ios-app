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
class WiziappAudioCellItem extends WiziappLayoutComponent{
    /**
    * The attribute map
    * 
    * @var array
    */
    var $attrMap = array(
        'L1' => array('title', 'duration', 'imageURL', 'actionURL'),
    );
    
    /**
    * The css classes to attach to the component according to the layout
    * 
    * @var mixed
    */
    var $layoutClasses = array(
        'L1' => 'audio',
    );
    
    /**
    * The base name of the component, the application knows the component by this name
    * 
    * @var string
    */
    var $baseName = 'audioCellItem';

    public $htmlTemplate = '<li class="audioCellItem __ATTR_class__ cellItem default">
                                <span class="attribute text_attribute imageURL __ATTR_classOf-imageURL__" data-image-src="__ATTR_imageURL__">
                                    <img class="hidden" src="" data-class="__ATTR_classOf-imageURL__"/>
                                </span>
                                <span class="__ATTR_classOf-title__ title attribute text_attribute">__ATTR_title__</span>
                                <span class="__ATTR_classOf-duration__ duration attribute text_attribute">__ATTR_duration__</span>
                                <span class="audio_button_play audio_play button_attribute attribute" data-action="play_audio" data-audio-id="audio-obj-__ATTR_id__"></span>
                                <span class="audio_button_stop audio_stop attribute" data-action="stop_audio" data-audio-id="audio-obj-__ATTR_id__"></span>
                                <span class="audio_slider"></span>
                                <span class="hidden">
                                    <audio id="audio-obj-__ATTR_id__">
                                      <source src="__ATTR_directActionURL__" type="audio/mp3" />
                                      Your browser does not support the audio tag.
                                    </audio>
                                </span>
                            </li>';

    /**
    * constructor 
    * 
    * @uses WiziappLayoutComponent::init()
    * 
    * @param string $layout the layout name
    * @param array $data the data the components relays on
    * @return WiziappAudioCellItem
    */
    function WiziappAudioCellItem($layout='L1', $data){
        parent::init($layout, $data);    
    }

    /**
    * Attribute getter method
    * 
    * @return the id of the component
    */
    function get_id_attr(){
        return "audio_{$this->data[0]['id']}";    
    }
    
    /**
    * Attribute getter method
    * 
    * @return the duration of the component
    */
    function get_duration_attr(){
        return $this->data[0]['duration'];
    }
    
    /**
    * Attribute getter method
    * 
    * @return the title of the component
    */
    function get_title_attr(){
        return $this->data[0]['title'];
    }
    
    /**
    * Attribute getter method
    * 
    * @return the imageURL of the component
    */
    function get_imageURL_attr(){
        $image = new WiziappImageHandler($this->data[0]['imageURL']);
        $size = WiziappConfig::getInstance()->getImageSize('audio_thumb');
        return $image->getResizedImageUrl(htmlspecialchars_decode($this->data[0]['imageURL']), $size['width'], $size['height']);
        
//        return $this->data[0]['imageURL'];
    }
    
    /**
    * Attribute getter method
    * 
    * @return the actionURL of the component
    */
    function get_actionURL_attr(){
		$actionURL = WiziappLinks::fixAudioLink($this->data[0]['actionURL']);
		
        return $actionURL;
    }

    function get_directActionURL_attr(){
        $actionURL = $this->get_actionURL_attr();
        //cmd://open/audio/http%3A%2F%2Ftest.comobix.com%2Fblogtest5%2Fwp-content%2Fuploads%2F2011%2F05%2FGeorge-Thorogood-Bad-To-The-Bone.mp3
        $actionURL = str_replace('cmd://open/audio/', '', $actionURL);
        return rawurldecode($actionURL);
    }
    
}