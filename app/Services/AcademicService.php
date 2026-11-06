<?php
declare(strict_types=1);

namespace Prospak\Services;

use PDO;
use Prospak\Core\AcademicValidation as AV;
use Prospak\Core\Database;
use Prospak\Repositories\AcademicRepository;
use Prospak\Repositories\PeopleRepository;

final class AcademicService
{
    public function __construct(private Database $database) {}

    public function repository(): AcademicRepository { return new AcademicRepository($this->database->pdo()); }

    public function person(int $id): ?array { return (new PeopleRepository($this->database->pdo()))->find($id); }

    /** Returns the saved record ID; never deletes historical academic entities. */
    public function save(string $entity, array $input, int $actorId, ?int $id=null): int
    {
        [$data, $errors] = AV::validate($entity,$input);
        if ($errors) { throw new \DomainException('Correct the highlighted fields.'); }
        $table = $entity==='levels' ? 'academic_levels' : AV::entity($entity);
        $pdo = $this->database->pdo();
        $pdo->beginTransaction();
        try {
            $old=null;
            if ($id !== null) {
                $lock=$pdo->prepare("SELECT * FROM {$table} WHERE id=:id FOR UPDATE");
                $lock->execute(['id'=>$id]);
                $old=$lock->fetch();
                if (!$old) { throw new \DomainException('Academic record not found.'); }
            }
            if (in_array($entity,['departments','programmes'],true)) {
                $key = $entity==='departments'?'faculty_id':'department_id';
                if ($old && (int)$old[$key]!==$data['parent_id']) {
                    $dependentTable=$entity==='departments'?'programmes':'students';
                    $dependentKey=$entity==='departments'?'department_id':'programme_id';
                    $check=$pdo->prepare("SELECT COUNT(*) FROM {$dependentTable} WHERE {$dependentKey}=:id");
                    $check->execute(['id'=>$id]);
                    if ((int)$check->fetchColumn() > 0) {
                        throw new \DomainException('Cannot move this record while dependent academic records exist.');
                    }
                    if ($entity==='departments') {
                        $lecturers=$pdo->prepare('SELECT COUNT(*) FROM lecturers WHERE department_id=:id');
                        $lecturers->execute(['id'=>$id]);
                        if ((int)$lecturers->fetchColumn()>0) {
                            throw new \DomainException('Cannot move a department while lecturers are assigned.');
                        }
                    }
                }
                if (($id===null || (int)$old[$key]!==$data['parent_id'] || ($old['status']!=='active' && $data['status']==='active'))
                    && !$this->repository()->eligibleParent($entity,$data['parent_id'])) {
                    throw new \DomainException('The selected parent must exist and be active (including its faculty).');
                }
            }
            $columns=['code'=>$data['code'],'name'=>$data['name'],'status'=>$data['status']];
            if ($entity==='departments') { $columns['faculty_id']=$data['parent_id']; }
            if ($entity==='programmes') { $columns['department_id']=$data['parent_id']; }
            if ($entity==='levels') { $columns['sort_order']=$data['sort_order']; }
            if ($id===null) {
                $fields=implode(',',array_keys($columns));
                $holders=implode(',',array_map(static fn(string $key):string=>':'.$key,array_keys($columns)));
                $stmt=$pdo->prepare("INSERT INTO {$table} ({$fields}) VALUES ({$holders})");
                $stmt->execute($columns);
                $id=(int)$pdo->lastInsertId();
                $action='created';
            } else {
                $parts=array_map(static fn(string $key):string=>$key.'=:'.$key,array_keys($columns));
                $stmt=$pdo->prepare("UPDATE {$table} SET ".implode(',',$parts).' WHERE id=:id');
                $stmt->execute($columns+['id'=>$id]);
                $action='updated';
            }
            $this->audit($pdo,$actorId,'academics.'.$entity.'.'.$action);
            $pdo->commit();
            return $id;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            if ($exception instanceof \PDOException && (int)($exception->errorInfo[1]??0)===1062) {
                throw new \DomainException('Code, name or level display order is already in use.',0,$exception);
            }
            throw $exception;
        }
    }

    /** Set only academic placement columns; never reset the person's Phase 2 identity. */
    public function place(int $userId, string $role, array $input, int $actorId): void
    {
        if (!in_array($role,['student','lecturer'],true)) {
            throw new \DomainException('Only students and lecturers can have academic placement.');
        }
        $errors=[];
        $fields=$role==='student'?['programme_id'=>'programme','academic_level_id'=>'level']:['department_id'=>'department'];
        $data=[];
        foreach ($fields as $key=>$kind) {
            $data[$key]=AV::optionalId($input[$key]??null,$errors,$key);
        }
        if ($errors) { throw new \DomainException(implode(' ',array_values($errors))); }
        $pdo=$this->database->pdo();
        $pdo->beginTransaction();
        try {
            $table=$role==='student'?'students':'lecturers';
            $stmt=$pdo->prepare("SELECT * FROM {$table} WHERE user_id=:id FOR UPDATE");
            $stmt->execute(['id'=>$userId]);
            $record=$stmt->fetch();
            if (!$record) { throw new \DomainException('Academic profile not found.'); }
            foreach ($fields as $key=>$kind) {
                if ($data[$key]!==null && (int)($record[$key]??0)!==$data[$key]
                    && !$this->repository()->eligiblePlacement($kind,$data[$key])) {
                    throw new \DomainException('The selected '.$kind.' and its parent organisation must be active.');
                }
            }
            $changes=[];
            foreach (array_keys($fields) as $key) { $changes[]=$key.'=:'.$key; }
            $stmt=$pdo->prepare("UPDATE {$table} SET ".implode(', ',$changes).' WHERE user_id=:user_id');
            $stmt->execute($data+['user_id'=>$userId]);
            $log=$pdo->prepare('INSERT INTO audit_logs (actor_user_id,subject_user_id,event_type) VALUES (:actor,:subject,:event)');
            $log->execute(['actor'=>$actorId,'subject'=>$userId,'event'=>'academics.placement.updated']);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }

    private function audit(PDO $pdo, int $actorId, string $event): void
    {
        $stmt=$pdo->prepare('INSERT INTO audit_logs (actor_user_id,event_type) VALUES (:actor,:event)');
        $stmt->execute(['actor'=>$actorId,'event'=>$event]);
    }
}
