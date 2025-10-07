<?php declare(strict_types=1);

use Coercive\Utility\Csv\Importer;
use PHPUnit\Framework\TestCase;

final class ImporterTest extends TestCase
{
	public function testDetectDelimiterSemicolon(): void
	{
		$csv = new Importer(__DIR__ . '/ressources/semicolon.csv');
		$csv->detectDelimiter();
		$this->assertSame(';', $csv->getDelimiter());
	}

	public function testDetectDelimiterComma(): void
	{
		$csv = new Importer(__DIR__ . '/ressources/comma.csv');
		$csv->detectDelimiter();
		$this->assertSame(',', $csv->getDelimiter());
	}

	public function testParseHeader(): void
	{
		$csv = new Importer(__DIR__ . '/ressources/basic.csv');
		$csv->detectDelimiter();
		$csv->parseHeader();
		$data = $csv->get();

		$this->assertArrayHasKey('name', $data[0]);
		$this->assertSame('Alice', $data[0]['name']);
	}

	public function testCustomHeader(): void
	{
		$csv = new Importer(__DIR__ . '/ressources/basic.csv');
		$csv->detectDelimiter();
		$csv->setHeader(['first', 'second', 'third']);
		$data = $csv->get();

		$this->assertArrayHasKey('second', $data[0]);
	}

	public function testPartialHeader(): void
	{
		$csv = new Importer(__DIR__ . '/ressources/basic.csv');
		$csv->detectDelimiter();
		$csv->setHeader(['id', null, 'age']);
		$data = $csv->get();

		$this->assertArrayHasKey('id', $data[0]);
		$this->assertArrayHasKey('age', $data[0]);
		$this->assertArrayNotHasKey('name', $data[0]);
	}

	public function testSeekAndGet(): void
	{
		$csv = new Importer(__DIR__ . '/ressources/seek_test.csv');
		$csv->detectDelimiter();
		$csv->seek(7);
		$data = $csv->get();

		$this->assertSame('G', $data[0][1] ?? null);
	}

	public function testCallbackProcessing()
	{
		$csv = new Importer(__DIR__ . '/ressources/basic.csv');
		$csv->detectDelimiter();
		$csv->parseHeader();

		$csv->callback(function ($value, $cellKey, $currentLine) {
			return strtoupper($value);
		});

		$data = $csv->get();
		$this->assertSame('ALICE', $data[0]['name']);
	}

	public function testUtf8BomFileHandledProperly()
	{
		$csv = new Importer(__DIR__ . '/ressources/utf8_bom.csv');
		$csv->detectDelimiter();
		$csv->parseHeader();
		$data = $csv->get();

		$this->assertArrayHasKey('id', $data[0]);
		$this->assertSame('1', $data[0]['id']);
	}

	public function testQuotedValuesHandledCorrectly()
	{
		$csv = new Importer(__DIR__ . '/ressources/quoted.csv');
		$csv->detectDelimiter();
		$csv->parseHeader();
		$data = $csv->get();

		$this->assertSame('This is a "quoted, field"', $data[0]['comment']);
	}
}