<?php
/**
* @package WiziappWordpressPlugin
* @subpackage Display
* @author comobix.com plugins@comobix.com
*
*/

class WiziappExternalScreen extends WiziappBaseScreen{
    protected $name = 'link';
    protected $type = 'external';

    public function run(){

    }

    public function runByLink($link){
        $screen = $this->prepare(array('link' => $link), '', $this->type);
        $this->output($screen);
    }
}