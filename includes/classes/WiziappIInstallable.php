<?php if (!defined('WP_WIZIAPP_BASE')) exit();

interface WiziappIInstallable
{
    function isInstalled();
    function install();
    function uninstall();
    function needUpgrade();
    function upgrade();
}