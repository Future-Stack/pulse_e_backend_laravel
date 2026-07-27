<?php

namespace Tests\Unit;

use App\Support\AddressNormalizer;
use PHPUnit\Framework\TestCase;

class AddressNormalizerTest extends TestCase
{
    public function test_it_normalizes_common_street_abbreviations(): void
    {
        $this->assertSame(
            '123 main st',
            AddressNormalizer::normalize('123 Main Street')
        );

        $this->assertSame(
            '456 n park ave ste 200',
            AddressNormalizer::normalize('456 North Park Avenue, Suite 200')
        );
    }

    public function test_it_collapses_whitespace_and_punctuation(): void
    {
        $this->assertSame(
            '789 oak blvd',
            AddressNormalizer::normalize('789   Oak Boulevard.,')
        );
    }

    public function test_empty_input_normalizes_to_empty_string(): void
    {
        $this->assertSame('', AddressNormalizer::normalize(null));
        $this->assertSame('', AddressNormalizer::normalize(''));
    }

    public function test_similarity_is_high_for_equivalent_addresses(): void
    {
        $similarity = AddressNormalizer::similarity(
            '123 Main Street, San Francisco, CA',
            '123 Main St San Francisco CA'
        );

        $this->assertGreaterThan(0.9, $similarity);
    }

    public function test_similarity_is_zero_when_either_side_is_empty(): void
    {
        $this->assertSame(0.0, AddressNormalizer::similarity('123 Main St', null));
        $this->assertSame(0.0, AddressNormalizer::similarity('', ''));
    }
}
