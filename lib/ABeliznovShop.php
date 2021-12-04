<?php
namespace Abeliznov\Shop;

use Bitrix\Catalog\Product\Basket;
use Bitrix\Main,
    Bitrix\Sale;
use Bitrix\Main\Application,
    Bitrix\Main\Context,
    Bitrix\Main\Request,
    Bitrix\Main\Server;
use Bitrix\Main\Mail\Event,
    Bitrix\Main\Localization\Loc as Loc,
    Bitrix\Main\Loader,
    Bitrix\Main\Config\Option,
    Bitrix\Sale\Delivery,
    Bitrix\Sale\PaySystem,
    Bitrix\Sale\Order,
    Bitrix\Sale\DiscountCouponsManager;
use Bitrix\Main\Web\Cookie;


Loader::IncludeModule('catalog');
Loader::IncludeModule('sale');



class Catalog{
    public static $currentFuser = null;



    public static function addToCart($product, $quantity, $props = array())
    {
        $fields = [
            'PRODUCT_ID' => $product,
            'QUANTITY' => $quantity,
            'PROPS' => $props,
        ];
        $r = \Bitrix\Catalog\Product\Basket::addProduct($fields);

        if (!$r->isSuccess()) {
            //pr($r->getErrorMessages());
            return false;
        }
        return $r->getData()['ID'];
    }


    public static function getCartInfo()
    {
        $result = [
            'NUM_PRODUCTS' => 0,
            'FINAL_CURRENT_PRICE_FORMATTED' => CurrencyFormat(0, 'RUB'),
            'FINAL_CURRENT_PRICE' => 0,
            'FINAL_OLD_PRICE_FORMATTED' => CurrencyFormat(0, 'RUB'),
            'FINAL_OLD_PRICE' => 0,
            'ITEMS' => []
        ];


        $fullBasket = \Bitrix\Sale\Basket::loadItemsForFUser(self::getFuserId(), SITE_ID);


        if ($fullBasket->isEmpty())
            return $result;

        $basketClone = $fullBasket->createClone();
        $orderableBasket = $basketClone->getOrderableItems();
        $basketItems_object = $basketClone->getBasketItems();
        $basketItems = array();
        foreach($basketItems_object as $product)
        {
            if($product->isDelay()) continue;
            $offer = \CCatalogSku::GetProductInfo( $product->getProductId() );
            $product_arr = array(
                'ID' => $product->getId(),
                'OFFER_IBLOCK_ID' => $offer['OFFER_IBLOCK_ID'],
                'OFFER_ID' => is_array($offer) ? $product->getProductId() : false,
                'PRODUCT_ID' => is_array($offer) ? $offer['ID'] : $product->getProductId(),
                'NAME' => $product->getField('NAME'),
                'QUANTITY' => $product->getQuantity(),
            );
            $basketItems[$product_arr['ID']] = $product_arr;
        }
        unset($basketClone);


        if (!$orderableBasket->isEmpty())
        {
            $onlySaleDiscounts = (string)Main\Config\Option::get('sale', 'use_sale_discount_only') == 'Y';
            if (!$onlySaleDiscounts)
            {
                $orderableBasket->refresh(Sale\Basket\RefreshFactory::create(Sale\Basket\RefreshFactory::TYPE_FULL));
            }
            $discounts = Sale\Discount::buildFromBasket(
                $orderableBasket,
                new Sale\Discount\Context\Fuser(self::getFuserId())
            );
            $discountResult = $discounts->calculate();

            $discountData = $discountResult->getData()['BASKET_ITEMS'];

            $total_old_price = 0;
            foreach($basketItems as &$product)
            {
                $optimal_price = \CCatalogProduct::GetOptimalPrice($product['PRODUCT_ID'], $product['QUANTITY']);


                $product['BASE_PRICE'] = array(
                    'CURRENT_PRICE' => $discountData[$product['ID']]['PRICE'],
                    'CURRENT_PRICE_TOTAL' => $discountData[$product['ID']]['PRICE'] * $product['QUANTITY'],
                    'CURRENT_PRICE_FORMATTED' => CurrencyFormat($discountData[$product['ID']]['PRICE'], 'RUB'),
                    'CURRENT_PRICE_TOTAL_FORMATTED' => CurrencyFormat($discountData[$product['ID']]['PRICE'] * $product['QUANTITY'], 'RUB'),
                    'OLD_PRICE' => $optimal_price['RESULT_PRICE']['BASE_PRICE'],
                    'OLD_PRICE_FORMATTED' => CurrencyFormat($optimal_price['RESULT_PRICE']['BASE_PRICE'], 'RUB'),
                    'OLD_PRICE_TOTAL' => $optimal_price['RESULT_PRICE']['BASE_PRICE'] * $product['QUANTITY'],
                    'OLD_PRICE_TOTAL_FORMATTED' => CurrencyFormat($optimal_price['RESULT_PRICE']['BASE_PRICE'] * $product['QUANTITY'], 'RUB'),
                    'DISCOUNT' => $discountData[$product['ID']]['DISCOUNT_PRICE'],
                    'DISCOUNT_PERCENT' => (100 - round( $discountData[$product['ID']]['PRICE'] * 100 / $optimal_price['RESULT_PRICE']['BASE_PRICE'] )),
                );
                $total_old_price += $product['BASE_PRICE']['OLD_PRICE'] * $product['QUANTITY'];
           }

           if ($discountResult->isSuccess())
           {
               $showPrices = $discounts->getShowPrices();
               if (!empty($showPrices['BASKET']))
               {
                   foreach ($showPrices['BASKET'] as $basketCode => $data)
                   {
                       $basketItem = $orderableBasket->getItemByBasketCode($basketCode);
                       if ($basketItem instanceof Sale\BasketItemBase)
                       {
                           $basketItem->setFieldNoDemand('BASE_PRICE', $data['SHOW_BASE_PRICE']);
                           $basketItem->setFieldNoDemand('PRICE', $data['SHOW_PRICE']);
                           $basketItem->setFieldNoDemand('DISCOUNT_PRICE', $data['SHOW_DISCOUNT']);
                       }
                   }
                   unset($basketItem, $basketCode, $data);
               }
               unset($showPrices);
           }
           unset($discountResult);


           $final_total_price = $orderableBasket->getPrice();
           $final_old_price = $total_old_price;

           $result['FINAL_CURRENT_PRICE_FORMATTED'] = CurrencyFormat($final_total_price, "RUB");
           $result['FINAL_CURRENT_PRICE'] = $final_total_price;
           $result['FINAL_OLD_PRICE_FORMATTED'] = CurrencyFormat($final_old_price, "RUB");
           $result['FINAL_OLD_PRICE'] = $final_old_price;
           $result['DISCOUNT'] = $final_old_price - $final_total_price;
           $result['DISCOUNT_FORMATTED'] = CurrencyFormat($result['DISCOUNT'], 'RUB');
           $result['DISCOUNT_PERCENT'] = 100 - round($final_total_price * 100 / $final_old_price);
           $result['NUM_PRODUCTS'] = $orderableBasket->count();
           $result['ITEMS'] = $basketItems;



       }
       unset($orderableBasket);

       return $result;
   }

    public static function count()
    {
        $basket = Sale\Basket::loadItemsForFUser(self::getFuserId(), \Bitrix\Main\Context::getCurrent()->getSite());
        return count($basket->getQuantityList());
    }

    public static function get_sum()
    {
        $basket = Sale\Basket::loadItemsForFUser(self::getFuserId(), \Bitrix\Main\Context::getCurrent()->getSite());
        return $basket->getPrice();
    }

   public static function getPaymentSystems()
   {
       $items = array();
       $paySystemResult = \Bitrix\Sale\PaySystem\Manager::getList(array(
           'filter' => array(

               'ACTIVE' => 'Y',

           ), 'order' => array('SORT' => 'ASC')
       ));
       while ($paySystem = $paySystemResult->fetch())
           $items[] = $paySystem;

       return $items;
   }

   public static function getDeliveries( $selected_delivery = false )
   {
       $deliveries = self::GetDeliveriesByRestrictions();

       if( $selected_delivery )
           $selected_delivery = \Bitrix\Sale\Delivery\Services\Manager::getById( $selected_delivery );

       if( $selected_delivery )
       {
           foreach($deliveries as $delivery)
           {
               if($delivery['PARENT_ID'] != $selected_delivery['PARENT_ID']) continue;
               $selected_delivery = $delivery;
           }
       }
       else
           $selected_delivery = reset($deliveries);

       $result = array(
           'ITEMS' => $deliveries,
           'SELECTED' => array(
               'ID' => $selected_delivery['ID'],
               'PRICE' => $selected_delivery['CONFIG']['MAIN']['PRICE']
           )
       );

       return $result;

   }

   private static function GetDeliveriesByRestrictions()
   {
       $basket = \Bitrix\Sale\Basket::loadItemsForFUser( self::getFuserId() , SITE_ID);
       $order = \Bitrix\Sale\Order::create(SITE_ID, self::getFuserId());
       $order->setPersonTypeId(1);
       $order->setBasket($basket);
       $shipmentCollection = $order->getShipmentCollection();
       $shipment = $shipmentCollection->createItem();
       $shipmentItemCollection = $shipment->getShipmentItemCollection();
       $shipment->setField('CURRENCY', $order->getCurrency());
       foreach ($order->getBasket() as $item)
       {
           $shipmentItem = $shipmentItemCollection->createItem($item);
           $shipmentItem->setQuantity($item->getQuantity());
       }

       $deliveryList = \Bitrix\Sale\Delivery\Services\Manager::getRestrictedList($shipment, \Bitrix\Sale\Services\Base\RestrictionManager::MODE_CLIENT);

       $result = array();
       foreach($deliveryList as $deliveryService)
       {
           if( $deliveryService['CLASS_NAME'] == '\Bitrix\Sale\Delivery\Services\Group' ) continue;

           $deliveryService['PRICE'] = !empty($deliveryService['CONFIG']['MAIN']['PRICE']) ? $deliveryService['CONFIG']['MAIN']['PRICE'] : 0;
           $result[$deliveryService['ID']] = $deliveryService;
       }

       if( !$result )
           return false;

       return $result;
   }

   public static function updateCartItem($cart_id, $arFields = array())
   {
       if( empty($arFields) )
           return false;

       \CSaleBasket::Update($cart_id, $arFields);
   }

   public function deleteItem($cart_id)
   {
       if( $cart_id )
            \CSaleBasket::Delete($cart_id);
   }


   public function getItemData(Sale\BasketItem $item)
   {
       $result = $item->getFieldValues();
       $this->makeCompatibleArray($result);
       $result['PRODUCT_ID'] = (int)$result['PRODUCT_ID'];
       $result['QUANTITY'] = $item->getQuantity();
       $result['MEASURE_NAME'] = (string)$result['MEASURE_NAME'];
       if ($result['MEASURE_NAME'] == '')
           $result['MEASURE_NAME'] = GetMessage('TSB1_MEASURE_NAME');
       $result['PRICE'] = Sale\PriceMaths::roundPrecision($result['PRICE']);
       $result['BASE_PRICE'] = Sale\PriceMaths::roundPrecision($result['BASE_PRICE']);
       $result['DISCOUNT_PRICE'] = Sale\PriceMaths::roundPrecision($result['DISCOUNT_PRICE']);
       $result['SUM_VALUE'] = $result['PRICE'] * $result['QUANTITY'];

       $result['SUM'] = \CCurrencyLang::CurrencyFormat($result['SUM_VALUE'], $result['CURRENCY'], true);
       $result['PRICE_FMT'] = \CCurrencyLang::CurrencyFormat($result['PRICE'], $result['CURRENCY'], true);
       $result['FULL_PRICE'] = \CCurrencyLang::CurrencyFormat($result['BASE_PRICE'], $result['CURRENCY'], true);

       // unused fields from \CSaleDiscount::DoProcessOrder - compatibility
       $result['PRICE_FORMATED'] = $result['PRICE_FMT'];
       $result['DISCOUNT_PRICE_PERCENT'] = Sale\Discount::calculateDiscountPercent(
           $result['BASE_PRICE'],
           $result['DISCOUNT_PRICE']
       );
       $result['DISCOUNT_PRICE_PERCENT_FORMATED'] = $result['DISCOUNT_PRICE_PERCENT'].'%';

       return $result;
   }

    /*
   private static function getProductProps( $product_id )
   {
        require __DIR__ . '/../config.php';

        $checkout_fields = preg_split('/\r\n|[\r\n]/', Option::get($ABELIZNOV_CONFIG['MODULE_ID'], "checkout_fields"));
        $checkout_product_props = preg_split('/\r\n|[\r\n]/', Option::get($ABELIZNOV_CONFIG['MODULE_ID'], "checkout_product_props"));
        $checkout_offer_props = preg_split('/\r\n|[\r\n]/', Option::get($ABELIZNOV_CONFIG['MODULE_ID'], "checkout_offer_props"));


        $offer = \CCatalogSku::GetProductInfo( $product_id );
        $offer_props = array();
        if( is_array($offer) )
        {
            foreach ($checkout_offer_props as $prop)
            {
                $prop_res = \CIBlockElement::GetProperty($offer['OFFER_IBLOCK_ID'], $offer['ID'], array("sort" => "asc"), Array("CODE" => $prop));
                $offer_props[$prop] = array();
                while($prop_value = $prop_res->Fetch())
                    $offer_props[$prop][] = $prop_value;
            }
            $product_iblock = $offer['IBLOCK_ID'];
        }
        else
        {
            $product_iblock = 0;
        }

        $product_props = array();
        foreach ($checkout_product_props as $prop)
        {
           $prop_res = \CIBlockElement::GetProperty($product_iblock, $product_id, array("sort" => "asc"), Array("CODE" => $prop));
           $offer_props[$prop] = array();
           while($prop_value = $prop_res->Fetch())
               $offer_props[$prop][] = $prop_value;
        }


        $fields = array_merge($checkout_fields, $checkout_product_props);

        $arProductData = getProductProps(array($product_id), $fields);
        if( !$arProductData ) return false;
        $arProductData = reset($arProductData);

        $arProductData['PROPERTIES'] = array();
        foreach ($checkout_product_props as $prop)
        {
            $prop_res = \CIBlockElement::GetProperty($arProductData['IBLOCK_ID'], $product_id, array("sort" => "asc"), Array("CODE" => $prop));
            $arProductData['PROPERTIES'][$prop] = array();
            while($prop_value = $prop_res->Fetch())
                $arProductData['PROPERTIES'][$prop][] = $prop_value;
        }

        return $arProductData;
    }
    */
    function getProductProperty($product_id, $property_code)
    {
        $offer = CCatalogSku::GetProductInfo($product_id);

        $product_iblock = $offer ? $offer['OFFER_IBLOCK_ID'] : CATALOG_IBLOCK;

        $property_res = CIBlockElement::GetProperty($product_iblock, $product_id, array("sort" => "asc"), Array("CODE" => $property_code));
        if($property = $property_res->Fetch())
        {
            return $property['VALUE'];
        }


        return false;
    }


    function getOneClickFields($product, $offer)
    {
        $product_res = CIBlockElement::GetList(array(), array('ID' => $product), false, array('nTopCount' => 1), array('ID', 'IBLOCK_ID', 'NAME', 'PREVIEW_PICTURE'));

        if( $arProduct = $product_res->GetNext())
        {
            $response_data = array(
                'ID' => $arProduct['ID'],
                'OFFER_ID' => 0,
                'NAME' => htmlspecialchars_decode($arProduct['NAME']),
            );
            $thumb = CFile::ResizeImageGet(
                $arProduct['PREVIEW_PICTURE'],
                array('width' => 215, 'height' => 215),
                BX_RESIZE_IMAGE_PROPORTIONAL
            );
            $response_data['IMAGE'] = $thumb ? $thumb['src'] : false;

            if( $offer )
            {
                $offers = CCatalogSKU::getOffersList($arProduct['ID'], CATALOG_IBLOCK, array( 'ID' => $offer), array('ID', 'PREVIEW_PICTURE', 'IBLOCK_ID', 'NAME'));
                $offers = reset($offers);
                if( !$offers ) return false;
                $offers = reset($offers);
                $response_data['OFFER_ID'] = $offers['ID'];
                if(!empty($offers['PREVIEW_PICTURE']) )
                {
                    $response_data['IMAGE'] = CFile::GetPath($offers['PREVIEW_PICTURE']);
                }
                else
                {
                    $response_data['IMAGE'] = SITE_TEMPLATE_PATH . "/assets/img/default-img.jpg";
                }
            }


            return $response_data;

        }
        return false;
    }

    private static function getPropertyByCode($propertyCollection, $code)  {
        foreach ($propertyCollection as $property)
        {
            if($property->getField('CODE') == $code)
                return $property;
        }
    }

    private static function registerUser($checkout_arr)
    {
        global $USER;
        if( !$USER->IsAuthorized() )
        {
            $checkout_arr['PERSONAL_PHONE'] = self::formatPhone($checkout_arr['PERSONAL_PHONE']);
            $checkout_arr['EMAIL'] = !empty($checkout_arr['EMAIL']) ? $checkout_arr['EMAIL'] : $checkout_arr['PERSONAL_PHONE'].'@'.$_SERVER['SERVER_NAME'];

            $user_found = \CUser::GetList($by="", $order="", array("LOGIN" => $checkout_arr['EMAIL']));
            if( $user_found->SelectedRowsCount() <= 0 )
            {
                $user_pass = randString();
                $arFields = Array(
                    "LOGIN"             => $checkout_arr['EMAIL'],
                    "ACTIVE"            => "Y",
                    "GROUP_ID"          => array(5),
                    "PASSWORD"          => $user_pass,
                    "CONFIRM_PASSWORD"  => $user_pass,
                );
                foreach($checkout_arr as $key => $item)
                    $arFields[$key] = $item;

                $user = new \CUser;
                $ID = $user->Add($arFields);

                if (intval($ID) <= 0)
                    return array('status' => 0, array('message' => $user->LAST_ERROR));
            }
            else
            {
                $ID = $user_found->Fetch()['ID'];
            }

            $USER->Authorize($ID, true);
            $FUSER_ID = \CSaleUser::GetList(array('USER_ID' => $ID));
            if(!$FUSER_ID['ID'])
            {
                $FUSER_ID = \CSaleUser::_Add(array("USER_ID" => $ID));
            }
            self::$currentFuser = $FUSER_ID['ID'];

            return array('status' => 1, array());
        }
    }

    public static function getCoupons()
    {
        return \Bitrix\Sale\DiscountCouponsManager::get(true);
    }

    public static function setCoupon($coupon)
    {
        $couponData = Sale\DiscountCouponsManager::getData($coupon, true);
        //$archeck2 =	DiscountCouponsManager::getCheckCodeList(true);
        //echo $archeck2[$couponData["CHECK_CODE"]]."</br>"; // Статус правила корзины
        //echo $archeck2[$couponData["STATUS"]]."</br>"; //Статус купона

        if( $couponData['CHECK_CODE'] == 0 && intval($couponData['DISCOUNT_ID']) > 0 && $couponData['ACTIVE'] == 'Y' )
        {
            Sale\DiscountCouponsManager::add($coupon);
            return $couponData;
        }
        return false;
    }

    public static  function removeCoupon()
    {
        \Bitrix\Sale\DiscountCouponsManager::clear(true);
        return true;
    }






    private function makeCompatibleArray(&$array)
    {
        if (empty($array) || !is_array($array))
            return;

        $arr = array();
        foreach ($array as $key => $value)
        {
            if (is_array($value) || preg_match("/[;&<>\"]/", $value))
            {
                $arr[$key] = htmlspecialcharsEx($value);
            }
            else
            {
                $arr[$key] = $value;
            }

            $arr['~'.$key] = $value;
        }

        $array = $arr;
    }


    public static function fastOrder($product_id, $checkout_config)
    {
        \CSaleBasket::DeleteAll( \CUser::GetID() );

        self::addToCart($product_id, 1);

        return self::checkout($checkout_config, false, false, true);
    }

    public static function checkout($checkout_config, $delivery, $payment, $is_fast_order = false)
    {
        $checkout_arr = $checkout_config;


        $siteId = \Bitrix\Main\Context::getCurrent()->getSite();
        $basket = Sale\Basket::loadItemsForFUser(\CSaleBasket::GetBasketUserID(), $siteId)->getOrderableItems();

        $currencyCode = Option::get('sale', 'default_currency', 'RUB');

        DiscountCouponsManager::init();

        global $USER;
        if( !$USER->IsAuthorized() )
        {
            $result = self::registerUser($checkout_arr);
            if( $result['status'] == 0 )
                return $result;
        }

        //$order = Order::create($siteId,  $this->currentFuser);
        $order = Order::create($siteId,  \CUser::GetID());
        $order->setPersonTypeId(isset($checkout_arr['PERSON_TYPE']) ? intval($checkout_arr['PERSON_TYPE']) : 1);
        $order->setBasket($basket);

        /*Shipment*/
        $shipmentCollection = $order->getShipmentCollection();
        if( $delivery )
        {
            $shipment = $shipmentCollection->createItem(
                \Bitrix\Sale\Delivery\Services\Manager::getObjectById($delivery)
            );
        }
        else
        {
            $shipment = $shipmentCollection->createItem();
        }
        $shipmentItemCollection = $shipment->getShipmentItemCollection();
        $shipment->setField('CURRENCY', $order->getCurrency());
        foreach ($order->getBasket() as $item)
        {
            $shipmentItem = $shipmentItemCollection->createItem($item);
            $shipmentItem->setQuantity($item->getQuantity());
        }
        $arDeliveryServiceAll = Delivery\Services\Manager::getRestrictedObjectsList($shipment);
        $shipmentCollection = $shipment->getCollection();

        if (!empty($arDeliveryServiceAll))
        {
            if( isset($arDeliveryServiceAll[$delivery]) )
            {
                $deliveryObj = $arDeliveryServiceAll[$delivery];
            }
            else
            {
                reset($arDeliveryServiceAll);
                $deliveryObj = current($arDeliveryServiceAll);
            }

            if ($deliveryObj->isProfile()) {
                $name = $deliveryObj->getNameWithParent();
            } else {
                $name = $deliveryObj->getName();
            }

            $shipment->setFields(array(
                'DELIVERY_ID' => $deliveryObj->getId(),
                'DELIVERY_NAME' => $name,
                'CURRENCY' => $order->getCurrency()
            ));

            $shipmentCollection->calculateDelivery();
        }

        /**/

        /*Payment*/
        $arPaySystemServiceAll = [];
        $paymentCollection = $order->getPaymentCollection();

        $remainingSum = $order->getPrice() - $paymentCollection->getSum();
        if ($remainingSum > 0 || $order->getPrice() == 0)
        {
            $extPayment = $paymentCollection->createItem();
            $extPayment->setField('SUM', $remainingSum);
            $arPaySystemServices = PaySystem\Manager::getListWithRestrictions($extPayment);

            $arPaySystemServiceAll += $arPaySystemServices;

            if (array_key_exists($payment, $arPaySystemServiceAll))
            {
                $arPaySystem = $arPaySystemServiceAll[$payment];
            }
            else
            {
                reset($arPaySystemServiceAll);

                $arPaySystem = current($arPaySystemServiceAll);
            }
            if (!empty($arPaySystem))
            {
                $extPayment->setFields(array(
                    'PAY_SYSTEM_ID' => $arPaySystem["ID"],
                    'PAY_SYSTEM_NAME' => $arPaySystem["NAME"]
                ));
            }
            else
                $extPayment->delete();
        }

        /*
        if( $arPaySystem['ACTION_FILE'] == 'sberbankonline' )
        {
            $order->setField("STATUS_ID", "NP");
        }
        */


        /**/

        $order->doFinalAction(true);

        $propertyCollection = $order->getPropertyCollection();

        foreach($checkout_arr as $k => $v)
        {
            $property = self::getPropertyByCode($propertyCollection, $k);
            if( $property )
            {
                $prop_arr = $property->getProperty();
                if( $prop_arr['REQUIRED'] == 'Y' && empty($v) && !$is_fast_order )
                {
                    return array('status' => 0, array('message' => 'Поле ' .$property->getField('NAME'). ' не заполнено'));
                }
                $property->setValue($v);
            }
        }

        $order->setField('CURRENCY', $currencyCode);
        if( $is_fast_order )
            $order->setField('USER_DESCRIPTION',  "Заказ в 1 клик");
        else
            $order->setField('USER_DESCRIPTION',  $_REQUEST['USER_DESCRIPTION']);
        $order->save();

        if (!$order->GetId())
        {
            return array('status' => 0, array('message' => $order->getErrors())) ;
        }
        else
        {
            return array('status' => 1, 'data' => array(
                'order_id' => $order->GetId(),
                'order_date' => date('d.m.Y H:i:s', strtotime($order->getDateInsert())),
            )) ;
        }
    }

    public static function getOrder($order_id)
    {
        //$order = CSaleOrder::GetByID($order_id);
        $order = Sale\Order::load($order_id);
		 if( !$order )
            return false;
        if($order->getUserId() == self::getFuserId() || $order->getUserId() == \CUser::GetID())
        {
            return $order;
        }
        return false;
    }
    private static function formatPhone($phone)
    {
        $phone = preg_replace('/\D/', '', $phone);
        $phone[0] = 7;
        return '+' . $phone;
    }
    private static function getFuserId()
    {
        if (self::$currentFuser === null)
            self::$currentFuser = \CSaleBasket::GetBasketUserID();

        return self::$currentFuser;
    }
}