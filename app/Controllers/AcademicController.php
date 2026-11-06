<?php
declare(strict_types=1);

namespace Prospak\Controllers;

use Prospak\Core\AcademicValidation as AV;
use Prospak\Core\Config;
use Prospak\Core\Guard;
use Prospak\Core\Session;
use Prospak\Core\View;
use Prospak\Services\AcademicService;
use Prospak\Services\AuthService;

final class AcademicController
{
    public function __construct(private AuthService $auth, private AcademicService $academics) {}

    private function viewer(bool $manage=false): array
    {
        $user=Guard::requirePermission($this->auth,$manage?'academics.manage':'academics.view');
        if ($manage && ($user['role_slug']??'')!=='administrator') {
            http_response_code(403); exit('Forbidden');
        }
        return $user;
    }

    private function requireId(mixed $value): int
    {
        $id=AV::id($value);
        if ($id===null) { http_response_code(400); exit('Invalid record ID.'); }
        return $id;
    }

    private function existing(string $entity,mixed $value): array
    {
        $item=$this->academics->repository()->find($entity,$this->requireId($value));
        if (!$item) { http_response_code(404); exit('Academic record not found.'); }
        return $item;
    }

    private static function redirect(string $path): never
    {
        header('Location: '.Config::url($path),true,303);
        exit;
    }

    public function index(string $entity): void
    {
        $user=$this->viewer();
        $search=AV::search($_GET['q']??'');
        $status=$_GET['status']??'';
        $status=is_string($status)&&in_array($status,AV::STATUSES,true)?$status:'';
        $list=$this->academics->repository()->page($entity,$search,$status,AV::page($_GET['page']??1));
        View::render('academics/list',['title'=>AV::LABELS[$entity],'user'=>$user,
          'entity'=>$entity,'listing'=>$list,'search'=>$search,'statusFilter'=>$status,
          'canManage'=>$user['role_slug']==='administrator'&&$this->auth->can('academics.manage'),
          'flash'=>Session::pullFlash()], 'portal');
    }

    public function form(string $entity): void
    {
        $user=$this->viewer(true);
        $item=isset($_GET['id'])?$this->existing($entity,$_GET['id']):null;
        $options=$this->parentOptions($entity);
        View::render('academics/form',['title'=>($item?'Edit ':'Add ').AV::LABELS[$entity],
          'user'=>$user,'entity'=>$entity,'item'=>$item,'values'=>$this->formValues($entity,$item),
          'options'=>$options,'errors'=>[]], 'portal');
    }

    public function save(string $entity): void
    {
        $user=$this->viewer(true);
        $id=null;
        if (isset($_POST['id'])&&$_POST['id']!=='') { $id=$this->requireId($_POST['id']); $this->existing($entity,$id); }
        [$data,$errors]=AV::validate($entity,$_POST);
        if (!$errors) {
            try {
                $this->academics->save($entity,$_POST,(int)$user['id'],$id);
                Session::flash('success',AV::LABELS[$entity].' saved successfully.');
                self::redirect('/academics/'.$entity);
            } catch (\DomainException $e) { $errors['form']=$e->getMessage(); }
        }
        http_response_code(422);
        View::render('academics/form',['title'=>($id?'Edit ':'Add ').AV::LABELS[$entity],
          'user'=>$user,'entity'=>$entity,'item'=>$id?['id'=>$id]:null,'values'=>$_POST,
          'options'=>$this->parentOptions($entity),'errors'=>$errors], 'portal');
    }

    private function parentOptions(string $entity): array
    {
        return match ($entity) {
            'departments'=>$this->academics->repository()->options('faculties'),
            'programmes'=>$this->academics->repository()->options('departments'),
            default=>[],
        };
    }

    private function formValues(string $entity,?array $item): array
    {
        if (!$item) { return ['status'=>'active']; }
        $item['parent_id']=match ($entity) {
            'departments'=>$item['faculty_id'], 'programmes'=>$item['department_id'], default=>'',
        };
        return $item;
    }

    public function students(): void
    {
        $user=$this->viewer();
        $entity=$_GET['entity']??'';
        if (!is_string($entity)||!in_array($entity,AV::ENTITIES,true)) { http_response_code(400); exit('Invalid academic entity.'); }
        $item=$this->existing($entity,$_GET['id']??null);
        $search=AV::search($_GET['q']??'');
        $listing=$this->academics->repository()->students($entity,(int)$item['id'],$search,AV::page($_GET['page']??1));
        View::render('academics/students',['title'=>'Associated students','user'=>$user,
          'entity'=>$entity,'item'=>$item,'search'=>$search,'listing'=>$listing,
          'canManage'=>$user['role_slug']==='administrator'&&$this->auth->can('users.manage')], 'portal');
    }

    public function placement(): void
    {
        $user=$this->viewer(true);
        $person=$this->person($_GET['id']??null);
        View::render('academics/placement',$this->placementData($user,$person), 'portal');
    }

    public function savePlacement(): void
    {
        $user=$this->viewer(true);
        $person=$this->person($_POST['id']??null);
        try {
            $this->academics->place((int)$person['id'],$person['role_slug'],$_POST,(int)$user['id']);
            Session::flash('success','Academic placement saved.');
            self::redirect('/admin/users/view?id='.(int)$person['id']);
        } catch (\DomainException $e) {
            http_response_code(422);
            View::render('academics/placement',$this->placementData($user,$person,$_POST,$e->getMessage()),'portal');
        }
    }

    private function person(mixed $id): array
    {
        $person=$this->academics->person($this->requireId($id));
        if ($person===null) { http_response_code(404); exit('User not found.'); }
        if (!in_array($person['role_slug'],['student','lecturer'],true)) { http_response_code(400); exit('Academic placement is only for students and lecturers.'); }
        return $person;
    }

    private function placementData(array $user,array $person,?array $values=null,?string $error=null): array
    {
        return ['title'=>'Academic placement','user'=>$user,'person'=>$person,
          'values'=>$values??['programme_id'=>$person['programme_id'], 'academic_level_id'=>$person['academic_level_id'], 'department_id'=>$person['lecturer_department_id']],
          'programmes'=>$this->academics->repository()->options('programmes'),
          'levels'=>$this->academics->repository()->options('levels'),
          'departments'=>$this->academics->repository()->options('departments'),'error'=>$error];
    }
}
