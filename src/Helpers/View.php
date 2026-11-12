<?php
declare(strict_types=1);
final class View {
    public static function render(string $template, array $data=[], string $layout='dashboard'): void {
        if (!preg_match('/^[a-z_]+\/[a-z_]+$/', $template)) throw new LogicException('Invalid template name.');
        if (!in_array($layout,['dashboard','auth','main'],true)) throw new LogicException('Invalid layout.');
        extract($data, EXTR_SKIP);
        ob_start();
        require PROJECT_ROOT.'/templates/'.$template.'.php';
        $content=(string)ob_get_clean();
        require PROJECT_ROOT.'/templates/layouts/'.$layout.'.php';
    }
}
