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
$csv = new Importer('path/name.csv');

# AUTO DETECT DELIMITER
$csv->detectDelimiter(int test nb line : 10 , array delimiter test list [';', ',']);

# PARSE HEADER
$csv->parseHeader();

# CUSTOM CELLS NAME
$csv->setHeader([
    'cell_name_1',
    'cell_name_2',
    'cell_name_3',
    'cell_name_4',
    ...
]);

# YOU CAN SPECIFY TO NOT RETRIEVE OPTIONAL CELLS BY SETTING NULL
$csv->setHeader([
    'cell_name_1',
    NULL,
    'cell_name_3',
    NULL,
    ...
]);

# GET CSV TO ARRAY
$array = $csv->get();

# CUSTOM CELL PROCESS
$csv->callback(function($value, $cellKey, $currentLine) {

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
$csv = new Exporter('/path/to/csv/file.csv');

# OPTIONS
$csv = new Exporter('/path/to/csv/file.csv', delimiter : ',', enclosure : '"', mysqlnull false);

# HEADER [optional]
$csv->addHeader(['name_col_1', 'name_col_2', 'name_col_3' ... ]);

# SAVE ONE LINE
foreach(x as y) $csv->addLine(['col_1', 'col_2', 'col_3' ... ]);

# SAVE MULTIPLE LINES
$csv->addLines([
    ['col_1', 'col_2', 'col_3' ... ],
    ['col_1', 'col_2', 'col_3' ... ],
    ['col_1', 'col_2', 'col_3' ... ],
    ['col_1', 'col_2', 'col_3' ... ]
    ...
]);

# GET ERROR
$csv->getErrors();

# CLOSE MANUALLY OR AUTO
$csv->close();
# OR
unset($csv);

```
