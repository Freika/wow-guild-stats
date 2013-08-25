<?php
/*
Plugin Name: WoW Guild Stats
Plugin URI: http://example.com/wordpress-plugins/my-plugin
Description: Описание плагина
Version: 0.1
Author: Evgeniy Burmakin
Author URI: http://freika.ru/
License: GPLv2
*/

//echo plugin_dir_path( __FILE__ );




 
//Хук для активации плагина. Внутри функции может проверяться совместимость версии и/или устанавливаться какие-то служебные опции

//__файл)__ - путь к вызываемому файлу, второе - имя функции, которая будет вызвана при активации плагина


function wgs_install () {
   global $wpdb;

   $table_name = $wpdb->prefix . "wowguildstats";
   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
      
      $sql = "CREATE TABLE " . $table_name . " (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  `name` varchar(40) CHARACTER SET utf8 NOT NULL,
	  `realm` varchar(40) CHARACTER SET utf8 NOT NULL,
	  `respec` int(10) NOT NULL,
	  `deaths_overall` int(10) NOT NULL,
	  `quests_complete` int(10) NOT NULL,
	  `dailys_complete` int(10) NOT NULL,
	  `dungeons_complete` int(10) NOT NULL,
	  `raids_10` int(10) NOT NULL,
	  `raids_25` int(10) NOT NULL,
	  `flights_sum` int(10) NOT NULL,
	  `hearthstone_sum` int(10) NOT NULL,
	  `hugs_sum` int(10) NOT NULL,
	  UNIQUE KEY id (id)
	);";

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($sql);

   }
}

register_activation_hook(__FILE__,'wgs_install');



	


add_action( 'plugins_loaded', 'frey_plugin_base_run' );

function frey_plugin_base_run() {
	/* добавляем действие */
	add_action( 'wp_footer', 'frey_plugin_base_footer_text', 100); //Где выполняем, что выполняем, с каким приоритетом
}

function frey_plugin_base_footer_text() {
	echo "Некоторый текст, который выведется в футере";
}


add_action( 'admin_menu', 'fpb_admin_settings_page' );
function fpb_admin_settings_page() {
add_options_page(
'WoW Guild Stats', //название пункта в настройках
'WoW Guild Stats',
'manage_options',
'wow-guild-stats',
'wow_guild_stats_page'
);
}


function wow_guild_stats_page() {
global $wpdb;
//Если указаны сервер и имя гильдии, то получаем список членов гильдии
if(isset($_POST['realm']) && isset($_POST['guildname'])) {
	update_option( 'wgs_realm', rawurlencode($_POST['realm']) );
	update_option( 'wgs_guildname', $_POST['guildname'] );
	$realm = rawurlencode($_POST['realm']);
	$guildname = rawurlencode($_POST['guildname']);
	$guildFields = "?fields=members";
  	$armoryRequestUrl = 'http://eu.battle.net/api/wow/guild/'.$realm.'/'.$guildname.$guildFields;
  	$result = file_get_contents($armoryRequestUrl, true);
  	$roster = json_decode($result, true);
	$members = $roster['members'];

	//каждого найденного члена гильдии через цикл помещаем в базу данных
	foreach($roster['members'] as $member) {
	    $name = $member[character][name];
	    $realm = $member[character][realm];

	    $result = mysql_query("SELECT `id` FROM `".$wpdb->prefix."wowguildstats` WHERE `name`='$name' and `realm`='$realm'");
		$myrow = mysql_fetch_array($result);

		//проверяем, не содержится ли уже в персонаж в БД
		if (!empty($myrow['id'])) {
		echo "Извините, персонаж по имени ".$name." уже содержится в базе данных.<br>";
		}
		//Если нет, то заносим в БД и говорим об этом на странице
		else {
			$sql = $wpdb->query("INSERT INTO `".$wpdb->prefix."wowguildstats` SET `id` = NULL, `name` = '$name', `realm` = '$realm'");
	        if ($sql) {
	    	echo "Участник гильдии ".$name." занесен в базу данных.<br>";
	    }
	    	else {
	    		echo mysql_error();
	    	}
		}   
    }
}
//Если была нажата кнопка "Обновить инфо", то обновляем список персонажей с уведомлениями
 	if (isset($_POST['refreshRoster']) && $_POST['refreshRoster'] == 'refresh') {
	$guildFields = "?fields=members";

	$realm = get_option('wgs_realm');
	$guildname = get_option( 'wgs_guildname' );

  	$armoryRequestUrl = 'http://eu.battle.net/api/wow/guild/'.$realm.'/'.$guildname.$guildFields;

  	$result = file_get_contents($armoryRequestUrl, true);
  	$roster = json_decode($result, true);
  	$members = $roster['members'];

  	foreach ($roster['members'] as $member) {
	    $name = $member[character][name];
	    $realm = $member[character][realm];
  		$sql = mysql_query("SELECT * FROM `".$wpdb->prefix."wowguildstats` WHERE `name` = '$name' AND `realm` = '$realm'");
  		$result = mysql_fetch_assoc($sql);

  		if ($name == $result['name'] && $realm == $result['realm']) {
  			echo "<span style='color: green'>".$name." по-прежнему в гильдии</span><br>";
  		}
  		elseif ($name !== $result['name'] && $realm !== $result['realm']) {
  			echo "<span style='color: yellow'>Персонаж ".$name." вступил в гильдию и добавлен в базу данных.<br>";
  		}
  		elseif ($result['name'] !== $name and $result['realm'] !== $realm) {
  			$sql = "DELETE FROM `".$wpdb->prefix."wowguildstats` WHERE `name` = '$result[name]' AND `realm` = '$result[realm]'";
  			$query = mysql_query($sql);
  			if ($query) {
  				echo "<span style='color: red'>Персонаж ".$name." покинул гильдию и был удален из базы данных.<br>";
  			}
  			else {
  				echo "<span style='color: red'>Персонаж ".$name." покинул гильдию, но не был удален из базы данных. Удалите все данные из базы данных и перезапросите их из Оружейной снова.<br>";
  			}

  		}
  		else {
  			echo "There is an error occure";
  		}

  	}
} 
//Получаем статы по всем
if (isset($_POST['getStats']) && $_POST['getStats'] == 'getStats') {

	$guildFields = "?fields=members";
	$characterFields = "?fields=statistics";
	$realm = get_option('wgs_realm');
	$guildname = get_option( 'wgs_guildname' );
  	$armoryRequestUrl = 'http://eu.battle.net/api/wow/guild/'.$realm.'/'.$guildname.$guildFields;


  	$result = file_get_contents($armoryRequestUrl, true);
  	$roster = json_decode($result, true);
  	$members = $roster['members'];

  	foreach ($roster['members'] as $member) {
	    $name = $member[character][name];
	    $realm = $member[character][realm];
  		$sql = mysql_query("SELECT * FROM `".$wpdb->prefix."wowguildstats` WHERE `name` = '$name' AND `realm` = '$realm'");
  		$result = mysql_fetch_assoc($sql);
		$characterRequestUrl = 'http://eu.battle.net/api/wow/character/'.$realm.'/'.$name.$characterFields;

   		$headers = get_headers($characterRequestUrl);
if ($headers[0] == "HTTP/1.1 404 Not Found") {
echo 'Персонаж '.$name.' не найден. Возможно, он ниже 10 уровня и не отображается в Оружейной.<br>';
}
else {
   		$requestStatistics = file_get_contents($characterRequestUrl);
   		$json = json_decode($requestStatistics);
  		$statistics = $json->statistics;
  		$getclass = $json->class;
  		$query = mysql_query("UPDATE  `".$wpdb->prefix."wowguildstats` SET `class` = '$getclass' WHERE id= $result[id]");
     foreach ($statistics->subCategories as $sub) {
      foreach ($sub->statistics as $stat) {
	  	switch ($stat->id) {
	  		case '1149':
	  			$query = mysql_query("UPDATE `".$wpdb->prefix."wowguildstats` SET `respec` = '$stat->quantity' WHERE id = $result[id]");
	  			break;
	  		case '1043':
	  			$query = mysql_query("UPDATE `".$wpdb->prefix."wowguildstats` SET `needs` = '$stat->quantity' WHERE id = $result[id]");
	  			break;
	  		case '197':
	  			$query = mysql_query("UPDATE `".$wpdb->prefix."wowguildstats` SET `damage_sum` = '$stat->quantity' WHERE id = $result[id]");
	  			break;
	  		case '198':
	  			$query = mysql_query("UPDATE `".$wpdb->prefix."wowguildstats` SET `heal_sum` = '$stat->quantity' WHERE id = $result[id]");
	  			break;
	  		case '60':
	  			$query = mysql_query("UPDATE `".$wpdb->prefix."wowguildstats` SET `deaths_overall` = '$stat->quantity' WHERE id = $result[id]");
	  			break;
	  		case '98':
	  			$query = mysql_query("UPDATE `".$wpdb->prefix."wowguildstats` SET `quests_complete` = '$stat->quantity' WHERE id = $result[id]");
	  			break;
	  		case '97':
	  			$query = mysql_query("UPDATE `".$wpdb->prefix."wowguildstats` SET `dailys_complete` = '$stat->quantity' WHERE id = $result[id]");
	  			break;
	  		case '932':
	  			$query = mysql_query("UPDATE `".$wpdb->prefix."wowguildstats` SET `dungeons_complete` = '$stat->quantity' WHERE id = $result[id]");
	  			break;
	  		case '933':
	  			$query = mysql_query("UPDATE `".$wpdb->prefix."wowguildstats` SET `raids_10` = '$stat->quantity' WHERE id = $result[id]");
	  			break;
	  		case '934':
	  			$query = mysql_query("UPDATE `".$wpdb->prefix."wowguildstats` SET `raids_25` = '$stat->quantity' WHERE id = $result[id]");
	  			break;
	  		case '349':
	  			$query = mysql_query("UPDATE `".$wpdb->prefix."wowguildstats` SET `flights_sum` = '$stat->quantity' WHERE id = $result[id]");
	  			break;
	  		case '353':
	  			$query = mysql_query("UPDATE `".$wpdb->prefix."wowguildstats` SET `hearthstone_sum` = '$stat->quantity' WHERE id = $result[id]");
	  			break;
	  		case '1042':
	  			$query = mysql_query("UPDATE `".$wpdb->prefix."wowguildstats` SET `hugs_sum` = '$stat->quantity' WHERE id = $result[id]");
	  			break;
	  		case '8278':
	  			$query = mysql_query("UPDATE `".$wpdb->prefix."wowguildstats` SET `pet_battles_won` = '$stat->quantity' WHERE id = $result[id]");
	  			break;
	  		
	  		default:
	  			
	  			break;
	  	}
      }

     }
      echo "Статистика по персонажу ".$json->name." внесена в БД.<br>";
  	}	
  }

  	
}


$what = mysql_query("SELECT COUNT(id) FROM `".$wpdb->prefix."wowguildstats`");
$howmuch = mysql_fetch_array($what);

echo "Количество членов гильдии в базе данных: ".$howmuch[0];

	?>

	<form action="" method="POST">
		<label for="realm">Сервер</label>
		<br>
		<select name="realm" id="realm">
		   <option value="azuregos" <?php if (get_option('wgs_realm') == 'azuregos') {echo 'selected';} ?>>Азурегос</option>
		   <option value="borean-tundra" <?php if (get_option('wgs_realm') == 'borean-tundra') {echo 'selected';} ?>>Борейская тундра</option>
		   <option value="eversong" <?php if (get_option('wgs_realm') == 'eversong') {echo 'selected';} ?>>Вечная Песня</option>
		   <option value="galakrond" <?php if (get_option('wgs_realm') == 'galakrond') {echo 'selected';} ?>>Галакронд</option>
		   <option value="goldrinn" <?php if (get_option('wgs_realm') == 'goldrinn') {echo 'selected';} ?>>Голдринн</option>
		   <option value="gordunni" <?php if (get_option('wgs_realm') == 'gordunni') {echo 'selected';} ?>>Гордунни</option>
		   <option value="grom" <?php if (get_option('wgs_realm') == 'grom') {echo 'selected';} ?>>Гром</option>
		   <option value="fordragon" <?php if (get_option('wgs_realm') == 'fordragon') {echo 'selected';} ?>>Дракономор</option>
		   <option value="lich-King" <?php if (get_option('wgs_realm') == 'lich-King') {echo 'selected';} ?>>Король Лич</option>
		   <option value="booty-Bay" <?php if (get_option('wgs_realm') == 'booty-Bay') {echo 'selected';} ?>>Пиратская бухта</option>
		   <option value="deepholm" <?php if (get_option('wgs_realm') == 'azuregos') {echo 'selected';} ?>>Подземье</option>
		   <option value="razuvious" <?php if (get_option('wgs_realm') == 'razuvious') {echo 'selected';} ?>>Разувий</option>
		   <option value="howling-fjord" <?php if (get_option('wgs_realm') == 'howling-fjord') {echo 'selected';} ?>>Ревущий фьорд</option>
		   <option value="soulflayer" <?php if (get_option('wgs_realm') == 'soulflayer') {echo 'selected';} ?>>Свежеватель душ</option>
		   <option value="greymane" <?php if (get_option('wgs_realm') == 'greymane') {echo 'selected';} ?>>Седогрив</option>
		   <option value="deathguard" <?php if (get_option('wgs_realm') == 'deathguard') {echo 'selected';} ?>>Страж Смерти</option>
		   <option value="thermaplugg" <?php if (get_option('wgs_realm') == 'thermaplugg') {echo 'selected';} ?>>Термоштепсель</option>
		   <option value="deathweaver" <?php if (get_option('wgs_realm') == 'deathweaver') {echo 'selected';} ?>>Ткач Смерти</option>
		   <option value="blackscar" <?php if (get_option('wgs_realm') == 'blackscar') {echo 'selected';} ?>>Черный шрам</option>
		   <option value="ashenvale" <?php if (get_option('wgs_realm') == 'ashenvale') {echo 'selected';} ?>>Ясеневый лес</option>
		</select>
		<br>
		<label for="guildname">Название гильдии</label>
		<br>
		<input type="text" text="" id="guildname" name="guildname" value="<?php echo get_option('wgs_guildname'); ?>">
		<br>
		<button type="submit" id="getRoster" name="getRoster">Получить данные о гильдии</button>
	</form>
	<form action="" method="POST">
		<button type="submit" name="refreshRoster" id="refreshRoster" value="refresh">Обновить информацию о составе гильдии</button>
	</form>
	<form action="" method="POST">
		<button type="submit" name="getStats" id="getStats" value="getStats">Получить статистику по персонажам</button>
	</form>

<div class="info">Порядок действия

<p>1. Выберите сервер и введите название вашей гильдии.</p>
<p>2. Нажмите кнопку "Получить статистику по персонажам". Если ваша гильдия содержит значительное количество персонажей(более 100), то страница будет обновляться 2-15 минут, в зависимости от того, как много игроков состоит в гильдии. По окончании процесса страница будет обновлена и вы увидите результаты: получены ли данные.</p>
<br>
<p>Для того, чтобы обновить данные, нажмите кнопку "Обновить информацию о составе гильдии". По окончании операции вы получите информацию об ушедших из гильдии игроках.</p>

</div>



	<?php
}




function respecTop() {
global $wpdb;	

//Вычисляем топ-3 по респекам
$compareTest = mysql_query("SELECT `name`, `respec`, `class` FROM `".$wpdb->prefix."wowguildstats`");
$compare = mysql_fetch_array($compareTest);



$var = array();

do {
	$var[]=array('respec'=>$compare[respec], 'name'=>$compare[name], 'class'=>$compare['class']);
} while ( $compare = mysql_fetch_array($compareTest));

rsort($var);

echo "<script type='text/javascript' src='http://www.google.com/jsapi'></script>
    <script type='text/javascript'>
      google.load('visualization', '1', {packages: ['corechart']});
    </script>
    <script type='text/javascript'>
      function drawVisualization() {
        // Create and populate the data table.
        var data = google.visualization.arrayToDataTable([
          ['Year', '".$var[0][name]."', '".$var[1][name]."', '".$var[2][name]."'],
          ['',  ".$var[0][respec].", ".$var[1][respec].", ".$var[2][respec]." ]
        ]);
      
        // Create and draw the visualization.
        new google.visualization.BarChart(document.getElementById('respec')).
            draw(data,
                 {title:'Топ-3 по количесту смен специализаций',
                  width:400, height:300,
                  vAxis: {title: ''},
                  hAxis: {title: ''}}
            );
      }
      

      google.setOnLoadCallback(drawVisualization);
    </script>";

echo "<div id='respec' style='width: 450px; height: 300px;'></div>";
// !-- конец топ-3 по респекам
}

add_shortcode( 'respec', 'respecTop' );

function deathTop() {
global $wpdb;	

//Вычисляем топ-3 по респекам
$compareTest = mysql_query("SELECT `name`, `deaths_overall`, `class` FROM `".$wpdb->prefix."wowguildstats`");
$compare = mysql_fetch_array($compareTest);



$var = array();

do {
	$var[]=array('deaths_overall'=>$compare[deaths_overall], 'name'=>$compare[name], 'class'=>$compare['class']);
} while ( $compare = mysql_fetch_array($compareTest));

rsort($var);

echo "<script type='text/javascript' src='http://www.google.com/jsapi'></script>
    <script type='text/javascript'>
      google.load('visualization', '1', {packages: ['corechart']});
    </script>
    <script type='text/javascript'>
      function drawVisualization() {
        // Create and populate the data table.
        var data = google.visualization.arrayToDataTable([
          ['Year', '".$var[0][name]."', '".$var[1][name]."', '".$var[2][name]."'],
          ['',  ".$var[0][deaths_overall].", ".$var[1][deaths_overall].", ".$var[2][deaths_overall]." ]
        ]);
      
        // Create and draw the visualization.
        new google.visualization.BarChart(document.getElementById('deaths_overall')).
            draw(data,
                 {title:'Топ-3 по количеству смертей',
                  width:400, height:300,
                  vAxis: {title: ''},
                  hAxis: {title: ''}}
            );
      }
      

      google.setOnLoadCallback(drawVisualization);
    </script>";

echo "<div id='deaths_overall' style='width: 450px; height: 300px;'></div>";
// !-- конец топ-3 по респекам
}

add_shortcode( 'deaths_overall', 'deathTop' );

function questsTop() {
global $wpdb;	

//Вычисляем топ-3 по сумме нанесенного урона
$compareTest = mysql_query("SELECT `name`, `quests_complete`, `class` FROM `".$wpdb->prefix."wowguildstats`");
$compare = mysql_fetch_array($compareTest);



$var = array();

do {
	$var[]=array('quests_complete'=>$compare[quests_complete], 'name'=>$compare[name], 'class'=>$compare['class']);
} while ( $compare = mysql_fetch_array($compareTest));

rsort($var);

switch ($var[0]['class']) {
	case 3:
		$zerocolor = '#ABD473';
		break;
	case 4:
		$zerocolor = '#FFF569';
		break;
	case 1:
		$zerocolor = '#C79C6E';
		break;
	case 2:
		$zerocolor = '#F58CBA';
		break;
	case 7:
		$zerocolor = '#0070DE';
		break;
	case 8:
		$zerocolor = '#69CCF0';
		break;
	case 5:
		$zerocolor = '#FFFFFF';
		break;
	case 6:
		$zerocolor = '#C41F3B';
		break;
	case 11:
		$zerocolor = '#FF7D0A';
		break;
	case 9:
		$zerocolor = '#9482C9';
		break;
	case 10:
		$zerocolor = '#00FF96';
		break;
	default:
		$zerocolor = '#000';
		break;
}

switch ($var[1]['class']) {
	case 3:
		$firstcolor = '#ABD473';
		break;
	case 4:
		$firstcolor = '#FFF569';
		break;
	case 1:
		$firstcolor = '#C79C6E';
		break;
	case 2:
		$firstcolor = '#F58CBA';
		break;
	case 7:
		$firstcolor = '#0070DE';
		break;
	case 8:
		$firstcolor = '#69CCF0';
		break;
	case 5:
		$firstcolor = '#FFFFFF';
		break;
	case 6:
		$firstcolor = '#C41F3B';
		break;
	case 11:
		$firstcolor = '#FF7D0A';
		break;
	case 9:
		$firstcolor = '#9482C9';
		break;
	case 10:
		$firstcolor = '#00FF96';
		break;
	default:
		$firstcolor = '#000';
		break;
}

switch ($var[0]['class']) {
	case 3:
		$lastcolor = '#ABD473';
		break;
	case 4:
		$lastcolor = '#FFF569';
		break;
	case 1:
		$lastcolor = '#C79C6E';
		break;
	case 2:
		$lastcolor = '#F58CBA';
		break;
	case 7:
		$lastcolor = '#0070DE';
		break;
	case 8:
		$lastcolor = '#69CCF0';
		break;
	case 5:
		$lastcolor = '#FFFFFF';
		break;
	case 6:
		$lastcolor = '#C41F3B';
		break;
	case 11:
		$lastcolor = '#FF7D0A';
		break;
	case 9:
		$lastcolor = '#9482C9';
		break;
	case 10:
		$lastcolor = '#00FF96';
		break;
	default:
		$lastcolor = '#000';
		break;
}

echo "<script type='text/javascript' src='http://www.google.com/jsapi'></script>
    <script type='text/javascript'>
      google.load('visualization', '1', {packages: ['corechart']});
    </script>
    <script type='text/javascript'>
      function drawVisualization() {
        // Create and populate the data table.
        var data = google.visualization.arrayToDataTable([
          ['Year', '".$var[0][name]."', '".$var[1][name]."', '".$var[2][name]."'],
          ['',  ".$var[0][quests_complete].", ".$var[1][quests_complete].", ".$var[2][quests_complete]." ]
        ]);
      
        // Create and draw the visualization.
        new google.visualization.BarChart(document.getElementById('quests_complete')).
            draw(data,
                 {title:'Топ-3 по сумме выполненных квестов',
                 //colors: ['".$zerocolor."', '".$firstcolor."', 'red'],
                  width:400, height:300,
                  vAxis: {title: ''},
                  hAxis: {title: ''}}
            );
      }
      

      google.setOnLoadCallback(drawVisualization);
    </script>";

echo "<div id='quests_complete' style='width: 450px; height: 300px;'></div>";
// !-- конец топ-3 по сумме нанесенного урона
}

add_shortcode( 'quests_complete', 'questsTop' );

function dailyTop() {
global $wpdb;	

//Вычисляем топ-3 по респекам
$compareTest = mysql_query("SELECT `name`, `dailys_complete`, `class` FROM `".$wpdb->prefix."wowguildstats`");
$compare = mysql_fetch_array($compareTest);



$var = array();

do {
	$var[]=array('dailys_complete'=>$compare[dailys_complete], 'name'=>$compare[name], 'class'=>$compare['class']);
} while ( $compare = mysql_fetch_array($compareTest));

rsort($var);

echo "<script type='text/javascript' src='http://www.google.com/jsapi'></script>
    <script type='text/javascript'>
      google.load('visualization', '1', {packages: ['corechart']});
    </script>
    <script type='text/javascript'>
      function drawVisualization() {
        // Create and populate the data table.
        var data = google.visualization.arrayToDataTable([
          ['Year', '".$var[0][name]."', '".$var[1][name]."', '".$var[2][name]."'],
          ['',  ".$var[0][dailys_complete].", ".$var[1][dailys_complete].", ".$var[2][dailys_complete]." ]
        ]);
      
        // Create and draw the visualization.
        new google.visualization.BarChart(document.getElementById('dailys_complete')).
            draw(data,
                 {title:'Топ-3 по количеству выполненных дейликов',
                  width:400, height:300,
                  vAxis: {title: ''},
                  hAxis: {title: ''}}
            );
      }
      

      google.setOnLoadCallback(drawVisualization);
    </script>";

echo "<div id='dailys_complete' style='width: 450px; height: 300px;'></div>";
// !-- конец топ-3 по респекам
}

add_shortcode( 'dailys_complete', 'dailyTop' );

function dungeonsTop() {
global $wpdb;	

//Вычисляем топ-3 по респекам
$compareTest = mysql_query("SELECT `name`, `dungeons_complete`, `class` FROM `".$wpdb->prefix."wowguildstats`");
$compare = mysql_fetch_array($compareTest);



$var = array();

do {
	$var[]=array('dungeons_complete'=>$compare[dungeons_complete], 'name'=>$compare[name], 'class'=>$compare['class']);
} while ( $compare = mysql_fetch_array($compareTest));

rsort($var);

echo "<script type='text/javascript' src='http://www.google.com/jsapi'></script>
    <script type='text/javascript'>
      google.load('visualization', '1', {packages: ['corechart']});
    </script>
    <script type='text/javascript'>
      function drawVisualization() {
        // Create and populate the data table.
        var data = google.visualization.arrayToDataTable([
          ['Year', '".$var[0][name]."', '".$var[1][name]."', '".$var[2][name]."'],
          ['',  ".$var[0][dungeons_complete].", ".$var[1][dungeons_complete].", ".$var[2][dungeons_complete]." ]
        ]);
      
        // Create and draw the visualization.
        new google.visualization.BarChart(document.getElementById('dungeons_complete')).
            draw(data,
                 {title:'Топ-3 по количеству пройденных подземелий на 5 игроков',
                  width:400, height:300,
                  vAxis: {title: ''},
                  hAxis: {title: ''}}
            );
      }
      

      google.setOnLoadCallback(drawVisualization);
    </script>";

echo "<div id='dungeons_complete' style='width: 450px; height: 300px;'></div>";
// !-- конец топ-3 по респекам
}

add_shortcode( 'dungeons_complete', 'dungeonsTop' );

function raids10Top() {
global $wpdb;	

//Вычисляем топ-3 по респекам
$compareTest = mysql_query("SELECT `name`, `raids_10`, `class` FROM `".$wpdb->prefix."wowguildstats`");
$compare = mysql_fetch_array($compareTest);



$var = array();

do {
	$var[]=array('raids_10'=>$compare[raids_10], 'name'=>$compare[name], 'class'=>$compare['class']);
} while ( $compare = mysql_fetch_array($compareTest));

rsort($var);

echo "<script type='text/javascript' src='http://www.google.com/jsapi'></script>
    <script type='text/javascript'>
      google.load('visualization', '1', {packages: ['corechart']});
    </script>
    <script type='text/javascript'>
      function drawVisualization() {
        // Create and populate the data table.
        var data = google.visualization.arrayToDataTable([
          ['Year', '".$var[0][name]."', '".$var[1][name]."', '".$var[2][name]."'],
          ['',  ".$var[0][raids_10].", ".$var[1][raids_10].", ".$var[2][raids_10]." ]
        ]);
      
        // Create and draw the visualization.
        new google.visualization.BarChart(document.getElementById('raids_10')).
            draw(data,
                 {title:'Топ-3 по количеству посещенных рейдов на 10 игроков',
                  width:400, height:300,
                  vAxis: {title: ''},
                  hAxis: {title: ''}}
            );
      }
      

      google.setOnLoadCallback(drawVisualization);
    </script>";

echo "<div id='raids_10' style='width: 450px; height: 300px;'></div>";
// !-- конец топ-3 по респекам
}

add_shortcode( 'raids_10', 'raids10Top' );

function raids25Top() {
global $wpdb;	

//Вычисляем топ-3 по респекам
$compareTest = mysql_query("SELECT `name`, `raids_25`, `class` FROM `".$wpdb->prefix."wowguildstats`");
$compare = mysql_fetch_array($compareTest);



$var = array();

do {
	$var[]=array('raids_25'=>$compare[raids_25], 'name'=>$compare[name], 'class'=>$compare['class']);
} while ( $compare = mysql_fetch_array($compareTest));

rsort($var);

echo "<script type='text/javascript' src='http://www.google.com/jsapi'></script>
    <script type='text/javascript'>
      google.load('visualization', '1', {packages: ['corechart']});
    </script>
    <script type='text/javascript'>
      function drawVisualization() {
        // Create and populate the data table.
        var data = google.visualization.arrayToDataTable([
          ['Year', '".$var[0][name]."', '".$var[1][name]."', '".$var[2][name]."'],
          ['',  ".$var[0][raids_25].", ".$var[1][raids_25].", ".$var[2][raids_25]." ]
        ]);
      
        // Create and draw the visualization.
        new google.visualization.BarChart(document.getElementById('raids_25')).
            draw(data,
                 {title:'Топ-3 по количеству посещенных рейдов на 25 игроков',
                  width:400, height:300,
                  vAxis: {title: ''},
                  hAxis: {title: ''}}
            );
      }
      

      google.setOnLoadCallback(drawVisualization);
    </script>";

echo "<div id='raids_25' style='width: 450px; height: 300px;'></div>";
// !-- конец топ-3 по респекам
}

add_shortcode( 'raids_25', 'raids25Top' );

function flightsTop() {
global $wpdb;	

//Вычисляем топ-3 по респекам
$compareTest = mysql_query("SELECT `name`, `flights_sum`, `class` FROM `".$wpdb->prefix."wowguildstats`");
$compare = mysql_fetch_array($compareTest);



$var = array();

do {
	$var[]=array('flights_sum'=>$compare[flights_sum], 'name'=>$compare[name], 'class'=>$compare['class']);
} while ( $compare = mysql_fetch_array($compareTest));

rsort($var);

echo "<script type='text/javascript' src='http://www.google.com/jsapi'></script>
    <script type='text/javascript'>
      google.load('visualization', '1', {packages: ['corechart']});
    </script>
    <script type='text/javascript'>
      function drawVisualization() {
        // Create and populate the data table.
        var data = google.visualization.arrayToDataTable([
          ['Year', '".$var[0][name]."', '".$var[1][name]."', '".$var[2][name]."'],
          ['',  ".$var[0][flights_sum].", ".$var[1][flights_sum].", ".$var[2][flights_sum]." ]
        ]);
      
        // Create and draw the visualization.
        new google.visualization.BarChart(document.getElementById('flights_sum')).
            draw(data,
                 {title:'Топ-3 по количеству совершенных полетов на такси',
                  width:400, height:300,
                  vAxis: {title: ''},
                  hAxis: {title: ''}}
            );
      }
      

      google.setOnLoadCallback(drawVisualization);
    </script>";

echo "<div id='flights_sum' style='width: 450px; height: 300px;'></div>";
// !-- конец топ-3 по респекам
}

add_shortcode( 'flights_sum', 'flightsTop' );

function hearthstoneTop() {
global $wpdb;	

//Вычисляем топ-3 по респекам
$compareTest = mysql_query("SELECT `name`, `hearthstone_sum`, `class` FROM `".$wpdb->prefix."wowguildstats`");
$compare = mysql_fetch_array($compareTest);



$var = array();

do {
	$var[]=array('hearthstone_sum'=>$compare[hearthstone_sum], 'name'=>$compare[name], 'class'=>$compare['class']);
} while ( $compare = mysql_fetch_array($compareTest));

rsort($var);

echo "<script type='text/javascript' src='http://www.google.com/jsapi'></script>
    <script type='text/javascript'>
      google.load('visualization', '1', {packages: ['corechart']});
    </script>
    <script type='text/javascript'>
      function drawVisualization() {
        // Create and populate the data table.
        var data = google.visualization.arrayToDataTable([
          ['Year', '".$var[0][name]."', '".$var[1][name]."', '".$var[2][name]."'],
          ['',  ".$var[0][hearthstone_sum].", ".$var[1][hearthstone_sum].", ".$var[2][hearthstone_sum]." ]
        ]);
      
        // Create and draw the visualization.
        new google.visualization.BarChart(document.getElementById('hearthstone_sum')).
            draw(data,
                 {title:'Топ-3 по количеству совершенных телепортаций в таверну',
                  width:400, height:300,
                  vAxis: {title: ''},
                  hAxis: {title: ''}}
            );
      }
      

      google.setOnLoadCallback(drawVisualization);
    </script>";

echo "<div id='hearthstone_sum' style='width: 450px; height: 300px;'></div>";
// !-- конец топ-3 по респекам
}

add_shortcode( 'hearthstone_sum', 'hearthstoneTop' );

function hugsTop() {
global $wpdb;	

//Вычисляем топ-3 по респекам
$compareTest = mysql_query("SELECT `name`, `hugs_sum`, `class` FROM `".$wpdb->prefix."wowguildstats`");
$compare = mysql_fetch_array($compareTest);



$var = array();

do {
	$var[]=array('hugs_sum'=>$compare[hugs_sum], 'name'=>$compare[name], 'class'=>$compare['class']);
} while ( $compare = mysql_fetch_array($compareTest));

rsort($var);

echo "<script type='text/javascript' src='http://www.google.com/jsapi'></script>
    <script type='text/javascript'>
      google.load('visualization', '1', {packages: ['corechart']});
    </script>
    <script type='text/javascript'>
      function drawVisualization() {
        // Create and populate the data table.
        var data = google.visualization.arrayToDataTable([
          ['Year', '".$var[0][name]."', '".$var[1][name]."', '".$var[2][name]."'],
          ['',  ".$var[0][hugs_sum].", ".$var[1][hugs_sum].", ".$var[2][hugs_sum]." ]
        ]);
      
        // Create and draw the visualization.
        new google.visualization.BarChart(document.getElementById('hugs_sum')).
            draw(data,
                 {title:'Топ-3 по количеству объятий',
                  width:400, height:300,
                  vAxis: {title: ''},
                  hAxis: {title: ''}}
            );
      }
      

      google.setOnLoadCallback(drawVisualization);
    </script>";

echo "<div id='hugs_sum' style='width: 450px; height: 300px;'></div>";
// !-- конец топ-3 по респекам
}

add_shortcode( 'hugs_sum', 'hugsTop' );















//Деактивация плагина, по тому же принципу, что и активация
register_deactivation_hook( __FILE__, 'frey_plugin_base_uninstall' );
function frey_plugin_base_uninstall() {
//do something
	//Здесь можно-нужно удалить установленные при активации опции
}

?>