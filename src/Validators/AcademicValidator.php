<?php
declare(strict_types=1);
final class AcademicValidator {
    public static function date(mixed $value, bool $optional=false): ?string {
        if ($optional && ($value===null || $value==='')) return null;
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) throw new InvalidArgumentException('Use a valid ISO date (YYYY-MM-DD).');
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('UTC'));
        if (!$d || $d->format('Y-m-d')!==$value) throw new InvalidArgumentException('Invalid calendar date.');
        return $value;
    }
    public static function session(mixed $value): string {
        $name=Input::required($value,9);
        if (!preg_match('/^(20\d{2})\/(20\d{2})$/D',$name,$m) || (int)$m[2] !== (int)$m[1]+1) throw new InvalidArgumentException('Use a session name such as 2026/2027.');
        return $name;
    }
    public static function status(mixed $value): string {
        if (!is_string($value) || !in_array($value,['active','inactive'],true)) throw new InvalidArgumentException('Invalid record status.');
        return $value;
    }
    public static function calendar(array $dates, ?array $parent=null): array {
        $start=self::date($dates['starts_on']??null);$end=self::date($dates['ends_on']??null);
        if($start>=$end)throw new InvalidArgumentException('Start date must be earlier than end date.');
        if($parent && ($start<$parent['starts_on']||$end>$parent['ends_on']))throw new DomainException('Dates must fit within the academic session.');
        $deadline=self::date($dates['registration_deadline']??null,true);
        if($deadline!==null && ($deadline<$start||$deadline>$end))throw new InvalidArgumentException('Registration deadline must be inside the period.');
        $examStart=self::date($dates['exam_starts_on']??null,true);
        $examEnd=self::date($dates['exam_ends_on']??null,true);
        if(($examStart===null)!==($examEnd===null) || ($examStart!==null && ($examStart<$start || $examStart>$examEnd || $examEnd>$end)))throw new InvalidArgumentException('Examination period must be complete and within the dates.');
        $gradingStart=self::date($dates['grading_starts_on']??null,true);
        $gradingEnd=self::date($dates['grading_ends_on']??null,true);
        if(($gradingStart===null)!==($gradingEnd===null) || ($gradingStart!==null && $gradingStart>$gradingEnd))throw new InvalidArgumentException('Grading period must have valid start and end dates.');
        return ['starts_on'=>$start,'ends_on'=>$end,'registration_deadline'=>$deadline,'exam_starts_on'=>$examStart,'exam_ends_on'=>$examEnd,'grading_starts_on'=>$gradingStart,'grading_ends_on'=>$gradingEnd];
    }
    public static function sortOrder(mixed $value): int {
        $v=Input::id($value);
        if($v>65535)throw new InvalidArgumentException('Sort order is out of range.');
        return $v;
    }
}
