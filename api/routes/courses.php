<?php
declare(strict_types=1);
AuthMiddleware::requireLogin();
[$items,$total,$page]=(new CourseRepository(db()))->paginate(page_number($_GET['page']??1),trim((string)($_GET['q']??'')));
JsonResponse::send(['data'=>$items,'page'=>$page,'total'=>$total,'per_page'=>PAGE_SIZE]);
