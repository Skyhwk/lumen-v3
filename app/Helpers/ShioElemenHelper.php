<?php

namespace App\Helpers;

class ShioElemenHelper
{
    private const SHIO_VALUES = [
        'Tikus', 'Kerbau', 'Macan', 'Kelinci', 'Naga', 'Ular',
        'Kuda', 'Kambing', 'Monyet', 'Ayam', 'Anjing', 'Babi',
    ];

    private const ELEMENT_BY_LAST_DIGIT = [
        0 => 'Logam', 1 => 'Logam', 2 => 'Air', 3 => 'Air', 4 => 'Kayu',
        5 => 'Kayu', 6 => 'Api', 7 => 'Api', 8 => 'Bumi', 9 => 'Bumi',
    ];

    private const BASE_ZODIAC_YEAR = 1948;

    private const CHINESE_NEW_YEAR = [
        1940 => [2, 8], 1941 => [1, 27], 1942 => [2, 15], 1943 => [2, 5], 1944 => [1, 25], 1945 => [2, 13], 1946 => [2, 2], 1947 => [1, 22], 1948 => [2, 10], 1949 => [1, 29],
        1950 => [2, 17], 1951 => [2, 6], 1952 => [1, 27], 1953 => [2, 14], 1954 => [2, 3], 1955 => [1, 24], 1956 => [2, 12], 1957 => [1, 31], 1958 => [2, 18], 1959 => [2, 8],
        1960 => [1, 28], 1961 => [2, 15], 1962 => [2, 5], 1963 => [1, 25], 1964 => [2, 13], 1965 => [2, 2], 1966 => [1, 21], 1967 => [2, 9], 1968 => [1, 30], 1969 => [2, 17],
        1970 => [2, 6], 1971 => [1, 27], 1972 => [2, 15], 1973 => [2, 3], 1974 => [1, 23], 1975 => [2, 11], 1976 => [1, 31], 1977 => [2, 18], 1978 => [2, 7], 1979 => [1, 28],
        1980 => [2, 16], 1981 => [2, 5], 1982 => [1, 25], 1983 => [2, 13], 1984 => [2, 2], 1985 => [2, 20], 1986 => [2, 9], 1987 => [1, 29], 1988 => [2, 17], 1989 => [2, 6],
        1990 => [1, 27], 1991 => [2, 15], 1992 => [2, 4], 1993 => [1, 23], 1994 => [2, 10], 1995 => [1, 31], 1996 => [2, 19], 1997 => [2, 7], 1998 => [1, 28], 1999 => [2, 16],
        2000 => [2, 5], 2001 => [1, 24], 2002 => [2, 12], 2003 => [2, 1], 2004 => [1, 22], 2005 => [2, 9], 2006 => [1, 29], 2007 => [2, 18], 2008 => [2, 7], 2009 => [1, 26],
        2010 => [2, 14], 2011 => [2, 3], 2012 => [1, 23], 2013 => [2, 10], 2014 => [1, 31], 2015 => [2, 19], 2016 => [2, 8], 2017 => [1, 28], 2018 => [2, 16], 2019 => [2, 5],
        2020 => [1, 25], 2021 => [2, 12], 2022 => [2, 1], 2023 => [1, 22], 2024 => [2, 10], 2025 => [1, 29], 2026 => [2, 17], 2027 => [2, 6], 2028 => [1, 26], 2029 => [2, 13],
        2030 => [2, 3], 2031 => [1, 23], 2032 => [2, 11], 2033 => [1, 31], 2034 => [2, 19], 2035 => [2, 8], 2036 => [1, 28], 2037 => [2, 15], 2038 => [2, 4], 2039 => [1, 24],
        2040 => [2, 12], 2041 => [2, 1], 2042 => [1, 22], 2043 => [2, 10], 2044 => [1, 30], 2045 => [2, 17], 2046 => [2, 6], 2047 => [1, 26], 2048 => [2, 14], 2049 => [2, 2],
        2050 => [1, 23],
    ];

    public static function parseBirthDateParts(?string $dateInput): ?array
    {
        if (!$dateInput) {
            return null;
        }

        $str = trim($dateInput);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $str, $matches)) {
            return [
                'year' => (int) $matches[1],
                'month' => (int) $matches[2],
                'day' => (int) $matches[3],
            ];
        }

        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $str, $matches)) {
            return [
                'year' => (int) $matches[3],
                'month' => (int) $matches[2],
                'day' => (int) $matches[1],
            ];
        }

        try {
            $date = new \DateTime($str);
            return [
                'year' => (int) $date->format('Y'),
                'month' => (int) $date->format('m'),
                'day' => (int) $date->format('d'),
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getZodiacYear(int $year, int $month, int $day): int
    {
        if (!isset(self::CHINESE_NEW_YEAR[$year])) {
            return $year;
        }

        [$cnyMonth, $cnyDay] = self::CHINESE_NEW_YEAR[$year];
        if ($month < $cnyMonth || ($month === $cnyMonth && $day < $cnyDay)) {
            return $year - 1;
        }

        return $year;
    }

    public static function getShioFromBirthDate(?string $dateInput): ?string
    {
        $parts = self::parseBirthDateParts($dateInput);
        if (!$parts) {
            return null;
        }

        $zodiacYear = self::getZodiacYear($parts['year'], $parts['month'], $parts['day']);
        $index = (($zodiacYear - self::BASE_ZODIAC_YEAR) % 12 + 12) % 12;

        return self::SHIO_VALUES[$index] ?? null;
    }

    public static function getElemenFromBirthDate(?string $dateInput): ?string
    {
        $parts = self::parseBirthDateParts($dateInput);
        if (!$parts) {
            return null;
        }

        $zodiacYear = self::getZodiacYear($parts['year'], $parts['month'], $parts['day']);
        $lastDigit = abs($zodiacYear) % 10;

        return self::ELEMENT_BY_LAST_DIGIT[$lastDigit] ?? null;
    }

    public static function resolve(?string $birthDate, ?string $storedShio = null, ?string $storedElemen = null): array
    {
        $shio = self::getShioFromBirthDate($birthDate) ?: $storedShio;
        $elemen = self::getElemenFromBirthDate($birthDate) ?: $storedElemen;

        return [
            'shio' => $shio,
            'elemen' => $elemen,
        ];
    }
}
