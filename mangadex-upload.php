<?php
/* Please only use this script locally or inside a password protected directory */

$config = [
	'default_regex'  => '/.*v[^\d]*?(\.?\d+(?:\.\d+)*[a-zA-Z]?).*?c[^\d]*?(\.?\d+(?:\.\d+)*[a-zA-Z]?).*?(?:\[(.+)\])?\.(?:zip|cbz)$/i',
	'default_path'   => 'G:/mangadex-uploads/',            /* Asbolute path */
	'completed_path' => 'G:/mangadex-uploads/done/',       /* Asbolute path */
	'default_group'  => '2',                               /* Unknown */
	'default_lang'   => '1',                               /* English */
	'session_token'  => 'd8e59dbf11cb1b5586f2b29356d5905f' /* Found in the "mangadex" cookie */
];

$group_db = [
	'iem' => 1334,
	'fh' => 621,
	'inp mangaz' => 128,
	'kefi' => 1196,
	'w,tf' => 1335,
	'bushido' => 645,
	'binktopia' => 577,
	'inane' => 937,
	'project_88' => 1370,
	'wek' => 1371
];

/* Make sure directories have a trailing slash */
$config['default_path'] = rtrim($config['default_path'], '/') . '/';
$config['completed_path'] = rtrim($config['completed_path'], '/') . '/';

if(!isset($_POST['regex'])) {?>
<form action="" method="post">
  <input required type="text" name="regex" id="regex" value="<?= $config['default_regex'] ?>"> <label for="regex">Regex</label><br>
  <input required type="text" name="path" id="path" value="<?= $config['default_path'] ?>"> <label for="path">Path</label><br>
  <input required type="text" name="completed_path" id="completed_path" value="<?= $config['completed_path'] ?>"> <label for="completed_path">Path for completed uploads</label><br>
  <input required type="number" name="group" id="group" value="<?= $config['default_group'] ?>"> <label for="group">Group ID</label><br>
  <input required type="number" name="manga" id="manga"> <label for="manga">Manga ID</label><br>
  <input required type="number" name="lang" id="lang" value="<?= $config['default_lang'] ?>"> <label for="lang">Language ID</label><br>
  <input type="submit" value="Stat uploading">
</form><?php
	exit;
}

@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
@ob_end_clean();
set_time_limit(0);

echo 'Uploading...<br>';
flush();

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://mangadex.com/ajax/actions.ajax.php?function=chapter_upload');
curl_setopt($ch, CURLOPT_COOKIE, 'mangadex=' . $config['session_token']);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); /* For dumbos with bad ssl */
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); /* Remove this if you know you won't need it */
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Requested-With: XMLHttpRequest']);

foreach(scandir($_POST['path']) as $zipfile) {
	if(!in_array($zipfile, ['.', '..', '.DS_Store', 'done'])) {
		$matches = array();
		preg_match_all($_POST['regex'], $zipfile, $matches);

		$volume = $matches[1][0];
		$chapter = $matches[2][0];
		$group = $_POST['group'];

		if(!empty($matches[3][0])) {
			$group = isset($group_db[strtolower($matches[3][0])]) ? $group_db[strtolower($matches[3][0])] : $_POST['group'];
		}

		/*
		Remove leading 0s
		*/
		$volume = ltrim($volume, '0');
		$chapter = ltrim($chapter, '0');

		/*
		Multi part chapters
		*/
		$chapter = str_replace('a', '.1', $chapter);
		$chapter = str_replace('b', '.2', $chapter);
		$chapter = str_replace('c', '.3', $chapter);
		$chapter = str_replace('d', '.4', $chapter);
		$chapter = str_replace('e', '.5', $chapter);

		$post = [
			'manga_id' => $_POST['manga'],
			'chapter_name' => '',
			'volume_number' => $volume,
			'chapter_number' => $chapter,
			'group_id' => $group,
			'lang_id' => $_POST['lang'],
			'file' => curl_file_create($_POST['path'] . $zipfile)
		];
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		$result = curl_exec($ch);

		if (strpos($result, 'Failed') !== false) {
			echo $result;
			exit;
		}

		rename($_POST['path'] . $zipfile, $_POST['completed_path'] . $zipfile);
		echo 'Uploaded [' . $group . '] Vol.' . $volume . ' Ch.' . $chapter . ' (' . $zipfile . ')<br>';
		flush();
	}
}

curl_close($ch);
echo 'Done uploading.';
