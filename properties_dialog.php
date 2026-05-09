<?
defined('B_PROLOG_INCLUDED') || die;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
?>

<tr>
	<td align="right" width="40%" valign="top">
		<span class="adm-required-field">*<?= Loc::getMessage('BPRU_PD_DEFAULT_USER') ?>:</span>
		<br><small><?= Loc::getMessage('BPRU_PD_DEFAULT_USER_HINT') ?></small>
	</td>
	<td width="60%">
		<?=CBPDocument::ShowParameterField("string", 'DefaultUser', $arCurrentValues['DefaultUser'], Array('rows'=>'1'))?>
	</td>
</tr>

<tr>
	<td align="right" width="40%">
		<span class="adm-detail-content-cell-l"><?= Loc::getMessage('BPRU_PD_USERS_LIST') ?>:</span>
		<br><small><?= Loc::getMessage('BPRU_PD_USERS_LIST_HINT') ?></small>
	</td>
	<td width="60%">
		<?=CBPDocument::ShowParameterField("string", 'UsersList', $arCurrentValues['UsersList'], Array('rows'=>'2'))?>
	</td>
</tr>

<tr>
	<td align="right" width="40%">
		<span class="adm-detail-content-cell-l"><?= Loc::getMessage('BPRU_PD_DEPT_LIST') ?>:</span>
		<br><small><?= Loc::getMessage('BPRU_PD_DEPT_LIST_HINT') ?></small>
	</td>
	<td width="60%">
		<?=CBPDocument::ShowParameterField("string", 'DeptList', $arCurrentValues['DeptList'], Array('rows'=>'2'))?>
	</td>
</tr>

<tr>
	<td align="right" width="40%">
		<span class="adm-detail-content-cell-l"><?= Loc::getMessage('BPRU_PD_FORBIDDEN_USERS') ?>:</span>
		<br><small><?= Loc::getMessage('BPRU_PD_FORBIDDEN_USERS_HINT') ?></small>
	</td>
	<td width="60%">
		<?=CBPDocument::ShowParameterField("string", 'ForbiddenUsers', $arCurrentValues['ForbiddenUsers'], Array('rows'=>'2'))?>
	</td>
</tr>