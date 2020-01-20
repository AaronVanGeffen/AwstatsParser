<?php
/*
Awstats Data File Parser / Merger / Generator
@version: 1.4.1
@date: 2020-01-20
@author: Aaron van Geffen
@website: https://aaronweb.net/
@license: BSD
*/

/**
 * Abstract class that allows generalization of Awstats files.
 */
abstract class AwstatsFile
{
	protected $data = [];

	public function getFileContents()
	{
		$contents = "AWSTATS DATA FILE 6.9 (build 1.925)\n\n";
		foreach ($this->data as $section_name => $rows)
		{
			$contents .= 'BEGIN_' . $section_name . ' ' . count($rows) . "\n";
			foreach ($rows as $key => $row)
				$contents .= $key . ' ' . implode(' ', $row) . "\n";
			$contents .= 'END_' . $section_name . "\n\n";
		}

		return $contents;
	}

	public function getSection($section)
	{
		$section = strtoupper($section);
		return isset($this->data[$section]) ? $this->data[$section] : [];
	}

	public function writeFileContents($filename)
	{
		file_put_contents($filename, $this->getFileContents());
	}
}
