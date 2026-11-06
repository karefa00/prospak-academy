<?php
declare(strict_types=1);

namespace Prospak\Core;

/** Academic organisation identifiers and display labels; no institutional rules are invented. */
final class AcademicValidation
{
    public const ENTITIES = ['faculties', 'departments', 'programmes', 'levels'];
    public const LABELS = ['faculties'=>'Faculties', 'departments'=>'Departments', 'programmes'=>'Programmes', 'levels'=>'Academic levels'];
    public const SINGULAR = ['faculties'=>'Faculty', 'departments'=>'Department', 'programmes'=>'Programme', 'levels'=>'Academic level'];
    public const STATUSES = ['active', 'inactive'];

    public static function entity(string $type): string
    {
        if (!in_array($type, self::ENTITIES, true)) {
            throw new \InvalidArgumentException('Invalid academic entity.');
        }
        return $type;
    }

    public static function id(mixed $value): ?int
    {
        if (!is_string($value) && !is_int($value)) { return null; }
        $value = (string) $value;
        if (!preg_match('/^[1-9][0-9]{0,9}$/D', $value)) { return null; }
        $number = (int) $value;
        return $number >= 1 && $number <= 4294967295 ? $number : null;
    }

    public static function optionalId(mixed $value, array &$errors, string $key): ?int
    {
        if ($value === '' || $value === null) { return null; }
        $id = self::id($value);
        if ($id === null) { $errors[$key] = 'Choose a valid record or leave it unassigned.'; }
        return $id;
    }

    public static function page(mixed $value): int
    {
        return min(self::id($value) ?? 1, 1000000);
    }

    public static function search(mixed $value): string
    {
        if (!is_string($value)) { return ''; }
        $value = trim($value);
        if (!preg_match('//u', $value)) { return ''; }
        if (function_exists('mb_strcut')) { return mb_strcut($value, 0, 120, 'UTF-8'); }
        while (strlen($value) > 120) { $value = preg_replace('/.$/u', '', $value) ?? ''; }
        return $value;
    }

    public static function validate(string $entity, array $input): array
    {
        self::entity($entity);
        $errors = [];
        $code = $input['code'] ?? null;
        $length = $entity === 'programmes' ? 30 : 20;
        $code = is_string($code) ? strtoupper(trim($code)) : '';
        if (!preg_match('/^[A-Z0-9][A-Z0-9_\/-]{1,' . ($length - 1) . '}$/D', $code)) {
            $errors['code'] = 'Code must be 2–' . $length . ' letters, digits, /, - or _.';
        }
        $name = $input['name'] ?? null;
        $name = is_string($name) ? trim($name) : '';
        if (strlen($name) < 2 || strlen($name) > 150 || preg_match('/[\x00-\x1f\x7f]/', $name)) {
            $errors['name'] = 'Name must contain 2–150 bytes without control characters.';
        }
        $status = $input['status'] ?? 'active';
        if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
            $errors['status'] = 'Choose active or inactive.';
            $status = 'active';
        }
        $parent = null;
        if (in_array($entity, ['departments', 'programmes'], true)) {
            $parent = self::id($input['parent_id'] ?? null);
            if ($parent === null) { $errors['parent_id'] = 'Select a valid parent record.'; }
        }
        $sort = null;
        if ($entity === 'levels') {
            $v = $input['sort_order'] ?? null;
            if ((!is_string($v) && !is_int($v)) || !preg_match('/^[1-9][0-9]{0,3}$/D', (string)$v) || (int)$v > 9999) {
                $errors['sort_order'] = 'Display order must be a whole number from 1 to 9999.';
            } else { $sort = (int) $v; }
        }
        return [['code'=>$code,'name'=>$name,'status'=>$status,'parent_id'=>$parent,'sort_order'=>$sort], $errors];
    }
}
