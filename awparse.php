<?php
/*
	AWSTATS DATA FILE PARSER / MERGER / GENERATOR ==============================
	@version: 1.1
	@date: 2012-08-29
	@author: Aaron van Geffen
	@website: http://aaronweb.net/
	@license: BSD

	VERSION HISTORY ============================================================
	version 1.1 (2012-08-29)
	* Removed unnecessary call-time pass-by-references;
	* Added newlines to error messages;

	version 1.0 (2010-06-19)
	* Initial release.

	TODO =======================================================================
	* Import mode: only add unique missing data, or merge all data
	* Prefixes and suffixes for URLs on import, i.e. adding /projects as a prefix

	PATCHES / PULL REQUESTS ====================================================
	Are most welcome through Github. The repository is located at:
	https://github.com/AaronVanGeffen/AwstatsParser
*/

// Not running PHP-CLI?
if (isset($_SERVER['HTTP_HOST']) || !isset($_SERVER['argv'], $_SERVER['argc']))
	die("This script is intended for use with PHP-CLI only.\n");

// Klingon functions have arguments! (Note: argument 0 is the script's file name.)
if ($_SERVER['argc'] <= 1)
	die("Not enough arguments. Expecting every argument to be an awstats file to include. Pipe to save the script\'s output. Example usage: awparse.php awstats062010.txt awstats072010.txt > awstats06072010.txt\n");

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

/**
 * Abstract class that allows generalization of Awstats files.
 */
abstract class AwstatsFile
{
	public $data = array();

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

	public function writeFileContents($filename)
	{
		file_put_contents($filename, $this->getFileContents());
	}
}

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

/**
 * Class that takes care of merging multiple instances of AwstatsFile into one AwstatsFile.
 */
class AwstatsMerger extends AwstatsFile
{
	/**
	 * Adds (merges) an existing AwstatsFile instance to this instance.
	 * @param $file AwstatsFile instance
	 */
	public function add(AwstatsFile $file)
	{
		foreach ($file->data as $section_name => $rows)
		{
			// Is this the first time this type of statistic is passed?
			if (empty($this->data[$section_name]))
			{
				$this->data[$section_name] = $rows;
				continue;
			}
			// The hard way: merge stats.
			else
			{
				// Does this section have its own merge function?
				$merge_func = 'merge_' . strtolower($section_name);
				if (method_exists($this, $merge_func))
					$this->$merge_func($rows, $section_name);
				// Use the generic merge function for basic addition.
				else
					$this->helper_sum_merge($rows, $section_name);

				// Prevent unnecessary sorting.
				if ($section_name == 'GENERAL')
					continue;

				// Few sections require us to sort ascending by their keys...
				if (in_array($section_name, array('TIME', 'DAY')))
				{
					ksort($this->data[$section_name]);
					continue;
				}

				// Other sections require us to order descending by their first value.
				uasort($this->data[$section_name], 'AwstatsMerger::helper_sort_desc');
			}
		}
	}

	/**
	 * Merges general statistics.
	 * TODO: improve this? (How? Suggestions are welcome.)
	 * @param $rows existing set of rows
	 * @param $section_name identifier of the current section
	 */
	private function merge_general(&$rows, &$section_name)
	{
		foreach ($rows as $item => $row)
		{
			if (!isset($this->data['GENERAL'][$item]))
			{
				$this->data['GENERAL'][$item] = $row;
				continue;
			}
			else
			{
				if ($item == 'FirstTime')
					$this->data['GENERAL'][$item][0] = min($row[0], $this->data['GENERAL'][$item][0]);
				else if (($item == 'LastTime') || ($item == 'LastUpdate'))
					$this->data['GENERAL'][$item][0] = max($row[0], $this->data['GENERAL'][$item][0]);
				else if ($item == 'TotalVisits')
					$this->data['GENERAL'][$item][0] += $row[0];
			}
		}
	}

	/**
	 * Merges login statistics.
	 * @param $rows existing set of rows
	 * @param $section_name identifier of the current section
	 */
	private function merge_login(&$rows, &$section_name)
	{
		foreach ($rows as $login => $row)
		{
			if (!isset($this->data['LOGIN'][$login]))
			{
				$this->data['LOGIN'][$login] = $row;
				continue;
			}

			$this->data['LOGIN'][$login][0] += $row[0];
			$this->data['LOGIN'][$login][1] += $row[1];
			$this->data['LOGIN'][$login][2] += $row[2];
			$this->data['LOGIN'][$login][3] = max($this->data['LOGIN'][$login][3], $row[3]);
		}
	}

	/**
	 * Merges robot statistics.
	 * @param $rows existing set of rows
	 * @param $section_name identifier of the current section
	 */
	private function merge_robot(&$rows, &$section_name)
	{
		foreach ($rows as $robot => $row)
		{
			if (!isset($this->data['ROBOT'][$robot]))
			{
				$this->data['ROBOT'][$robot] = $row;
				continue;
			}

			$this->data['ROBOT'][$robot][0] += $row[0];
			$this->data['ROBOT'][$robot][1] += $row[1];
			$this->data['ROBOT'][$robot][2] = max($this->data['ROBOT'][$robot][2], $row[2]);
			$this->data['ROBOT'][$robot][3] += $row[3];
		}
	}

	/**
	 * Merges worm statistics.
	 * Note: handled the same way as emailsender and emailreceiver.
	 * @param $rows existing set of rows
	 * @param $section_name identifier of the current section
	 */
	private function merge_worm(&$rows, &$section_name)
	{
		foreach ($rows as $worm => $row)
		{
			if (!isset($this->data[$section_name][$worm]))
			{
				$this->data[$section_name][$worm] = $row;
				continue;
			}

			$this->data[$section_name][$worm][0] += $row[0];
			$this->data[$section_name][$worm][1] += $row[1];
			$this->data[$section_name][$worm][2] = max($this->data[$section_name][$worm][2], $row[2]);
		}
	}

	/**
	 * Merges email sender statistics.
	 * Note: handled the same way as emailreceiver and worm.
	 * @param $rows existing set of rows
	 * @param $section_name identifier of the current section
	 */
	private function merge_emailsender(&$rows, &$section_name)
	{
		return $this->merge_worm($rows, $section_name);
	}

	/**
	 * Merges email receiver statistics.
	 * Note: handled the same way as emailsender and worm.
	 * @param $rows existing set of rows
	 * @param $section_name identifier of the current section
	 */
	private function merge_emailreceiver(&$rows, &$section_name)
	{
		return $this->merge_worm($rows, $section_name);
	}

	/**
	 * Merges unknown referer statistics.
	 * Note: handled the same way as unknownrefererbrowser.
	 * @param $rows existing set of rows
	 * @param $section_name identifier of the current section
	 */
	private function merge_unknownreferer(&$rows, &$section_name)
	{
		foreach ($rows as $referer => $row)
		{
			if (!isset($this->data[$section_name][$referer]))
			{
				$this->data[$section_name][$referer] = $row;
				continue;
			}

			$this->data[$section_name][$referer][0] = max($this->data[$section_name][$referer][0], $row[0]);
		}
	}

	/**
	 * Merges unknown referer browser statistics.
	 * Note: handled the same way as unknownreferer.
	 * @param $rows existing set of rows
	 * @param $section_name identifier of the current section
	 */
	private function merge_unknownrefererbrowser(&$rows, &$section_name)
	{
		return $this->merge_unknownreferer($rows, $section_name);
	}

	/**
	 * Merges visitor statistics.
	 * @param $rows existing set of rows
	 * @param $section_name identifier of the current section
	 */
	private function merge_visitor(&$rows, &$section_name)
	{
		foreach ($rows as $key => $row)
		{
			if (!isset($this->data[$section_name][$key]))
			{
				$this->data[$key][$key] = $row;
				continue;
			}

			// Merge rows, taking in account that not all vistors have a start and end date of their visit set.
			foreach ($row as $num => $stats)
				$this->data[$section_name][$key][$num] = isset($this->data[$section_name][$key][$num]) ? $this->data[$section_name][$key][$num] + $stats : $stats;
		}
	}

	/**
	 * Merges statistics by simply taking the sum of existing rows.
	 * @param $rows existing set of rows
	 * @param $section_name identifier of the current section
	 */
	private function helper_sum_merge(&$rows, &$section_name)
	{
		foreach ($rows as $key => $row)
		{
			if (!isset($this->data[$section_name][$key]))
			{
				$this->data[$key][$key] = $row;
				continue;
			}

			foreach ($row as $num => $stats)
				$this->data[$section_name][$key][$num] += $stats;
		}
	}

	/**
	 * Helps sorting stats descending.
	 * @param $a first item to compare against
	 * @param $b section item to compare against
	 */
	public static function helper_sort_desc($a, $b)
	{
		if ($a[0] == $b[0])
			return 0;
		else
			return $a[0] > $b[0] ? -1 : 1; // sorts descending
	}

}

?>
