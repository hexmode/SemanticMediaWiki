<?php

namespace SMW\Tests\Unit;

use SMW\NamespaceUriFinder;
use SMW\Tests\Unit\PHPUnitCompat;

/**
 * @covers \SMW\NamespaceUriFinder
 * @group semantic-mediawiki
 *
 * @license GNU GPL v2+
 * @since 2.4
 *
 * @author mwjames
 */
class NamespaceUriFinderTest extends \PHPUnit_Framework_TestCase {

	use PHPUnitCompat;

	/**
	 * @dataProvider namespaceProvider
	 */
	public function testGetUriForNamespaceKey( $key, $expected ) {
		$this->assertInternalType(
			$expected,
			NamespaceUriFinder::getUri( $key )
		);
	}

	public function namespaceProvider() {

		$provider[] = [
			'Foo',
			'boolean'
		];

		$ns = [
			'owl', 'rdf', 'rdfs', 'swivt', 'xsd', 'skos', 'foaf', 'dc',
			'OWL'
		];

		foreach ( $ns as $key ) {
			$provider[] = [
				$key,
				'string'
			];
		}

		return $provider;
	}

}
