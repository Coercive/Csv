<?php
namespace Coercive\Utility\Csv;

use SplFileObject;
use Exception;

/**
 * Importer
 *
 * @package Coercive\Utility\Csv
 * @link https://github.com/Coercive/Csv
 *
 * @author Anthony Moral <contact@coercive.fr>
 * @copyright 2025
 * @license MIT
 */
class Importer
{
	const BOM = "\xef\xbb\xbf";

	const ENCODINGS = ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'];

	/** @var SplFileObject CSV file */
	private $file;

	/** @var array Csv column header */
	private array $header = [];

	/** @var string Csv target encoding */
	private string $targetEncoding = 'UTF-8';

	/** @var string Csv field encoding */
	private string $encoding = '';

	/** @var string Csv field delimiter */
	private string $delimiter;

	/** @var string Csv field enclosure */
	private string $enclosure;

	/** @var string Csv escape */
	private string $escape;

	/** @var callable|null Custom user process */
	private $callback = null;

	/** @var bool[] List needed workarounds */
	private array $workarounds = [
		'seek_workaround' => false
	];

	/**
	 * Prepare and list needed workarounds status
	 *
	 * @return void
	 */
	private function workarounds(): void
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
	private function seek_workaround(int $line, bool $rewind = true): void
	{
		# 0 === rewind
		if($rewind || $line === 0) {
			$this->reset();
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
	private function bom(): void
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
		$previousLineEnding = ini_get('auto_detect_line_endings');
		ini_set('auto_detect_line_endings', true);
		$this->file = new SplFileObject($path);
		ini_set('auto_detect_line_endings', $previousLineEnding);
		if (!$this->file->isFile() || !$this->file->isReadable()) {
			throw new Exception('Can\'t open CSV File.');
		}

		# Reset pointer position
		$this->reset();

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

		# Reset pointer position
		$this->reset();

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

		# Reset pointer position
		$this->reset();

		# Set probability delimiter
		if($sampled) {
			$sampled = array_keys($sampled, max($sampled));
			$this->delimiter = strval($sampled[0] ?? $this->delimiter);
		}

		# Maintain chainability
		return $this;
	}

	/**
	 * AUTO DETECT CSV ENCODING
	 *
	 * @param int $lines
	 * @param array $encodings
	 * @return Importer
	 */
	public function detectEncoding(int $lines = 10, array $encodings = self::ENCODINGS): Importer
	{
		# Init
		$i = 0;

		# Reset pointer position
		$this->reset();

		# Loop count probability delimiter
		$sample = '';
		while($this->file->valid() && $i <= $lines)
		{
			$sample .= $this->file->fgets();
		}

		# Reset pointer position
		$this->reset();

		# Update detected encoding
		$this->encoding = mb_detect_encoding($sample, $encodings, true) ?: 'UTF-8';

		# Maintain chainability
		return $this;
	}

	/**
	 * Mannualy set file encoding
	 *
	 * @param string $encoding
	 * @return $this
	 */
	public function setTargetEncoding(string $encoding): self
	{
		$this->targetEncoding = $encoding;
		return $this;
	}

	/**
	 * Mannualy set file encoding
	 *
	 * @param string $encoding
	 * @return $this
	 */
	public function setEncoding(string $encoding): self
	{
		$this->encoding = $encoding;
		return $this;
	}

	/**
	 * Reset point position
	 *
	 * @return $this
	 */
	public function reset(): Importer
	{
		$this->file->rewind();
		$this->bom();
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
		# Reset pointer position
		if($rewind) {
			$this->reset();
		}

		# Get header struct
		$this->header = $this->file->fgetcsv($this->delimiter, $this->enclosure, $this->escape);

		# Transcoding each field if necessary
		if ($this->encoding && $this->targetEncoding && $this->encoding !== $this->targetEncoding) {
			foreach ($this->header as $k => $name) {
				$this->header[$k] = mb_convert_encoding($name, $this->targetEncoding, $this->encoding);
			}
		}

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
	 * Export delimiter
	 *
	 * @return string
	 */
	public function getDelimiter(): string
	{
		return $this->delimiter;
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

			# Transcoding each field if necessary
			if ($this->encoding && $this->targetEncoding && $this->encoding !== $this->targetEncoding) {
				foreach ($datas[$line] as $k => $v) {
					$datas[$line][$k] = mb_convert_encoding($v, $this->targetEncoding, $this->encoding);
				}
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
