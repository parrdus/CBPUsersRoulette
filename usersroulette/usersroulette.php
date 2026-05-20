<?php
declare(strict_types=1);

defined('B_PROLOG_INCLUDED') || die();

use Bitrix\Bizproc\FieldType;
use Bitrix\Main\Config\Option;
use Bitrix\Main\UserTable;
use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

Bitrix\Main\Loader::includeModule('iblock');

/**
 * Действие БП: случайный выбор исполнителя из списка/отдела с исключениями
 */
class CBPUsersRoulette extends CBPActivity
{
	//определение структуры properties заранее для конструктора и GetPropertiesDialog
	public static function getPropertySchema(): array
	{
		return [
			'DefaultUser' => ['default'=>null, 'Type' => FieldType::USER],
			'UsersList' => ['default'=>'', 'Type' => FieldType::STRING],
			'DeptList' => ['default'=>'', 'Type' => FieldType::STRING],
			'ForbiddenUsers' => ['default'=>'', 'Type' => FieldType::STRING],
			'User' => ['default'=>null, 'Type' => FieldType::USER],
			'LogMessage' => ['default'=>null, 'Type' => FieldType::STRING],
		];
	}
	
	public function __construct(string $name)
	{
		parent::__construct($name);
		
		$properties = self::getPropertySchema();
		
		$this->arProperties = [];
		$types = [];
		
		foreach ($properties as $name => $config) {
			$this->arProperties[$name] = $config['default'] ?? null;
			$types[$name] = ['Type' => $config['Type'] ?? ''];
		}
		
		$this->SetPropertiesTypes($types);
	}
	
	/**
	 * Основной метод выполнения активити.
	 */
	public function Execute ()
	{
		//Установим пользователя default
		$this->User = $this->formatUserId((int)$this->DefaultUser);
		$this->LogMessage = 'time: ' . date('d.m.Y H:i:s') . PHP_EOL;
		
		if (empty($this->DefaultUser)) {
			
			$error = Loc::getMessage('BPRU_ERROR_EMPTY_DEFAULT');
			
			$this->writeToTrackingService(sprintf(
				'CBPUsersRoulette stops action: %s (file: %s)',
				$error,
				__FILE__
			));
			
			$this->LogMessage .= 'Error: ' . $error;
			
			//раскомментировать для сохранения полного лога в файл
			//$this->logDebug($this->LogMessage);
			
			return CBPActivityExecutionStatus::Closed;
		}
		
		$result = $this->runRoulette();
		
		if ($result['user'] !== null) {
			$this->User = $result['user'];
		}
		$this->LogMessage .= $result['shortLog'];
		
		//раскомментировать для сохранения полного лога в файл
		//if (!empty($result['fullLog'])) $this->logDebug($result['fullLog']);
		
		return CBPActivityExecutionStatus::Closed;
	}
	
	/**
	 * Основная логика рулетки.
	 */
	private function runRoulette(): array
	{
		$usersList = $this->parseIdList($this->UsersList);
		$deptList = $this->parseIdList($this->DeptList);
		$forbiddenUsers = $this->parseIdList($this->ForbiddenUsers);
		
		$shortLog = '';
		$fullLog = '';
		//
		
		$shortLog .= Loc::getMessage('BPRU_LOG_DEFAULT_USER', [
			'#DEFAULT_USER#' => $this->User,
		]);
		$fullLog .= Loc::getMessage('BPRU_LOG_DEFAULT_USER', [
			'#DEFAULT_USER#' => $this->User,
		]);
		
		// 1. Собираем пользователей из явного списка
		$explicitUsers = [];
		if (!empty($usersList)) {
			$explicitUsers = $this->findUsers(['ID' => $usersList]);
			
			$shortLog .= Loc::getMessage('BPRU_LOG_EXPLICIT_USERS', [
				'#USERS_LIST#' => $this->formatUserList($explicitUsers, 50),
			]);
			$fullLog .= Loc::getMessage('BPRU_LOG_EXPLICIT_USERS', [
				'#USERS_LIST#' => $this->formatUserList($explicitUsers),
			]);
		}
		
		// 2. Собираем пользователей из отделов с подотделами
		$deptUsers = [];
		if (!empty($deptList)) {
			try {
				$deptIds = $this->findDeptIds($deptList);
				$deptUsers = $this->findUsers(['@UF_DEPARTMENT' => $deptIds]);
				
				$shortLog .= Loc::getMessage('BPRU_LOG_DEPT_RESOLVED', [
					'#DEPT_IDS#' => implode(', ', $deptIds),
				]);
				$fullLog .= Loc::getMessage('BPRU_LOG_DEPT_RESOLVED', [
					'#DEPT_IDS#' => implode(', ', $deptIds),
				]);

				$shortLog .= Loc::getMessage('BPRU_LOG_DEPT_USERS', [
					'#USERS_LIST#' => $this->formatUserList($deptUsers, 50),
				]);
				$fullLog .= Loc::getMessage('BPRU_LOG_DEPT_USERS', [
					'#USERS_LIST#' => $this->formatUserList($deptUsers),
				]);
				
			} catch (\RuntimeException $e) {
				$shortLog .= 'Error: ' . $e->getMessage() . PHP_EOL;
				$fullLog .= 'Error: ' . $e->getMessage() . PHP_EOL;
				
				return [
					'user' => null,
					'shortLog' => $shortLog,
					'fullLog' => $fullLog,
				];
			}
		}
		
		// 3. Логируем запрещённых пользователей
		$forbidden = [];
		if (!empty($forbiddenUsers)) {
			$forbidden = $this->findUsers(['ID' => $forbiddenUsers]);
			
			$shortLog .= Loc::getMessage('BPRU_LOG_FORBIDDEN_USERS', [
				'#USERS_LIST#' => $this->formatUserList($forbidden, 50),
			]);
			$fullLog .= Loc::getMessage('BPRU_LOG_FORBIDDEN_USERS', [
				'#USERS_LIST#' => $this->formatUserList($forbidden),
			]);
		}
		
		// 4. Формируем финальный пул
		$pool = $explicitUsers + $deptUsers;
		if (!empty($forbidden)) {
			$pool = array_diff_key($pool, $forbidden);
		}
		
		$poolIds = array_keys($pool);
		$poolSize = count($poolIds);
		
		if ($poolSize === 0) {
			$shortLog .= Loc::getMessage('BPRU_LOG_NO_CANDIDATES');
			$fullLog .= Loc::getMessage('BPRU_LOG_NO_CANDIDATES');
			
			return [
				'user' => null,
				'shortLog' => $shortLog,
				'fullLog' => $fullLog,
			];
		}
		else
		{
			$shortLog .= Loc::getMessage('BPRU_LOG_FINAL_POOL', [
				'#USERS_LIST#' => $this->formatUserList($pool, 50),
			]);
			$fullLog .= Loc::getMessage('BPRU_LOG_FINAL_POOL', [
				'#USERS_LIST#' => $this->formatUserList($pool),
			]);
		}
		
		// 5. Случайный выбор
		$winnerId = $poolIds[array_rand($poolIds)];
		$winner = $pool[$winnerId];

		$shortLog .= Loc::getMessage('BPRU_LOG_SELECTED', [
			'#USER_NAME#' => $winner['NAME'] . ' ' . $winner['LAST_NAME'],
			'#USER_ID#' => $winnerId,
			'#COUNT#' => $poolSize,
		]);
		
		$fullLog .= Loc::getMessage('BPRU_LOG_FINAL_POOL_FL', [
			'#COUNT#' => $poolSize,
			'#USERS_LIST#' => $this->formatUserList($pool),
		]);
		$fullLog .= Loc::getMessage('BPRU_LOG_WINNER', [
			'#KEY#' => array_search($winnerId, $poolIds),
			'#USER#' => 'user_' . $winnerId,
		]);
		
		return [
			'user' => $this->formatUserId((int)$winnerId),
			'shortLog' => $shortLog,
			'fullLog' => $fullLog,
		];
	}
	
	/**
	 * Парсит строку ID в массив уникальных целых чисел.
	 */
	private function parseIdList(?string $raw): array
	{
		if (empty($raw)) {
			return [];
		}

		$cleaned = preg_replace('/[^0-9,]/', '', (string)$raw);
		$parts = array_filter(explode(',', $cleaned), 'strlen');
		
		return array_unique(array_map('intval', $parts));
	}
	
	/**
	 * Форматирует ID пользователя в Bizproc-формат.
	 */
	private function formatUserId(int $id): string
	{
		return 'user_' . $id;
	}
	
	/**
	 * Форматирует список пользователей в виде строки для логов
	 */
	private function formatUserList(array $users, ?int $maxLength = null): string
	{
		$string = '';
		
		$string .= sprintf('count{%d}',count($users)) . ' = ';
		
		$names = array_map(
			fn(array $user): string => sprintf(
				'%s {%d}',
				trim($user['NAME'] . ' ' . $user['LAST_NAME']),
				(int)$user['ID']
			),
			$users
		);
		$nameString = implode(', ', $names);
		
		if ($maxLength > 0 && mb_strlen($nameString) > $maxLength)
		{
			$nameString = sprintf(
				'%s ... + %d symbols more',
				mb_substr($nameString, 0, $maxLength),
				mb_strlen($nameString) - $maxLength
			);
		}
		
		$string .= $nameString;
		
		return $string;
	}
	
	/**
	 * Поиск пользователей
	 */
	private function findUsers(array $filter): array
	{
		if (empty($filter)) {
			return [];
		}

		$result = UserTable::getList([
			'select' => ['ID', 'NAME', 'LAST_NAME'],
			'filter' => array_merge(['ACTIVE' => true], $filter),
			'order' => ['LAST_NAME' => 'ASC', 'NAME' => 'ASC'],
		]);

		$users = [];
		while ($row = $result->fetch()) {
			$users[(int)$row['ID']] = $row;
		}

		return $users;
	}
	
	/**
	 * Раскрывает родительские отделы в полный список включая подотделы.
	 * 
	 * @throws \RuntimeException если инфоблок структуры не найден
	 */
	private function findDeptIds(array $parentIds): array
	{
		$iblockId = (int)Option::get('intranet', 'iblock_structure', 0);
		
		if ($iblockId <= 0) {
			throw new \RuntimeException(Loc::getMessage('BPRU_ERROR_STRUCTURE_NOT_FOUND'));
		}
		
		// Получаем границы родительских разделов
		$parents = [];
		$rs = \CIBlockSection::GetList(
			[],
			['IBLOCK_ID' => $iblockId, 'ID' => $parentIds],
			false,
			['ID', 'LEFT_MARGIN', 'RIGHT_MARGIN']
		);
		
		while ($row = $rs->Fetch()) {
			$parents[] = [
				'LEFT_MARGIN' => (int)$row['LEFT_MARGIN'],
				'RIGHT_MARGIN' => (int)$row['RIGHT_MARGIN'],
			];
		}
		
		if (empty($parents)) {
			return [];
		}
		
		// Собираем все подотделы nested set
		$ranges = array_map(
			fn(array $p): string => sprintf(
				'(LEFT_MARGIN >= %d AND RIGHT_MARGIN <= %d)',
				$p['LEFT_MARGIN'],
				$p['RIGHT_MARGIN']
			),
			$parents
		);
		
		$sql = sprintf(
			'SELECT ID FROM b_iblock_section WHERE IBLOCK_ID = %d AND (%s)',
			$iblockId,
			implode(' OR ', $ranges)
		);
		
		$conn = Application::getConnection();
		$res = $conn->query($sql);
		
		$allIds = [];
		while ($row = $res->fetch()) {
			$allIds[] = (int)$row['ID'];
		}
		
		return $allIds;
	}
	
	/**
	 * Запись отладочного лога.
	 */
	private function logDebug(string $message): void
	{
		AddMessage2Log($message, 'CBPUsersRoulette');
	}
	
	// ==================== UI-методы ====================

	public static function GetPropertiesDialog(
		$documentType,
		$activityName,
		$arWorkflowTemplate,
		$arWorkflowParameters,
		$arWorkflowVariables,
		$arCurrentValues = null,
		$formName = ''
	) {
		$runtime = CBPRuntime::GetRuntime();
		
		if (!is_array($arCurrentValues)) {
			$arCurrentValues = array_map(
				fn($v) => $v['default'] ?? null, 
				self::getPropertySchema()
			);
			
			$arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName(
				$arWorkflowTemplate,
				$activityName
			);
			
			if (is_array($arCurrentActivity['Properties'])) {
				$arCurrentValues = array_merge(
					$arCurrentValues,
					$arCurrentActivity['Properties']
				);
			}
		}
		
		return $runtime->ExecuteResourceFile(
			__FILE__,
			'properties_dialog.php',
			[
				'arCurrentValues' => $arCurrentValues,
				'formName' => $formName,
			]
		);
	}

	public static function GetPropertiesDialogValues(
		$documentType,
		$activityName,
		&$arWorkflowTemplate,
		&$arWorkflowParameters,
		&$arWorkflowVariables,
		$arCurrentValues,
		&$arErrors
	): bool {
		$arErrors = [];
		$runtime = CBPRuntime::GetRuntime();
		
		// Валидация DefaultUser
		$defaultUser = (int)($arCurrentValues['DefaultUser'] ?? 0);
		
		if ($defaultUser <= 0) {
			$arErrors[] = [
				'code' => 'Empty',
				'message' => Loc::getMessage('BPRU_PD_ERROR_EMPTY_DEFAULT'),
			];
			return false;
		}
		
		// Санитизация списков ID
		$sanitizeList = function (?string $raw): string {
			if (empty($raw)) {
				return '';
			}
			$cleaned = preg_replace('/[^0-9,]/', '', $raw);
			$parts = array_filter(explode(',', $cleaned), 'strlen');
			return implode(',', array_unique(array_map('intval', $parts)));
		};
		
		$properties = [
			'DefaultUser' => $defaultUser,
			'UsersList' => $sanitizeList($arCurrentValues['UsersList'] ?? ''),
			'DeptList' => $sanitizeList($arCurrentValues['DeptList'] ?? ''),
			'ForbiddenUsers' => $sanitizeList($arCurrentValues['ForbiddenUsers'] ?? ''),
		];
		
		$arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName(
			$arWorkflowTemplate,
			$activityName
		);
		$arCurrentActivity['Properties'] = $properties;
		
		return true;
	}
}