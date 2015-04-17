<?php

class Diff extends Diff_Abstract
{
    public function __construct()
    {
        $this->_HTML = new Html('Home', 'hello');
        parent::__construct();
        return true;
    }

    public function run()
    {
        $buttons = '';
        $descriptions = '';
        $forms = '';
        foreach ($this->tools as $t) {
            $buttons .= '<a onclick="showForm(\'' . $t['class'] . '\', this);" class="btn btn-lg btn-default">' . $t['name'] . '</a> ';
            $descriptions .= '<strong>' . $t['name'] . '</strong> - ' . $t['desc'] . '<br/>';
            $$t['class'] = new $t['class']();
            $forms .= '<div id="diff-form-' . $t['class'] . '" class="lead diff-form">' . $$t['class']->renderForm() . '</div>';
        }


        $html = '    <div class="site-wrapper">
      <div class="site-wrapper-inner">
        <div class="cover-container">
          <div class="inner cover tCenter">
            <h1>Magento Debug Diff Tool <sup class="beta">beta</sup></h1>
            <h2 class="cover-heading">Magento Support</h2>
            <p class="lead">' . $buttons . '</p>
            <p class="lead tJustify diff-form active">' . $descriptions . '</p>
            ' . $forms . '
          </div>

          <div class="mastfoot">
            <div class="inner">
                <p>git clone <a href="https://github.com/ydenyshchenk/magento-diff-tool">https://github.com/ydenyshchenk/magento-diff-tool</a>.git .</p>
                <p>by <a href="mailto:ydenyshchenk@ebay.com">YuriyDenyshchenko</a>.</p>
            </div>
          </div>
        </div>
      </div>
    </div>';
        echo $html;
    }
}