<?php

namespace App\Services\Naming;

/**
 * Suggest an enterprise detection code prefix based on the enterprise name.
 *
 * Heuristics-only, no external dependencies:
 * - Strip company suffixes / brackets / punctuation
 * - Remove common stopwords (industry/functional words)
 * - Prefer domain keywords (蜂/蜜/蜂业/蜂卫/蜂安/蜂博士/蜜源/丰蜜/蜂护 等)
 * - Take pinyin initials (A-Z), fallback to ASCII letters/digits when present
 * - Enforce length 2..8 (hard limit 16)
 */
class EnterprisePrefixSuggester
{
    /** @var string[] */
    protected array $companySuffixes = [
        '有限责任公司', '股份有限公司', '有限公司', '集团', '控股', '公司', '有限', '股份',
    ];

    /** @var string[] */
    protected array $stopwords = [
        '科技','信息','技术','生物','农业','生态','发展','贸易','实业','投资','管理','工程','设备','电子','网络','国际','传媒','广告','服务','产业',
        '研究院','研究所','中心','联合体','联盟','研究','学院','平台','基地',
        '（','）','(',')','·','－','—','-','_','&','/','\\','.', '　', ' ' , '　',
    ];

    /** @var string[] */
    protected array $domainBoost = [
        '蜂','蜜','蜂蜜','蜂业','蜂卫','蜂安','蜂博士','蜂源','蜜源','蜂族','蜂护','丰蜜',
    ];

    public function suggest(string $name, int $min = 2, int $max = 8): string
    {
        $clean = $this->normalize($name);
        $clean = $this->stripBrackets($clean);
        $clean = $this->stripCompanySuffix($clean);

        $tokens = $this->tokenize($clean);
        $tokens = $this->filterStopwords($tokens);

        if (empty($tokens)) {
            // fallback: make initials from original name
            $initials = $this->initials($this->mbSubstrSafe($clean, 0, 8));
            return $this->enforceLength($initials, $min, $max);
        }

        // Choose up to 2 brand tokens, with domain boost first
        $preferred = $this->pickBrandTokens($tokens);
        $base = implode('', $preferred);
        $initials = $this->initials($base);

        if (strlen($initials) < $min && count($tokens) > count($preferred)) {
            // add next token
            $extra = array_slice($tokens, count($preferred), 1);
            $initials = $this->initials($base . implode('', $extra));
        }

        if ($initials === '') {
            $initials = $this->initials($this->mbSubstrSafe($clean, 0, 8));
        }

        return $this->enforceLength($initials, $min, $max);
    }

    protected function normalize(string $s): string
    {
        $s = trim($s);
        // unify spaces
        $s = preg_replace('/\s+/u', '', $s) ?? $s;
        // remove special dots etc.
        return $s;
    }

    protected function stripBrackets(string $s): string
    {
        // remove content in （...） and (...)
        $s = preg_replace('/（[^）]*）/u', '', $s) ?? $s;
        $s = preg_replace('/\([^\)]*\)/u', '', $s) ?? $s;
        return $s;
    }

    protected function stripCompanySuffix(string $s): string
    {
        foreach ($this->companySuffixes as $suffix) {
            if (str_ends_with($s, $suffix)) {
                $s = mb_substr($s, 0, mb_strlen($s) - mb_strlen($suffix));
                break;
            }
        }
        return $s;
    }

    /**
     * @return array<int,string>
     */
    protected function tokenize(string $s): array
    {
        // Split into tokens: sequences of CJK or Latin/digits
        $tokens = [];
        $len = mb_strlen($s);
        $buf = '';
        $lastType = '';
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($s, $i, 1);
            $type = $this->charType($ch);
            if ($type === 'skip') continue;
            if ($buf !== '' && $type !== $lastType) {
                $tokens[] = $buf;
                $buf = '';
            }
            $buf .= $ch;
            $lastType = $type;
        }
        if ($buf !== '') $tokens[] = $buf;
        return $tokens;
    }

    /**
     * @param array<int,string> $tokens
     * @return array<int,string>
     */
    protected function filterStopwords(array $tokens): array
    {
        $out = [];
        foreach ($tokens as $t) {
            if (in_array($t, $this->stopwords, true)) continue;
            $out[] = $t;
        }
        return $out;
    }

    /**
     * @param array<int,string> $tokens
     * @return array<int,string>
     */
    protected function pickBrandTokens(array $tokens): array
    {
        // Boost tokens containing domain words
        $scored = [];
        foreach ($tokens as $idx => $t) {
            $score = 0;
            foreach ($this->domainBoost as $w) {
                if (mb_strpos($t, $w) !== false) { $score += 10; }
            }
            // longer tokens get slight boost
            $score += min(5, mb_strlen($t));
            $scored[] = ['t' => $t, 'score' => $score, 'idx' => $idx];
        }
        usort($scored, function ($a, $b) {
            if ($a['score'] === $b['score']) return $a['idx'] <=> $b['idx'];
            return $b['score'] <=> $a['score'];
        });
        $picked = array_slice(array_column($scored, 't'), 0, 2);
        return $picked;
    }

    protected function initials(string $s): string
    {
        $len = mb_strlen($s);
        $res = '';
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($s, $i, 1);
            $type = $this->charType($ch);
            if ($type === 'latin' || $type === 'digit') {
                $res .= strtoupper($ch);
            } elseif ($type === 'cjk') {
                $res .= $this->pinyinInitial($ch);
            }
        }
        // keep only A-Z
        $res = preg_replace('/[^A-Z]/', '', strtoupper($res)) ?? '';
        return $res;
    }

    protected function enforceLength(string $s, int $min, int $max): string
    {
        if ($s === '') return $s;
        if (strlen($s) < $min) {
            // pad by repeating last char
            $s = str_pad($s, $min, substr($s, -1));
        }
        if (strlen($s) > $max) {
            $s = substr($s, 0, $max);
        }
        // absolute cap at 16
        if (strlen($s) > 16) $s = substr($s, 0, 16);
        return $s;
    }

    protected function charType(string $ch): string
    {
        $ord = ord($ch);
        if (preg_match('/[A-Za-z]/', $ch)) return 'latin';
        if (preg_match('/[0-9]/', $ch)) return 'digit';
        // CJK Unified Ideographs range by Unicode
        $code = $this->uniCodePoint($ch);
        if (($code >= 0x4E00 && $code <= 0x9FFF) || ($code >= 0x3400 && $code <= 0x4DBF)) return 'cjk';
        return 'skip';
    }

    protected function mbSubstrSafe(string $s, int $start, int $length): string
    {
        return mb_substr($s, $start, $length);
    }

    protected function uniCodePoint(string $ch): int
    {
        $k = mb_convert_encoding($ch, 'UCS-4BE', 'UTF-8');
        $code = unpack('N', $k);
        return $code ? $code[1] : 0;
    }

    protected function pinyinInitial(string $ch): string
    {
        // Convert to GBK and compute initial by range (classic table)
        $s1 = iconv('UTF-8', 'GBK//IGNORE', $ch);
        if ($s1 === false || strlen($s1) < 2) return '';
        $asc = ord($s1[0]) * 256 + ord($s1[1]) - 65536;
        if ($asc >= -20319 && $asc <= -20284) return 'A';
        if ($asc >= -20283 && $asc <= -19776) return 'B';
        if ($asc >= -19775 && $asc <= -19219) return 'C';
        if ($asc >= -19218 && $asc <= -18711) return 'D';
        if ($asc >= -18710 && $asc <= -18527) return 'E';
        if ($asc >= -18526 && $asc <= -18240) return 'F';
        if ($asc >= -18239 && $asc <= -17923) return 'G';
        if ($asc >= -17922 && $asc <= -17418) return 'H';
        if ($asc >= -17417 && $asc <= -16475) return 'J';
        if ($asc >= -16474 && $asc <= -16213) return 'K';
        if ($asc >= -16212 && $asc <= -15641) return 'L';
        if ($asc >= -15640 && $asc <= -15166) return 'M';
        if ($asc >= -15165 && $asc <= -14923) return 'N';
        if ($asc >= -14922 && $asc <= -14915) return 'O';
        if ($asc >= -14914 && $asc <= -14631) return 'P';
        if ($asc >= -14630 && $asc <= -14150) return 'Q';
        if ($asc >= -14149 && $asc <= -14091) return 'R';
        if ($asc >= -14090 && $asc <= -13319) return 'S';
        if ($asc >= -13318 && $asc <= -12839) return 'T';
        if ($asc >= -12838 && $asc <= -12557) return 'W';
        if ($asc >= -12556 && $asc <= -11848) return 'X';
        if ($asc >= -11847 && $asc <= -11056) return 'Y';
        if ($asc >= -11055 && $asc <= -10247) return 'Z';
        return '';
    }
}

