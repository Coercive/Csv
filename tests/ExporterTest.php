<?php declare(strict_types=1);

use Coercive\Utility\Csv\Exporter;
use PHPUnit\Framework\TestCase;

final class ExporterTest extends TestCase
{
	private string $tmpFile;

	protected function setUp(): void
	{
		$this->tmpFile = tempnam(sys_get_temp_dir(), 'coercive_csv_exp_utest');
	}

	protected function tearDown(): void
	{
		if (file_exists($this->tmpFile)) {
			unlink($this->tmpFile);
		}
	}

	public function testExportBasicCsv(): void
	{
		$csv = new Exporter($this->tmpFile);
		$csv->addHeader(['id', 'name', 'age']);
		$csv->addLine([1, 'John', 30]);
		$csv->addLine([2, 'Jane', 25]);

		$content = file_get_contents($this->tmpFile);
		$this->assertStringContainsString("id,name,age", $content);
		$this->assertStringContainsString("1,John,30", $content);
		$this->assertStringContainsString("2,Jane,25", $content);
	}

	public function testExportWithSemicolonDelimiter(): void
	{
		$csv = new Exporter($this->tmpFile, ';');
		$csv->addHeader(['city', 'country']);
		$csv->addLine(['Paris', 'France']);

		$content = file_get_contents($this->tmpFile);
		$this->assertStringContainsString('Paris;France', $content);
	}

	public function testExportWithQuotes(): void
	{
		$csv = new Exporter($this->tmpFile, ',', '"');
		$csv->addHeader(['quote_test']);
		$csv->addLine(['John "The Hammer" Doe']);

		$content = file_get_contents($this->tmpFile);
		$this->assertStringContainsString('"John ""The Hammer"" Doe"', $content);
	}

	public function testExportMultipleLines(): void
	{
		$csv = new Exporter($this->tmpFile);
		$csv->addHeader(['col1', 'col2']);
		$csv->addLines([
			['A', 'B'],
			['C', 'D'],
			['E', 'F']
		]);

		$content = file_get_contents($this->tmpFile);
		$this->assertStringContainsString("A,B", $content);
		$this->assertStringContainsString("E,F", $content);
	}

	public function testExportTsvFile(): void
	{
		$csv = new Exporter($this->tmpFile, "\t");
		$csv->addHeader(['col1', 'col2']);
		$csv->addLine(['X', 'Y']);

		$content = file_get_contents($this->tmpFile);
		$this->assertStringContainsString("X\tY", $content);
	}

	public function testExportWithMysqlNull(): void
	{
		$csv = new Exporter($this->tmpFile, ',', '"', true);
		$csv->addHeader(['id', 'value']);
		$csv->addLines([
			[1, null],
			[2, 'data']
		]);

		$content = file_get_contents($this->tmpFile);
		$this->assertStringContainsString("1,NULL", $content);
		$this->assertStringContainsString("2,data", $content);
	}

	public function testAddLineWithoutHeaderStillWorks()
	{
		$csv = new Exporter($this->tmpFile);
		$csv->addLine(['a', 'b', 'c']);

		$content = file_get_contents($this->tmpFile);
		$this->assertStringContainsString('a,b,c', $content);
	}

	public function testEmptyPathThrowsException()
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Filepath not specified');
		new Exporter('');
	}

	public function testInvalidPathThrowsException()
	{
		$this->expectException(InvalidArgumentException::class);
		new Exporter('invalid<>path.csv');
	}

	public function testUncreatableDirectoryThrowsException()
	{
		$this->expectException(RuntimeException::class);
		new Exporter('/root/protected/file.csv');
	}
}