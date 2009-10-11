<?php
/** 
 * phpman_Setup_postinstall
 *
 * @author      sotarok
 */

require_once 'phpman/Command.php';

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
        $this->command = new phpman_Command();

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

            try {
                $this->command->sync(compact('lang', 'mirror'));
            }
            catch (Exception $e) {
                $this->_ui->outputData($e->getMessage());
                return false;
            }

            return true;
        }
        return false;

        $this->_ui->outputData('ERROR: Unknown parameter group <'.$param_group.'>.');
        return false;
    }
}
