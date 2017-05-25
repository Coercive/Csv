<?php
namespace Coercive\Utility\Csv;

/**
 * Exporter
 * PHP Version 	7
 *
 * @package 	Coercive\Utility\Csv
 * @link		@link https://github.com/Coercive/Csv
 *
 * @author  	Anthony Moral <contact@coercive.fr>
 * @copyright   (c) 2016 - 2017 Anthony Moral
 * @license 	http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */
class Exporter {

	/** @var resource */
	private $_rFile;

	/** @var array */
	private $_aErrors = [];

	/** @var string */
	private $_sDelimiter = '';

	/** @var string */
	private $_sEnclosure = '';

	/** @var bool */
	private $_bMysqlNull = false;

	/**
	 * ERROR
	 *
	 * @param string $sMessage
	 */
	protected function _setError($sMessage) {
		$this->_aErrors[] = $sMessage;
	}

	/**
	 * GETTER ERROR
	 *
	 * @return array
	 */
	public function getErrors() {
		return (array) $this->_aErrors;
	}

	/**
	 * CsvWriter constructor.
	 *
	 * @param string $sFilePath
	 * @param string $sDelimiter [optional]
	 * @param string $sEnclosure [optional]
	 * @param bool $bMysqlNull [optional]
	 */
	public function __construct($sFilePath, $sDelimiter = ',', $sEnclosure = '"', $bMysqlNull = false) {

		# NOT DIRECTORY : create
		if(!file_exists($sFilePath) || !is_file($sFilePath)) {

			if(!preg_match('`^(?P<path>.*)\/.*$`', $sFilePath, $aMatches)) { $this->_setError('Destpath match error'); return; }
			if(!is_dir($aMatches['path'])) {
				if(!mkdir($aMatches['path'], 0755, true)) {
					$this->_setError('Failure when creating directory'); return;
				}
			}

		}

		# Create/Write in file
		$this->_rFile = fopen($sFilePath, 'a');

		# Set Delimiter
		$this->_sDelimiter = $sDelimiter;

		# Set Enclosure
		$this->_sEnclosure = $sEnclosure;

		# Set MySQL NULL
		$this->_bMysqlNull = $bMysqlNull;

	}

	/**
	 * CsvWriter destructor.
	 */
	public function __destruct() {
		$this->close();
	}

	/**
	 * CLOSE FILE
	 */
	public function close() {
		if(is_resource($this->_rFile)) {
			fclose($this->_rFile);
		}
	}

	/**
	 * ADD HEADER
	 *
	 * @param array $aHeader
	 */
	public function addHeader($aHeader) {
		fputcsv($this->_rFile, $aHeader, $this->_sDelimiter);
	}

	/**
	 * ADD ONE LINE
	 *
	 * @param array $aOneLine
	 * @param bool $bSqlMode [optional]
	 * @return $this
	 */
	public function addLine($aOneLine, $bSqlMode = false) {

		# NORMAL MODE
		if(!$bSqlMode) {
			fputcsv($this->_rFile, $aOneLine, $this->_sDelimiter);
			return $this;
		}

		# SQL MODE
		$sDelimiterEscape = preg_quote($this->_sDelimiter, '`');
		$sEnclosureEscape = preg_quote($this->_sEnclosure, '`');

		$aOutput = [];
		foreach ($aOneLine as $mField) {
			if ($mField === null && $this->_bMysqlNull) {
				$aOutput[] = 'NULL';
				continue;
			}

			$aOutput[] = preg_match("`(?:$sDelimiterEscape|$sEnclosureEscape|\s)`", $mField) ? (
				$this->_sEnclosure . str_replace($this->_sEnclosure, $this->_sEnclosure . $this->_sEnclosure, $mField) . $this->_sEnclosure
			) : $mField;
		}

		fwrite($this->_rFile, join($this->_sDelimiter, $aOutput) . "\n");

		return $this;

	}

	/**
	 * ADD MULTI LINES
	 *
	 * @param array $aMultiLines
	 * @return $this
	 */
	public function addLines($aMultiLines) {
		foreach ($aMultiLines as $aOneLine) {
			$this->addLine($aOneLine);
		}
		return $this;
	}

}