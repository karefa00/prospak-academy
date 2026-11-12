<?php
declare(strict_types=1);
// First-time setup, CLI ONLY. Never expose admin provisioning through public web routes.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../../bootstrap/app.php';
$email=isset($argv[1])?Input::email($argv[1]):'';
$name=isset($argv[2])?Input::required($argv[2],150):'Prospak Administrator';
if(!$email){fwrite(STDERR,"Usage: php database/seeders/create_admin.php admin@example.test 'Admin Name'\n");exit(1);}
$pdo=db();$repo=new UserRepository($pdo);
if($repo->byEmail($email)){fwrite(STDERR,"Account already exists. No changes made.\n");exit(1);}
$password=bin2hex(random_bytes(12));
$pdo->beginTransaction();
try{$id=$repo->create('administrator',$name,$email,$password);$pdo->commit();}
catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
echo "Admin user created (ID {$id}).\nTemporary password (copy privately; shown once): {$password}\nYou must change this on first login.\n";
