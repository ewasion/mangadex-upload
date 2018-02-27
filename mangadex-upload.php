<?php
/* Please only use this script locally or inside a password protected directory */

$config = [
	'default_regex'  => '/.*?(?:\[(?<group0>.+)\].*)?v[^\d]*?(?<volume>\.?\d+(?:\.\d+)*[a-zA-Z]?).*?c[^\d]*?(?<chapter>\.?\d+(?:\.\d+)*[a-zA-Z]?).*?(?:\[(?<group1>.+)\].*)?\.(?:zip|cbz)$/i',
	'default_path'   => 'G:/mangadex-uploads/',
	'completed_path' => 'G:/mangadex-uploads/done/',
	'default_group'  => 2,
	'default_lang'   => 1,
	'session_token'  => 'd8e59dbf11cb1b5586f2b29356d5905f'
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

$manga_db = [
	'naruto' => 5,
	'detective conan' => 153,
	'nichijou' => 188,
	'xblade' => 69
];

/* Make sure directories have a trailing slash */
$config['default_path'] = rtrim($config['default_path'], '/') . '/';
$config['completed_path'] = rtrim($config['completed_path'], '/') . '/';

if(!isset($_POST['regex'])) {?>
<form action="" method="post">
  <input required type="text" name="regex" id="regex" value="<?= $config['default_regex'] ?>"> <label for="regex">Regex</label><br>
  <input required type="text" name="path" id="path" value="<?= $config['default_path'] ?>"> <label for="path">Path</label><br>
  <input required type="text" name="completed_path" id="completed_path" value="<?= $config['completed_path'] ?>"> <label for="completed_path">Path for completed uploads</label><br>
  <input required type="number" name="group" id="group" value="<?= $config['default_group'] ?>"> <label for="group">Fallback group ID</label><br>
  <input required type="number" name="manga" id="manga"> <label for="manga">Fallback manga ID</label><br>
  <input required type="number" name="lang" id="lang" value="<?= $config['default_lang'] ?>"> <label for="lang">Language ID</label><br><br>
  <label for="titles">Chapter titles (number:title)<br>Leave blank to ignore<br></label>
  <textarea name="titles" id="titles"></textarea><br>
  <input type="submit" value="Start uploading">
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
curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 'progress_bar');
curl_setopt($ch, CURLOPT_NOPROGRESS, false);

function progress_bar($resource, $download_size = 0, $downloaded = 0, $upload_size = 0, $uploaded = 0) {
	global $allprogress;
	if($uploaded != 0) {
		$progress = round(($uploaded / $upload_size) * 100);
		if($progress == 0 && $allprogress != $progress) {
			$allprogress = $progress;
			echo '(';
		}
		if($progress != 0 && $progress % 10 == 0 && $allprogress != $progress) {
			$allprogress = $progress;
			echo $progress.'%';
			if($progress == 100) {
				echo ')';
			}
			flush();
		}
	}
}

$line = strtok($_POST['titles'], "\r\n");
$titles = [];

while($line !== false) {
	if(strpos($line, ':') !== false) {
		preg_match('/(\.?\d+(?:\.\d+)?):(.*)/', $line, $split);
		$titles[$split[1]] = $split[2];
	}
	$line = strtok("\r\n");
}

foreach(scandir($_POST['path']) as $zipfile) {
	if(!in_array($zipfile, ['.', '..', '.DS_Store', 'done'])) {
		$skip = false;
		$allprogress = -1;
		$matches = [];
		preg_match($_POST['regex'].$_POST['regex'], $zipfile, $matches);

		$volume = !empty($matches['volume']) ? $matches['volume'] : false;
		$chapter = !empty($matches['chapter']) ? $matches['chapter'] : false;
		$group = $_POST['group'];
		$manga = $_POST['manga'];

		if(!empty($matches['group0']) || !empty($matches['group1'])) {
			$groupname = !empty($matches['group1']) ? $matches['group1'] : $matches['group0'];
			$group = isset($group_db[strtolower($groupname)]) ? $group_db[strtolower($groupname)] : $_POST['group'];
		}
		
		foreach($manga_db as $mango => $id) {
			if(strpos(strtolower($zipfile), $mango) !== false) {
				$manga = $id;
			}
		}

		/*
		Remove leading 0s
		*/
		$volume = ($volume !== false) ? ltrim($volume, '0') : false;
		$chapter = ($chapter !== false) ? ltrim($chapter, '0') : false;

		/*
		Multi part chapters
		*/
		preg_match('/(.*)([a-zA-Z])$/i', $chapter, $chaptermatches);
		if(isset($chaptermatches[2])) {
			$chapter = str_replace($chaptermatches[2], '.' . (ord(strtoupper($chaptermatches[2])) - ord('A') + 1), $chapter);
		}

		/*
		Chapter titles
		*/
		$title = (isset($titles[$chapter]) ? $titles[$chapter] : '');

		/*
		For volume/chapter 0
		*/
		$volume = ($volume === '' ? 0 : $volume);
		$chapter = ($chapter === '' ? 0 : $chapter);

		$post = [
			'manga_id' => $manga,
			'chapter_name' => $title,
			'volume_number' => $volume,
			'chapter_number' => $chapter,
			'group_id' => $group,
			'lang_id' => $_POST['lang'],
			'file' => curl_file_create($_POST['path'] . $zipfile)
		];
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

		echo '[' . $group . '] Vol.' . $volume . ' Ch.' . $chapter . ' (' . $zipfile . ') ';
		flush();

		if($chapter !== false) {
			$result = curl_exec($ch);
		} else {
			echo 'Skipping. Regex doesn\'t match';
			$skip = true;
		}

		if(curl_errno($ch)){
			echo 'Skipping. Error: ' . curl_error($ch);
			$skip = true;
		}

		if(strpos($result, 'Failed') !== false) {
			echo 'Skipping. ' . $result;
			$skip = true;
		}

		if(strpos($result, 'cf.errors.css') !== false) {
			preg_match('/<title>.* \| (.*)<\/title>/', $result, $cfmatch);
			echo 'Skipping. Cloudflare error ' . $cfmatch[1];
			$skip = true;
		}

		if(!$skip) {
			rename($_POST['path'] . $zipfile, $_POST['completed_path'] . $zipfile);
			echo ' Done.';
		}
		echo '<br>';
		flush();
	}
}

curl_close($ch);
echo 'Done uploading.';
