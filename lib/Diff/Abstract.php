<?php

abstract class Diff_Abstract
{
    /** @var PDO  */
    protected $_db = null;
    protected $_config = array();
    protected $_dbList = false;
    protected $_directoryList = false;
    protected $_defaultConfig = false;
    protected $_HTML = null;

    public $tools = array(
        'db' => array('class' => 'Diff_Db','url' => 'db', 'name' => 'Config diff', 'desc' => 'shows all non-default configurations set in DB'),
        'file' => array('class' => 'Diff_File', 'url' => 'file', 'name' => 'Files diff', 'desc' => 'shows changes of core files'),
        'triggers' => array('class' => 'Diff_Triggers', 'url' => 'triggers', 'name' => 'Triggers diff', 'desc' => 'shows differences in statements DB triggers'),
        'logs' => array('class' => 'Diff_Logs', 'url' => 'logs', 'name' => 'Logs diff', 'desc' => 'shows differences in between MySQL logs'),
    );

    public function __construct()
    {
        global $config;
        $this->_config = $config;
        $this->_dbConnect();
    }

    protected function _getMagentoVersion($magentoRoot)
    {
        $Mage = $magentoRoot . '/app/Mage.php';
        if (!file_exists($Mage)) {
            return '';
        }

        $content = file_get_contents($Mage);
        $pattern = '/getVersionInfo\(\)\r*\n*\s*\{\r*\n*\s+return\s([\na-z0-9\(\)\s\'\=\>\,\;]+)/';
        preg_match($pattern, $content, $matches);

        if (empty($matches[1])) {
            return '';
        }

        $versionArrayRaw = $matches[1];

        $pattern = '/[0-9]+/';
        preg_match_all($pattern, $versionArrayRaw, $matches);

        if (empty($matches[0])) {
            return '';
        }

        $v = $matches[0];

        $version = implode('-', $v);

        return $version;
    }

    protected function _renderNavButton()
    {
        $buttons = '';
        $descriptions = '';
        foreach ($this->tools as $t) {
            $buttons .= '<li><a href="' . $t['url'] . '">' . $t['name'] . '</a></li>';
            $descriptions .= '<strong>' . $t['name'] . '</strong> - ' . $t['desc'] . '<br/>';
        }
        $html = '
<div class="btn-group">
  <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
    Magento Diff Tool <span class="caret"></span>
  </button>
  <ul class="dropdown-menu" role="menu"><li><a href="' . BU . '">Home</a></li><li class="divider"></li>' . $buttons . '</ul>
</div>
        ';
        return $html;
    }

    protected function _renderToolbar($innerHtml = '', $class = 'result', $renderNavButton = true)
    {
        $html = '<div class="container"><div class="diff-control-' . $class . '">'
            . ($renderNavButton ? $this->_renderNavButton() : '')
            . $innerHtml
            . '</div></div>';
        return $html;
    }

    protected function _renderSelect($id, $name, $items, $selected = '')
    {
        if ($selected == '') {
            $selected = current($items);
        }
        if (is_array($selected)) {
            $selected = $selected['name'];
        }

        $html = '<input id="' . $id . '_input" type="hidden" name="' . $name . '" value="' . $selected . '">';
        $html .= '
        <div class="btn-group">
          <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
            <span id="' . $id . '">' . $selected . '</span> <span class="caret"></span>
          </button>
          <ul class="dropdown-menu" role="menu">';


        foreach ($items as $v) {
            if (is_array($v)) {
                $html .= '<li><a onclick="select(\'#' . $id . '\', this)" data-value="' . $v['value'] . '">' . $v['name'] . '</a></li>';
            } else {
                $html .= '<li><a onclick="select(\'#' . $id . '\', this)" data-value="' . $v . '">' . $v . '</a></li>';
            }
        }

        $html .= '</ul></div>';

        return $html;
    }

    protected function _dbConnect()
    {
        $db = $this->_config['db'];

        try {
            $PDO = new PDO('mysql:host=' . $db['host'], $db['user'], $db['pass']);
        } catch (PDOException $e) {
            exit ('<h1>' . $e->getCode() . ': ' .$e->getMessage() . '</h1>');
        }

        $this->_db = $PDO;
    }

    protected function _getDbList($filter = '')
    {
        if ($this->_dbList === false) {
            $databases = $this->_db->query('show databases')->fetchAll(PDO::FETCH_OBJ);

            $dbs = array();
            foreach ($databases as $db) {
                $dbs[] = $db->Database;
            }

            $databasesRaw = '|' . implode('|', $dbs) . '|';
            $eePattern = '/\|(' . preg_quote($this->_config['db']['ee_prefix']) . '[0-9a-z\.\-]+)/ui';
            preg_match_all($eePattern, $databasesRaw, $matchesEE);

            $supeePattern = '/\|(' . preg_quote($this->_config['db']['supee_prefix']) . '[0-9a-z\.\-]+)/ui';
            preg_match_all($supeePattern, $databasesRaw, $matchesSupEE);

            $this->_dbList = array(
                'all' => $dbs,
                'ee' => $matchesEE[1],
                'supee' => $matchesSupEE[1]
            );
        }

        if (!empty($filter) && isset($this->_dbList[$filter]) && !empty($this->_dbList[$filter])) {
            return $this->_dbList[$filter];
        }

        return $this->_dbList;
    }

    protected function _getDirectoryList($filter = '')
    {
        if ($this->_directoryList === false) {
            $scanDirResult = scandir($this->_config['scan_dir']);

            if (!is_array($scanDirResult) || !$scanDirResult) {
                exit('Error occurred');
            }

            $scanDirPlain = '|' . implode('|', $scanDirResult) . '|';

            $eePattern = '/\|(' . preg_quote($this->_config['ee_prefix']) . '[0-9a-z\.\-]+)/ui';
            preg_match_all($eePattern, $scanDirPlain, $matchesEE);

            $supeePattern = '/\|(' . preg_quote($this->_config['supee_prefix']) . '[0-9a-z\.\-]+)/ui';
            preg_match_all($supeePattern, $scanDirPlain, $matchesSupEE);

            $this->_directoryList = array(
                'all' => $scanDirResult,
                'ee' => (isset($matchesEE[1]) && !empty($matchesEE[1])) ? $matchesEE[1] : array(),
                'supee' => (isset($matchesSupEE[1]) && !empty($matchesSupEE[1])) ? $matchesSupEE[1] : array(),
            );

            if ($this->_config['diff_file__add_ee_to_supee']) {
                $this->_directoryList['supee'] = array_merge($this->_directoryList['ee'], $this->_directoryList['supee']);
            }
        }

        if (!empty($filter) && isset($this->_directoryList[$filter]) && !empty($this->_directoryList[$filter])) {
            return $this->_directoryList[$filter];
        }

        return $this->_directoryList;
    }

    protected function _isUnserializable($string = '')
    {
        $data = @unserialize($string);
        if (is_array($data)) {
            return true;
        }
        return false;
    }
}