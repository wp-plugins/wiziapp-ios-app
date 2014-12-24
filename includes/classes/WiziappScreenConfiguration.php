<?php if (!defined('WP_WIZIAPP_BASE')) exit();

/**
* @package WiziappWordpressPlugin
* @subpackage Configuration
* @author comobix.com plugins@comobix.com
*
*/
class WiziappScreenConfiguration{
    var $config = array();

    function WiziappScreenConfiguration(){
        $this->config = get_option('wiziapp_screens');
    }

    function getScreenLayout($screen, $type='list'){
        //return $this->config[$screen][$this->layouts[$screen.'_'.$type]];
        global $wiziappLoader;

        $config = null;
        if ( isset($this->config[$wiziappLoader->getVersion()]) ){
            $config = $this->config[$wiziappLoader->getVersion()][$screen.'_'.$type];
        } else {
            $config = $this->config[WIZIAPP_P_VERSION][$screen.'_'.$type];
        }

        return $config;
    }
}