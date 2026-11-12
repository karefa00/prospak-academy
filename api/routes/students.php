<?php
declare(strict_types=1);
RoleMiddleware::requireRole('administrator','academic_officer');
[$items,$total,$page]=(new StudentRepository(db()))->paginate(page_number($_GET['page']??1),trim((string)($_GET['q']??'')));
JsonResponse::send(['data'=>$items,'page'=>$page,'total'=>$total,'per_page'=>PAGE_SIZE]);
