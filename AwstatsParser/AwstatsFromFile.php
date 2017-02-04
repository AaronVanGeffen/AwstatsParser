<?php
/*
Awstats Data File Parser / Merger / Generator
@version: 1.3
@date: 2017-02-04
@author: Aaron van Geffen
@website: https://aaronweb.net/
@license: BSD
*/

/**
 * Class that interprets an existing Awstats statistics file into a generic AwstatsFile instance.
 */
class AwstatsFromFile extends AwstatsFile
{
	/**
	 * Opens the designated file and parses all sections in it.
	 */
	public function __construct($filename)
	{
		if (!$fp = fopen($filename, 'r'))
			die('File not found: ' . $filename);

		while ($section_name = $this->read_to_section($fp))
		{
			// Skip the MAP section; have the Awstats script regenerate it later.
			if ($section_name == 'MAP')
				continue;

			$this->data[$section_name] = $this->parse_section($fp);
		}
	}

	/**
	 * Outputs the data in $data to the output buffer.
	 */
	public function print_data()
	{
		print_r($this->data);
	}

	/**
	 * Reads to the next section, ignoring everything in its way.
	 */
	private function read_to_section(&$fp)
	{
		do
			$str = trim(fgets($fp));
		while (!feof($fp) && strpos($str, "BEGIN_") === false);

		return !feof($fp) ? substr($str, 6, strrpos($str, ' ') - 6) : false;
	}

	/**
	 * Parses a section until its end.
	 */
	private function parse_section(&$fp)
	{
		$stats = array();

		do
		{
			if (feof($fp))
				break;

			$str = trim(fgets($fp));

			if (strpos($str, 'END_') !== false)
				break;

			if ($str{0} == "#")
				continue;

			$row = explode(' ', $str);
			$key = array_shift($row);
			$stats[$key] = $row;
		}
		while (!feof($fp) && strpos($str, 'END_') === false);

		return $stats;
	}
}
