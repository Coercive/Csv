<?php
namespace Coercive\Utility\Csv;

use InvalidArgumentException;
use RuntimeException;

/**
 * Exporter
 *
 * @package 	Coercive\Utility\Csv
 * @link		@link https://github.com/Coercive/Csv
 *
 * @author  	Anthony Moral <contact@coercive.fr>
 * @copyright   2025
 * @license 	MIT
 */
class Exporter
{
	/** @var resource */
	private $stream;

	private string $delimiter = '';

	private string $enclosure = '';

	private bool $mysqlNull = false;

	/**
	 * Exporter constructor.
	 *
	 * @param string $filepath
	 * @param string $delimiter [optional]
	 * @param string $enclosure [optional]
	 * @param bool $mysqlNull [optional] Puts a string 'NULL' instead of a null value
	 * @return void
	 * @throws InvalidArgumentException
	 * @throws RuntimeException
	 */
	public function __construct(string $filepath, string $delimiter = ',', string $enclosure = '"', bool $mysqlNull = false)
	{
		if(!$filepath) {
			throw new InvalidArgumentException('Filepath not specified');
		}
		if(!is_file($filepath)) {
			if (preg_match('/[<>:"\'§&@#!(){}\[\]|?*]/', $filepath)) {
				throw new InvalidArgumentException('Invalid chars in file path: ' . $filepath);
			}
			if(!preg_match('`^(?P<path>.*)/.*$`', $filepath, $matches)) {
				throw new InvalidArgumentException('Invalid directory path: ' . $filepath);
			}
			if(!is_dir($matches['path'])) {
				if(!mkdir($matches['path'], 0755, true)) {
					throw new RuntimeException('Unable to create directory: ' . $matches['path']);
				}
			}
		}

		# Create/Write in file
		$this->stream = fopen($filepath, 'a');

		# Set Delimiter
		$this->delimiter = $delimiter;

		# Set Enclosure
		$this->enclosure = $enclosure;

		# Set MySQL NULL
		$this->mysqlNull = $mysqlNull;
	}

	/**
	 * Exporter destructor.
	 *
	 * @return void
	 */
	public function __destruct()
	{
		$this->close();
	}

	/**
	 * Close file
	 *
	 * @return void
	 */
	public function close(): void
	{
		if(is_resource($this->stream)) {
			fclose($this->stream);
		}
	}

	/**
	 * Add header
	 *
	 * @param array $fields
	 * @return $this
	 */
	public function addHeader(array $fields): self
	{
		fputcsv($this->stream, $fields, $this->delimiter, $this->enclosure);
		return $this;
	}

	/**
	 * Add one line
	 *
	 * @param array $fields
	 * @return $this
	 */
	public function addLine(array $fields): self
	{
		if($this->mysqlNull) {
			foreach ($fields as &$field) {
				if ($field === null) {
					$field = 'NULL';
				}
			}
		}
		fputcsv($this->stream, $fields, $this->delimiter, $this->enclosure);
		return $this;
	}

	/**
	 * Add multi lines
	 *
	 * @param array $lines
	 * @return $this
	 */
	public function addLines(array $lines): self
	{
		foreach ($lines as $aOneLine) {
			$this->addLine($aOneLine);
		}
		return $this;
	}

	/**
	 * @param bool $enable
	 * @return $this
	 */
	public function setSqlNull(bool $enable): self
	{
		$this->mysqlNull = $enable;
		return $this;
	}
}