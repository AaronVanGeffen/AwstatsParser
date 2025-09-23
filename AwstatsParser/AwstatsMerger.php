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
 * Class that takes care of merging multiple instances of AwstatsFile into one file.
 */
class AwstatsMerger
{
	private array $files = [];
	private array $sections = [];

	/**
	 * Adds (merges) an existing AwstatsFile instance to this instance.
	 * @param $file AwstatsFile instance
	 */
	public function add(AwstatsFile $file)
	{
		$this->files[] = $file;

		$this->sections = array_unique(array_merge($this->sections, $file->getSections()));
	}

	/**
	 * Writes the current file to the output buffer.
	 * Legacy function interface; offered for compatibility/transition purposes.
	 *
	 * @deprecated Use writeFileContents directly
	 */
	public function getFileContents()
	{
		return $this->writeFileContents('php://output');
	}

	public function getSection(string $section)
	{
		if (!$this->hasSection($section))
			throw new DomainException('Unknown section: ' .  $section);

		// Does this section have its own merge function?
		$merge_func = 'merge_' . strtolower($section);
		if (!method_exists($this, $merge_func))
			$merge_func = 'helper_sum_merge';

		// Merge data for this section from all files.
		$rows = [];
		foreach ($this->files as $file)
		{
			if (!$file->hasSection($section))
				continue;

			$fileRows = $file->getSection($section);
			$rows = $this->$merge_func($rows, $fileRows);
			unset($fileRows);
		}

		// Prevent unnecessary sorting.
		if ($section == 'general')
			return $rows;

		// Few sections require us to sort ascending by their keys...
		if (in_array($section, ['time', 'day']))
		{
			ksort($rows);
			return $rows;
		}

		// Other sections require us to order descending by their first value.
		uasort($rows, 'AwstatsMerger::helper_sort_desc');
		return $rows;
	}

	public function getSections()
	{
		return $this->sections;
	}

	public function hasSection(string $section)
	{
		return in_array($section, $this->sections);
	}

	/**
	 * Merges general statistics.
	 * TODO: improve this? (How? Suggestions are welcome.)
	 * @param $rows existing set of rows to merge into
	 * @param $newRows rows to merge into $rows
	 */
	private static function merge_general(array $rows, array $newRows)
	{
		foreach ($newRows as $item => $row)
		{
			if (!isset($rows[$item]))
			{
				$rows[$item] = $row;
				continue;
			}
			else
			{
				if ($item == 'FirstTime')
					$rows[$item][0] = min($row[0], $rows[$item][0]);
				elseif ($item == 'LastTime' || $item == 'LastUpdate')
					$rows[$item][0] = max($row[0], $rows[$item][0]);
				elseif ($item == 'TotalVisits')
					$rows[$item][0] += $row[0];
			}
		}
		return $rows;
	}

	/**
	 * Merges login statistics.
	 * @param $rows existing set of rows to merge into
	 * @param $newRows rows to merge into $rows
	 */
	private static function merge_login(array $rows, array $newRows)
	{
		foreach ($newRows as $login => $row)
		{
			if (!isset($rows[$login]))
			{
				$rows[$login] = $row;
				continue;
			}

			$rows[$login][0] += $row[0];
			$rows[$login][1] += $row[1];
			$rows[$login][2] += $row[2];
			$rows[$login][3] = max($rows[$login][3], $row[3]);
		}
		return $rows;
	}

	/**
	 * Merges robot statistics.
	 * @param $rows existing set of rows to merge into
	 * @param $newRows rows to merge into $rows
	 */
	private static function merge_robot(array $rows, array $newRows)
	{
		foreach ($newRows as $robot => $row)
		{
			if (!isset($rows[$robot]))
			{
				$rows[$robot] = $row;
				continue;
			}

			$rows[$robot][0] += $row[0];
			$rows[$robot][1] += $row[1];
			$rows[$robot][2] = max($rows[$robot][2], $row[2]);
			$rows[$robot][3] += $row[3];
		}
		return $rows;
	}

	/**
	 * Merges worm statistics.
	 * Note: handled the same way as emailsender and emailreceiver.
	 * @param $rows existing set of rows to merge into
	 * @param $newRows rows to merge into $rows
	 */
	private static function merge_worm(array $rows, array $newRows)
	{
		foreach ($newRows as $worm => $row)
		{
			if (!isset($rows[$worm]))
			{
				$rows[$worm] = $row;
				continue;
			}

			$rows[$worm][0] += $row[0];
			$rows[$worm][1] += $row[1];
			$rows[$worm][2] = max($rows[$worm][2], $row[2]);
		}
		return $rows;
	}

	/**
	 * Merges email sender statistics.
	 * Note: handled the same way as emailreceiver and worm.
	 * @param $rows existing set of rows to merge into
	 * @param $newRows rows to merge into $rows
	 */
	private static function merge_emailsender(array $rows, array $newRows)
	{
		return self::merge_worm($rows, $newRows);
	}

	/**
	 * Merges email receiver statistics.
	 * Note: handled the same way as emailsender and worm.
	 * @param $rows existing set of rows to merge into
	 * @param $newRows rows to merge into $rows
	 */
	private static function merge_emailreceiver(array $rows, array $newRows)
	{
		return self::merge_worm($rows, $newRows);
	}

	/**
	 * Merges unknown referer statistics.
	 * Note: handled the same way as unknownrefererbrowser.
	 * @param $rows existing set of rows to merge into
	 * @param $newRows rows to merge into $rows
	 */
	private static function merge_unknownreferer(array $rows, array $newRows)
	{
		foreach ($newRows as $referer => $row)
		{
			if (!isset($rows[$referer]))
			{
				$rows[$referer] = $row;
				continue;
			}

			$rows[$referer][0] = max($rows[$referer][0], $row[0]);
		}
		return $rows;
	}

	/**
	 * Merges unknown referer browser statistics.
	 * Note: handled the same way as unknownreferer.
	 * @param $rows existing set of rows to merge into
	 * @param $newRows rows to merge into $rows
	 */
	private static function merge_unknownrefererbrowser(array $rows, array $newRows)
	{
		return self::merge_unknownreferer($rows, $newRows);
	}

	/**
	 * Merges sections that have dates in indexes 3 and 4 (if present)
	 * @param $rows existing set of rows to merge into
	 * @param $newRows rows to merge into $rows
	 */
	private static function helper_sum_with_dates(array $rows, array $newRows)
	{
		foreach ($newRows as $key => $row)
		{
			if (!isset($rows[$key]))
			{
				$rows[$key] = $row;
				continue;
			}

			// Merge rows, taking into account that not all entries have the same number of data items
			// Indexes 3 and 4 are dates so take max instead of sum
			// For indexes above 4 take whichever one comes first
			foreach ($row as $num => $stats)
			{
				if ($num <= 2)
					$rows[$key][$num] = isset($rows[$key][$num]) ? $rows[$key][$num] + $stats : $stats;
				elseif ($num <= 4)
					$rows[$key][$num] = isset($rows[$key][$num]) ? max($rows[$key][$num], $stats) : $stats;
				elseif (!isset($rows[$key][$num]))
					$rows[$key][$num] = $stats;
			}
		}
		return $rows;
	}

	/**
	 * Merges visitor statistics.
	 * @param $rows existing set of rows to merge into
	 * @param $newRows rows to merge into $rows
	 */
	private static function merge_visitor(array $rows, array $newRows)
	{
		// Note that index 3 is a start date and the max will be taken when it
		// should technically be min but it doesn't matter because the start date is never used.
		return self::helper_sum_with_dates($rows, $newRows);
	}

	/**
	 * Merges extra_1 statistics.
	 * @param $rows existing set of rows to merge into
	 * @param $newRows rows to merge into $rows
	 */
	private static function merge_extra_1(array $rows, array $newRows)
	{
		return self::helper_sum_with_dates($rows, $newRows);
	}

	/**
	 * Merges statistics by simply taking the sum of existing rows.
	 * @param $rows existing set of rows to merge into
	 * @param $newRows rows to merge into $rows
	 */
	private static function helper_sum_merge(array $rows, array $newRows)
	{
		foreach ($newRows as $key => $row)
		{
			if (!isset($rows[$key]))
			{
				$rows[$key] = $row;
				continue;
			}

			foreach ($row as $num => $stats)
			{
				$rows[$key][$num] += $stats;
			}
		}
		return $rows;
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

	/**
	 * Write the current file to a file.
	 */
	public function writeFileContents(mixed $target)
	{
		if (!is_resource($target))
			$target = fopen($target, 'w');

		fwrite($target, "AWSTATS DATA FILE 6.9 (build 1.925)\n\n");
		foreach ($this->sections as $section_name)
		{
			echo $section_name, "\n";
			$rows = $this->getSection($section_name);
			fwrite($target, 'BEGIN_' . strtoupper($section_name) . ' ' . count($rows) . "\n");

			foreach ($rows as $key => $row)
				fwrite($target, $key . ' ' . implode(' ', $row) . "\n");

			fwrite($target, 'END_' . strtoupper($section_name) . "\n\n");
		}

		fclose($target);
	}
}
