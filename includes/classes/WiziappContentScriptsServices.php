<?php if (!defined('WP_WIZIAPP_BASE')) exit();

class WiziappContentScriptsServices{
    public function get(){
        header("Content-type: text/javascript; charset: UTF-8");
        header('Cache-Control: no-cache, must-revalidate');
        $offset = 3600 * 24; // 24 hours
        header('Expires: ' . gmdate("D, d M Y H:i:s", time() + $offset) . ' GMT');

        $lastModified = 0;
        // TODO: Add routing to the right app version scripts according to the requesting website page or udid
        // load the javascript needed for the webview
        $dir = WIZI_DIR_PATH . 'themes/iphone/scripts/';
        $scripts = array('/content.js');

        $script = '';
        for($s=0, $total=count($scripts); $s < $total; ++$s){
            $script .= file_get_contents($dir . $scripts[$s]);
            $fileModified = filemtime($dir . $scripts[$s]);
            if ($lastModified < $fileModified){
                $lastModified = $fileModified;
            }
        }

        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');

        if (isset($_SERVER["HTTP_IF_MODIFIED_SINCE"]) && strtotime($_SERVER["HTTP_IF_MODIFIED_SINCE"]) == $lastModified){
            // Nothing was updated
            header('HTTP/1.1 304 Not Modified');
        } else {
            echo $script;
        }
    }
}