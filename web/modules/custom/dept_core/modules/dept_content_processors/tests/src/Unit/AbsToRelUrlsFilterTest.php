<?php

declare(strict_types=1);

namespace Drupal\Tests\dept_content_processors\Unit;

use Drupal\dept_content_processors\Plugin\Filter\AbsToRelUrlsFilter;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dept_content_processors\Plugin\Filter\AbsToRelUrlsFilter
 * @group dept
 */
class AbsToRelUrlsFilterTest extends UnitTestCase {

  /**
   * @var \Drupal\dept_content_processors\Plugin\Filter\AbsToRelUrlsFilter
   */
  protected AbsToRelUrlsFilter $filter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $configuration['settings'] = [];
    $this->filter = new AbsToRelUrlsFilter($configuration, 'dept_abs2rel_url', ['provider' => 'test']);
  }

  /**
   * @covers ::process
   *
   * @dataProvider providerAbsToRelUrls
   *
   * @param string $text
   *   Input HTML text.
   * @param string $expected
   *   The expected output.
   */
  public function testAbsToRelUrlsFilterProcess($text, $expected) {
    $this->filter->setDepartmentId('finance');
    // Add a newline to the expected result as FilterProcessResult adds this.
    $this->assertSame($expected . "\n", $this->filter->process($text, 'en')->getProcessedText());
  }

  /**
   * Provides data for testAbsToRelUrlsFilterProcess.
   *
   * @return array
   *   An array of test data.
   */
  public function providerAbsToRelUrls(): array {
    return [
      [
        '<a href="https://valuationservices.finance-ni.gov.uk/Property/Search">Valuation services</a>',
        '<a href="https://valuationservices.finance-ni.gov.uk/Property/Search">Valuation services</a>'
      ],
      [
        '<a href="http://valuationservices.finance-ni.gov.uk/Property/Search">Valuation services</a>',
        '<a href="http://valuationservices.finance-ni.gov.uk/Property/Search">Valuation services</a>'
      ],
      [
        '<a href="https://finance-ni.gov.uk/Property/Search">Property Search</a>',
        '<a href="/Property/Search">Property Search</a>'
      ],
      [
        '<a href="http://finance-ni.gov.uk/Property/Search">Property Search</a>',
        '<a href="/Property/Search">Property Search</a>'
      ],
      [
        '<a href="https://www.finance-ni.gov.uk/Property/Search">Property Search</a>',
        '<a href="/Property/Search">Property Search</a>'
      ],
      [
        '<a href="http://www.finance-ni.gov.uk/Property/Search">Property Search</a>',
        '<a href="/Property/Search">Property Search</a>'
      ],
      [
        '<p>This is a link test</p><a href="http://www.finance-ni.gov.uk/Property/Search">Property Search</a><span>Span text</span>',
        '<p>This is a link test</p><a href="/Property/Search">Property Search</a><span>Span text</span>'
      ],
      [
        '<a href="http://www.finance-ni.gov.uk/Property/Search" target="_blank">Property Search</a>',
        '<a href="/Property/Search" target="_blank">Property Search</a>'
      ],
      [
        '<a href="http://www.finance-ni.gov.uk/Property/Search?town=belfast" target="_blank">Property Search</a>',
        '<a href="/Property/Search?town=belfast" target="_blank">Property Search</a>'
      ],
      [
        '<a href="http://www.finance-ni.gov.uk" target="_blank">Department of Finance</a>',
        '<a href="http://www.finance-ni.gov.uk" target="_blank">Department of Finance</a>'
      ],
    ];
  }

}
