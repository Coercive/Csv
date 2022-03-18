<?php
namespace Coercive\Utility\Csv;

use SplFileObject;
use Exception;

/**
 * Importer
 *
 * @package 	Coercive\Utility\Csv
 * @link	https://github.com/Coercive/Csv
 *
 * @author  	Anthony Moral <contact@coercive.fr>
 * @copyright   2019
 * @license 	MIT
 */
class Importer
{
	const BOM = "\xef\xbb\xbf";

	/** @var SplFileObject CSV file */
	private $file;

	/** @var array Csv column header */
	private $header = [];

	/** @var string Csv field delimiter */
	private $delimiter;

	/** @var string Csv field enclosure */
	private $enclosure;

	/** @var string Csv escape */
	private $escape;

	/** @var callable Custom user process */
	private $callback = null;

	/** @var bool[] List needed workarounds */
	private $workarounds = [
		'seek_workaround' => false
	];

	/**
	 * Prepare and list needed workarounds status
	 *
	 * @return void
	 */
	private function workarounds()
	{
		$this->workarounds['seek_workaround'] = !version_compare(PHP_VERSION, '8.0.1', '>=');
	}

	/**
	 * There is a bug in php for seeking files
	 * seems solved php version > 8.0.1
	 *
	 * @author info@inatica.com
	 * @link https://www.php.net/manual/en/splfileobject.seek.php#126365
	 *
	 * @param int $line
	 * @param bool $rewind [optional]
	 * @return void
	 */
	private function seek_workaround(int $line, bool $rewind = true)
	{
		# 0 === rewind
		if($rewind || $line === 0) {
			$this->file->rewind();
		}
		if($line === 0) {
			return;
		}

		# If PHP > 8.0.1
		if (!$this->workarounds['seek_workaround']) {
			$this->file->seek($line);
			return;
		}

		# If lower version of PHP
		if($line === 1 && $this->file->key() === 0) {
			# Consumes one line into nothingness
			$this->file->fgets();
		}

		# The line before (-1) for all other cases.
		else {
			$this->file->seek($line - 1);
		}
	}

	/**
	 * If BOM is detected, the file pointer move to the 4th character, else rewind.
	 *
	 * @return void
	 */
	private function bom()
	{
		if ($this->file->fread(3) !== self::BOM) {
			$this->file->rewind();
		}
	}

	/**
	 * Importer constructor.
	 *
	 * @param string $path
	 * @param string $delimiter [optional]
	 * @param string $enclosure [optional]
	 * @param string $escape [optional]
	 * @throws Exception
	 */
	public function __construct(string $path, string $delimiter = ',', string $enclosure = "\"", string $escape = "\\")
	{
		# Set properties
		$this->delimiter = $delimiter;
		$this->enclosure = $enclosure;
		$this->escape = $escape;

		# Open file
		ini_set('auto_detect_line_endings', true);
		$this->file = new SplFileObject($path);
		ini_set('auto_detect_line_endings', false);
		if (!$this->file->isFile() || !$this->file->isReadable()) {
			throw new Exception('Can\'t open CSV File.');
		}

		# Workaround adaptations
		$this->workarounds();
	}

	/**
	 * Importer destructor.
	 *
	 * @return void
	 */
	public function __destruct()
	{
		$this->file = null;
	}

	/**
	 * AUTO DETECT CSV DELIMITER
	 *
	 * @param int $lines
	 * @param array $delimiters
	 * @return Importer
	 */
	public function detectDelimiter(int $lines = 10, array $delimiters = [',',"\t",';','|',':']): Importer
	{
		# Init
		$i = 0;
		$sampled = [];
		$this->file->rewind();
		$this->bom();

		# Loop count probability delimiter
		while($this->file->valid() && $i <= $lines)
		{
			$line = $this->file->fgets();
			foreach ($delimiters as $delimiter)
			{
				$fields = preg_split('`['.$delimiter.']`', $line);
				if(count($fields) > 1) {
					if(!empty($sampled[$delimiter])) {
						$sampled[$delimiter]++;
					} else {
						$sampled[$delimiter] = 1;
					}
				}
			}
			$i++;
		}

		# Reset pointer
		$this->file->rewind();

		# Set probability delimiter
		if($sampled) {
			$sampled = array_keys($sampled, max($sampled));
			$this->delimiter = strval($sampled[0] ?? $this->delimiter);
		}

		# Maintain chainability
		return $this;
	}

	/**
	 * Seek to targeted line or add offset from the current position
	 *
	 * @param int $line
	 * @param bool $rewind [optional]
	 * @return $this
	 */
	public function seek(int $line, bool $rewind = true): Importer
	{
		$this->seek_workaround($line, $rewind);
		return $this;
	}

	/**
	 * PARSE HEADER
	 *
	 * @param bool $rewind [optional]
	 * @return Importer
	 */
	public function parseHeader(bool $rewind = true): Importer
	{
		# Init pointer / bom
		if($rewind) {
			$this->file->rewind();
			$this->bom();
		}

		# Get header struct
		$this->header = $this->file->fgetcsv($this->delimiter, $this->enclosure, $this->escape);

		# Maintain chainability
		return $this;
	}

	/**
	 * Export headers
	 *
	 * @return array
	 */
	public function getHeader(): array
	{
		return $this->header;
	}

	/**
	 * CUSTOM HEADER
	 *
	 * @param array $fields [optional]
	 * @return Importer
	 */
	public function setHeader(array $fields = []): Importer
	{
		$this->header = $fields;
		return $this;
	}

	/**
	 * Retrieve only header like fields given
	 *
	 * @param array $fields [optional]
	 * @return Importer
	 */
	public function onlyHeader(array $fields = []): Importer
	{
		foreach ($this->header as $k => $name) {
			if(!in_array($name, $fields, true)) {
				unset($this->header[$k]);
			}
		}
		return $this;
	}

	/**
	 * READ SER CSV FILE
	 *
	 * @param int $lines [optional]
	 * @return array
	 */
	public function get(int $lines = 0): array
	{
		# -1 : Loop limit ignored
		$line = 0;
		$count = $lines > 0 ? 0 : -1;

		# Read lines
		$datas = [];
		while ($count < $lines && $this->file->valid()) {

			$data = $this->file->fgetcsv($this->delimiter);
			if(!$data || $data === [0=>null]) { continue; }

			# Named column
			if ($this->header) {
				$row = [];
				foreach ($this->header as $k => $name) {
					if(null === $name) { continue; }
					if(!array_key_exists($k, $data)) { continue; }
					$row[$name] = $data[$k];
				}
				$datas[$line] = $row;
			}

			# Numeric column
			else {
				$datas[$line] = $data;
			}

			# Process line / elements
			if($this->callback) {
				foreach ($datas[$line] as $k => $v) {
					$datas[$line][$k] = ($this->callback)($v, strval($k), $line);
				}
			}

			# Line count
			if ($lines > 0) { $count++; }
			$line++;

		}
		return $datas;
	}

	/**
	 * PROCESS CELL
	 *
	 * Receive 3 arguments :
	 * function( (string) $value , (string) $cellKey , (int) $currentLine )
	 *
	 * The cellKey is allways a string, even if numeric column name (for strict compare)
	 *
	 * Your callable should return the processed value of the current cell
	 *
	 * @param callable $function
	 * @return $this
	 */
	public function callback(callable $function)
	{
		$this->callback = $function;
		return $this;
	}
}
