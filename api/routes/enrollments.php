<?php
declare(strict_types=1);
$user=RoleMiddleware::requireRole('student');$student=(new StudentRepository(db()))->byUser((int)$user['id']);
if(!$student)JsonResponse::send(['error'=>'Student profile missing'],404);
[$items,$total,$page]=(new EnrollmentRepository(db()))->forStudent((int)$student['id'],page_number($_GET['page']??1));
JsonResponse::send(['data'=>$items,'page'=>$page,'total'=>$total,'per_page'=>PAGE_SIZE]);
