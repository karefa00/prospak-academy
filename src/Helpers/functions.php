<?php
declare(strict_types=1);
function e(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $path): never { header('Location: ' . app_url($path), true, 303); exit; }
function csrf_token(): string {
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function flash(string $message, string $kind = 'info'): void {
    $_SESSION['flash'] = ['message'=>$message,'kind'=>in_array($kind,['success','danger','info'],true)?$kind:'info'];
}
function old(string $key): string { return e($_SESSION['old_input'][$key] ?? ''); }
function keep_old(array $input, array $allow): void {
    $_SESSION['old_input'] = array_intersect_key($input, array_flip($allow));
}
function forget_old(): void { unset($_SESSION['old_input']); }
function wat_time(?string $utc): string {
    if (!$utc) return '—';
    $date = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    return $date->setTimezone(new DateTimeZone(APP_TIMEZONE))->format('d M Y, g:i A') . ' WAT';
}
function positive_id(mixed $v): int {
    $id = filter_var($v, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    return $id === false ? 0 : (int)$id;
}
function page_number(mixed $value): int { return min(100000, max(1, positive_id($value) ?: 1)); }
function page_links(int $page, int $total, string $path): string {
    $pages=max(1,(int)ceil($total/PAGE_SIZE));
    $next = htmlspecialchars($path . (str_contains($path,'?')?'&':'?') . 'page=', ENT_QUOTES, 'UTF-8');
    $html='<nav class="paginationbar" aria-label="Pagination"><span>Page '.e($page).' of '.e($pages).' · '.e($total).' records</span>';
    if($page>1)$html.='<a class="btn btn-outline-primary btn-sm" href="'.$next.($page-1).'">Previous</a>';
    if($page<$pages)$html.='<a class="btn btn-primary btn-sm" href="'.$next.($page+1).'">Next</a>';
    return $html.'</nav>';
}
