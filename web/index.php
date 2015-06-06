<?
	//die('Закрыто!');
	require_once dirname(__FILE__) . '/app.php';

	session_save_path(dirname(__FILE__) . '/sessions');
	session_set_cookie_params(60 * 60 * 24 * 30, '/');
	session_start();

	function http_load($url)
	{
		$options = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_AUTOREFERER    => false,
			CURLOPT_CONNECTTIMEOUT => 120,
			CURLOPT_TIMEOUT        => 120,
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_SSL_VERIFYPEER => false
		);

		$ch = curl_init($url);

		curl_setopt_array($ch, $options);
		$data = curl_exec($ch);

		echo $data;

		curl_close($ch);

		return json_decode($data, true);
	}

	function generate_api_request($method, $params, $access_token, $secret = '')
	{
		$uri = '/method/' . $method . '?' . $params . '&v=5.8&access_token=' . $access_token;

		if (!empty($secret))
			$secret = '&sig=' . md5($uri . $secret);

		return 'https://api.vk.com' . $uri . $secret;
	}

	$content = '';

	if (isset($_GET['code']))
	{
		$params = array(
			'client_id' => VK_CLIENT_ID,
			'client_secret' => VK_CLIENT_SECRET,
			'code' => $_GET['code'],
			'redirect_uri' => VK_REDIRECT_URI
		);

		$result = http_load('https://oauth.vk.com/access_token' . '?' . urldecode(http_build_query($params)));

		if (!isset($result['access_token']))
			die('Hacking attempt!');

		$info = http_load(generate_api_request('users.get', 'users_id=' . $result['user_id'] . '&fields=photo_50', $result['access_token']));

		if (!isset($info['response']))
			die('failed');

		$info = $info['response'][0];

		$_SESSION['vkname'] = $info['first_name'] . ' ' . $info['last_name'];
		$_SESSION['vkphoto'] = $info['photo_50'];
		$_SESSION['vkid'] = $result['user_id'];

		header('Location: ' . $_SERVER['PHP_SELF']);
		die;
	}
	elseif (!isset($_SESSION['vkid']))
	{
		header('Location: https://oauth.vk.com/authorize?client_id=' . VK_CLIENT_ID . '&redirect_uri=http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '&response_type=code');
		die;
	}
	else
	{
		if (isset($_POST['login']))
		{
			if (!is_numeric($_POST['login']) || $_POST['login'] > 20 || $_POST['login'] < 10)
			{
				header('Location: ' . $_SERVER['PHP_SELF']);
				die;
			}

			$_SESSION['login'] = $_POST['login'];
		}
		elseif (isset($_POST['logout']))
		{
			unset($_SESSION['login']);
			header('Location: ' . $_SERVER['PHP_SELF']);
			die;
		}
		elseif (isset($_POST['query']) || isset($_POST['sqlscript']))
		{
			if (!isset($_SESSION['login']))
				die('Hacking attempt!');

			function my_exec($cmd, $input = '')
			{
				$proc = proc_open($cmd, array( 0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);

				fwrite($pipes[0], $input);
				fclose($pipes[0]);

				$stdout = stream_get_contents($pipes[1]);
				fclose($pipes[1]);

				$stderr = stream_get_contents($pipes[2]);
				fclose($pipes[2]);

				$rtn = proc_close($proc);

				return array('stdout' => $stdout, 'stderr' => $stderr, 'return' => $rtn);
			}

			function startsWith($haystack, $needle)
			{
				return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
			}

			if (isset($_POST['sqlscript']))
			{
				if (!isset($_FILES['sqlfile']) || !is_uploaded_file($_FILES['sqlfile']['tmp_name']))
					die('Upload failed! Retry again!');

				if (strtolower(pathinfo(basename($_FILES['sqlfile']['name']), PATHINFO_EXTENSION)) != 'sql')
					die('Upload file must ends with .sql!');

				$_POST['query'] = file_get_contents($_FILES['sqlfile']['tmp_name']);
				unlink($_FILES['sqlfile']['tmp_name']);
			}

			$_POST['query'] = trim($_POST['query']);
			$_SESSION['lastquery'] = $_POST['query'];

			$queries = explode(';', $_POST['query']);

			foreach ($queries as $key1 => &$query)
			{
				$lines = explode(PHP_EOL, $query);

				foreach ($lines as $key => &$line)
				{
					if (startsWith(trim($line), '--'))
						unset($lines[$key]);
				}

				$query = trim(implode(PHP_EOL, $lines));

				if (strlen($query) == 0)
					unset($queries[$key1]);
			}

			error_log('[' . date('d.m.y H:i:s') . ', ' . $_SESSION['vkname'] . ', ' . $_SESSION['vkid'] . '] ' . print_r($queries, true) . PHP_EOL, 3, dirname(__FILE__) . '/queries.log');

			$return = my_exec('java -jar ' . escapeshellarg(dirname(__FILE__) . '/app.jar') . ' ' . escapeshellarg($_SESSION['login']), json_encode($queries));

			if ($return['return'] != 0)
			{
				$content .= '<span style="color: red">Return code: ' . $return['return'] . ', Stderr:</span><br>
					<textarea rows="10" cols="100">' . htmlspecialchars(iconv('windows-1251', 'utf-8', $return['stderr']), ENT_QUOTES) . '</textarea><br><br>
				';

				if (!empty($return['stdout']))
				{
					$content .= 'Stdout:<br>
						<textarea rows="3" cols="100">' . htmlspecialchars(iconv('windows-1251', 'utf-8', $return['stdout']), ENT_QUOTES) . '</textarea><br><br>
					';
				}
			}
			else
			{
				$alldata = json_decode($return['stdout'], true);

				foreach ($alldata as &$data)
				{
					$thead = '';
					foreach($data['fields'] as $field)
					{
						$thead .= '<th>' . htmlspecialchars(iconv('windows-1251', 'utf-8', $field)) . '</th>';
					}

					$tbody = '';
					foreach($data['data'] as $dataField)
					{
						$tbody .= '<tr>';

						foreach($dataField as $value)
						{
							$vvv = htmlspecialchars(iconv('windows-1251', 'utf-8', $value));
							$tbody .= '<td>' . $vvv . (isset($_GET['mark_tbls']) ? ' [<a href="javascript:void(0);" onclick="pasteQuery1(\'' . $vvv . '\');">структура</a>]' : '') . '</td>';
						}

						$tbody .= '</tr>';
					}

					if ($_SESSION['vkid'] == '157756516')
						$content = '<img src="./trollface-dancing.gif" alt="" style="width: 400px; height: 400px"><br><br>';

					$content .= 'Запрос "' . htmlspecialchars(iconv('windows-1251', 'utf-8', $data['query'])) . '" выполнен:<br>
						<h4><table>
						<thead>' . $thead . '
						</thead>
						<tbody>' . $tbody . '
						</tbody>
						</table></h4><hr><br>
					';
				}
			}
		}

		if (isset($_SESSION['login']))
		{
			$content .= '
			<h4>
				Примеры: <a href="javascript:void(0);" onclick="pasteQuery1();">Вывести поля таблицы</a>, <a href="javascript:void(0);" onclick="pasteQuery2();">Вывести список таблиц</a>
				<br>
			</h4>
			<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">
				Запрос (<strong>мультизапросы: разделитель - точка с запятой</strong>): <br>
				<textarea id="query" name="query" rows="10" cols="100">' . (isset($_SESSION['lastquery']) ? htmlspecialchars($_SESSION['lastquery'], ENT_QUOTES) : '') . '</textarea><br>
				<div style="padding-top: 10px"></div>
				<input type="submit" value="Выполнить запрос(ы) из поля"> Logged as stud' . $_SESSION['login'] . ' <input type="submit" name="logout" value="Выход">
			</form>
			<form method="POST" enctype="multipart/form-data" action="' . $_SERVER['PHP_SELF'] . '" style="margin-top: 10px">
				<input type="hidden" name="MAX_FILE_SIZE" value="30000">
				<input name="sqlfile" type="file">
				<input type="submit" name="sqlscript" value="Выполнить скрипт">
			</form>
			<div id="vk_like" style="margin-top: 120px"></div>
			<div id="vk_comments" style="margin-top: 10px"></div>
			<script type="text/javascript">
				VK.Widgets.Comments("vk_comments", {limit: 5, width: "550", attach: "*"});
				VK.Widgets.Like("vk_like", {type: "button"});
			</script>
			';
		}
		else
		{
			$logins = '';
			$sel = rand(10, 20);

			for ($i = 10; $i <= 20; $i++)
				$logins .= '<option' . ($i == $sel ? ' selected' : '') . ' value="' . $i . '">' . $i . '</option>';

			$content = '
			<form method="POST">
				Login as: stud <select name="login">
				' . $logins . '
				</select><br>
				<input type="submit" value="OK">
			</form>';
		}
	}
?><!DOCTYPE html>
<html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8">
	<style type="text/css">
		body
		{
			font-family: "Myriad Pro", Arial, Helvetica, Tahoma, sans-serif;
			font-weight: regular;
			font-size: 15px;
		}
		table
		{
			border: solid #dfdfdf 1px;
		}
		table tr
		{
			background-color: #f2f2f2;
		}
		tr.head
		{
			font-weight: bold;
		}
		td
		{
			padding-top: 10px;
			padding-bottom: 10px;
		}
	</style>

	<script type="text/javascript" src="http://vk.com/js/api/openapi.js?116"></script>
	<script type="text/javascript" src="http://code.jquery.com/jquery-2.0.3.min.js"></script>
	<script type="text/javascript">
		VK.init({apiId: <?= VKAPI_APPID ?>, onlyWidgets: true});

		function pasteQuery1()
		{
			var table_name = (arguments.length == 0 ? prompt('Введите название таблицы') : arguments[0]);

			if (table_name.length != 0)
			{
				$('#query').html("SELECT RPAD(COLUMN_NAME,30) || ': ' || DATA_TYPE || '(' || DATA_LENGTH || ')' as descr FROM all_tab_cols WHERE TABLE_NAME = UPPER('" + table_name + "')");
				$('form:eq(0)').submit();
			}
		}

		function pasteQuery2()
		{
			if (!confirm('Продолжить?'))
				return;

			$('#query').html('SELECT table_name FROM user_tables');
			$('form:eq(0)').attr('action', $('form:eq(0)').attr('action') + '?mark_tbls').submit();
		}
	</script>
</head>
<body>
	<?= $content; ?>
</body>
</html>