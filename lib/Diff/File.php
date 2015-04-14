<?php
class Diff_File extends Diff_Abstract
{
    protected $_url = '/file';
    protected $_isForced = 0;

    public function __construct()
    {
        $class = '';
        if (!isset($_POST['diff']) || empty($_POST['diff'])) {$class = 'hello';}
        $this->_HTML = new Html('Files', $class);
        parent::__construct();
        return true;
    }

    public function renderForm($ee = '', $supee = '', $comparePath = '')
    {
        $entities = $this->_getDirectoryList();

        $form = '<form method="post" action="' . BU . $this->tools['file']['url'] . '">Show diff of ';
        $form .= $this->_renderSelect('currentPath', 'diff[compare_path]', $this->_config['core_paths'], $comparePath)
            . ' between ';
        unset($entities['all']);
        foreach ($entities as $entityType=>$items) {
            if ($entityType == 'supee') {
                $s = $supee; $form .= ' and ';
                $tempItems = $items;
                $items = array();
                $path = $this->_config['scan_dir'];
                foreach($tempItems as $i) {
                    $v = $this->_getMagentoVersion($path . $i);
                    $items[] = array(
                        'name' => $i . ($v ? ' [' . $v . ']' : ''),
                        'value' => $i
                    );
                }
            } else {
                $s = $ee;
            }
            $form .= $this->_renderSelect('entity' . $entityType, 'diff[' . $entityType . ']', $items, $s);

        }
        $form .= ' <input type="submit" value="Submit" class="btn btn-primary"></form>';

        return $form;
    }

    protected function _renderForm($ee = '', $supee = '', $comparePath = '', $page = 'hello')
    {
        $form = $this->renderForm($ee, $supee, $comparePath);
        $html = $this->_renderToolbar($form, $page);
        return $html;
    }

    protected function _sanitize($input)
    {
        return preg_replace('/[\.\(\)\-\_]/', '', $input);
    }

    public function run()
    {
        $diffStorage = BP . DS . 'var' . DS . 'diffs';
        //$diffXclude = ' ';//'-x \'*.txt\'';
        $diffOptions = "-ENwbur";
        $diffIgnore = "--ignore-matching-lines='Copyright (c)' --exclude=.svn";
        $diffCommands = "$diffOptions $diffIgnore";

        $path0 = $path1 = $corePath = '';

        if (
            $_POST && isset($_POST['diff']) && !empty($_POST['diff'])
            && isset($_POST['diff']['compare_path']) && !empty($_POST['diff']['compare_path'])
            && isset($_POST['diff']['ee']) && !empty($_POST['diff']['ee'])
            && isset($_POST['diff']['supee']) && !empty($_POST['diff']['supee'])
        ) {
            $d = $_POST['diff'];
            $path0 = $this->_config['scan_dir'] . $d['ee'] . DS . $d['compare_path'];
            $path1 = $this->_config['scan_dir'] . $d['supee'] . DS . $d['compare_path'];
            $corePath = trim(preg_replace('/([\/])/', '-', $d['compare_path']), '-');
        }

        if (empty($path0) || empty($path1)) {
           echo $this->_renderForm();
        } elseif (!empty($path0) && !empty($path1) && !empty($d)) {
           echo $this->_renderForm($d['ee'], $d['supee'], $d['compare_path'], 'result');

            echo '<div class="container">';

            $pathParts0 = explode(DS, $path0);
            $pathParts1 = explode(DS, $path1);

            $uniquePathParts = array('f' => array(), 's' => array());
            $duplicatePathParts = array('b' => array(), 'a' => array());
            $diffName = 'diff' . $diffOptions;
            foreach ($pathParts0 as $i => $el) {
                if ($el != $pathParts1[$i]) {
                    $uniquePathParts['f'][] = $el;
                    $uniquePathParts['s'][] = $pathParts1[$i];
                    $diffName .= '_' .$this->_sanitize($el) . '-' .$this->_sanitize($pathParts1[$i]);
                } elseif (empty($uniquePathParts['f'])) {
                    $duplicatePathParts['b'][] = $el;
                }
            }
            $diffName .= '_' . $corePath;
            $diffName .= '.diff';
            $diffPath = $diffStorage . DS . $diffName;
            $beforePath = implode(DS, $duplicatePathParts['b']);

            $uniqueNameFirst = implode(DS, $uniquePathParts['f']);
            $uniqueNameSecond = implode(DS, $uniquePathParts['s']);

            if (!file_exists($diffPath) or $this->_isForced) {
                $cmd = "diff $diffCommands $path0 $path1 > $diffPath";
                //echo $cmd . '<br>';
                exec($cmd);

                if (!file_exists($diffPath)) {
                    exit('Unable to create diff file');
                }
            }

            $diff = file_get_contents($diffPath);
            $diffData = explode("diff $diffCommands", $diff);

            $i = 0;

            foreach ($diffData as $dFile) {
                $dFile = trim($dFile);
                if (empty($dFile)) {
                    continue;
                }

                $dFileStrings = explode("\n", $dFile);

                $files = explode(' ', str_replace($beforePath . DS, '', $dFileStrings[0]));
                $paths = explode($uniqueNameFirst, $files[0]);

                unset($dFileStrings[0]);
                unset($dFileStrings[1]);
                unset($dFileStrings[2]);

                echo '<div class="jumbotron">';
                echo '<h4><strong>' . $paths[1] . '</strong></h4>';
                foreach ($dFileStrings as $string) {
                    $countA = $countD = 0;
                    $string = preg_replace('/^(\+){1}/ui', ' ', $string, -1, $countA);
                    $string = preg_replace('/^(\-){1}/ui', ' ', $string, -1, $countD);

                    echo '<div class="diff-string' . (($countA > 0) ? '-added' : (($countD > 0) ? '-deleted' : '')) . '">'
                        . str_replace(' ', '&nbsp;', htmlspecialchars($string)) . '&nbsp;</div>';
                }
                echo '</div>';

                if ($i == 10) {
                    break;
                }

                $i++;
            }
            echo '</div>';
        }
    }
}