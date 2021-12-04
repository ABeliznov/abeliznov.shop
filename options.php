<?
use Abeliznov\Shop;
use Bitrix\Main\HttpApplication;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;


require __DIR__ . '/config.php';

$MODULE_ID = $ABELIZNOV_CONFIG['MODULE_ID'];

IncludeModuleLangFile(__FILE__);

global $APPLICATION, $CONFIG;

$POST_RIGHT = $APPLICATION->GetGroupRight($MODULE_ID);

if ($POST_RIGHT != "W")
    $APPLICATION->ThrowException(Loc::getMessage('ACCESS_DENIED'));

$request = HttpApplication::getInstance()->getContext()->getRequest();

Loader::includeModule($MODULE_ID);


$aTabs = array(
    array(
        "DIV" => "edit1",
        "TAB" => Loc::getMessage('ABELIZNOV_SHOP_CONFIG'),
        "TITLE" => Loc::getMessage('ABELIZNOV_SHOP_CONFIG'),
    ),
    array(
        "DIV" => "edit3",
        "TAB" => Loc::getMessage('ABELIZNOV_ACCESS'),
        "TITLE" => Loc::getMessage('ABELIZNOV_ACCESS'),
    )

);

if ($request->isPost() && check_bitrix_sessid()) {


    if (isset($_POST['abeliznov_shop'])) {
        foreach ($_POST['abeliznov_shop'] as $key => $value) {
            $optionValue = $value;
            Option::set($MODULE_ID, $key, is_array($optionValue) ? implode(",", $optionValue) : $optionValue);
        }
    }


    $Update = $Update . $Apply;
    ob_start();
    require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/admin/group_rights.php");
    ob_end_clean();

    LocalRedirect($APPLICATION->GetCurPage() . "?mid=" . $MODULE_ID . "&lang=" . LANG);
}


$tabControl = new CAdminTabControl(
    "tabControl",
    $aTabs
);

$tabControl->Begin();
?>

    <form action="<? echo($APPLICATION->GetCurPage()); ?>?mid=<? echo($MODULE_ID); ?>&lang=<? echo(LANG); ?>"
          method="post">
        <input type="hidden" name="module_id" value="<?= $MODULE_ID ?>"/>
        <?
        $tabControl->BeginNextTab();
        $checkout_product_props = Option::get($MODULE_ID, "checkout_product_props");
        $checkout_offer_props = Option::get($MODULE_ID, "checkout_offer_props");
        $checkout_offer_recount_props = Option::get($MODULE_ID, "checkout_offer_recount_props");
        $checkout_fields = Option::get($MODULE_ID, "checkout_fields");
        $discount_recount_prop = Option::get($MODULE_ID, "discount_recount_prop");

        ?>

        <tr class="heading">
            <td colspan="2"><?=Loc::GetMessage('ABELIZNOV_SHOP_GENERAL_SETTINGS')?></td>
        </tr>

        <tr>
            <td width="50%" class="adm-detail-content-cell-l"><?=Loc::GetMessage('ABELIZNOV_SHOP_RECOUNT_DISCOUNTS_BY_PROP')?></td>
            <td width="50%" class="adm-detail-content-cell-r">
                <input type="text" name="abeliznov_shop[discount_recount_prop]" value="<?=$discount_recount_prop?>" >
            </td>
        </tr>
        <tr>
            <td width="50%" class="adm-detail-content-cell-l"><?=Loc::GetMessage('ABELIZNOV_SHOP_CART_FIELDS')?></td>
            <td width="50%" class="adm-detail-content-cell-r">
                <textarea cols="42" rows="6" name="abeliznov_shop[checkout_fields]"><?= $checkout_fields ?></textarea>
            </td>
        </tr>
        <tr>
            <td width="50%" class="adm-detail-content-cell-l"><?=Loc::GetMessage('ABELIZNOV_SHOP_CART_PRODUCT_PROPERTIES')?></td>
            <td width="50%" class="adm-detail-content-cell-r">
                <textarea cols="42" rows="6" name="abeliznov_shop[checkout_product_props]"><?= $checkout_product_props ?></textarea>
            </td>
        </tr>
        <tr>
            <td width="50%" class="adm-detail-content-cell-l"><?=Loc::GetMessage('ABELIZNOV_SHOP_CART_OFFER_PROPERTIES')?></td>
            <td width="50%" class="adm-detail-content-cell-r">
                <textarea cols="42" rows="6" name="abeliznov_shop[checkout_offer_props]"><?= $checkout_offer_props ?></textarea>
            </td>
        </tr>
        <tr>
            <td width="50%" class="adm-detail-content-cell-l"><?=Loc::GetMessage('ABELIZNOV_SHOP_CART_OFFER_RECOUNT_PROPERTIES')?></td>
            <td width="50%" class="adm-detail-content-cell-r">
                <textarea cols="42" rows="6" name="abeliznov_shop[checkout_offer_recount_props]"><?= $checkout_offer_recount_props ?></textarea>
            </td>
        </tr>


        <? $tabControl->BeginNextTab(); ?>
        <? require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/admin/group_rights.php"); ?>

        <? $tabControl->Buttons(); ?>

        <input type="submit" class="adm-btn-green" name="Apply" value="<?= Loc::GetMessage('ABELIZNOV_SAVE') ?>">


        <?
        echo(bitrix_sessid_post());
        ?>

    </form>

<?
$tabControl->End();
?>