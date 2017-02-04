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
				elseif ($item == 'LastTime' || $item == 'LastUpdate')
					$this->data['GENERAL'][$item][0] = max($row[0], $this->data['GENERAL'][$item][0]);
				elseif ($item == 'TotalVisits')
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
	 * Merges sections that have dates in indexes 3 and 4 (if present)
	 * @param $rows existing set of rows
	 * @param $section_name identifier of the current section
	 */
	private function helper_sum_with_dates(&$rows, &$section_name)
	{
		foreach ($rows as $key => $row)
		{
			if (!isset($this->data[$section_name][$key]))
			{
				$this->data[$section_name][$key] = $row;
				continue;
			}

			// Merge rows, taking into account that not all entries have the same number of data items
			// Indexes 3 and 4 are dates so take max instead of sum
			// For indexes above 4 take whichever one comes first
			foreach ($row as $num => $stats)
			{
				if ($num <= 2)
					$this->data[$section_name][$key][$num] = isset($this->data[$section_name][$key][$num]) ? $this->data[$section_name][$key][$num] + $stats : $stats;
				elseif ($num <= 4)
					$this->data[$section_name][$key][$num] = isset($this->data[$section_name][$key][$num]) ? max($this->data[$section_name][$key][$num], $stats) : $stats;
				elseif (!isset($this->data[$section_name][$key][$num]))
					$this->data[$section_name][$key][$num] = $stats;
			}
		}
	}

	/**
	 * Merges visitor statistics.
	 * @param $rows existing set of rows
	 * @param $section_name identifier of the current section
	 */
	private function merge_visitor(&$rows, &$section_name)
	{
		// Note that index 3 is a start date and the max will be taken when it
		// should technically be min but it doesn't matter because the start date is never used.
		return $this->helper_sum_with_dates($rows, $section_name);
	}

	/**
	 * Merges extra_1 statistics.
	 * @param $rows existing set of rows
	 * @param $section_name identifier of the current section
	 */
	private function merge_extra_1(&$rows, &$section_name)
	{
		return $this->helper_sum_with_dates($rows, $section_name);
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
				$this->data[$section_name][$key] = $row;
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
