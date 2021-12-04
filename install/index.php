<?
use Bitrix\Main\Loader;
use Bitrix\Highloadblock;
use Bitrix\Main\Entity;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;

IncludeModuleLangFile(__FILE__);

class abeliznov_shop extends CModule{

    public  $MODULE_ID;
    public  $MODULE_VERSION;
    public  $MODULE_VERSION_DATE;
    public  $MODULE_NAME;
    public  $MODULE_DESCRIPTION;
    public  $MODULE_PATH;
    public  $WISHLIST_HL;

	public function __construct(){
        $path = str_replace("\\", "/", __FILE__);
        $path = substr($path, 0, strlen($path) - strlen("/install/index.php"));

		if(file_exists(__DIR__."/version.php"))
		{
			include ($path."/install/version.php");
			include ($path."/config.php");

			$this->WISHLIST_HL = $ABELIZNOV_CONFIG['HL_WISHLIST_NAME'];
            $this->MODULE_PATH = $path;
			$this->MODULE_ID            = str_replace("_", ".", get_class($this));
            $this->MODULE_VERSION       = $arModuleVersion["VERSION"];
			$this->MODULE_VERSION_DATE  = $arModuleVersion["VERSION_DATE"];
			$this->MODULE_NAME          = Loc::GetMessage('ABELIZNOV_SHOP_MODULE_NAME');
			$this->MODULE_DESCRIPTION   = Loc::GetMessage('ABELIZNOV_SHOP_MODULE_DESCRIPTION');
            $this->PARTNER_NAME = GetMessage("ABELIZNOV_INSTALL_PARTNER_NAME");
            $this->PARTNER_URI = 'https://vk.com/id341961737';
		}

	   return false;
	}
	
	public function DoInstall()
	{

		global $APPLICATION;

		if(!CheckVersion(ModuleManager::getVersion("main"), "14.00.00"))
			$APPLICATION->ThrowException(Loc::getMessage('ABELIZNOV_SHOP_CMS_VERSION_ERROR'));

		$this->InstallFiles();
		$this->InstallDB();
		ModuleManager::registerModule($this->MODULE_ID);
		$this->InstallEvents();

		$APPLICATION->IncludeAdminFile(
            Loc::getMessage('ABELIZNOV_SHOP_MODULE_INSTALL'),
			__DIR__."/step.php"
		);

	    return false;
	}
	
	public function DoUninstall()
	{

		global $APPLICATION, $step;
		
		$step = IntVal($step);
		


		if( $step < 2 )
		{
			$APPLICATION->IncludeAdminFile(
                Loc::getMessage('ABELIZNOV_SHOP_MODULE_UNISTALL'),
				__DIR__."/unstep1.php"
			);
		}
		elseif( $step == 2 )
		{
			if( !isset($_REQUEST['savedata']) )
				$this->UnInstallDB();
			
			$this->UnInstallFiles();
			$this->UnInstallEvents();
			ModuleManager::unRegisterModule($this->MODULE_ID);
			
			$APPLICATION->IncludeAdminFile(
                Loc::getMessage('ABELIZNOV_SHOP_MODULE_UNISTALL'),
				__DIR__."/unstep2.php"
			);
		}

		return false;
	}
	
	public function InstallFiles()
	{
		return false;
	}
	
	
	public function UnInstallFiles()
	{
		return false;
	}
	
	
	public function InstallDB()
	{

        Loader::IncludeModule("highloadblock");
        $hlblock = Highloadblock\HighloadBlockTable::getList([
            'filter' => ['=NAME' => $this->WISHLIST_HL]
        ])->fetch();
        if( !$hlblock )
        {
            $result = Highloadblock\HighloadBlockTable::add(array(
                'NAME' => $this->WISHLIST_HL,
                'TABLE_NAME' => strtolower($this->WISHLIST_HL),
            ));

            $oUserTypeEntity = new CUserTypeEntity();
            $aUserFields = array(
                'ENTITY_ID'         => 'HLBLOCK_'.$result->getId(),
                'FIELD_NAME'        => 'UF_USER_ID',
                'USER_TYPE_ID'      => 'integer',
                'XML_ID'            => 'XML_ID_USER_ID',
                'SORT'              => 500,
                'MULTIPLE'          => 'N',
                'MANDATORY'         => 'N',
                'SHOW_FILTER'       => 'N',
                'SHOW_IN_LIST'      => '',
                'EDIT_IN_LIST'      => '',
                'IS_SEARCHABLE'     => 'N',
                'SETTINGS'          => array(
                    'DEFAULT_VALUE' => '',
                    'SIZE'          => '20',
                    'ROWS'          => '1',
                    'MIN_LENGTH'    => '0',
                    'MAX_LENGTH'    => '0',
                    'REGEXP'        => '',
                ),
                'EDIT_FORM_LABEL'   => array(
                    'ru'    => Loc::GetMessage('ABELIZNOV_SHOP_HL_USER_ID'),
                    'en'    => 'User ID',
                ),
                'LIST_COLUMN_LABEL' => array(
                    'ru'    => Loc::GetMessage('ABELIZNOV_SHOP_HL_USER_ID'),
                    'en'    => 'User ID',
                ),
                'LIST_FILTER_LABEL' => array(
                    'ru'    => Loc::GetMessage('ABELIZNOV_SHOP_HL_USER_ID'),
                    'en'    => 'User ID',
                ),
                'ERROR_MESSAGE'     => array(
                    'ru'    => Loc::GetMessage('ABELIZNOV_SHOP_HL_FIELD_ERROR'),
                    'en'    => 'An error in completing the user field',
                ),
                'HELP_MESSAGE'      => array(
                    'ru'    => '',
                    'en'    => '',
                ),
            );
            $iUserFieldId   = $oUserTypeEntity->Add( $aUserFields );
            $aUserFields = array(
                'ENTITY_ID'         => 'HLBLOCK_'.$result->getId(),
                'FIELD_NAME'        => 'UF_PRODUCT_ID',
                'USER_TYPE_ID'      => 'integer',
                'XML_ID'            => 'XML_ID_PRODUCT_ID',
                'SORT'              => 500,
                'MULTIPLE'          => 'N',
                'MANDATORY'         => 'N',
                'SHOW_FILTER'       => 'N',
                'SHOW_IN_LIST'      => '',
                'EDIT_IN_LIST'      => '',
                'IS_SEARCHABLE'     => 'N',
                'SETTINGS'          => array(
                    'DEFAULT_VALUE' => '',
                    'SIZE'          => '20',
                    'ROWS'          => '1',
                    'MIN_LENGTH'    => '0',
                    'MAX_LENGTH'    => '0',
                    'REGEXP'        => '',
                ),
                'EDIT_FORM_LABEL'   => array(
                    'ru'    => Loc::GetMessage('ABELIZNOV_SHOP_HL_PRODUCT_ID'),
                    'en'    => 'Product ID',
                ),
                'LIST_COLUMN_LABEL' => array(
                    'ru'    => Loc::GetMessage('ABELIZNOV_SHOP_HL_PRODUCT_ID'),
                    'en'    => 'Product ID',
                ),
                'LIST_FILTER_LABEL' => array(
                    'ru'    => Loc::GetMessage('ABELIZNOV_SHOP_HL_PRODUCT_ID'),
                    'en'    => 'Product ID',
                ),
                'ERROR_MESSAGE'     => array(
                    'ru'    => Loc::GetMessage('ABELIZNOV_SHOP_HL_FIELD_ERROR'),
                    'en'    => 'An error in completing the user field',
                ),
                'HELP_MESSAGE'      => array(
                    'ru'    => '',
                    'en'    => '',
                ),
            );
            $iUserFieldId   = $oUserTypeEntity->Add( $aUserFields );

        }

		return false;
	}
	public function InstallEvents()
	{
		return false;
    }
	
	public function UnInstallEvents()
	{
		return false;
	}
	
	
	public function UnInstallDB()
	{
        CModule::IncludeModule("highloadblock");
        $hlblock = Highloadblock\HighloadBlockTable::getList([
            'filter' => ['=NAME' => $this->WISHLIST_HL]
        ])->fetch();

        if( isset($hlblock['ID'] ) )
        {
            Highloadblock\HighloadBlockTable::delete($hlblock['ID']);
            $oUserTypeEntity    = new CUserTypeEntity();
            $fields_res = CUserTypeEntity::GetList(array(), array('ENTITY_ID' => 'HLBLOCK_'.$hlblock['ID']));
            while($arField = $fields_res->Fetch())
            {
                $oUserTypeEntity->Delete( $arField['ID'] );
            }
        }

		return false;
	}
}