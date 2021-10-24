<?php

/**
 * TODO: Plugin-Header 
 */ 

// Voreinstellungen
$site_domain = 'http://moench.net';
$obsidian_dir = 'obsidian/';


// --- Ab hier keine Änderungen ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(-1);

require '../wp-config.php';
require './Parsedown.php'; // https://github.com/erusev/parsedown
require './frontmatter.php'; // https://github.com/Modularr/YAML-FrontMatter

$parser = new Parsedown();

$db = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD) or die(mysql_error());
mysql_select_db(DB_NAME, $db) or die(mysql_error());

$mdfiles = scandir($obsidian_dir);
array_shift($mdfiles); // .
array_shift($mdfiles); // ..

foreach($mdfiles as $mf) {
	import_markdown($mf);
}


//-----------------------------------------------------------------------------
// Funktionen 
//-----------------------------------------------------------------------------

function import_markdown($mf) {
	global $db, $parser, $;

	$mdFile = __DIR__ . '/' . $obsidian_dir . $mf;
	$md = file_get_contents($mdFile);
	$post = new FrontMatter($md);

	$title = trim($post->fetch('title'), "'\" ");
	$date = $post->fetch('date');
	$date = date('Y-m-d H:i:s', strtotime($date));
	$body = $post->fetch('content');
	$tags = explode(',', str_replace(array("'", '[', ']'), '', $post->fetch('tags')));

	// Dateiname ist der Slug 
	$slug = substr($mf, 11);
	$slug = preg_replace('/-+/', '-', substr($slug, 0, strpos($slug, '.')));
 
	// Permalink
	$permalink = $site_domain . '/????? /' . $slug . "\n";

	// 'READMORE' mit WordPress Entsprechung ersetzen 
	$body = str_replace('READMORE', '<!--more-->', $body);

	$title = mysql_escape_string($title);
	$body_md = mysql_escape_string($body);
	$body_html = mysql_escape_string(str_replace(array("\r\n", "\r", "\n"), " ", $parsedown->text($body)));

	echo 'Permalink: ' . $permalink . " (Tags: " . implode($tags, ',') . ")<br />\n";

	$sql = "INSERT INTO wp_posts (post_author, post_date, post_date_gmt, post_content, post_content_filtered, post_title, post_status, comment_status, ping_status, post_name, post_modified, post_modified_gmt, post_parent, post_type) VALUES ";
	$sql .= "(1, '$date', '$date', '$body_html', '$body_md', '$title', 'publish', 'closed', 'open', '$slug', '$date', '$date', 0, 'post')";
	mysql_query($sql, $db);
	$id = mysql_insert_id($db);
	wp_set_post_tags($id, $tags, false);
	mysql_query("UPDATE wp_posts SET guid = '$site_domain/?p=$id' WHERE ID = $id", $db);
	mysql_query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES ($id, '_sd_is_markdown', '1')", $db);
}
