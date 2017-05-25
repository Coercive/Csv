Coercive Csv
============

- The CSV Importer allows you to easily import CSV files by parsing them to array with setted labels.
- The CSV Exporter allows you to easily export array to CSV files.


Get
---
```
composer require coercive/csv
```

IMPORTER
--------
```php
use Coercive\Utility\Csv\Importer;

# INIT CSV OBJECT
$oCsv = new Importer('path/name.csv');

# OPTIONS
$oCsv = new Importer('path/name.csv', '; (delimiter)', '8000 (max line length)');

# AUTO DETECT DELIMITER
$oCsv->autoDetectCsvDelimiter(int test nb line : 10 , array delimiter test list [';', ',']);

# PARSE HEADER
$oCsv->parseHeader();

# CUSTOM CELLS NAME
$oCsv->setCustomHeader([
    'cell_name_1',
    'cell_name_2',
    'cell_name_3',
    'cell_name_4',
    ...
]);

# YOU CAN SPECIFY TO NOT RETRIEVE OPTIONAL CELLS BY SETTING NULL
$oCsv->setCustomHeader([
    'cell_name_1',
    NULL,
    'cell_name_3',
    NULL,
    ...
]);

# GET CSV TO ARRAY
$array = $oCsv->get();

# CUSTOM LINE PROCESS
$oCsv->processLine(function($value, $cellKey, $currentLine) {

	/** 
	 * Receive 3 arguments :
	 * function( (string) $value , (string) $cellKey , (int) $currentLine )
	 *
	 * The cellKey is allways a string, even if numeric column name (for strict compare)
	 *
	 * Your callable should return the processed value of the current cell
	 */
    
	return "Line : $currentLine, cell : $cellKey, value : $value";
});

```

EXPORTER
--------
```php
use Coercive\Utility\Csv\Exporter;

# INIT CSV OBJECT (csv, tsv etc...)
$oCSV = new Exporter('/path/to/csv/file.csv');

# OPTIONS
$oCSV = new Exporter('/path/to/csv/file.csv', delimiter : ',', enclosure : '"', mysqlnull false);

# HEADER [optional]
$oCSV->addHeader(['name_col_1', 'name_col_2', 'name_col_3' ... ]);

# SAVE ONE LINE
foreach(x as y) $oCSV->addLine(['col_1', 'col_2', 'col_3' ... ]);

# SAVE MULTIPLE LINES
$oCSV->addLines([
    ['col_1', 'col_2', 'col_3' ... ],
    ['col_1', 'col_2', 'col_3' ... ],
    ['col_1', 'col_2', 'col_3' ... ],
    ['col_1', 'col_2', 'col_3' ... ]
    ...
]);

# GET ERROR
$oCSV->getErrors();

# CLOSE MANUALLY OR AUTO
$oCSV->close();
# OR
unset($oCSV);

```
