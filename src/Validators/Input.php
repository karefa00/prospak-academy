<?php
declare(strict_types=1);
final class Input {
    public static function required(mixed $v,int $max=150): string {
        if (!is_string($v) || str_contains($v,"\0")) throw new InvalidArgumentException('Invalid text value.');
        $v=trim($v);
        if ($v==='' || mb_strlen_safe($v)>$max) throw new InvalidArgumentException('Required text is empty or too long.');
        return $v;
    }
    public static function email(mixed $v): string {
        $v=strtolower(self::required($v,254));
        if (!filter_var($v,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email address.');
        return $v;
    }
    public static function code(mixed $v, int $max=30): string {
        $v=strtoupper(self::required($v,$max));
        if (!preg_match('/^[A-Z0-9][A-Z0-9_-]*$/D',$v)) throw new InvalidArgumentException('Codes may use letters, numbers, dash and underscore only.');
        return $v;
    }
    public static function id(mixed $v): int {
        $id=positive_id($v);
        if(!$id) throw new InvalidArgumentException('Invalid identifier.');
        return $id;
    }
    public static function score(mixed $v, float $max): string {
        if (!is_string($v) && !is_numeric($v)) throw new InvalidArgumentException('Invalid score.');
        $value=(string)$v;
        if(!preg_match('/^(?:0|[1-9][0-9]{0,2})(?:\.[0-9]{1,2})?$/D',$value)) throw new InvalidArgumentException('Score must have at most two decimal places.');
        $number=(float)$value;
        if($number<0 || $number>$max) throw new InvalidArgumentException('Score exceeds the component maximum.');
        return number_format($number,2,'.','');
    }
}
function mb_strlen_safe(string $v): int { return function_exists('mb_strlen') ? mb_strlen($v,'UTF-8') : strlen($v); }
