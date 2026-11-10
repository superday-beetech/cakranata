<?php

declare(strict_types=1);

if (! function_exists('bulan')) {
    /**
     * Nama bulan dalam bahasa Indonesia.
     */
    function bulan(int|string $bln): string
    {
        $months = [
            1  => 'Januari',
            2  => 'Februari',
            3  => 'Maret',
            4  => 'April',
            5  => 'Mei',
            6  => 'Juni',
            7  => 'Juli',
            8  => 'Agustus',
            9  => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return $months[(int) $bln] ?? '';
    }
}

if (! function_exists('short_bulan')) {
    /**
     * Nomor bulan dua digit.
     */
    function short_bulan(int|string $bln): string
    {
        $month = (int) $bln;

        return $month >= 1 && $month <= 12
            ? sprintf('%02d', $month)
            : '';
    }
}

if (! function_exists('medium_bulan')) {
    /**
     * Nama bulan singkat dalam bahasa Indonesia.
     */
    function medium_bulan(int|string $bln): string
    {
        $months = [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'Mei',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Ags',
            9 => 'Sep',
            10 => 'Okt',
            11 => 'Nov',
            12 => 'Des',
        ];

        return $months[(int) $bln] ?? '';
    }
}

if (! function_exists('indo_date_object')) {
    /**
     * Mengubah input tanggal menjadi DateTimeImmutable.
     *
     * Input tanggal tanpa timezone mengikuti timezone aplikasi CI4.
     * Null atau string kosong menghasilkan null.
     */
    function indo_date_object(
        mixed $tanggal,
        ?string $timezone = null
    ): ?\DateTimeImmutable {
        if ($tanggal === null || $tanggal === '') {
            return null;
        }

        if (
            ! is_string($tanggal) &&
            ! is_int($tanggal) &&
            ! $tanggal instanceof \DateTimeInterface
        ) {
            return null;
        }

        try {
            $tz = new \DateTimeZone(
                $timezone ?? (string) config('App')->appTimezone
            );

            if ($tanggal instanceof \DateTimeInterface) {
                return (new \DateTimeImmutable(
                    $tanggal->format('Y-m-d H:i:s.u'),
                    $tanggal->getTimezone()
                ))->setTimezone($tz);
            }

            if (is_int($tanggal)) {
                return (new \DateTimeImmutable('@' . $tanggal))
                    ->setTimezone($tz);
            }

            // Untuk format tanggal saja, hindari interpretasi relatif
            // seperti "tomorrow" atau "next Monday".
            if (! preg_match(
                '/\A\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?)?\z/',
                trim($tanggal)
            )) {
                return null;
            }

            $date = new \DateTimeImmutable($tanggal, $tz);

            // Pastikan tanggal kalender tidak dinormalisasi diam-diam.
            if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $tanggal)) {
                if ($date->format('Y-m-d') !== $tanggal) {
                    return null;
                }
            }

            return $date->setTimezone($tz);
        } catch (\Throwable) {
            return null;
        }
    }
}

if (! function_exists('date_indo')) {
    /**
     * Format: 27 Oktober 2026
     */
    function date_indo(
        mixed $tgl,
        ?string $timezone = null
    ): string {
        $date = indo_date_object($tgl, $timezone);

        return $date
            ? $date->format('d') . ' '
            . bulan($date->format('n')) . ' '
            . $date->format('Y')
            : '';
    }
}

if (! function_exists('tgl_indo')) {
    /**
     * Alias kompatibilitas.
     */
    function tgl_indo(
        mixed $tgl,
        ?string $timezone = null
    ): string {
        return date_indo($tgl, $timezone);
    }
}

if (! function_exists('shortdate_indo')) {
    /**
     * Format: 27/10/2026
     */
    function shortdate_indo(
        mixed $tgl,
        ?string $timezone = null
    ): string {
        $date = indo_date_object($tgl, $timezone);

        return $date ? $date->format('d/m/Y') : '';
    }
}

if (! function_exists('mediumdate_indo')) {
    /**
     * Format: 27-Okt-2026
     */
    function mediumdate_indo(
        mixed $tgl,
        ?string $timezone = null
    ): string {
        $date = indo_date_object($tgl, $timezone);

        return $date
            ? $date->format('d') . '-'
            . medium_bulan($date->format('n')) . '-'
            . $date->format('Y')
            : '';
    }
}

if (! function_exists('longdate_indo')) {
    /**
     * Format: Selasa, 27 Oktober 2026
     */
    function longdate_indo(
        mixed $tgl,
        ?string $timezone = null
    ): string {
        $date = indo_date_object($tgl, $timezone);

        if ($date === null) {
            return '';
        }

        $days = [
            'Sunday'    => 'Minggu',
            'Monday'    => 'Senin',
            'Tuesday'   => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday'  => 'Kamis',
            'Friday'    => 'Jumat',
            'Saturday'  => 'Sabtu',
        ];

        return ($days[$date->format('l')] ?? '')
            . ', ' . $date->format('d')
            . ' ' . bulan($date->format('n'))
            . ' ' . $date->format('Y');
    }
}
