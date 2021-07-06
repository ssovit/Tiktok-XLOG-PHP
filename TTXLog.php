<?php
class XLog
{
    private $HEX_CHAR = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f'];

    public function __construct()
    {
    }

    public function decode($decode)
    {
        $resultLen    = 0;
        $last         = '78468ec4';
        $s            = substr($this->bytesToHexFun($decode), 2);
        $stringBuffer = '';
        $strList      = [];
        for (
            $i = 0;
            $i < strlen($s) / 16 - 1;
            $i++
        ) {
            $input     = substr($s, $i * 16, ($i * 16 + 16) - ($i * 16));
            $strList[] = $input;
        }
        $strList[] = $last;
        $times     = 56;
        for (
            $i = 0;
            $i < count($strList) - 1;
            $i++
        ) {
            $calculate = $this->calculate($strList[$i], $times);
            if (0 == $i) {
                $tmp = $last . '744c0000';
                for ($j = 0; $j < 8; $j++) {
                    $xor = $this->doxor(substr($calculate, ($j * 2), ($j * 2 + 2) - ($j * 2)), substr($tmp, $j * 2, ($j * 2 + 2) - ($j * 2)));
                    if (strlen($xor) < 2) {
                        $xor = '0' . $xor;
                    }
                    $stringBuffer .= $xor;
                }
            }
            if ($i >= 1) {
                $tmp = $strList[$i - 1];
                for ($j = 0; $j < 8; $j++) {
                    $xor = $this->doxor(substr($calculate, ($j * 2), ($j * 2 + 2) - ($j * 2)), substr($tmp, $j * 2, ($j * 2 + 2) - ($j * 2)));
                    if (strlen($xor) < 2) {
                        $xor = '0' . $xor;
                    }
                    $stringBuffer .= $xor;
                }
            }
        }
        $string    = $stringBuffer;
        $bytes     = $this->hexToByteArray($string);
        $count     = $bytes[0] & 7;
        $resultLen = count($decode) - 13 - $count;
        $count     = $count % 4;
        if (0 == $count) {
            $count = 4;
        }
        $result = array_fill(0, $resultLen, 0);
        $result = $this->arraycopy($bytes, $count, $result, 0, $resultLen);
        $result = $this->changeToLongArray($result);
        return $this->bytearray_decode($result);
    }

    public function decodeHex($hex)
    {
        $decode = $this->hexToByteArray($hex);
        return $this->decode($decode);
    }

    public function encode($input)
    {
        $inputStart = $this->changeLongArrayTobytes($this->bytearray($input));
        $sourceLen  = count($inputStart);
        $fillCount  = 4 - $sourceLen % 4;
        $fillNum    = 8 - $sourceLen % 8;
        $isAdd      = 4;
        if ($sourceLen % 8 >= 4) {
            $isAdd = 0;
        }
        if (8 == $fillNum) {
            $isAdd   = -4;
            $fillNum = 0;
        }
        $bytes    = array_fill(0, $sourceLen + $fillNum + 8, 0);
        $eorByte  = [0x78, 0x46, 0x8e, 0xc4, 0x74, 0x4c, 0x00, 0x00];
        $bytes[0] = 0x80 | $fillNum;
        $bytes[1] = 0x30;
        $bytes[2] = 0x22;
        $bytes[3] = 0x24;
        $result   = '02';
        $bytes    = $this->arraycopy($inputStart, 0, $bytes, $fillCount, count($inputStart));
        $bytes    = $this->changeLongArrayTobytes($bytes);
        for (
            $i = 0;
            $i < count($bytes) / 8;
            $i++
        ) {
            $sb = '';
            for ($j = 0; $j < 8; $j++) {
                $r1 = $bytes[$j + 8 * $i];
                $r2 = $eorByte[$j];
                if ($r2 < 0) {
                    $r2 = $r2 + 256;
                }
                if ($r1 < 0) {
                    $r1 = $r1 + 256;
                }
                $tmp = $r1 ^ $r2;
                if (0 == $tmp) {
                    $sb .= '00';
                } else {
                    $str = dechex($tmp);
                    if (strlen($str) < 2) {
                        $sb .= '0';
                    }
                    $sb .= $str;
                }
            }
            $times = $this->getHandleCount('78468ec4');
            $times = 56;
            $s     = $this->calculateRev($sb, $times);
            for ($z = 0; $z < 8; $z++) {
                $substring   = substr($s, (2 * $z), (2 * $z + 2) - $this->makeSafe(2 * $z));
                $eorByte[$z] = $this->makeSafe($this->parseLong($substring, 16));
            }
            $result .= $s;
        }
        $result .= '78468ec4';
        $bytes1 = $this->hexToByteArray($result);
        return $this->bytearray_decode($bytes1);
    }

    private function arraycopy($src, $startPos = 0, $dest = [], $destPos = 0, $length = false)
    {
        $final = [];
        if (false == $length) {
            $length = count($src);
        }
        $final = array_merge($final, array_slice($dest, 0, $destPos));
        $final = array_merge($final, array_slice($src, $startPos, $length));
        $final = array_merge($final, array_slice($dest, $length + $destPos));
        return $final;
    }

    private function bytearray($string)
    {
        return array_values(unpack('C*', $string));
    }

    private function bytearray_decode($byteArray)
    {
        $chars = array_map('chr', $byteArray);
        return join($chars);
    }

    private function bytesToHexFun($bytes)
    {
        $buf   = array_fill(0, count($bytes) * 2, ' ');
        $a     = 0;
        $index = 0;
        foreach ($bytes as $b) {
            if ($b < 0) {
                $a = 256 + $b;
            } else {
                $a = $b;
            }
            $buf[$index++] = $this->HEX_CHAR[(int) ($a / 16)];
            $buf[$index++] = $this->HEX_CHAR[$a % 16];
        }
        return join('', $buf);
    }

    private function calculate($input, $times)
    {
        if (strlen($input) != 16) {
            return '';
        }
        $s36  = 0;
        $s40  = 0;
        $s108 = -1073747680;
        $s136 = $this->makeSafe(-1640531527 * $times);
        $str1 = substr($input, 0, 8 - 0);
        $str2 = substr($input, 8, 16 - 8);
        $s140 = $this->makeSafe($this->parseLong(substr($input, 0, 8 - 0), 16));
        $s144 = $this->makeSafe($this->parseLong(substr($input, 8, 16 - 8), 16));
        $r0   = 1180082309;
        $r2   = 1180082309;
        $r4   = 1180082309;
        $r6   = -1436101968;
        $r5   = $s108;
        $r12  = 0;
        for ($i = 0; $i < $times; $i++) {
            $r0     = $s140;
            $r2     = $s140;
            $r4     = $s140;
            $r6     = $s136;
            $r5     = $s108;
            $string = $this->stablizieBinary(decbin($r6 >> 11));
            if (strlen($string) < 3) {
                $string = '0';
            } else {
                $string = substr($string, strlen($string) - 2);
            }
            $r6 = intval($string, 2);

            $r0   = ($this->unsignedRightShift($r2, 5) ^ $r0 << 4) + $r4;
            $r5   = $this->getShifting($r5 + ($r6 << 2));
            $r6   = 1640531527;
            $r2   = $this->makeSafe($s136 + $r5);
            $r5   = $s136;
            $r0   = $this->makeSafe($r0 ^ $r2);
            $r2   = $s108;
            $r6   = $this->makeSafe($r6 + $r5);
            $r4   = $this->makeSafe($s144 - $r0);
            $r5   = $this->makeSafe($r6 & 3);
            $r0   = $this->makeSafe($r4 << 4);
            $r2   = $this->getShifting($r2 + ($r5 << 2));
            $r0   = $this->makeSafe(($r0 ^ $this->unsignedRightShift($r4, 5)) + $r4);
            $r2   = $this->makeSafe($r2 + $r6);
            $r0   = $this->makeSafe($r0 ^ $r2);
            $s140 = $this->makeSafe($s140 - $r0);
            $s136 = $r6;
            $s144 = $r4;
        }
        $str140 = $this->stablizeHex((strlen($this->toHexString($s140)) == 7 ? '0' : '') . $this->toHexString($s140));
        $str144 = $this->stablizeHex((strlen($this->toHexString($s144)) == 7 ? '0' : '') . $this->toHexString($s144));
        if (strlen($str140) < 8) {
            $count = 8 - strlen($str140);
            for ($i = 0; $i < $count; $i++) {
                $str140 = '0' . $str140;
            }
        }
        if (strlen($str144) < 8) {
            $count = 8 - strlen($str144);
            for ($i = 0; $i < $count; $i++) {
                $str144 = '0' . $str144;
            }
        }
        return $str140 . $str144;
    }

    private function calculateRev($input, $times)
    {
        $r12  = 0;
        $s108 = -1073747680;
        $s136 = 0;
        $s140 = $this->makeSafe($this->parseLong(substr($input, 0, 8 - 0), 16));
        $s144 = $this->makeSafe($this->parseLong(substr($input, 8, 16 - 8), 16));
        for ($i = 0; $i < $times; $i++) {
            $r2     = $s108;
            $r6     = $s136;
            $r4     = $s144;
            $r5     = $r6 & 3;
            $r0     = $this->makeSafe($r4 << 4);
            $r2     = $this->getShifting($r2 + ($r5 << 2));
            $r0     = $this->makeSafe(($r0 ^ $this->unsignedRightShift($r4, 5)) + $r4);
            $r2     = $this->makeSafe($r2 + $r6);
            $r0     = $this->makeSafe($r0 ^ $r2);
            $s140   = $this->makeSafe($s140 + $r0);
            $s136   = $this->makeSafe($s136 - 0x61c88647);
            $r5     = $s108;
            $r4     = $s140;
            $r2     = $s140;
            $r0     = $s140;
            $r6     = $s136;
            $string = $this->stablizieBinary(decbin($r6 >> 11));
            if (strlen($string) < 3) {
                $string = '0';
            } else {
                $string = substr($string, strlen($string) - 2);
            }
            $r6   = intval($string, 2);
            $r0   = $this->makeSafe($this->makeSafe($this->unsignedRightShift($r2, 5) ^ $this->makeSafe($r0 << 4)) + $r4);
            $r5   = $this->getShifting($r5 + ($r6 << 2));
            $r2   = $this->makeSafe($s136 + $r5);
            $r0   = $this->makeSafe($r0 ^ $r2);
            $s144 = $this->makeSafe($s144 + $r0);
        }
        $str140 = $this->stablizeHex((strlen($this->toHexString($s140)) == 7 ? '0' : '') . $this->toHexString($s140));
        $str144 = $this->stablizeHex((strlen($this->toHexString($s144)) == 7 ? '0' : '') . $this->toHexString($s144));
        if (strlen($str140) < 8) {
            $count = 8 - strlen($str140);
            for ($i = 0; $i < $count; $i++) {
                $str140 = '0' . $str140;
            }
        }
        if (strlen($str144) < 8) {
            $count = 8 - strlen($str144);
            for ($i = 0; $i < $count; $i++) {
                $str144 = '0' . $str144;
            }
        }
        return $str140 . $str144;
    }

    private function changeLongArrayTobytes($arrays)
    {
        $result = [];
        for (
            $i = 0;
            $i < count($arrays);
            $i++
        ) {
            if ($arrays[$i] > 127) {
                $result[$i] = $arrays[$i] - 256;
            } else {
                $result[$i] = $arrays[$i];
            }
        }
        return $result;
    }

    private function changeToLongArray($bytes)
    {
        $result = [];
        for (
            $i = 0;
            $i < count($bytes);
            $i++
        ) {
            if ($bytes[$i] < 0) {
                $result[$i] = $bytes[$i] + 256;
            } else {
                $result[$i] = $bytes[$i];
            }
        }
        return $result;
    }

    private function doxor($strHex_X, $strHex_Y)
    {
        $anotherBinary = decbin(hexdec($strHex_X));
        $thisBinary    = decbin(hexdec($strHex_Y));
        $result        = '';
        if (strlen($anotherBinary) != 8) {
            for (
                $i = strlen($anotherBinary);
                $i < 8;
                $i++
            ) {
                $anotherBinary = '0' . $anotherBinary;
            }
        }
        if (strlen($thisBinary) != 8) {
            for (
                $i = strlen($thisBinary);
                $i < 8;
                $i++
            ) {
                $thisBinary = '0' . $thisBinary;
            }
        }
        for (
            $i = 0;
            $i < strlen($anotherBinary);
            $i++
        ) {
            if ($thisBinary[$i] == $anotherBinary[$i]) {
                $result .= '0';
            } else {
                $result .= '1';
            }
        }
        return dechex(intval($result, 2));
    }

    private function getHandleCount($hex)
    {
        $reverse = $this->reverse($hex);
        $r1      = $this->makeSafe($this->parseLong($reverse, 16));
        $r0      = $this->makeSafe(-858993459);
        $r2      = $this->getUmullHigh($r1, $r0);
        $s58     = $r0;
        $r2      = $r2 >> 2;
        $r2      = $r2 + ($r2 << 2);
        $r1      = $r1 - $r2;
        $r2      = 32;
        $r1      = $r2 + ($r1 << 3);
        return $r1;
    }

    private function getShifting($point)
    {
        switch ($point) {
            case -1073747680:
                return 1198522846;
            case -1073747676:
                return -87105875;
            case -1073747672:
                return 808464432;
            case -1073747668:
                return 959787575;
        }
        return 0;
    }

    private function getUmullHigh($r0, $r2)
    {
        $n1     = $this->parseLong($this->stablizeHex(dechex($r0)), 16);
        $n2     = $this->parseLong($this->stablizeHex(dechex($r2)), 16);
        $result = $this->makeSafe($n1 * $n2);
        $string = dechex($result);
        $string = substr($string, 0, strlen($string) - 8 - 0);
        return $this->makeSafe(hexdec($string));
    }

    private function hexToByteArray($inHex)
    {
        $hexlen = strlen($inHex);
        if ($hexlen % 2 == 1) {
            $hexlen++;
            $result = array_fill(0, ((int) ($hexlen / 2)), NULL);
            $inHex  = '0' . $inHex;
        } else {
            $result = array_fill(0, ((int) ($hexlen / 2)), NULL);
        }
        $j = 0;
        for ($i = 0; $i < $hexlen; $i += 2) {
            $result[$j] = hexdec(substr($inHex, $i, $i + 2 - $i));
            $j++;
        }
        return $this->changeLongArrayTobytes($result);
    }

    private function makeSafe($num)
    {
        $i = pack('i', $num);
        $i = unpack('i', $i)[1];
        return $i;
    }

    private function parseLong($str, $radix = 10)
    {
        return (int) base_convert($str, $radix, 10);
    }

    private function reverse($hex)
    {
        return substr($hex, 6, 8 - 6) . substr($hex, 4, 6 - 4) . substr($hex, 2, 4 - 2) . substr($hex, 0, 2 - 0);
    }

    private function stablizeHex($val)
    {
        $final = $val;
        if (strlen($val) == 16) {
            $final = substr($val, 8);
        }
        return $final;
    }

    private function stablizieBinary($val)
    {
        $final = $val;
        if (strlen($val) == 64) {
            $final = substr($val, 32);
        }
        return $final;
    }

    private function toHexString($hex)
    {
        return dechex($hex);
    }

    private function unsignedRightShift($a, $b)
    {
        if ($b >= 32 || $b < -32) {
            $m = (int) ($b / 32);
            $b = $b - ($m * 32);
        }
        if ($b < 0) {
            $b = 32 + $b;
        }
        if (0 == $b) {
            return (($a >> 1) & 0x7fffffff) * 2 + (($a >> $b) & 1);
        }
        if ($a < 0) {
            $a = ($a >> 1);
            $a &= 0x7fffffff;
            $a |= 0x40000000;
            $a = ($a >> ($b - 1));
        } else {
            $a = ($a >> $b);
        }
        return $a;
    }
}
