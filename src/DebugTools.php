<?php

declare(strict_types=1);

namespace unreal4u\MQTT;

use function base64_encode;
use function base_convert;
use function chr;
use function ord;
use function sprintf;
use function strlen;
use function substr;

/**
 * Collection of function that have proven useful while debugging issues and creating unit tests
 * @package unreal4u\MQTT
 */
final class DebugTools
{
    /**
     * Handy debugging function
     *
     * @param string $rawString
     * @return string
     */
    public static function convertToBinaryRepresentation(string $rawString): string
    {
        $out = '';
        $strLength = strlen($rawString);
        for ($a = 0; $a < $strLength; $a++) {
            $dec = ord($rawString[$a]); //determine symbol ASCII-code
            //convert to binary representation and add leading zeros
            $bin = sprintf('%08d', base_convert((string)$dec, 10, 2));
            $out .= $bin;
        }

        return $out;
    }

    /**
     * Handy debugging function
     *
     * @param string $binaryRepresentation
     * @param bool $applyBase64
     * @return string
     */
    public static function convertBinaryToString(string $binaryRepresentation, bool $applyBase64 = false): string
    {
        $output = '';
        $binaryStringLength = strlen($binaryRepresentation);
        for ($i = 0; $i < $binaryStringLength; $i += 8) {
            $output .= chr((int)base_convert(substr($binaryRepresentation, $i, 8), 2, 10));
        }

        if ($applyBase64 === true) {
            return base64_encode($output);
        }

        return $output;
    }
}
