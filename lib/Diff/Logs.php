<?php

class Diff_Logs extends Diff_Abstract
{
    protected $_filesData = false;
    protected $_filesDataByHash = false;
    public function __construct()
    {
        $class = '';
        if (!isset($_POST['diff']) || empty($_POST['diff'])) {
            $class = 'hello';
        }
        $this->_HTML = new Html('Logs diff', $class);
        parent::__construct();
        return true;
    }

    public function renderForm($ee = '', $supee = '')
    {
        $form = '<form method="post" action="' . BU .$this->tools['logs']['url'] . '">Show logs diff'
            . '<input type="hidden" name="diff[ee]" value="1">'
            . '<input type="hidden" name="diff[supee]" value="1">'
            . '<input type="file" name="diff[file0]">'
            . '<input type="file" name="diff[file1]">';

        $form .= ' <input type="submit" value="Submit" class="btn btn-primary"></form>';

        return $form;
    }

    protected function _renderForm($ee = '', $supee = '', $page = 'hello')
    {
        $form = $this->renderForm($ee, $supee);
        $html = $this->_renderToolbar($form, $page);
        return $html;
    }

    protected function _getFileNameHash($file)
    {
        return sha1($file);
    }

    protected function _parseLog($file, $groupByConnect = false)
    {
        $fileNameHash = $this->_getFileNameHash($file);

        if (
            !isset($this->_filesData[$fileNameHash]) || empty($this->_filesData[$fileNameHash])
        ) {
            $content = file_get_contents($file);
            $pattern = '/SQL\:\s((.+)\n)+\n/ui';

            preg_match_all($pattern, $content, $matches);
            $queriesRAW = $matches[0];
            $matches = array();
            $queries = $queriesByHash = array();
            $groupId = 0;
            $i = 0;
            foreach ($queriesRAW as $q) {
                $pattern = '/.+/ui';
                preg_match_all($pattern, $q, $matches);

                $queryTemp = array();

                foreach ($matches[0] as $m) {
                    if (preg_match('/(SQL|BIND|AFF|TIME)\:\s/u', $m, $parts)) {
                        $key = $parts[1];
                        $queryTemp[$key][] = $m;
                    } elseif (preg_match('/^\#.+/u', $m, $parts)) {
                        $queryTemp['TRACE'][] = trim($m);
                    } elseif (preg_match('/^\}$/u', $m, $parts)) {
                        $queryTemp['TRACE'][] = trim($m);
                    } elseif (preg_match('/^\s{1}[a-z].+/ui', $m, $parts)) {
                        $queryTemp['SQL'][] = trim($m);
                    } elseif (preg_match('/^\s{2}\`.+/ui', $m, $parts)) {
                        $queryTemp['SQL'][] = trim($m);
                    } elseif (preg_match('/^\s{2}INDEX.+/ui', $m, $parts)) {
                        $queryTemp['SQL'][] = trim($m);
                    } elseif (preg_match('/^\).+/ui', $m, $parts)) {
                        $queryTemp['SQL'][] = trim($m);
                    } elseif (preg_match('/^\s{2}.+/ui', $m, $parts)) {
                        $queryTemp['BIND'][] = trim($m);
                    } elseif (preg_match('/^\)/ui', $m, $parts)) {
                        $queryTemp['BIND'][] = trim($m);
                    } else {
                        $queryTemp['TRACE'][] = trim($m);
                    }

                }

                $sql = mb_strcut(implode(' ', $queryTemp['SQL']), 5);
                $hash = sha1($sql);

                $query = array(
                    'id' => $i,
                    'sql' => $sql,
                    'bind' => ((!empty($queryTemp['BIND'])) ? mb_strcut(implode(' ', $queryTemp['BIND']), 6) : ''),
                    'aff' => mb_strcut(implode('', $queryTemp['AFF']), 5),
                    'time' => mb_strcut(implode('', $queryTemp['TIME']), 6),
                    'trace' => implode("\n", $queryTemp['TRACE']),
                    'hash' => $hash
                );


                if (preg_match('/SET\sNAMES/', $query['sql'])) {
                    if ($queries) {
                        $groupId++;
                    }
                }

                if ($groupByConnect) {
                    $queries[$groupId][$i] = $query;
                    $queriesByHash[$groupId][$hash] = $query;
                } else {
                    $queries[$i] = $query;
                    $queriesByHash[$hash] = $query;
                }
                $i++;
            }

            $this->_filesData[$fileNameHash] = $queries;
            $this->_filesDataByHash[$fileNameHash] = $queriesByHash;
        }

        return $this->_filesData[$fileNameHash];
    }

    protected function _getQueryHashes ($queries)
    {
        $hashes = array();
        foreach ($queries as $q) {
            if (!empty($q['hash'])) {
                $hashes[] = $q['hash'];
            } elseif (isset($q['sql'])) {
                $hashes[] = sha1($q['sql']);
            }
        }
        return $hashes;
    }

    protected function _highlightSql($sql)
    {

        $sql = preg_replace('/^(?:SELECT|INSERT|UPDATE|DELETE)/ui', '<span class="sql-$0">$0</span>', $sql);
/*
        $sql = preg_replace('/^SELECT/', '<span class="sql-select">SELECT</span>', $sql);
        $sql = preg_replace('/^INSERT/', '<span class="sql-insert">INSERT</span>', $sql);
        $sql = preg_replace('/^UPDATE/', '<span class="sql-update">UPDATE</span>', $sql);
        $sql = preg_replace('/^DELETE/', '<span class="sql-delete">DELETE</span>', $sql);
*/
        return $sql;
    }

    public function run()
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


        echo '<div class="container" style="margin-top: 60px;">';

        $file0 = BP . DS . 'temp' . DS . 'log0.log';
        $file1 = BP . DS . 'temp' . DS . 'log1.log';

        $queries0 = $this->_parseLog($file0);
        $queries1 = $this->_parseLog($file1);

        $hashes0 = $this->_getQueryHashes($queries0);
        $hashes1 = $this->_getQueryHashes($queries1);

        $unique = $unique0 = $unique1 = array();
        $hashesDiffA = array_diff($hashes0, $hashes1);
        $hashesDiffR = array_diff($hashes1, $hashes0);
        $hashesDiff = array_merge($hashesDiffA, $hashesDiffR);

        $fileNameHash0 = $this->_getFileNameHash($file0);
        $fileNameHash1 = $this->_getFileNameHash($file1);

        foreach($hashesDiff as $h) {
            if (isset($this->_filesDataByHash[$fileNameHash0][$h]) && $this->_filesDataByHash[$fileNameHash0][$h]) {
                $u = $this->_filesDataByHash[$fileNameHash0][$h];
                $id = $u['id'];
                $u['float'] = 'left';
                $unique0[$id] = $u;
                $unique[$id] = $u;
            }
            if (isset($this->_filesDataByHash[$fileNameHash1][$h]) && $this->_filesDataByHash[$fileNameHash1][$h]) {
                $u = $this->_filesDataByHash[$fileNameHash1][$h];
                $id = $u['id'];
                $u['float'] = 'right';
                $unique1[$id] = $u;
                $unique[$id] = $u;
            }
        }

        foreach ($unique as $q) {
            echo '<div class="diff-item diff-logs diff-logs-'. $q['float'] . '">'
                . '<div>' . $this->_highlightSql($q['sql']) . '</div>'
                . '<div class="diff-logs-info">' .(($q['bind']) ? '<div>Bind: ' . $q['bind'] . '</div>' : '')
                . '<div>Affected: ' . $q['aff'] . '</div>'
                . '<div>Time: ' . $q['time'] . '</div>'
                . '</div></div>';
        }

        //var_dump($unique);
    }
}