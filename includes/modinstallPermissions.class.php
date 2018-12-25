<?php
/**
 * Created by PhpStorm.
 * User: AKoloskova
 * Date: 12.09.2018
 * Time: 13:25
 * Класс для смены прав на директории и файлы ядра и установщика
 */

class modinstallPermissions
{
    public $chmod = ['dir' => 0775, 'file' => 0666];

    public function changePermissions()
    {
        $change_list = [
            'dir' => [
                MODX_CORE_PATH . 'cache',
                MODX_CORE_PATH . 'export',
                MODX_CORE_PATH . 'packages',
            ],
            'file' => [
                'config.core.php',
                MODX_CORE_PATH . 'config/config.inc.php',
                /*'connectors/config.core.php',
                'sysconnectors/config.core.php',
                'manager/config.core.php',
                'synergy-adm/config.core.php',*/
            ],
        ];

        $not_found = '';
        // обрабатываем список папок
        foreach ($change_list['dir'] as $item) {
            if ($item == MODX_CORE_PATH . 'cache') rmdir(MODX_CORE_PATH . 'cache');
            else $result = mkdir($item, $this->chmod['dir']);
            if (is_dir($item)) $result = $this->chmoder($item, $this->chmod['dir']);

            if ($result == false) $not_found .= $item . ', ';
        }

        // обрабатываем список файлов
        foreach ($change_list['file'] as $item) {
            if (!file_exists($item)) continue;
            $result = $this->chmoder($item, $this->chmod['file']);
            if ($result == false) $not_found .= $item . ', ';
        }

        return $not_found ?: 'Права на папки и файлы установщика и ядра изменены.';
    }

    public function chmoder($file, $mode)
    {
        if (!$file || !$mode) return false;
        $result = chmod($file, $mode);
        if (!$result) shell_exec('sudo chmod ' . $mode . ' ' . $file); // не лучшая идея, но для dev-сервера не критично
        return $result;
    }
}