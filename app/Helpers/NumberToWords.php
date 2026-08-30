<?php
// app/Helpers/NumberToWords.php

if (!function_exists('numberToWordsINR')) {
    function numberToWordsINR($number) {
        $number = (int)round($number); // Round to nearest integer
        
        $words = array(
            0 => 'Zero',
            1 => 'One',
            2 => 'Two',
            3 => 'Three',
            4 => 'Four',
            5 => 'Five',
            6 => 'Six',
            7 => 'Seven',
            8 => 'Eight',
            9 => 'Nine',
            10 => 'Ten',
            11 => 'Eleven',
            12 => 'Twelve',
            13 => 'Thirteen',
            14 => 'Fourteen',
            15 => 'Fifteen',
            16 => 'Sixteen',
            17 => 'Seventeen',
            18 => 'Eighteen',
            19 => 'Nineteen',
            20 => 'Twenty',
            30 => 'Thirty',
            40 => 'Forty',
            50 => 'Fifty',
            60 => 'Sixty',
            70 => 'Seventy',
            80 => 'Eighty',
            90 => 'Ninety'
        );

        if ($number < 0) {
            return 'Minus ' . numberToWordsINR(abs($number));
        }

        if ($number < 21) {
            return $words[$number];
        }

        if ($number < 100) {
            $tens = $words[10 * floor($number / 10)];
            $units = $number % 10;
            return $tens . ($units ? ' ' . $words[$units] : '');
        }

        if ($number < 1000) {
            $hundreds = $words[floor($number / 100)] . ' Hundred';
            $remainder = $number % 100;
            return $hundreds . ($remainder ? ' ' . numberToWordsINR($remainder) : '');
        }

        if ($number < 100000) {
            $thousands = numberToWordsINR(floor($number / 1000)) . ' Thousand';
            $remainder = $number % 1000;
            return $thousands . ($remainder ? ' ' . numberToWordsINR($remainder) : '');
        }

        if ($number < 10000000) {
            $lakhs = numberToWordsINR(floor($number / 100000)) . ' Lakh';
            $remainder = $number % 100000;
            return $lakhs . ($remainder ? ' ' . numberToWordsINR($remainder) : '');
        }

        $crores = numberToWordsINR(floor($number / 10000000)) . ' Crore';
        $remainder = $number % 10000000;
        return $crores . ($remainder ? ' ' . numberToWordsINR($remainder) : '');
    }
}

// Register as a Blade directive in AppServiceProvider.php
// In the boot method: Blade::directive('numberToWords', function ($expression) { return "<?php echo numberToWordsINR($expression); ?>"; });