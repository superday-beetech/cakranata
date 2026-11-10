<?php

declare(strict_types=1);

namespace App\Libraries;

use InvalidArgumentException;
use LengthException;

final class Terbilang
{
    private const MAX_INTEGER_DIGITS = 36;
    private const MAX_DECIMAL_DIGITS = 18;

    private const ONES = [
        0 => 'nol',
        1 => 'satu',
        2 => 'dua',
        3 => 'tiga',
        4 => 'empat',
        5 => 'lima',
        6 => 'enam',
        7 => 'tujuh',
        8 => 'delapan',
        9 => 'sembilan',
    ];

    private const SCALES = [
        '',
        'ribu',
        'juta',
        'miliar',
        'triliun',
        'kuadriliun',
        'kuintiliun',
        'sekstiliun',
        'septiliun',
        'oktiliun',
        'noniliun',
        'desiliun',
    ];

    private string $separator;

    private string $result = '';

    public function __construct(string|int $num = '', string $sep = ',')
    {
        $this->setSeparator($sep);

        if ((string) $num !== '') {
            $this->parse($num);
        }
    }

    /**
     * Mengatur pemisah desimal.
     *
     * ',' : 1.234,56
     * '.' : 1,234.56
     */
    public function setSeparator(string $sep): self
    {
        if (! in_array($sep, [',', '.'], true)) {
            throw new InvalidArgumentException(
                'Pemisah desimal harus berupa koma atau titik.'
            );
        }

        $this->separator = $sep;

        return $this;
    }

    /**
     * Mengubah angka menjadi terbilang.
     */
    public function parse(string|int $num = '', string $sep = ''): self
    {
        if ($sep !== '') {
            $this->setSeparator($sep);
        }

        $value = trim((string) $num);

        if ($value === '') {
            $this->result = '';

            return $this;
        }

        // Format input harus dinyatakan secara eksplisit.
        $decimalSeparator = $this->separator;
        $groupSeparator = $decimalSeparator === ',' ? '.' : ',';

        $integerPattern = '(?:\d+|\d{1,3}(?:'
            . preg_quote($groupSeparator, '/')
            . '\d{3})+)';

        $pattern = '/\A(-?)(' . $integerPattern . ')'
            . '(?:' . preg_quote($decimalSeparator, '/')
            . '(\d+))?\z/D';

        if (! preg_match($pattern, $value, $matches)) {
            throw new InvalidArgumentException(
                'Format angka tidak valid. Gunakan pemisah ribuan dan desimal yang konsisten.'
            );
        }

        $negative = ($matches[1] ?? '') === '-';
        $integer = str_replace($groupSeparator, '', $matches[2]);
        $fraction = $matches[3] ?? '';

        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;

        if (strlen($integer) > self::MAX_INTEGER_DIGITS) {
            throw new LengthException(
                'Jumlah digit bilangan bulat melebihi batas 36 digit.'
            );
        }

        if (strlen($fraction) > self::MAX_DECIMAL_DIGITS) {
            throw new LengthException(
                'Jumlah digit desimal melebihi batas 18 digit.'
            );
        }

        $words = [];

        if ($negative && preg_match('/[1-9]/', $integer . $fraction)) {
            $words[] = 'minus';
        }

        $words[] = $this->integerToWords($integer);

        if ($fraction !== '') {
            $words[] = 'koma';

            // Digit desimal dibaca satu per satu agar nol di depan
            // maupun nol di belakang tidak hilang.
            foreach (str_split($fraction) as $digit) {
                $words[] = self::ONES[(int) $digit];
            }
        }

        $this->result = implode(' ', $words);

        return $this;
    }

    /**
     * Mengambil hasil terbilang.
     */
    public function getResult(array $result = []): string
    {
        // Parameter array dipertahankan untuk kompatibilitas API lama.
        if ($result !== []) {
            return implode(' ', array_filter(
                $result,
                static fn($value): bool => is_string($value) && $value !== ''
            ));
        }

        return $this->result;
    }

    /**
     * Alias untuk mendapatkan hasil terbilang.
     */
    public function result(): string
    {
        return $this->result;
    }

    /**
     * Mengubah string menjadi digit saja.
     * Dipertahankan untuk kompatibilitas kode lama.
     */
    public function filter_num(string $num = ''): string
    {
        return preg_replace('/\D+/', '', $num) ?? '';
    }

    /**
     * Mengeja digit satu per satu.
     * Dipertahankan untuk kompatibilitas kode lama.
     */
    public function spell(string $num = ''): self
    {
        if ($num !== '' && ! ctype_digit($num)) {
            throw new InvalidArgumentException(
                'Input spell() hanya boleh berisi digit.'
            );
        }

        $words = [];

        foreach (str_split($num) as $digit) {
            $words[] = self::ONES[(int) $digit];
        }

        $this->result = implode(' ', $words);

        return $this;
    }

    /**
     * Mengubah bilangan bulat menjadi kata.
     */
    private function integerToWords(string $number): string
    {
        $number = ltrim($number, '0');

        if ($number === '') {
            return 'nol';
        }

        $groups = array_reverse(str_split(
            str_pad($number, (int) ceil(strlen($number) / 3) * 3, '0', STR_PAD_LEFT),
            3
        ));

        $parts = [];

        foreach ($groups as $index => $group) {
            $value = (int) $group;

            if ($value === 0) {
                continue;
            }

            if (! isset(self::SCALES[$index])) {
                throw new LengthException('Skala bilangan tidak didukung.');
            }

            $words = $this->threeDigitsToWords($value);

            // Khusus 1.000 menjadi "seribu", bukan "satu ribu".
            if ($index === 1 && $value === 1) {
                $parts[] = 'seribu';
                continue;
            }

            $scale = self::SCALES[$index];

            $parts[] = trim($words . ' ' . $scale);
        }

        return implode(' ', array_reverse($parts));
    }

    /**
     * Mengeja bilangan 0 sampai 999.
     */
    private function threeDigitsToWords(int $number): string
    {
        if ($number === 0) {
            return '';
        }

        if ($number < 10) {
            return self::ONES[$number];
        }

        if ($number === 10) {
            return 'sepuluh';
        }

        if ($number === 11) {
            return 'sebelas';
        }

        if ($number < 20) {
            return self::ONES[$number % 10] . ' belas';
        }

        if ($number < 100) {
            $tens = intdiv($number, 10);
            $ones = $number % 10;

            return self::ONES[$tens] . ' puluh'
                . ($ones > 0 ? ' ' . self::ONES[$ones] : '');
        }

        $hundreds = intdiv($number, 100);
        $remainder = $number % 100;

        $words = $hundreds === 1
            ? 'seratus'
            : self::ONES[$hundreds] . ' ratus';

        if ($remainder > 0) {
            $words .= ' ' . $this->threeDigitsToWords($remainder);
        }

        return $words;
    }
}
