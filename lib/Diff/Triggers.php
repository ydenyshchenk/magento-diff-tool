<?php

class Diff_Triggers extends Diff_Abstract
{
    protected $_triggersList = false;

    public function __construct()
    {
        $class = '';
        if (!isset($_POST['diff']) || empty($_POST['diff'])) {$class = 'hello';}
        $this->_HTML = new Html('Triggers diff', $class);
        parent::__construct();
        return true;
    }

    protected function _getTriggersList($db, $filter = '')
    {
        if ($this->_triggersList === false || !isset($this->_triggersList[$db])) {
            $this->_db->query('use `' . $db . '`');
            $showTriggers = $this->_db->query('show triggers')->fetchAll(PDO::FETCH_OBJ);

            $triggers = array();
            foreach ($showTriggers as $t) {
                $triggers[$t->Trigger] = array(
                    'trigger' => $t->Trigger,
                    'event' => $t->Event,
                    'statement' => $t->Statement
                );
            }
            $this->_triggersList[$db] = $triggers;
        }


        if (!empty($filter) && isset($this->_triggersList[$db][$filter]) && !empty($this->_triggersList[$db][$filter])) {
            return $this->_triggersList[$db][$filter];
        }

        return $this->_triggersList[$db];
    }

    public function renderForm($ee = '', $supee = '')
    {
        $entitiesEE = $this->_getDbList('ee');
        $entitiesSUPEE = $this->_getDbList('supee');

        $form = '<form method="post" action="' . BU . $this->tools['triggers']['url'] . '">Show trigger statements diff between ';
        $form .= $this->_renderSelect('ee', 'diff[ee]', $entitiesEE, $ee);
        $form .= ' clean at ';
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

    public function run($eeVersion = '', $db = '', $prefix = '')
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

        $coreTriggers = $this->_getTriggersList($eeVersion);
        $triggers = $this->_getTriggersList($db);



        $html = '<table border="1" style="margin-top: 60px; font-size: 12px; line-height: 1.5;">';
        $html .= '<tr><td>trigger</td><td>event</td><td>statement ('
            . count($triggers) . ')</td><td>default statement (' . count($coreTriggers) . ')</td></tr>';

        foreach ($triggers as $code => $t) {
            $s = $t['statement'];
            $cS = ((isset($coreTriggers[$code]['statement']) && $coreTriggers[$code]['statement']) ? $coreTriggers[$code]['statement'] : '');

            if ($s == $cS) {
                continue;
            }

            $html .= '<tr>';
            $html .= '<td>' . $t['trigger'] . '</td>'
                . '<td>' . $t['event'] . '</td>'
                . '<td style="color: ' . (($s != $cS) ? 'red' : '') . '">' . preg_replace('/\n/u', '<br>', $t['statement']) . '</td>'
                . '<td>' . preg_replace('/\n/u', '<br>', $cS) . '</td>';

            $html .= '</tr>';
        }
        $html .= '';

        echo '<div class="container">' . $html . '</div>';
    }
}