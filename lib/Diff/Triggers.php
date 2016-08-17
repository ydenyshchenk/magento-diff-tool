<?php

class Diff_Triggers extends Diff_Abstract
{
    protected $_triggersList = false;
    protected $_mviewEvents = false;
    protected $_mviewEventIds = false;
    public $prefix = false;

    public function __construct($skipHtmlInit = false)
    {
        if (!$skipHtmlInit) {
            $class = '';
            if (!isset($_POST['diff']) || empty($_POST['diff'])) {
                $class = 'hello';
            }
            $this->_HTML = new Html('Triggers diff', $class);
        }
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
                    'statement' => $t->Statement,
                    'table' => $t->Table,
                    'timing' => $t->Timing
                );
            }
            $this->_triggersList[$db] = $triggers;
        }


        if (!empty($filter) && isset($this->_triggersList[$db][$filter]) && !empty($this->_triggersList[$db][$filter])) {
            return $this->_triggersList[$db][$filter];
        }

        return $this->_triggersList[$db];
    }

    protected function _detectPrefix($db)
    {
        $this->_db->query('use `' . $db . '`');
        $table = $this->_db->query("show tables like '%core_config_data';")->fetchColumn(0);
        $this->prefix = preg_replace('/core_config_data/', '', $table);

        return $this->prefix;
    }

    protected function _getMviewEvents($db, $getIds = false)
    {
        if (!isset($this->_mviewEvents[$db]) || !isset($this->_mviewEventIds[$db])) {
            $this->_db->query('use `' . $db . '`');
            $table = $this->_db->query("show tables like '%enterprise_mview_event';")->fetchColumn(0);
            if ($table == false) {
                return false;
            }
            $eventsRaw = $this->_db->query('SELECT * from ' . $table)->fetchAll(PDO::FETCH_OBJ);
            $events = array();
            $eventIds = array();
            foreach ($eventsRaw as $e) {
                $events[(int)$e->mview_event_id] = $e->name;
                $eventIds[$e->name] = (int)$e->mview_event_id;
            }
            ksort($events);
            $this->_mviewEvents[$db] = $events;
            $this->_mviewEventIds[$db] = $eventIds;
        }
        return ($getIds) ? $this->_mviewEventIds[$db] : $this->_mviewEvents[$db];
    }

    protected function _getMviewEventName($eventId, $db)
    {
        $events = $this->_getMviewEvents($db);
        return (!empty($events[$eventId])
            ? $events[$eventId] : false
        );
    }

    protected function _getMviewEventId($event, $db)
    {
        $eventIds = $this->_getMviewEvents($db, true);
        return (!empty($eventIds[$event])
            ? $eventIds[$event] : false
        );
    }

    public function renderForm($ee = '', $supee = '')
    {
        $entitiesEE = $this->_getDbList('ee');
        $entitiesSUPEE = $this->_getDbList('supee');

        $form = '<form method="post" action="' . BU . $this->tools['triggers']['url'] . '">Show trigger statements diff between ';
        $form .= $this->_renderSelect('trigger_ee', 'diff[ee]', $entitiesEE, $ee);
        $form .= ' and ';
        $form .= $this->_renderSelect('trigger_supee', 'diff[supee]', $entitiesSUPEE, $supee);
        $form .= ' <input type="submit" value="Submit" class="btn btn-primary"></form>';

        return $form;
    }

    protected function _renderForm($ee = '', $supee = '', $page = 'hello')
    {
        $form = $this->renderForm($ee, $supee);
        $html = $this->_renderToolbar($form, $page);
        return $html;
    }

    public function run($eeDb = '', $supeeDb = '', $prefix = '')
    {
        if (
            $_POST && isset($_POST['diff']) && !empty($_POST['diff'])
            && isset($_POST['diff']['ee']) && !empty($_POST['diff']['ee'])
            && isset($_POST['diff']['supee']) && !empty($_POST['diff']['supee'])
        ) {
            $d = $_POST['diff'];
            $eeDb = $d['ee'];
            $supeeDb = $d['supee'];
            $prefix = $this->_detectPrefix($supeeDb);
        }

        if (empty($d) || empty($eeDb) || empty($supeeDb)) {
            echo $this->_renderForm();
            exit();
        } elseif (!empty($eeDb) && !empty($supeeDb)) {
            echo $this->_renderForm($eeDb, $supeeDb, 'result');
        }

        $coreTriggers = $this->_getTriggersList($eeDb);
        $localTriggers = $this->_getTriggersList($supeeDb);
        $triggersMerged = array();
        foreach ($coreTriggers as $triggerName => $triggerData) {
            $triggerName = preg_replace("/trg_/", "trg_$prefix", $triggerName);
            $statement = $triggerData['statement'];
            $statement = preg_replace_callback('/(INTO|JOIN|UPDATE) \`([a-z\_]+)\`/', function($matches) use ($prefix) {
                return $matches[1] . ' `' . $prefix . $matches[2] . '`';
            }, $statement);
            $triggerData['table'] = $prefix . $triggerData['table'];
            $triggerData['statement'] = $statement;
            $triggersMerged[$triggerName]['core'] = $triggerData;
        }
        foreach ($localTriggers as $triggerName => $triggerData) {
            $triggersMerged[$triggerName]['local'] = $triggerData;
        }

        $missedTriggers = array();
        $corruptedTriggers = array();
        $triggersDiff = array();
        foreach ($triggersMerged as $triggerName => $t) {
            if (empty($t['local'])) {
                $missedTriggers[$triggerName] = $t['core'];
                $triggersDiff[$triggerName] = $t;
            } elseif (
                (!empty($t['core']['statement']) && $t['local']['statement'] != $t['core']['statement'])
                || empty($t['core']['statement'])
            ) {
                $corruptedTriggers[$triggerName] = $t;
                $triggersDiff[$triggerName] = $t;
            }
        }


        $html = '';
        if ($triggersDiff) {
            $html .= '<table class="table-bordered table-results">';
            $html .= '<thead><tr><th>Trigger</th><th>Event</th><th>Local statement (count: '
                . count($localTriggers) . ')</th><th>Default statement (count: ' . count($coreTriggers) . ')</th></tr></thead><tbody>';

            foreach ($triggersDiff as $code => $t) {
                $s = (!empty($t['local']['statement'])) ? $t['local']['statement'] : '';
                $cS = (!empty($t['core']['statement'])) ? $t['core']['statement'] : '';
                $event = (!empty($t['core']['event'])) ? $t['core']['event'] : '';

                if ($s == $cS) {
                    continue;
                }

                $html .= '<tr>';
                $html .= '<td>' . $code . '</td>'
                    . '<td>' . $event . '</td>'
                    . '<td style="color: ' . (($s != $cS) ? 'red' : '') . '">' . (($s) ? preg_replace('/\n/u', '<br>', $s) : 'MISSED') . '</td>'
                    . '<td>' . preg_replace('/\n/u', '<br>', $cS) . '</td>';

                $html .= '</tr>';
            }
            $html .= '</tbody></table>';


            if ($missedTriggers || $corruptedTriggers) {
                $html .= '<div class="well well-lg">';
                $html .= 'Triggers install script:';
                $html .= '<textarea style="width: 100%;" rows="100">delimiter //' . "\n";

                foreach ($missedTriggers as $triggerName => $t) {
                    $html .= $this->_triggerStatement($triggerName, $t);
                }

                foreach ($corruptedTriggers as $triggerName => $t) {
                    if (!empty($t['core'])) {
                        $html .= $this->_triggerStatement($triggerName, $t['core']);
                    }
                }

                $html .= '</textarea>';
                $html .= '</div>';
            }
        } else {
            $html .= '<div class="pT60"><div class="well well-lg"><h1 class="m0">Triggers are identical</h1></div></div>';
        }

        if (!empty($triggersMerged)) {
            $html .= '<div class="well well-lg">';
            $html .= 'All triggers install script:';
            $html .= '<textarea style="width: 100%;" rows="100">delimiter //' . "\n";
            foreach ($triggersMerged as $triggerName => $t) {
                if (!empty($t['core'])) {
                    $html .= $this->_triggerStatement($triggerName, $t['core']);
                }
            }
            $html .= '</textarea>';
            $html .= '</div>';
        }

        if (!empty($localTriggers)) {
            $html .= '<div class="well well-lg">';
            $html .= 'Delete all local triggers script:';
            $html .= '<textarea style="width: 100%;" rows="100">' . "\n";
            foreach ($localTriggers as $triggerName => $t) {
                $html .= 'DROP TRIGGER `' . $triggerName . '`;' . "\n";
            }
            $html .= '</textarea>';
            $html .= '</div>';
        }

        echo '<div class="container">' . $html . '</div>';
    }

    private function _triggerStatement($triggerName, $triggerData)
    {
        $html = '';
        $action = "DROP TRIGGER IF EXISTS `$triggerName`// CREATE";
        $tTime = $triggerData['timing'];
        $tEvent = $triggerData['event'];
        $tTable = $triggerData['table'];
        $tStatement = $triggerData['statement'];

        $tStatement = preg_replace_callback('/mview\_event\_id\s\=\s\'(\d+)\'/', function ($matches) {
            global $eeDb, $supeeDb;
            $coreEventId = (int)$matches[1];
            $mviewEvent = $this->_getMviewEventName($coreEventId, $eeDb);
            $eventId = $this->_getMviewEventId($mviewEvent, $supeeDb);

            return "mview_event_id = '$eventId'";
        }, $tStatement);

        $html .= "$action TRIGGER `$triggerName`\n$tTime $tEvent\nON `$tTable` FOR EACH ROW \n$tStatement//\n";
        return $html;
    }
}
