<?php
/**
 * ================================================
 * Настройки https://getwork.today/
 * ================================================
 */
$cabinet = '';	// ← Ссылка на ваш личный кабинет (субдомен)
$api_key = '';	// ← API ключ

define('GETWORK_BASE_URL', 'https://' . $cabinet . '.getwork.today/public-api');
define('GETWORK_API_KEY',  $api_key);


function print_pre($data, $colorOrTitle = null, $maybeTitle = null) {
	$knownColors = ['default', 'error', 'success', 'info', 'warning'];

	$color = 'default';
	$title = null;

	if ($colorOrTitle !== null) {
		if (is_string($colorOrTitle) && in_array($colorOrTitle, $knownColors, true)) {
			// Второй параметр — это цвет
			$color = $colorOrTitle;
			$title = $maybeTitle;
		} else {
			// Второй параметр — это заголовок
			$title = $colorOrTitle;
			// Если третий — цвет, то используем его
			if ($maybeTitle !== null && is_string($maybeTitle) && in_array($maybeTitle, $knownColors, true)) {
				$color = $maybeTitle;
			}
		}
	}

	// Темы
	$themes = [
		'error'   => ['bg' => '#ffebee', 'text' => '#c62828', 'border' => '#ef9a9a'],
		'success' => ['bg' => '#e8f5e9', 'text' => '#2e7d32', 'border' => '#a5d6a7'],
		'info'    => ['bg' => '#e3f2fd', 'text' => '#1565c0', 'border' => '#90caf9'],
		'warning' => ['bg' => '#fff3e0', 'text' => '#ef6c00', 'border' => '#ffcc80'],
		'default' => ['bg' => '#f5f5f5', 'text' => '#333333', 'border' => '#cccccc'],
	];

	$theme = $themes[$color] ?? $themes['default'];

	$containerStyle = sprintf(
		'background-color:%s;color:%s;padding:15px;border:1px solid %s;border-radius:8px;overflow:auto;font-family:Consolas,Monaco,"Courier New",monospace;font-size:14px;line-height:1.5;margin:15px 0;box-shadow:0 2px 6px rgba(0,0,0,0.1);',
		$theme['bg'],
		$theme['text'],
		$theme['border']
	);

	echo '<pre style="' . $containerStyle . '">';

	if ($title !== null) {
		$safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
		echo '<strong style="display:block;font-size:17px;margin-bottom:12px;padding-bottom:6px;border-bottom:2px solid ' . $theme['text'] . ';">';
		echo $safeTitle;
		echo '</strong>';
	}

	print_r($data);
	echo '</pre>';
}


/**
 * Базовая функция для выполнения запросов к GetWork API
 *
 * @param string $method       HTTP-метод (GET, POST и т.д.)
 * @param string $endpoint     путь после /public-api, например '/user'
 * @param array  $data         данные для POST/PUT (обычно ассоциативный массив)
 * @param array  $query_params GET-параметры (?page=1&limit=20...)
 * @return array ['http_code' => int, 'response' => string (обычно json)]
 */
function getwork_request($method, $endpoint, $data = [], $query_params = []) {

	$url = GETWORK_BASE_URL . $endpoint;

	if (!empty($query_params)) {
		$url .= '?' . http_build_query($query_params);
	}

	$ch = curl_init($url);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'apikey: ' . GETWORK_API_KEY,
		'Content-Type: application/json'
	]);

	// Для POST/PUT
	if (in_array($method, ['POST', 'PUT'])) {
		if (isset($data['multipart']) && $data['multipart'] === true) {
			// multipart/form-data (onboarding, создание задания и др.)
			unset($data['multipart']);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				'apikey: ' . GETWORK_API_KEY
			]);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		} else {
			// обычный JSON
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}
	}

	$response  = curl_exec($ch);
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	curl_close($ch);

	return [
		'http_code' => $http_code,
		'response'  => $response
	];
}

// ────────────────────────────────────────────────
// Обёртки для конкретных методов
// ────────────────────────────────────────────────

function getwork_onboarding_sign_oferta($phone, $code) {
	$data = [
		'phone'     => $phone,
		'code'      => $code,
		'multipart' => true
	];
	return getwork_request('POST', '/onboarding/sign-oferta', $data);
}

function getwork_onboarding_sign_document($phone, $code) {
	$data = [
		'phone'     => $phone,
		'code'      => $code,
		'multipart' => true
	];
	return getwork_request('POST', '/onboarding/sign-document', $data);
}

function getwork_pz_tasks_create($task_data) {
	$task_data['multipart'] = true;
	return getwork_request('POST', '/pz-tasks/user/create', $task_data);
}

function getwork_pz_task_get($task_id) {
	return getwork_request('GET', "/pz-tasks/user/{$task_id}");
}

function getwork_pz_directory() {
	return getwork_request('GET', '/pz-tasks/user/directory');
}

function getwork_payouts_list($filters = []) {
	// $filters может содержать: page, limit, project_id, organization_id, selfemployed_inn и т.д.
	return getwork_request('GET', '/payouts', [], $filters);
}

function getwork_registry_payouts_create($payload) {
	// $payload должен содержать payouts[], organization_id, project_id
	return getwork_request('POST', '/registry-payouts', $payload);
}

function getwork_registry_payout_get($registry_id, $query = []) {
	return getwork_request('GET', "/registry-payouts/{$registry_id}", [], $query);
}

function getwork_registry_payouts_list($project_id, $organization_id = '') {
	$path = "/registry-payouts/{$project_id}/list";
	if ($organization_id !== '') {
		$path .= "/{$organization_id}";
	}
	return getwork_request('GET', $path);
}

function getwork_registry_selfemployed_create($payload) {
	// $payload: selfemployeds[], organization_id, project_id, is_pz?
	return getwork_request('POST', '/registry-selfemployed', $payload);
}

function getwork_registry_selfemployed_get($registry_id, $query = []) {
	return getwork_request('GET', "/registry-selfemployed/{$registry_id}", [], $query);
}

function getwork_registry_selfemployed_list($project_id, $organization_id = '') {
	$path = "/registry-selfemployed/{$project_id}/list";
	if ($organization_id !== '') {
		$path .= "/{$organization_id}";
	}
	return getwork_request('GET', $path);
}

function getwork_registry_selfemployed_list_all($project_id, $organization_id = '') {
	$path = "/registry-selfemployed/{$project_id}/selfemployeds";
	if ($organization_id !== '') {
		$path .= "/{$organization_id}";
	}
	return getwork_request('GET', $path);
}

function getwork_user_info() {
	return getwork_request('GET', '/user');
}

// ────────────────────────────────────────────────
// ПРИМЕРЫ ИСПОЛЬЗОВАНИЯ
// ────────────────────────────────────────────────

/*
// 1. Подписание оферты
echo "1. Подписание оферты<br>";
$res = getwork_onboarding_sign_oferta('79991234567', '483920');
print_pre($res);
print_pre(json_decode($res['response'], true), 'warning');
echo "<br><br>";

// 2. Подписание документов
echo "2. Подписание документов<br>";
$res = getwork_onboarding_sign_document('79991234567', '574821');
print_pre($res);
print_pre(json_decode($res['response'], true), 'warning');
echo "<br><br>";

// 3. Создание задания (pz-tasks/user/create)
echo "3. Создание задания<br>";
$task_data = [
	'title'                   => 'Разработка лендинга под ключ',
	'description'             => 'Нужен современный лендинг для продажи онлайн-курсов. 5 блоков + форма заявки.',
	'price_type'              => 'FIXED',
	'organization_id'         => '4c4101f7-eae4-4e23-a5db-30d7af198760',
	'pz_project_id'           => 'bdbafa99-9f16-4b9a-a523-bfb1cf0af2e3',
	'pz_tasks_work_type_id'   => '3b6ea44b-13da-4f94-b9c4-7468aabfe296',
	'price'                   => '85000',
	'date_start'              => '15.04.2026',
	'date_end'                => '30.04.2026',
	'address_place'           => 'ONLINE',
	'work_time'               => 'FULL_TIME',
	'frequency_payment'       => 'ONCE_MONTH',
];
$res = getwork_pz_tasks_create($task_data);
print_pre($res);
print_pre(json_decode($res['response'], true), 'warning');
echo "<br><br>";

// 4. Получение одного задания по ID
echo "4. Получение задания по ID<br>";
$task_id = 'e76f0644-d78d-11ed-afa1-0242ac120002';  // тестовый uuid из документации
$res = getwork_pz_task_get($task_id);
print_pre($res);
print_pre(json_decode($res['response'], true), 'warning');
echo "<br><br>";


// 5. Получение справочников (directory)
echo "5. Справочники для создания заданий<br>";
$res = getwork_pz_directory();
print_pre($res);
print_pre(json_decode($res['response'], true), 'warning');
echo "<br><br>";

// 6. Список выплат (payouts) с фильтрами
echo "6. Список выплат (первые 20)<br>";
$filters = [
	'page'               => 1,
	'limit'              => 20,
	'created_at_from'    => '01.03.2026',
	'created_at_to'      => '10.03.2026',
	//'selfemployed_inn' => '123456789012',
];
$res = getwork_payouts_list($filters);
print_pre($res);
print_pre(json_decode($res['response'], true), 'warning');
echo "<br><br>";

// 7. Создание реестра выплат
echo "7. Создание реестра выплат<br>";
$registry_payload = [
	'organization_id' => '4c4101f7-eae4-4e23-a5db-30d7af198760',
	'project_id'      => 'f4774522-a7e2-4d0f-b84d-75dd49b718fc',
	'payouts'         => [
		[
			'vatin'                 => '7707083893',
			'job_quantity'          => 40,
			'job_unit'              => 'ч',
			'job_date_start'        => '01.03.2026',
			'job_date_end'          => '31.03.2026',
			'service_name'          => 'Разработка и поддержка сайта',
			'payout_total_amount'   => 148000.00,
			'receipt_total_amount'  => 157680.00,  // обычно +6% НПД
			'comment'               => 'Март 2026 — основной функционал',
		],
		[
			'vatin'                 => '7725778789',
			'job_quantity'          => 12,
			'job_unit'              => 'шт',
			'job_date_start'        => '05.03.2026',
			'job_date_end'          => '05.03.2026',
			'service_name'          => 'Создание 12 баннеров',
			'payout_total_amount'   => 36000.00,
			'receipt_total_amount'  => 38160.00,
		],
	],
];
$res = getwork_registry_payouts_create($registry_payload);
print_pre($res);
print_pre(json_decode($res['response'], true), 'warning');
echo "<br><br>";

// 8. Получение информации по конкретному реестру выплат
echo "8. Просмотр реестра выплат<br>";
$registry_id = '759cb801-f39e-4b93-879e-04d49394c448';  // пример из доки
$query = [
	'limit' => 50,
	//'search' => 'Иванов_Иван_79891234567',
];
$res = getwork_registry_payout_get($registry_id, $query);
print_pre($res);
print_pre(json_decode($res['response'], true), 'warning');
echo "<br><br>";


// 9. Список реестров выплат по проекту и организации
echo "9. Список реестров выплат проекта<br>";
$res = getwork_registry_payouts_list(
	'f4774522-a7e2-4d0f-b84d-75dd49b718fc',
	'4c4101f7-eae4-4e23-a5db-30d7af198760'
);
print_pre($res);
print_pre(json_decode($res['response'], true), 'warning');
echo "<br><br>";

// 10. Загрузка реестра самозанятых
echo "10. Добавление самозанятых в проект<br>";
$selfemployed_payload = [
	'organization_id' => '4c4101f7-eae4-4e23-a5db-30d7af198760',
	'project_id'      => 'f4774522-a7e2-4d0f-b84d-75dd49b718fc',
	'is_pz'           => true,
	'selfemployeds'   => [
		['vatin' => '123456789012', 'phone' => '79991234567'],
		['vatin' => '987654321098', 'phone' => '79161239876'],
		['vatin' => '112233445566', 'phone' => '79031237788'],
	],
];
$res = getwork_registry_selfemployed_create($selfemployed_payload);
print_pre($res);
print_pre(json_decode($res['response'], true), 'warning');
echo "<br><br>";

// 11. Просмотр реестра самозанятых
echo "11. Просмотр реестра самозанятых<br>";
$registry_id = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
$res = getwork_registry_selfemployed_get($registry_id, ['limit' => 100]);
print_pre($res);
print_pre(json_decode($res['response'], true), 'warning');
echo "<br><br>";

// 12. Список реестров самозанятых по проекту
echo "12. Список реестров самозанятых<br>";
$res = getwork_registry_selfemployed_list(
	'f4774522-a7e2-4d0f-b84d-75dd49b718fc',
	'4c4101f7-eae4-4e23-a5db-30d7af198760'
);
print_pre($res);
print_pre(json_decode($res['response'], true), 'warning');
echo "<br><br>";

// 13. Все самозанятые проекта / организации
echo "13. Все самозанятые в проекте<br>";
$res = getwork_registry_selfemployed_list_all(
	'f4774522-a7e2-4d0f-b84d-75dd49b718fc',
	'4c4101f7-eae4-4e23-a5db-30d7af198760'
);
print_pre($res);
print_pre(json_decode($res['response'], true), 'warning');
echo "<br><br>";
*/

// 14. Информация о текущем пользователе
//echo "14. Кто я (информация о пользователе)<br>";
$res = getwork_user_info();
print_pre($res);
print_pre(json_decode($res['response'], true), 'warning');
