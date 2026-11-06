<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\LeadManager\Services;

use Espo\Modules\LeadManager\Services\PhoneFormatterService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneFormatterServiceTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function phones(): iterable
    {
        yield 'null' => [null, ''];
        yield 'empty' => ['', ''];
        yield 'belgian mobile' => ['0470 12 34 56', '+32470123456'];
        yield 'belgian mobile with dots' => ['0470.12.34.56', '+32470123456'];
        yield 'belgian landline' => ['09/123.45.67', '+3291234567'];
        yield 'already international' => ['+32 470 12 34 56', '+32470123456'];
        yield 'dutch international' => ['+31 6 12345678', '+31612345678'];
        yield '00 prefix' => ['0032470123456', '+32470123456'];
        yield 'without leading zero' => ['470123456', '+32470123456'];
        yield 'too long with plus' => ['+1234567890123456', ''];
        yield 'too long with 00' => ['001234567890123456', ''];
        yield 'too long with 0' => ['01234567890123456', ''];
        // Without a prefix an invalid number is returned unchanged, so the input is not lost.
        yield 'too long without prefix keeps input' => ['1234567890123456', '1234567890123456'];
        // Probably a bug: with all non-digits stripped only the country code remains, which passes the E.164 check.
        yield 'letters only becomes country code' => ['geen nummer', '+32'];
        yield 'plus only is invalid' => ['+', ''];
    }

    #[DataProvider('phones')]
    public function testFormat(?string $input, string $expected): void
    {
        self::assertSame($expected, (new PhoneFormatterService())->format($input));
    }

    public function testFormatWithOtherCountryCode(): void
    {
        self::assertSame('+31612345678', (new PhoneFormatterService())->format('0612345678', '31'));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function belgianNumbers(): iterable
    {
        yield 'mobile' => ['+32470123456', true];
        yield 'mobile with spaces' => ['+32 470 12 34 56', true];
        yield 'landline 8 digits' => ['+3291234567', true];
        yield 'landline 9 digits' => ['+32212345678', true];
        yield 'national format is not accepted' => ['0470123456', false];
        yield 'dutch' => ['+31612345678', false];
        yield 'too short' => ['+32123456', false];
        yield 'starts with zero after code' => ['+320470123456', false];
    }

    #[DataProvider('belgianNumbers')]
    public function testIsValidBelgianNumber(string $input, bool $expected): void
    {
        self::assertSame($expected, (new PhoneFormatterService())->isValidBelgianNumber($input));
    }

    public function testFormatMultiple(): void
    {
        $service = new PhoneFormatterService();

        self::assertSame('', $service->formatMultiple(null));
        self::assertSame('', $service->formatMultiple(''));
        self::assertSame(
            '+32470123456; +3291234567; +31612345678',
            $service->formatMultiple("0470 12 34 56, 09/123.45.67\n+31 6 12345678"),
        );
    }

    public function testFormatMultipleDropsInvalidNumbersButKeepsUnprefixedInput(): void
    {
        self::assertSame(
            '+32470123456; 1234567890123456',
            (new PhoneFormatterService())->formatMultiple('0470123456;; +1234567890123456; 1234567890123456'),
        );
    }
}
