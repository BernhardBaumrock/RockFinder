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
      $f = $ace;
    }
    $f->name = 'code';
    $f->value = $code = $this->input->post->code ?: file_get_contents($this->config->paths->assets . 'RockGrid/tester.txt');
    $f->label = 'SQL that gets executed';
    $form->add($f);

    // save code to file
    file_put_contents($this->config->paths->assets . 'RockGrid/tester.txt', $code);
    $sql = eval(str_replace('<?php', '//', $code));
    
    $form->add([
      'type' => 'RockGrid',
      'label' => 'Result',
      'name' => 'result',
      'sql' => $sql,
    ]);

    $this->config->styles->add('//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.12.0/styles/default.min.css');
    $this->config->scripts->add('//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.12.0/highlight.min.js');
    $form->add([
      'type' => 'markup',
      'value' => "<pre><code>$sql</code></pre><script>hljs.initHighlightingOnLoad();</script>",
      'label' => 'Resulting SQL',
      'collapsed' => Inputfield::collapsedYes,
    ]);
    
    $form->add([
      'type' => 'submit',
      'value' => __('Execute SQL'),
      'icon' => 'bolt',
    ]);

    return $form->render();
  }

}

