<?php
/*
Awstats Data File Parser / Merger / Generator
@version: 2.0
@date: 2025-09-23
@author: Aaron van Geffen
@website: https://aaronweb.net/
@license: BSD
*/

/**
 * Parses an Awstats statistics file.
 */
class AwstatsFile
{
	protected $handle;
	protected array $sections;

	/**
	 * Opens the designated file and indexes all sections in it.
	 */
	public function __construct(string $filename)
	{
		if (!$this->handle = fopen($filename, 'r'))
			die('File not found: ' . $filename);

		$this->populateSectionMap();
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

	/**
	 * Processes all rows for particular section into an array of arrays.
	 */
	public function getSection(string $section)
	{
		if (!$this->hasSection($section))
			throw new DomainException('Unknown section: ' .  $section);

		fseek($this->handle, $this->sections[$section]);

		$rows = [];
		while (!feof($this->handle))
		{
			$line = trim(fgets($this->handle));
			if (str_starts_with($line, 'END_'))
				break;

			if ($line[0] == "#")
				continue;

			$row = explode(' ', $line);
			$key = array_shift($row);
			$rows[$key] = $row;
		}

		return $rows;
	}

	/**
	 * Returns an array of indexed sections.
	 */
	public function getSections()
	{
		return array_keys($this->sections);
	}

	public function hasSection(string $section)
	{
		return isset($this->sections[$section]);
	}

	/**
	 * Maps all sections in the current file, storing their positions.
	 */
	private function populateSectionMap()
	{
		fseek($this->handle, 0);

		$this->sections = [];
		while (!feof($this->handle))
		{
			$line = trim(fgets($this->handle));
			if (!str_starts_with($line, 'BEGIN_'))
				continue;

			$section = strtolower(substr($line, 6, strpos($line, ' ') - 6));
			$this->sections[$section] = ftell($this->handle);
		}
	}

	/**
	 * Write the current file to a file.
	 */
	public function writeFileContents(mixed $target)
	{
		if (!is_resource($target))
			$target = fopen($target, 'w');

		fwrite($target, "AWSTATS DATA FILE 7.8 (build 20200416)\n\n");
		foreach ($this->sections as $section_name => $pos)
		{
			$rows = $this->getSection($section_name);
			fwrite($target, 'BEGIN_' . strtoupper($section_name) . ' ' . count($rows) . "\n");

			foreach ($rows as $key => $row)
				fwrite($target, $key . ' ' . implode(' ', $row) . "\n");

			fwrite($target, 'END_' . strtoupper($section_name) . "\n\n");
		}

		fclose($target);
	}
}
