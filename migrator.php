<?php
/**
 * Created by PhpStorm.
 * User: AKoloskova
 * Date: 13.09.2018
 * Time: 19:46
 * Скачивает актуальную версию пакета с официального репозитория и добавляет изменения для решения вопроса миграции проектов на DEV
 */

// ссылка для определения версии
$version_url = 'https://ilyaut.ru/download-modx/?api=getmodxlastversion';
// массив со сслыками для скачивания
$distibutive_urls = [
    'https://ilyaut.ru/modx/modx-',
    'https://modx.com/download/direct/modx-',
];
// имя сохраняемого архива дистрибутива
$zip_name = 'modx.zip';
// список файлов для модификации/добавления (пути относительно папки установщика - setup)
$files_modify = [
    'index.php',
    'controllers/install.php',
];


// Функция загрузки последней версии дистрибутива
function downloadLastVersion($version_url, $urls, $file_path)
{
    // определяем название последней версии
    $version = file_get_contents($version_url);

    // проходим массив ссылок и пробуем скачать дистрибутив нужной версии
    foreach ($urls as $url) {
        // дополняем ссылку данными о версии и разширении скачиваемого файла
        $url .= $version . '-pl-advanced.zip';

        $fp = fopen($file_path, 'w+');
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        //curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_exec($ch);

        curl_close($ch);
        fclose($fp);

        if (filesize($file_path) > 0) return $version;
    }
    // если мы не вышли из цикла выше, то значит не удалось скачать, поэтому возвращаем false
    return false;
}

// Функция распаковки файлов дистрибутива из архива
function unzip($file)
{
    $amode = 0775;
    if (!extension_loaded('zip')) {
        if (strtoupper(substr(PHP_OS, 0, 3) == 'WIN')) {
            if (!@dl('php_zip.dll')) return 0;
        } else {
            if (!@dl('zip.so')) return 0;
        }
    }

    $zip = zip_open($file);
    if ($zip) {
        $old_umask = umask(0);
        while ($zip_entry = zip_read($zip)) {
            if (zip_entry_filesize($zip_entry) > 0) {
                $complete_path = dirname(zip_entry_name($zip_entry));
                $complete_name = zip_entry_name($zip_entry);
                $complete_name_arr = explode('/', $complete_name);
                $complete_name = str_replace($complete_name_arr[0] . '/' . $complete_name_arr[1] . '/', '', $complete_name);

                if (!file_exists($complete_path) && strpos($complete_path, 'core') === false) {
                    $tmp = '';
                    foreach (explode('/', $complete_path) as $i => $k) {
                        if ($i < 2) continue;
                        $tmp .= $k . '/';
                        if (!file_exists($tmp)) {
                            @mkdir($tmp, $amode);
                        } else chmod($tmp, $amode);
                    }
                }
                if (zip_entry_open($zip, $zip_entry, 'r')) {
                    if ($tmp !== null && $fd = fopen($complete_name, 'w')) {
                        fwrite($fd, zip_entry_read($zip_entry, zip_entry_filesize($zip_entry)));
                        fclose($fd);
                    }
                    zip_entry_close($zip_entry);
                }

            }
        }
        umask($old_umask);
        zip_close($zip);
        return true;
    }
    zip_close($zip);
}

// 1. загрузка последней версии дистрибутива
$result = downloadLastVersion($version_url, $distibutive_urls, $zip_name);
if ($result === false) exit('Не удалось скачать архив дистрибутива последней версии. Проверьте настройки в файле миграции (ссылки для скачивания, пути и т.п.), а также на разрешения для запускаемого скрипта и места сохранения архива.');
else $version = $result; // иначе если в качестве результата вернулась версия, то запишим ее в переменную

if (!file_exists($zip_name)) exit('Не удалось открыть архив. Проверьте правильность его пути и имени, а также разрешения на папку.');


// 2. распаковка файлов дистрибутива из архива
$result = unzip($zip_name);
if ($result === false) exit('Не удалось распаковать архив дистрибутива.');
unlink($zip_name);

// 3. модификация (костомизация) пакета
// находим нужные файлы, которые будем модифицировать
foreach ($files_modify as $file) {
    if (!file_exists($file)) echo 'В новом дистрибутиве не найден файл ' . $file . '. Если это новый файл, который добавляем в дистрибутив, то можно проигнорировать данное сообщение. Иначе проверьте совместимость версий, обновите костомизацию и запустите повторно пересборку дистрибутива.';
    else $file_content = file_get_contents($file);

    $modify_content = '';

    switch ($file) {
        case 'index.php':
            $modify_content = str_replace('<?php', '<?php
if (stripos($_SERVER[\'HTTP_HOST\'], \'.dev\') === false) die(\'<html><head><title></title></head><body><h1>FATAL ERROR: MODX Migrate cannot continue in <span style="color:red">PRODACTION SERVER</span>! No-no-no</h1></body></html>\');
                    ', $file_content);
            $modify_content = str_replace('$modInstall = new modInstall();', '
// меняем права на файлы и папки ядра и установщика
if (!$_REQUEST[\'action\']) {
    if (!include_once(MODX_SETUP_PATH . \'includes/modinstallPermissions.class.php\')) {
        die(\'<html><head><title></title></head><body><h1>FATAL ERROR: MODX Migrate cannot continue.</h1><p>Make sure you have uploaded all of the migrate/ files; your migrate/includes/modinstallPermissions.class.php file is missing.</p></body></html>\');
    }
    $files_distr = new modinstallPermissions();
    $files_distr->changePermissions();
}

$modInstall = new modInstall();
                    ', $modify_content);
            break;
        case 'controllers/install.php':
            $modify_content = str_replace('return $parser->render(\'install.tpl\');', '
// Импортируем файл базы данных
if (!include(MODX_SETUP_PATH . \'includes/modMigrateToDev.class.php\')) {
    die(\'<html><head><title></title></head><body><h1>FATAL ERROR: MODX Migrate cannot continue.</h1><p>Make sure you have uploaded all of the migrate/ files; your migrate/includes/modMigrateToDev.class.php file is missing.</p></body></html>\');
}
$migrate = new modMigrateToDev($install);
var_dump($migrate);
$migrate->migrate();
// Конец импорта

return $parser->render(\'install.tpl\');
                    ', $file_content);
            break;
        default:
            // просто скачиваем подготовленные наши файлы и размещаем их по нужным путям
            // их нет в стандартном резозитории modx, поэтому не заморачиваемся
            $modify_content = file_get_contents($file);
    }

    if (!empty($modify_content)) {
        $file_handler = fopen($file, 'w');
        fputs($file_handler, $modify_content);
        fclose($file_handler);
    }
}

unset($modify_content);
exit('Кастомизация пакета установки завершена');