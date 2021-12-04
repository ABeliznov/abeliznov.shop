<?php
namespace Abeliznov\Shop;
abstract class AWishlist
{
    abstract public static function isInWishlist($product_id);
    abstract public static function addToWishlist($product_id);
    abstract public static function getWishlist($filter = array());
    abstract public static function resetWishList();
    abstract public static function count();
}
