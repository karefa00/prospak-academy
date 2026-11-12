<?php
declare(strict_types=1);
final class CourseValidator {
    public static function credit(mixed $v): int {
        $i=positive_id($v);
        if($i<1 || $i>99) throw new InvalidArgumentException('Credit units must be between 1 and 99.');
        return $i;
    }
    public static function semester(mixed $v): int {
        $i=positive_id($v);
        if(!in_array($i,[1,2],true)) throw new InvalidArgumentException('Semester must be first or second.');
        return $i;
    }
}
