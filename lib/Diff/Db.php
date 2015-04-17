<?php

class Diff_Db extends Diff_Abstract
{
    public function __construct($skipHtmlInit = false)
    {
        if (!$skipHtmlInit) {
            $class = '';
            if (!isset($_POST['diff']) || empty($_POST['diff'])) {
                $class = 'hello';
            }
            $this->_HTML = new Html('Config diff', $class);
        }
        parent::__construct();
        return true;
    }

    protected function _getCoreConfigData($db)
    {
        $this->_db->query('use `' . $db . '`');

        $tables = $this->_db->query('show tables like \'%core_config_data\'')->fetch(PDO::FETCH_NUM);
        if (empty($tables[0])) {
            return false;
        }

        return $this->_db->query('select * from `' . $tables[0] . '` where `value` is not null and `value` not in (\'\', \'a:0:{}\')')->fetchAll(PDO::FETCH_OBJ);
    }

    protected function _getParsedConfigData($db)
    {
        $configData = $this->_getCoreConfigData($db);

        $coreConfig = array();

        foreach ($configData as $c) {
            $xmlParts = explode('/', $c->path);
            $coreConfig[$xmlParts[0]][$c->path][$c->scope . '_' . $c->scope_id] = $c->value;
        }

        $data = $coreConfig;
        return $data;
    }

    protected function _loadString($string)
    {
        if (is_string($string)) {
            $xml = simplexml_load_string($string, 'SimpleXMLElement');

            if ($xml instanceof SimpleXMLElement) {
                return $xml;
            }
        } else {
           exit('"$string" parameter for simplexml_load_string is not a string');
        }
        return false;
    }

    protected function _loadFile($file)
    {
        if (!is_readable($file)) {
            return false;
        }
        $fileData = file_get_contents($file);
        $xml = $this->_loadString($fileData);

        return $xml;
    }

    protected function _xml2array($xml)
    {
        $json = json_encode($xml);
        //$array = json_decode($json, true);
        return json_decode($json, true);
    }

    protected function _getDefaultConfigData($eePath)
    {
        if ($this->_defaultConfig === false) {
            $data = array();

            $scanDir = dirname(BP) . DS . $eePath . DS . 'app/code/*/*/*/etc/' . 'config.xml';
            $files = glob($scanDir);
            foreach ($files as $file) {
                $xml = $this->_loadFile($file);
                if (isset($xml->default) && $xml->default) {
                    $fileData = $this->_xml2array($xml->default);
                    $data = array_merge_recursive($data, $fileData);
                }
            }
            $this->_defaultConfig = $data;
        }

        return $this->_defaultConfig;
    }

    protected function _renderConfigDataChildren($children)
    {
        $html = '';
        foreach ($children as $key=>$child) {
            $html .= '<div style="padding-left: 20px;">' . $key . '=>';
            if (is_array($child) && !empty($child)) {
                $html .= $this->_renderConfigDataChildren($child);
            } elseif (!is_array($child)) {
                $html .= htmlspecialchars($child);
            }
            $html .= '</div>';
        }
        return $html;
    }

    protected function _renderConfigData($eeVersion)
    {
        $data = $this->_getDefaultConfigData($eeVersion . '.lo');
        return $this->_renderConfigDataChildren($data);
    }

    protected function _expandTree($tree, $node)
    {
        $value = null;
        if (isset($tree[$node]) && !empty($tree[$node])) {
            $value = $tree[$node];
        }
        return $value;
    }

    protected function _analyzeConfig($defaultConfigData, $coreConfigData, $all = false)
    {
        $result = array(
            'core' => array(),
            'custom' => array()
        );

        foreach ($coreConfigData as $c) {
            $levels = explode('/', (string)$c->path);

            $node = $defaultConfigData;
            foreach ($levels as $l) {
                $node = $this->_expandTree($node, $l);
            }

            if ($node === null) {
                //custom
                $result['custom'][(string)$c->path][] = array(
                    'value' => $c->value,
                    'default' => '',
                    'scope' => $c->scope . '_' . $c->scope_id,
                );
            } elseif ($node == $c->value) {
                //default value
            } else {
                //non-default
                $result['core'][(string)$c->path][] = array(
                    'value' => $c->value,
                    'default' => $node,
                    'scope' => $c->scope . '_' . $c->scope_id,
                );
            }
        }

        if ($all) {
            return array_merge($result['core'], $result['custom']);
        }

        return $result;
    }

    public function renderForm($ee = '', $supee = '')
    {
        $entitiesEE = $this->_getDirectoryList('ee');
        $entitiesSUPEE = $this->_getDbList('supee');

        $form = '<form method="post" action="' . BU . $this->tools['db']['url'] . '">Show configuration diff between ';
        $form .= $this->_renderSelect('ee', 'diff[ee]', $entitiesEE, $ee);
        $form .= ' and ';
        $form .= $this->_renderSelect('supee', 'diff[supee]', $entitiesSUPEE, $supee);
        $form .= ' <input type="submit" value="Submit" class="btn btn-primary"></form>';

        return $form;
    }

    protected function _renderForm($ee = '', $supee = '', $page = 'hello')
    {
        $form = $this->renderForm($ee, $supee);
        $html = $this->_renderToolbar($form, $page);
        return $html;
    }

    public function run($eeVersion = '', $db = '')
    {
        if (
            $_POST && isset($_POST['diff']) && !empty($_POST['diff'])
            && isset($_POST['diff']['ee']) && !empty($_POST['diff']['ee'])
            && isset($_POST['diff']['supee']) && !empty($_POST['diff']['supee'])
        ) {
            $d = $_POST['diff'];
            $eeVersion = $d['ee'];
            $db = $d['supee'];
        }

        if (empty($d) || empty($eeVersion) || empty($db)) {
            echo $this->_renderForm();
            exit();
        } elseif (!empty($eeVersion) && !empty($db)) {
            echo $this->_renderForm($eeVersion, $db, 'result');
        }


        echo '<div class="container">';
        $defaultConfigData = $this->_getDefaultConfigData($eeVersion);
        $coreConfigData = $this->_getCoreConfigData($db);
        //analyze
        $analyzeResults = $this->_analyzeConfig($defaultConfigData, $coreConfigData, true);

        $analyzeResultsHtml = '<table class="table-bordered table-results">';
        $analyzeResultsHtml .= '<thead><tr>'
            . '<th>path</th>'
            . '<th>value</th>'
            . '<th>default</th>'
            . '<th>scope</th>'
            . '</tr></thead><tbody>';

        //ksort($analyzeResults);
        $entities = $analyzeResults;

        foreach ($entities as $path => $row) {
            $analyzeResultsHtml .= '<tr><td rowspan="' . count($row) . '"><strong>' . $path . '</strong></td>';
            $i = 0;
            foreach ($row as $group) {
                if ($i > 0) { $analyzeResultsHtml .= '</tr><tr>'; }
                $i++;

                foreach ($group as $key => $value) {
                    switch($key) {
                        case 'default': { $style = 'color: green;'; break; }
                        case 'value': { $style = 'color: red; word-break: break-all'; break; }
                        default: { $style = ''; break;}
                    }

                    $analyzeResultsHtml .= '<td style="' . $style . '">';
                    if (is_array($value)) {
                        foreach ($value as $k => $v) {
                            $analyzeResultsHtml .= '<div>' . $k . ' => ' . var_export($v, true) . '</div>';
                        }
                    } elseif (preg_match('/[\:\;\"\{\}]/u', $value) && $this->_isUnserializable($value)) {
                        $analyzeResultsHtml .= '<div>' . var_export(unserialize($value), true) . '</div>';
                    } else {
                        //$analyzeResultsHtml .= mb_substr($value, 0, 50);
                        $analyzeResultsHtml .= preg_replace(array('/\,\s{0,}/u', '/\;\s{0,}/u'), array(', ', '; '), $value);
                    }
                    $analyzeResultsHtml .= '</td>';
                }
            }
            $analyzeResultsHtml .= '</tr>';
        }
        $analyzeResultsHtml .= '</tbody></table>';

        echo $analyzeResultsHtml;
        echo '</div>';
    }
}