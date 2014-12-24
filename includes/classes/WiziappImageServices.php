<?php if (!defined('WP_WIZIAPP_BASE')) exit();

class WiziappImageServices{
    public static function getByRequest(){
        $width = $_GET['width'];

        $image = new WiziappImageHandler($_GET['url']);
        WiziappLog::getInstance()->write('info', 'Requesting image: '.$_GET['url'], 'WiziappImageServices.getByRequest');
        $image->wiziapp_getResizedImage($width, $_GET['height'], $_GET['type'], $_GET['allow_up']);
    }
}