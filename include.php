<?php
use Bitrix\Main\Loader;

global $USER;
require dirname(__FILE__) ."/config.php";


$loader_cfg = array(
    '\Abeliznov\Shop\Catalog' => 'lib/ABeliznovShop.php',
);
$loader_cfg['\ABeliznov\Shop\Wishlist'] =  $USER->IsAuthorized() ? 'lib/ABeliznovWishlistUser.php' : 'lib/ABeliznovWishlistAnonym.php';

Loader::registerAutoLoadClasses($ABELIZNOV_CONFIG['MODULE_ID'], $loader_cfg);
