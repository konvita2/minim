<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 24.09.2015
 * Time: 9:44
 */

require_once('min-js.php');

class Compressor {

    // пути к файлам соответствующих типов
    private $dir_css = '';
    private $dir_js = '';

    // режим работы минимизатора
    // 'debug' - ничего не минимизирует, грузит как есть: неужатые отдельные файлы
    // 'prod' - грузит минимизированные файлы
    private $mode = '';

    /**
     * @param $dir_css
     * @param $dir_js
     * @param string $mode - режим работы минимизатора (по умолчанию не ужимаем)
     */
    public function __construct($dir_css, $dir_js, $mode = 'debug'){
        $this->dir_css = $this->add_slash($dir_css);
        $this->dir_js = $this->add_slash($dir_js);
        $this->mode = strtolower($mode);
    }

    private function add_slash($ss){
        $s = preg_replace("#/$#", "", $ss) . "/";
        return $s;
    }

    public function set_mode($mode){
        $this->mode = $mode;
    }

    public function get_mode(){
        return $this->mode;
    }

    public function load_css($files){
        $ext = "css";
        if($this->mode == "debug"){
            foreach($files as $fn){
                // можем писать как с расширением файла так и без него
                // отработаем этот случай
                if(substr($fn, -(strlen($ext)+1)) != '.' . $ext){
                    $fn .= '.' . $ext;
                }
                echo '<link rel="stylesheet" href="' . $this->dir_css . $fn . '">';
            }
        }
        else{
            // сгенерировать имя файла
            $compfn = $this->get_full_md5fn($this->dir_css, $files, 'css');

            // проверить наличие такого файла
            if(file_exists($compfn)){
                if($this->need_to_rebuild($this->dir_css, $files, 'css')){
                    // rebuild
                    $this->rebuild_css($this->dir_css, $files, 'css');
                }
                // просто выводим
                echo '<link rel="stylesheet" href="' . $compfn . '">';
            }
            else{
                // rebuild
                $this->rebuild_css($this->dir_css, $files, 'css');
                // просто выводим
                echo '<link rel="stylesheet" href="' . $compfn . '">';
            }
        }
    }

    public function load_js($files){
        $ext = "js";
        if($this->mode == "debug"){
            foreach($files as $fn){
                // можем писать как с расширением файла так и без него
                // отработаем этот случай
                if(substr($fn, -(strlen($ext)+1)) != '.' . $ext){
                    $fn .= '.' . $ext;
                }
                echo '<script type="text/javascript" src="' . $this->dir_js . $fn . '"></script>';
            }
        }
        else{
            // сгенерировать имя файла
            $compfn = $this->get_full_md5fn($this->dir_js, $files, 'js');

            // проверить наличие такого файла
            if(file_exists($compfn)){
                if($this->need_to_rebuild($this->dir_js, $files, 'js')){
                    // rebuild
                    $this->rebuild_js($this->dir_js, $files, 'js');
                }
                // просто выводим
                echo '<script type="text/javascript" src="' . $compfn . '"></script>';
            }
            else{
                // rebuild
                $this->rebuild_js($this->dir_js, $files, 'js');
                // просто выводим
                echo '<script type="text/javascript" src="' . $compfn . '"></script>';
            }
        }

    }

    /**
     * сгенерировать имя файла по массиву с именами файлов
     * @param $files
     * @return string
     */
    private function get_md5fn($files){
        $s = implode($files);
        return md5($s);
    }

    /**
     * сгенерировать полное имя файла с учетом директории и расширения
     * @param $dir
     * @param $files
     * @param $ext
     * @return string
     */
    private function get_full_md5fn($dir, $files, $ext){
        $compfn = $dir . $this->get_md5fn($files) . '.' . $ext;
        return $compfn;
    }

    /**
     * Определить надо ли перестроить заново ужатый файл с расширением ext
     * созданный по
     * файлам files в папке dir
     *
     * @param $dir
     * @param $files
     * @param $ext
     * @return bool
     */
    private function need_to_rebuild($dir, $files, $ext){
        $need = false;

        // сгенерировать имя минимизированного файла
        $compfn = $this->get_full_md5fn($dir, $files, $ext);

        // получить дату модификации этого файла
        $comptime = filemtime($compfn);

        // сравнить дату модификации с датами файлов из массива
        foreach ($files as $file) {
            if(substr($file, -(strlen($ext) + 1)) != '.' . $ext){
                $file .= '.' . $ext;
            }

            $ftime = filemtime($dir . $file);

            if($ftime > $comptime){
                $need = true;
                break;
            }
        }

        return $need;
    }

    /**
     * Построить сводный сжатый файл типа ext из файлов files (массив имен файлов)
     * в папке dir
     * @param $dir
     * @param $files
     * @param $ext
     */
    private function rebuild_css($dir, $files, $ext){
        $compfile = $this->get_full_md5fn($dir, $files, $ext);

        if(file_exists($compfile)){
            unlink($compfile);
        }

        $fout = fopen($compfile, "w+");

        foreach ($files as $file) {
            if(substr($file, -(strlen($ext) + 1)) != '.' . $ext){
                $file .= '.' . $ext;
            }
            $ff = fopen($dir . $file, "r");
            $buf = fread($ff, filesize($dir . $file));

            /* удалить комментарии и пробелы*/
            $buf = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buf);
            $buf = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $buf);

            fwrite($fout, $buf);
        }
        fclose($fout);
    }


    /**
     * Построить сводный сжатый файл типа ext из файлов files (массив имен файлов)
     * в папке dir
     * @param $dir
     * @param $files
     * @param $ext
     */
    private function rebuild_js($dir, $files, $ext){
        $compfile = $this->get_full_md5fn($dir, $files, $ext);

        if(file_exists($compfile)){
            unlink($compfile);
        }

        $JSqueeze = new JSqueeze();

        $fout = fopen($compfile, "w+");
        foreach ($files as $file) {
            if(substr($file, -(strlen($ext) + 1)) != '.' . $ext){
                $file .= '.' . $ext;
            }
            $ff = fopen($dir . $file, "r");
            $buf = fread($ff, filesize($dir . $file));

            /* удалить комментарии и пробелы*/
            //$buf = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buf);
            //$buf = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $buf);

            $buf = $JSqueeze->squeeze($buf, true, false);

            fwrite($fout, $buf . ' ');
        }
        fclose($fout);
    }

} 