$(document).ready(function() {
  hljs.initHighlightingOnLoad();
  

  // submit form on ctrl+enter
  $('#wrap_Inputfield_code').keydown(function (e) {
    if ((e.ctrlKey || e.altKey) && e.keyCode == 13) {
      $('#submit').click();
    }
  });

});

document.addEventListener('RockGridItemBeforeInit', function(e) {
  if(e.target.id != 'RockGridItem_ProcessRockFinderResult') return;
  var grid = RockGrid.getGrid(e.target.id);
  
  // overwrite rowactions for first column
  col = grid.getColDef('id');
  col.cellRenderer = function(params) {
    var grid = RockGrid.getGrid(params);
    // extend the current renderer and add custom icons
    return '<span>' + params.data.id + '</span>' + RockGrid.renderers.actionItems(params, [{
      icon: 'fa fa-search',
      href: ProcessWire.config.urls.admin + 'page/edit/?id=' + params.data.id,
      str: 'show',
      class: 'class="pw-panel"',
      target: 'target="_blank"',
    }]);
  }
});

document.addEventListener('RockGridButtons.beforeRender', function(e) {
  if(e.target.id != 'RockGridWrapper_ProcessRockFinderResult') return;
  var grid = RockGrid.getGrid(e.target);
  var plugin = grid.plugins.buttons;

  // remove a btton
  plugin.buttons.remove('refresh');
});