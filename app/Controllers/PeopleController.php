<?php
declare(strict_types=1);

namespace Prospak\Controllers;

use Prospak\Core\Config;
use Prospak\Core\Guard;
use Prospak\Core\PeopleValidation;
use Prospak\Core\Session;
use Prospak\Core\View;
use Prospak\Services\AuthService;
use Prospak\Services\PeopleService;

final class PeopleController
{
    public function __construct(private AuthService $auth, private PeopleService $people) {}

    /** A separate role check is intentional: academic staff cannot provision accounts even if misassigned a permission. */
    private function admin(): array
    {
        $user = Guard::requirePermission($this->auth, 'users.manage');
        if ($user['role_slug'] !== 'administrator') {
            http_response_code(403);
            exit('Forbidden');
        }
        return $user;
    }

    private function targetId(mixed $value): int
    {
        $id = PeopleValidation::positiveId($value);
        if ($id === null) {
            http_response_code(400);
            exit('Invalid user ID.');
        }
        return $id;
    }

    private function target(mixed $id): array
    {
        $person = $this->people->repository()->find($this->targetId($id));
        if ($person === null) {
            http_response_code(404);
            exit('User not found.');
        }
        return $person;
    }

    public function index(): void
    {
        $admin = $this->admin();
        $role = $_GET['role'] ?? '';
        $status = $_GET['status'] ?? '';
        $search = $_GET['q'] ?? '';
        $role = is_string($role) && in_array($role, PeopleValidation::ROLES, true) ? $role : '';
        $status = is_string($status) && in_array($status, PeopleValidation::STATUSES, true) ? $status : '';
        $search = is_string($search) ? mbSafeTrim($search, 120) : '';
        $listing = $this->people->repository()->page($search, $role, $status, PeopleValidation::page($_GET['page'] ?? 1));
        View::render('people/list', ['title' => 'Account management', 'user' => $admin, 'listing' => $listing,
            'roleFilter' => $role, 'statusFilter' => $status, 'search' => $search,
            'flash' => Session::pullFlash()], 'portal');
    }

    public function students(): void
    {
        $_GET['role'] = 'student';
        $this->index();
    }

    public function lecturers(): void
    {
        $_GET['role'] = 'lecturer';
        $this->index();
    }

    public function new(): void
    {
        $admin = $this->admin();
        View::render('people/form', ['title' => 'Create account', 'user' => $admin, 'values' => ['role' => 'student'],
            'errors' => [], 'isEdit' => false], 'portal');
    }

    public function create(): void
    {
        $admin = $this->admin();
        [$validated, $errors] = PeopleValidation::validate($_POST);
        if (!$errors) {
            try {
                $created = $this->people->create($_POST, (int) $admin['id']);
                $_SESSION['_provisioned'] = ['id' => $created['id'], 'password' => $created['password'], 'issued_at' => time()];
                self::redirect('/admin/users/view?id=' . $created['id']);
            } catch (\DomainException $e) {
                $errors['form'] = $e->getMessage();
            }
        }
        http_response_code(422);
        View::render('people/form', ['title' => 'Create account', 'user' => $admin,
            'values' => $_POST, 'errors' => $errors, 'isEdit' => false], 'portal');
    }

    public function show(): void
    {
        $admin = $this->admin();
        $person = $this->target($_GET['id'] ?? null);
        $credential = null;
        $issued = $_SESSION['_provisioned'] ?? null;
        if (is_array($issued) && ($issued['id'] ?? null) === (int) $person['id']) {
            $credential = (time() - (int) ($issued['issued_at'] ?? 0) < 300) ? ($issued['password'] ?? null) : null;
            unset($_SESSION['_provisioned']);
        }
        View::render('people/detail', ['title' => 'Account details', 'user' => $admin, 'person' => $person,
            'credential' => $credential, 'canManage' => true, 'flash' => Session::pullFlash()], 'portal');
    }

    public function profile(): void
    {
        $user = Guard::requirePermission($this->auth, 'dashboard.view');
        $person = $this->target($user['id']);
        View::render('people/detail', ['title' => 'My profile', 'user' => $user, 'person' => $person,
            'credential' => null, 'canManage' => false], 'portal');
    }

    public function edit(): void
    {
        $admin = $this->admin();
        $person = $this->target($_GET['id'] ?? null);
        View::render('people/form', ['title' => 'Edit account', 'user' => $admin, 'values' => $this->formValues($person),
            'errors' => [], 'isEdit' => true, 'targetId' => (int) $person['id'], 'existingRole' => $person['role_slug']], 'portal');
    }

    public function update(): void
    {
        $admin = $this->admin();
        $person = $this->target($_POST['id'] ?? null);
        [$validated, $errors] = PeopleValidation::validate($_POST, true, $person['role_slug']);
        if (!$errors) {
            try {
                $this->people->update((int) $person['id'], $_POST, (int) $admin['id']);
                Session::flash('success', 'Account profile updated.');
                self::redirect('/admin/users/view?id=' . $person['id']);
            } catch (\DomainException $e) {
                $errors['form'] = $e->getMessage();
            }
        }
        http_response_code(422);
        View::render('people/form', ['title' => 'Edit account', 'user' => $admin,
            'values' => $_POST, 'errors' => $errors, 'isEdit' => true,
            'targetId' => (int) $person['id'], 'existingRole' => $person['role_slug']], 'portal');
    }

    public function status(): void
    {
        $admin = $this->admin();
        $id = $this->targetId($_POST['id'] ?? null);
        try {
            $requested = $_POST['status'] ?? null;
            $this->people->setStatus($id, is_string($requested) ? $requested : '', (int) $admin['id']);
            Session::flash('success', 'Account status updated.');
            self::redirect('/admin/users/view?id=' . $id);
        } catch (\DomainException $e) {
            $this->renderActionError($admin, $id, $e->getMessage());
        }
    }

    public function temporaryPassword(): void
    {
        $admin = $this->admin();
        $id = $this->targetId($_POST['id'] ?? null);
        try {
            $temporary = $this->people->resetTemporaryPassword($id, (int) $admin['id']);
            $_SESSION['_provisioned'] = ['id' => $id, 'password' => $temporary, 'issued_at' => time()];
            self::redirect('/admin/users/view?id=' . $id);
        } catch (\DomainException $e) {
            $this->renderActionError($admin, $id, $e->getMessage());
        }
    }

    private function renderActionError(array $admin, int $id, string $message): void
    {
        $person = $this->target($id);
        http_response_code(422);
        View::render('people/detail', ['title' => 'Account details', 'user' => $admin, 'person' => $person,
            'canManage' => true, 'credential' => null, 'error' => $message], 'portal');
    }

    public function photo(): void
    {
        $viewer = Guard::requirePermission($this->auth, 'dashboard.view');
        $targetId = $this->targetId($_GET['id'] ?? null);
        $canManage = $viewer['role_slug'] === 'administrator' && $this->auth->can('users.manage');
        // Reject cross-user access before looking up the other record (avoid enumeration).
        if (!$canManage && (int) $viewer['id'] !== $targetId) {
            http_response_code(403);
            exit('Forbidden');
        }
        $person = $this->target($targetId);
        if ($person['role_slug'] !== 'student' || !is_string($person['passport_filename'])) {
            http_response_code(404);
            exit('Image not found.');
        }
        $filename = $person['passport_filename'];
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/D', $filename)) {
            http_response_code(404);
            exit('Image not found.');
        }
        $path = dirname(__DIR__, 2) . '/storage/uploads/student_photos/' . $filename;
        if (!is_file($path)) { http_response_code(404); exit('Image not found.'); }
        $mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][pathinfo($filename, PATHINFO_EXTENSION)];
        header('Content-Type: ' . $mime);
        header('Cache-Control: private, no-store');
        readfile($path);
    }

    public function uploadPhoto(): void
    {
        $admin = $this->admin();
        $person = $this->target($_POST['id'] ?? null);
        if ($person['role_slug'] !== 'student') {
            $this->renderActionError($admin, (int) $person['id'], 'Only student profiles accept passport photos.');
            return;
        }
        $file = $_FILES['passport'] ?? null;
        if (!is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_int($file['size'] ?? null) || $file['size'] < 1 || $file['size'] > 2 * 1024 * 1024 || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
            $this->renderActionError($admin, (int) $person['id'], 'Upload a JPEG, PNG or WebP image no larger than 2 MB.');
            return;
        }
        $allowed = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
            $this->renderActionError($admin, (int) $person['id'], 'Invalid image content. JPEG, PNG or WebP only.');
            return;
        }
        $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $directory = dirname(__DIR__, 2) . '/storage/uploads/student_photos';
        if (!is_dir($directory) || !is_writable($directory) || !move_uploaded_file($file['tmp_name'], $directory . '/' . $name)) {
            throw new \RuntimeException('Private upload storage is not writable.');
        }
        try {
            $dbh = $this->people->pdo();
            $dbh->beginTransaction();
            $check = $dbh->prepare('SELECT passport_filename FROM students WHERE user_id = :id FOR UPDATE');
            $check->execute(['id' => $person['id']]);
            $old = $check->fetchColumn();
            $stmt = $dbh->prepare('UPDATE students SET passport_filename = :filename WHERE user_id = :id');
            $stmt->execute(['filename' => $name, 'id' => $person['id']]);
            if ($stmt->rowCount() !== 1) { throw new \RuntimeException('Student profile not found.'); }
            $dbh->prepare('INSERT INTO audit_logs (actor_user_id, subject_user_id, event_type) VALUES (:actor,:subject, :event)')
                ->execute(['actor'=>$admin['id'], 'subject'=>$person['id'], 'event'=>'people.photo_updated']);
            $dbh->commit();
            if (is_string($old) && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/D', $old)) {
                @unlink($directory . '/' . $old);
            }
        } catch (\Throwable $e) {
            if (isset($dbh) && $dbh->inTransaction()) { $dbh->rollBack(); }
            @unlink($directory . '/' . $name);
            throw $e;
        }
        Session::flash('success', 'Student passport photograph updated.');
        self::redirect('/admin/users/view?id=' . $person['id']);
    }

    private function formValues(array $person): array
    {
        $student = $person['role_slug'] === 'student';
        $lecturer = $person['role_slug'] === 'lecturer';
        return ['role'=>$person['role_slug'], 'full_name'=>$person['full_name'], 'email'=>$person['email'],
            'first_name'=>$student ? $person['student_first_name'] : $person['lecturer_first_name'],
            'last_name'=>$student ? $person['student_last_name'] : $person['lecturer_last_name'],
            'phone'=>$student ? $person['student_phone'] : $person['lecturer_phone'],
            'matriculation_number'=>$person['matriculation_number'], 'staff_number'=>$person['staff_number'],
            'gender'=>$person['gender'], 'date_of_birth'=>$person['date_of_birth'],
            'admission_year'=>$person['admission_year'], 'designation'=>$person['designation']];
    }

    private static function redirect(string $path): never
    {
        header('Location: ' . Config::url($path), true, 303);
        exit;
    }
}

/** Truncate search input by bytes, retaining valid UTF-8 if possible without mbstring. */
function mbSafeTrim(string $value, int $limit): string
{
    $value = preg_replace('/[\x00-\x1f\x7f]/', '', trim($value)) ?? '';
    if (strlen($value) <= $limit) { return $value; }
    $value = substr($value, 0, $limit);
    while ($value !== '' && !preg_match('//u', $value)) {
        $value = substr($value, 0, -1);
    }
    return $value;
}
