<?php
defined('B_PROLOG_INCLUDED') || die();

use Bitrix\Bizproc\FieldType;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$arActivityDescription = array(
  // Название действия для конструтора.
  'NAME' => Loc::getMessage('BPRU_ACTIVITY_NAME'),
  
  // Описание действия для конструктора.
  'DESCRIPTION' => Loc::getMessage('BPRU_ACTIVITY_DESCRIPTION'),
  
  // Тип: “activity” - действие, “condition” - ветка составного действия.
  'TYPE' => 'activity',
  
  // Название класса действия без префикса “CBP”.
  'CLASS' => 'UsersRoulette',
  
  // Название JS-класса для управления внешним видом и поведением в конструкторе.
  'JSCLASS' => 'BizProcActivity',

  // Категория действия в конструкторе
  'CATEGORY' => array(
    'ID'       => 'custom_group',
    'OWN_ID'   => 'custom_group',
    'OWN_NAME' => Loc::getMessage('BPRU_ACTIVITY_CATEGORY_NAME'),
  ),
  
  'RETURN' => [
    'User' => [
        'NAME' => Loc::getMessage('BPRU_ACTIVITY_RETURN_USER'),
        'TYPE' => FieldType::USER,
    ],
    'LogMessage' => [
        'NAME' => Loc::getMessage('BPRU_ACTIVITY_RETURN_LOG'),
        'TYPE' => FieldType::STRING,
    ],
],
  
);