<?php
namespace Coercive\Utility\Csv;

use SplFileObject;
use Exception;

/**
 * Importer
 * PHP Version 	7
 *
 * @package 	Coercive\Utility\Csv
 * @link		@link https://github.com/Coercive/Csv
 *
 * @author  	Anthony Moral <contact@coercive.fr>
 * @copyright   (c) 2016 - 2017 Anthony Moral
 * @license 	http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */
class Importer {

	/** @var string Csv FileName */
	private $_sFilePath;

	/** @var resource fopen */
	private $_rCsvHandle;

	/** @var bool Custom or Parsed Header */
	private $_bCustomHeader;

	/** @var array */
	private $_aHeader;

	/** @var string Csv Delimiter */
	private $_sDelimiter;

	/** @var int Parse Line Max Length */
	private $_ilength;

	/** @var callable Custom user process */
	private $_cCallbackProcessLine = null;

	/**
	 * CsvImporter constructor.
	 *
	 * @param string $sFilePath
	 * @param string $sDelimiter [optional]
	 * @param int|null $iLength [optional]
	 * @throws Exception
	 */
	public function __construct($sFilePath, $sDelimiter = ',', $iLength = null) {

		# SKIP
		if(!file_exists($sFilePath) || !is_file($sFilePath)) {
			throw new Exception('CSV File does not exist or is not a regular file.');
		}

		# PROPERTIES
		$this->_sFilePath = (string) $sFilePath;
		$this->_sDelimiter = (string) $sDelimiter;
		$this->_ilength = (int) $iLength;
		$this->_bCustomHeader = false;

		# OPEN
		ini_set('auto_detect_line_endings', TRUE);
		$this->_rCsvHandle = fopen($sFilePath, 'r');
		ini_set('auto_detect_line_endings', FALSE);
		if ($this->_rCsvHandle === false) {
			throw new Exception('Can\'t open CSV File.');
		}

	}

	/**
	 * CsvImporter destructor.
	 */
	public function __destruct() {
		if ($this->_rCsvHandle) {
			fclose($this->_rCsvHandle);
		}
	}

	/**
	 * AUTO DETECT CSV DELIMITER
	 *
	 * @param int $iCheckLines
	 * @param array $aDelimiters
	 * @return CsvImporter
	 */
	public function autoDetectCsvDelimiter($iCheckLines = 10, $aDelimiters = [',',"\t",';','|',':']) {

		# OPEN
		ini_set('auto_detect_line_endings', TRUE);
		$oFile = new SplFileObject($this->_sFilePath);
		ini_set('auto_detect_line_endings', FALSE);

		# INIT
		$aResults = []; $i = 0;

		# LOOP COUNT PROBABILITY DELIMITER
		while($oFile->valid() && $i <= $iCheckLines){
			$sLine = $oFile->fgets();
			foreach ($aDelimiters as $sDelimiter){
				$aFields = preg_split('`['.$sDelimiter.']`', $sLine);
				if(count($aFields) > 1) {
					if(!empty($aResults[$sDelimiter])) {
						$aResults[$sDelimiter]++;
					} else {
						$aResults[$sDelimiter] = 1;
					}
				}
			}
			$i++;
		}

		# CLOSE
		$oFile = null;

		# Set Delimiter
		$aResults = array_keys($aResults, max($aResults));
		$this->_sDelimiter = (string) $aResults[0];

		# Maintain chainability
		return $this;
	}

	/**
	 * PARSE HEADER
	 *
	 * @return CsvImporter
	 */
	public function parseHeader() {

		# Set header information
		$this->_bCustomHeader = true;
		$this->_aHeader = fgetcsv($this->_rCsvHandle, $this->_ilength, $this->_sDelimiter);

		# Maintain chainability
		return $this;
	}

	/**
	 * CUSTOM HEADER
	 *
	 * @param array $aHeaderCells [optional]
	 * @return CsvImporter
	 */
	public function setCustomHeader($aHeaderCells = []) {

		# Set header information
		$this->_bCustomHeader = true;
		$this->_aHeader = $aHeaderCells;

		# Maintain chainability
		return $this;
	}

	/**
	 * READ SER CSV FILE
	 *
	 * @param int $iMaxLines [optional]
	 * @return array
	 */
	public function get($iMaxLines = 0) {

		# Current line
		$iCurrentLine = 0;

		# -1 : Loop limit ignored
		$iLineCount = $iMaxLines > 0 ? 0 : -1;

		# READ LINES
		$aDatas = [];
		while ($iLineCount < $iMaxLines && ($aData = fgetcsv($this->_rCsvHandle, 0, $this->_sDelimiter)) !== FALSE) {

			# With custom column name
			if ($this->_bCustomHeader) {
				$aCurrentRow = [];
				foreach ($this->_aHeader as $iKey => $sHeaderCellName) {
					if(is_null($sHeaderCellName)) { continue; }
					$aCurrentRow[$sHeaderCellName] = $aData[$iKey];
				}
				$aDatas[$iCurrentLine] = $aCurrentRow;
			}

			# Numeric column
			else {
				$aDatas[$iCurrentLine] = $aData;
			}

			# Process line / elements
			if($this->_cCallbackProcessLine) {
				foreach ($aDatas[$iCurrentLine] as $sKey => $sValue) {
					$aDatas[$iCurrentLine][$sKey] = ($this->_cCallbackProcessLine)($sValue, (string)$sKey, $iCurrentLine);
				}
			}

			# Increment line count
			if ($iMaxLines > 0) { $iLineCount++; }

			# Update current line
			$iCurrentLine++;

		}

		return $aDatas;

	}

	/**
	 * PROCESS LINE
	 *
	 * Receive 3 arguments :
	 * function( (string) $value , (string) $cellKey , (int) $currentLine )
	 *
	 * The cellKey is allways a string, even if numeric column name (for strict compare)
	 *
	 * Your callable should return the processed value of the current cell
	 *
	 * @param callable $cCallback
	 * @return $this
	 */
	public function processLine(callable $cCallback) {

		# Assign
		$this->_cCallbackProcessLine = $cCallback;

		# Maintain chainability
		return $this;

	}

}
