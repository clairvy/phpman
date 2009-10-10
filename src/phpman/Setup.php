<?php
/** 
 * phpman_Setup_postinstall
 *
 * @author      sotarok
 */

class phpman_Setup_postinstall
{
    /**
     * PEAR_Installer_Ui
     *
     * @var object(PEAR_Installer_Ui)
     * @access protected
     */
    private $_config;
    private $_ui;

    function init(&$config, $self, $last_installed_version = null)
    {
        $this->_config = &$config;
        $this->_ui = &PEAR_Frontend::singleton();

        return true;
    }

    function run($options, $param_group)
    {
        if ('_undoOnError' == $param_group) {
            $this->_ui->outputData('An error occured during installation.');
            return false;
        }

        if ($param_group == 'downloadhtml') {
            $mirror          = $options['mirror'];
            $lang            = $options['lang'];
            $download_dir    = $this->_config->get('download_dir');
            $data_dir_phpman = $this->_config->get('data_dir') . '/phpman/';
            //$tmp_download_file = $download_dir . '/php_manual.tar.gz';
            $file_url = 'http://' . $mirror . '/get/php_manual_' . $lang . '.tar.gz/from/this/mirror';


            require_once 'PEAR/Downloader.php';
            require_once 'Archive/Tar.php';

            $downloader = new PEAR_Downloader($this->_ui, array(), $this->_config);
            $file = $downloader->downloadHttp($file_url,  $this->_ui, $download_dir);
            if (PEAR::isError($file)) {
                $this->_ui->outputData('failed to download manual file: ' . $file_url);
                return false;
            }
            $this->_ui->outputData('Complete to download manual file.');

            $tar = new Archive_Tar($file);
            if (!$tar->extract($data_dir_phpman)) {
                $this->_ui->outputData('failed to extract file: ' . $file);
                return false;
            }

            $this->_ui->outputData('Complete to extract manual files.');
            return true;
        }
        return false;

        $this->_ui->outputData('ERROR: Unknown parameter group <'.$param_group.'>.');
        return false;
    }
}
