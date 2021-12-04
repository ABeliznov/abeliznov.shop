<?php
namespace Abeliznov\Shop;

use Bitrix\Main;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock;
use Bitrix\Main\Entity,
    Bitrix\Main\Application,
    Bitrix\Main\Context,
    Bitrix\Main\Request,
    Bitrix\Main\Server,
    Bitrix\Main\Web\Cookie;


require_once __DIR__ . '/ABeliznovWishlist.php';

class Wishlist extends AWishlist
{
    public static function isInWishlist($product_id)
    {
        return self::getWishlist( array('UF_PRODUCT_ID' => $product_id) );
    }

    public static function addToWishlist($product_id)
    {
        $products = self::getWishlist();
        $favourites = array();

        $in_wishlist = isset($products[$product_id]) ? $products[$product_id] : false;

        if( !$in_wishlist )
        {
            foreach ($products as $k => $item)
                $favourites[$k] = $item['ID'];

            $favourites[$product_id] = $product_id;
            self::setAnonymCookie("favourites", json_encode($favourites), time() + 60*60*24*30*12*2);

            $result = array(
                'status' => 1,
                'state' => 'add',
                'count' => count($favourites)
            );
        }
        else
        {
            foreach ($products as $k => $item)
                $favourites[$k] = $item['ID'];

            unset($favourites[$product_id]);
            self::setAnonymCookie("favourites", json_encode($favourites), time()+60*60*24*30*12*2 );

            $result = array(
                'status' => 1,
                'state' => 'delete',
                'count' => count($favourites)
            );
        }

        return $result;
    }

    public static function getWishlist( $filter = array() )
    {
        $favourites = array();
		global $APPLICATION;
		
        $favouritesObj = $APPLICATION->get_cookie('favourites');
        $favouritesObj = !empty($favouritesObj) ? json_decode($favouritesObj, true) : array();

        foreach($favouritesObj as $k => $v)
        {
            $favourites[$k] = array(
                'ID' => $v
            );
        }

        return $favourites;
    }

    public static function count()
    {
        return count(self::getWishList());
    }

    public static function resetWishList()
    {
        self::setAnonymCookie("favourites", json_encode(array()), -1);
    }

    private static function setAnonymCookie($cookie_name, $cookie_value, $time)
    {
        $context = Application::getInstance()->getContext();
        $cookie = new Cookie($cookie_name, $cookie_value, $time);
        $cookie->setDomain($context->getServer()->getHttpHost());
        $cookie->setHttpOnly(false);
        $cookie->setSecure(false);
        $context->getResponse()->addCookie($cookie);
        $context->getResponse()->writeHeaders("");
    }
}
