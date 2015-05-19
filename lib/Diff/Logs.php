<?php

class Diff_Logs extends Diff_Abstract
{
    protected $_filesData = false;
    protected $_filesDataByHash = false;
    protected $_uploadedData = false;
    public function __construct($skipHtmlInit = false)
    {
        if (!$skipHtmlInit) {
            $class = '';
            if (!($files = $this->_getUploadedFiles())) {
                $class = 'hello';
            }
            $this->_HTML = new Html('Logs diff', $class);
        }
        parent::__construct();
        return true;
    }

    public function renderForm()
    {
        $form = '<form method="post" enctype="multipart/form-data" action="' . BU .$this->tools['logs']['url'] . '">Show logs diff '
            . '<input type="file" name="file0">'
            . '<input type="file" name="file1">';

        $form .= ' <input type="submit" value="Submit" class="btn btn-primary"></form>';

        return $form;
    }

    protected function _renderForm($page = 'hello')
    {
        $form = $this->renderForm();
        $html = $this->_renderToolbar($form, $page);
        return $html;
    }

    protected function _getFileNameHash($file)
    {
        return sha1($file);
    }

    protected function _parseLog($file, $groupByConnect = false)
    {
        if (empty($file)) {
            return array();
        }
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
        return $sql;
    }

    protected function _getUploadedFiles()
    {
        if (empty($_FILES) || empty($_FILES['file0']['tmp_name'])
            //|| empty($_FILES['file1']['tmp_name'])
        ) {
            return false;
        }
        if ($this->_uploadedData === false) {
            $this->_uploadedData = $_FILES;
        }
        return $this->_uploadedData;
    }

    protected function _unlinkUploadedFiles()
    {
        foreach($this->_uploadedData as $f) {
            if (!empty($f) && !empty($f['tmp_name'])) {
                unlink($f['tmp_name']);
            }
        }
    }

    public function run()
    {
        if ( !($files = $this->_getUploadedFiles()) ) {
            echo $this->_renderForm();
            exit();
        }

        echo $this->_renderForm('result');


        echo '<div class="container" style="margin-top: 60px;">';

        $file0 = (!empty($files['file0']['tmp_name'])) ? $files['file0']['tmp_name'] : '';
        $file1 = (!empty($files['file1']['tmp_name'])) ? $files['file1']['tmp_name'] : '';

        $queries0 = $this->_parseLog($file0);
        $queries1 = $this->_parseLog($file1);

        $this->_unlinkUploadedFiles();

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
                . '<div class="diff-logs-trace">' . preg_replace('/\n/', '<br>', $q['trace']) . '</div>'
                . '</div></div>';
        }

    }
}