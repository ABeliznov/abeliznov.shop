<?
if(!check_bitrix_sessid()){

  return;
}

?>

<form action="<? echo($APPLICATION->GetCurPage()); ?>">
	
	<?=bitrix_sessid_post()?>
	<input type="hidden" name="lang" value="<?echo LANG?>">
	<input type="hidden" name="id" value="<?=$_GET['id']?>">
	<input type="hidden" name="uninstall" value="Y">
	<input type="hidden" name="step" value="2">
	
	<p>
		<input type="checkbox" name="savedata" id="savedata" value="Y" checked>
		<label for="savedata">Сохранить данные</label>
	</p>
	
	
	<input type="submit" name="inst" value="Далее">
</form>