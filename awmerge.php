<?php
/*
Awstats Data File Parser / Merger / Generator
@version: 1.4.1
@date: 2020-01-20
@author: Aaron van Geffen
@website: https://aaronweb.net/
@license: BSD
*/

// Not running PHP-CLI?
if (isset($_SERVER['HTTP_HOST']) || !isset($_SERVER['argv'], $_SERVER['argc']))
	die("This script is intended for use with PHP-CLI only.\n");

// Klingon functions have arguments! (Note: argument 0 is the script's file name.)
if ($_SERVER['argc'] <= 1)
	die("Not enough arguments. Expecting every argument to be an awstats file to parse. Pipe to save the script's output.\nExample usage: awparse.php awstats062010.txt awstats072010.txt > awstats06072010.txt\n");

// Register a simple autoloader for example purposes...
spl_autoload_register(function($className) {
	require dirname(__FILE__) . '/src/AaronVanGeffen/AwstatsParser/' . $className . '.php';
});

// Instantiate a new merger class.
$stats = new AwstatsMerger();

// Assuming all arguments are files, try to add them.
for ($i = 1; $i < count($_SERVER['argv']); $i++)
{
	if (!file_exists($_SERVER['argv'][$i]))
		die('File not found: ' . $_SERVER['argv'][$i]);

	$stats->add(new AwstatsFromFile($_SERVER['argv'][$i]));
}

// Print merged statistics file.
echo $stats->getFileContents();
