<?php
/* Please only use this script locally or inside a password protected directory */

$config = [
	'default_regex'  => '/.*?(?:\[(?<group0>[^\]]+)\].*)?v[^\dc]*?(?<volume>\.?\d+(?:\.\d+)*[a-zA-Z]?(?!\d)).*?c[^\dv]*?(?<chapter>\.?\d+(?:\.\d+)*[a-zA-Z]?(?!\d)).*?(?:\[(?<group1>[^\]]+)\].*)?\.(?:zip|cbz)$/i',
	'default_path'   => 'G:/mangadex-uploads/',
	'completed_path' => 'G:/mangadex-uploads/done/',
	'default_group'  => 2,
	'default_lang'   => 1,
	'session_token'  => 'd8e59dbf11cb1b5586f2b29356d5905f'
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
curl_setopt($ch, CURLOPT_URL, 'https://mangadex.org/ajax/actions.ajax.php?function=chapter_upload');
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

function id_name($source, $reverse = false) {
	$names = [];
	$line = strtok($source, "\r\n");
	while($line !== false) {
		if(strpos($line, ':') !== false) {
			preg_match('/(\.?\d+(?:\.\d+)?):(.*)/', $line, $split);
			if($reverse) {
				$names[$split[2]] = $split[1];
			} else {
				$names[$split[1]] = $split[2];
			}
		}
		$line = strtok("\r\n");
	}
	return $names;
}

$titles = id_name($_POST['titles']);
$group_db = id_name(strtolower(file_get_contents('groups.txt')), true);
$manga_db = id_name(strtolower(file_get_contents('manga.txt')), true);

foreach(scandir($_POST['path']) as $zipfile) {
	if(!in_array($zipfile, ['.', '..', '.DS_Store', 'done', 'groups.txt', 'manga.txt', 'index.php', 'mangadex-upload.php'])) {
		$skip = false;
		$allprogress = -1;
		$matches = [];
		preg_match($_POST['regex'], $zipfile.$zipfile, $matches);

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

		if(isset($result) && strpos($result, 'Failed') !== false) {
			echo 'Skipping. ' . $result;
			$skip = true;
		}

		if(isset($result) && strpos($result, 'cf.errors.css') !== false) {
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
