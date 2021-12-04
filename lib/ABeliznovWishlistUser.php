<?php
namespace Abeliznov\Shop;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock;
use Bitrix\Main\Entity;

require_once __DIR__ . '/ABeliznovWishlist.php';


class Wishlist extends AWishlist
{
    public static function isInWishlist($product_id)
    {
        return self::getWishlist( array('UF_PRODUCT_ID' => $product_id) );
    }



    public static function addToWishlist($product_id)
    {

        global $USER;
        $products = self::getWishlist();
        $in_wishlist = isset($products[$product_id]) ? $products[$product_id] : false;
        $entity_data_class = self::getWishlistEntity();
        if( !$in_wishlist )
        {
            $data = array(
                "UF_USER_ID" => $USER->GetID(),
                "UF_PRODUCT_ID" => $product_id,
            );
            $result = $entity_data_class::add($data);

            if ( $result )
            {
                $response = array(
                    'status' => 1,
                    'state' => 'add',
                );
            }
        }
        else
        {
            $entity_data_class::Delete($in_wishlist['HL_ID']);

            $response = array(
                'status' => 1,
                'state' => 'delete',
            );
        }
		
		$response['count'] = self::count();
        return $response;
    }

    public static function getWishlist( $filter = array() )
    {
        global $USER;
        $products = array();

        $entity_data_class = self::getWishlistEntity();

        $rsData = $entity_data_class::getList(array(
            "select" => array("*"),
            "order" => array("ID" => "ASC"),
            "filter" => $filter + array('UF_USER_ID' => $USER->GetID())
        ));
        while($arData = $rsData->Fetch()){
            $products[$arData['UF_PRODUCT_ID']] = array(
                'ID' => $arData['UF_PRODUCT_ID'],
                'HL_ID' => $arData['ID']
            );
        }
        return $products;
    }


    public static function count()
    {
        return count(self::getWishList());
    }

    public  static function resetWishList()
    {
        global $USER;
        $favouriteProduct = self::getWishlist();

        $entity_data_class = self::getHighloadEntity();
        foreach($favouriteProduct as $product)
            $entity_data_class::Delete($product['HL_ID']);
    }

    private static function getWishlistEntity()
    {
        Loader::IncludeModule("highloadblock");
        require __DIR__ . '/../config.php';
        $hlblock = Highloadblock\HighloadBlockTable::getList([
            'filter' => ['=NAME' => $ABELIZNOV_CONFIG['HL_WISHLIST_NAME']]
        ])->fetch();

        if (empty($hlblock) || $hlblock['ID'] < 1)
            return false;

        $entity = Highloadblock\HighloadBlockTable::compileEntity($hlblock);
        return $entity->getDataClass();
    }

}
