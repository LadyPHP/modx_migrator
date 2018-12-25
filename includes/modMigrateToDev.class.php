<?php
/**
 * Created by PhpStorm.
 * User: AKoloskova
 * Date: 12.09.2018
 * Time: 13:24
 */

class modMigrateToDev
{
    /**
     * @var modInstall $install Reference to the modInstall instance.
     */
    public $install = null;

    function __construct(modInstall &$install)
    {
        $this->install =& $install;
    }

    public function migrate()
    {
        if (stripos($_SERVER['HTTP_HOST'], '.dev') !== false) {

            $database_server = $this->install->settings->get('database_server');
            $table_prefix = $this->install->settings->get('table_prefix');
            $dbase = $this->install->settings->get('dbase');
            $database_user = $this->install->settings->get('database_user');
            $database_password = $this->install->settings->get('database_password');
            $filessql = [
                'structure' => MODX_SETUP_PATH . 'structure_db.sql',
                'demo_content' => MODX_SETUP_PATH . 'demo_content.sql'
            ];

            foreach ($filessql as $query_type => $filesql) {
                if (!file_exists($filesql)) echo 'не найден файл';

                $sqldate = file_get_contents($filesql);
                $sqldate = str_replace('mgrt_', $table_prefix, $sqldate);
                $new_base_url = !empty($_SERVER['HTTPS']) ? 'https://' : 'http://';
                $new_base_url .= $_SERVER['HTTP_HOST'];

                if ($query_type == 'demo_content') $sqldate = str_replace('https://MGRT_HOST', $new_base_url, $sqldate);
                if (is_writable($filesql) === false) @chmod($filesql, 0664);
                file_put_contents($filesql, $sqldate);

                $command = 'mysql -h' . $database_server . ' -u' . $database_user . ' -p' . $database_password . ' ' . $dbase . ' < ' . $filesql;
                if (exec($command) === false) echo 'не возможно выполнить функцию exec()';
            }
        } else echo 'Установка таблиц из бекапов миграции не возможна, так как адрес сайта не содержит "dev".';
    }
}