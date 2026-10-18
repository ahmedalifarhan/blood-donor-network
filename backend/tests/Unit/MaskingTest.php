<?php

use App\Support\Masking;

// Constitution II and AU-05: logs never hold full phone numbers or full IPs.

it('masks any run of 9 or more digits to first 4 and last 4', function () {
    expect(Masking::text('201012345678'))->toBe('2010****5678')
        ->and(Masking::text('call 01012345678 now'))->toBe('call 0101****5678 now')
        ->and(Masking::text('123456789'))->toBe('1234****6789');
});

it('leaves runs shorter than 9 digits alone', function () {
    expect(Masking::text('code 12345678'))->toBe('code 12345678')
        ->and(Masking::text('2026-10-19T10:15Z'))->toBe('2026-10-19T10:15Z');
});

it('masks strings inside nested context arrays', function () {
    $masked = Masking::context([
        'key' => 'AdminWhatsAppNumber',
        'old' => '201012345678',
        'nested' => ['phones' => ['01198765432', 'none']],
        'count' => 7,
    ]);

    expect($masked)->toBe([
        'key' => 'AdminWhatsAppNumber',
        'old' => '2010****5678',
        'nested' => ['phones' => ['0119****5432', 'none']],
        'count' => 7,
    ]);
});

it('masks IPv4 addresses to their /24 network', function () {
    expect(Masking::ip('41.234.12.7'))->toBe('41.234.12.0/24');
});

it('masks IPv6 addresses to their /48 network', function () {
    expect(Masking::ip('2001:db8:85a3:8d3:1319:8a2e:370:7348'))->toBe('2001:db8:85a3::/48');
});

it('returns an empty string for something that is not an IP', function () {
    expect(Masking::ip('not-an-ip'))->toBe('');
});

it('encodes masked context as JSON capped at 5,000 characters', function () {
    $json = Masking::json(['phone' => '201012345678', 'note' => 'عرب مطير']);
    expect($json)->toBe('{"phone":"2010****5678","note":"عرب مطير"}');

    $long = Masking::json(['text' => str_repeat('x', 6000)]);
    expect(mb_strlen($long))->toBe(5000)
        ->and(mb_substr($long, -1))->toBe('…');
});
