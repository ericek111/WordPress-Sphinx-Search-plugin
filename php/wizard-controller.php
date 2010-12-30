<?php
/*
    Copyright 2008  &copy; Ivinco LTD  (email : opensource@ivinco.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class WizardController
{
    /**
     * Special object to get/set plugin configuration parameters
     * @access private
     * @var SphinxSearch_Config
     * 
     */
    private $_config = null;

    private $view = null;

    public function  __construct(SphinxSearch_Config $config)
    {
        $this->view = new SphinxView();
        $this->_config = $config;
        $this->view->assign('header', 'Sphinx Search :: Wizard');
    }

    public function stop_action()
    {
        $options['wizard_done'] = 'true';
        $this->_config->update_admin_options($options);
        return $this->_next_action('config');
    }

    public function start_action()
    {
        if (!empty($_POST['start_process'])){
            //$sphinxService = new SphinxService($this->_config);
            //$res = $sphinxService->stop();

            $options['wizard_done'] = 'false';
            $this->_config->update_admin_options($options);
            return $this->_next_action('start');
        }
        $this->view->render('admin/wizard_layout.phtml');
    }

    public function connection_action()
    {
        if (!empty($_POST['skip_wizard_connection'])){
            $this->view->success_message = 'Step was skipped.';
            return $this->_next_action('connection');
        }
        if (!empty($_POST['connection_process'])){
            if (empty($_POST['sphinx_host']) ||
                empty($_POST['sphinx_port']) ||
                empty($_POST['sphinx_index'])){
                $this->view->error_message = 'Connection parameters can\'t be empty';                
                $this->view->sphinx_host = $_POST['sphinx_host'];
                $this->view->sphinx_port = $_POST['sphinx_port'];
                $this->view->sphinx_index = $_POST['sphinx_index'];                
                $this->view->render('admin/wizard_sphinx_connection.phtml');
             } else {
                $this->_set_sphinx_connect();
                $this->view->success_message = 'Connection parameters successfully set.';
                return $this->_next_action('connection');
             }
        } else {
            $this->view->sphinx_host = $this->_config->get_option('sphinx_host');
            $this->view->sphinx_port = $this->_config->get_option('sphinx_port');
            $this->view->sphinx_index = $this->_config->get_option('sphinx_index');
            $this->view->render('admin/wizard_sphinx_connection.phtml');
        }
        exit;
    }

    public function detection_action()
    {
        $detect_system_searchd = $this->detect_program('searchd');
        $detect_system_indexer = $this->detect_program('indexer');
        $detect_installed_searchd = $this->_config->get_option('sphinx_searchd');
        $detect_installed_indexer = $this->_config->get_option('sphinx_indexer');
        if (!file_exists($detect_installed_searchd)){
            $detect_installed_searchd = '';
        }
        if (!file_exists($detect_installed_indexer)){
            $detect_installed_indexer = '';
        }

        $this->view->detect_system_searchd = $detect_system_searchd;
        $this->view->detect_system_indexer = $detect_system_indexer;
        $this->view->detect_installed_searchd = $detect_installed_searchd;
        $this->view->detect_installed_indexer = $detect_installed_indexer;
        $this->view->install_path = SPHINXSEARCH_SPHINX_INSTALL_DIR;

        if (!empty($_POST['skip_wizard_detection'])){
            if ( empty($detect_installed_searchd) ||
                 empty($detect_installed_indexer) ){
                $this->view->success_message = 'Sphinx is not installed. All step was skipped.';
                return $this->_next_action('config');
                exit;
            } else {
                $this->view->success_message = 'Step was skipped.';
                return $this->_next_action('detection');
            }
        }
        

        if (!empty($_POST['detection_process'])){
            if ('install' == $_POST['detected_install']){                
                $sphinxService = new SphinxService($this->_config);
                $sphinxService->stop();
                
		$sphinxInstall = new SphinxSearch_Install($this->_config);
		$res = $sphinxInstall->install();
                if (true === $res){
                    $this->view->success_message = 'Sphinx successfully installed.';
                    return $this->_next_action('install');
                } else {
                    $this->view->error_message = $res['err'];
                     $this->view->render('admin/wizard_sphinx_detect.phtml');
                    exit;
                }
            } else if('detect_system' == $_POST['detected_install']) {
                if (empty($_POST['detected_system_searchd']) ||
                    empty($_POST['detected_system_indexer'])){
                    $this->view->error_message = 'Path to searchd or indexer can\'t be empty';
                    $this->view->render('admin/wizard_sphinx_detect.phtml');
                    exit;
                } else {
                    $this->_set_sphinx_detected($_POST['detected_system_searchd'], $_POST['detected_system_indexer']);
                    $this->view->success_message = 'Sphinx binaries are set.';
                    return $this->_next_action('detection');
                }
            } else if('detect_installed' == $_POST['detected_install']) {
                if (empty($_POST['detected_installed_searchd']) ||
                    empty($_POST['detected_installed_indexer'])){
                    $this->view->error_message = 'Path to searchd or indexer can\'t be empty';
                    $this->view->render('admin/wizard_sphinx_detect.phtml');
                    exit;
                } else {
                    $this->_set_sphinx_detected($_POST['detected_installed_searchd'], $_POST['detected_installed_indexer']);
                    $this->view->success_message = 'Sphinx binaries are set.';
                    return $this->_next_action('detection');
                }
            }
        } 
        $this->view->render('admin/wizard_sphinx_detect.phtml');
        exit;
    }

    public function folder_action()
    {
        if (!empty($_POST['skip_wizard_folder'])){
            $this->view->success_message = 'Step was skipped.';
            return $this->_next_action('folder');
        }
        if (!empty($_POST['folder_process'])){
            $sphinx_install_path = $_POST['sphinx_path'];
            if (empty($sphinx_install_path)) {
                $error_message = 'Path can\'t be empty';
            }
            if (!file_exists($sphinx_install_path)){
                @mkdir($sphinx_install_path);
            }
            if (!file_exists($sphinx_install_path)){
                $error_message = 'Path '.$sphinx_install_path.' does not exist!';
            } else if (!is_writable($sphinx_install_path)){
                $error_message = 'Path '.$sphinx_install_path.' is not writeable!';
            } else {
                $this->_setup_sphinx_path();
                $config_file_name = $this->_generate_config_file_name();
                $config_file_content = $this->_generate_config_file_content();
                $this->_save_config($config_file_name, $config_file_content);

                if (!file_exists($sphinx_install_path.'/var')){
                    mkdir($sphinx_install_path.'/var');
                }
                if (!file_exists($sphinx_install_path.'/var/data')){
                    mkdir($sphinx_install_path.'/var/data');
                }
                if (!file_exists($sphinx_install_path.'/var/log')){
                    mkdir($sphinx_install_path.'/var/log');
                }
                //setup cronjob files
                $sphinx_install = new SphinxSearch_Install($this->_config);
                $sphinx_install->setup_cron_job();

                $this->view->success_message = 'Path is set';
                return $this->_next_action('folder');
            }

            $this->view->error_message = $error_message;            
            $this->view->install_path = $_POST['sphinx_path'];
        } else {
            $this->view->install_path = SPHINXSEARCH_SPHINX_INSTALL_DIR;
        }
        $this->view->render('admin/wizard_sphinx_folder.phtml');
        exit;
    }

    public function config_action()
    {
        if (!empty($_POST['skip_wizard_config'])){
            $this->view->success_message = 'Step was skipped.';
            return $this->_next_action('config');
        }
        if (!empty($_POST['config_process'])){        
            return $this->_next_action('config');
        }
        $this->view->config_content = $this->_generate_config_file_content();
        $this->view->sphinx_conf = $this->_config->get_option('sphinx_conf');
        $this->view->render('/admin/wizard_sphinx_config.phtml');
        exit;
    }

    public function finish_action()
    {
        $sphinxService = new SphinxService($this->_config);
        $res = $sphinxService->start();
        $options['wizard_done'] = 'true';
        $this->_config->update_admin_options($options);
        $this->view->render('/admin/wizard_sphinx_finish.phtml');
        exit;
    }

    public function indexing_action()
    {
        if (!empty($_POST['skip_wizard_indexsation'])){
            $this->view->success_message = 'Step was skipped.';
            return $this->_next_action('indexing');
        }

        if (!empty($_POST['process_indexing'])){
            $sphinxService = new SphinxService($this->_config);
            $res = $sphinxService->reindex();
            if (true === $res){
                $this->view->indexsation_done = true;
                $this->view->success_message = 'Indexing is done successfully!';
                return $this->_next_action('indexing');
            } else {
                $this->view->error_message = $res['err'];
            }
        }
        $this->view->indexsation_done = false;
        $this->view->render('admin/wizard_sphinx_indexsation.phtml');
        exit;
    }

     public function detect_program($progname)
     {
         $progname = escapeshellcmd($progname);
         $res = exec("whereis {$progname}");
         if (!preg_match("#{$progname}:\s?([\w/]+)\s?#", $res, $matches)) {
            return false;
         }
         return $matches[1];
     }

    private function _set_sphinx_connect()
    {
        $options['sphinx_host'] = $_POST['sphinx_host'];
        $options['sphinx_port'] = $_POST['sphinx_port'];
        $options['sphinx_index'] = $_POST['sphinx_index'];
        $this->_config->update_admin_options($options);
        return true;
    }

    private function _generate_config_file_name()
     {
         $options = $this->_config->get_admin_options();
         $filename = $options['sphinx_path'].'/sphinx.conf';
         file_put_contents($filename, '');
         $options['sphinx_conf'] = $filename;
         $this->_config->update_admin_options($options);
         return $filename;
     }

     private function _generate_config_file_content()
     {
         $config_tempate = file_get_contents(SPHINXSEARCH_PLUGIN_DIR.'/rep/sphinx.conf');

         $sphinxInst = new SphinxSearch_Install($this->_config);
         $content = $sphinxInst->generate_config_content($config_tempate);

         return $content;
     }
     private function _save_config($filename, $content)
     {
         file_put_contents($filename, $content);
     }





     private function _setup_sphinx_path()
     {
         $options['sphinx_path'] = $_POST['sphinx_path'];
         $this->_config->update_admin_options($options);
         return true;

     }

    

    private function _set_sphinx_detected($searchd, $indexer)
    {
        $options['sphinx_searchd'] = $searchd;
        $options['sphinx_indexer'] = $indexer;
        $this->_config->update_admin_options($options);
        return true;
     }



    private function _next_action($prevAction)
    {
        switch ($prevAction) {
            case 'start':
                $this->connection_action();
                break;
            case 'connection':
                $this->detection_action();
                break;
            case 'install':
                case 'folder':
                $this->indexing_action();
                break;
            case 'indexing':
                $this->config_action();
                break;
            case 'config':
                $this->finish_action();
                break;
            case 'detection':
                $this->folder_action();
                break;
            default:
                $this->start_action();
                break;
        }
    }

}