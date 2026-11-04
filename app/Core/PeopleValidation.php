<?php
declare(strict_types=1);

namespace Prospak\Core;

/** Shared, DB-independent validation for administrator-created academic people. */
final class PeopleValidation
{
    public const ROLES = ['administrator', 'academic_officer', 'lecturer', 'student'];
    public const STATUSES = ['active', 'suspended'];

    public static function positiveId(mixed $value): ?int
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $v = (string) $value;
        if (!preg_match('/^[1-9][0-9]{0,17}$/D', $v) || (int) $v < 1) {
            return null;
        }
        return (int) $v;
    }

    public static function page(mixed $value): int
    {
        return self::positiveId($value) ?? 1;
    }

    public static function validate(array $input, bool $isUpdate = false, ?string $existingRole = null): array
    {
        $errors = [];
        $role = $input['role'] ?? null;
        if (!is_string($role) || !in_array($role, self::ROLES, true)) {
            $errors['role'] = 'Choose a valid role.';
            $role = '';
        }
        $email = Validation::email($input['email'] ?? null);
        if ($email === null) {
            $errors['email'] = 'Provide a valid email (maximum 254 bytes).';
        }
        $firstName = '';
        $lastName = '';
        $name = null;
        if (in_array($role, ['student', 'lecturer'], true)) {
            $firstName = self::shortName($input['first_name'] ?? null);
            $lastName = self::shortName($input['last_name'] ?? null);
            if ($firstName === null) {
                $errors['first_name'] = 'First name is required (maximum 75 bytes).';
            }
            if ($lastName === null) {
                $errors['last_name'] = 'Last name is required (maximum 75 bytes).';
            }
            if ($firstName !== null && $lastName !== null) {
                $name = $firstName . ' ' . $lastName;
                if (strlen($name) > 150) {
                    $errors['first_name'] = 'Combined name is too long.';
                }
            }
        } else {
            $name = Validation::name($input['full_name'] ?? null);
            if ($name === null) {
                $errors['full_name'] = 'Full name is required (maximum 150 bytes).';
            }
        }
        // Academic record role changes require a dedicated future migration to preserve history.
        if ($isUpdate && $role !== $existingRole &&
            (in_array($existingRole, ['student', 'lecturer'], true) || in_array($role, ['student', 'lecturer'], true))) {
            $errors['role'] = 'Student and lecturer role conversions are disabled to preserve academic identity.';
        }
        $number = null;
        if ($role === 'student' || $role === 'lecturer') {
            $key = $role === 'student' ? 'matriculation_number' : 'staff_number';
            $number = $input[$key] ?? null;
            if (!is_string($number) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_\/-]{2,39}$/D', trim($number))) {
                $errors[$key] = 'Enter an issued identifier (3–40 letters, numbers, /, - or _).';
                $number = null;
            } else {
                $number = strtoupper(trim($number));
            }
        }
        $phone = self::optional($input['phone'] ?? null);
        foreach (['phone', 'gender', 'date_of_birth', 'admission_year', 'designation'] as $optionalField) {
            if (isset($input[$optionalField]) && !is_string($input[$optionalField])) {
                $errors[$optionalField] = 'Invalid field value.';
            }
        }
        if ($phone !== null && (!preg_match('/^\+?[0-9 ()\-]{7,30}$/D', $phone) || strlen($phone) > 30)) {
            $errors['phone'] = 'Enter a valid phone number (7–30 characters).';
        }
        $gender = self::optional($input['gender'] ?? null);
        if ($role === 'student' && $gender !== null && !in_array($gender, ['female', 'male', 'other', 'prefer_not_to_say'], true)) {
            $errors['gender'] = 'Select a valid gender value.';
        }
        $dob = self::optional($input['date_of_birth'] ?? null);
        if ($role === 'student' && $dob !== null) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dob);
            if (!$date || $date->format('Y-m-d') !== $dob || $dob > gmdate('Y-m-d')) {
                $errors['date_of_birth'] = 'Enter a valid date of birth not in the future.';
            }
        }
        $admission = self::optional($input['admission_year'] ?? null);
        if ($role === 'student' && $admission !== null && (!preg_match('/^[0-9]{4}$/D', $admission) || (int) $admission < 1901 || (int) $admission > 2155)) {
            $errors['admission_year'] = 'Enter a valid four-digit year.';
        }
        $designation = self::optional($input['designation'] ?? null);
        if ($role === 'lecturer' && $designation !== null && (strlen($designation) > 120 || preg_match('/[\x00-\x1f\x7f]/', $designation))) {
            $errors['designation'] = 'Designation must not exceed 120 bytes.';
        }
        return [[
            'role' => $role, 'email' => $email, 'full_name' => $name,
            'first_name' => $firstName, 'last_name' => $lastName,
            'matriculation_number' => $role === 'student' ? $number : null,
            'staff_number' => $role === 'lecturer' ? $number : null,
            'phone' => $phone, 'gender' => $gender, 'date_of_birth' => $dob,
            'admission_year' => $admission, 'designation' => $designation,
        ], $errors];
    }

    private static function optional(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function shortName(mixed $value): ?string
    {
        $value = Validation::name($value);
        return $value !== null && strlen($value) <= 75 ? $value : null;
    }
}
