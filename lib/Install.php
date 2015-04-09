<?php

class Install
{
    protected $_HTML = null;
    protected $_form = array(
        'base_url' => 'Base url: http://diff.local/',
        'scan_dir' => 'Projects root dir for scan: /var/www/',
        'ee_prefix' => 'Prefix for EE projects: ee-',
        'supee_prefix' => 'Prefix for SUPEE project dir: supee-',
        'core_paths' => 'app/code/core, app/design/, lib/',
        'db_host' => 'DB hostname',
        'db_user' => 'DB user',
        'db_pass' => 'DB password',
        'db_ee_prefix' => 'DB prefix for EE DB names: ee-',
        'db_supee_prefix' => 'DB prefix for SUPEE DB names: supee-',
    );

    public function __construct()
    {
        $this->_HTML = new Html('Install', 'hello');
    }

    protected function _renderForm()
    {
        $html = '<form method="post">';
        foreach ($this->_form as $name => $description) {
            $html .= ' <div class="input-group mb10"><span class="input-group-addon" id="basic-addon1">'
                . $name . '</span><input type="text" name="i[' . $name . ']" class="form-control" placeholder="'
                . $description . '" aria-describedby="basic-addon1"></div>';
        }
        $html .= '<div class="lead"><input type="submit" value="Install" class="btn btn-lg btn-primary"></div>';
        $html .= '</form>';
        return $html;

    }

    public function run()
    {
        if (!empty($_POST['i'])) {
            $formData = $_POST['i'];

            $dist = file_get_contents(BP . DS . 'etc' . DS . 'config.php.dist');

            $pattern = array();
            $replacement = array();
            foreach($formData as $k => $v) {
                if ($k == 'core_paths') {
                    $v = "'" . preg_replace(array('/\s/', '/\,/'), array('', "', '"), $v) . "'";
                }
                $pattern[] = '/\{\{' . $k . '\}\}/';
                $replacement[] = $v;
            }
            $dist = preg_replace($pattern, $replacement, $dist);
            $result = @file_put_contents(BP . DS . 'etc' . DS . 'config.php', $dist);
            if ($result) {
                header('Location: ' . $formData['base_url']);
            }
            return $result;
        }

        $html = '    <div class="site-wrapper">
      <div class="site-wrapper-inner">
        <div class="cover-container">
          <div class="inner cover tCenter">
            <h1>Magento Debug Diff Tool</h1>
            <h2 class="cover-heading">Configuration</h2>
            <p class="lead">' . $this->_renderForm() . '</p>
          </div>

          <div class="mastfoot">
            <div class="inner">
              <p>by <a href="mailto:ydenyshchenk@ebay.com">YuriyDenyshchenko</a>.</p>
            </div>
          </div>
        </div>
      </div>
    </div>';
        echo $html;
    }
}