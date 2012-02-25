<?php
set_time_limit(0);

$cfg['bookdir']  = "wdqk";
$cfg['cache']    = $cfg['bookdir'] . "/cache";
$cfg['asset']    = $cfg['bookdir'] . "/asset";
$cfg['log']      = true;
$cfg['file_log'] = true;
$cfg['timeout']  = 15;
$cfg['body']     = "";
$cfg['charset']  = "gbk"; // utf8
$cfg['cid']      = 0;

#
# bookdir 必须包含一个 config.inc.php
# 此配置要定义图书的首页地址
#
#
include("./" . $cfg['bookdir'] . "/config.inc.php");

function spider_log($info) {
	global $cfg;
	
	if($cfg['log'])
		echo "[*] " . $info . "\n";
}

function spider_die($info) {
	echo "[-] " . $info . "\n";
	exit(0);
}

function append_book_info($name, $author, $category) {
	global $cfg;
	
	$cfg['body'] = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
<book>
	<info>
		<name>$name</name>
		<author>$author</author>
		<category>$category</category>
	</info>";
}

function append_book_chapter_start($title) {
	global $cfg;
	$cfg['cid']++;
	$cid = $cfg['cid'];
	
	$cfg['body'] = $cfg['body'] . "<chapter id=\"$cid\" name=\"$title\">";
}

function append_book_chapter_text_part($body) {
	global $cfg;
	
	$cfg['body'] = $cfg['body'] . "<part type=\"text\">";
	$cfg['body'] = $cfg['body'] . $body;
	$cfg['body'] = $cfg['body'] . "</part>";
}

function append_book_chapter_image_part($url) {
	global $cfg;
	
	$cfg['body'] = $cfg['body'] . "<part type=\"image\">";
	$cfg['body'] = $cfg['body'] . $url;
	$cfg['body'] = $cfg['body'] . "</part>";
}

function append_book_chapter_end() {
	global $cfg;
	
	$cfg['body'] = $cfg['body'] . "</chapter>";
}

function finish_book() {
	global $cfg;
	
	$cfg['body'] = $cfg['body'] . "\n</book>";
}

function get_url_content($url) {
	global $cfg;
	
	// return file_get_contents($cfg['bookdir'] . "/index.html");

	// $ch = curl_init();
	// curl_setopt($ch, CURLOPT_URL, $url);
	// curl_setopt($ch, CURLOPT_HEADER, 1);
	// curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	// curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $cfg['timeout']); 
	// $ret = curl_exec($ch);
	// curl_close($ch);

	// return $ret;
	
	return file_get_contents($url);
}

function complete_url($url) {
	global $book_cfg;
	
	$base_url = $book_cfg['url'];
	$spos = strrpos($base_url, "/");
	$new_url = substr($base_url, 0, $spos + 1) . $url;
	return $new_url;
}

function check_config() {
	global $book_cfg;
	
	if(is_null($book_cfg['url']))
		spider_die("没有设置图书的网页地址");
	
	@mkdir($cfg['cache']);
	@mkdir($cfg['asset']);
}

function save_image($url) {
	global $cfg;
	
	$content = file_get_contents($url);
	if($content == false)
		return false;
	
	$p = strrpos($url, ".");
	$ext = substr($url, $p);
	$basename = md5($url) . $ext;
	$filename = $cfg['asset'] . "/" . $basename;
	file_put_contents($filename, $content);
	return $basename;
}

function parse_chapter_body($title, $data) {
	global $cfg;
	global $book_cfg;
	
	$body_start = $book_cfg['chapter']['body']['start'];
	$body_end   = $book_cfg['chapter']['body']['end'];
	
	$body = strstr($data, $body_start);
	$body = substr($body, strlen($body_start));
	
	$epos = strpos($body, $body_end);
	if($epos != false)
		$body = substr($body, 0, $epos);
	else
		echo "[-] not found body end!\n";
	
	append_book_chapter_start($title);
	
	if(strstr($body, ".gif") != false) {
		$img_start = $book_cfg['chapter']['image']['start'];
		$img_end = $book_cfg['chapter']['image']['end'];
		
		$ret = strstr($body, $img_start);
		while($ret != false) {
			$ret = substr($ret, strlen($img_start));
			$p = strpos($ret, $img_end);
			$img = substr($ret, 0, $p);
			
			$basename = save_image($img);
			if($basename != false)
				append_book_chapter_image_part($basename);
			
			$ret = substr($ret, $p);
			$ret = strstr($ret, $img_start);
		}
	} else {
		$body = str_replace("&nbsp;", " ", $body);
		$body = str_replace("<br />", "\r\n", $body);
		append_book_chapter_text_part($body);
	}

	append_book_chapter_end();
}

function get_chapter_body($title, $url) {
	global $cfg;
	
	$filename = $cfg['cache'] . "/" . $url;
	$chapter_url = complete_url($url);
	$body = "";
	
	if(file_exists($filename)) {
		$body = file_get_contents($filename);
	} else {
		$body = get_url_content($chapter_url);
	
		if($cfg['file_log'] && $body) {
			file_put_contents($filename, $body);
		}
	}
	
	if($body && strlen($body) > 0) {
		parse_chapter_body($title, $body);
		echo "[+] " . $url . " done!\n";
	} else {
		echo "[-] " . $url . "\n";
	}
}

function parse_chapter($content) {
	global $book_cfg;
	
	$url_start = $book_cfg['index']['chapter']['url_start'];
	$url_end   = $book_cfg['index']['chapter']['url_end'];
	
	$title_start = $book_cfg['index']['chapter']['title_start'];
	$title_end   = $book_cfg['index']['chapter']['title_end'];
	
	$ret = strstr($content, $url_start);
	while($ret != false) {
		$ret = substr($ret, strlen($url_start));
		$p = strpos($ret, $url_end);
		$url = substr($ret, 0, $p);
		
		$title = strstr($ret, $title_start);
		if($title != false) {
			$title = substr($title, strlen($title_start));
			
			$p = strpos($title, $title_end);
			$title = substr($title, 0, $p);
		} else {
			$title = "No Title";
		}
		
		get_chapter_body($title, $url);
		# spider_log("find chapter:" . $title . "[" . $url . "]");
		$ret = strstr($ret, $url_start);
	}
}

function parse_book_info($content) {
	$name_prefix = "var articlename='";
	$author_prefix = "var author='";
	$category_prefix = "var sortname='";
	
	$name = strstr($content, $name_prefix);
	$name = substr($name, strlen($name_prefix));
	$name = substr($name, 0, strpos($name, "'"));
	
	$author = strstr($content, $author_prefix);
	$author = substr($author, strlen($author_prefix));
	$author = substr($author, 0, strpos($author, "'"));
	
	$category = strstr($content, $category_prefix);
	$category = substr($category, strlen($category_prefix));
	$category = substr($category, 0, strpos($category, "'"));
	
	append_book_info($name, $author, $category);
}

function main($argc, $argv) {
	global $book_cfg;
	global $cfg;
	
	if($argc > 2) {
		if(strcmp($argv[1], "test") == 0) {
			get_chapter_body("test", $argv[2]);
			echo $cfg['body'];
			return;
		}
	}
	
	spider_log("检查图书配置！");
	check_config();
	
	spider_log("请求大纲！");
	$index_filename = $cfg['cache'] . "/index.html";
	if(!file_exists($index_filename)) {
		$index_html = get_url_content($book_cfg['url']);
		if($index_html == false) {
			spider_die("不能请求网页地址：" . $book_cfg['url']);
		}
		
		if($cfg['file_log']) {
			file_put_contents($index_filename , $index_html);
		}
	} else {
		$index_html = file_get_contents($index_filename);
	}
	
	parse_book_info($index_html);
	parse_chapter($index_html);
	finish_book();
	
	$book_filename = $cfg['bookdir'] . "/book.xml";
	$content = $cfg['body'];
	if(strcmp($cfg['charset'], "gbk") == 0) {
		$content = iconv("GBK", "UTF-8//IGNORE", $cfg['body']);
	}
	file_put_contents($book_filename, $content);
	
	spider_log("Done！");
}

function test() {
	global $cfg;
	
	append_book_info("aaaa", "tomken", "gongfu");
	append_book_chapter("xxxxx");
	finish_book();
	
	echo $cfg['body'];
}

main($argc, $argv);
# test();

?>