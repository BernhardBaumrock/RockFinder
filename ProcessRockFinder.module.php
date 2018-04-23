<?php namespace ProcessWire;

class ProcessRockFinder extends Process {

  /**
   * init the module
   */
	public function init() {
		parent::init(); // always remember to call the parent init
	}

  /**
   * list all projects
   */
  public function ___execute() {
    $form = $this->modules->get('InputfieldForm');

    // if tester.txt does not exist create it from sample file
    if(!is_file($this->config->paths->assets . 'RockGrid/tester.txt')) {
      $this->files->copy(__DIR__ . '/exampleTester.php', $this->config->paths->assets . 'RockGrid/tester.txt');
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
      $ace->notes = "Execute on CTRL+ENTER or ALT+ENTER";
      $ace->notes .= "\nThe code must return either an SQL statement or a RockFinder instance";
      $f = $ace;
    }
    $f->name = 'code';
    $f->value = $code = $this->input->post->code ?: file_get_contents($this->config->paths->assets . 'RockGrid/tester.txt');
    $f->label = 'Code to execute';
    $form->add($f);

    // save code to file
    file_put_contents($this->config->paths->assets . 'RockGrid/tester.txt', $code);
    $search = ['<?php', 'new RockFinder'];
    $replace = ['//', 'new \ProcessWire\RockFinder'];
    $code = eval(str_replace($search, $replace, $code));

    $f = $this->modules->get('InputfieldRockGrid');
    $f->type = 'RockGrid';
    $f->label = 'Result';
    $f->name = 'result';    
    if($code instanceof RockFinder) {
      $finder = $code;
      // get code of this finder
      $code = $finder->getSQL();

      // enable debugging now the initial sql request is done
      $finder->debug = true;
      $f->setData($finder);
    }
    else {
      $f->sql = $code;
    }
    $form->add($f);

    $this->config->styles->add('//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.12.0/styles/default.min.css');
    $this->config->scripts->add('//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.12.0/highlight.min.js');
    $form->add([
      'type' => 'markup',
      'value' => "<pre><code>$code</code></pre>",
      'label' => 'Resulting SQL',
      'collapsed' => Inputfield::collapsedYes,
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

