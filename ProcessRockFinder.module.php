<?php namespace ProcessWire;

class ProcessRockFinder extends Process {

  /**
   * init the module
   */
	public function init() {
		parent::init(); // always remember to call the parent init
	}

  /**
   * execute the tester interface
   */
  public function ___execute() {
    $form = $this->modules->get('InputfieldForm');

    // if reset parameter is set, add comments to tester.txt file
    $file = $this->config->paths->assets . 'RockGrid/tester.txt';
    if($this->input->get->reset) {
      $str = file_get_contents($file);
      file_put_contents($file, str_replace("\n", "\n// ", $str));
      $this->session->redirect('./');
    }

    // if tester.txt does not exist create it from sample file
    if(!is_file($file)) {
      $this->files->copy(__DIR__ . '/exampleTester.php', $file);
    }

    $f = $this->modules->get('InputfieldTextarea');
    if($ace = $this->modules->get('InputfieldAceExtended')) {
      $ace->rows = 10;
      $ace->theme = 'monokai';
      $ace->mode = 'php';
      $ace->setAdvancedOptions(array(
        'highlightActiveLine' => false,
        'showLineNumbers'     => false,
        'showGutter'          => false,
        'tabSize'             => 2,
        'printMarginColumn'   => false,
      ));
      $f = $ace;
    }
    
    $f->notes = "Execute on CTRL+ENTER or ALT+ENTER";
    $f->notes .= "\nThe code must return either an SQL statement or a RockFinder instance";
    if(!$ace) $f->notes .= "\nYou can install 'InputfieldAceExtended' for better code editing";
    
    $f->name = 'code';
    $f->value = $code = $this->input->post->code ?: file_get_contents($this->config->paths->assets . 'RockGrid/tester.txt');
    $f->label = 'Code to execute';
    $form->add($f);

    try {
      // save code to file
      file_put_contents($this->config->paths->assets . 'RockGrid/tester.txt', $code);
      $search = ['<?php', 'new RockFinder'];
      $replace = ['//', 'new \ProcessWire\RockFinder'];
      $code = eval(str_replace($search, $replace, $code));

      $f = $this->modules->get('InputfieldRockGrid');
      if($f) {
        $f->type = 'RockGrid';
        $f->label = 'Result';
        $f->name = 'ProcessRockFinderResult';
        $f->debug = true;
        if($code instanceof RockFinder) {
          $finder = $code;
          $finder->debug = true;
          // get code of this finder
          $code = $finder->getSQL();

          // enable debugging now the initial sql request is done
          $f->setData($finder);
        }
        else {
          // populate sql property of finder
          $f->sql = $code;
        }
        $form->add($f);
      }
    } catch (\Throwable $th) {
      $this->error($th->getMessage());
    }

    $this->config->styles->add('//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.12.0/styles/default.min.css');
    $this->config->scripts->add('//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.12.0/highlight.min.js');
    $form->add([
      'type' => 'markup',
      'value' => "<pre><code>$code</code></pre>",
      'label' => 'Resulting SQL',
      // 'collapsed' => Inputfield::collapsedYes,
    ]);
    
    $form->add([
      'type' => 'submit',
      'id' => 'submit',
      'value' => __('Execute SQL'),
      'icon' => 'bolt',
    ]);

    return $form->render();
  }

}

